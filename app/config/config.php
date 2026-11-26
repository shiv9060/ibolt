<?php
/**
 * iboltnetworks E-Commerce - Configuration Loader
 * 
 * Safely parses .env file without external composer dependencies,
 * making it 100% compatible with MAMP and Hostinger shared hosting.
 */

declare(strict_types=1);

if (!defined('IBOLT_INIT')) {
    define('IBOLT_INIT', true);
}

// Global configuration repository
class Config
{
    private static array $settings = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Default configurations (Safe production defaults for Hostinger shared hosting)
        self::$settings = [
            'app_env' => 'production',
            'app_debug' => false,
            'app_url' => 'https://iboltnetworks.com',
            'app_name' => 'iboltnetworks',
            'db_host' => 'localhost',
            'db_port' => 3306,
            'db_name' => '',
            'db_user' => '',
            'db_pass' => '',
            'db_charset' => 'utf8mb4',
            'db_socket' => '',
            'razorpay_key_id' => '',
            'razorpay_key_secret' => '',
            'razorpay_currency' => 'INR',
            'session_lifetime' => 7200,
            'session_secure_cookie' => true,
            'store_email' => 'sales@iboltnetworks.com',
            'store_phone' => '+91 99110 03865',
            'contact_whatsapp' => '919911003865',
            'store_address' => 'Vishal Bhawan, Nehru Place, New Delhi, Delhi 110019',
            'mail_driver' => 'smtp',
            'smtp_host' => 'smtp.hostinger.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_user' => 'sales@iboltnetworks.com',
            'smtp_pass' => '',
            'mail_from_address' => 'sales@iboltnetworks.com',
            'mail_from_name' => 'iboltnetworks Support',
        ];

        // Parse .env if it exists (checks parent folder outside public_html first for hardened security)
        $envCandidates = [
            dirname(__DIR__, 3) . '/.env',
            dirname(__DIR__, 2) . '/.env',
            dirname(__DIR__) . '/.env',
        ];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $envCandidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/.env';
        }

        $envFile = null;
        foreach ($envCandidates as $candidate) {
            if (file_exists($candidate) && is_readable($candidate)) {
                $envFile = $candidate;
                break;
            }
        }
        if ($envFile !== null) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = strtolower(trim($key));
                    $value = trim($value);
                    // Remove enclosing quotes
                    if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match("/^'(.*)'$/", $value, $matches)) {
                        $value = $matches[1];
                    }
                    if ($value === 'true') {
                        $value = true;
                    } elseif ($value === 'false') {
                        $value = false;
                    } elseif ($value === 'null') {
                        $value = null;
                    }
                    self::$settings[$key] = $value;
                }
            }
        }

        // Support environment variables from getenv / $_ENV as overrides
        foreach (self::$settings as $key => $defaultVal) {
            $upperKey = strtoupper($key);
            $envVal = getenv($upperKey);
            if ($envVal === false && isset($_ENV[$upperKey])) {
                $envVal = $_ENV[$upperKey];
            }
            if ($envVal !== false && $envVal !== null && $envVal !== '') {
                if ($envVal === 'true') {
                    $envVal = true;
                } elseif ($envVal === 'false') {
                    $envVal = false;
                }
                self::$settings[$key] = $envVal;
            }
        }

        // Configure server-level PHP error handling
        $debug = (bool)(self::$settings['app_debug'] ?? false);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting($debug ? E_ALL : (E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT));

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        $normalizedKey = strtolower($key);
        return self::$settings[$normalizedKey] ?? $default;
    }

    public static function hasDatabaseConfig(): bool
    {
        self::load();
        $dbName = (string)(self::$settings['db_name'] ?? '');
        $dbUser = (string)(self::$settings['db_user'] ?? '');
        return ($dbName !== '' && $dbUser !== '');
    }
}

// Global helper functions
function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('render_controlled_error_page')) {
    /**
     * Render a professional, branded error page without leaking credentials or stack traces.
     */
    function render_controlled_error_page(string $title, string $message, int $statusCode = 500): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $brandName = (string)config('app_name', 'iboltnetworks');
        $phone = (string)config('store_phone', '+91 99110 03865');
        $whatsapp = (string)config('contact_whatsapp', '919911003865');
        $email = (string)config('store_email', 'sales@iboltnetworks.com');
        $cleanWa = preg_replace('/[^0-9]/', '', $whatsapp);
        if (empty($cleanWa)) {
            $cleanWa = '919911003865';
        }
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> | <?= htmlspecialchars($brandName) ?></title>
  <style>
    :root { --primary-green: #0d8244; --accent-green: #20e58a; --dark-navy: #051833; --bg-light: #f5f8fa; --text-muted: #5e6d82; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: var(--bg-light); color: var(--dark-navy); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; }
    .error-card { background: #ffffff; border: 1px solid #e1e8ed; border-radius: 12px; max-width: 620px; width: 100%; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); text-align: center; }
    .brand-mark { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; background: linear-gradient(135deg, var(--primary-green), #075e2f); color: #fff; font-weight: 800; font-size: 24px; border-radius: 12px; margin-bottom: 20px; letter-spacing: -0.5px; }
    .status-badge { display: inline-block; padding: 4px 12px; background: #eaf8f0; color: var(--primary-green); border-radius: 20px; font-size: 13px; font-weight: 700; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; }
    h1 { font-size: 24px; font-weight: 700; color: var(--dark-navy); margin-bottom: 14px; line-height: 1.3; }
    p { font-size: 15px; color: var(--text-muted); line-height: 1.6; margin-bottom: 28px; }
    .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-bottom: 24px; }
    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; cursor: pointer; transition: all 0.2s; }
    .btn-wa { background: #25d366; color: #ffffff; }
    .btn-wa:hover { background: #1ebc59; }
    .btn-outline { background: #ffffff; border: 1px solid #ccd6dd; color: var(--dark-navy); }
    .btn-outline:hover { background: #f5f8fa; border-color: #aab8c2; }
    .contact-info { border-top: 1px solid #edf2f6; padding-top: 20px; font-size: 13px; color: var(--text-muted); }
    .contact-info a { color: var(--primary-green); text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>
  <div class="error-card">
    <div class="brand-mark">iB</div>
    <div><span class="status-badge"><?= $statusCode === 503 ? 'Service Notice' : 'System Notice' ?></span></div>
    <h1><?= htmlspecialchars($title) ?></h1>
    <p><?= htmlspecialchars($message) ?></p>
    <div class="actions">
      <a class="btn btn-wa" href="https://wa.me/<?= htmlspecialchars($cleanWa) ?>" target="_blank" rel="noopener noreferrer">💬 Chat on WhatsApp</a>
      <a class="btn btn-outline" href="tel:<?= htmlspecialchars($cleanWa) ?>">📞 Call Sales: <?= htmlspecialchars($phone) ?></a>
      <a class="btn btn-outline" href="javascript:location.reload()">⟳ Retry</a>
    </div>
    <div class="contact-info">
      Enterprise Hardware Sales &amp; Support: <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
    </div>
  </div>
</body>
</html>
        <?php
    }
}

// Initialize on include
Config::load();
