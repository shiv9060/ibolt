<?php
declare(strict_types=1);

/**
 * Product Image Management & Secure Upload Helper
 * Advanced MIME verification, decoding checks, derivatives, shared image protection,
 * atomic transactions, and library health checks.
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/sanitize.php';

const ALLOWED_IMAGE_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif'
];

const MAX_IMAGE_FILE_SIZE = 8 * 1024 * 1024; // 8MB
const MIN_IMAGE_DIMENSION = 20;               // 20px min width/height
const MAX_IMAGE_DIMENSION = 6000;             // 6000px max width/height

/**
 * Validate an uploaded image file thoroughly before processing.
 *
 * @param array $file $_FILES entry
 * @return array ['valid' => bool, 'error' => string, 'mime' => string, 'ext' => string, 'width' => int, 'height' => int]
 */
function validate_image_file(array $file): array
{
    $isUploaded = is_uploaded_file($file['tmp_name'] ?? '') || (PHP_SAPI === 'cli' && !empty($file['tmp_name']) && file_exists($file['tmp_name']));
    if (empty($file['tmp_name']) || !$isUploaded) {
        return ['valid' => false, 'error' => 'No valid uploaded file found.', 'mime' => '', 'ext' => '', 'width' => 0, 'height' => 0];
    }

    // Check PHP upload error code
    $errCode = $file['error'] ?? UPLOAD_ERR_OK;
    if ($errCode !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'Image exceeds server upload_max_filesize limit (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE  => 'Image exceeds form MAX_FILE_SIZE limit.',
            UPLOAD_ERR_PARTIAL    => 'Image was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No image file was received.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write image file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A server extension blocked the image upload.'
        ];
        return ['valid' => false, 'error' => $messages[$errCode] ?? 'Upload error code: ' . $errCode, 'mime' => '', 'ext' => '', 'width' => 0, 'height' => 0];
    }

    // Enforce size limit
    if ($file['size'] > MAX_IMAGE_FILE_SIZE) {
        return ['valid' => false, 'error' => 'Image size exceeds maximum limit of 8MB.', 'mime' => '', 'ext' => '', 'width' => 0, 'height' => 0];
    }

    if ($file['size'] <= 0) {
        return ['valid' => false, 'error' => 'Uploaded file is empty (0 bytes).', 'mime' => '', 'ext' => '', 'width' => 0, 'height' => 0];
    }

    // Server-side MIME verification using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mime, ALLOWED_IMAGE_MIMES)) {
        return ['valid' => false, 'error' => 'Invalid image format (' . htmlspecialchars((string)$mime) . '). Only JPG, PNG, WebP, and GIF are permitted.', 'mime' => '', 'ext' => '', 'width' => 0, 'height' => 0];
    }

    // Strict image content and decoding verification via getimagesize
    $imgDetails = @getimagesize($file['tmp_name']);
    if ($imgDetails === false) {
        return ['valid' => false, 'error' => 'Malformed or corrupted image content. File structure cannot be decoded.', 'mime' => '', 'ext' => '', 'width' => 0, 'height' => 0];
    }

    $detectedMime = $imgDetails['mime'] ?? '';
    if ($detectedMime !== $mime) {
        return ['valid' => false, 'error' => 'Image content structure (' . htmlspecialchars($detectedMime) . ') does not match file MIME signature (' . htmlspecialchars($mime) . ').', 'mime' => '', 'ext' => '', 'width' => 0, 'height' => 0];
    }

    $width = (int)$imgDetails[0];
    $height = (int)$imgDetails[1];

    if ($width < MIN_IMAGE_DIMENSION || $height < MIN_IMAGE_DIMENSION) {
        return ['valid' => false, 'error' => "Image dimensions ({$width}x{$height}px) are too small. Minimum required is " . MIN_IMAGE_DIMENSION . 'x' . MIN_IMAGE_DIMENSION . 'px.', 'mime' => '', 'ext' => '', 'width' => 0, 'height' => 0];
    }

    if ($width > MAX_IMAGE_DIMENSION || $height > MAX_IMAGE_DIMENSION) {
        return ['valid' => false, 'error' => "Image dimensions ({$width}x{$height}px) exceed maximum limit of " . MAX_IMAGE_DIMENSION . 'x' . MAX_IMAGE_DIMENSION . 'px.', 'mime' => '', 'ext' => '', 'width' => 0, 'height' => 0];
    }

    // Attempt GD stream decode to ensure no memory bomb / corrupt stream
    if (extension_loaded('gd')) {
        $gdTest = null;
        switch ($mime) {
            case 'image/jpeg':
                $gdTest = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $gdTest = @imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/webp':
                $gdTest = @imagecreatefromwebp($file['tmp_name']);
                break;
            case 'image/gif':
                $gdTest = @imagecreatefromgif($file['tmp_name']);
                break;
        }
        if ($gdTest === false || $gdTest === null) {
            return ['valid' => false, 'error' => 'Image data stream could not be decoded. File may be truncated or corrupt.', 'mime' => '', 'ext' => '', 'width' => 0, 'height' => 0];
        }
        imagedestroy($gdTest);
    }

    return [
        'valid'  => true,
        'error'  => '',
        'mime'   => $mime,
        'ext'    => ALLOWED_IMAGE_MIMES[$mime],
        'width'  => $width,
        'height' => $height
    ];
}

