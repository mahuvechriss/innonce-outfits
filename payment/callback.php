<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/pawapay.php';
require_once __DIR__ . '/../includes/stakaba.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// ============================================================
// Stakaba Webhook Handling
// ============================================================
$logFile = __DIR__ . '/../storage/logs/webhook.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
file_put_contents($logFile, date('Y-m-d H:i:s') . ' RECEIVED: ' . file_get_contents('php://input') . "\n", FILE_APPEND);

if (!empty($input['event']) && in_array($input['event'], ['transaction.success', 'transaction.failed'])) {
    $internalRef = $input['internalReference'] ?? '';
    $status = $input['status'] ?? '';
    $metadata = $input['metadata'] ?? [];
    $orderId = $metadata['orderId'] ?? null;

    if (!$internalRef) {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // Try to find the transaction by order_id from metadata, or by internalReference
    $transaction = null;
    if ($orderId) {
        $stmt = $db->prepare("SELECT pt.*, o.id as oid FROM payment_transactions pt JOIN orders o ON pt.order_id = o.id WHERE o.id = ? AND pt.payment_method = 'stakaba'");
        $stmt->execute([$orderId]);
        $transaction = $stmt->fetch();
    }
    if (!$transaction) {
        $stmt = $db->prepare("SELECT pt.*, o.id as oid FROM payment_transactions pt JOIN orders o ON pt.order_id = o.id WHERE pt.transaction_id = ?");
        $stmt->execute([$internalRef]);
        $transaction = $stmt->fetch();
    }

    if ($transaction) {
        $newStatus = $status === 'SUCCESS' ? 'success' : 'failed';

        $db->prepare("UPDATE payment_transactions SET status = ?, transaction_id = COALESCE(?, transaction_id), callback_data = ? WHERE id = ?")
            ->execute([$newStatus, $internalRef, json_encode($input), $transaction['id']]);

        if ($newStatus === 'success') {
            $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?")->execute([$transaction['oid']]);
            $db->prepare("INSERT INTO order_trackings (order_id, status, description) VALUES (?, 'payment_received', 'Payment received via card (Stakaba).')")->execute([$transaction['oid']]);
            require_once __DIR__ . '/../includes/notifications.php';
            notifyOrderUpdate($transaction['oid'], 'processing', 'Payment received. Order is now processing.');
        } elseif ($newStatus === 'failed') {
            $db->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([$transaction['oid']]);
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// ============================================================
// PawaPay Callback Handling
// ============================================================
// PawaPay sends callbacks to this URL with payment status updates.
// Whitelisted IPs: Sandbox 3.64.89.224, Production: see pawapay.php
if (!empty($input['checkoutId']) || !empty($input['depositId'])) {
    // Optional: verify the sender IP is from PawaPay
    // Uncomment the line below to enforce IP whitelisting:
    // if (!pawapayIsValidCallbackIp()) { http_response_code(403); echo json_encode(['status' => 'error', 'message' => 'Forbidden']); exit; }

    $checkoutId = $input['checkoutId'] ?? null;
    $depositId = $input['deposit']['depositId'] ?? $input['depositId'] ?? null;

    if ($checkoutId) {
        // For checkout callbacks
        $status = $input['status'] ?? '';
        $clientRefId = $input['clientReferenceId'] ?? '';
        $depositData = $input['deposit'] ?? [];
        $depositStatus = $depositData['status'] ?? '';

        if ($clientRefId) {
            $stmt = $db->prepare("SELECT pt.*, o.id as oid FROM payment_transactions pt JOIN orders o ON pt.order_id = o.id WHERE o.order_number = ?");
            $stmt->execute([$clientRefId]);
            $transaction = $stmt->fetch();

            if ($transaction) {
                $newStatus = match($status) {
                    'COMPLETED' => $depositStatus === 'COMPLETED' ? 'success' : 'pending',
                    'FAILED', 'EXPIRED' => 'failed',
                    'CANCELLED' => 'cancelled',
                    default => 'pending',
                };

                $db->prepare("UPDATE payment_transactions SET status = ?, transaction_id = COALESCE(?, transaction_id), callback_data = ? WHERE id = ?")
                    ->execute([$newStatus, $depositId, json_encode($input), $transaction['id']]);

                if ($newStatus === 'success') {
                    $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?")->execute([$transaction['oid']]);
                    $db->prepare("INSERT INTO order_trackings (order_id, status, description) VALUES (?, 'payment_received', 'Payment received via PawaPay.')")->execute([$transaction['oid']]);
                    require_once __DIR__ . '/../includes/notifications.php';
                    notifyOrderUpdate($transaction['oid'], 'processing', 'Payment received. Order is now processing.');
                } elseif ($newStatus === 'failed') {
                    $db->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([$transaction['oid']]);
                }

                http_response_code(200);
                echo json_encode(['status' => 'ok']);
                exit;
            }
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// ============================================================
// Legacy / Other provider callback handling (AzamPay, Beem)
// ============================================================
$reference = $_GET['reference'] ?? $_GET['externalId'] ?? $input['externalId'] ?? '';

if (empty($reference)) {
    $rawRef = $input['referenceNumber'] ?? $input['reference_number'] ?? $_GET['referenceNumber'] ?? $_GET['reference_number'] ?? '';
    if ($rawRef) {
        $parts = explode('-', $rawRef, 2);
        $reference = $parts[1] ?? $rawRef;
    }
}

if ($reference && $input) {
    $stmt = $db->prepare("SELECT * FROM payment_transactions WHERE reference = ?");
    $stmt->execute([$reference]);
    $transaction = $stmt->fetch();

    if ($transaction) {
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
        echo json_encode([
            'amount' => $transaction['amount'],
            'status' => $newStatus,
            'referenceNumber' => $input['referenceNumber'] ?? ($input['reference_number'] ?? ''),
            'statusMessage' => 'Payment processed successfully',
            'transactionId' => $transactionId,
        ]);
        exit;
    }
}

http_response_code(404);
echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
