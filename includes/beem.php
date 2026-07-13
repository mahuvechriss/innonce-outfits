<?php

function sendSms(string $phone, string $message): bool {
    $apiKey = getSetting('beem_api_key', '');
    $secretKey = getSetting('beem_secret_key', '');
    $senderId = trim(preg_replace('/[^a-zA-Z0-9]/', '', getSetting('beem_sender_id', 'INNOCE')));
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