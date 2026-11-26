<?php
declare(strict_types=1);

/**
 * Order Calculation & Pricing Service
 * iboltnetworks E-Commerce Platform
 */

class OrderService
{
    public const GST_RATE = 0.18; // 18% standard GST for server hardware & components
    public const FREE_SHIPPING_THRESHOLD_PAISE = 500000; // ₹5,000 in paise
    public const STANDARD_SHIPPING_PAISE = 49900; // ₹499 in paise

    /**
     * Calculate comprehensive financial breakdown for a given subtotal
     *
     * @param int $subtotalPaise Amount in paise
     * @return array{subtotal: int, gst: int, shipping: int, total: int}
     */
    public static function calculateBreakdown(int $subtotalPaise): array
    {
        $gstPaise = (int)round($subtotalPaise * self::GST_RATE);
        $shippingPaise = ($subtotalPaise >= self::FREE_SHIPPING_THRESHOLD_PAISE || $subtotalPaise === 0)
            ? 0
            : self::STANDARD_SHIPPING_PAISE;
        $totalPaise = $subtotalPaise + $gstPaise + $shippingPaise;

        return [
            'subtotal' => $subtotalPaise,
            'gst'      => $gstPaise,
            'shipping' => $shippingPaise,
            'total'    => $totalPaise,
        ];
    }
}
