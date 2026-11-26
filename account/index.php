<?php
declare(strict_types=1);

/**
 * Customer Account Dashboard
 * iboltnetworks E-Commerce Platform
 */

$rootPath = '../';

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/sanitize.php';
require_once __DIR__ . '/../app/helpers/currency.php';

init_session();
require_login('../login.php');

$user = current_user();
$db = Database::getConnection();

// Fetch customer stats
$orderCountStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$orderCountStmt->execute([$user['id']]);
$totalOrders = (int)$orderCountStmt->fetchColumn();

// Fetch recent 5 orders
$recentOrdersStmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$recentOrdersStmt->execute([$user['id']]);
$recentOrders = $recentOrdersStmt->fetchAll();

$pwdError = '';
$pwdSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $pwdError = 'Security session expired. Please try again.';
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $pwdError = 'Please fill in all password fields.';
        } elseif (strlen($newPassword) < 8) {
            $pwdError = 'New password must be at least 8 characters in length.';
        } elseif ($newPassword !== $confirmPassword) {
            $pwdError = 'New password and confirmation do not match.';
        } else {
            $uStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$user['id']]);
            $currentHash = $uStmt->fetchColumn();

            if (!$currentHash || !password_verify($currentPassword, $currentHash)) {
                $pwdError = 'Your current password was entered incorrectly.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $upStmt->execute([$newHash, $user['id']]);
                $pwdSuccess = 'Your password has been updated successfully!';
            }
        }
    }
}


$pageTitle = "My Account Dashboard | iboltnetworks";
$pageDescription = "Manage your iboltnetworks account, view past server orders, and check tracking status.";

