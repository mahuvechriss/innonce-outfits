<?php
require_once __DIR__ . '/vendor/autoload.php';

$sessionPath = __DIR__ . '/tmp';
if (!is_dir($sessionPath)) { mkdir($sessionPath, 0777, true); }
ini_set('session.save_path', $sessionPath);
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
define('SHOP_LAT', -6.1722);
define('SHOP_LNG', 35.7395);

// Volume discount tiers: [min_qty, max_qty, discount_percent]
define('VOLUME_DISCOUNT_TIERS', json_encode([
    [3, 9, 2],
    [10, 19, 3],
    [20, 39, 4],
    [40, 59, 5],
    [60, 999999, 6],
]));

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

// Google OAuth
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', SITE_URL . '/auth/google-callback.php');

// Google Gemini AI (https://aistudio.google.com) - Primary, free tier
define('GEMINI_API_KEY', env('GEMINI_API_KEY', ''));

// Groq AI (https://console.groq.com) - Free Llama vision
define('GROQ_API_KEY', env('GROQ_API_KEY', ''));
define('GROQ_MODEL', env('GROQ_MODEL', 'llama-3.3-70b-versatile'));
define('GROQ_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions');

// OpenRouter AI (https://openrouter.ai) - Fallback
define('AI_FALLBACK_ENDPOINT', env('AI_FALLBACK_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'));
define('AI_FALLBACK_KEY', env('AI_FALLBACK_KEY', ''));
define('AI_FALLBACK_MODEL', env('AI_FALLBACK_MODEL', 'openai/gpt-4o-mini'));
// Redirect users with incomplete profiles to complete-profile page
requireCompleteProfile();

// Load active theme for public-facing pages
$activeTheme = getActiveTheme();
$activeThemeCssVars = [];
if ($activeTheme) {
    $decoded = json_decode($activeTheme['css_variables'], true);
    if (is_array($decoded)) $activeThemeCssVars = $decoded;
}

// Track last activity for online/offline status
if (isset($_SESSION['user_id'])) {
    $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);
}
