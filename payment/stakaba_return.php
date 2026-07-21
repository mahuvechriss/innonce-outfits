<?php
require_once __DIR__ . '/../config.php';

$internalRef = $_SESSION['stakaba_ref'] ?? null;
$orderNumber = $_SESSION['stakaba_order_number'] ?? null;
unset($_SESSION['stakaba_ref'], $_SESSION['stakaba_order_number']);

if (!$orderNumber) {
    header("Location: " . SITE_URL . "/account/orders.php");
    exit;
}

// Check if webhook already updated the order
$stmt = $db->prepare("SELECT o.payment_status FROM orders o WHERE o.order_number = ?");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if ($order && $order['payment_status'] === 'paid') {
    $_SESSION['success'] = 'Payment successful! Your order is now being processed.';
} else {
    $_SESSION['info'] = 'Payment is being processed. Check your orders for updates.';
}

header("Location: " . SITE_URL . "/account/orders.php?order=" . urlencode($orderNumber));
exit;
