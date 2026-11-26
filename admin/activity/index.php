<?php
declare(strict_types=1);

/**
 * Staff Activity & Security Audit Logs Controller
 * iboltnetworks E-Commerce Platform
 */

$adminPageTitle = 'Staff Activity & Security Audit Logs | iboltnetworks Admin';
$adminRoot = '../';

require_once __DIR__ . '/../../app/helpers/auth.php';
require_admin();

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../../app/helpers/security.php';

$db = Database::getConnection();

// -----------------------------------------------------------------------------
// Handle CSV Export Action (POST + CSRF Protected + Formula Injection Guarded)
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'export_csv') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        header("Location: index.php?error=" . urlencode("Security session expired. Please retry CSV export."));
        exit;
    }

    // Fetch recent 2000 log records for export
    $exportSql = "SELECT l.id, l.created_at, l.action, l.entity_type, l.entity_id, l.details, l.ip_address,
                         COALESCE(u.name, u.username, 'System / Anonymous') AS admin_name,
                         COALESCE(u.email, 'N/A') AS admin_email
                  FROM admin_activity_logs l
                  LEFT JOIN admin_users u ON l.admin_id = u.id
                  ORDER BY l.id DESC
                  LIMIT 2000";
    $exportStmt = $db->query($exportSql);
    $exportRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "iboltnetworks-audit-logs-" . date('Ymd-His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    // CSV Header row
    fputcsv($output, ['Log ID', 'Timestamp (IST)', 'Administrator', 'Admin Email', 'Action', 'Entity Type', 'Entity ID', 'Details / Parameters', 'Client IP Address']);

    foreach ($exportRows as $row) {
        $cleanDetails = '';
        if (!empty($row['details'])) {
            $decoded = json_decode((string)$row['details'], true);
            $cleanDetails = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_SLASHES) : (string)$row['details'];
        }

        // Apply formula injection defense (escape_csv_cell logic before fputcsv)
        $defend = function(mixed $val): string {
            $str = (string)($val ?? '');
            if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $str;
            }
            return $str;
        };

        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $defend($row['admin_name']),
            $defend($row['admin_email']),
            $defend($row['action']),
            $defend($row['entity_type']),
            $row['entity_id'] ?: 'N/A',
            $defend($cleanDetails),
            $defend($row['ip_address'])
        ]);
    }

    fclose($output);
    exit;
}

// -----------------------------------------------------------------------------
// Filters & Search Parameters
// -----------------------------------------------------------------------------
$search       = clean_string($_GET['q'] ?? '');
$actionFilter = clean_string($_GET['action'] ?? '');
$entityFilter = clean_string($_GET['entity_type'] ?? '');
$adminFilter  = sanitize_int($_GET['admin_id'] ?? 0);
$dateRange    = clean_string($_GET['date_range'] ?? '');
$page         = max(1, sanitize_int($_GET['page'] ?? 1, 1));
$perPage      = 30;
$offset       = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

if (!empty($actionFilter)) {
    $where[] = "l.action = :act";
    $params[':act'] = $actionFilter;
}

if (!empty($entityFilter)) {
    $where[] = "l.entity_type = :entity";
    $params[':entity'] = $entityFilter;
}

if ($adminFilter > 0) {
    $where[] = "l.admin_id = :aid";
    $params[':aid'] = $adminFilter;
}

$kolkataTz = new DateTimeZone('Asia/Kolkata');
if ($dateRange === 'today') {
    $todayStart = (new DateTime('today', $kolkataTz))->format('Y-m-d 00:00:00');
    $where[] = "l.created_at >= :today_start";
    $params[':today_start'] = $todayStart;
} elseif ($dateRange === 'last_7_days') {
    $sevenDaysAgo = (new DateTime('-7 days', $kolkataTz))->format('Y-m-d 00:00:00');
    $where[] = "l.created_at >= :seven_days";
    $params[':seven_days'] = $sevenDaysAgo;
} elseif ($dateRange === 'this_month') {
    $monthStart = (new DateTime('first day of this month', $kolkataTz))->format('Y-m-d 00:00:00');
    $where[] = "l.created_at >= :month_start";
    $params[':month_start'] = $monthStart;
}

if (!empty($search)) {
    $where[] = "(l.action LIKE :s1 OR l.entity_type LIKE :s2 OR l.details LIKE :s3 OR l.ip_address LIKE :s4)";
    $like = '%' . $search . '%';
    $params[':s1'] = $like;
    $params[':s2'] = $like;
    $params[':s3'] = $like;
    $params[':s4'] = $like;
}

$whereSql = implode(' AND ', $where);

// Count total matching logs
$countStmt = $db->prepare("SELECT COUNT(*) FROM admin_activity_logs l WHERE {$whereSql}");
$countStmt->execute($params);
$totalLogs = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalLogs / $perPage));

