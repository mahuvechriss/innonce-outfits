<?php
require_once __DIR__ . '/../../config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

$input = $_POST + $_GET;

$sessionId = $input['_clerk_session_id'] ?? '';

$clerkAction = $input['clerk_action'] ?? '';

// Fallback to session
if (empty($sessionId) && empty($input['__clerk_handshake']) && empty($input['__clerk_db_jwt'])) {
    if (!empty($_SESSION['clerk_handshake'])) {
        $input['__clerk_handshake'] = $_SESSION['clerk_handshake'];
    }
    if (!empty($_SESSION['clerk_db_jwt'])) {
        $input['__clerk_db_jwt'] = $_SESSION['clerk_db_jwt'];
    }
    if (empty($clerkAction) && !empty($_SESSION['clerk_action'])) {
        $clerkAction = $_SESSION['clerk_action'];
    }
    unset($_SESSION['clerk_handshake'], $_SESSION['clerk_db_jwt']);
}

if (empty($sessionId) && isset($input['__clerk_handshake'])) {
    $handshakeJwt = $input['__clerk_handshake'];
    $parts = explode('.', $handshakeJwt);
    if (count($parts) === 3) {
        $payload = base64_decode(strtr($parts[1], '-_', '+/'));
        $handshakeData = json_decode($payload, true);
        if ($handshakeData && isset($handshakeData['handshake'])) {
            foreach ($handshakeData['handshake'] as $cookie) {
                if (strpos($cookie, '__session=') === 0) {
                    $sessionJwt = explode(';', $cookie)[0];
                    $sessionJwt = substr($sessionJwt, strlen('__session='));
                    $sessionParts = explode('.', $sessionJwt);
                    if (count($sessionParts) === 3) {
                        $sessionPayload = base64_decode(strtr($sessionParts[1], '-_', '+/'));
                        $sessionData = json_decode($sessionPayload, true);
                        if ($sessionData && isset($sessionData['sid'])) {
                            $sessionId = $sessionData['sid'];
                        }
                    }
                    break;
                }
            }
        }
    }
}

if (empty($sessionId) && isset($input['code'])) {
    $code = $input['code'];
    try {
        $ch = curl_init(CLERK_API_URL . '/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => CLERK_PUBLISHABLE_KEY,
                'client_secret' => CLERK_SECRET_KEY,
                'redirect_uri' => SITE_URL . '/index.php',
            ]),
        ]);
        $tokenResponse = curl_exec($ch);
        curl_close($ch);
        $tokenData = json_decode($tokenResponse, true);
        $sessionId = $tokenData['session_id'] ?? '';
    } catch (Exception $e) {}
}

if (empty($sessionId)) {
    echo json_encode(['error' => 'no_session', 'redirect' => SITE_URL . '/auth/login.php']);
    exit;
}

try {
    $ch = curl_init(CLERK_API_URL . '/sessions/' . $sessionId);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . CLERK_SECRET_KEY],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode(['error' => 'verify_failed', 'redirect' => SITE_URL . '/auth/login.php']);
        exit;
    }

    $sessionData = json_decode($response, true);
    $userId = $sessionData['user_id'] ?? null;

    if (!$userId) {
        echo json_encode(['error' => 'verify_failed', 'redirect' => SITE_URL . '/auth/login.php']);
        exit;
    }

    $ch = curl_init(CLERK_API_URL . '/users/' . $userId);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . CLERK_SECRET_KEY],
    ]);
    $userResponse = curl_exec($ch);
    $userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($userHttpCode !== 200) {
        echo json_encode(['error' => 'verify_failed', 'redirect' => SITE_URL . '/auth/login.php']);
        exit;
    }

    $userData = json_decode($userResponse, true);

    $email = '';
    if (!empty($userData['email_addresses'])) {
        foreach ($userData['email_addresses'] as $ea) {
            if (!empty($ea['email_address'])) {
                $email = $ea['email_address'];
                break;
            }
        }
    }
    if (empty($email) && !empty($userData['primary_email_address_id']) && !empty($userData['email_addresses'])) {
        foreach ($userData['email_addresses'] as $ea) {
            if ($ea['id'] === $userData['primary_email_address_id']) {
                $email = $ea['email_address'] ?? '';
                break;
            }
        }
    }
    if (empty($email)) {
        echo json_encode(['error' => 'no_email', 'redirect' => SITE_URL . '/auth/login.php']);
        exit;
    }
    $firstName = $userData['first_name'] ?? '';
    $lastName = $userData['last_name'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName) ?: explode('@', $email)[0];
    $clerkId = $userData['id'];
    $photoUrl = $userData['profile_image_url'] ?? '';
    if ($photoUrl && strpos($photoUrl, '/oauth_') !== false) {
        $photoUrl = '';
    }

    // First try to find by clerk_id (same Google account)
    $stmt = $db->prepare("SELECT * FROM users WHERE clerk_id = ?");
    $stmt->execute([$clerkId]);
    $user = $stmt->fetch();

    $isNewUser = false;

    if ($user) {
        // Same Google account — returning user
        $updateFields = [];
        $existingPhoto = $user['profile_photo'];
        if ($existingPhoto && strpos($existingPhoto, '/oauth_') !== false) {
            $updateFields[] = 'profile_photo = ?';
        }
        if (!empty($updateFields)) {
            $stmt = $db->prepare("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?");
            $params = [];
            if ($existingPhoto && strpos($existingPhoto, '/oauth_') !== false) {
                $params[] = '';
            }
            $params[] = $user['id'];
            $stmt->execute($params);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_photo'] = ($existingPhoto && strpos($existingPhoto, '/oauth_') !== false) ? '' : $existingPhoto;
        $_SESSION['user_align'] = $user['photo_align'] ?? 'center';
        $_SESSION['success'] = 'Welcome back, ' . $user['name'] . '!';
    } elseif (!empty($email)) {
        // Check if email already exists (different Google account)
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingByEmail = $stmt->fetch();

        if ($existingByEmail) {
            echo json_encode(['redirect' => SITE_URL . '/auth/login.php?email_exists=1']);
            exit;
        } else {
            $isNewUser = true;
            $placeholderPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role, clerk_id, profile_photo, notify_sms) VALUES (?, ?, ?, 'customer', ?, ?, 1)");
            $stmt->execute([$fullName, $email, $placeholderPassword, $clerkId, $photoUrl]);
            $newId = $db->lastInsertId();

            $_SESSION['user_id'] = $newId;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'customer';
            $_SESSION['user_photo'] = $photoUrl;
            $_SESSION['user_align'] = 'center';
        }
    }

    $_SESSION['clerk_sid'] = $sessionId;
    unset($_SESSION['clerk_handshake'], $_SESSION['clerk_db_jwt'], $_SESSION['clerk_session_id'], $_SESSION['clerk_code']);

    if ($isNewUser) {
        echo json_encode(['redirect' => SITE_URL . '/account/dashboard.php']);
        exit;
    }

    echo json_encode(['redirect' => SITE_URL . '/account/dashboard.php']);
} catch (Exception $e) {
    echo json_encode(['error' => 'catch_error', 'redirect' => SITE_URL . '/auth/login.php']);
}
