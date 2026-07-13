<?php
require_once __DIR__ . '/../config.php';

// Get the Clerk session ID stored during login
$clerkSessionId = $_SESSION['clerk_sid'] ?? '';

// Revoke the Clerk session via API
if (!empty($clerkSessionId)) {
    try {
        $ch = curl_init('https://api.clerk.com/v1/sessions/' . $clerkSessionId . '/revoke');
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . CLERK_SECRET_KEY],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } catch (Exception $e) {}
}

// Clear Clerk cookies (best effort)
$cookiePaths = ['/', '/innonce-outfits'];
foreach (['__session', '__clerk_db_jwt', '__client', '__clerk_handshake'] as $name) {
    foreach ($cookiePaths as $path) {
        setcookie($name, '', time() - 3600, $path);
    }
}

session_destroy();

header('Location: ../index.php');
exit;