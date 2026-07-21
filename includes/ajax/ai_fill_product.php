<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'POST required'], 405);
}

$imageData = trim($_POST['image'] ?? '');
if (!$imageData) {
    sendJson(['error' => 'No image data provided.'], 400);
}

// Fetch categories for mapping
$cats = $db->query("SELECT id, name_en, name_sw FROM categories WHERE status = 1 ORDER BY name_en")->fetchAll();
$catNames = [];
foreach ($cats as $c) {
    $catNames[] = $c['name_en'];
    if ($c['name_sw']) $catNames[] = $c['name_sw'];
}
$catList = implode(', ', array_unique($catNames));

$pal = colorPalette();
$colorList = implode(', ', array_keys($pal));

function parseAIResponse(string $content): ?array {
    $content = trim($content);
    if (strpos($content, '```') === 0) {
        $content = substr($content, strpos($content, "\n") + 1);
        $content = substr($content, 0, strrpos($content, '```'));
        $content = trim($content);
    }
    $result = json_decode($content, true);
    return ($result && isset($result['name_en'])) ? $result : null;
}

function mapCategory(?string $catName, array $cats): string {
    if (empty($catName)) return '';
    foreach ($cats as $c) {
        if (strcasecmp($c['name_en'], $catName) === 0 || strcasecmp($c['name_sw'] ?? '', $catName) === 0) {
            return (string)$c['id'];
        }
    }
    foreach ($cats as $c) {
        if (stripos($catName, $c['name_en']) !== false || stripos($c['name_en'], $catName) !== false ||
            ($c['name_sw'] && (stripos($catName, $c['name_sw']) !== false || stripos($c['name_sw'], $catName) !== false))) {
            return (string)$c['id'];
        }
    }
    return '';
}

function formatResult(array $result, array $cats): array {
    return [
        'name_en' => $result['name_en'] ?? '',
        'name_sw' => $result['name_sw'] ?? '',
        'category_id' => mapCategory($result['category'] ?? null, $cats),
        'price' => $result['price'] ?? '',
        'brand' => $result['brand'] ?? '',
        'sizes' => $result['sizes'] ?? [],
        'colors' => $result['colors'] ?? [],
        'description_en' => $result['description_en'] ?? '',
        'description_sw' => $result['description_sw'] ?? '',
        'gender' => $result['gender'] ?? '',
    ];
}

function buildSystemPrompt($catList, $colorList): string {
    return "You are a fashion product analyzer. Analyze the product image and return ONLY valid JSON with these fields:
{
  \"name_en\": \"English product name (title case, max 100 chars)\",
  \"name_sw\": \"Swahili product name (translated, max 100 chars)\",
  \"category\": \"Best matching category from this list: $catList. Return exact English name or empty string.\",
  \"price\": estimated retail price as integer in TZS,
  \"brand\": \"Brand name if visible, else empty string\",
  \"sizes\": [\"Available sizes from: XS, S, M, L, XL, XXL, 3XL\"],
  \"colors\": [\"Colors detected from: $colorList\"],
  \"description_en\": \"Detailed English description (2-3 sentences)\",
  \"description_sw\": \"Swahili translation of description\",
  \"gender\": \"male, female, or unisex\"
}

Rules: price must be reasonable TZS for Tanzania (t-shirt 15000-35000, dress 25000-80000, suit 80000-200000). Respond with ONLY the JSON.";
}

function callGemini($imageData, $catList, $colorList, $model, &$err = ''): ?array {
    $apiKey = GEMINI_API_KEY;
    if (!$apiKey) { $err = 'GEMINI_API_KEY not set'; return null; }

    if (!preg_match('/^data:(image\/\w+);base64,(.+)$/s', $imageData, $m)) {
        $err = 'Invalid image data URL'; return null;
    }
    $mimeType = $m[1];
    $rawBase64 = $m[2];

    $systemPrompt = buildSystemPrompt($catList, $colorList);
    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => $systemPrompt],
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $rawBase64]],
            ]
        ]],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 1000,
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

    $maxRetries = 2;
    $retryDelay = 3;
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if (!$response) {
            $err = "curl error: $curlErr";
            return null;
        }

        if ($httpCode === 429) {
            $body = json_decode($response, true);
            $msg = $body['error']['message'] ?? $response;
            $err = "HTTP 429 (attempt $attempt/$maxRetries): $msg";
            if ($attempt < $maxRetries) {
                sleep($retryDelay * $attempt);
                continue;
            }
            return null;
        }

        if ($httpCode !== 200) {
            $body = json_decode($response, true);
            $msg = $body['error']['message'] ?? $response;
            $err = "HTTP $httpCode: $msg";
            return null;
        }

        break;
    }

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$text) { $err = 'empty response'; return null; }

    return parseAIResponse($text);
}

