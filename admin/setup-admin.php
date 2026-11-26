<?php
declare(strict_types=1);

/**
 * Initial Administrator Account Setup & Security Initialization
 * iboltnetworks E-Commerce Platform
 * 
 * Secure one-time wizard for Hostinger shared-hosting environments without SSH access.
 * Automatically locks once an active administrator account exists.
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/sanitize.php';

init_session();

$error = '';
$success = '';
$setupLocked = false;
$existingAdminCount = 0;

try {
    $db = Database::getConnection();
    $adminCountStmt = $db->query("SELECT COUNT(*) FROM admin_users WHERE status = 'active'");
    $existingAdminCount = (int)$adminCountStmt->fetchColumn();
    if ($existingAdminCount > 0) {
        $setupLocked = true;
    }
} catch (Throwable $e) {
    $error = "Database connection error: " . $e->getMessage() . ". Please check that your .env configuration has valid credentials.";
}

// Token-based override for emergency recovery (configured via ADMIN_SETUP_TOKEN in .env)
$envSetupToken = (string)config('admin_setup_token', '');
$providedToken = clean_string($_POST['setup_token'] ?? $_GET['token'] ?? '');
if ($setupLocked && !empty($envSetupToken) && hash_equals($envSetupToken, $providedToken)) {
    $setupLocked = false; // Token authorized recovery
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$setupLocked) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please refresh the page.';
    } else {
        $name = clean_string($_POST['name'] ?? 'System Administrator');
        $username = clean_string($_POST['username'] ?? 'admin');
        $email = strtolower(clean_string($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if (empty($name) || strlen($name) < 2) {
            $error = 'Please enter a valid administrator name (minimum 2 characters).';
        } elseif (empty($username) || strlen($username) < 3) {
            $error = 'Username must be at least 3 characters long.';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid administrator email address.';
        } elseif (strlen($password) < 10) {
            $error = 'Password must be at least 10 characters long with uppercase, lowercase, and numbers for production security.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwords do not match. Please verify and retype.';
        } else {
            try {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Check if account already exists
                $chk = $db->prepare("SELECT id FROM admin_users WHERE email = ? OR username = ? LIMIT 1");
                $chk->execute([$email, $username]);
                $existing = $chk->fetch();

                if ($existing) {
                    $up = $db->prepare("UPDATE admin_users SET name = ?, username = ?, email = ?, password_hash = ?, role = 'super_admin', status = 'active', updated_at = NOW() WHERE id = ?");
                    $up->execute([$name, $username, $email, $passwordHash, (int)$existing['id']]);
                } else {
                    $ins = $db->prepare("INSERT INTO admin_users (name, username, email, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'super_admin', 'active', NOW(), NOW())");
                    $ins->execute([$name, $username, $email, $passwordHash]);
                }

                $success = "Super Administrator account created successfully! Redirecting to login...";
                $setupLocked = true;
                header("Refresh: 2; url=login.php?password_reset=1");
            } catch (Throwable $e) {
                $error = "Failed to create administrator: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Initial Administrator Setup | iboltnetworks</title>
  <style>
    :root { --primary: #0d8244; --primary-hover: #075e2f; --dark: #051833; --bg: #f4f7f6; --text: #2c3e50; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .setup-card { background: #ffffff; border-radius: 12px; width: 100%; max-width: 520px; padding: 36px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
    .brand-mark { display: inline-flex; align-items: center; justify-content: center; width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: #fff; font-weight: 800; font-size: 22px; border-radius: 10px; margin-bottom: 16px; }
    h1 { font-size: 22px; font-weight: 700; color: var(--dark); margin-bottom: 8px; }
    p.subtitle { font-size: 14px; color: #64748b; margin-bottom: 24px; line-height: 1.5; }
    .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; line-height: 1.4; }
    .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
    .form-group { margin-bottom: 18px; text-align: left; }
    label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
    input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; transition: border 0.2s; }
    input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13, 130, 68, 0.15); }
    .btn-submit { width: 100%; padding: 12px; background: var(--primary); color: #fff; font-weight: 600; border: none; border-radius: 8px; font-size: 15px; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
    .btn-submit:hover { background: var(--primary-hover); }
    .btn-link { display: inline-block; margin-top: 16px; color: var(--primary); text-decoration: none; font-size: 14px; font-weight: 600; }
    .password-row { position: relative; }
    .gen-btn { position: absolute; right: 8px; top: 7px; background: #e2e8f0; border: none; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; }
    .gen-btn:hover { background: #cbd5e1; }
  </style>
</head>
<body>
  <div class="setup-card">
    <div class="brand-mark">iB</div>
    <h1>Administrator Initialization</h1>
    <p class="subtitle">Secure initial administrator account provisioning for iboltnetworks.</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($setupLocked): ?>
      <div class="alert alert-info">
        <strong>Setup Locked:</strong> An active administrator account already exists in this database. For security reasons, this setup wizard is disabled.
      </div>
      <p style="font-size: 13px; color: #64748b; margin-top: 14px;">
        To log in to the administration panel, please proceed to the admin login page:
      </p>
      <a href="login.php" class="btn-link">Proceed to Admin Login &rarr;</a>
    <?php else: ?>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <?php if (!empty($providedToken)): ?>
          <input type="hidden" name="setup_token" value="<?= e($providedToken) ?>">
        <?php endif; ?>

        <div class="form-group">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" value="System Administrator" required>
        </div>

        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" value="admin" required autocomplete="username">
        </div>

        <div class="form-group">
          <label for="email">Administrator Email</label>
          <input type="email" id="email" name="email" value="admin@iboltnetworks.com" required autocomplete="email">
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="password-row">
            <input type="password" id="password" name="password" required autocomplete="new-password" minlength="10" placeholder="Minimum 10 characters">
            <button type="button" class="gen-btn" onclick="generatePassword()">Generate</button>
          </div>
        </div>

        <div class="form-group">
          <label for="password_confirm">Confirm Password</label>
          <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" placeholder="Retype password">
        </div>

        <button type="submit" class="btn-submit">Initialize Administrator Account</button>
      </form>
      <script>
        function generatePassword() {
          const chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*";
          let pass = "";
          for (let i = 0; i < 18; i++) {
            pass += chars.charAt(Math.floor(Math.random() * chars.length));
          }
          document.getElementById('password').value = pass;
          document.getElementById('password_confirm').value = pass;
          document.getElementById('password').type = 'text';
          alert("Generated password: " + pass + "\nPlease copy and save this password before submitting!");
        }
      </script>
    <?php endif; ?>
  </div>
</body>
</html>

