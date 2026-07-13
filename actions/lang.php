<?php
require_once __DIR__ . '/../config.php';

$locale = $_GET['lang'] ?? 'en';
if (!in_array($locale, ['en', 'sw'])) $locale = 'en';

$_SESSION['lang'] = $locale;
$redirect = $_SERVER['HTTP_REFERER'] ?? SITE_URL . '/index.php';
header('Location: ' . $redirect);
exit;
