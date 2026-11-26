<?php
declare(strict_types=1);

/**
 * Customer Login Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/helpers/csrf.php';
require_once __DIR__ . '/app/helpers/sanitize.php';
require_once __DIR__ . '/app/helpers/currency.php';

init_session();

// If already authenticated, redirect to account dashboard
if (is_logged_in()) {
    $redirectUrl = clean_string($_GET['redirect'] ?? 'account/index.php');
    // Ensure redirect target is internal
    if (!str_starts_with($redirectUrl, 'http://') && !str_starts_with($redirectUrl, 'https://')) {
        header("Location: " . $redirectUrl);
    } else {
        header("Location: account/index.php");
    }
    exit;
}

$error = '';
$success = '';

if (!empty($_GET['registered'])) {
    $success = 'Account created successfully! You can now log in with your credentials.';
} elseif (!empty($_GET['password_reset'])) {
    $success = 'Your password has been reset successfully! You can now sign in with your new password.';
} elseif (!empty($_GET['logged_out'])) {
    $success = 'You have been successfully logged out.';
}

$redirectTarget = clean_string($_GET['redirect'] ?? $_POST['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please submit the form again.';
    } else {
        $email = strtolower(clean_string($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $db = Database::getConnection();
                $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
                    login_user($user);

                    // Handle post-login redirection
                    if (!empty($redirectTarget) && !str_starts_with($redirectTarget, 'http') && !str_starts_with($redirectTarget, '//')) {
                        header("Location: " . $redirectTarget);
                    } else {
                        header("Location: account/index.php");
                    }
                    exit;
                } else {
                    $error = 'Invalid email address or password. Please try again.';
                }
            } catch (Throwable $e) {
                error_log("[iboltnetworks] login error: " . $e->getMessage());
                $error = 'Authentication service is temporarily unavailable. Please try again shortly or contact support at +91 99110 03865.';
            }
        }
    }
}

$pageTitle = "Customer Login | iboltnetworks";
$pageDescription = "Sign in to your iboltnetworks account to track orders, manage addresses, and request server quotations.";

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
    max-width: 440px;
    padding: 40px;
  }
  .auth-brand {
    text-align: center;
    margin-bottom: 24px;
  }
  .auth-brand .brand-logo {
    display: inline-block;
    font-size: 26px;
    font-weight: 800;
    color: #111;
    text-decoration: none;
  }
  .auth-brand .brand-logo span { color: #078e49; }
  .auth-card h1 {
    font-size: 24px;
    font-weight: 800;
    color: #111827;
    margin: 0 0 8px;
    text-align: center;
  }
  .auth-subtitle {
    text-align: center;
    font-size: 14px;
    color: #64748b;
    margin: 0 0 24px;
  }
  .form-group {
    margin-bottom: 20px;
  }
  .form-group label {
    display: block;
    font-size: 13.5px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
  }
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
    padding: 12px 16px;
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

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navigation.php';
?>

<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-brand">
      <a href="index.php" class="brand-logo">ibolt<span>networks</span></a>
    </div>

    <h1>Sign in to your account</h1>
    <p class="auth-subtitle">Access your order history and hardware quotes</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php<?= !empty($redirectTarget) ? '?redirect=' . urlencode($redirectTarget) : '' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required autofocus placeholder="name@company.com">
      </div>

      <div class="form-group">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
          <label for="password" style="margin-bottom:0;">Password</label>
          <a href="forgot-password.php" style="font-size:12.5px; color:#078e49; text-decoration:none; font-weight:600;">Forgot Password?</a>
        </div>
        <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
      </div>

      <button type="submit" class="btn-auth-submit">Sign In</button>
    </form>

    <div class="auth-footer">
      Don't have an account yet?
      <a href="register.php<?= !empty($redirectTarget) ? '?redirect=' . urlencode($redirectTarget) : '' ?>">Create an Account</a>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
