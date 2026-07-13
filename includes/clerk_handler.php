<?php
$currentScript = basename($_SERVER['SCRIPT_NAME']);
if (in_array($currentScript, ['callback.php', 'clerk.php', 'logout.php'])) return;

$hasClerkParams = isset($_GET['_clerk_session_id'])
    || isset($_GET['__clerk_handshake'])
    || isset($_GET['__clerk_db_jwt'])
    || isset($_GET['code']);

if (!$hasClerkParams) return;

$params = [];
foreach (['__clerk_handshake', '__clerk_db_jwt', '_clerk_session_id', 'code', 'clerk_action'] as $key) {
    if (isset($_GET[$key])) {
        $params[] = rawurlencode($key) . '=' . rawurlencode($_GET[$key]);
    }
}
$qs = implode('&', $params);

header('Location: ' . SITE_URL . '/auth/callback.php' . ($qs ? '?' . $qs : ''));
exit;
