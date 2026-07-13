<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'innonce_outfits');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SITE_URL', 'http://localhost:8080/innonce-outfits');
define('SITE_NAME', 'INNOCE OUTFITS');
define('CURRENCY', 'TZS');
define('TAX_RATE', 5);
define('SHIPPING_FEE', 5000);
define('FREE_SHIPPING_MIN', 100000);
define('ITEMS_PER_PAGE', 12);

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

// Clerk Authentication (https://clerk.com)
define('CLERK_PUBLISHABLE_KEY', 'pk_test_cHJvbW90ZWQtc2Vhc25haWwtNDUuY2xlcmsuYWNjb3VudHMuZGV2JA');
define('CLERK_SECRET_KEY', 'sk_test_qmS7eSDsYCw025g0f4NfBKzzXofX84Xq0eObNU5Vjo');
define('CLERK_API_URL', 'https://api.clerk.com/v1');
// Derived Clerk frontend URL (extracted from publishable key)
$__pkB64 = str_replace(['pk_test_', 'pk_live_'], '', CLERK_PUBLISHABLE_KEY);
$__pkDecoded = base64_decode($__pkB64, true);
$__domain = $__pkDecoded ? rtrim($__pkDecoded, '$') : '';
$__instance = $__domain ? explode('.', $__domain)[0] : '';
define('CLERK_FRONTEND_API_DOMAIN', $__domain);
define('CLERK_ACCOUNT_PORTAL_URL', $__instance ? 'https://' . $__instance . '.accounts.dev' : '');
unset($__pkB64, $__pkDecoded, $__domain, $__instance);

require_once __DIR__ . '/includes/clerk_handler.php';

// Track last activity for online/offline status
if (isset($_SESSION['user_id'])) {
    $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);
}