function callGroq($imageData, $catList, $colorList, &$err = ''): ?array {
    $apiKey = GROQ_API_KEY;
    if (!$apiKey) { $err = 'GROQ_API_KEY not set in .env'; return null; }

    $systemPrompt = buildSystemPrompt($catList, $colorList);
    $payload = [
        'model' => GROQ_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Analyze this fashion product image and provide complete product details.'],
                ['type' => 'image_url', 'image_url' => ['url' => $imageData]],
            ]],
        ],
        'max_tokens' => 1000,
        'temperature' => 0.3,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => GROQ_ENDPOINT,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if (!$response) {
        $err = "Groq curl error: $curlErr";
        return null;
    }

    if ($httpCode !== 200) {
        $body = json_decode($response, true);
        $msg = $body['error']['message'] ?? $response;
        $err = "Groq HTTP $httpCode: $msg";
        return null;
    }

    $data = json_decode($response, true);
    $text = $data['choices'][0]['message']['content'] ?? '';
    if (!$text) { $err = 'Groq returned empty response'; return null; }

    return parseAIResponse($text);
}

function callOpenRouter($imageData, $catList, $colorList, $model, &$err = ''): ?array {
    $apiKey = AI_FALLBACK_KEY;
    if (!$apiKey) { $err = 'AI_FALLBACK_KEY not set'; return null; }

    $systemPrompt = buildSystemPrompt($catList, $colorList);
    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Analyze this fashion product image and provide complete product details.'],
                ['type' => 'image_url', 'image_url' => ['url' => $imageData]],
            ]],
        ],
        'max_tokens' => 1000,
        'temperature' => 0.3,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => AI_FALLBACK_ENDPOINT,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: ' . SITE_URL,
            'X-Title: Innocé Outfits - Product AI',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if (!$response) {
        $err = "curl error: $curlErr";
        return null;
    }

    if ($httpCode !== 200) {
        $body = json_decode($response, true);
        $msg = $body['error']['message'] ?? $response;
        $err = "HTTP $httpCode: $msg";
        return null;
    }

    $data = json_decode($response, true);
    $text = $data['choices'][0]['message']['content'] ?? '';
    if (!$text) { $err = 'empty response'; return null; }

    return parseAIResponse($text);
}

$result = null;
$used = '';
$errors = [];

// Try Groq (free Llama vision)
$err = '';
$result = callGroq($imageData, $catList, $colorList, $err);
if ($result) {
    $used = 'groq/' . GROQ_MODEL;
} else {
    $errors[] = "Groq: $err";
}

// Try Gemini models in order (different models may have separate free quotas)
if (!$result) {
    $geminiModels = ['gemini-3.1-flash-lite', 'gemini-3.5-flash', 'gemini-3-flash-preview', 'gemini-2.0-flash', 'gemini-2.0-flash-lite'];
    foreach ($geminiModels as $model) {
        $err = '';
        $result = callGemini($imageData, $catList, $colorList, $model, $err);
        if ($result) {
            $used = "gemini/$model";
            break;
        }
        $errors[] = "Gemini $model: $err";
    }
}

// Fallback to OpenRouter models (daily refresh)
if (!$result) {
    $orModels = [AI_FALLBACK_MODEL, 'meta-llama/llama-4-scout-17b-16e-instruct', 'google/gemma-3-27b-it', 'qwen/qwen3.6-27b'];
    $orModels = array_unique($orModels);
    foreach ($orModels as $model) {
        $err = '';
        $result = callOpenRouter($imageData, $catList, $colorList, $model, $err);
        if ($result) {
            $used = "openrouter/$model";
            break;
        }
        $errors[] = "OpenRouter $model: $err";
    }
}

if (!$result) {
    sendJson(['error' => 'All AI providers failed.', 'details' => $errors], 502);
}

$final = formatResult($result, $cats);
$final['_provider'] = $used;
sendJson($final);
