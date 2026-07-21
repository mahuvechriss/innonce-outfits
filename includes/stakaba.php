<?php

function stakabaApiKey(): ?string {
    $v = getSetting('stakaba_api_key', '');
    if ($v) return $v;
    return env('STAKABA_API_KEY', null);
}

function stakabaIsSandbox(): bool {
    $key = stakabaApiKey();
    return $key && str_starts_with($key, 'sk_test_');
}

function stakabaCheckout(float $amount, string $orderNumber, int $orderId, string $email, string $phone, string $name): array {
    $key = stakabaApiKey();
    if (!$key) {
        return ['success' => false, 'message' => 'Stakaba not configured (no API key).'];
    }

    $phone = ltrim($phone, '+');
    if (!str_starts_with($phone, '255')) {
        $phone = '255' . ltrim($phone, '0');
    }

    $payload = [
        'grossAmount' => $amount,
        'currency' => 'TZS',
        'customerEmail' => $email,
        'customerName' => $name,
        'customerPhone' => $phone,
        'billingAddress' => 'Tanzania',
        'metadata' => [
            'orderId' => (string) $orderId,
            'orderNumber' => $orderNumber,
        ],
    ];

    $ch = curl_init('https://api.stakaba.com/api/v1/payments/card');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $key,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $body = json_decode($resp, true) ?: [];

    if ($httpCode === 201 && !empty($body['checkoutUrl'])) {
        return [
            'success' => true,
            'internal_reference' => $body['internalReference'] ?? '',
            'redirect_url' => $body['checkoutUrl'],
            'message' => 'Redirecting to card payment...',
            'raw' => $body,
        ];
    }

    $errorMsg = $body['detail'] ?? $body['message'] ?? 'Stakaba checkout failed (HTTP ' . $httpCode . ').';
    if ($curlError) $errorMsg .= ' Curl: ' . $curlError;
    return [
        'success' => false,
        'message' => $errorMsg,
        'raw' => $body,
    ];
}

function stakabaGetTransaction(string $internalReference): array {
    $key = stakabaApiKey();
    if (!$key) return ['status' => 'ERROR', 'message' => 'Stakaba not configured.'];

    $ch = curl_init('https://api.stakaba.com/api/v1/transactions/' . urlencode($internalReference));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200) {
        $body = json_decode($resp, true) ?: [];
        return [
            'status' => 'FOUND',
            'data' => $body,
        ];
    }

    return ['status' => 'NOT_FOUND', 'http' => $httpCode, 'body' => $resp ?? '', 'error' => $curlError ?? ''];
}