$extraHead = <<<'HTML'
<style>
  .dashboard-wrapper { background: #f6f8fa; padding: 40px 0 80px; min-height: 600px; }
  .dashboard-container { width: min(var(--content-width), calc(100% - 48px)); margin: 0 auto; }
  
  .account-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 32px;
  }
  
  /* Sidebar Nav */
  .account-nav {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
    height: fit-content;
  }
  .user-badge {
    text-align: center;
    padding-bottom: 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid #f1f5f9;
  }
  .user-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #078e49;
    color: #ffffff;
    display: grid;
    place-items: center;
    font-size: 24px;
    font-weight: 800;
    margin: 0 auto 12px;
  }
  .user-badge h3 { font-size: 16px; margin: 0 0 4px; color: #1e293b; }
  .user-badge p { font-size: 13px; color: #64748b; margin: 0; }

  .account-menu { list-style: none; padding: 0; margin: 0; }
  .account-menu li { margin-bottom: 6px; }
  .account-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: 8px;
    color: #334155;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
  }
  .account-menu a:hover { background: #f1f5f9; color: #078e49; }
  .account-menu a.active { background: #078e49; color: #ffffff; }
  .account-menu a.logout { color: #dc2626; margin-top: 14px; border-top: 1px solid #f1f5f9; padding-top: 14px; }
  .account-menu a.logout:hover { background: #fef2f2; }

  /* Dashboard Content */
  .dashboard-main { display: flex; flex-direction: column; gap: 24px; }
  
  .welcome-card {
    background: linear-gradient(135deg, #078e49 0%, #056633 100%);
    color: #ffffff;
    border-radius: 12px;
    padding: 30px 36px;
    box-shadow: 0 4px 20px rgba(7,142,73,0.15);
  }
  .welcome-card h1 { margin: 0 0 6px; font-size: 26px; font-weight: 800; }
  .welcome-card p { margin: 0; font-size: 14.5px; opacity: 0.94; }

  .stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
  }
  .stat-card {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 10px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
  }
  .stat-card .stat-label { font-size: 12.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
  .stat-card .stat-value { font-size: 28px; font-weight: 800; color: #0f172a; margin-top: 6px; }

  .card-box {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
  }
  .card-box h2 {
    font-size: 18px;
    font-weight: 800;
    color: #1e293b;
    margin: 0 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .card-box h2 a { font-size: 13px; color: #078e49; font-weight: 700; text-decoration: none; }
  .card-box h2 a:hover { text-decoration: underline; }

  .table-responsive { width: 100%; overflow-x: auto; }
  .order-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
    text-align: left;
  }
  .order-table th {
    padding: 12px 14px;
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    border-bottom: 2px solid #edf2f6;
  }
  .order-table td {
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
  }
  
  .badge-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
  }
  .status-paid { background: #dcfce7; color: #166534; }
  .status-unpaid { background: #fef3c7; color: #92400e; }
  .status-pending { background: #f1f5f9; color: #475569; }
  .status-processing { background: #e0f2fe; color: #0369a1; }
  .status-shipped { background: #ede9fe; color: #6d28d9; }
  .status-delivered { background: #dcfce7; color: #166534; }

  @media (max-width: 900px) {
    .account-grid { grid-template-columns: 1fr; }
    .stats-row { grid-template-columns: 1fr; }
  }
</style>
HTML;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<div class="dashboard-wrapper">
  <div class="catalog-container">

    <div class="account-grid">
      <!-- Sidebar Navigation -->
      <aside class="account-nav">
        <div class="user-badge">
          <div class="user-avatar">
            <?= strtoupper(substr((string)($user['name'] ?? 'U'), 0, 1)) ?>
          </div>
          <h3><?= e($user['name'] ?? 'Customer') ?></h3>
          <p><?= e($user['email'] ?? '') ?></p>
        </div>

        <ul class="account-menu">
          <li><a href="index.php" class="active">📊 Dashboard</a></li>
          <li><a href="orders.php">📦 Order History</a></li>
          <li><a href="#security-settings">🔒 Security &amp; Password</a></li>
          <li><a href="../products.php">🛒 Browse Hardware</a></li>
          <li><a href="../logout.php" class="logout">🚪 Sign Out</a></li>
        </ul>
      </aside>

      <!-- Main Dashboard Content -->
      <main class="dashboard-main">
        <div class="welcome-card">
          <h1>Welcome, <?= e(explode(' ', (string)$user['name'])[0]) ?>!</h1>
          <p>Here you can monitor pending server orders, inspect invoices, and track hardware deliveries.</p>
        </div>

        <div class="stats-row">
          <div class="stat-card">
            <span class="stat-label">Total Orders</span>
            <span class="stat-value"><?= $totalOrders ?></span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Account Status</span>
            <span class="stat-value" style="color: #078e49; font-size: 22px; margin-top: 10px;">Verified Active</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Customer Support</span>
            <span class="stat-value" style="font-size: 20px; margin-top: 10px;">24x7 Active</span>
          </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="card-box">
          <h2>
            Recent Orders
            <?php if ($totalOrders > 5): ?>
              <a href="orders.php">View All (<?= $totalOrders ?>) →</a>
            <?php endif; ?>
          </h2>

          <?php if (empty($recentOrders)): ?>
            <div style="text-align: center; padding: 30px; color: #64748b;">
              <p style="margin: 0 0 16px; font-size: 14.5px;">You have not placed any hardware orders yet.</p>
              <a href="../products.php" class="btn-quote" style="display: inline-block; background: #078e49; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 13.5px;">
                Explore Hardware Catalog
              </a>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="order-table">
                <thead>
                  <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentOrders as $o): ?>
                    <tr>
                      <td><strong><?= e($o['order_number']) ?></strong></td>
                      <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                      <td><strong><?= format_inr((int)$o['total_paise']) ?></strong></td>
                      <td>
                        <span class="badge-status status-<?= e($o['payment_status']) ?>">
                          <?= e(ucfirst($o['payment_status'])) ?>
                        </span>
                      </td>
                      <td>
                        <span class="badge-status status-<?= e($o['status']) ?>">
                          <?= e(ucfirst($o['status'])) ?>
                        </span>
                      </td>
                      <td>
                        <a href="order-detail.php?id=<?= (int)$o['id'] ?>" style="color: #078e49; font-weight: 700; text-decoration: none;">
                          Details →
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      
        <!-- Security & Change Password -->
        <div class="card-box" id="security-settings" style="margin-top: 28px;">
          <h2>
            <span>🔒 Account Security &amp; Password</span>
          </h2>

          <?php if (!empty($pwdError)): ?>
            <div style="background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13.5px;">
              <?= e($pwdError) ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($pwdSuccess)): ?>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13.5px;">
              <?= e($pwdSuccess) ?>
            </div>
          <?php endif; ?>

          <form method="post" action="index.php#security-settings" style="max-width: 520px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div style="margin-bottom: 16px;">
              <label for="current_password" style="display:block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">Current Password</label>
              <input type="password" id="current_password" name="current_password" required placeholder="••••••••" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 16px;">
              <label for="new_password" style="display:block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">New Password (Min. 8 characters)</label>
              <input type="password" id="new_password" name="new_password" required minlength="8" placeholder="Minimum 8 characters" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 20px;">
              <label for="confirm_password" style="display:block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">Confirm New Password</label>
              <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Repeat new password" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <button type="submit" style="background: #078e49; color: #ffffff; border: none; padding: 11px 22px; border-radius: 6px; font-weight: 700; font-size: 14px; cursor: pointer;">
              Update Password
            </button>
          </form>
        </div>

      </main>
    </div>

  </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
