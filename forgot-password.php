<?php
declare(strict_types=1);

/**
 * Customer Forgot Password Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . "/app/config/config.php";
require_once __DIR__ . "/app/config/database.php";
require_once __DIR__ . "/app/helpers/auth.php";
require_once __DIR__ . "/app/helpers/csrf.php";
require_once __DIR__ . "/app/helpers/sanitize.php";
require_once __DIR__ . "/app/helpers/currency.php";
require_once __DIR__ . "/app/services/MailerService.php";

init_session();

if (is_logged_in()) {
    header("Location: account/index.php");
    exit;
}

$error = "";
$success = "";
$resetUrl = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? null)) {
        $error = "Security session expired. Please submit the form again.";
    } else {
        $email = strtolower(clean_string($_POST["email"] ?? ""));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid registered email address.";
        } else {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id, name, email, is_active FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && (int)$user["is_active"] === 1) {
                // Generate a cryptographically secure token
                $token = bin2hex(random_bytes(32));
                $expiresAt = date("Y-m-d H:i:s", strtotime("+1 hour"));

                // Delete any old customer tokens for this email
                $delStmt = $db->prepare("DELETE FROM password_resets WHERE email = ? AND user_type = 'customer'");
                $delStmt->execute([$email]);

                // Insert new reset token
                $insStmt = $db->prepare("INSERT INTO password_resets (email, token, user_type, expires_at) VALUES (?, ?, 'customer', DATE_ADD(NOW(), INTERVAL 1 HOUR))");
                $insStmt->execute([$email, $token]);

                $baseUrl = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . rtrim(dirname($_SERVER["PHP_SELF"]), "/\\");
                $resetUrl = $baseUrl . "/reset-password.php?token=" . urlencode($token) . "&email=" . urlencode($email);

                // Dispatch branded reset email via MailerService
                $mailResult = MailerService::sendPasswordResetEmail($email, (string)($user["name"] ?? ""), $resetUrl, "customer");
                $success = "A secure password reset link has been emailed to <strong>" . e($email) . "</strong>. Please check your inbox and follow the instructions to set your new password.";
            } else {
                // For privacy, we show the same message or indicate account status
                $error = "No active customer account was found with that email address. Please check your spelling or register for a new account.";
            }
        }
    }
}

$pageTitle = "Reset Password | iboltnetworks";
$pageDescription = "Request a password reset link for your iboltnetworks customer account.";

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
    max-width: 480px;
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
  .alert-success {
    background: #f0fdf4;
    border: 1px solid #dcfce7;
    color: #166534;
  }
  .reset-box {
    margin-top: 20px;
    padding: 18px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    text-align: left;
  }
  .reset-box strong {
    display: block;
    font-size: 13px;
    color: #1e293b;
    margin-bottom: 6px;
  }
  .reset-link-input {
    width: 100%;
    font-size: 12.5px;
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
    background: #087fc0;
    color: #ffffff;
    padding: 10px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 700;
    font-size: 13.5px;
    transition: background 0.2s;
  }
  .btn-reset-now:hover {
    background: #06679e;
    text-decoration: none;
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

    <h1>Forgot your password?</h1>
    <p class="auth-subtitle">Enter your registered email address and we will provide you with a secure password reset link.</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success">
        <?= $success ?>
        
        <?php if (!empty($resetUrl)): ?>
          <div class="reset-box">
            <strong>Email Sent! Direct Reset Link (Active for 1 Hour):</strong>
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
          <label for="email">Registered Email Address</label>
          <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST["email"] ?? "") ?>" required autofocus placeholder="name@company.com">
        </div>

        <button type="submit" class="btn-auth-submit">Generate Reset Link</button>
      </form>
    <?php endif; ?>

    <div class="auth-footer">
      Remembered your password?
      <a href="login.php">Back to Sign In</a>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . "/includes/footer.php";
