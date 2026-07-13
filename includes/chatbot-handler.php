<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$message = trim($_POST['message'] ?? $_GET['message'] ?? '');
if (!$message) {
    sendJson(['reply' => 'Hello! I am INNOCEshow. How can I help you today?', 'products' => []]);
}

// --- Build dynamic store info from database (auto-updates when admin changes settings) ---
$siteName = getSetting('site_name', 'INNOCE OUTFITS');
$currency = getSetting('currency', CURRENCY);
$taxRate = getSetting('tax_rate', TAX_RATE);
$shipThreshold = getSetting('shipping_threshold', SHIPPING_THRESHOLD);
$shipRateDefault = getSetting('shipping_rate_default', SHIPPING_RATE_DEFAULT);
$shipRateReduced = getSetting('shipping_rate_reduced', SHIPPING_RATE_REDUCED);
$freeShipMin = getSetting('free_shipping_min', FREE_SHIPPING_MIN);
$defaultPayment = getSetting('default_payment', 'mpesa');
$paymentLabels = ['mpesa' => 'M-Pesa', 'airtel_money' => 'Airtel Money', 'tigo_pesa' => 'Tigo Pesa', 'halopesa' => 'HaloPesa'];
$paymentMethods = implode(', ', array_values($paymentLabels));

$totalProducts = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$totalCategories = $db->query("SELECT COUNT(*) FROM categories WHERE status=1")->fetchColumn();
$allCategories = $db->query("SELECT name_en, name_sw FROM categories WHERE status=1")->fetchAll();
$catList = implode(', ', array_map(function($c) { return $c['name_en'] . ($c['name_sw'] ? '/' . $c['name_sw'] : ''); }, $allCategories));
$pal = colorPalette();
$colorList = implode(', ', array_keys($pal));

$storeInfo = "- $totalProducts products across $totalCategories categories
- Currency: $currency (Tax: $taxRate%)
- Categories: $catList
- Colors: $colorList
- Payment methods: $paymentMethods
- Physical shop: $siteName, One way, Tenth Rd, Dodoma (Google Maps: https://www.google.com/maps/dir//INNOCE+OUTFITS,+One+way,+Tenth+Rd,+Dodoma)
- WhatsApp: +255 752 263 474 / +255 683 086 608
- Delivery: Available. Shipping = percentage of subtotal.
  - If subtotal < $shipThreshold $currency: shipping = subtotal × $shipRateDefault%
  - If subtotal >= $shipThreshold $currency: shipping = subtotal × $shipRateReduced%
  - Free shipping if subtotal >= $freeShipMin $currency
  - Pickup at our shop: free (no shipping fee)";

$model = defined('AI_FALLBACK_MODEL') ? AI_FALLBACK_MODEL : 'openai/gpt-4o-mini';
$aiData = null;

// --- Step 1: AI determines intent ---
$intentPrompt = "You are INNOCEshow, a helpful fashion store assistant for $siteName.

Store info:
$storeInfo

Analyze intent. Respond ONLY with valid JSON:
{
  \"intent\": \"product_search\" or \"conversation\",
  \"filters\": {
    \"category\": \"category name or null\",
    \"color\": \"color name or null\",
    \"max_price\": number or null,
    \"keywords\": \"search keywords or null\"
  },
  \"needs_math\": true or false (true if user asks about quantities, totals, sums, multiplication, \"how much for X items\", \"jumla\")
}

product_search = user wants to SEE, FIND, BROWSE, CALCULATE about products, prices, stock, catalog, or asks ANY of: \"what do you sell\", \"what is available\", \"what products do you have\", \"what do you have\", \"show me everything\", \"kuna nini\", \"mnauza nini\", \"what do you offer\", \"catalog\", \"inventory\", \"products\".
conversation = ONLY pure greetings (\"hi\", \"hello\"), identity (\"who are you\", \"your name\"), thanks, or truly unrelated topic (weather, news, sports).";

try {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => AI_FALLBACK_ENDPOINT,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_FALLBACK_KEY,
            'HTTP-Referer: ' . SITE_URL,
            'X-Title: Innocé Outfits',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $intentPrompt],
                ['role' => 'user', 'content' => "User: \"$message\""],
            ],
            'max_tokens' => 300,
            'temperature' => 0.3,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response && $httpCode === 200) {
        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        $aiData = json_decode($content, true);
    }
} catch (Exception $e) {
    error_log("OpenRouter intent call failed: " . $e->getMessage());
}

