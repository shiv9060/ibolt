<?php
declare(strict_types=1);

/**
 * Customer Reset Password Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . "/app/config/config.php";
require_once __DIR__ . "/app/config/database.php";
require_once __DIR__ . "/app/helpers/auth.php";
require_once __DIR__ . "/app/helpers/csrf.php";
require_once __DIR__ . "/app/helpers/sanitize.php";
require_once __DIR__ . "/app/helpers/currency.php";

init_session();

if (is_logged_in()) {
    header("Location: account/index.php");
    exit;
}

$token = clean_string($_GET["token"] ?? $_POST["token"] ?? "");
$email = strtolower(clean_string($_GET["email"] ?? $_POST["email"] ?? ""));

$error = "";
$tokenValid = false;
$db = Database::getConnection();

// Validate token and email
if (!empty($token) && !empty($email)) {
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND user_type = 'customer' AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$email, $token]);
    $resetRecord = $stmt->fetch();

    if ($resetRecord) {
        $tokenValid = true;
    } else {
        $error = "This password reset link is invalid, has expired, or has already been used. Please request a new one.";
    }
} else {
    $error = "Missing security token or email. Please use the complete link provided in your reset email.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $tokenValid) {
    if (!verify_csrf_token($_POST["csrf_token"] ?? null)) {
        $error = "Security session expired. Please submit the form again.";
    } else {
        $newPassword = (string)($_POST["new_password"] ?? "");
        $confirmPassword = (string)($_POST["confirm_password"] ?? "");

        if (empty($newPassword) || empty($confirmPassword)) {
            $error = "Please fill in all password fields.";
        } elseif (strlen($newPassword) < 8) {
            $error = "Your new password must be at least 8 characters in length.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "The passwords you entered do not match. Please verify and try again.";
        } else {
            // Update password in database
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $updateStmt->execute([$passwordHash, $email]);

            // Clear reset token
            $delStmt = $db->prepare("DELETE FROM password_resets WHERE email = ? AND user_type = 'customer'");
            $delStmt->execute([$email]);

            // Redirect to login with success indicator
            header("Location: login.php?password_reset=1");
            exit;
        }
    }
}

$pageTitle = "Set New Password | iboltnetworks";
$pageDescription = "Create a new secure password for your iboltnetworks customer account.";

$extraHead = <<<'HTML'
<style>
  .auth-wrapper {
    background: #f6f8fa;
    padding: 60px 20px 80px;
    min-height: calc(100vh - 350px);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .auth-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e1e7ec;
    box-shadow: 0 8px 30px rgba(0,0,0,0.06);
    width: 100%;
    max-width: 460px;
    padding: 40px;
  }
  .auth-brand {
    text-align: center;
    margin-bottom: 24px;
  }
  .auth-brand .brand-logo {
    font-size: 26px;
    font-weight: 800;
    color: #087fc0;
    text-decoration: none;
  }
  .auth-brand .brand-logo span {
    color: #009e52;
  }
  .auth-card h1 {
    font-size: 22px;
    color: #1e293b;
    margin: 0 0 8px;
    text-align: center;
  }
  .auth-subtitle {
    font-size: 14px;
    color: #64748b;
    text-align: center;
    margin: 0 0 28px;
    line-height: 1.45;
  }
  .form-group {
    margin-bottom: 20px;
  }
  .form-group label {
    display: block;
    font-size: 13.5px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
  }
  .form-control {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
    color: #1e293b;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  .form-control:focus {
    border-color: #078e49;
    box-shadow: 0 0 0 3px rgba(7, 142, 73, 0.15);
  }
  .btn-auth-submit {
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
  }
  .btn-auth-submit:hover {
    background: #06773d;
  }
  .alert {
    padding: 14px 16px;
    border-radius: 8px;
    font-size: 13.5px;
    margin-bottom: 22px;
    line-height: 1.5;
  }
  .alert-error {
    background: #fef2f2;
    border: 1px solid #fee2e2;
    color: #991b1b;
  }
  .auth-footer {
    text-align: center;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #eef2f6;
    font-size: 13.5px;
    color: #64748b;
  }
  .auth-footer a {
    color: #078e49;
    font-weight: 700;
    text-decoration: none;
  }
  .auth-footer a:hover {
    text-decoration: underline;
  }
  .password-hint {
    font-size: 12px;
    color: #64748b;
    margin-top: 5px;
  }
</style>
HTML;

require_once __DIR__ . "/includes/header.php";
require_once __DIR__ . "/includes/navigation.php";
?>

<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-brand">
      <a href="index.php" class="brand-logo">ibolt<span>networks</span></a>
    </div>

    <h1>Set a new password</h1>
    <p class="auth-subtitle">Create a strong, secure password for <strong><?= e($email) ?></strong></p>

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
          <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autofocus placeholder="At least 8 characters">
          <div class="password-hint">Must contain at least 8 characters</div>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" placeholder="Repeat your new password">
        </div>

        <button type="submit" class="btn-auth-submit">Update Password</button>
      </form>
    <?php else: ?>
      <div style="text-align: center; margin-top: 16px;">
        <a href="forgot-password.php" class="btn-auth-submit" style="display:inline-block; text-decoration:none; width:auto; padding: 11px 24px;">Request a New Reset Link</a>
      </div>
    <?php endif; ?>

    <div class="auth-footer">
      <a href="login.php">Back to Sign In</a>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . "/includes/footer.php";
