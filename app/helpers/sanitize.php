<?php
declare(strict_types=1);

/**
 * Sanitization and Output Escaping Helpers
 * iboltnetworks E-Commerce Platform
 */

if (!function_exists('e')) {
    /**
     * Escape HTML output against XSS attacks.
     *
     * @param string|null $value
     * @return string
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('clean_string')) {
    /**
     * Clean and normalize a string input (trim, remove non-printable characters).
     *
     * @param string|null $value
     * @return string
     */
    function clean_string(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $trimmed = trim($value);
        // Remove null bytes and non-printable control characters except newline and tab
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $trimmed) ?? $trimmed;
    }
}

if (!function_exists('slugify')) {
    /**
     * Generate an SEO-friendly URL slug.
     *
     * @param string $text
     * @return string
     */
    function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        return empty($text) ? 'n-a' : $text;
    }
}

if (!function_exists('sanitize_int')) {
    /**
     * Cast input safely to integer.
     *
     * @param mixed $value
     * @param int $default
     * @return int
     */
    function sanitize_int(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT) ?: $default;
    }
}

if (!function_exists('sanitize_float')) {
    /**
     * Cast input safely to float.
     *
     * @param mixed $value
     * @param float $default
     * @return float
     */
    function sanitize_float(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $filtered = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        return is_numeric($filtered) ? (float)$filtered : $default;
    }
}
