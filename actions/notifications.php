<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/notifications.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'poll') {
    $count = getUnreadNotificationCount($_SESSION['user_id']);
    $notifs = getNotifications($_SESSION['user_id'], 5);
    sendJson(['count' => $count, 'notifications' => $notifs]);
}

if ($action === 'mark_read') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) markNotificationRead($id, $_SESSION['user_id']);
    sendJson(['ok' => true]);
}

if ($action === 'mark_all_read') {
    markAllNotificationsRead($_SESSION['user_id']);
    sendJson(['ok' => true]);
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) deleteNotification($id);
    sendJson(['ok' => true]);
}

if ($action === 'delete_broadcast') {
    $ids = $_GET['ids'] ?? '';
    if ($ids) deleteBroadcastGroup($ids);
    sendJson(['ok' => true]);
}

sendJson(['error' => 'Invalid action'], 400);