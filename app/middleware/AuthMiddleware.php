<?php
declare(strict_types=1);

/**
 * Authentication Middleware
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../helpers/auth.php';

class AuthMiddleware
{
    /**
     * Enforce customer authentication
     */
    public static function customer(?string $redirectUrl = null): void
    {
        require_login($redirectUrl);
    }

    /**
     * Enforce administrator authentication
     */
    public static function admin(): void
    {
        require_admin();
    }
}
