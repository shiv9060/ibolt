<?php
declare(strict_types=1);

/**
 * WhatsApp Order & Inquiry Integration Helper
 * Direct Customer-to-WhatsApp Checkout Pipeline
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/currency.php';

/**
 * Retrieve the clean, international WhatsApp phone number.
 */
function get_whatsapp_phone(): string
{
    $number = env('CONTACT_WHATSAPP', '919911003865');
    $clean = preg_replace('/[^0-9]/', '', (string)$number);
    if (strlen($clean) === 10) {
        $clean = '91' . $clean;
    }
    return !empty($clean) ? $clean : '919911003865';
}

/**
 * Build a raw wa.me URL with a custom prefilled message.
 */
function build_whatsapp_url(string $message): string
{
    return 'https://wa.me/' . get_whatsapp_phone() . '?text=' . rawurlencode($message);
}

/**
 * Generate a WhatsApp message for an order submitted on checkout.php.
 */
function build_order_whatsapp_message(array $order, array $items, array $address = []): string
{
    $lines = [];
    $lines[] = "🛒 *NEW ORDER INQUIRY - iboltnetworks*";
    $lines[] = "📋 *Order Reference:* #" . ($order['order_number'] ?? 'N/A');
    $lines[] = "";

    $lines[] = "👤 *Customer Information:*";
    $lines[] = "• *Name:* " . ($address['full_name'] ?? $order['customer_name'] ?? 'Customer');
    $lines[] = "• *Phone:* " . ($address['phone'] ?? $order['customer_phone'] ?? 'N/A');
    if (!empty($address['email'] ?? $order['customer_email'])) {
        $lines[] = "• *Email:* " . ($address['email'] ?? $order['customer_email']);
    }

    $addrLine = trim(($address['address_line1'] ?? '') . ' ' . ($address['address_line2'] ?? ''));
    $cityState = trim(($address['city'] ?? '') . ', ' . ($address['state'] ?? ''));
    $pin = $address['postal_code'] ?? '';
    if (!empty($addrLine) || !empty($cityState)) {
        $fullAddr = array_filter([$addrLine, $cityState, $pin ? "PIN: {$pin}" : '']);
        $lines[] = "• *Delivery Address:* " . implode(' - ', $fullAddr);
    }

    if (!empty($order['customer_notes'])) {
        $lines[] = "• *Notes / GSTIN:* " . $order['customer_notes'];
    }

    $lines[] = "";
    $lines[] = "📦 *Ordered Hardware (" . count($items) . " item" . (count($items) > 1 ? "s" : "") . "):*";

    foreach ($items as $idx => $item) {
        $num = $idx + 1;
        $title = $item['title'] ?? $item['product_title'] ?? 'Product';
        $sku = $item['sku'] ?? 'N/A';
        $qty = (int)($item['quantity'] ?? 1);
        $unitPrice = (int)($item['unit_price'] ?? $item['price_paise'] ?? 0);
        $lineTotal = (int)($item['line_total'] ?? $item['total_paise'] ?? ($unitPrice * $qty));

        $lines[] = "{$num}. *{$title}*";
        $lines[] = "   • SKU: `{$sku}`";
        $lines[] = "   • Qty: {$qty} × " . format_inr($unitPrice) . " = *" . format_inr($lineTotal) . "*";
    }

    $totalPaise = (int)($order['total_paise'] ?? 0);
    $lines[] = "";
    $lines[] = "💰 *Total Order Amount:* *" . format_inr($totalPaise) . "* (Free Shipping)";
    $lines[] = "";
    $lines[] = "Hello iboltnetworks, I have placed this order on your website. Please confirm stock availability, invoice, and dispatch schedule!";

    return implode("\n", $lines);
}

/**
 * Generate a WhatsApp message for instant cart checkout.
 */
function build_cart_whatsapp_message(array $cartItems, int $totalPaise): string
{
    $lines = [];
    $lines[] = "🛒 *CART CHECKOUT INQUIRY - iboltnetworks*";
    $lines[] = "";
    $lines[] = "📦 *Selected Hardware (" . count($cartItems) . " item" . (count($cartItems) > 1 ? "s" : "") . "):*";

    foreach ($cartItems as $idx => $item) {
        $num = $idx + 1;
        $title = $item['title'] ?? 'Product';
        $sku = $item['sku'] ?? 'N/A';
        $qty = (int)($item['quantity'] ?? 1);
        $unitPrice = (int)($item['unit_price'] ?? 0);
        $lineTotal = (int)($item['line_total'] ?? ($unitPrice * $qty));

        $lines[] = "{$num}. *{$title}*";
        $lines[] = "   • SKU: `{$sku}`";
        $lines[] = "   • Qty: {$qty} × " . format_inr($unitPrice) . " = *" . format_inr($lineTotal) . "*";
    }

    $lines[] = "";
    $lines[] = "💰 *Estimated Total:* *" . format_inr($totalPaise) . "* (Free Shipping)";
    $lines[] = "";
    $lines[] = "Hello iboltnetworks, I would like to purchase these items from my cart. Please assist with stock confirmation, delivery timeline, and order completion!";

    return implode("\n", $lines);
}

/**
 * Generate a WhatsApp message for a single product order/inquiry from product.php.
 */
function build_product_whatsapp_message(array $product, int $quantity = 1, string $productUrl = ''): string
{
    $unitPrice = (int)($product['price_paise'] ?? 0);
    $totalPrice = $unitPrice * max(1, $quantity);

    $lines = [];
    $lines[] = "🛍️ *PRODUCT ORDER INQUIRY - iboltnetworks*";
    $lines[] = "• *Product:* " . ($product['title'] ?? 'Hardware Item');
    $lines[] = "• *SKU / Model:* " . ($product['sku'] ?? 'N/A') . (!empty($product['model']) ? " (" . $product['model'] . ")" : "");
    if (!empty($product['brand_name'])) {
        $lines[] = "• *Brand:* " . $product['brand_name'];
    }
    $lines[] = "• *Price:* " . format_inr($unitPrice) . " each";
    $lines[] = "• *Quantity:* " . $quantity;
    $lines[] = "• *Total Amount:* *" . format_inr($totalPrice) . "*";
    if (!empty($productUrl)) {
        $lines[] = "• *Product Link:* " . $productUrl;
    }
    $lines[] = "";
    $lines[] = "Hello iboltnetworks, I would like to order this hardware model. Please confirm availability, warranty, and dispatch details!";

    return implode("\n", $lines);
}
