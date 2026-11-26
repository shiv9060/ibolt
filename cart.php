<?php
declare(strict_types=1);

/**
 * Shopping Cart Controller & View
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/helpers/csrf.php';
require_once __DIR__ . '/app/helpers/sanitize.php';
require_once __DIR__ . '/app/helpers/currency.php';
require_once __DIR__ . '/app/helpers/whatsapp.php';

init_session();

try {
    $db = Database::getConnection();
} catch (Throwable $e) {
    error_log("[iboltnetworks] cart.php DB error: " . $e->getMessage());
    if ((isset($_GET['format']) && $_GET['format'] === 'json') ||
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Cart service temporarily unavailable.']);
        exit;
    }
    render_controlled_error_page("Shopping Cart Unavailable", "Our cart service is temporarily undergoing maintenance. Please message our technical sales desk on WhatsApp at +91 99110 03865 to complete your order directly.", 503);
    exit;
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$isJson = (isset($_GET['format']) && $_GET['format'] === 'json') ||
          (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

$action = clean_string($_POST['action'] ?? $_GET['action'] ?? 'view');
$flashSuccess = '';
$flashError = '';

// Handle Cart Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Security token invalid or expired.']);
            exit;
        }
        $flashError = 'Security token invalid or expired. Please try again.';
    } else {
        switch ($action) {
            case 'add':
                $productId = sanitize_int($_POST['product_id'] ?? 0);
                $quantity  = max(1, sanitize_int($_POST['quantity'] ?? 1, 1));
                $buyNow    = !empty($_POST['buy_now']);

                if ($productId <= 0) {
                    $flashError = 'Invalid product selected.';
                    break;
                }

                // Query database for authoritative stock and status
                $stmt = $db->prepare("SELECT id, title, sku, price_paise, stock_quantity, status FROM products WHERE id = ? LIMIT 1");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();

                if (!$product || $product['status'] !== 'published') {
                    $flashError = 'The selected item is no longer available.';
                    break;
                }

                $availableStock = (int)$product['stock_quantity'];
                $currentCartQty = (int)($_SESSION['cart'][$productId]['quantity'] ?? 0);
                $newQty = $currentCartQty + $quantity;

                if ($availableStock <= 0) {
                    $flashError = 'Sorry, this hardware model is currently out of stock.';
                    break;
                }

                if ($newQty > $availableStock) {
                    $newQty = $availableStock;
                    $flashError = "Quantity adjusted to maximum available stock ({$availableStock} units).";
                }

                $_SESSION['cart'][$productId] = [
                    'product_id' => $productId,
                    'quantity'   => $newQty
                ];

                if ($isJson) {
                    $totalItems = 0;
                    foreach ($_SESSION['cart'] as $item) {
                        $totalItems += (int)($item['quantity'] ?? 1);
                    }
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success'     => true,
                        'message'     => 'Added to cart successfully.',
                        'total_items' => $totalItems
                    ]);
                    exit;
                }

                if ($buyNow) {
                    header("Location: checkout.php");
                    exit;
                }

                $flashSuccess = "Added \"{$product['title']}\" to your cart.";
                break;

            case 'update':
                $productId = sanitize_int($_POST['product_id'] ?? 0);
                $quantity  = sanitize_int($_POST['quantity'] ?? 1);

                if ($productId > 0) {
                    if ($quantity <= 0) {
                        unset($_SESSION['cart'][$productId]);
                        $flashSuccess = 'Item removed from your cart.';
                    } else {
                        // Check stock limit
                        $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                        $stmt->execute([$productId]);
                        $stock = (int)$stmt->fetchColumn();

                        if ($quantity > $stock) {
                            $quantity = max(1, $stock);
                            $flashError = "Quantity capped at available inventory ({$stock} units).";
                        }
                        $_SESSION['cart'][$productId]['quantity'] = $quantity;
                        $flashSuccess = 'Cart updated successfully.';
                    }
                }
                break;

            case 'remove':
                $productId = sanitize_int($_POST['product_id'] ?? 0);
                if ($productId > 0 && isset($_SESSION['cart'][$productId])) {
                    unset($_SESSION['cart'][$productId]);
                    $flashSuccess = 'Item removed from your cart.';
                }
                break;

            case 'clear':
                $_SESSION['cart'] = [];
                $flashSuccess = 'Your shopping cart has been emptied.';
                break;
        }
    }
}

// Build Authoritative Cart Items List from Database
$cartItems = [];
$cartSubtotalPaise = 0;

if (!empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $inPlaceholders = implode(',', array_fill(0, count($productIds), '?'));

    $sql = "SELECT p.id, p.title, p.slug, p.sku, p.price_paise, p.stock_quantity, p.condition_type,
                   b.name AS brand_name,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id IN ({$inPlaceholders})";

    $stmt = $db->prepare($sql);
    $stmt->execute($productIds);
    $productsDb = $stmt->fetchAll();

    foreach ($productsDb as $p) {
        $qty = (int)($_SESSION['cart'][$p['id']]['quantity'] ?? 1);
        // Ensure quantity doesn't exceed database stock
        $qty = min($qty, max(1, (int)$p['stock_quantity']));
        $lineTotalPaise = (int)$p['price_paise'] * $qty;
        $cartSubtotalPaise += $lineTotalPaise;

        $cartItems[] = [
            'id'             => (int)$p['id'],
            'title'          => $p['title'],
            'slug'           => $p['slug'],
            'sku'            => $p['sku'],
            'brand_name'     => $p['brand_name'] ?? 'iboltnetworks',
            'condition_type' => $p['condition_type'],
            'image'          => $p['primary_image'] ?? 'images/server-hud.png',
            'unit_price'     => (int)$p['price_paise'],
            'quantity'       => $qty,
            'line_total'     => $lineTotalPaise,
            'max_stock'      => (int)$p['stock_quantity']
        ];
    }
}

$pageTitle = "Shopping Cart | iboltnetworks";
$pageDescription = "Review hardware items, enterprise servers, and networking accessories in your shopping cart.";

$quickWhatsappCartUrl = !empty($cartItems) ? build_whatsapp_url(build_cart_whatsapp_message($cartItems, $cartSubtotalPaise)) : '#';

$extraHead = <<<'HTML'
<style>
  .cart-wrapper { background: #f6f8fa; padding: 40px 0 80px; min-height: 620px; }
  .cart-container { width: min(var(--content-width), calc(100% - 48px)); margin: 0 auto; }
  
  .cart-header { margin-bottom: 28px; }
  .cart-header h1 { font-size: 28px; font-weight: 800; color: #111827; margin: 0 0 6px; }
  .cart-header p { font-size: 14.5px; color: #64748b; margin: 0; }

  .cart-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 32px;
    align-items: start;
  }

  /* Cart Table Card */
  .cart-card {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
  }
  .cart-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
  }
  .cart-table th {
    text-align: left;
    padding: 12px 14px;
    color: #475569;
    font-weight: 700;
    border-bottom: 2px solid #edf2f6;
    background: #f8fafc;
  }
  .cart-table td {
    padding: 18px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
  }

  .cart-item-info {
    display: flex;
    align-items: center;
    gap: 16px;
  }
  .cart-item-img {
    width: 72px;
    height: 72px;
    object-fit: contain;
    background: #fbfcfd;
    border: 1px solid #edf1f4;
    border-radius: 8px;
    padding: 6px;
  }
  .cart-item-title {
    font-weight: 700;
    color: #111;
    text-decoration: none;
    line-height: 1.35;
    display: block;
    margin-bottom: 4px;
  }
  .cart-item-title:hover { color: #078e49; }
  .cart-item-sku { font-size: 12px; color: #64748b; }

  .cart-qty-form {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .cart-qty-input {
    width: 52px;
    height: 34px;
    text-align: center;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 700;
    outline: none;
  }
  .btn-cart-update {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    padding: 7px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
  }
  .btn-cart-update:hover { background: #e2e8f0; }

  .btn-remove-item {
    background: none;
    border: none;
    color: #ef4444;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    padding: 4px;
  }
  .btn-remove-item:hover { text-decoration: underline; }

  /* Summary Card */
  .summary-card {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
  }
  .summary-card h2 { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0 0 20px; }
  .summary-line {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #475569;
    margin-bottom: 12px;
  }
  .summary-line.total {
    border-top: 2px solid #e2e8f0;
    padding-top: 16px;
    margin-top: 16px;
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
  }

  .btn-checkout {
    display: block;
    width: 100%;
    background: #078e49;
    color: #ffffff;
    text-align: center;
    padding: 14px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
    margin-top: 20px;
    transition: background 0.2s;
    box-sizing: border-box;
  }
  .btn-checkout:hover { background: #06773d; }

  .cart-clear-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
  }
  .btn-clear-cart {
    background: none;
    border: none;
    color: #64748b;
    font-size: 13px;
    cursor: pointer;
  }
  .btn-clear-cart:hover { color: #dc2626; text-decoration: underline; }

  /* Empty Cart */
  .empty-cart-card {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 12px;
    padding: 60px 24px;
    text-align: center;
    max-width: 600px;
    margin: 0 auto;
  }
  .empty-cart-icon { font-size: 48px; margin-bottom: 16px; display: block; }
  .empty-cart-card h2 { font-size: 22px; color: #1e293b; margin: 0 0 8px; }
  .empty-cart-card p { font-size: 14.5px; color: #64748b; margin: 0 0 24px; line-height: 1.5; }

  .alert {
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 13.5px;
    margin-bottom: 20px;
  }
  .alert-success { background: #f0fdf4; border: 1px solid #dcfce7; color: #166534; }
  .alert-error { background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; }

  @media (max-width: 900px) {
    .cart-layout { grid-template-columns: 1fr; }
  }
</style>
HTML;

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navigation.php';
?>

<div class="cart-wrapper">
  <div class="cart-container">

    <div class="cart-header">
      <h1>Shopping Cart</h1>
      <p>Review and adjust your hardware selection before secure checkout</p>
    </div>

    <?php if (!empty($flashSuccess)): ?>
      <div class="alert alert-success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
      <div class="alert alert-error"><?= e($flashError) ?></div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
      <div class="empty-cart-card">
        <span class="empty-cart-icon">🛒</span>
        <h2>Your Shopping Cart is Empty</h2>
        <p>Explore our servers, enterprise network switches, DDR4/DDR5 components, and computer accessories to find what you need.</p>
        <a href="products.php" class="btn-quote" style="display: inline-block; background: #078e49; color: #fff; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: 700;">
          Continue Shopping
        </a>
      </div>
    <?php else: ?>
      <div class="cart-layout">
        <!-- Cart Items List -->
        <div class="cart-card">
          <table class="cart-table">
            <thead>
              <tr>
                <th>Hardware Item</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cartItems as $item): ?>
                <tr>
                  <td>
                    <div class="cart-item-info">
                      <img src="<?= e($item['image']) ?>" alt="<?= e($item['title']) ?>" class="cart-item-img">
                      <div>
                        <a href="product.php?slug=<?= e($item['slug']) ?>" class="cart-item-title"><?= e($item['title']) ?></a>
                        <span class="cart-item-sku">SKU: <?= e($item['sku']) ?> • Brand: <?= e($item['brand_name']) ?></span>
                      </div>
                    </div>
                  </td>
                  <td><strong><?= format_inr($item['unit_price']) ?></strong></td>
                  <td>
                    <form action="cart.php" method="post" class="cart-qty-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                      <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['max_stock'] ?>" class="cart-qty-input">
                      <button type="submit" class="btn-cart-update" title="Update Quantity">Update</button>
                    </form>
                  </td>
                  <td><strong><?= format_inr($item['line_total']) ?></strong></td>
                  <td>
                    <form action="cart.php" method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="remove">
                      <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                      <button type="submit" class="btn-remove-item" title="Remove item">✕</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="cart-clear-bar">
            <a href="products.php" style="color: #078e49; font-weight: 700; text-decoration: none; font-size: 13.5px;">
              ← Continue Shopping
            </a>

            <form action="cart.php" method="post" onsubmit="return confirm('Are you sure you want to empty your entire cart?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="clear">
              <button type="submit" class="btn-clear-cart">Clear Entire Cart</button>
            </form>
          </div>
        </div>

        <!-- Order Summary Card -->
        <div class="summary-card">
          <h2>Order Summary</h2>
          <div class="summary-line">
            <span>Subtotal</span>
            <span><?= format_inr($cartSubtotalPaise) ?></span>
          </div>
          <div class="summary-line">
            <span>Shipping &amp; Freight</span>
            <span style="color: #078e49; font-weight: 700;">FREE</span>
          </div>
          <div class="summary-line">
            <span>GST &amp; Taxes</span>
            <span>Included in Price</span>
          </div>
          <div class="summary-line total">
            <span>Total Payable</span>
            <span><?= format_inr($cartSubtotalPaise) ?></span>
          </div>

          <a href="<?= e($quickWhatsappCartUrl) ?>" target="_blank" rel="noopener noreferrer" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: #25D366; color: #ffffff; text-align: center; padding: 14px; border-radius: 8px; font-weight: 700; font-size: 15px; text-decoration: none; margin-top: 18px; box-sizing: border-box; transition: background 0.2s;">
            <svg style="width: 20px; height: 20px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.06-2.072-.511-1.636-.679-2.73-2.316-2.812-2.426-.082-.109-.661-.88-.661-1.677 0-.797.413-1.189.56-1.348.146-.16.32-.2.427-.2.106 0 .213 0 .306.006.1.006.233-.038.365.279.133.319.453 1.106.493 1.187.04.08.067.174.013.281-.053.107-.08.174-.16.267-.08.093-.167.208-.239.28-.08.08-.163.167-.07.327.094.16.417.688.894 1.114.614.547 1.132.717 1.292.797.16.08.254.067.347-.04.094-.107.4-.467.507-.627.107-.16.213-.133.36-.08.147.053.934.44 1.094.52.16.08.267.12.307.187.04.066.04.386-.104.791z"/></svg>
            Order via WhatsApp (Instant) →
          </a>

          <a href="checkout.php" class="btn-checkout" style="margin-top: 10px; background: #078e49;">
            Add Shipping Address &amp; Order →
          </a>

          <div style="margin-top: 18px; font-size: 12px; color: #64748b; text-align: center; line-height: 1.5;">
            💬 Direct WhatsApp Checkout • No online payment required
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
