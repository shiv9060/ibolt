<?php
declare(strict_types=1);

/**
 * Currency and Price Formatting Helpers
 * iboltnetworks E-Commerce Platform
 *
 * All prices in the database and shopping cart are stored as integer paise
 * (e.g., ₹1,499.00 = 149900 paise) to eliminate floating point rounding errors
 * and match Razorpay's native payment amount requirement.
 */

if (!function_exists('format_inr')) {
    /**
     * Format an amount in paise to Indian Rupee notation (e.g. ₹1,499 or ₹12,34,567).
     *
     * @param int $paise Amount in paise
     * @param bool $showDecimals Whether to force .00 decimals
     * @return string
     */
    function format_inr(int $paise, bool $showDecimals = false): string
    {
        $negative = $paise < 0;
        $paise = abs($paise);

        $rupees = (int)($paise / 100);
        $fraction = $paise % 100;

        // Indian numbering format: comma after last 3 digits, then every 2 digits
        $rupeeStr = (string)$rupees;
        $len = strlen($rupeeStr);

        if ($len > 3) {
            $lastThree = substr($rupeeStr, -3);
            $remaining = substr($rupeeStr, 0, $len - 3);
            // Group remainder in 2s from right to left
            $formattedRemaining = '';
            while (strlen($remaining) > 2) {
                $formattedRemaining = ',' . substr($remaining, -2) . $formattedRemaining;
                $remaining = substr($remaining, 0, strlen($remaining) - 2);
            }
            $formattedRupees = $remaining . $formattedRemaining . ',' . $lastThree;
        } else {
            $formattedRupees = $rupeeStr;
        }

        $formatted = '₹' . ($negative ? '-' : '') . $formattedRupees;

        if ($showDecimals || $fraction > 0) {
            $formatted .= '.' . str_pad((string)$fraction, 2, '0', STR_PAD_LEFT);
        }

        return $formatted;
    }
}

if (!function_exists('inr_to_paise')) {
    /**
     * Convert an INR rupee amount (float or string) safely to integer paise.
     *
     * @param float|string|int $inr
     * @return int
     */
    function inr_to_paise(float|string|int $inr): int
    {
        // Strip any commas, currency symbols, or whitespace
        if (is_string($inr)) {
            $inr = preg_replace('/[^\d.-]/', '', $inr);
        }
        return (int)round(((float)$inr) * 100);
    }
}

if (!function_exists('paise_to_inr')) {
    /**
     * Convert integer paise to a float rupee amount.
     *
     * @param int $paise
     * @return float
     */
    function paise_to_inr(int $paise): float
    {
        return round($paise / 100, 2);
    }
}
