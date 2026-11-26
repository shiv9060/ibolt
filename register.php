<?php
declare(strict_types=1);

/**
 * Customer Registration Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/helpers/csrf.php';
require_once __DIR__ . '/app/helpers/sanitize.php';
require_once __DIR__ . '/app/helpers/currency.php';

init_session();

if (is_logged_in()) {
    header("Location: account/index.php");
    exit;
}

$error = '';
$redirectTarget = clean_string($_GET['redirect'] ?? $_POST['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please submit the form again.';
    } else {
        $name = clean_string($_POST['name'] ?? '');
        $email = strtolower(clean_string($_POST['email'] ?? ''));
        $phone = clean_string($_POST['phone'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirmation'] ?? '');

        if (empty($name) || strlen($name) < 2) {
            $error = 'Please enter your full name (minimum 2 characters).';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwords do not match. Please verify and retype.';
        } else {
            try {
                $db = Database::getConnection();

                // Check if email already registered
                $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    $error = 'An account with this email address already exists. Please log in instead.';
                } else {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $insertStmt = $db->prepare("INSERT INTO users (name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, 1)");
                    $insertStmt->execute([$name, $email, $phone, $passwordHash]);
                    $newUserId = (int)$db->lastInsertId();

                    // Fetch new user and log in automatically
                    $userStmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                    $userStmt->execute([$newUserId]);
                    $newUser = $userStmt->fetch();

                    login_user($newUser);

                    if (!empty($redirectTarget) && !str_starts_with($redirectTarget, 'http') && !str_starts_with($redirectTarget, '//')) {
                        header("Location: " . $redirectTarget);
                    } else {
                        header("Location: account/index.php?welcome=1");
                    }
                    exit;
                }
            } catch (Throwable $e) {
                error_log("[iboltnetworks] register error: " . $e->getMessage());
                $error = 'Registration service is temporarily unavailable. Please try again shortly or contact support at +91 99110 03865.';
            }
        }
    }
}

$pageTitle = "Create Customer Account | iboltnetworks";
$pageDescription = "Create a customer account on iboltnetworks to purchase enterprise compute hardware, manage shipping addresses, and view invoices.";

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
  .auth-brand { text-align: center; margin-bottom: 24px; }
  .auth-brand .brand-logo { font-size: 26px; font-weight: 800; color: #111; text-decoration: none; }
  .auth-brand .brand-logo span { color: #078e49; }
  .auth-card h1 { font-size: 24px; font-weight: 800; color: #111827; margin: 0 0 8px; text-align: center; }
  .auth-subtitle { text-align: center; font-size: 14px; color: #64748b; margin: 0 0 24px; }
  .form-group { margin-bottom: 18px; }
  .form-group label { display: block; font-size: 13.5px; font-weight: 700; color: #334155; margin-bottom: 6px; }
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
    margin-top: 6px;
  }
  .btn-auth-submit:hover { background: #06773d; }
  .alert-error {
    background: #fef2f2;
    border: 1px solid #fee2e2;
    color: #991b1b;
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 13.5px;
    margin-bottom: 20px;
  }
  .auth-footer {
    text-align: center;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #eef2f6;
    font-size: 13.5px;
    color: #64748b;
  }
  .auth-footer a { color: #078e49; font-weight: 700; text-decoration: none; }
  .auth-footer a:hover { text-decoration: underline; }
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

    <h1>Create Customer Account</h1>
    <p class="auth-subtitle">Register to manage orders and track shipments</p>

    <?php if (!empty($error)): ?>
      <div class="alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="register.php<?= !empty($redirectTarget) ? '?redirect=' . urlencode($redirectTarget) : '' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">

      <div class="form-group">
        <label for="name">Full Name *</label>
        <input type="text" id="name" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required autofocus placeholder="John Doe">
      </div>

      <div class="form-group">
        <label for="email">Email Address *</label>
        <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required placeholder="john@example.com">
      </div>

      <div class="form-group">
        <label for="phone">Phone / Mobile (Optional)</label>
        <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+91 98765 43210">
      </div>

      <div class="form-group">
        <label for="password">Password (Minimum 8 Characters) *</label>
        <input type="password" id="password" name="password" class="form-control" required minlength="8" placeholder="••••••••">
      </div>

      <div class="form-group">
        <label for="password_confirmation">Confirm Password *</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="8" placeholder="••••••••">
      </div>

      <button type="submit" class="btn-auth-submit">Create Account</button>
    </form>

    <div class="auth-footer">
      Already have an account?
      <a href="login.php<?= !empty($redirectTarget) ? '?redirect=' . urlencode($redirectTarget) : '' ?>">Sign In</a>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
