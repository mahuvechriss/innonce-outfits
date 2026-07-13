<?php

function azampayBaseUrl(): string {
    $env = getSetting('azampay_environment', 'sandbox');
    return $env === 'production'
        ? 'https://api.azampay.co.tz'
        : 'https://sandbox.azampay.co.tz';
}

function azampayGetToken(): ?string {
    $clientId = getSetting('azampay_client_id');
    $clientSecret = getSetting('azampay_client_secret');
    $appName = getSetting('azampay_app_name', '');
    if (!$clientId || !$clientSecret) return null;

    $payload = [
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
    ];
    if ($appName) $payload['appName'] = $appName;

    $ch = curl_init(azampayBaseUrl() . '/api/v1/Token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;
    $data = json_decode($resp, true);
    return $data['access_token'] ?? $data['data']['access_token'] ?? null;
}

function azampayFormaPhone(string $phone): string {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 9 && str_starts_with($phone, '7')) {
        $phone = '255' . $phone;
    } elseif (strlen($phone) === 10 && str_starts_with($phone, '0')) {
        $phone = '255' . substr($phone, 1);
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

function azampayMapProvider(string $method): string {
    return match ($method) {
        'mpesa'        => 'Mpesa',
        'airtel_money' => 'Airtel',
        'tigo_pesa'    => 'Tigo',
        'halopesa'     => 'HaloPesa',
        default        => 'Mpesa',
    };
}

function azampayInitiatePayment(float $amount, string $phone, string $reference, string $provider): array {
    $token = azampayGetToken();
    if (!$token) {
        return ['success' => false, 'message' => 'Payment service temporarily unavailable.'];
    }

    $payload = [
        'amount'        => number_format($amount, 2, '.', ''),
        'currency'      => 'TZS',
        'phoneNumber'   => azampayFormaPhone($phone),
        'externalId'    => $reference,
        'provider'      => azampayMapProvider($provider),
    ];

    $ch = curl_init(azampayBaseUrl() . '/api/v1/Checkout');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = json_decode($resp, true) ?: [];

    if ($httpCode === 200) {
        $transactionId = $body['data']['transactionId'] ?? $body['transactionId'] ?? null;
        return [
            'success'        => true,
            'transaction_id' => $transactionId,
            'message'        => $body['message'] ?? 'Payment request sent to your phone.',
            'raw'            => $body,
        ];
    }

    return [
        'success' => false,
        'message' => $body['message'] ?? 'AzamPay request failed (HTTP ' . $httpCode . ').',
        'raw'     => $body,
    ];
}
