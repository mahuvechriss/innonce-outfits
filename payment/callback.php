<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// Try to find reference from: GET param, GET externalId, or body externalId
$reference = $_GET['reference'] ?? $_GET['externalId'] ?? $input['externalId'] ?? '';

if ($reference && $input) {
    $stmt = $db->prepare("SELECT * FROM payment_transactions WHERE reference = ?");
    $stmt->execute([$reference]);
    $transaction = $stmt->fetch();

    if ($transaction) {
        // AzamPay sends: status, transactionId, externalId, amount, message
        // Map AzamPay field names
        $status = $input['status'] ?? 'failed';
        $transactionId = $input['transactionId'] ?? $input['transaction_id'] ?? null;

        $newStatus = match(strtolower($status)) {
            'success', 'completed', 'paid' => 'success',
            'failed', 'error' => 'failed',
            'cancelled' => 'cancelled',
            default => 'pending',
        };

        $stmt = $db->prepare("UPDATE payment_transactions SET status = ?, transaction_id = COALESCE(?, transaction_id), callback_data = ? WHERE id = ?");
        $stmt->execute([$newStatus, $transactionId, json_encode($input), $transaction['id']]);

        if ($newStatus === 'success') {
            $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?")->execute([$transaction['order_id']]);
            $trackingDesc = 'Payment received via ' . $transaction['payment_method'];
            $db->prepare("INSERT INTO order_trackings (order_id, status, description) VALUES (?, 'payment_received', ?)")->execute([$transaction['order_id'], $trackingDesc]);
            require_once __DIR__ . '/../includes/notifications.php';
            notifyOrderUpdate($transaction['order_id'], 'processing', 'Payment received. Order is now processing.');
        } elseif ($newStatus === 'failed') {
            $db->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([$transaction['order_id']]);
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }
}

http_response_code(404);
echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
