<?php
declare(strict_types=1);

/**
 * Admin Self-Service Profile, Username & Password Management
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '';
$adminPageTitle = 'Staff Profile & Security | iboltnetworks Admin';

require_once __DIR__ . '/includes/admin-header.php';

$db = Database::getConnection();
$adminUser = current_admin();

// Fetch fresh details from database
$stmt = $db->prepare("SELECT * FROM admin_users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$adminUser['id']]);
$currentRecord = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentRecord) {
    header("Location: login.php");
    exit;
}

$profileSuccess = '';
$profileError = '';
$passwordSuccess = '';
$passwordError = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $profileError = 'Security session expired. Please refresh and try again.';
    } else {
        $action = clean_string($_POST['form_action'] ?? '');

        // 1. UPDATE USERNAME & PROFILE
        if ($action === 'update_profile') {
            $newUsername = strtolower(clean_string($_POST['username'] ?? ''));
            $newName = clean_string($_POST['name'] ?? '');
            $newEmail = strtolower(clean_string($_POST['email'] ?? ''));

            if (empty($newUsername) || strlen($newUsername) < 3) {
                $profileError = 'Username must be at least 3 characters long.';
            } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $newUsername)) {
                $profileError = 'Username can only contain letters, numbers, hyphens, underscores, and dots.';
            } elseif (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $profileError = 'Please provide a valid administrator email address.';
            } elseif (empty($newName)) {
                $profileError = 'Please provide a display name.';
            } else {
                // Check username uniqueness
                $uCheck = $db->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ? LIMIT 1");
                $uCheck->execute([$newUsername, (int)$adminUser['id']]);
                if ($uCheck->fetch()) {
                    $profileError = "The username '{$newUsername}' is already taken by another administrator.";
                } else {
                    // Check email uniqueness
                    $eCheck = $db->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ? LIMIT 1");
                    $eCheck->execute([$newEmail, (int)$adminUser['id']]);
                    if ($eCheck->fetch()) {
                        $profileError = "The email '{$newEmail}' is already registered with another administrator.";
                    } else {
                        // Update database
                        $updStmt = $db->prepare("UPDATE admin_users SET username = ?, name = ?, email = ? WHERE id = ?");
                        $updStmt->execute([$newUsername, $newName, $newEmail, (int)$adminUser['id']]);

                        // Refresh active session variables immediately
                        refresh_admin_session([
                            'username' => $newUsername,
                            'name'     => $newName,
                            'email'    => $newEmail
                        ]);

                        // Audit log
                        try {
                            $logStmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, 'profile_update', 'admin_user', ?, ?, ?, ?)");
                            $logStmt->execute([
                                (int)$adminUser['id'],
                                (int)$adminUser['id'],
                                json_encode(['username' => $newUsername, 'email' => $newEmail, 'name' => $newName]),
                                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                                substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255)
                            ]);
                        } catch (Exception $e) {}

                        // Refresh local record
                        $currentRecord['username'] = $newUsername;
                        $currentRecord['name'] = $newName;
                        $currentRecord['email'] = $newEmail;
                        $admin['username'] = $newUsername;

                        $profileSuccess = "Administrator username and profile updated successfully! You can now log in using either '{$newUsername}' or '{$newEmail}'.";
                    }
                }
            }
        }

        // 2. UPDATE PASSWORD
        elseif ($action === 'update_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $passwordError = 'Please fill in all password fields.';
            } elseif (strlen($newPassword) < 8) {
                $passwordError = 'New password must be at least 8 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $passwordError = 'New password and confirmation do not match.';
            } else {
                $currentHash = $currentRecord['password_hash'] ?? '';
                $isCurrentValid = password_verify($currentPassword, $currentHash);

                if (!$isCurrentValid) {
                    $passwordError = 'Your current password was entered incorrectly.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updStmt = $db->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
                    $updStmt->execute([$newHash, (int)$adminUser['id']]);
                    if (!headers_sent()) {
                        session_regenerate_id(true);
                    }

                    // Audit log
                    try {
                        $logStmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, 'password_change', 'admin_user', ?, ?, ?, ?)");
                        $logStmt->execute([
                            (int)$adminUser['id'],
                            (int)$adminUser['id'],
                            json_encode(['note' => 'Admin updated secret password']),
                            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                            substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255)
                        ]);
                    } catch (Exception $e) {}

                    $currentRecord['password_hash'] = $newHash;
                    $passwordSuccess = 'Administrator password updated successfully! Your new password is now active.';
                }
            }
        }
    }
}
?>

<div class="admin-container" style="max-width: 900px; margin: 30px auto;">

  <div class="admin-page-header" style="margin-bottom: 24px;">
    <div>
      <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 6px;">Staff Account &amp; Security Settings</h1>
      <p style="color: #64748b; margin: 0; font-size: 14px;">Manage your administrator username, login email, display name, and login password.</p>
    </div>
    <div>
      <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">

    <!-- CARD 1: CHANGE USERNAME & PROFILE -->
    <div class="admin-card" style="padding: 24px; border-radius: 12px; height: fit-content;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
        <span style="font-size: 24px;">👤</span>
        <div>
          <h2 style="font-size: 18px; font-weight: 800; margin: 0; color: #1e293b;">Admin Profile &amp; Username</h2>
          <span style="font-size: 12px; color: #64748b;">Change your login username &amp; email</span>
        </div>
      </div>

      <?php if (!empty($profileSuccess)): ?>
        <div class="alert alert-success" style="font-size: 13.5px;"><?= e($profileSuccess) ?></div>
      <?php endif; ?>

      <?php if (!empty($profileError)): ?>
        <div class="alert alert-error" style="font-size: 13.5px;"><?= e($profileError) ?></div>
      <?php endif; ?>

      <form method="post" action="change-password.php">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="update_profile">

        <div class="form-group">
          <label for="username">Administrator Username *</label>
          <input type="text" id="username" name="username" class="form-control" value="<?= e($currentRecord['username'] ?? 'admin') ?>" required minlength="3" maxlength="50" placeholder="e.g. admin or custom handle">
          <small style="color: #64748b; font-size: 11.5px; display: block; margin-top: 4px;">Used to sign into the admin panel (letters, numbers, hyphens, underscores).</small>
        </div>

        <div class="form-group">
          <label for="name">Display / Staff Full Name *</label>
          <input type="text" id="name" name="name" class="form-control" value="<?= e($currentRecord['name'] ?? 'System Administrator') ?>" required placeholder="e.g. System Administrator">
        </div>

        <div class="form-group">
          <label for="email">Administrator Email Address *</label>
          <input type="email" id="email" name="email" class="form-control" value="<?= e($currentRecord['email'] ?? 'sales@iboltnetworks.com') ?>" required placeholder="sales@iboltnetworks.com">
          <small style="color: #64748b; font-size: 11.5px; display: block; margin-top: 4px;">Receives system notifications and can also be used to sign in.</small>
        </div>

        <div class="form-group">
          <label>Administrator Role</label>
          <div style="padding: 9px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; font-weight: 700; color: #0369a1; text-transform: uppercase;">
            🛡️ <?= e($currentRecord['role'] ?? 'super_admin') ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; min-height: 44px; margin-top: 8px;">
          Save Username &amp; Profile Details
        </button>
      </form>
    </div>

    <!-- CARD 2: CHANGE PASSWORD -->
    <div class="admin-card" style="padding: 24px; border-radius: 12px; height: fit-content;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
        <span style="font-size: 24px;">🔒</span>
        <div>
          <h2 style="font-size: 18px; font-weight: 800; margin: 0; color: #1e293b;">Change Login Password</h2>
          <span style="font-size: 12px; color: #64748b;">Set a new secure access password</span>
        </div>
      </div>

      <?php if (!empty($passwordSuccess)): ?>
        <div class="alert alert-success" style="font-size: 13.5px;"><?= e($passwordSuccess) ?></div>
      <?php endif; ?>

      <?php if (!empty($passwordError)): ?>
        <div class="alert alert-error" style="font-size: 13.5px;"><?= e($passwordError) ?></div>
      <?php endif; ?>

      <form method="post" action="change-password.php">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="update_password">

        <div class="form-group">
          <label for="current_password">Current Password *</label>
          <div style="position: relative;">
            <input type="password" id="current_password" name="current_password" class="form-control" required placeholder="Enter current password" style="padding-right: 44px;">
            <button type="button" onclick="togglePass('current_password', this)" style="position: absolute; right: 2px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px; color: #64748b; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center;" title="Show / Hide Password" aria-label="Toggle password visibility">👁️</button>
          </div>
        </div>

        <div class="form-group">
          <label for="new_password">New Password (Min. 8 chars) *</label>
          <div style="position: relative;">
            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" placeholder="Create new password" style="padding-right: 44px;" oninput="checkStrength(this.value)">
            <button type="button" onclick="togglePass('new_password', this)" style="position: absolute; right: 2px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px; color: #64748b; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center;" title="Show / Hide Password" aria-label="Toggle password visibility">👁️</button>
          </div>
          <div id="strengthMeter" style="font-size: 11.5px; margin-top: 4px; color: #64748b;">At least 8 characters with letters &amp; numbers</div>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm New Password *</label>
          <div style="position: relative;">
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" placeholder="Re-type new password" style="padding-right: 44px;">
            <button type="button" onclick="togglePass('confirm_password', this)" style="position: absolute; right: 2px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px; color: #64748b; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center;" title="Show / Hide Password" aria-label="Toggle password visibility">👁️</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; min-height: 44px; margin-top: 8px;">
          Update Administrator Password
        </button>
      </form>
    </div>

  </div>

  <!-- ACCOUNT OVERVIEW & ACTIVITY -->
  <div class="admin-card" style="margin-top: 24px; padding: 22px 28px; border-radius: 12px;">
    <h3 style="font-size: 15px; font-weight: 700; color: #1e293b; margin: 0 0 14px;">Security &amp; Session Audit</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; font-size: 13px;">
      <div>
        <span style="color: #64748b; display: block; font-size: 11.5px; text-transform: uppercase; font-weight: 700;">Account ID</span>
        <strong>#<?= (int)$currentRecord['id'] ?></strong>
      </div>
      <div>
        <span style="color: #64748b; display: block; font-size: 11.5px; text-transform: uppercase; font-weight: 700;">Active Username</span>
        <code><?= e($currentRecord['username'] ?? 'admin') ?></code>
      </div>
      <div>
        <span style="color: #64748b; display: block; font-size: 11.5px; text-transform: uppercase; font-weight: 700;">Registered Email</span>
        <span><?= e($currentRecord['email']) ?></span>
      </div>
      <div>
        <span style="color: #64748b; display: block; font-size: 11.5px; text-transform: uppercase; font-weight: 700;">Last Session Login</span>
        <span><?= !empty($currentRecord['last_login_at']) ? date('M d, Y h:i A', strtotime($currentRecord['last_login_at'])) : 'Recent session' ?></span>
      </div>
    </div>
  </div>

</div>

<script>
  function togglePass(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      btn.textContent = '🙈';
    } else {
      input.type = 'password';
      btn.textContent = '👁️';
    }
  }

  function checkStrength(val) {
    const meter = document.getElementById('strengthMeter');
    if (!meter) return;
    if (val.length === 0) {
      meter.textContent = 'At least 8 characters with letters & numbers';
      meter.style.color = '#64748b';
    } else if (val.length < 8) {
      meter.textContent = 'Too short (minimum 8 characters required)';
      meter.style.color = '#dc2626';
    } else if (val.length >= 8 && /[A-Z]/.test(val) && /[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val)) {
      meter.textContent = 'Strong password ✓';
      meter.style.color = '#166534';
    } else {
      meter.textContent = 'Good password (add numbers or special characters for extra security)';
      meter.style.color = '#d97706';
    }
  }
</script>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
