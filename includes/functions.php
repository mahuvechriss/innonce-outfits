<?php

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please login first.';
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['error'] = 'Access denied.';
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function redirect(string $url, string $message = '', string $type = 'success'): void {
    if ($message) $_SESSION[$type] = $message;
    header("Location: $url");
    exit;
}

function old(string $key, string $default = ''): string {
    return $_SESSION['old'][$key] ?? $default;
}

function csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

function verifyCsrf(string $token): bool {
    if (!isset($_SESSION['csrf_token'])) return false;
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    if ($valid) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $valid;
}

function escape(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'n-a';
}

function generateOrderNumber(): string {
    global $db;
    $prefix = 'INV-' . date('Y') . '-';
    do {
        $number = $prefix . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = $db->prepare("SELECT id FROM orders WHERE order_number = ?");
        $stmt->execute([$number]);
    } while ($stmt->fetch());
    return $number;
}

function generateReference(): string {
    global $db;
    $prefix = 'PAY-' . date('Ymd') . '-';
    do {
        $ref = $prefix . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $stmt = $db->prepare("SELECT id FROM payment_transactions WHERE reference = ?");
        $stmt->execute([$ref]);
    } while ($stmt->fetch());
    return $ref;
}

function cartCount(): int {
    if (!isLoggedIn()) return 0;
    global $db;
    $stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetchColumn();
}

function cartTotal(int $userId): float {
    global $db;
    $stmt = $db->prepare("SELECT SUM(c.quantity * COALESCE(p.discount_price, p.price)) FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
    $stmt->execute([$userId]);
    return (float) $stmt->fetchColumn();
}

function getSetting(string $key, string $default = ''): string {
    global $db;
    $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function formatMoney(float $amount): string {
    return CURRENCY . ' ' . number_format($amount);
}

function uploadFile(array $file, string $path = 'products'): string|false {
    $targetDir = __DIR__ . '/../uploads/' . $path;
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed)) return false;
    $filename = uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $targetDir . '/' . $filename)) {
        return 'uploads/' . $path . '/' . $filename;
    }
    return false;
}

function sendJson(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function currentLang(): string {
    return $_SESSION['lang'] ?? 'en';
}

function __(string $key): string {
    global $lang;
    $locale = currentLang();
    return $lang[$locale][$key] ?? $lang['en'][$key] ?? $key;
}

function t(string $en, string $sw): string {
    return currentLang() === 'sw' ? $sw : $en;
}

function colorNames(): array {
    return [
        'Red' => ['en' => 'Red', 'sw' => 'Nyekundu'],
        'Blue' => ['en' => 'Blue', 'sw' => 'Bluu'],
        'Black' => ['en' => 'Black', 'sw' => 'Nyeusi'],
        'White' => ['en' => 'White', 'sw' => 'Nyeupe'],
        'Green' => ['en' => 'Green', 'sw' => 'Kijani'],
        'Yellow' => ['en' => 'Yellow', 'sw' => 'Manjano'],
        'Purple' => ['en' => 'Purple', 'sw' => 'Zambarau'],
        'Pink' => ['en' => 'Pink', 'sw' => 'Waridi'],
        'Orange' => ['en' => 'Orange', 'sw' => 'Chungwa'],
        'Brown' => ['en' => 'Brown', 'sw' => 'Kahawia'],
        'Grey' => ['en' => 'Grey', 'sw' => 'Kijivu'],
        'Gold' => ['en' => 'Gold', 'sw' => 'Dhahabu'],
        'Silver' => ['en' => 'Silver', 'sw' => 'Fedha'],
        'Navy' => ['en' => 'Navy', 'sw' => 'Navy'],
        'Maroon' => ['en' => 'Maroon', 'sw' => 'Maroon'],
        'Beige' => ['en' => 'Beige', 'sw' => 'Beige'],
        'Cream' => ['en' => 'Cream', 'sw' => 'Cream'],
        'Teal' => ['en' => 'Teal', 'sw' => 'Teal'],
    ];
}

function expandSearchWithColors(string $search): array {
    $colors = colorNames();
    $searchLower = mb_strtolower($search);
    $matchedColors = [];
    foreach ($colors as $en => $names) {
        if (mb_strpos($searchLower, mb_strtolower($names['en'])) !== false ||
            mb_strpos($searchLower, mb_strtolower($names['sw'])) !== false) {
            $matchedColors[] = $en;
        }
    }
    return $matchedColors;
}

function commonColors(): array {
    return array_keys(colorNames());
}

function colorPalette(): array {
    return [
        'Red' => '#FF0000', 'Dark Red' => '#8B0000', 'Crimson' => '#DC143C', 'Maroon' => '#800000',
        'Pink' => '#FFC0CB', 'Hot Pink' => '#FF69B4', 'Rose' => '#FF007F', 'Coral' => '#FF7F50',
        'Orange' => '#FFA500', 'Dark Orange' => '#FF8C00', 'Peach' => '#FFDAB9', 'Amber' => '#FFBF00',
        'Yellow' => '#FFFF00', 'Gold' => '#FFD700', 'Light Yellow' => '#FFFFE0', 'Lemon' => '#FFF700',
        'Green' => '#008000', 'Lime' => '#00FF00', 'Olive' => '#808000', 'Teal' => '#008080',
        'Cyan' => '#00FFFF', 'Mint' => '#98FF98', 'Forest' => '#228B22', 'Emerald' => '#50C878',
        'Blue' => '#0000FF', 'Navy' => '#000080', 'Royal Blue' => '#4169E1', 'Sky Blue' => '#87CEEB',
        'Baby Blue' => '#89CFF0', 'Turquoise' => '#40E0D0', 'Indigo' => '#4B0082',
        'Purple' => '#800080', 'Lavender' => '#E6E6FA', 'Violet' => '#EE82EE', 'Plum' => '#DDA0DD',
        'Brown' => '#A52A2A', 'Chocolate' => '#D2691E', 'Khaki' => '#F0E68C', 'Tan' => '#D2B48C',
        'Beige' => '#F5F5DC', 'Cream' => '#FFFDD0', 'Ivory' => '#FFFFF0', 'Wheat' => '#F5DEB3',
        'White' => '#FFFFFF', 'Off White' => '#FAF9F6', 'Snow' => '#FFFAFA',
        'Grey' => '#808080', 'Silver' => '#C0C0C0', 'Charcoal' => '#36454F', 'Slate' => '#708090',
        'Black' => '#000000', 'Jet Black' => '#0A0A0A',
        'Burgundy' => '#800020', 'Mauve' => '#E0B0FF', 'Salmon' => '#FA8072', 'Mustard' => '#FFDB58',
    ];
}
