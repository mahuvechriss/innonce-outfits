<?php

require_once __DIR__ . '/email.php';
require_once __DIR__ . '/beem.php';

function notifyUser(int $userId, string $title, string $message, string $type = 'info', ?string $orderNumber = null): void {
    global $db;

    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $message, $type]);

    $stmt = $db->prepare("SELECT email, phone, notify_email, notify_sms, notify_inapp FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) return;

    $prefEmail = $user['notify_email'] ?? 1;
    $prefSms   = $user['notify_sms'] ?? 0;
    $prefInapp = $user['notify_inapp'] ?? 1;

    if ($prefEmail && !empty($user['email'])) {
        if ($orderNumber) {
            sendOrderStatusEmail($user['email'], $orderNumber, $message);
        } else {
            sendEmail($user['email'], $title, nl2br($message));
        }
    }

    if ($prefSms && !empty($user['phone'])) {
        sendSms($user['phone'], "$title - $message");
    }
}

function notifyAdmin(string $title, string $message, string $type = 'info'): void {
    global $db;

    $stmt = $db->prepare("SELECT id, email, phone, notify_email, notify_sms FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll();

    foreach ($admins as $admin) {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin['id'], $title, $message, $type]);

        if (($admin['notify_email'] ?? 1) && !empty($admin['email'])) {
            sendEmail($admin['email'], $title, nl2br($message));
        }
        if (($admin['notify_sms'] ?? 0) && !empty($admin['phone'])) {
            sendSms($admin['phone'], "$title - $message");
        }
    }
}

function notifyOrderUpdate(int $orderId, string $status, string $description): void {
    global $db;

    $stmt = $db->prepare("SELECT o.*, u.email, u.phone, u.name, u.notify_email, u.notify_sms, u.notify_inapp FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return;

    $title = "Order #{$order['order_number']}";
    $message = $description ?: "Status updated to $status";

    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')");
    $stmt->execute([$order['user_id'], $title, $message]);

    if (($order['notify_email'] ?? 1) && !empty($order['email'])) {
        sendOrderStatusEmail($order['email'], $order['order_number'], $status);
    }

    if (($order['notify_sms'] ?? 0) && !empty($order['phone'])) {
        sendSms($order['phone'], "INNOCE: Order #{$order['order_number']} - $message");
    }

    notifyAdmin("Order Updated", "Order #{$order['order_number']} by {$order['name']} - $message");

    $stmt = $db->prepare("UPDATE notifications SET is_read = 0 WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$order['user_id']]);
}

function getUnreadNotificationCount(int $userId): int {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getNotifications(int $userId, int $limit = 20): array {
    global $db;
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function markNotificationRead(int $notificationId, int $userId): void {
    global $db;
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
}

function markAllNotificationsRead(int $userId): void {
    global $db;
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}

function deleteNotification(int $notificationId): void {
    global $db;
    $db->prepare("DELETE FROM notifications WHERE id = ?")->execute([$notificationId]);
}

function getSentBroadcasts(int $limit = 50): array {
    global $db;
    $stmt = $db->query("
        SELECT n.title, n.message, n.type, DATE(n.created_at) as sent_date, MAX(n.created_at) as last_sent,
               COUNT(*) as recipient_count,
               GROUP_CONCAT(DISTINCT n.id ORDER BY n.id SEPARATOR ',') as notif_ids
        FROM notifications n
        WHERE n.type = 'broadcast'
        GROUP BY n.title, n.message, DATE(n.created_at)
        ORDER BY last_sent DESC
        LIMIT $limit
    ");
    return $stmt->fetchAll();
}

function deleteBroadcastGroup(string $notifIds): void {
    global $db;
    $ids = array_map('intval', explode(',', $notifIds));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("DELETE FROM notifications WHERE id IN ($placeholders)")->execute($ids);
}

function getNewArrivalProducts(int $limit = 4): array {
    global $db;
    $stmt = $db->query("SELECT p.id, p.name_en, p.slug, p.price, p.discount_price,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM products p WHERE p.status = 'active' AND p.new_arrival = 1
        ORDER BY p.created_at DESC LIMIT $limit");
    $products = $stmt->fetchAll();
    $result = [];
    foreach ($products as $p) {
        $img = $p['image'] ? SITE_URL . '/' . $p['image'] : 'https://placehold.co/200x250/121212/D4A017?text=INNOCE';
        $price = $p['discount_price'] ?: $p['price'];
        $result[] = [
            'name' => $p['name_en'],
            'url' => SITE_URL . '/shop/index.php?product=' . $p['slug'],
            'image' => $img,
            'price' => number_format((float)$price),
        ];
    }
    return $result;
}

function sendBroadcastNotification(string $title, string $message, string $recipientType = 'all', array $channels = ['inapp'], ?string $productLink = null): array {
    global $db;

    if ($recipientType === 'customers') {
        $stmt = $db->query("SELECT id, email, phone, notify_email, notify_sms, notify_inapp FROM users WHERE role = 'customer'");
    } elseif ($recipientType === 'opted_in') {
        $stmt = $db->query("SELECT id, email, phone, notify_email, notify_sms, notify_inapp FROM users WHERE role = 'customer' AND (notify_email = 1 OR notify_sms = 1 OR notify_inapp = 1)");
    } elseif ($recipientType === 'admins') {
        $stmt = $db->query("SELECT id, email, phone, notify_email, notify_sms, notify_inapp FROM users WHERE role = 'admin'");
    } else {
        $stmt = $db->query("SELECT id, email, phone, notify_email, notify_sms, notify_inapp FROM users");
    }

    $users = $stmt->fetchAll();
    $totalUsers = count($users);

    $fullMessage = $message;
    if ($productLink) {
        $fullMessage .= "\n\nView: $productLink";
    }

    $inappEnabled = in_array('inapp', $channels);
    $emailEnabled = in_array('email', $channels);
    $smsEnabled = in_array('sms', $channels);

    $stats = [
        'total_users' => $totalUsers,
        'inapp_sent' => 0,
        'email_eligible' => 0,
        'email_sent' => 0,
        'email_failed' => 0,
        'sms_eligible' => 0,
        'sms_sent' => 0,
        'sms_failed' => 0,
    ];

    $newProducts = getNewArrivalProducts();

    foreach ($users as $user) {
        $prefEmail = $user['notify_email'] ?? 1;
        $prefSms   = $user['notify_sms'] ?? 0;
        $prefInapp = $user['notify_inapp'] ?? 1;

        if ($inappEnabled && $prefInapp) {
            $stmt2 = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'broadcast')");
            $stmt2->execute([$user['id'], $title, $fullMessage]);
            $stats['inapp_sent']++;
        }

        if ($emailEnabled && $prefEmail && !empty($user['email'])) {
            $stats['email_eligible']++;
            $emailBody = buildBroadcastEmailBody($title, $message, $productLink, $newProducts);
            if (sendEmail($user['email'], $title, $emailBody)) {
                $stats['email_sent']++;
            } else {
                $stats['email_failed']++;
            }
        }

        if ($smsEnabled && $prefSms && !empty($user['phone'])) {
            $stats['sms_eligible']++;
            if (sendSms($user['phone'], "$title - $fullMessage")) {
                $stats['sms_sent']++;
            } else {
                $stats['sms_failed']++;
            }
        }
    }

    return $stats;
}