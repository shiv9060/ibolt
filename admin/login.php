<?php
declare(strict_types=1);

/**
 * Admin Login Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/sanitize.php';
require_once __DIR__ . '/../app/helpers/currency.php';

init_session();

// If already authenticated as admin, redirect to admin dashboard
if (is_admin()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if (!empty($_GET['logged_out'])) {
    $success = 'You have been safely signed out from the administration panel.';
} elseif (!empty($_GET['password_reset'])) {
    $success = 'Administrator password has been updated successfully! Please sign in with your new password.';
} elseif (!empty($_GET['session_expired'])) {
    $error = 'Your administrator session expired due to inactivity. Please sign in again.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please re-submit.';
    } else {
        $identifier = clean_string($_POST['email'] ?? $_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($identifier) || empty($password)) {
            $error = 'Please enter both your administrator username/email and password.';
        } else {
            try {
                $clientIp = get_client_ip();
                $throttle = check_login_throttle($identifier, $clientIp);

                if ($throttle['throttled']) {
                    record_throttled_login($identifier, $clientIp, (int)$throttle['retry_after']);
                    $error = "Too many failed login attempts. Please wait {$throttle['retry_after']} minute(s) before trying again.";
                } else {
                    $db = Database::getConnection();
                    $stmt = $db->prepare("SELECT * FROM admin_users WHERE email = ? OR username = ? LIMIT 1");
                    $stmt->execute([strtolower($identifier), $identifier]);
                    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    $isPasswordMatch = ($adminUser && password_verify($password, $adminUser['password_hash']));
                    $isActive = ($adminUser && ($adminUser['status'] ?? '') === 'active');

                    if ($adminUser && $isActive && $isPasswordMatch) {
                        // Reset failure counters upon successful authentication
                        clear_login_attempts($identifier, $clientIp);

                        // Update last login timestamp
                        $db->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?")->execute([$adminUser['id']]);

                        // Audit successful login
                        try {
                            $logStmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
                                                     VALUES (?, 'login_success', 'auth', ?, ?, ?, ?, NOW())");
                            $logStmt->execute([
                                $adminUser['id'],
                                $adminUser['id'],
                                json_encode(['username' => $adminUser['username'], 'email' => $adminUser['email']]),
                                $clientIp,
                                substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255)
                            ]);
                        } catch (Throwable) {}

                        login_admin([
                            'id'       => $adminUser['id'],
                            'username' => !empty($adminUser['username']) ? $adminUser['username'] : $adminUser['name'],
                            'name'     => $adminUser['name'],
                            'email'    => $adminUser['email'],
                            'role'     => $adminUser['role']
                        ]);

                        header("Location: index.php");
                        exit;
                    } else {
                        // Record failed attempt for rate-limiting (without storing password)
                        record_failed_login($identifier, $clientIp);
                        $error = 'Invalid administrator credentials or inactive account.';
                    }
                }
            } catch (Throwable $e) {
                error_log("[iboltnetworks] admin/login error: " . $e->getMessage());
                $error = 'Administration database service is temporarily unavailable. Please verify that .env database credentials are correct.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Admin Login | iboltnetworks</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #0f172a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .admin-login-card {
      background: #ffffff;
      border-radius: 12px;
      padding: 40px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    }
    .brand {
      text-align: center;
      margin-bottom: 24px;
    }
    .brand-logo {
      font-size: 26px;
      font-weight: 800;
      color: #0f172a;
      text-decoration: none;
    }
    .brand-logo span { color: #078e49; }
    .badge {
      display: inline-block;
      background: #e0f2fe;
      color: #0369a1;
      font-size: 11px;
      font-weight: 800;
      padding: 2px 8px;
      border-radius: 12px;
      text-transform: uppercase;
      margin-top: 6px;
      letter-spacing: 0.5px;
    }
    h1 {
      font-size: 20px;
      font-weight: 800;
      color: #1e293b;
      margin: 0 0 8px;
      text-align: center;
    }
    p.sub {
      font-size: 13.5px;
      color: #64748b;
      margin: 0 0 24px;
      text-align: center;
    }
    .form-group {
      margin-bottom: 18px;
    }
    label {
      display: block;
      font-size: 13px;
      font-weight: 700;
      color: #334155;
      margin-bottom: 6px;
    }
    input {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      font-size: 14px;
      color: #0f172a;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    input:focus {
      border-color: #078e49;
      box-shadow: 0 0 0 3px rgba(7, 142, 73, 0.15);
    }
    .btn-submit {
      width: 100%;
      background: #078e49;
      color: #ffffff;
      font-size: 15px;
      font-weight: 700;
      padding: 13px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.2s;
      margin-top: 8px;
      min-height: 44px;
    }
    .btn-submit:hover {
      background: #06773d;
    }
    .alert {
      padding: 12px 14px;
      border-radius: 6px;
      font-size: 13.5px;
      margin-bottom: 20px;
      line-height: 1.45;
    }
    .alert-error {
      background: #fef2f2;
      border: 1px solid #fee2e2;
      color: #991b1b;
    }
    .alert-success {
      background: #f0fdf4;
      border: 1px solid #dcfce7;
      color: #166534;
    }

    @media (max-width: 480px) {
      body {
        padding: 16px 12px;
      }
      .admin-login-card {
        padding: 24px 18px;
        border-radius: 10px;
      }
      input {
        font-size: 16px;
        min-height: 44px;
      }
      .btn-submit {
        min-height: 48px;
        font-size: 16px;
      }
    }
    
  </style>
</head>
<body>

  <div class="admin-login-card">
    <div class="brand">
      <a href="../index.php" class="brand-logo">ibolt<span>networks</span></a><br>
      <span class="badge">Staff Administration Control</span>
    </div>

    <h1>Sign in to Control Panel</h1>
    <p class="sub">Authorized administrative personnel only</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="email">Admin Username or Email Address</label>
        <input type="text" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus placeholder="Username or email address">
      </div>

      <div class="form-group">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
          <label for="password" style="margin-bottom:0;">Password</label>
          <a href="forgot-password.php" style="font-size:12px; color:#087fc0; text-decoration:none; font-weight:600;">Forgot Password?</a>
        </div>
        <div style="position: relative;">
          <input type="password" id="password" name="password" required placeholder="••••••••" style="padding-right: 42px;">
          <button type="button" onclick="togglePasswordVisibility()" id="togglePassBtn" style="position: absolute; right: 4px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 18px; color: #64748b; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center;" title="Show / Hide Password" aria-label="Toggle password visibility">👁️</button>
        </div>
      </div>

      <button type="submit" class="btn-submit">Authenticate &amp; Enter</button>
    </form>



  <script>
    function togglePasswordVisibility() {
      const input = document.getElementById('password');
      const btn = document.getElementById('togglePassBtn');
      if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
      } else {
        input.type = 'password';
        btn.textContent = '👁️';
      }
    }


  </script>
  </div>

</body>
</html>
