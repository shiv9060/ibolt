<?php
declare(strict_types=1);

/**
 * Admin Password Recovery Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . "/../app/config/config.php";
require_once __DIR__ . "/../app/config/database.php";
require_once __DIR__ . "/../app/helpers/auth.php";
require_once __DIR__ . "/../app/helpers/csrf.php";
require_once __DIR__ . "/../app/helpers/sanitize.php";
require_once __DIR__ . "/../app/helpers/currency.php";
require_once __DIR__ . "/../app/services/MailerService.php";

init_session();

if (is_admin()) {
    header("Location: index.php");
    exit;
}

$error = "";
$success = "";
$resetUrl = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? null)) {
        $error = "Security session expired. Please re-submit.";
    } else {
        $email = strtolower(clean_string($_POST["email"] ?? ""));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid administrator email address.";
        } else {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id, name, email, status FROM admin_users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $adminUser = $stmt->fetch();

            if ($adminUser && $adminUser["status"] === "active") {
                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $expiresAt = date("Y-m-d H:i:s", strtotime("+1 hour"));

                // Clear previous tokens
                $delStmt = $db->prepare("DELETE FROM password_resets WHERE email = ? AND user_type = 'admin'");
                $delStmt->execute([$email]);

                // Insert token
                $insStmt = $db->prepare("INSERT INTO password_resets (email, token, user_type, expires_at) VALUES (?, ?, 'admin', DATE_ADD(NOW(), INTERVAL 1 HOUR))");
                $insStmt->execute([$email, $token]);

                $baseUrl = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . rtrim(dirname($_SERVER["PHP_SELF"]), "/\\");
                $resetUrl = $baseUrl . "/reset-password.php?token=" . urlencode($token) . "&email=" . urlencode($email);

                // Dispatch branded reset email via MailerService
                $mailResult = MailerService::sendPasswordResetEmail($email, (string)($adminUser["name"] ?? "Administrator"), $resetUrl, "admin");
                $success = "An administrator recovery link has been emailed to <strong>" . e($email) . "</strong>. Please check your staff inbox.";
            } else {
                $error = "No active administrator account matches that email address.";
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
  <title>Admin Password Recovery | iboltnetworks</title>
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
      max-width: 440px;
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
      background: #fef3c7;
      color: #b45309;
      font-size: 11px;
      font-weight: 800;
      padding: 3px 10px;
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
      line-height: 1.45;
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
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    input:focus {
      border-color: #078e49;
      box-shadow: 0 0 0 3px rgba(7, 142, 73, 0.15);
    }
    .btn-submit {
      width: 100%;
      background: #087fc0;
      color: #ffffff;
      padding: 13px;
      border: none;
      border-radius: 6px;
      font-size: 14.5px;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s;
      min-height: 44px;
    }
    .btn-submit:hover {
      background: #06679e;
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
    .alert {
      padding: 13px 16px;
      border-radius: 8px;
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
    .reset-box {
      margin-top: 18px;
      padding: 16px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      text-align: left;
    }
    .reset-box strong {
      display: block;
      font-size: 12.5px;
      color: #1e293b;
      margin-bottom: 6px;
    }
    .reset-link-input {
      width: 100%;
      font-size: 12px;
      padding: 8px 10px;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      background: #ffffff;
      color: #475569;
      box-sizing: border-box;
      margin-bottom: 12px;
      font-family: monospace;
    }
    .btn-reset-now {
      display: block;
      text-align: center;
      background: #078e49;
      color: #ffffff;
      padding: 10px 16px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 700;
      font-size: 13.5px;
      transition: background 0.2s;
    }
    .btn-reset-now:hover {
      background: #056633;
    }
    .footer-links {
      text-align: center;
      margin-top: 24px;
      padding-top: 18px;
      border-top: 1px solid #e2e8f0;
      font-size: 13px;
    }
    .footer-links a {
      color: #087fc0;
      text-decoration: none;
      font-weight: 600;
    }
    .footer-links a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <div class="admin-login-card">
    <div class="brand">
      <a href="../index.php" class="brand-logo">ibolt<span>networks</span></a><br>
      <span class="badge">Staff Credential Recovery</span>
    </div>

    <h1>Recover Admin Access</h1>
    <p class="sub">Enter your staff email to generate a secure recovery token.</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success">
        <?= $success ?>
        
        <?php if (!empty($resetUrl)): ?>
          <div class="reset-box">
            <strong>Direct Reset Link (Valid for 1 Hour):</strong>
            <input type="text" class="reset-link-input" readonly value="<?= e($resetUrl) ?>" onclick="this.select();">
            <a href="<?= e($resetUrl) ?>" class="btn-reset-now">Proceed to Reset Password &rarr;</a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($resetUrl)): ?>
      <form method="post" action="forgot-password.php">
        <?= csrf_field() ?>

        <div class="form-group">
          <label for="email">Admin Email Address</label>
          <input type="email" id="email" name="email" value="<?= e($_POST["email"] ?? "sales@iboltnetworks.com") ?>" required autofocus placeholder="sales@iboltnetworks.com">
        </div>

        <button type="submit" class="btn-submit">Generate Recovery Link</button>
      </form>
    <?php endif; ?>

    <div class="footer-links">
      <a href="login.php">&larr; Back to Staff Login</a>
    </div>
  </div>

</body>
</html>
