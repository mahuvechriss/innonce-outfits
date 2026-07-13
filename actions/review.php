<?php
require_once __DIR__ . '/../config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { redirect(SITE_URL . '/shop/index.php', 'Invalid token.', 'error'); }
    $productId = (int)($_POST['product_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $review = trim($_POST['review'] ?? '');
    if (!$productId || !$rating || !$review) { redirect(SITE_URL . '/shop/index.php', 'Please fill all fields.', 'error'); }
    $stmt = $db->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $productId]);
    if ($stmt->fetch()) { redirect(SITE_URL . "/shop/index.php?product=" . $productId, 'You already reviewed this.', 'error'); }
    $stmt = $db->prepare("INSERT INTO product_reviews (user_id, product_id, rating, review, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$_SESSION['user_id'], $productId, $rating, $review]);
    $slug = $db->prepare("SELECT slug FROM products WHERE id = ?");
    $slug->execute([$productId]);
    $s = $slug->fetch();
    $stmt2 = $db->prepare("SELECT name_en FROM products WHERE id = ?");
    $stmt2->execute([$productId]);
    $p = $stmt2->fetch();
    require_once __DIR__ . '/../includes/notifications.php';
    notifyAdmin("New Product Review", "Product: {$p['name_en']}\nRating: $rating/5\nReview: $review\nBy: {$_SESSION['user_name']}");
    redirect(SITE_URL . "/shop/index.php?product=" . $s['slug'], 'Review submitted and pending approval.');
}
redirect(SITE_URL . '/shop/index.php');