// Force product_search for explicit product inquiries (backup for AI misclassification)
$productKeywords = ['sell', 'offer', 'available', 'catalog', 'inventory', 'products', 'stock', 'bidhaa', 'what do you have', 'what is there', 'show everything', 'kuna nini', 'mnauza nini', 'orodha'];
if ($aiData && $aiData['intent'] === 'conversation') {
    $lower = strtolower($message);
    foreach ($productKeywords as $kw) {
        if (strpos($lower, $kw) !== false) {
            $aiData['intent'] = 'product_search';
            $aiData['filters'] = $aiData['filters'] ?? ['category' => null, 'color' => null, 'max_price' => null, 'keywords' => null];
            break;
        }
    }
}

// --- Step 2: Search products if needed ---
$productCards = [];
$reply = '';
$needsMath = false;

if ($aiData && $aiData['intent'] === 'product_search' && isset($aiData['filters'])) {
    $f = $aiData['filters'];
    $needsMath = !empty($aiData['needs_math']);

    $sql = "SELECT p.id, p.name_en, p.name_sw, p.slug, p.price, p.discount_price, p.description_en, p.colors, c.name_en as cat_name,
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
            FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status='active'";
    $params = [];
    $conditions = [];

    if (!empty($f['category']) && $f['category'] !== 'null') {
        $catMatch = $db->prepare("SELECT id FROM categories WHERE (name_en LIKE ? OR name_sw LIKE ?) AND status=1 LIMIT 1");
        $catLike = '%' . $f['category'] . '%';
        $catMatch->execute([$catLike, $catLike]);
        $catRow = $catMatch->fetch();
        if ($catRow) {
            $conditions[] = "p.category_id = ?";
            $params[] = $catRow['id'];
        }
    }

    if (!empty($f['color']) && $f['color'] !== 'null') {
        $conditions[] = "JSON_SEARCH(LOWER(p.colors), 'one', ?) IS NOT NULL";
        $params[] = strtolower($f['color']);
    }

    if (!empty($f['max_price']) && $f['max_price'] > 0) {
        $conditions[] = "IF(p.discount_price > 0, p.discount_price, p.price) <= ?";
        $params[] = (float) $f['max_price'];
    }

    $keywords = trim($f['keywords'] ?? '');
    if ($keywords && $keywords !== 'null') {
        $terms = explode(' ', $keywords);
        $kwConditions = [];
        foreach ($terms as $term) {
            $term = trim($term);
            if (strlen($term) < 2) continue;
            $kwConditions[] = "(p.name_en LIKE ? OR p.name_sw LIKE ? OR p.description_en LIKE ? OR p.description_sw LIKE ?)";
            $likeParam = "%$term%";
            $params[] = $likeParam; $params[] = $likeParam; $params[] = $likeParam; $params[] = $likeParam;
        }
        if (!empty($kwConditions)) {
            $conditions[] = "(" . implode(' AND ', $kwConditions) . ")";
        }
    }

    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY p.created_at DESC LIMIT 15";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll() ?? [];

    if (empty($products) && empty($f['category']) && empty($f['color']) && empty($f['max_price']) && empty(trim($f['keywords'] ?? ''))) {
        $stmt = $db->query("SELECT p.id, p.name_en, p.name_sw, p.slug, p.price, p.discount_price, p.description_en, p.colors, c.name_en as cat_name,
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
            FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status='active' ORDER BY p.created_at DESC LIMIT 15");
        $products = $stmt->fetchAll();
    }

    foreach ($products as $p) {
        $price = $p['discount_price'] ?: $p['price'];
        $originalPrice = $p['discount_price'] ? $p['price'] : null;
        $img = $p['image'] ? SITE_URL . '/' . $p['image'] : SITE_URL . '/assets/images/placeholder.png';
        $link = SITE_URL . '/shop/index.php?product=' . urlencode($p['slug']);
        $colors = $p['colors'] ? json_decode($p['colors'], true) : [];
        $colorSwatches = [];
        foreach ($colors as $c) {
            $colorSwatches[] = ['name' => $c, 'hex' => $pal[$c] ?? '#ccc'];
        }
        $productCards[] = [
            'name' => $p['name_en'],
            'name_sw' => $p['name_sw'],
            'slug' => $p['slug'],
            'price' => (float) $price,
            'price_formatted' => "$currency " . number_format($price),
            'original_price' => $originalPrice ? (float) $originalPrice : null,
            'original_price_formatted' => $originalPrice ? "$currency " . number_format($originalPrice) : null,
            'image' => $img,
            'link' => $link,
            'category' => $p['cat_name'] ?? '',
            'colors' => $colorSwatches,
        ];
    }

    // --- Step 3: Generate final reply with AI (dynamic store info) ---
    $hasProducts = !empty($productCards);

    $replyPrompt = "You are INNOCEshow, a helpful fashion store assistant for $siteName.
Currency: $currency
Website: " . SITE_URL . "
$siteName, One way, Tenth Rd, Dodoma
Google Maps: https://www.google.com/maps/dir//INNOCE+OUTFITS,+One+way,+Tenth+Rd,+Dodoma
WhatsApp: +255 752 263 474 / +255 683 086 608
Shipping: $shipRateDefault% if under $shipThreshold $currency, $shipRateReduced% if over. Free above $freeShipMin $currency. Pickup = free.

User asked: \"$message\"
" . ($hasProducts ? "Products found:\n" . json_encode($productCards, JSON_UNESCAPED_UNICODE) : "No products found in the database.") . "

" . ($needsMath ? "IMPORTANT: The user is asking about calculations (totals, quantities, sums, etc.). Do the math using the product prices and show the result clearly. Include shipping if delivery is requested." : "If products were found, mention them naturally. If not, suggest alternatives.") . "

Instructions:
1. Answer in the same language as the user (English or Swahili).
2. Be concise (2-4 sentences). DO NOT repeat the user's question.
3. If the user asked for quantities or totals, CALCULATE and show the result (e.g., \"20 belts × TZS 12,000 = TZS 240,000\").
4. If the user asked about delivery cost, include the shipping fee in the total.
5. NEVER make up product names or prices. Use ONLY the data above.
6. DO NOT include product page URLs — product cards are shown separately instead.
7. For location: paste the Google Maps URL directly as a raw link (no markdown brackets).
8. For contact: include the WhatsApp number.";

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => AI_FALLBACK_ENDPOINT,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . AI_FALLBACK_KEY,
                'HTTP-Referer: ' . SITE_URL,
                'X-Title: Innocé Outfits',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $replyPrompt],
                    ['role' => 'user', 'content' => "Answer based on the product data provided."],
                ],
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode === 200) {
            $data = json_decode($response, true);
            $reply = $data['choices'][0]['message']['content'] ?? '';
        }
    } catch (Exception $e) {
        error_log("OpenRouter reply call failed: " . $e->getMessage());
    }

    if (!$reply) {
        if ($hasProducts) {
            $reply = "🔍 " . ($needsMath ? "Here are the products for your calculation:" : "I found these products for you:");
        } else {
            $reply = "Sorry, I couldn't find products matching \"$message\". Try different words or browse our categories.";
        }
    }
} else {
    // Conversation intent — AI reply with dynamic store info
    $convPrompt = "You are INNOCEshow, a helpful fashion store assistant for $siteName.
- Location: $siteName, One way, Tenth Rd, Dodoma
- Google Maps: https://www.google.com/maps/dir//INNOCE+OUTFITS,+One+way,+Tenth+Rd,+Dodoma
- WhatsApp: +255 752 263 474 / +255 683 086 608
- Website: " . SITE_URL . "
- Shipping: $shipRateDefault% of subtotal if under $shipThreshold $currency, $shipRateReduced% if over. Free above $freeShipMin $currency. Pickup = free.
Be friendly and concise. Answer in the same language as the user (English or Swahili).
For location: paste the Google Maps URL directly as a raw link.
For contact: include the WhatsApp number.";

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => AI_FALLBACK_ENDPOINT,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . AI_FALLBACK_KEY,
                'HTTP-Referer: ' . SITE_URL,
                'X-Title: Innocé Outfits',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $convPrompt],
                    ['role' => 'user', 'content' => $message],
                ],
                'max_tokens' => 200,
                'temperature' => 0.7,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode === 200) {
            $data = json_decode($response, true);
            $reply = $data['choices'][0]['message']['content'] ?? "Hello! How can I help you today?";
        } else {
            $reply = "Hello! How can I help you today?";
        }
    } catch (Exception $e) {
        $reply = "Hello! How can I help you today?";
    }
}

$reply = preg_replace('/\*\*(.*?)\*\*/', '*$1*', $reply);
$reply = preg_replace('/\*(.*?)\*/', '<strong>$1</strong>', $reply);

sendJson(['reply' => $reply, 'products' => $productCards]);
