<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/pawapay.php';

$checkoutId = $_GET['checkoutId'] ?? $_SESSION['pawapay_checkout_id'] ?? null;
$orderNumber = $_SESSION['pawapay_order_number'] ?? null;

if (!$checkoutId || !$orderNumber) {
    header("Location: " . SITE_URL . "/account/orders.php");
    exit;
}

unset($_SESSION['pawapay_checkout_id'], $_SESSION['pawapay_order_number']);

// Check the checkout status from PawaPay
$result = pawapayCheckCheckoutStatus($checkoutId);

$success = false;
$message = 'Payment is being processed. Check your orders for updates.';

if ($result['status'] === 'FOUND') {
    $data = $result['data'];
    $checkoutStatus = $data['status'] ?? 'UNKNOWN';

    if ($checkoutStatus === 'COMPLETED') {
        $depositStatus = $data['deposit']['status'] ?? '';
        if ($depositStatus === 'COMPLETED') {
            $success = true;
            $message = 'Payment successful! Your order is now being processed.';

            $orderStmt = $db->prepare("SELECT id FROM orders WHERE order_number = ?");
            $orderStmt->execute([$orderNumber]);
            $order = $orderStmt->fetch();

            if ($order) {
                $db->prepare("UPDATE payment_transactions SET status = 'success', transaction_id = COALESCE(?, transaction_id), callback_data = ? WHERE order_id = ?")
                    ->execute([$data['deposit']['depositId'] ?? null, json_encode($data), $order['id']]);
                $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?")->execute([$order['id']]);
                $db->prepare("INSERT INTO order_trackings (order_id, status, description) VALUES (?, 'payment_received', 'Payment received via PawaPay.')")->execute([$order['id']]);
                require_once __DIR__ . '/../includes/notifications.php';
                notifyOrderUpdate($order['id'], 'processing', 'Payment received. Order is now processing.');
            }
        } elseif ($depositStatus === 'FAILED') {
            $message = 'Payment failed. Please try again.';
        }
    } elseif ($checkoutStatus === 'FAILED' || $checkoutStatus === 'EXPIRED') {
        $message = 'Payment was not completed. Please try again.';
    } elseif ($checkoutStatus === 'CANCELLED') {
        $message = 'Payment was cancelled.';
    }
} else {
    $message = 'Could not verify payment status. Check your orders for updates.';
}

if ($success) {
    $_SESSION['success'] = $message;
} else {
    $_SESSION['info'] = $message;
}

header("Location: " . SITE_URL . "/account/orders.php?order=" . urlencode($orderNumber));
exit;
