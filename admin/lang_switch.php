<?php
session_start();
$lang = $_GET['lang'] ?? '';
if ($lang === 'sw' || $lang === 'en') {
    $_SESSION['admin_lang'] = $lang;
}
$redirect = $_GET['redirect'] ?? 'index.php';
$allowedHost = parse_url(SITE_URL, PHP_URL_HOST);
$parsed = parse_url($redirect);
if (!empty($parsed['host']) && $parsed['host'] !== $allowedHost) {
    $redirect = 'index.php';
}
header("Location: $redirect");
exit;