// Fetch paginated log records
$sql = "SELECT l.*,
               COALESCE(u.name, u.username, 'System / Anonymous') AS admin_name,
               u.username AS admin_username
        FROM admin_activity_logs l
        LEFT JOIN admin_users u ON l.admin_id = u.id
        WHERE {$whereSql}
        ORDER BY l.id DESC
        LIMIT {$perPage} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct actions and entities for filter dropdowns
$availableActions = $db->query("SELECT DISTINCT action FROM admin_activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
$availableEntities = $db->query("SELECT DISTINCT entity_type FROM admin_activity_logs WHERE entity_type IS NOT NULL AND entity_type != '' ORDER BY entity_type ASC")->fetchAll(PDO::FETCH_COLUMN);
$availableAdmins = $db->query("SELECT id, name, username FROM admin_users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$hasFilters = (!empty($search) || !empty($actionFilter) || !empty($entityFilter) || $adminFilter > 0 || !empty($dateRange));
$error = clean_string($_GET['error'] ?? '');
?>

<div class="admin-container">

  <div class="admin-page-header">
    <div>
      <h1>Staff Activity &amp; Security Audit Logs</h1>
      <p>Inspect immutable administrative audit trails, security events, authentication attempts, and data modifications.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
      <!-- Export CSV Form -->
      <form method="post" action="index.php" style="margin: 0;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="export_csv">
        <button type="submit" class="btn btn-secondary" title="Export audit logs to spreadsheet (protected against CSV formula injection)">
          📥 Export Audit CSV
        </button>
      </form>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- Search & Filter Card -->
  <div class="admin-card" style="padding: 18px 24px;">
    <form method="get" action="index.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
      <div style="flex: 1; min-width: 200px; max-width: 280px;">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search action, details, IP..." class="form-control">
      </div>

      <select name="action" class="form-control" style="width: auto; min-width: 160px;" onchange="this.form.submit()">
        <option value="">All Actions</option>
        <?php foreach ($availableActions as $act): ?>
          <option value="<?= e($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= e($act) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="entity_type" class="form-control" style="width: auto; min-width: 150px;" onchange="this.form.submit()">
        <option value="">All Entities</option>
        <?php foreach ($availableEntities as $ent): ?>
          <option value="<?= e($ent) ?>" <?= $entityFilter === $ent ? 'selected' : '' ?>><?= e(ucfirst($ent)) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="admin_id" class="form-control" style="width: auto; min-width: 160px;" onchange="this.form.submit()">
        <option value="">All Administrators</option>
        <?php foreach ($availableAdmins as $adm): ?>
          <option value="<?= (int)$adm['id'] ?>" <?= $adminFilter === (int)$adm['id'] ? 'selected' : '' ?>>
            <?= e($adm['name'] ?: $adm['username']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="date_range" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
        <option value="">All Dates</option>
        <option value="today" <?= $dateRange === 'today' ? 'selected' : '' ?>>Today (IST)</option>
        <option value="last_7_days" <?= $dateRange === 'last_7_days' ? 'selected' : '' ?>>Last 7 Days</option>
        <option value="this_month" <?= $dateRange === 'this_month' ? 'selected' : '' ?>>This Month</option>
      </select>

      <button type="submit" class="btn btn-secondary">Filter</button>

      <?php if ($hasFilters): ?>
        <a href="index.php" class="btn btn-secondary" style="color: #dc2626;">Clear</a>
      <?php endif; ?>

      <div style="margin-left: auto; font-size: 13.5px; color: #64748b; font-weight: 600;">
        Found <strong><?= $totalLogs ?></strong> log entry(s)
      </div>
    </form>
  </div>

  <!-- Logs Table & Cards -->
  <div class="admin-card">
    <?php if (empty($logs)): ?>
      <p style="text-align: center; padding: 40px; color: #64748b; font-size: 14.5px;">No activity logs found matching the filter criteria.</p>
    <?php else: ?>
      <!-- Desktop Table -->
      <div class="desktop-only-wrap">
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 60px;">ID</th>
                <th style="width: 160px;">Timestamp (IST)</th>
                <th style="width: 170px;">Administrator</th>
                <th style="width: 180px;">Action</th>
                <th style="width: 140px;">Entity</th>
                <th>Audit Summary / Parameters</th>
                <th style="width: 120px; text-align: right;">Client IP</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $l): ?>
                <?php
                  // Action badge coloring
                  $badgeClass = 'badge-gray';
                  $act = $l['action'];
                  if (str_contains($act, 'login_success') || str_contains($act, 'create') || str_contains($act, 'paid')) {
                      $badgeClass = 'badge-green';
                  } elseif (str_contains($act, 'fail') || str_contains($act, 'throttled') || str_contains($act, 'delete') || str_contains($act, 'cancel')) {
                      $badgeClass = 'badge-red';
                  } elseif (str_contains($act, 'update') || str_contains($act, 'reorder') || str_contains($act, 'change')) {
                      $badgeClass = 'badge-blue';
                  }

                  // Decode details JSON safely
                  $detailsFormatted = '—';
                  if (!empty($l['details'])) {
                      $decoded = json_decode((string)$l['details'], true);
                      if (is_array($decoded)) {
                          $parts = [];
                          foreach ($decoded as $k => $v) {
                              $valStr = is_array($v) ? json_encode($v) : (string)$v;
                              $parts[] = '<strong>' . e((string)$k) . ':</strong> ' . e($valStr);
                          }
                          $detailsFormatted = implode(' &bull; ', $parts);
                      } else {
                          $detailsFormatted = e((string)$l['details']);
                      }
                  }
                ?>
                <tr>
                  <td><code style="color: #64748b;">#<?= (int)$l['id'] ?></code></td>
                  <td style="font-size: 12.5px; white-space: nowrap;">
                    <?= date('M d, Y', strtotime($l['created_at'])) ?><br>
                    <span style="color: #64748b; font-size: 11px;"><?= date('h:i:s A', strtotime($l['created_at'])) ?></span>
                  </td>
                  <td>
                    <strong><?= e($l['admin_name']) ?></strong>
                    <?php if (!empty($l['admin_username'])): ?>
                      <br><small style="color: #64748b; font-size: 11.5px;">@<?= e($l['admin_username']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge <?= $badgeClass ?>"><?= e($l['action']) ?></span>
                  </td>
                  <td>
                    <?php if (!empty($l['entity_type'])): ?>
                      <span style="font-weight: 600; text-transform: uppercase; font-size: 11.5px; color: #475569;">
                        <?= e($l['entity_type']) ?>
                      </span>
                      <?php if (!empty($l['entity_id'])): ?>
                        <code style="font-size: 11px;">#<?= (int)$l['entity_id'] ?></code>
                      <?php endif; ?>
                    <?php else: ?>
                      <span style="color: #94a3b8;">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size: 12.5px; line-height: 1.5; color: #1e293b;">
                    <?= $detailsFormatted ?>
                  </td>
                  <td style="text-align: right; font-family: monospace; font-size: 11.5px; color: #64748b; white-space: nowrap;">
                    <?= e($l['ip_address'] ?? '127.0.0.1') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Mobile Cards (≤ 768px) -->
      <div class="mobile-only" style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($logs as $l): ?>
          <?php
            $badgeClass = 'badge-gray';
            $act = $l['action'];
            if (str_contains($act, 'login_success') || str_contains($act, 'create') || str_contains($act, 'paid')) {
                $badgeClass = 'badge-green';
            } elseif (str_contains($act, 'fail') || str_contains($act, 'throttled') || str_contains($act, 'delete') || str_contains($act, 'cancel')) {
                $badgeClass = 'badge-red';
            } elseif (str_contains($act, 'update') || str_contains($act, 'reorder') || str_contains($act, 'change')) {
                $badgeClass = 'badge-blue';
            }

            $detailsFormatted = '—';
            if (!empty($l['details'])) {
                $decoded = json_decode((string)$l['details'], true);
                if (is_array($decoded)) {
                    $parts = [];
                    foreach ($decoded as $k => $v) {
                        $valStr = is_array($v) ? json_encode($v) : (string)$v;
                        $parts[] = '<strong>' . e((string)$k) . ':</strong> ' . e($valStr);
                    }
                    $detailsFormatted = implode('<br>', $parts);
                } else {
                    $detailsFormatted = e((string)$l['details']);
                }
            }
          ?>
          <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
              <div>
                <span class="badge <?= $badgeClass ?>" style="font-size: 12px; padding: 4px 8px;"><?= e($l['action']) ?></span>
                <?php if (!empty($l['entity_type'])): ?>
                  <span style="font-size: 11.5px; font-weight: 700; color: #475569; margin-left: 4px; text-transform: uppercase;">
                    <?= e($l['entity_type']) ?> <?= !empty($l['entity_id']) ? '#' . (int)$l['entity_id'] : '' ?>
                  </span>
                <?php endif; ?>
              </div>
              <code style="color: #94a3b8; font-size: 12px;">#<?= (int)$l['id'] ?></code>
            </div>

            <div style="font-size: 13px; color: #0f172a; margin-bottom: 8px;">
              <strong><?= e($l['admin_name']) ?></strong>
              <?php if (!empty($l['admin_username'])): ?>
                <span style="color: #64748b; font-size: 12px;">(@<?= e($l['admin_username']) ?>)</span>
              <?php endif; ?>
            </div>

            <div style="background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 6px; padding: 8px 10px; font-size: 12px; color: #334155; line-height: 1.45; margin-bottom: 8px;">
              <?= $detailsFormatted ?>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: #64748b; border-top: 1px solid #f1f5f9; padding-top: 8px;">
              <span>🕒 <?= date('M d, Y h:i A', strtotime($l['created_at'])) ?></span>
              <code><?= e($l['ip_address'] ?? '127.0.0.1') ?></code>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <?php $qParams = $_GET; ?>
        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px; flex-wrap: wrap;">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $qParams['page'] = $i; ?>
            <a href="index.php?<?= http_build_query($qParams) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
              <?= $i ?>
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
