<?php
declare(strict_types=1);

/**
 * Admin Security, Rate Limiting & Audit Hardening Helper
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/sanitize.php';

/**
 * Retrieve the validated remote client IP address.
 */
function get_client_ip(): string
{
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', (string)$_SERVER[$key]);
            $candidate = trim($ips[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }
    return '127.0.0.1';
}

/**
 * Check whether a login attempt is rate-limited / throttled.
 * Evaluates both the specific account identifier and the source IP address.
 */
function check_login_throttle(string $identifier, ?string $ipAddress = null): array
{
    $db = Database::getConnection();
    $ip = $ipAddress ?: get_client_ip();
    $cleanIdent = strtolower(trim($identifier));

    $maxIdentifierAttempts = (int)config('auth_max_identifier_attempts', 5);
    $maxIpAttempts         = (int)config('auth_max_ip_attempts', 10);
    $windowMinutes         = (int)config('auth_throttle_window_minutes', 15);

    // 1. Check account identifier attempts (compute elapsed seconds in MySQL to eliminate server/db timezone skew)
    $stmtIdent = $db->prepare("SELECT COUNT(*) AS attempt_count, TIMESTAMPDIFF(SECOND, MAX(attempted_at), NOW()) AS elapsed_seconds
                               FROM login_attempts
                               WHERE LOWER(identifier) = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmtIdent->execute([$cleanIdent, $windowMinutes]);
    $rowIdent = $stmtIdent->fetch(PDO::FETCH_ASSOC) ?: ['attempt_count' => 0, 'elapsed_seconds' => null];
    $identCount = (int)($rowIdent['attempt_count'] ?? 0);

    // 2. Check source IP attempts
    $stmtIp = $db->prepare("SELECT COUNT(*) AS attempt_count, TIMESTAMPDIFF(SECOND, MAX(attempted_at), NOW()) AS elapsed_seconds
                            FROM login_attempts
                            WHERE ip_address = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmtIp->execute([$ip, $windowMinutes]);
    $rowIp = $stmtIp->fetch(PDO::FETCH_ASSOC) ?: ['attempt_count' => 0, 'elapsed_seconds' => null];
    $ipCount = (int)($rowIp['attempt_count'] ?? 0);

    $isThrottled = ($identCount >= $maxIdentifierAttempts || $ipCount >= $maxIpAttempts);

    if ($isThrottled) {
        $elapsedIdent = $rowIdent['elapsed_seconds'] !== null ? (int)$rowIdent['elapsed_seconds'] : null;
        $elapsedIp = $rowIp['elapsed_seconds'] !== null ? (int)$rowIp['elapsed_seconds'] : null;

        $elapsed = 0;
        if ($elapsedIdent !== null && $elapsedIp !== null) {
            $elapsed = min($elapsedIdent, $elapsedIp);
        } elseif ($elapsedIdent !== null) {
            $elapsed = $elapsedIdent;
        } elseif ($elapsedIp !== null) {
            $elapsed = $elapsedIp;
        }

        $cooldownWindow = $windowMinutes * 60;
        $remainingSeconds = max(1, $cooldownWindow - max(0, $elapsed));
        $retryMinutes = (int)ceil($remainingSeconds / 60);

        return [
            'throttled'       => true,
            'retry_after'     => $retryMinutes,
            'retry_seconds'   => $remainingSeconds,
            'attempts_count'  => max($identCount, $ipCount),
            'limit'           => ($identCount >= $maxIdentifierAttempts ? $maxIdentifierAttempts : $maxIpAttempts),
            'reason'          => ($identCount >= $maxIdentifierAttempts ? 'account' : 'ip')
        ];
    }

    return [
        'throttled'      => false,
        'attempts_count' => max($identCount, $ipCount)
    ];
}

/**
 * Record a failed authentication attempt in the durable login_attempts table.
 * Also logs a security audit event without storing password data.
 */
function record_failed_login(string $identifier, ?string $ipAddress = null): void
{
    $db = Database::getConnection();
    $ip = $ipAddress ?: get_client_ip();
    $cleanIdent = strtolower(trim($identifier));

    // Durable attempt record
    $stmt = $db->prepare("INSERT INTO login_attempts (identifier, ip_address, attempted_at) VALUES (?, ?, NOW())");
    $stmt->execute([$cleanIdent, $ip]);

    // Security activity log (Strictly NO password recorded)
    try {
        $logStmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
                                 VALUES (NULL, 'login_failed', 'auth', NULL, ?, ?, ?, NOW())");
        $logStmt->execute([
            json_encode(['identifier' => $cleanIdent, 'event' => 'invalid_credentials']),
            $ip,
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255)
        ]);
    } catch (Throwable) {}
}

/**
 * Record a rate-limit lockout event in admin activity logs.
 */
function record_throttled_login(string $identifier, ?string $ipAddress = null, int $retryAfter = 15): void
{
    $db = Database::getConnection();
    $ip = $ipAddress ?: get_client_ip();
    $cleanIdent = strtolower(trim($identifier));

    try {
        $logStmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
                                 VALUES (NULL, 'login_throttled', 'auth', NULL, ?, ?, ?, NOW())");
        $logStmt->execute([
            json_encode(['identifier' => $cleanIdent, 'event' => 'rate_limit_exceeded', 'cooldown_minutes' => $retryAfter]),
            $ip,
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255)
        ]);
    } catch (Throwable) {}
}

/**
 * Reset failed login attempts after successful authentication.
 * Prunes expired records older than 24 hours.
 */
function clear_login_attempts(string $identifier, ?string $ipAddress = null): void
{
    $db = Database::getConnection();
    $ip = $ipAddress ?: get_client_ip();
    $cleanIdent = strtolower(trim($identifier));

    // Clear active counters for this identifier and IP
    $delStmt = $db->prepare("DELETE FROM login_attempts WHERE LOWER(identifier) = ? OR ip_address = ?");
    $delStmt->execute([$cleanIdent, $ip]);

    // Housekeeping: remove attempts older than 24 hours
    try {
        $db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    } catch (Throwable) {}
}

/**
 * Defend against CSV / Spreadsheet Formula Injection (CWE-1236).
 * Prepends a single apostrophe if cell content begins with =, +, -, @, tab, or carriage return.
 */
function escape_csv_cell(mixed $value): string
{
    if ($value === null) {
        return '""';
    }

    $str = (string)$value;

    // Check if cell starts with a formula injection trigger
    if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $str = "'" . $str;
    }

    return '"' . str_replace('"', '""', $str) . '"';
}
