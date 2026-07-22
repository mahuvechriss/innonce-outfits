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

function isWorker(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'worker';
}

function requireWorker(): void {
    requireLogin();
    if (!isWorker()) {
        $_SESSION['error'] = 'Access denied.';
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
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

function getTotalCartQuantity(int $userId): int {
    global $db;
    $stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function calculateVolumeDiscount(int $totalQty): int {
    $tiers = json_decode(getSetting('volume_discount_tiers', VOLUME_DISCOUNT_TIERS), true);
    if (!is_array($tiers)) {
        $tiers = json_decode(VOLUME_DISCOUNT_TIERS, true);
    }
    $percent = 0;
    foreach ($tiers as [$min, $max, $pct]) {
        if ($totalQty >= $min && $totalQty <= $max) {
            $percent = $pct;
            break;
        }
    }
    return $percent;
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

function getActiveTheme(): ?array {
    global $db;
    try {
        // Admin preview: check session for staging preview
        if (!empty($_SESSION['preview_staging'])) {
            $stmt = $db->prepare("SELECT * FROM themes WHERE is_staging = 1 LIMIT 1");
            $stmt->execute();
            $theme = $stmt->fetch();
            if ($theme) return $theme;
        }
        // Check for auto-scheduled themes first (only live ones)
        $stmt = $db->prepare("SELECT * FROM themes WHERE auto_schedule = 1 AND scheduled_from <= CURDATE() AND scheduled_to >= CURDATE() AND is_live = 0 ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $theme = $stmt->fetch();
        if ($theme) return $theme;
        // Fall back to live theme
        $stmt = $db->prepare("SELECT * FROM themes WHERE is_live = 1 LIMIT 1");
        $stmt->execute();
        $theme = $stmt->fetch();
        if ($theme) return $theme;
        // Fall back to default theme if no live theme is set
        $stmt = $db->prepare("SELECT * FROM themes WHERE is_default = 1 LIMIT 1");
        $stmt->execute();
        $theme = $stmt->fetch();
        if ($theme) return $theme;
    } catch (Exception $e) {
        // Table likely does not exist yet
    }
    return null;
}

function adjustBrightness(string $hex, int $percent): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = max(0, min(255, $r + $percent));
    $g = max(0, min(255, $g + $percent));
    $b = max(0, min(255, $b + $percent));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function renderThemeCss(): string {
    $theme = getActiveTheme();
    if (!$theme) return '';

    $data = json_decode($theme['css_variables'], true);
    $dec = json_decode($theme['decorations'] ?? '{}', true);
    $qs = $dec['quick_styles'] ?? [];

    $accent = $qs['btn_bg'] ?? ($data['--orange'] ?? '#FF8C00');
    $bgBody = $qs['bg_color'] ?? ($data['--bg-body'] ?? '#FFF5EB');
    $textPrimary = $qs['text_color'] ?? ($data['--text-primary'] ?? '#121212');
    $navbarBg = $qs['navbar_bg'] ?? ($data['--bg-navbar'] ?? '#FF8C00');
    $cardBg = $qs['card_bg'] ?? ($data['--bg-card'] ?? '#ffffff');
    $linkColor = $qs['link_color'] ?? ($data['--orange'] ?? '#FF8C00');
    $headingColor = $qs['heading_color'] ?? $textPrimary;
    $btnText = $qs['btn_text'] ?? '#ffffff';

    $vars = [
        '--orange' => $accent,
        '--orange-light' => adjustBrightness($accent, 40),
        '--orange-dark' => adjustBrightness($accent, -30),
        '--orange-gradient' => "linear-gradient(135deg,{$accent} 0%," . adjustBrightness($accent, -20) . " 50%,{$accent} 100%)",
        '--gold' => $accent,
        '--gold-light' => adjustBrightness($accent, 40),
        '--gold-dark' => adjustBrightness($accent, -30),
        '--gold-gradient' => "linear-gradient(135deg,{$accent} 0%," . adjustBrightness($accent, -20) . " 50%,{$accent} 100%)",
        '--bg-body' => $bgBody,
        '--bg-navbar' => $navbarBg,
        '--bg-card' => $cardBg,
        '--text-primary' => $textPrimary,
        '--text-secondary' => adjustBrightness($textPrimary, 40),
        '--text-on-orange' => $btnText,
        '--border-light' => adjustBrightness($bgBody, -20),
        '--dark' => '#121212',
        '--darker' => '#0A0A0A',
        '--light' => $bgBody,
        '--bg-hero' => "linear-gradient(135deg,{$accent} 0%," . adjustBrightness($accent, -20) . " 50%," . adjustBrightness($accent, -40) . " 100%)",
        '--bg-section-alt' => adjustBrightness($bgBody, -10),
        '--shadow-sm' => '0 2px 8px rgba(0,0,0,0.08)',
        '--shadow-md' => '0 4px 20px rgba(0,0,0,0.12)',
        '--shadow-lg' => '0 8px 32px rgba(0,0,0,0.16)',
        '--shadow-gold' => "0 4px 20px " . adjustBrightness($accent, 0) . '40',
    ];

    $borderRadius = $qs['border_radius'] ?? '';
    $brMap = ['none' => '0', 'sm' => '4px', 'md' => '8px', 'lg' => '16px'];
    $brVal = $brMap[$borderRadius] ?? '';
    $vars['--radius'] = $brVal ?: '10px';
    $vars['--radius-lg'] = $brVal ?: '16px';

    // Dark mode
    $darkBg = $qs['dark_bg_color'] ?? '#121212';
    $darkText = $qs['dark_text_color'] ?? '#F5F0EB';
    $darkNavbar = $qs['dark_navbar_bg'] ?? '#121212';
    $darkCard = $qs['dark_card_bg'] ?? '#1E1E1E';
    $darkLink = $qs['dark_link_color'] ?? $accent;
    $darkBtn = $qs['dark_btn_bg'] ?? $accent;
    $darkBtnText = $qs['dark_btn_text'] ?? '#ffffff';
    $darkHeading = $qs['dark_heading_color'] ?? $darkText;

    $darkVars = [
        '--orange' => $accent,
        '--orange-light' => adjustBrightness($accent, 40),
        '--orange-dark' => adjustBrightness($accent, -30),
        '--orange-gradient' => "linear-gradient(135deg,{$accent} 0%," . adjustBrightness($accent, -20) . " 50%,{$accent} 100%)",
        '--gold' => $accent,
        '--gold-light' => adjustBrightness($accent, 40),
        '--gold-dark' => adjustBrightness($accent, -30),
        '--gold-gradient' => "linear-gradient(135deg,{$accent} 0%," . adjustBrightness($accent, -20) . " 50%,{$accent} 100%)",
        '--bg-body' => $darkBg,
        '--bg-navbar' => $darkNavbar,
        '--bg-card' => $darkCard,
        '--text-primary' => $darkText,
        '--text-secondary' => adjustBrightness($darkText, -40),
        '--text-on-orange' => $darkBtnText,
        '--border-light' => '#333',
        '--dark' => '#0A0A0A',
        '--darker' => '#050505',
        '--light' => '#1A1A1A',
        '--bg-hero' => "linear-gradient(135deg,#0A0A0A 0%,#1A1A1A 50%,#0A0A0A 100%)",
        '--bg-section-alt' => '#1A1A1A',
        '--shadow-sm' => '0 2px 8px rgba(0,0,0,0.3)',
        '--shadow-md' => '0 4px 20px rgba(0,0,0,0.4)',
        '--shadow-lg' => '0 8px 32px rgba(0,0,0,0.5)',
        '--shadow-gold' => "0 4px 20px " . adjustBrightness($accent, 0) . '40',
        '--radius' => $vars['--radius'],
        '--radius-lg' => $vars['--radius-lg'],
    ];

    // Merge css_variables on top for backward compatibility
    $oldDark = [];
    if (is_array($data)) {
        $oldDark = $data['_dark'] ?? [];
        unset($data['_dark']);
        $vars = array_merge($vars, $data);
    }
    if ($oldDark) {
        $darkVars = array_merge($darkVars, $oldDark);
    }

    $css = '';
    foreach ([':root', '[data-theme="light"]'] as $selector) {
        $css .= $selector . '{';
        foreach ($vars as $key => $value) {
            $css .= $key . ':' . $value . ';';
        }
        $css .= '}';
    }
    $css .= '[data-theme="dark"]{';
    foreach ($darkVars as $key => $value) {
        $css .= $key . ':' . $value . ';';
    }
    $css .= '}';
    return '<style id="active-theme-css">' . $css . '</style>';
}

function getThemeDecorations(): array {
    $theme = getActiveTheme();
    if (!$theme) return ['enabled' => false];
    $raw = $theme['decorations'] ?? '{}';
    $dec = json_decode($raw, true);
    return is_array($dec) ? $dec : ['enabled' => false];
}

function renderThemeDecorations(): string {
    $dec = getThemeDecorations();

    $html = '';
    $css = '';
    $js = '';

    $badgeText = currentLang() === 'sw'
        ? ($dec['badge_text_sw'] ?? '')
        : ($dec['badge_text_en'] ?? '');

    // Badge / banner
    if (!empty($dec['enabled']) && !empty($dec['badge_enabled']) && $badgeText) {
        $icon = !empty($dec['badge_icon']) ? '<i class="fas ' . $dec['badge_icon'] . ' me-2"></i>' : '';
        $html .= '<div class="theme-badge" id="themeBadge">';
        $html .= $icon . escape($badgeText);
        $html .= '<button class="btn-close btn-close-white ms-2" onclick="document.getElementById(\'themeBadge\').remove()" style="font-size:10px"></button>';
        $html .= '</div>';
    }

    $particleType = !empty($dec['enabled']) ? ($dec['particles'] ?? 'none') : 'none';
    $count = min(120, max(10, (int)($dec['particle_count'] ?? 50)));

    // ---- SNOW ----
    if ($particleType === 'snow') {
        $css .= '
.theme-particle{position:fixed;top:0;pointer-events:none;z-index:9999;will-change:transform;backface-visibility:hidden}
@keyframes snowFall{0%{transform:translateY(-5vh) translateX(0) scale(0.6);opacity:0}5%{opacity:0.6}90%{opacity:0.6}100%{transform:translateY(105vh) translateX(30px) scale(1.2);opacity:0}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment();for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var s=2+Math.random()*6;e.style.cssText="width:"+s+"px;height:"+s+"px;left:"+(Math.random()*100)+"%;top:-"+(s+5)+"px;background:radial-gradient(circle at 30% 30%,rgba(255,255,255,0.95),rgba(255,255,255,0.25));border-radius:50%;box-shadow:0 0 "+(s*2)+"px rgba(255,255,255,0.5);animation:snowFall "+(6+Math.random()*10)+"s linear infinite;animation-delay:"+(Math.random()*12)+"s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- CONFETTI ----
    elseif ($particleType === 'confetti') {
        $css .= '
.theme-particle{position:fixed;top:0;pointer-events:none;z-index:9999;will-change:transform}
@keyframes confettiFall{0%{transform:translateY(-5vh) rotate(0deg);opacity:0}5%{opacity:1}90%{opacity:1}100%{transform:translateY(105vh) rotate(1080deg);opacity:0}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment(),cols=["#D42426","#1EB53A","#FCD116","#0066CC","#FFD700","#FF69B4","#FF8C00","#9B59B6","#00CEC9"];for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var t=j%4;if(t===0){e.style.cssText="width:8px;height:8px;border-radius:50%;"}else if(t===1){e.style.cssText="width:6px;height:14px;border-radius:2px;"}else if(t===2){e.style.cssText="width:10px;height:6px;border-radius:1px;"}else{e.style.cssText="width:7px;height:7px;border-radius:2px;transform:rotate(45deg);"}e.style.background=cols[j%cols.length];e.style.left=(5+Math.random()*90)+"%";e.style.top="-10px";e.style.animation="confettiFall "+(3+Math.random()*4)+"s linear infinite";e.style.animationDelay=(Math.random()*6)+"s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- STARS ----
    elseif ($particleType === 'stars') {
        $css .= '
.theme-particle{position:fixed;top:0;pointer-events:none;z-index:9999;will-change:transform}
.theme-star{position:relative;display:flex;align-items:center;justify-content:center}
.theme-star-bar{position:absolute;border-radius:1px;background:linear-gradient(135deg,#FFD700,#FFA500)}
@keyframes starFloat{0%{transform:translateY(110vh) scale(0) rotate(0deg);opacity:0}15%{opacity:0.9}75%{opacity:0.6}100%{transform:translateY(-5vh) scale(1) rotate(180deg);opacity:0}}
@keyframes starTwinkle{0%,100%{opacity:0.3;transform:scale(0.8)}50%{opacity:1;transform:scale(1.2)}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment();for(var j=0;j<c;j++){var w=document.createElement("div");w.className="theme-particle theme-star";var sz=8+Math.random()*12;w.style.cssText="width:"+sz+"px;height:"+sz+"px;left:"+(2+Math.random()*96)+"%;animation:starFloat "+(10+Math.random()*15)+"s ease-in-out infinite;animation-delay:"+(Math.random()*20)+"s;";var b1=document.createElement("div");b1.className="theme-star-bar";b1.style.cssText="width:"+(sz*0.22)+"px;height:"+sz+"px;box-shadow:0 0 "+(sz*0.5)+"px #FFD700,0 0 "+(sz*1.5)+"px rgba(255,215,0,0.3);animation:starTwinkle "+(1.5+Math.random()*2)+"s ease-in-out infinite;animation-delay:"+(Math.random()*3)+"s";var b2=document.createElement("div");b2.className="theme-star-bar";b2.style.cssText="width:"+sz+"px;height:"+(sz*0.22)+"px;box-shadow:0 0 "+(sz*0.5)+"px #FFD700,0 0 "+(sz*1.5)+"px rgba(255,215,0,0.3);animation:starTwinkle "+(1.5+Math.random()*2)+"s ease-in-out infinite;animation-delay:"+(Math.random()*3)+"s";w.appendChild(b1);w.appendChild(b2);p.appendChild(w)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- LUXURY GOLD DUST ----
    elseif ($particleType === 'gold_dust') {
        $css .= '
.theme-particle{position:fixed;top:0;pointer-events:none;z-index:9999;will-change:transform}
@keyframes goldDustFloat{0%{transform:translateY(110vh) translateX(0) scale(0);opacity:0}10%{opacity:.9}50%{opacity:.7}90%{opacity:.4}100%{transform:translateY(-5vh) translateX(40px) scale(1);opacity:0}}
@keyframes goldShimmer{0%,100%{opacity:.4;filter:brightness(.8)}50%{opacity:1;filter:brightness(1.4)}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment();for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var s=3+Math.random()*5;e.style.cssText="width:"+s+"px;height:"+s+"px;left:"+(2+Math.random()*96)+"%;top:-"+(s+5)+"px;background:radial-gradient(circle at 30% 30%,#FFE44D,#FFD700);border-radius:50%;box-shadow:0 0 "+(s*3)+"px rgba(255,215,0,0.6),0 0 "+(s*6)+"px rgba(255,215,0,0.3);animation:goldDustFloat "+(12+Math.random()*18)+"s ease-in-out infinite,goldShimmer "+(2+Math.random()*3)+"s ease-in-out infinite;animation-delay:"+(Math.random()*15)+"s,0s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- SOFT SPARKLE ----
    elseif ($particleType === 'sparkle') {
        $css .= '
.theme-particle{position:fixed;pointer-events:none;z-index:9999;will-change:transform}
@keyframes sparkleAppear{0%{transform:scale(0);opacity:0}15%{opacity:1;transform:scale(1)}75%{opacity:.7;transform:scale(.8)}100%{opacity:0;transform:scale(0)}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment();for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var s=2+Math.random()*4;e.style.cssText="width:"+s+"px;height:"+s+"px;left:"+(2+Math.random()*96)+"%;top:"+(2+Math.random()*96)+"%;background:radial-gradient(circle at 30% 30%,#fff,#FFE44D);border-radius:50%;box-shadow:0 0 "+(s*4)+"px rgba(255,215,0,0.5);animation:sparkleAppear "+(2+Math.random()*3)+"s ease-in-out infinite;animation-delay:"+(Math.random()*5)+"s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- DIAMOND SPARKLE ----
    elseif ($particleType === 'diamond') {
        $css .= '
.theme-particle{position:fixed;pointer-events:none;z-index:9999;will-change:transform}
.theme-diamond{position:relative;display:flex;align-items:center;justify-content:center}
.theme-diamond-bar{position:absolute;border-radius:1px}
@keyframes diamondFloat{0%{transform:translateY(110vh) scale(0) rotate(0deg);opacity:0}15%{opacity:1}65%{opacity:.5}100%{transform:translateY(-5vh) scale(1) rotate(180deg);opacity:0}}
@keyframes diamondFlash{0%,100%{opacity:.2;transform:scale(.6)}50%{opacity:1;transform:scale(1.3)}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment();for(var j=0;j<c;j++){var w=document.createElement("div");w.className="theme-particle theme-diamond";var sz=6+Math.random()*10;w.style.cssText="width:"+sz+"px;height:"+sz+"px;left:"+(2+Math.random()*96)+"%;animation:diamondFloat "+(12+Math.random()*18)+"s ease-in-out infinite;animation-delay:"+(Math.random()*20)+"s;";var b1=document.createElement("div");b1.className="theme-diamond-bar";b1.style.cssText="width:"+(sz*0.18)+"px;height:"+sz+"px;background:linear-gradient(180deg,#fff,#B0E0E6);box-shadow:0 0 "+(sz*0.5)+"px rgba(255,255,255,0.8);animation:diamondFlash "+(1.5+Math.random()*2)+"s ease-in-out infinite;animation-delay:"+(Math.random()*3)+"s";var b2=document.createElement("div");b2.className="theme-diamond-bar";b2.style.cssText="width:"+sz+"px;height:"+(sz*0.18)+"px;background:linear-gradient(90deg,#fff,#B0E0E6);box-shadow:0 0 "+(sz*0.5)+"px rgba(255,255,255,0.8);animation:diamondFlash "+(1.5+Math.random()*2)+"s ease-in-out infinite;animation-delay:"+(Math.random()*3)+"s";w.appendChild(b1);w.appendChild(b2);p.appendChild(w)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- FLOATING GLITTER ----
    elseif ($particleType === 'glitter') {
        $css .= '
.theme-particle{position:fixed;pointer-events:none;z-index:9999;will-change:transform}
@keyframes glitterFall{0%{transform:translateY(-5vh) rotate(0deg) scale(0);opacity:0}10%{opacity:1}80%{opacity:.5}100%{transform:translateY(105vh) rotate(720deg) scale(.5);opacity:0}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment(),cols=["#FFD700","#FFE44D","#FFF8DC","#FFA500","#FFD700"];for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var t=j%3;if(t===0){e.style.cssText="width:5px;height:5px;border-radius:50%;"}else if(t===1){e.style.cssText="width:4px;height:8px;border-radius:1px;"}else{e.style.cssText="width:8px;height:3px;border-radius:1px;"}e.style.background=cols[j%cols.length];e.style.left=(2+Math.random()*96)+"%";e.style.top="-5px";e.style.boxShadow="0 0 4px rgba(255,215,0,0.5)";e.style.animation="glitterFall "+(5+Math.random()*7)+"s linear infinite";e.style.animationDelay=(Math.random()*8)+"s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- PEARL GLOW ----
    elseif ($particleType === 'pearl_glow') {
        $css .= '
.theme-particle{position:fixed;top:0;pointer-events:none;z-index:9999;will-change:transform}
@keyframes pearlFloat{0%{transform:translateY(110vh) scale(.3);opacity:0}15%{opacity:.6}75%{opacity:.4}100%{transform:translateY(-5vh) scale(1.2);opacity:0}}
@keyframes pearlPulse{0%,100%{filter:brightness(.9) blur(0px)}50%{filter:brightness(1.4) blur(1px)}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment();for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var s=8+Math.random()*16;e.style.cssText="width:"+s+"px;height:"+s+"px;left:"+(2+Math.random()*96)+"%;top:-"+(s+10)+"px;background:radial-gradient(circle at 35% 35%,#fff 0%,#F5F0EB 40%,#E8E0D8 100%);border-radius:50%;box-shadow:0 0 "+(s*2)+"px rgba(245,240,235,0.4),inset 0 -"+(s*0.3)+"px "+(s*0.5)+"px rgba(0,0,0,0.1);animation:pearlFloat "+(18+Math.random()*22)+"s ease-in-out infinite,pearlPulse "+(4+Math.random()*3)+"s ease-in-out infinite;animation-delay:"+(Math.random()*20)+"s,0s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- ROSE PETAL ----
    elseif ($particleType === 'rose_petal') {
        $css .= '
.theme-particle{position:fixed;top:0;pointer-events:none;z-index:9999;will-change:transform}
@keyframes petalFall{0%{transform:translateY(-5vh) translateX(0) rotate(0deg);opacity:0}10%{opacity:.8}80%{opacity:.6}100%{transform:translateY(105vh) translateX(60px) rotate(360deg);opacity:0}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment(),cols=["#FF6B8A","#FF8DA1","#E84367","#FFB6C1","#D4456A","#FF4D6D"];for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var w=8+Math.random()*10,h=12+Math.random()*16;e.style.cssText="width:"+w+"px;height:"+h+"px;left:"+(2+Math.random()*96)+"%;top:-"+(h+5)+"px;background:radial-gradient(ellipse at 40% 30%,"+cols[j%cols.length]+","+cols[(j+1)%cols.length]+");border-radius:50% 0 50% 0;opacity:.85;box-shadow:0 0 6px rgba(255,107,138,0.3);animation:petalFall "+(8+Math.random()*10)+"s ease-in-out infinite;animation-delay:"+(Math.random()*12)+"s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- FLOATING FEATHER ----
    elseif ($particleType === 'feather') {
        $css .= '
.theme-particle{position:fixed;top:0;pointer-events:none;z-index:9999;will-change:transform}
@keyframes featherFloat{0%{transform:translateY(110vh) translateX(0) rotate(0deg);opacity:0}15%{opacity:.4}50%{opacity:.3}85%{opacity:.2}100%{transform:translateY(-5vh) translateX(-50px) rotate(180deg);opacity:0}}
@keyframes featherSway{0%,100%{margin-left:0}25%{margin-left:15px}75%{margin-left:-15px}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment();for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var h=14+Math.random()*20;e.style.cssText="width:3px;height:"+h+"px;left:"+(5+Math.random()*90)+"%;top:-"+(h+5)+"px;background:linear-gradient(180deg,transparent 0%,rgba(255,255,255,0.6) 30%,rgba(230,220,210,0.5) 70%,transparent 100%);border-radius:50% 50% 20% 20%;box-shadow:0 0 4px rgba(255,255,255,0.2);animation:featherFloat "+(20+Math.random()*25)+"s ease-in-out infinite,featherSway "+(4+Math.random()*3)+"s ease-in-out infinite;animation-delay:"+(Math.random()*25)+"s,0s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- FIREFLIES ----
    elseif ($particleType === 'fireflies') {
        $css .= '
.theme-particle{position:fixed;pointer-events:none;z-index:9999;will-change:transform}
@keyframes fireflyFloat{0%{transform:translateY(0) translateX(0);opacity:0}15%{opacity:1}75%{opacity:.8}100%{transform:translateY(-105vh) translateX(30px);opacity:0}}
@keyframes fireflyBlink{0%,100%{opacity:.15;box-shadow:0 0 2px rgba(255,255,100,0.2)}50%{opacity:1;box-shadow:0 0 10px rgba(255,255,100,0.8),0 0 20px rgba(255,255,100,0.4)}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment();for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var s=3+Math.random()*4;e.style.cssText="width:"+s+"px;height:"+s+"px;left:"+(2+Math.random()*96)+"%;bottom:"+(2+Math.random()*80)+"%;background:radial-gradient(circle at 30% 30%,#FFFFAA,#FFD700);border-radius:50%;box-shadow:0 0 "+(s*3)+"px rgba(255,255,100,0.6);animation:fireflyFloat "+(15+Math.random()*20)+"s ease-in-out infinite,fireflyBlink "+(1+Math.random()*2.5)+"s ease-in-out infinite;animation-delay:"+(Math.random()*20)+"s,0s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- RAIN ----
    elseif ($particleType === 'rain') {
        $css .= '
.theme-particle{position:fixed;top:0;pointer-events:none;z-index:9999;will-change:transform}
@keyframes rainFall{0%{transform:translateY(-5vh) translateX(0);opacity:0}10%{opacity:0.7}90%{opacity:0.3}100%{transform:translateY(105vh) translateX(-20px);opacity:0}}';
        $js .= '(function(){function i(){var c='.($count*2).',p=document.createDocumentFragment();for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var h=12+Math.random()*20;e.style.cssText="width:2px;height:"+h+"px;left:"+(Math.random()*100)+"%;top:-"+(h+10)+"px;background:linear-gradient(180deg,transparent,rgba(150,180,255,0.6));border-radius:0 0 2px 2px;animation:rainFall "+(0.5+Math.random()*0.8)+"s linear infinite;animation-delay:"+(Math.random()*1.5)+"s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- MIST ----
    elseif ($particleType === 'mist') {
        $css .= '
.theme-particle{position:fixed;bottom:0;pointer-events:none;z-index:9999;will-change:transform}
@keyframes mistDrift{0%{transform:translateX(-10vw) scale(0.8);opacity:0}15%{opacity:0.4}75%{opacity:0.2}100%{transform:translateX(110vw) scale(1.2);opacity:0}}';
        $js .= '(function(){function i(){var c='.($count/2).',p=document.createDocumentFragment();for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var s=40+Math.random()*80;e.style.cssText="width:"+s+"px;height:"+(s*0.4)+"px;left:-"+(s+10)+"px;bottom:"+(5+Math.random()*50)+"%;background:radial-gradient(ellipse at center,rgba(200,210,230,"+(0.08+Math.random()*0.12)+"),transparent);border-radius:50%;animation:mistDrift "+(20+Math.random()*30)+"s ease-in-out infinite;animation-delay:"+(Math.random()*20)+"s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- SMOKE ----
    elseif ($particleType === 'smoke') {
        $css .= '
.theme-particle{position:fixed;bottom:0;pointer-events:none;z-index:9999;will-change:transform}
@keyframes smokeRise{0%{transform:translateY(0) scale(0.5);opacity:0}20%{opacity:0.3}70%{opacity:0.15}100%{transform:translateY(-110vh) scale(2.5);opacity:0}}';
        $js .= '(function(){function i(){var c='.($count/2).',p=document.createDocumentFragment();for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var s=10+Math.random()*30;e.style.cssText="width:"+s+"px;height:"+s+"px;left:"+(5+Math.random()*90)+"%;bottom:"+(2+Math.random()*20)+"%;background:radial-gradient(circle at center,rgba(180,180,190,"+(0.06+Math.random()*0.08)+"),transparent);border-radius:50%;filter:blur("+(2+Math.random()*4)+"px);animation:smokeRise "+(10+Math.random()*15)+"s ease-out infinite;animation-delay:"+(Math.random()*10)+"s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    // ---- STONE RAIN ----
    elseif ($particleType === 'stone_rain') {
        $css .= '
.theme-particle{position:fixed;top:0;pointer-events:none;z-index:9999;will-change:transform}
@keyframes stoneFall{0%{transform:translateY(-5vh) rotate(0deg);opacity:0}10%{opacity:0.8}90%{opacity:0.6}100%{transform:translateY(105vh) rotate(360deg);opacity:0}}';
        $js .= '(function(){function i(){var c='.$count.',p=document.createDocumentFragment();for(var j=0;j<c;j++){var e=document.createElement("div");e.className="theme-particle";var s=6+Math.random()*12;e.style.cssText="width:"+s+"px;height:"+(s*(0.6+Math.random()*0.6))+"px;left:"+(2+Math.random()*96)+"%;top:-"+(s+10)+"px;background:radial-gradient(circle at 35% 35%,#8B7355,#5C4033,#3E2723);border-radius:"+(2+Math.random()*4)+"px;box-shadow:inset -2px -2px 4px rgba(0,0,0,0.4),inset 2px 2px 4px rgba(255,255,255,0.15);animation:stoneFall "+(3+Math.random()*4)+"s ease-in infinite;animation-delay:"+(Math.random()*5)+"s";p.appendChild(e)}document.body.appendChild(p)}if(document.body)i();else window.addEventListener("DOMContentLoaded",i)})();';
    }

    if ($css) $html .= '<style id="theme-decorations-css">' . $css . '</style>';
    if ($js) $html .= '<script id="theme-decorations-js">' . $js . '</script>';

    // Quick Styles (visual editor for non-devs)
    $qs = $dec['quick_styles'] ?? [];
    $qsCss = '';
    if (!empty($qs['bg_color'])) {
        $qsCss .= 'body{background-color:' . $qs['bg_color'] . '!important}';
    }
    if (!empty($qs['text_color'])) {
        $qsCss .= 'body{color:' . $qs['text_color'] . '!important}';
    }
    if (!empty($qs['link_color'])) {
        $qsCss .= 'a:not(.btn):not(.btn-gold-sm){color:' . $qs['link_color'] . '!important}';
        $qsCss .= 'a:not(.btn):not(.btn-gold-sm):hover{color:' . $qs['link_color'] . '!important;opacity:0.85}';
    }
    if (!empty($qs['heading_color'])) {
        $qsCss .= 'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6{color:' . $qs['heading_color'] . '!important}';
    }
    if (!empty($qs['btn_bg'])) {
        $qsCss .= '.btn-gold,.btn-primary,.btn-dark-custom,.btn-outline-dark-custom:hover{background-color:' . $qs['btn_bg'] . '!important;border-color:' . $qs['btn_bg'] . '!important}';
    }
    if (!empty($qs['btn_text'])) {
        $qsCss .= '.btn-gold,.btn-primary,.btn-dark-custom{color:' . $qs['btn_text'] . '!important}';
    }
    if (!empty($qs['navbar_bg'])) {
        $qsCss .= '.navbar{background-color:' . $qs['navbar_bg'] . '!important}';
    }
    if (!empty($qs['card_bg'])) {
        $qsCss .= '.card{background-color:' . $qs['card_bg'] . '!important}';
    }
    // Dark mode overrides
    $qsDarkCss = '';
    if (!empty($qs['dark_bg_color'])) {
        $qsDarkCss .= 'body{background-color:' . $qs['dark_bg_color'] . '!important}';
    }
    if (!empty($qs['dark_text_color'])) {
        $qsDarkCss .= 'body{color:' . $qs['dark_text_color'] . '!important}';
    }
    if (!empty($qs['dark_link_color'])) {
        $qsDarkCss .= 'a:not(.btn):not(.btn-gold-sm){color:' . $qs['dark_link_color'] . '!important}';
        $qsDarkCss .= 'a:not(.btn):not(.btn-gold-sm):hover{color:' . $qs['dark_link_color'] . '!important;opacity:0.85}';
    }
    if (!empty($qs['dark_heading_color'])) {
        $qsDarkCss .= 'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6{color:' . $qs['dark_heading_color'] . '!important}';
    }
    if (!empty($qs['dark_btn_bg'])) {
        $qsDarkCss .= '.btn-gold,.btn-primary,.btn-dark-custom,.btn-outline-dark-custom:hover{background-color:' . $qs['dark_btn_bg'] . '!important;border-color:' . $qs['dark_btn_bg'] . '!important}';
    }
    if (!empty($qs['dark_btn_text'])) {
        $qsDarkCss .= '.btn-gold,.btn-primary,.btn-dark-custom{color:' . $qs['dark_btn_text'] . '!important}';
    }
    if (!empty($qs['dark_navbar_bg'])) {
        $qsDarkCss .= '.navbar{background-color:' . $qs['dark_navbar_bg'] . '!important}';
    }
    if (!empty($qs['dark_card_bg'])) {
        $qsDarkCss .= '.card{background-color:' . $qs['dark_card_bg'] . '!important}';
    }
    if ($qsDarkCss) {
        $qsCss .= '[data-theme="dark"]{' . $qsDarkCss . '}';
    }
    if (!empty($qs['border_radius'])) {
        $brMap = ['none'=>'0','sm'=>'4px','md'=>'8px','lg'=>'16px'];
        $brVal = $brMap[$qs['border_radius']] ?? '';
        if ($brVal !== '') {
            $qsCss .= '.card,.btn,.form-control,.form-select,.modal-content,.list-group-item{border-radius:' . $brVal . '!important}';
        }
    }
    if (!empty($qs['font_size'])) {
        $fsMap = ['sm'=>'14px','md'=>'16px','lg'=>'18px'];
        $fsVal = $fsMap[$qs['font_size']] ?? '';
        if ($fsVal !== '') {
            $qsCss .= 'body{font-size:' . $fsVal . '!important}';
        }
    }
    if ($qsCss) {
        $html .= '<style id="theme-quick-styles">' . $qsCss . '</style>';
    }

    return $html;
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

function needsProfileCompletion(): bool {
    if (!isLoggedIn()) return false;
    $phone = $_SESSION['user_phone'] ?? '';
    return empty($phone);
}

function requireCompleteProfile(): void {
    if (needsProfileCompletion()) {
        $current = basename($_SERVER['SCRIPT_NAME']);
        if ($current !== 'complete-profile.php') {
            header('Location: ' . SITE_URL . '/auth/complete-profile.php');
            exit;
        }
    }
}
