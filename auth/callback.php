<?php
require_once __DIR__ . '/../config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// Read Clerk params from URL (passed directly by clerk_handler.php)
$handshake = $_GET['__clerk_handshake'] ?? $_SESSION['clerk_handshake'] ?? '';
$dbJwt = $_GET['__clerk_db_jwt'] ?? $_SESSION['clerk_db_jwt'] ?? '';
$sessId = $_GET['_clerk_session_id'] ?? $_SESSION['clerk_session_id'] ?? '';
$code = $_GET['code'] ?? $_SESSION['clerk_code'] ?? '';
$clerkAction = $_GET['clerk_action'] ?? $_SESSION['clerk_action'] ?? 'login';

$hasClerkParams = !empty($sessId) || !empty($handshake) || !empty($dbJwt) || !empty($code);

if (!$hasClerkParams) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

$isReturning = false;
if (!empty($handshake)) {
    $parts = explode('.', $handshake);
    if (count($parts) === 3) {
        $payload = base64_decode(strtr($parts[1], '-_', '+/'));
        $data = json_decode($payload, true);
        if ($data && isset($data['handshake'])) {
            foreach ($data['handshake'] as $cookie) {
                if (strpos($cookie, '__session=') === 0) {
                    $sessionJwt = substr(explode(';', $cookie)[0], strlen('__session='));
                    $sp = explode('.', $sessionJwt);
                    if (count($sp) === 3) {
                        $spPayload = base64_decode(strtr($sp[1], '-_', '+/'));
                        $spData = json_decode($spPayload, true);
                        if ($spData && isset($spData['sub'])) {
                            $stmt = $db->prepare("SELECT 1 FROM users WHERE clerk_id = ?");
                            $stmt->execute([$spData['sub']]);
                            $isReturning = (bool)$stmt->fetchColumn();
                        }
                    }
                    break;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> — <?= t('Authenticating', 'Inathibitisha') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #0f0f1a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .loading-card {
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 48px;
            text-align: center;
            max-width: 380px;
            width: 90%;
        }
        .logo {
            width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
            margin-bottom: 20px;
            border: 2px solid rgba(255,255,255,0.1);
        }
        .spinner {
            width: 40px; height: 40px; margin: 24px auto;
            border: 3px solid rgba(255,255,255,0.08);
            border-top: 3px solid #ff8c00;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        h2 { color: #fff; font-size: 18px; font-weight: 600; margin-bottom: 8px; }
        p { color: rgba(255,255,255,0.5); font-size: 14px; }
    </style>
</head>
<body>
    <div class="loading-card">
        <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="<?= SITE_NAME ?>" class="logo">
        <div class="spinner"></div>
        <h2><?= $isReturning ? t('Logging in to', 'Inaingia kwenye') : t('Creating your account at', 'Inaunda akaunti yako katika') ?> <?= SITE_NAME ?>...</h2>
        <p><?= $isReturning ? t('Please wait...', 'Tafadhali subiri...') : t('Setting up your profile...', 'Inaandaa wasifu wako...') ?></p>
    </div>

    <script>
    (function() {
        var body = '';
        function addParam(key, val) {
            if (val) body += (body ? '&' : '') + encodeURIComponent(key) + '=' + encodeURIComponent(val);
        }
        addParam('__clerk_handshake', <?= json_encode($handshake) ?>);
        addParam('__clerk_db_jwt', <?= json_encode($dbJwt) ?>);
        addParam('_clerk_session_id', <?= json_encode($sessId) ?>);
        addParam('code', <?= json_encode($code) ?>);
        addParam('clerk_action', <?= json_encode($clerkAction) ?>);
        fetch('<?= SITE_URL ?>/includes/ajax/clerk.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    window.location.href = '<?= SITE_URL ?>/auth/login.php';
                }
            })
            .catch(function() {
                window.location.href = '<?= SITE_URL ?>/auth/login.php';
            });
    })();
    </script>
</body>
</html>
