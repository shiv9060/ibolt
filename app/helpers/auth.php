<?php
/**
 * iboltnetworks E-Commerce - Authentication & Session Helper
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/security.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent()) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return;
    }

    $lifetime = (int)config('session_lifetime', 7200);
    $isSecure = (bool)config('session_secure_cookie', false)
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');

    session_start();

    // Idle session expiration check for administrative users
    if (!empty($_SESSION['admin_logged_in'])) {
        $maxIdle = (int)config('admin_session_max_idle', 7200);
        $lastActivity = (int)($_SESSION['admin_last_activity'] ?? 0);
        if ($lastActivity > 0 && (time() - $lastActivity > $maxIdle)) {
            logout_admin();
            if (!headers_sent() && php_sapi_name() !== 'cli') {
                $isSubfolder = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/');
                $loginPath = $isSubfolder ? 'login.php?session_expired=1' : 'admin/login.php?session_expired=1';
                header('Location: ' . $loginPath);
                exit;
            }
        } else {
            $_SESSION['admin_last_activity'] = time();
        }
    }
}

function init_session(): void
{
    start_session();
}

// Automatically start session when this file is included
start_session();

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? 'Customer',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? '',
        'role'  => $_SESSION['user_role'] ?? 'customer'
    ];
}

function is_admin(): bool
{
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_logged_in']);
}

function current_admin(): ?array
{
    if (!is_admin()) {
        return null;
    }
    return [
        'id'       => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'] ?? 'admin',
        'name'     => $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Administrator',
        'email'    => $_SESSION['admin_email'] ?? '',
        'role'     => $_SESSION['admin_role'] ?? 'admin'
    ];
}

function require_login(?string $redirectUrl = null): void
{
    if (!is_logged_in()) {
        $target = $redirectUrl ?: $_SERVER['REQUEST_URI'] ?? 'index.php';
        header("Location: login.php?redirect=" . urlencode($target));
        exit;
    }
}

function require_admin(?string $redirectUrl = null): void
{
    if (!is_admin()) {
        if ($redirectUrl !== null) {
            header("Location: " . $redirectUrl);
            exit;
        }
        // Determine whether currently in admin/ or a subfolder like admin/products/
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        if (preg_match('#/admin/[^/]+/#', $scriptPath)) {
            header("Location: ../login.php");
        } elseif (str_contains($scriptPath, '/admin/')) {
            header("Location: login.php");
        } else {
            header("Location: admin/login.php");
        }
        exit;
    }
}

function login_user(array $user): void
{
    // Prevent session fixation
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'] ?? $user['full_name'] ?? 'Customer';
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_phone'] = $user['phone'] ?? '';
    $_SESSION['user_role'] = $user['role'] ?? 'customer';
    $_SESSION['logged_at'] = time();
}

function logout_user(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['user_email'],
        $_SESSION['user_phone'],
        $_SESSION['user_role'],
        $_SESSION['logged_at']
    );
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
}

function login_admin(array $admin): void
{
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = !empty($admin['username']) ? $admin['username'] : ($admin['name'] ?? 'admin');
    $_SESSION['admin_name'] = $admin['name'] ?? $admin['username'] ?? 'Administrator';
    $_SESSION['admin_email'] = $admin['email'] ?? '';
    $_SESSION['admin_role'] = $admin['role'] ?? 'super_admin';
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_logged_at'] = time();
    $_SESSION['admin_last_activity'] = time();
}

function refresh_admin_session(array $updatedAdmin): void
{
    if (isset($updatedAdmin['username'])) {
        $_SESSION['admin_username'] = $updatedAdmin['username'];
    }
    if (isset($updatedAdmin['name'])) {
        $_SESSION['admin_name'] = $updatedAdmin['name'];
    }
    if (isset($updatedAdmin['email'])) {
        $_SESSION['admin_email'] = $updatedAdmin['email'];
    }
    if (isset($updatedAdmin['role'])) {
        $_SESSION['admin_role'] = $updatedAdmin['role'];
    }
}

function logout_admin(): void
{
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin_name'],
        $_SESSION['admin_email'],
        $_SESSION['admin_role'],
        $_SESSION['admin_logged_in'],
        $_SESSION['admin_logged_at'],
        $_SESSION['admin_last_activity']
    );
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
}

