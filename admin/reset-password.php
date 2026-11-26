<?php
declare(strict_types=1);

/**
 * Admin Reset Password Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . "/../app/config/config.php";
require_once __DIR__ . "/../app/config/database.php";
require_once __DIR__ . "/../app/helpers/auth.php";
require_once __DIR__ . "/../app/helpers/csrf.php";
require_once __DIR__ . "/../app/helpers/sanitize.php";
require_once __DIR__ . "/../app/helpers/currency.php";

init_session();

if (is_admin()) {
    header("Location: index.php");
    exit;
}

$token = clean_string($_GET["token"] ?? $_POST["token"] ?? "");
$email = strtolower(clean_string($_GET["email"] ?? $_POST["email"] ?? ""));

$error = "";
$tokenValid = false;
$db = Database::getConnection();

if (!empty($token) && !empty($email)) {
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND user_type = 'admin' AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$email, $token]);
    $resetRecord = $stmt->fetch();

    if ($resetRecord) {
        $tokenValid = true;
    } else {
        $error = "This administrator reset link is invalid, has expired, or has already been used. Please request a new recovery link.";
    }
} else {
    $error = "Missing recovery token or email parameter.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $tokenValid) {
    if (!verify_csrf_token($_POST["csrf_token"] ?? null)) {
        $error = "Security session expired. Please re-submit.";
    } else {
        $newPassword = (string)($_POST["new_password"] ?? "");
        $confirmPassword = (string)($_POST["confirm_password"] ?? "");

        if (empty($newPassword) || empty($confirmPassword)) {
            $error = "Please fill in all password fields.";
        } elseif (strlen($newPassword) < 8) {
            $error = "Administrator passwords must be at least 8 characters long.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Passwords do not match. Please re-enter.";
        } else {
            // Fetch admin user ID
            $adminStmt = $db->prepare("SELECT id FROM admin_users WHERE email = ? LIMIT 1");
            $adminStmt->execute([$email]);
            $adminId = $adminStmt->fetchColumn();

            if ($adminId) {
                // Update password
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updStmt = $db->prepare("UPDATE admin_users SET password_hash = ? WHERE email = ?");
                $updStmt->execute([$passwordHash, $email]);

                // Delete used token
                $delStmt = $db->prepare("DELETE FROM password_resets WHERE email = ? AND user_type = 'admin'");
                $delStmt->execute([$email]);

                // Log activity
                try {
                    $logStmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, 'password_reset', 'admin_user', ?, ?, ?, ?)");
                    $logStmt->execute([
                        (int)$adminId,
                        (int)$adminId,
                        json_encode(["note" => "Administrator password reset successfully via token"]),
                        $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1",
                        substr($_SERVER["HTTP_USER_AGENT"] ?? "CLI", 0, 255)
                    ]);
                } catch (Exception $e) {
                    // Non-fatal if log fails
                }

                header("Location: login.php?password_reset=1");
                exit;
            } else {
                $error = "Administrator account could not be found.";
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
  <title>Set New Admin Password | iboltnetworks</title>
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
      background: #e0f2fe;
      color: #0369a1;
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
      background: #078e49;
      color: #ffffff;
      padding: 13px;
      border: none;
      border-radius: 6px;
      font-size: 14.5px;
      font-weight: 700;
      cursor: pointer;
      min-height: 44px;
      transition: background 0.2s;
    }
    .btn-submit:hover {
      background: #06773d;
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
      transition: background 0.2s;
    }
    .btn-submit:hover {
      background: #056633;
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
      <span class="badge">Staff Credential Update</span>
    </div>

    <h1>Set New Admin Password</h1>
    <p class="sub">Account: <strong><?= e($email) ?></strong></p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($tokenValid): ?>
      <form method="post" action="reset-password.php">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">

        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" required minlength="8" autofocus placeholder="Minimum 8 characters">
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Repeat new password">
        </div>

        <button type="submit" class="btn-submit">Save New Password</button>
      </form>
    <?php else: ?>
      <div style="text-align: center; margin-top: 16px;">
        <a href="forgot-password.php" class="btn-submit" style="display:inline-block; text-decoration:none; width:auto; padding: 11px 24px;">Request Recovery Link</a>
      </div>
    <?php endif; ?>

    <div class="footer-links">
      <a href="login.php">&larr; Back to Staff Login</a>
    </div>
  </div>

</body>
</html>
