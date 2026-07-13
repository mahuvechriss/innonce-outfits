<?php
require_once __DIR__ . '/../config.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$action = $_GET['action'] ?? 'login';
$provider = $_GET['provider'] ?? '';
$portalAction = $action === 'register' ? 'sign-up' : 'sign-in';
$portalUrl = 'https://promoted-seasnail-45.accounts.dev/' . $portalAction . '?redirect_url=' . urlencode(SITE_URL . '/index.php');

header('Location: ' . $portalUrl);
exit;