/**
 * Generate a responsive thumbnail derivative preserving aspect ratio and quality.
 *
 * @param string $sourceRelativePath Relative path from webroot (e.g. uploads/products/foo.jpg)
 * @param int $maxWidth
 * @param int $maxHeight
 * @param int $quality
 * @return string|null Relative thumbnail path or null if unavailable
 */
function generate_image_derivative(string $sourceRelativePath, int $maxWidth = 400, int $maxHeight = 400, int $quality = 85): ?string
{
    if (!extension_loaded('gd')) {
        return null;
    }

    $baseDir = dirname(__DIR__, 2);
    $fullSource = $baseDir . '/' . $sourceRelativePath;
    if (!file_exists($fullSource) || !is_file($fullSource)) {
        return null;
    }

    $thumbDir = $baseDir . '/uploads/products/thumbs';
    if (!is_dir($thumbDir)) {
        @mkdir($thumbDir, 0755, true);
    }

    $filename = basename($sourceRelativePath);
    $fullDest = $thumbDir . '/thumb_' . $filename;
    $relativeDest = 'uploads/products/thumbs/thumb_' . $filename;

    $imageInfo = @getimagesize($fullSource);
    if (!$imageInfo) {
        return null;
    }

    $origWidth = (int)$imageInfo[0];
    $origHeight = (int)$imageInfo[1];
    $mime = $imageInfo['mime'] ?? '';

    if ($origWidth <= 0 || $origHeight <= 0) {
        return null;
    }

    // Do not upscale small source images
    if ($origWidth <= $maxWidth && $origHeight <= $maxHeight) {
        $scale = 1.0;
    } else {
        $scale = min($maxWidth / $origWidth, $maxHeight / $origHeight);
    }
    $newWidth = max(1, (int)round($origWidth * $scale));
    $newHeight = max(1, (int)round($origHeight * $scale));

    $sourceImg = null;
    switch ($mime) {
        case 'image/jpeg':
            $sourceImg = @imagecreatefromjpeg($fullSource);
            break;
        case 'image/png':
            $sourceImg = @imagecreatefrompng($fullSource);
            break;
        case 'image/webp':
            $sourceImg = @imagecreatefromwebp($fullSource);
            break;
        case 'image/gif':
            $sourceImg = @imagecreatefromgif($fullSource);
            break;
        default:
            return null;
    }

    if (!$sourceImg) {
        return null;
    }

    $thumbImg = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG, WebP, and GIF
    if ($mime === 'image/png' || $mime === 'image/webp' || $mime === 'image/gif') {
        imagealphablending($thumbImg, false);
        imagesavealpha($thumbImg, true);
        $transparent = imagecolorallocatealpha($thumbImg, 255, 255, 255, 127);
        imagefilledrectangle($thumbImg, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($thumbImg, $sourceImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    $saved = false;
    switch ($mime) {
        case 'image/jpeg':
            $saved = @imagejpeg($thumbImg, $fullDest, $quality);
            break;
        case 'image/png':
            $saved = @imagepng($thumbImg, $fullDest, 6);
            break;
        case 'image/webp':
            $saved = @imagewebp($thumbImg, $fullDest, $quality);
            break;
        case 'image/gif':
            $saved = @imagegif($thumbImg, $fullDest);
            break;
    }

    imagedestroy($sourceImg);
    imagedestroy($thumbImg);

    return $saved ? $relativeDest : null;
}

/**
 * Return thumbnail derivative path if exists, otherwise fallback to original path.
 */
function get_product_thumbnail_path(string $imagePath): string
{
    if (!str_starts_with($imagePath, 'uploads/products/')) {
        return $imagePath;
    }
    $filename = basename($imagePath);
    $thumbPath = 'uploads/products/thumbs/thumb_' . $filename;
    $fullThumbPath = dirname(__DIR__, 2) . '/' . $thumbPath;
    if (file_exists($fullThumbPath)) {
        return $thumbPath;
    }
    return $imagePath;
}

/**
 * Securely validate and store an uploaded image file for a product.
 *
 * @param int $productId
 * @param array $file Single $_FILES entry
 * @param bool $isPrimary
 * @param int $sortOrder
 * @param string|null $altText
 * @return array ['success' => bool, 'image_id' => int, 'path' => string, 'thumb_path' => string, 'error' => string]
 */
function upload_product_image(int $productId, array $file, bool $isPrimary = false, int $sortOrder = 0, ?string $altText = null): array
{
    $validation = validate_image_file($file);
    if (!$validation['valid']) {
        return ['success' => false, 'image_id' => 0, 'path' => '', 'thumb_path' => '', 'error' => $validation['error']];
    }

    $db = Database::getConnection();

    // Verify product exists
    $prodCheck = $db->prepare("SELECT id, title FROM products WHERE id = ? LIMIT 1");
    $prodCheck->execute([$productId]);
    $prod = $prodCheck->fetch(PDO::FETCH_ASSOC);
    if (!$prod) {
        return ['success' => false, 'image_id' => 0, 'path' => '', 'thumb_path' => '', 'error' => 'Target product ID does not exist.'];
    }

    $ext = $validation['ext'];
    $uploadDir = dirname(__DIR__, 2) . '/uploads/products';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    // Cryptographically secure, unguessable filename
    $safeName = sprintf('prod_%d_%s_%s.%s', $productId, date('Ymd_His'), bin2hex(random_bytes(6)), $ext);
    $destination = $uploadDir . '/' . $safeName;
    $relativePath = 'uploads/products/' . $safeName;

    $moved = is_uploaded_file($file['tmp_name'])
        ? @move_uploaded_file($file['tmp_name'], $destination)
        : @copy($file['tmp_name'], $destination);

    if (!$moved) {
        return ['success' => false, 'image_id' => 0, 'path' => '', 'thumb_path' => '', 'error' => 'Failed to save uploaded image to destination directory.'];
    }

    // Generate thumbnail derivative
    $thumbRelative = generate_image_derivative($relativePath);
    $thumbDest = $thumbRelative ? (dirname(__DIR__, 2) . '/' . $thumbRelative) : null;

    $manageTx = !$db->inTransaction();
    if ($manageTx) {
        $db->beginTransaction();
    }

    try {
        // Check if product currently has any primary image
        $hasPrimaryStmt = $db->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = 1");
        $hasPrimaryStmt->execute([$productId]);
        $hasPrimary = ((int)$hasPrimaryStmt->fetchColumn() > 0);

        // If no primary image exists, automatically mark this one as primary
        if (!$hasPrimary) {
            $isPrimary = true;
        }

        // If this image is primary, reset any other primary flags for this product
        if ($isPrimary) {
            $db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$productId]);
        }

        // Sort order calculation
        if ($sortOrder === 0) {
            $maxOrderStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?");
            $maxOrderStmt->execute([$productId]);
            $sortOrder = (int)$maxOrderStmt->fetchColumn() + 1;
        }

        $defaultAlt = $altText ?: ($prod['title'] . ' - image');
        $insert = $db->prepare("INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order)
                               VALUES (?, ?, ?, ?, ?)");
        $insert->execute([
            $productId,
            $relativePath,
            $defaultAlt,
            $isPrimary ? 1 : 0,
            $sortOrder
        ]);

        $newId = (int)$db->lastInsertId();

        if ($manageTx) {
            $db->commit();
        }

        return [
            'success'    => true,
            'image_id'   => $newId,
            'path'       => $relativePath,
            'thumb_path' => $thumbRelative ?: $relativePath,
            'error'      => ''
        ];
    } catch (Throwable $e) {
        if ($manageTx && $db->inTransaction()) {
            $db->rollBack();
        }
        // Cleanup newly created files on database failure
        if (file_exists($destination)) {
            @unlink($destination);
        }
        if ($thumbDest && file_exists($thumbDest)) {
            @unlink($thumbDest);
        }
        return [
            'success'    => false,
            'image_id'   => 0,
            'path'       => '',
            'thumb_path' => '',
            'error'      => 'Database transaction failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle batch multiple file uploads for a product.
 *
 * @param int $productId
 * @param array $files Normalized array of uploaded files
 * @return array Array of upload results
 */
function upload_multiple_product_images(int $productId, array $files): array
{
    $results = [];
    $isFirst = true;

    $db = Database::getConnection();
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
    $cntStmt->execute([$productId]);
    $existingCount = (int)$cntStmt->fetchColumn();

    // Normalize multiple file structure
    $normalized = [];
    if (isset($files['name']) && is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            if (empty($files['name'][$i])) continue;
            $normalized[] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i]
            ];
        }
    } else {
        $normalized = $files;
    }

    $order = $existingCount + 1;
    foreach ($normalized as $f) {
        $makePrimary = ($existingCount === 0 && $isFirst);
        $res = upload_product_image($productId, $f, $makePrimary, $order++);
        $results[] = $res;
        $isFirst = false;
    }

    return $results;
}

/**
 * Set an image as primary for a product with strict ownership and transaction handling.
 */
function set_primary_product_image(int $productId, int $imageId): bool
{
    $db = Database::getConnection();

    // Verify ownership
    $chkStmt = $db->prepare("SELECT id FROM product_images WHERE id = ? AND product_id = ? LIMIT 1");
    $chkStmt->execute([$imageId, $productId]);
    if (!$chkStmt->fetch()) {
        return false;
    }

    $manageTx = !$db->inTransaction();
    if ($manageTx) {
        $db->beginTransaction();
    }
    try {
        $db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$productId]);
        $stmt = $db->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?");
        $stmt->execute([$imageId, $productId]);
        if ($manageTx) {
            $db->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($manageTx && $db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

/**
 * Delete a product image safely.
 * Promotes replacement primary image if needed.
 * Strictly prevents unlinking shared images used elsewhere.
 */
function delete_product_image(int $imageId, ?int $productId = null): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT * FROM product_images WHERE id = ? LIMIT 1");
    $stmt->execute([$imageId]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$img) {
        return ['success' => false, 'error' => 'Image record not found.'];
    }

    $actualProductId = (int)$img['product_id'];
    if ($productId !== null && $actualProductId !== $productId) {
        return ['success' => false, 'error' => 'Image does not belong to specified product.'];
    }

    $wasPrimary = ((int)$img['is_primary'] === 1);
    $filePath = (string)$img['image_path'];

    $manageTx = !$db->inTransaction();
    if ($manageTx) {
        $db->beginTransaction();
    }
    try {
        // Delete record from DB
        $db->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imageId]);

        // If it was primary, promote next available image
        if ($wasPrimary) {
            $nextStmt = $db->prepare("SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
            $nextStmt->execute([$actualProductId]);
            $nextId = $nextStmt->fetchColumn();
            if ($nextId) {
                $db->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ?")->execute([$nextId]);
            }
        } else {
            // Verify there is still at least one primary image if images remain
            $chkPrimary = $db->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = 1");
            $chkPrimary->execute([$actualProductId]);
            if ((int)$chkPrimary->fetchColumn() === 0) {
                $nextStmt = $db->prepare("SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
                $nextStmt->execute([$actualProductId]);
                $nextId = $nextStmt->fetchColumn();
                if ($nextId) {
                    $db->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ?")->execute([$nextId]);
                }
            }
        }

        if ($manageTx) {
            $db->commit();
        }

        // Shared-file protection: Only unlink if inside uploads/ AND no other records reference it!
        if (str_starts_with($filePath, 'uploads/')) {
            $refStmt = $db->prepare("SELECT COUNT(*) FROM product_images WHERE image_path = ?");
            $refStmt->execute([$filePath]);
            if ((int)$refStmt->fetchColumn() === 0) {
                $fullPath = dirname(__DIR__, 2) . '/' . $filePath;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                $thumbPath = get_product_thumbnail_path($filePath);
                $fullThumb = dirname(__DIR__, 2) . '/' . $thumbPath;
                if ($thumbPath !== $filePath && file_exists($fullThumb)) {
                    @unlink($fullThumb);
                }
            }
        }

        return ['success' => true, 'error' => ''];
    } catch (Throwable $e) {
        if ($manageTx && $db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete multiple product images safely in batch.
 */
function delete_multiple_product_images(int $productId, array $imageIds): array
{
    $deletedCount = 0;
    $errors = [];
    foreach ($imageIds as $imgId) {
        $imgId = (int)$imgId;
        if ($imgId <= 0) continue;
        $res = delete_product_image($imgId, $productId);
        if ($res['success']) {
            $deletedCount++;
        } else {
            $errors[] = "Image #{$imgId}: " . $res['error'];
        }
    }
    return [
        'success' => ($deletedCount > 0 || empty($errors)),
        'count'   => $deletedCount,
        'errors'  => $errors
    ];
}

/**
 * Replace an existing product image with a new file.
 * Validates ownership, uploads new file, updates record, and safely protects shared files.
 */
function replace_product_image(int $imageId, array $file, ?int $productId = null): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT * FROM product_images WHERE id = ? LIMIT 1");
    $stmt->execute([$imageId]);
    $oldImg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldImg) {
        return ['success' => false, 'error' => 'Image not found.'];
    }

    $actualProductId = (int)$oldImg['product_id'];
    if ($productId !== null && $actualProductId !== $productId) {
        return ['success' => false, 'error' => 'Image does not belong to specified product.'];
    }

    $oldFilePath = (string)$oldImg['image_path'];
    $isPrimary = ((int)$oldImg['is_primary'] === 1);
    $sortOrder = (int)$oldImg['sort_order'];
    $altText = $oldImg['alt_text'];

    // Upload new image first
    $newRes = upload_product_image($actualProductId, $file, $isPrimary, $sortOrder, $altText);
    if (!$newRes['success']) {
        return $newRes;
    }

    $manageTx = !$db->inTransaction();
    if ($manageTx) {
        $db->beginTransaction();
    }
    try {
        // Delete old image record
        $db->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imageId]);

        if ($manageTx) {
            $db->commit();
        }

        // Shared file protection: Unlink old file ONLY if in uploads/ AND not referenced anywhere else!
        if (str_starts_with($oldFilePath, 'uploads/')) {
            $refStmt = $db->prepare("SELECT COUNT(*) FROM product_images WHERE image_path = ?");
            $refStmt->execute([$oldFilePath]);
            if ((int)$refStmt->fetchColumn() === 0) {
                $fullPath = dirname(__DIR__, 2) . '/' . $oldFilePath;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                $thumbPath = get_product_thumbnail_path($oldFilePath);
                $fullThumb = dirname(__DIR__, 2) . '/' . $thumbPath;
                if ($thumbPath !== $oldFilePath && file_exists($fullThumb)) {
                    @unlink($fullThumb);
                }
            }
        }

        return $newRes;
    } catch (Throwable $e) {
        if ($manageTx && $db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update sort order for product images with ownership and transaction safety.
 */
function update_product_images_order(int $productId, array $orderMap): void
{
    $db = Database::getConnection();
    $manageTx = !$db->inTransaction();
    if ($manageTx) {
        $db->beginTransaction();
    }
    try {
        $stmt = $db->prepare("UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?");
        foreach ($orderMap as $imageId => $order) {
            $stmt->execute([(int)$order, (int)$imageId, $productId]);
        }
        if ($manageTx) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($manageTx && $db->inTransaction()) {
            $db->rollBack();
        }
    }
}

/**
 * Comprehensive read-only audit of the product image library and filesystem integrity.
 *
 * @return array
 */
function audit_image_library(): array
{
    $db = Database::getConnection();
    $baseDir = dirname(__DIR__, 2);

    // 1. Missing files (in DB but not on disk)
    $stmt = $db->query("SELECT pi.id, pi.product_id, pi.image_path, pi.is_primary, pi.alt_text, p.title AS product_title, p.sku AS product_sku
                        FROM product_images pi
                        LEFT JOIN products p ON pi.product_id = p.id
                        ORDER BY pi.id ASC");
    $dbImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $missingFiles = [];
    $validDbImagesCount = 0;
    $dbPaths = [];

    foreach ($dbImages as $img) {
        $fullPath = $baseDir . '/' . $img['image_path'];
        $dbPaths[] = $img['image_path'];
        if (!file_exists($fullPath)) {
            $missingFiles[] = $img;
        } else {
            $validDbImagesCount++;
        }
    }

    // 2. Orphaned files in uploads/products/ (not in DB)
    $uploadsDir = $baseDir . '/uploads/products';
    $orphanedFiles = [];
    if (is_dir($uploadsDir)) {
        $files = scandir($uploadsDir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === 'thumbs') continue;
            $rel = 'uploads/products/' . $f;
            $full = $uploadsDir . '/' . $f;
            if (is_file($full) && !in_array($rel, $dbPaths, true)) {
                $orphanedFiles[] = [
                    'relative_path' => $rel,
                    'filename'      => $f,
                    'size'          => filesize($full),
                    'size_formatted'=> round(filesize($full) / 1024, 1) . ' KB',
                    'modified'      => date('Y-m-d H:i:s', filemtime($full))
                ];
            }
        }
    }

    // 3. Products without images
    $noImgStmt = $db->query("SELECT p.id, p.title, p.sku, p.price_paise, p.status, c.name AS category_name
                             FROM products p
                             LEFT JOIN product_images pi ON p.id = pi.product_id
                             LEFT JOIN categories c ON p.category_id = c.id
                             WHERE pi.id IS NULL
                             ORDER BY p.id ASC");
    $productsWithoutImages = $noImgStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Shared / duplicate file references across records
    $sharedStmt = $db->query("SELECT image_path, COUNT(*) AS usage_count, GROUP_CONCAT(product_id) AS product_ids
                              FROM product_images
                              GROUP BY image_path
                              HAVING usage_count > 1
                              ORDER BY usage_count DESC");
    $sharedImages = $sharedStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Products with multiple primary images or no primary image
    $multiPrimaryStmt = $db->query("SELECT product_id, COUNT(*) AS primary_count
                                    FROM product_images
                                    WHERE is_primary = 1
                                    GROUP BY product_id
                                    HAVING primary_count > 1");
    $productsWithMultiPrimary = $multiPrimaryStmt->fetchAll(PDO::FETCH_ASSOC);

    $noPrimaryStmt = $db->query("SELECT DISTINCT product_id
                                 FROM product_images
                                 WHERE product_id NOT IN (
                                     SELECT product_id FROM product_images WHERE is_primary = 1
                                 )");
    $productsWithNoPrimary = $noPrimaryStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'total_db_records'              => count($dbImages),
        'missing_files'                 => $missingFiles,
        'missing_files_count'           => count($missingFiles),
        'orphaned_files'                => $orphanedFiles,
        'orphaned_files_count'          => count($orphanedFiles),
        'products_without_images'       => $productsWithoutImages,
        'products_without_images_count' => count($productsWithoutImages),
        'shared_images'                 => $sharedImages,
        'shared_images_count'           => count($sharedImages),
        'multi_primary_anomalies'       => $productsWithMultiPrimary,
        'no_primary_anomalies'          => $productsWithNoPrimary,
        'is_healthy'                    => (count($missingFiles) === 0 && count($productsWithMultiPrimary) === 0 && count($productsWithNoPrimary) === 0)
    ];
}

/**
 * Safely delete confirmed orphaned files after user review and explicit confirmation.
 *
 * @param array $relativePaths
 * @return array
 */
function cleanup_orphaned_files(array $relativePaths): array
{
    $db = Database::getConnection();
    $baseDir = dirname(__DIR__, 2);
    $deleted = [];
    $failed = [];

    foreach ($relativePaths as $rel) {
        $rel = trim(str_replace('\\', '/', $rel));
        // Strict path safety check: must start with uploads/products/ and no .. traversal
        if (!str_starts_with($rel, 'uploads/products/') || str_contains($rel, '..')) {
            $failed[] = "Security check failed for path: {$rel}";
            continue;
        }

        // Verify that this path is truly NOT in DB
        $chk = $db->prepare("SELECT COUNT(*) FROM product_images WHERE image_path = ?");
        $chk->execute([$rel]);
        if ((int)$chk->fetchColumn() > 0) {
            $failed[] = "File {$rel} is actively referenced by a database record; deletion aborted.";
            continue;
        }

        $fullPath = $baseDir . '/' . $rel;
        if (file_exists($fullPath) && is_file($fullPath)) {
            if (@unlink($fullPath)) {
                $deleted[] = $rel;
                // If thumbnail exists, clean it up too
                $thumbPath = get_product_thumbnail_path($rel);
                $fullThumb = $baseDir . '/' . $thumbPath;
                if ($thumbPath !== $rel && file_exists($fullThumb)) {
                    @unlink($fullThumb);
                }
            } else {
                $failed[] = "Could not unlink {$rel} (filesystem permission issue).";
            }
        }
    }

    return [
        'success'       => count($deleted) > 0 || empty($failed),
        'deleted_count' => count($deleted),
        'deleted'       => $deleted,
        'failed'        => $failed
    ];
}
