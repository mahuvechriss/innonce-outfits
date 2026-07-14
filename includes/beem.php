<?php

function sendSms(string $phone, string $message): bool {
    $apiKey = getSetting('beem_api_key', '');
    $secretKey = getSetting('beem_secret_key', '');
    $senderId = trim(preg_replace('/[^a-zA-Z0-9]/', '', getSetting('beem_sender_id', 'CHILDAFYA')));
    $senderId = substr($senderId, 0, 11);

    if (empty($apiKey) || empty($secretKey)) {
        error_log("Beem Africa: API credentials not configured.");
        return false;
    }

    $phone = ltrim($phone, '+');
    if (strlen($phone) === 9) {
        $phone = '255' . $phone;
    } elseif (strlen($phone) === 10 && $phone[0] === '0') {
        $phone = '255' . substr($phone, 1);
    } elseif (strlen($phone) === 13) {
        $phone = substr($phone, 1);
    }

    $postData = json_encode([
        'source_addr' => $senderId,
        'schedule_time' => '',
        'encoding' => '0',
        'message' => $message,
        'recipients' => [
            ['recipient_id' => '1', 'dest_addr' => $phone]
        ]
    ]);

    $ch = curl_init('https://apisms.beem.africa/v1/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$apiKey:$secretKey"),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log("Beem SMS sent to $phone: $response");
        return true;
    }

    error_log("Beem SMS failed ($httpCode) to $phone — response: " . ($response ?: 'EMPTY') . " — curl: " . ($curlError ?: 'none'));
    return false;
}

function beemPaymentBaseUrl(): string {
    return getSetting('beem_payment_environment', 'sandbox') === 'production'
        ? 'https://checkout.beem.africa'
        : 'https://checkout.beem.africa';
}

function beemFormatPhone(string $phone): string {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 9) {
        return '255' . $phone;
    } elseif (strlen($phone) === 10 && $phone[0] === '0') {
        return '255' . substr($phone, 1);
    } elseif (strlen($phone) === 12 && str_starts_with($phone, '255')) {
        return $phone;
    } elseif (strlen($phone) === 13 && str_starts_with($phone, '255')) {
        return $phone;
    }
    if (!str_starts_with($phone, '255')) {
        $phone = '255' . $phone;
    }
    return $phone;
}

function beemInitiatePayment(float $amount, string $phone, string $reference, string $transactionId): array {
    $apiKey = getSetting('beem_payment_api_key', '');
    $secretKey = getSetting('beem_payment_secret_key', '');
    $referencePrefix = getSetting('beem_reference_prefix', 'INNOCE');

    if (empty($apiKey) || empty($secretKey)) {
        return ['success' => false, 'message' => 'Beem payment not configured.'];
    }

    $payload = [
        'amount' => (int) round($amount),
        'transaction_id' => $transactionId,
        'reference_number' => strtoupper($referencePrefix . '-' . $reference),
        'mobile' => beemFormatPhone($phone),
        'sendSource' => true,
    ];

    $ch = curl_init(beemPaymentBaseUrl() . '/v1/checkout');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$apiKey:$secretKey"),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $body = json_decode($resp, true) ?: [];

    if ($httpCode >= 200 && $httpCode < 300 && !empty($body['data']['checkoutUrl'])) {
        return [
            'success' => true,
            'checkout_url' => $body['data']['checkoutUrl'],
            'transaction_id' => $body['data']['transactionId'] ?? $transactionId,
            'message' => 'Redirecting to Beem checkout...',
            'raw' => $body,
        ];
    }

    $errorMsg = $body['message'] ?? $body['error'] ?? 'Beem payment request failed (HTTP ' . $httpCode . ').';
    if ($curlError) {
        $errorMsg .= ' Curl: ' . $curlError;
    }
    return [
        'success' => false,
        'message' => $errorMsg,
        'raw' => $body,
    ];
}