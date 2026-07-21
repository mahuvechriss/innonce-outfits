<?php

const PAWAPAY_IPS_SANDBOX = ['3.64.89.224'];
const PAWAPAY_IPS_PRODUCTION = ['18.192.208.15', '18.195.113.136', '3.72.212.107', '54.73.125.42', '54.155.38.214', '54.73.130.113'];

function pawapayIsSandbox(): bool {
    return getSetting('pawapay_environment', 'sandbox') === 'sandbox';
}

function pawapayBaseUrl(): string {
    return pawapayIsSandbox() ? 'https://api.sandbox.pawapay.io' : 'https://api.pawapay.io';
}

function pawapayApiToken(): ?string {
    $v = getSetting('pawapay_api_token', '');
    if ($v) return $v;
    $envKey = pawapayIsSandbox() ? 'PAWAPAY_SANDBOX_API_TOKEN' : 'PAWAPAY_PRODUCTION_API_TOKEN';
    return env($envKey, null);
}

function pawapayGenerateUuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function pawapayInitiateCheckout(float $amount, string $orderNumber, int $orderId): array {
    $token = pawapayApiToken();
    if (!$token) {
        return ['success' => false, 'message' => 'PawaPay not configured (no API token).'];
    }

    $checkoutId = pawapayGenerateUuidV4();
    $amountInt = (int) round($amount);

    $payload = [
        'checkoutId' => $checkoutId,
        'returnUrl' => SITE_URL . '/payment/pawapay_return.php',
        'amounts' => [
            [
                'country' => 'TZA',
                'currency' => 'TZS',
                'amount' => (string) $amountInt,
            ],
        ],
        'clientReferenceId' => $orderNumber,
        'reason' => [
            'en' => 'INNOCE OUTFITS',
        ],
        'metadata' => [
            ['fieldName' => 'orderId', 'fieldValue' => (string) $orderId],
            ['fieldName' => 'orderNumber', 'fieldValue' => $orderNumber],
        ],
    ];

    $ch = curl_init(pawapayBaseUrl() . '/v2/checkouts');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
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

    if ($httpCode >= 200 && $httpCode < 300 && ($body['status'] ?? '') === 'ACCEPTED') {
        return [
            'success' => true,
            'checkout_id' => $checkoutId,
            'redirect_url' => $body['redirectUrl'] ?? null,
            'message' => 'Redirecting to PawaPay checkout...',
            'raw' => $body,
        ];
    }

    $errorMsg = $body['errorMessage'] ?? $body['message'] ?? 'PawaPay checkout failed (HTTP ' . $httpCode . ').';
    if ($curlError) $errorMsg .= ' Curl: ' . $curlError;
    return [
        'success' => false,
        'message' => $errorMsg,
        'raw' => $body,
    ];
}

function pawapayCheckCheckoutStatus(string $checkoutId): array {
    $token = pawapayApiToken();
    if (!$token) return ['status' => 'NOT_FOUND'];

    $ch = curl_init(pawapayBaseUrl() . '/v2/checkouts/' . $checkoutId);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $body = json_decode($resp, true) ?: [];
        $checkoutData = $body['data'] ?? $body;
        return [
            'status' => 'FOUND',
            'data' => $checkoutData,
        ];
    }

    return ['status' => 'NOT_FOUND'];
}

function pawapayIsValidCallbackIp(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $whitelist = pawapayIsSandbox() ? PAWAPAY_IPS_SANDBOX : PAWAPAY_IPS_PRODUCTION;
    return in_array($ip, $whitelist, true);
}
