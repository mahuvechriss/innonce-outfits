<?php
require_once __DIR__ . '/../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use GuzzleHttp\Client as GuzzleClient;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    $_SESSION['error'] = t('Missing authorization code.', 'Nambari ya uidhinishaji haipo.');
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

try {
    $guzzle = new GuzzleClient(['timeout' => 15]);

    $tokenResponse = $guzzle->post('https://oauth2.googleapis.com/token', [
        'form_params' => [
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'grant_type' => 'authorization_code',
        ],
    ]);

    $tokenData = json_decode((string) $tokenResponse->getBody(), true);
    $idToken = $tokenData['id_token'] ?? '';

    if (empty($idToken)) {
        throw new Exception('No ID token returned');
    }

    $certsResponse = $guzzle->get('https://www.googleapis.com/oauth2/v3/certs');
    $certsData = json_decode((string) $certsResponse->getBody(), true);

    $keySet = (array) JWK::parseKeySet($certsData);

    $decoded = JWT::decode($idToken, $keySet);

    if (($decoded->aud ?? '') !== GOOGLE_CLIENT_ID) {
        throw new Exception('Token audience mismatch');
    }
    $validIssuers = ['https://accounts.google.com', 'accounts.google.com'];
    if (!in_array($decoded->iss ?? '', $validIssuers)) {
        throw new Exception('Invalid token issuer');
    }
    if (($decoded->exp ?? 0) < time()) {
        throw new Exception('Token expired');
    }

    $googleId = $decoded->sub ?? '';
    $email = $decoded->email ?? '';
    $fullName = $decoded->name ?? explode('@', $email)[0];
    $photoUrl = $decoded->picture ?? '';

    if (empty($email)) {
        throw new Exception('No email from Google');
    }

    $state = $_GET['state'] ?? '';

    $stmt = $db->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
    $stmt->execute([$googleId, $email]);
    $existingUser = $stmt->fetch();

    if ($state === 'register') {
        if ($existingUser) {
            $_SESSION['error'] = t('An account with this email already exists. Please login instead.', 'Akaunti iliyo na barua pepe hii tayari ipo. Tafadhali ingia badala yake.');
            header('Location: ' . SITE_URL . '/auth/login.php?email_exists=1');
            exit;
        }
        $placeholderPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, password, role, google_id, profile_photo, notify_sms) VALUES (?, ?, ?, 'customer', ?, ?, 1)");
        $stmt->execute([$fullName, $email, $placeholderPassword, $googleId, $photoUrl]);
        $newId = $db->lastInsertId();

        $_SESSION['user_id'] = $newId;
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = 'customer';
        $_SESSION['user_photo'] = $photoUrl;
        $_SESSION['user_align'] = 'center';
        $_SESSION['user_phone'] = '';
        $_SESSION['_new_oauth_user'] = true;

        header('Location: ' . SITE_URL . '/auth/complete-profile.php');
        exit;
    }

    if ($existingUser) {
        if ($existingUser['google_id'] !== $googleId) {
            $stmt = $db->prepare("UPDATE users SET google_id = ?, profile_photo = ? WHERE id = ?");
            $stmt->execute([$googleId, $photoUrl, $existingUser['id']]);
        }
        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['user_name'] = $existingUser['name'];
        $_SESSION['user_email'] = $existingUser['email'];
        $_SESSION['user_role'] = $existingUser['role'];
        $_SESSION['user_photo'] = $photoUrl ?: $existingUser['profile_photo'];
        $_SESSION['user_align'] = $existingUser['photo_align'] ?? 'center';
        $_SESSION['user_phone'] = $existingUser['phone'] ?? '';
        $_SESSION['success'] = t('Welcome back', 'Karibu tena') . ', ' . $existingUser['name'] . '!';

        $redirect = needsProfileCompletion() ? '/auth/complete-profile.php' : '/account/dashboard.php';
        header('Location: ' . SITE_URL . $redirect);
        exit;
    }

    $_SESSION['error'] = t('No account found with this email. Please register first.', 'Hakuna akaunti iliyopatikana kwa barua pepe hii. Tafadhali jisajili kwanza.');
    header('Location: ' . SITE_URL . '/auth/register.php');
    exit;

} catch (Exception $e) {
    error_log('Google OAuth error: ' . $e->getMessage());
    $_SESSION['error'] = t('Authentication failed. Please try again.', 'Uthibitishaji umeshindwa. Tafadhali jaribu tena.');
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}
