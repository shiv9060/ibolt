<?php
declare(strict_types=1);

/**
 * Checkout Controller & Address Pipeline
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
    error_log("[iboltnetworks] checkout.php DB error: " . $e->getMessage());
    render_controlled_error_page("Checkout Temporarily Unavailable", "Our checkout service is temporarily undergoing maintenance. Please message our sales team on WhatsApp at +91 99110 03865 to complete your order directly.", 503);
    exit;
}

// Redirect if cart is empty
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

$isLoggedIn = is_logged_in();
$currentUser = $isLoggedIn ? current_user() : null;

// Fetch authoritative cart items & verify live inventory
$productIds = array_keys($_SESSION['cart']);
$inPlaceholders = implode(',', array_fill(0, count($productIds), '?'));

$sql = "SELECT p.id, p.title, p.sku, p.price_paise, p.stock_quantity, p.status,
               b.name AS brand_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.id IN ({$inPlaceholders})";

$stmt = $db->prepare($sql);
$stmt->execute($productIds);
$productsDb = $stmt->fetchAll();

if (empty($productsDb)) {
    $_SESSION['cart'] = [];
    header("Location: cart.php");
    exit;
}

$cartItems = [];
$subtotalPaise = 0;
$hasStockIssue = false;
$stockIssueMessage = '';

foreach ($productsDb as $p) {
    if ($p['status'] !== 'published') {
        $hasStockIssue = true;
        $stockIssueMessage = "Item \"{$p['title']}\" is no longer available.";
        break;
    }

    $qty = (int)($_SESSION['cart'][$p['id']]['quantity'] ?? 1);
    if ($qty > (int)$p['stock_quantity']) {
        $hasStockIssue = true;
        $stockIssueMessage = "Requested quantity for \"{$p['title']}\" exceeds available inventory ({$p['stock_quantity']} units).";
        break;
    }

    $lineTotal = (int)$p['price_paise'] * $qty;
    $subtotalPaise += $lineTotal;

    $cartItems[] = [
        'id'          => (int)$p['id'],
        'title'       => $p['title'],
        'sku'         => $p['sku'],
        'brand_name'  => $p['brand_name'] ?? 'iboltnetworks',
        'image'       => $p['primary_image'] ?? 'images/server-hud.png',
        'unit_price'  => (int)$p['price_paise'],
        'quantity'    => $qty,
        'line_total'  => $lineTotal,
    ];
}

$shippingPaise = 0; // Free pan-India shipping
$totalPaise = $subtotalPaise + $shippingPaise;

$error = $hasStockIssue ? $stockIssueMessage : '';

// Handle Checkout Submission (Creates Pending Order & Proceeds to Payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasStockIssue) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please submit again.';
    } else {
        $name     = clean_string($_POST['name'] ?? '');
        $email    = strtolower(clean_string($_POST['email'] ?? ''));
        $phone    = clean_string($_POST['phone'] ?? '');
        $address1 = clean_string($_POST['address1'] ?? '');
        $address2 = clean_string($_POST['address2'] ?? '');
        $city     = clean_string($_POST['city'] ?? '');
        $state    = clean_string($_POST['state'] ?? '');
        $pincode  = clean_string($_POST['pincode'] ?? '');
        $notes    = clean_string($_POST['notes'] ?? '');

        if (empty($name) || strlen($name) < 2) {
            $error = 'Please enter your full contact name.';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address for order notifications.';
        } elseif (empty($phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 10) {
            $error = 'Please provide a valid 10-digit phone number for delivery coordination.';
        } elseif (empty($address1) || strlen($address1) < 5) {
            $error = 'Please provide a complete shipping address (street, building, or premises).';
        } elseif (empty($city)) {
            $error = 'Please enter the delivery city.';
        } elseif (empty($state)) {
            $error = 'Please select your delivery state / union territory.';
        } elseif (empty($pincode) || strlen(preg_replace('/[^0-9]/', '', $pincode)) !== 6) {
            $error = 'Please enter a valid 6-digit Indian Postal PIN code.';
        } else {
            try {
                $db->beginTransaction();

                // Lock inventory rows to avoid race conditions
                foreach ($cartItems as $item) {
                    $lockStmt = $db->prepare("SELECT stock_quantity, status FROM products WHERE id = ? FOR UPDATE");
                    $lockStmt->execute([$item['id']]);
                    $liveProduct = $lockStmt->fetch();

                    if (!$liveProduct || $liveProduct['status'] !== 'published' || (int)$liveProduct['stock_quantity'] < $item['quantity']) {
                        $db->rollBack();
                        throw new RuntimeException("Inventory changed for \"{$item['title']}\". Please return to cart and update your selection.");
                    }
                }

                // Save address snapshot
                $addressSnapshot = [
                    'full_name'      => $name,
                    'email'          => $email,
                    'phone'          => $phone,
                    'address_line1'  => $address1,
                    'address_line2'  => $address2,
                    'city'           => $city,
                    'state'          => $state,
                    'postal_code'    => $pincode,
                    'country'        => 'India'
                ];

                $addrStmt = $db->prepare("INSERT INTO addresses (user_id, full_name, phone, email, address_line1, address_line2, city, state, postal_code, country)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'India')");
                $addrStmt->execute([
                    $isLoggedIn ? $currentUser['id'] : null,
                    $name,
                    $phone,
                    $email,
                    $address1,
                    $address2,
                    $city,
                    $state,
                    $pincode
                ]);
                $addressId = (int)$db->lastInsertId();

                // Generate unique order number (e.g. IBN-20261024-XXXX)
                $orderNumber = 'IBN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

                // Insert into orders table
                $orderInsert = $db->prepare("INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, shipping_address_id, shipping_address_snapshot, status, payment_status, payment_method, currency, subtotal_paise, shipping_paise, tax_paise, total_paise, customer_notes)
                                             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', 'whatsapp', 'INR', ?, ?, 0, ?, ?)");
                $orderInsert->execute([
                    $orderNumber,
                    $isLoggedIn ? $currentUser['id'] : null,
                    $name,
                    $email,
                    $phone,
                    $addressId,
                    json_encode($addressSnapshot),
                    $subtotalPaise,
                    $shippingPaise,
                    $totalPaise,
                    $notes
                ]);
                $orderId = (int)$db->lastInsertId();

                // Insert line items
                $itemInsert = $db->prepare("INSERT INTO order_items (order_id, product_id, product_title, sku, price_paise, quantity, total_paise)
                                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($cartItems as $item) {
                    $itemInsert->execute([
                        $orderId,
                        $item['id'],
                        $item['title'],
                        $item['sku'],
                        $item['unit_price'],
                        $item['quantity'],
                        $item['line_total']
                    ]);
                }

                $db->commit();

                // Empty the shopping cart
                $_SESSION['cart'] = [];

                // Build WhatsApp order message with complete product and customer information
                $orderRecord = [
                    'order_number'   => $orderNumber,
                    'customer_name'  => $name,
                    'customer_phone' => $phone,
                    'customer_email' => $email,
                    'customer_notes' => $notes,
                    'total_paise'    => $totalPaise
                ];
                $waMessage = build_order_whatsapp_message($orderRecord, $cartItems, $addressSnapshot);
                $whatsappUrl = build_whatsapp_url($waMessage);

                $_SESSION['last_order_number'] = $orderNumber;
                $_SESSION['last_whatsapp_url'] = $whatsappUrl;

                // Redirect directly to WhatsApp with product information
                header("Location: " . $whatsappUrl);
                exit;

            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    }
}

$pageTitle = "WhatsApp Checkout | iboltnetworks";
$pageDescription = "Place your hardware order directly on WhatsApp with our certified solution architects.";

$extraHead = <<<'HTML'
<style>
  .checkout-wrapper { background: #f6f8fa; padding: 40px 0 80px; min-height: 650px; }
  .checkout-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 32px;
    align-items: start;
  }
  .checkout-card {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 12px;
    padding: 32px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
  }
  .checkout-card h2 {
    font-size: 20px;
    font-weight: 800;
    color: #1e293b;
    margin: 0 0 20px;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 12px;
  }
  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
  }
  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; font-size: 13.5px; font-weight: 700; color: #334155; margin-bottom: 6px; }
  .form-control {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
    color: #1e293b;
    background: #ffffff;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  .form-control:focus {
    border-color: #078e49;
    box-shadow: 0 0 0 3px rgba(7, 142, 73, 0.15);
  }

  /* Order Summary Sidebar */
  .summary-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
  }
  .summary-item-img {
    width: 48px;
    height: 48px;
    object-fit: contain;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 4px;
    background: #f8fafc;
  }
  .summary-item-desc { flex: 1; }
  .summary-item-title { font-weight: 600; color: #1e293b; display: block; line-height: 1.3; }
  .summary-item-qty { font-size: 12px; color: #64748b; }
  .summary-item-price { font-weight: 700; color: #0f172a; }

  .summary-line {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #475569;
    margin-bottom: 10px;
  }
  .summary-line.total {
    border-top: 2px solid #e2e8f0;
    padding-top: 14px;
    margin-top: 14px;
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
  }

  .btn-submit-order {
    width: 100%;
    background: #078e49;
    color: #ffffff;
    font-size: 16px;
    font-weight: 700;
    padding: 14px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 16px;
  }
  .btn-submit-order:hover { background: #06773d; }

  .alert-error {
    background: #fef2f2;
    border: 1px solid #fee2e2;
    color: #991b1b;
    padding: 14px 18px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 24px;
    line-height: 1.5;
  }

  @media (max-width: 900px) {
    .checkout-layout { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
  }
</style>
HTML;

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navigation.php';
?>

<div class="checkout-wrapper">
  <div class="catalog-container">

    <div style="margin-bottom: 24px;">
      <a href="cart.php" style="color: #078e49; font-weight: 700; text-decoration: none; font-size: 14px;">
        ← Back to Shopping Cart
      </a>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="checkout-layout">
      <!-- Shipping & Customer Form -->
      <form method="post" action="checkout.php" id="checkout-form">
        <?= csrf_field() ?>

        <div class="checkout-card">
          <h2>1. Contact Information</h2>
          <div class="form-row">
            <div class="form-group">
              <label for="name">Full Name *</label>
              <input type="text" id="name" name="name" class="form-control" value="<?= e($_POST['name'] ?? $currentUser['name'] ?? '') ?>" required placeholder="e.g. Rahul Sharma">
            </div>
            <div class="form-group">
              <label for="phone">Phone / Mobile (10 Digits) *</label>
              <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $currentUser['phone'] ?? '') ?>" required placeholder="9876543210">
            </div>
          </div>
          <div class="form-group">
            <label for="email">Email Address (for order receipts &amp; dispatch alerts) *</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? $currentUser['email'] ?? '') ?>" required placeholder="rahul@company.com">
          </div>

          <h2 style="margin-top: 36px;">2. Shipping &amp; Delivery Address</h2>
          <div class="form-group">
            <label for="address1">Street Address, Premise, or Tech Park *</label>
            <input type="text" id="address1" name="address1" class="form-control" value="<?= e($_POST['address1'] ?? '') ?>" required placeholder="Plot No. 42, Electronics City Phase 1">
          </div>
          <div class="form-group">
            <label for="address2">Floor, Suite, or Landmark (Optional)</label>
            <input type="text" id="address2" name="address2" class="form-control" value="<?= e($_POST['address2'] ?? '') ?>" placeholder="3rd Floor, Tower B">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="city">City / District *</label>
              <input type="text" id="city" name="city" class="form-control" value="<?= e($_POST['city'] ?? '') ?>" required placeholder="Bengaluru">
            </div>
            <div class="form-group">
              <label for="state">State / Union Territory *</label>
              <select id="state" name="state" class="form-control" required>
                <option value="">Select State</option>
                <?php
                $states = [
                    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
                    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
                    'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
                    'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
                    'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
                    'Delhi', 'Chandigarh', 'Puducherry', 'Jammu & Kashmir'
                ];
                $selectedState = $_POST['state'] ?? 'Karnataka';
                foreach ($states as $st) {
                    $sel = ($selectedState === $st) ? 'selected' : '';
                    echo "<option value=\"" . e($st) . "\" {$sel}>" . e($st) . "</option>";
                }
                ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="pincode">Postal PIN Code (6 Digits) *</label>
            <input type="text" id="pincode" name="pincode" maxlength="6" class="form-control" value="<?= e($_POST['pincode'] ?? '') ?>" required placeholder="560100">
          </div>

          <h2 style="margin-top: 36px;">3. Order Notes &amp; B2B GSTIN (Optional)</h2>
          <div class="form-group">
            <label for="notes">Delivery instructions or company GSTIN for tax invoice</label>
            <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="Optional notes for delivery team or GSTIN number for tax input credit..."><?= e($_POST['notes'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="btn-submit-order" style="background: #25D366; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 16.5px; font-weight: 700; border-radius: 8px;">
            <svg style="width: 22px; height: 22px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.06-2.072-.511-1.636-.679-2.73-2.316-2.812-2.426-.082-.109-.661-.88-.661-1.677 0-.797.413-1.189.56-1.348.146-.16.32-.2.427-.2.106 0 .213 0 .306.006.1.006.233-.038.365.279.133.319.453 1.106.493 1.187.04.08.067.174.013.281-.053.107-.08.174-.16.267-.08.093-.167.208-.239.28-.08.08-.163.167-.07.327.094.16.417.688.894 1.114.614.547 1.132.717 1.292.797.16.08.254.067.347-.04.094-.107.4-.467.507-.627.107-.16.213-.133.36-.08.147.053.934.44 1.094.52.16.08.267.12.307.187.04.066.04.386-.104.791z"/></svg>
            Send Order to WhatsApp (<?= format_inr($totalPaise) ?>) →
          </button>
          <div style="margin-top: 14px; font-size: 13px; color: #64748b; text-align: center; line-height: 1.5;">
            📲 <strong>No online payment required.</strong> Clicking this button records your order and immediately opens our verified WhatsApp with your selected hardware items and delivery address pre-filled.
          </div>
        </div>
      </form>

      <!-- Order Summary Card -->
      <aside class="checkout-card">
        <h2>Order Summary (<?= count($cartItems) ?> items)</h2>

        <div style="margin-bottom: 20px;">
          <?php foreach ($cartItems as $item): ?>
            <div class="summary-item">
              <img src="<?= e($item['image']) ?>" alt="<?= e($item['title']) ?>" class="summary-item-img">
              <div class="summary-item-desc">
                <span class="summary-item-title"><?= e($item['title']) ?></span>
                <span class="summary-item-qty">Qty: <?= $item['quantity'] ?></span>
              </div>
              <span class="summary-item-price"><?= format_inr($item['line_total']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="summary-line">
          <span>Subtotal</span>
          <span><?= format_inr($subtotalPaise) ?></span>
        </div>
        <div class="summary-line">
          <span>Insured Pan-India Freight</span>
          <span style="color: #078e49; font-weight: 700;">FREE</span>
        </div>
        <div class="summary-line">
          <span>GST / Taxes</span>
          <span>Included</span>
        </div>
        <div class="summary-line total">
          <span>Total Amount</span>
          <span><?= format_inr($totalPaise) ?></span>
        </div>

        <div style="margin-top: 20px; padding: 14px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; font-size: 12.5px; color: #166534; line-height: 1.5;">
          💬 <strong>WhatsApp Order Assistance</strong><br>
          We currently take orders directly via WhatsApp! When you submit, our solution architects will immediately review your hardware specifications, confirm live inventory, provide tax invoices, and schedule courier dispatch.
        </div>
      </aside>
    </div>

  </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
