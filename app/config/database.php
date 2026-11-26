<?php
/**
 * iboltnetworks E-Commerce - Database Connection (PDO Singleton)
 * 
 * Provides robust PDO connection with prepared statements,
 * transaction support, and error reporting.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO connection
     */
    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = (string)config('db_host', 'localhost');
        $port = (int)config('db_port', 3306);
        $dbName = (string)config('db_name', '');
        $user = (string)config('db_user', '');
        $pass = (string)config('db_pass', '');
        $charset = (string)config('db_charset', 'utf8mb4');
        $socket = (string)config('db_socket', '');

        // Fail safely if database configuration is missing
        if (empty($dbName) || empty($user)) {
            $msg = "Database configuration is incomplete (DB_NAME or DB_USER is empty). Ensure .env file exists and contains valid credentials.";
            error_log("[iboltnetworks] " . $msg);
            throw new PDOException(config('app_debug', false) ? $msg : "Database configuration is incomplete. Please contact technical support at +91 99110 03865.");
        }

        // Build DSN: Try socket first if socket file exists on macOS/Linux
        if (!empty($socket) && file_exists($socket)) {
            $dsn = "mysql:unix_socket={$socket};dbname={$dbName};charset={$charset}";
        } else {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci"
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
            return self::$instance;
        } catch (PDOException $e) {
            // Always log connection failures to server error log regardless of app_debug
            error_log("[iboltnetworks] Database connection failure to [{$host}:{$port}/{$dbName}] with user [{$user}]: " . $e->getMessage());
            throw new PDOException("Database connection failed: " . (config('app_debug', false) ? $e->getMessage() : "Please contact technical support at +91 99110 03865."));
        }
    }

    /**
     * Helper to execute a prepared query with bound parameters
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Helper to fetch a single row
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Helper to fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }
}
