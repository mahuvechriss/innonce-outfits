<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$checkoutUrl = $_SESSION['beem_checkout_url'] ?? null;
$orderNumber = $_SESSION['beem_order_number'] ?? null;

if (!$checkoutUrl) {
    redirect(SITE_URL . '/account/orders.php' . ($orderNumber ? "?order=$orderNumber" : ''), 'Payment link expired. Please retry.', 'error');
}

unset($_SESSION['beem_checkout_url']);
unset($_SESSION['beem_order_number']);

header("Location: $checkoutUrl");
exit;
