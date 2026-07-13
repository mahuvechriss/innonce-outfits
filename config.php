<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

// Load .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, '"\'');
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

function env(string $key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return defined($key) ? constant($key) : $default;
    }
    return $value;
}

define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_NAME', env('DB_NAME', 'innonce_outfits'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('SITE_URL', env('SITE_URL', 'http://localhost:8080/innonce-outfits'));
define('SITE_NAME', 'INNOCE OUTFITS');
define('CURRENCY', 'TZS');
define('TAX_RATE', 5);
define('SHIPPING_THRESHOLD', 50000);
define('SHIPPING_RATE_DEFAULT', 5);
define('SHIPPING_RATE_REDUCED', 2);
define('FREE_SHIPPING_MIN', 200000);
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
define('CLERK_PUBLISHABLE_KEY', env('CLERK_PUBLISHABLE_KEY', ''));

// OpenRouter AI (https://openrouter.ai)
define('AI_FALLBACK_ENDPOINT', env('AI_FALLBACK_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'));
define('AI_FALLBACK_KEY', env('AI_FALLBACK_KEY', ''));
define('AI_FALLBACK_MODEL', env('AI_FALLBACK_MODEL', 'openai/gpt-4o-mini'));
define('CLERK_SECRET_KEY', env('CLERK_SECRET_KEY', ''));
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
