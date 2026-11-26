<?php
declare(strict_types=1);

/**
 * Customer Order History
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

// Fetch all orders for this user
$stmt = $db->prepare("SELECT o.*,
                             (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
                      FROM orders o
                      WHERE o.user_id = ?
                      ORDER BY o.created_at DESC");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$pageTitle = "Order History | iboltnetworks";
$pageDescription = "View all past and current hardware orders placed with iboltnetworks.";

$extraHead = <<<'HTML'
<style>
  .dashboard-wrapper { background: #f6f8fa; padding: 40px 0 80px; min-height: 600px; }
  .account-grid { display: grid; grid-template-columns: 280px 1fr; gap: 32px; }
  .account-nav { background: #ffffff; border: 1px solid #e1e7ec; border-radius: 12px; padding: 24px; box-shadow: 0 4px 18px rgba(0,0,0,0.04); height: fit-content; }
  .user-badge { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; }
  .user-avatar { width: 64px; height: 64px; border-radius: 50%; background: #078e49; color: #ffffff; display: grid; place-items: center; font-size: 24px; font-weight: 800; margin: 0 auto 12px; }
  .user-badge h3 { font-size: 16px; margin: 0 0 4px; color: #1e293b; }
  .user-badge p { font-size: 13px; color: #64748b; margin: 0; }
  .account-menu { list-style: none; padding: 0; margin: 0; }
  .account-menu li { margin-bottom: 6px; }
  .account-menu a { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 8px; color: #334155; font-size: 14px; font-weight: 600; text-decoration: none; transition: background 0.15s, color 0.15s; }
  .account-menu a:hover { background: #f1f5f9; color: #078e49; }
  .account-menu a.active { background: #078e49; color: #ffffff; }
  .account-menu a.logout { color: #dc2626; margin-top: 14px; border-top: 1px solid #f1f5f9; padding-top: 14px; }
  .account-menu a.logout:hover { background: #fef2f2; }

  .card-box { background: #ffffff; border: 1px solid #e1e7ec; border-radius: 12px; padding: 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
  .card-box h1 { font-size: 22px; font-weight: 800; color: #1e293b; margin: 0 0 20px; }

  .table-responsive { width: 100%; overflow-x: auto; }
  .order-table { width: 100%; border-collapse: collapse; font-size: 13.5px; text-align: left; }
  .order-table th { padding: 12px 14px; background: #f8fafc; color: #475569; font-weight: 700; border-bottom: 2px solid #edf2f6; }
  .order-table td { padding: 14px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }

  .badge-status { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
  .status-paid { background: #dcfce7; color: #166534; }
  .status-unpaid { background: #fef3c7; color: #92400e; }
  .status-pending { background: #f1f5f9; color: #475569; }
  .status-processing { background: #e0f2fe; color: #0369a1; }
  .status-shipped { background: #ede9fe; color: #6d28d9; }
  .status-delivered { background: #dcfce7; color: #166534; }

  @media (max-width: 900px) { .account-grid { grid-template-columns: 1fr; } }
</style>
HTML;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<div class="dashboard-wrapper">
  <div class="catalog-container">

    <div class="account-grid">
      <aside class="account-nav">
        <div class="user-badge">
          <div class="user-avatar">
            <?= strtoupper(substr((string)($user['name'] ?? 'U'), 0, 1)) ?>
          </div>
          <h3><?= e($user['name'] ?? 'Customer') ?></h3>
          <p><?= e($user['email'] ?? '') ?></p>
        </div>

        <ul class="account-menu">
          <li><a href="index.php">📊 Dashboard</a></li>
          <li><a href="orders.php" class="active">📦 Order History</a></li>
          <li><a href="../products.php">🛒 Browse Hardware</a></li>
          <li><a href="../logout.php" class="logout">🚪 Sign Out</a></li>
        </ul>
      </aside>

      <main class="card-box">
        <h1>All Orders (<?= count($orders) ?>)</h1>

        <?php if (empty($orders)): ?>
          <div style="text-align: center; padding: 40px; color: #64748b;">
            <p style="font-size: 15px; margin-bottom: 20px;">You haven't placed any orders yet.</p>
            <a href="../products.php" class="btn-quote" style="display: inline-block; background: #078e49; color: #fff; padding: 11px 22px; border-radius: 6px; text-decoration: none; font-weight: 700;">
              Start Shopping
            </a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="order-table">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Date</th>
                  <th>Items</th>
                  <th>Total Amount</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $o): ?>
                  <tr>
                    <td><strong><?= e($o['order_number']) ?></strong></td>
                    <td><?= date('M d, Y h:i A', strtotime($o['created_at'])) ?></td>
                    <td><?= (int)$o['item_count'] ?> item(s)</td>
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
                        View Invoice →
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </main>
    </div>

  </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
