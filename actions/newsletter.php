<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { redirect(SITE_URL . '/index.php', 'Invalid token.', 'error'); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { redirect(SITE_URL . '/index.php', 'Invalid email.', 'error'); }
    $stmt = $db->prepare("SELECT id FROM newsletters WHERE email = ?");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        $db->prepare("INSERT INTO newsletters (email) VALUES (?)")->execute([$email]);
    }
    redirect(SITE_URL . '/index.php', 'Subscribed successfully!');
}
redirect(SITE_URL . '/index.php');
