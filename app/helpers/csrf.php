<?php
/**
 * iboltnetworks E-Commerce - CSRF Protection Helper
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function validate_csrf(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || empty($token)) {
        return false;
    }

    return hash_equals($sessionToken, (string)$token);
}

function verify_csrf_token(?string $token = null): bool
{
    return validate_csrf($token);
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validate_csrf()) {
            http_response_code(403);
            die('Security check failed: Invalid or expired CSRF token. Please refresh the page and try again.');
        }
    }
}

function generate_csrf_token(): string
{
    return csrf_token();
}
