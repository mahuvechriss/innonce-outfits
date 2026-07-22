<?php
ob_start();
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Admin Dashboard';

$action = $_GET['action'] ?? 'dashboard';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionPost = $_POST['admin_action'] ?? '';
    if ($actionPost !== 'product_multi_save' && !verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect('index.php', 'Invalid token.', 'error');
    }


    // Products
    if ($actionPost === 'product_save') {
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameSw = trim($_POST['name_sw'] ?? '');
        $slug = slugify($nameEn);
        if (!$editId) {
            $baseSlug = $slug;
            $slugCounter = 1;
            $checkSlug = $db->prepare("SELECT id FROM products WHERE slug = ? AND deleted_at IS NULL");
            $checkSlug->execute([$slug]);
            while ($checkSlug->fetchColumn()) {
                $slug = $baseSlug . '-' . $slugCounter++;
                $checkSlug->execute([$slug]);
            }
        }
        $price = (float)($_POST['price'] ?? 0);
        $discountPrice = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
        $quantity = (int)($_POST['quantity'] ?? 0);
        $brand = trim($_POST['brand'] ?? '');
        $descEn = trim($_POST['description_en'] ?? '');
        $descSw = trim($_POST['description_sw'] ?? '');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $newArrival = isset($_POST['new_arrival']) ? 1 : 0;
        $status = $_POST['status'] ?? 'active';
        $sizes = !empty($_POST['sizes']) ? json_encode($_POST['sizes']) : null;
        $colors = !empty($_POST['colors_edit']) ? $_POST['colors_edit'] : (!empty($_POST['colors']) ? json_encode($_POST['colors']) : null);
        $editId = (int)($_POST['id'] ?? 0);

        if ($editId) {
            $oldStmt = $db->prepare("SELECT new_arrival FROM products WHERE id = ?");
            $oldStmt->execute([$editId]);
            $oldArrival = $oldStmt->fetchColumn();
            $stmt = $db->prepare("UPDATE products SET category_id=?, name_en=?, name_sw=?, slug=?, price=?, discount_price=?, quantity=?, brand=?, description_en=?, description_sw=?, featured=?, new_arrival=?, status=?, sizes=?, colors=? WHERE id=?");
            $stmt->execute([$categoryId, $nameEn, $nameSw, $slug, $price, $discountPrice, $quantity, $brand, $descEn, $descSw, $featured, $newArrival, $status, $sizes, $colors, $editId]);
            if (!empty($_FILES['image']['name'])) {
                $path = uploadFile($_FILES['image']);
                if ($path) {
                    $hash = md5_file($_FILES['image']['tmp_name']);
                    $db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$editId]);
                    $stmt = $db->prepare("SELECT id FROM product_images WHERE product_id = ? AND is_primary = 1");
                    $stmt->execute([$editId]);
                    if (!$stmt->fetch()) {
                        $db->prepare("INSERT INTO product_images (product_id, image_path, image_hash, is_primary) VALUES (?, ?, ?, 1)")->execute([$editId, $path, $hash]);
                    }
                }
            }
            if ($newArrival && !$oldArrival) {
                redirect('index.php?action=broadcast&product_id=' . $editId, 'Product updated! Send a new arrival notification to customers.');
            }
            redirect('index.php?action=products', 'Product updated.');
        } else {
            $stmt = $db->prepare("INSERT INTO products (category_id, name_en, name_sw, slug, price, discount_price, quantity, brand, description_en, description_sw, featured, new_arrival, status, sizes, colors, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $sku = strtoupper(substr($nameEn, 0, 3)) . '-' . time();
            $stmt->execute([$categoryId, $nameEn, $nameSw, $slug, $price, $discountPrice, $quantity, $brand, $descEn, $descSw, $featured, $newArrival, $status, $sizes, $colors, $sku]);
            $pid = $db->lastInsertId();
            if (!empty($_FILES['image']['name'])) {
                $path = uploadFile($_FILES['image']);
                if ($path) {
                    $hash = md5_file($_FILES['image']['tmp_name']);
                    $db->prepare("INSERT INTO product_images (product_id, image_path, image_hash, is_primary) VALUES (?, ?, ?, 1)")->execute([$pid, $path, $hash]);
                }
            }
            if ($newArrival) {
                redirect('index.php?action=broadcast&product_id=' . $pid, 'Product created! Send a new arrival notification to customers.');
            }
            redirect('index.php?action=products', 'Product created.');
        }
    } elseif ($actionPost === 'product_bulk_save') {
        $ids = $_POST['ids'] ?? [];
        $names = $_POST['name_en'] ?? [];
        $prices = $_POST['price'] ?? [];
        $discounts = $_POST['discount_price'] ?? [];
        $qtys = $_POST['quantity'] ?? [];
        $brands = $_POST['brand'] ?? [];
        $statuses = $_POST['status'] ?? [];
        $featured = $_POST['featured'] ?? [];
        $arrivals = $_POST['new_arrival'] ?? [];
        foreach ($ids as $i => $pid) {
            $pid = (int)$pid;
            if (!$pid) continue;
            $nameEn = trim($names[$i] ?? '');
            $slug = slugify($nameEn);
            $price = (float)($prices[$i] ?? 0);
            $discount = $discounts[$i] !== '' ? (float)$discounts[$i] : null;
            $qty = (int)($qtys[$i] ?? 0);
            $brand = trim($brands[$i] ?? '');
            $status = $statuses[$i] ?? 'active';
            $feat = in_array($pid, $featured) ? 1 : 0;
            $arr = in_array($pid, $arrivals) ? 1 : 0;
            $db->prepare("UPDATE products SET name_en=?, slug=?, price=?, discount_price=?, quantity=?, brand=?, featured=?, new_arrival=?, status=? WHERE id=?")
                ->execute([$nameEn, $slug, $price, $discount, $qty, $brand, $feat, $arr, $status, $pid]);
        }
        redirect('index.php?action=products', 'Bulk update saved.');
    } elseif ($actionPost === 'product_multi_save') {
        ob_clean();
        header('Content-Type: application/json');
        if (!isAdmin()) {
            echo json_encode(['ok' => false, 'message' => 'Session expired. Please refresh the page and login again.']);
            exit;
        }
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            echo json_encode(['ok' => false, 'message' => 'Invalid session token. Please refresh the page and try again.']);
            exit;
        }
        try {
        $items = json_decode($_POST['products_json'] ?? '[]', true);
        $force = !empty($_POST['force']);
        $total = 0; $dupCount = 0; $dupNames = [];

        // First pass: detect duplicates
        $tempFiles = [];
        foreach ($items as $i => $p) {
            $nameEn = trim($p['name'] ?? '');
            if (!$nameEn) continue;
            $total++;
            $fileKey = 'product_image_' . $i;
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['tmp_name']) {
                $hash = md5_file($_FILES[$fileKey]['tmp_name']);
                $tempFiles[$i] = $hash;
                if (!$force) {
                    $dupStmt = $db->prepare("SELECT COUNT(*) FROM product_images pi JOIN products p2 ON pi.product_id = p2.id WHERE pi.image_hash = ? AND p2.deleted_at IS NULL");
                    $dupStmt->execute([$hash]);
                    if ($dupStmt->fetchColumn() > 0) {
                        $dupCount++;
                        $dupNames[] = $nameEn;
                    }
                }
            }
        }

        if (!$force && $dupCount > 0) {
            echo json_encode([
                'ok' => false,
                'duplicates' => $dupCount,
                'total' => $total,
                'message' => "$dupCount duplicate image(s) found among $total product(s): " . implode(', ', array_slice($dupNames, 0, 5)) . (count($dupNames) > 5 ? '...' : '') . '. Save anyway?',
                'ask_confirm' => true,
            ]);
            exit;
        }

        // Second pass: save
        $created = 0; $duplicates = 0;
        foreach ($items as $i => $p) {
            $imageData = null; $imageHash = null; $isDuplicate = false;
            $fileKey = 'product_image_' . $i;
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['tmp_name']) {
                $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed)) {
                    $targetDir = __DIR__ . '/../uploads/products/';
                    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                    $filename = uniqid() . '.' . $ext;
                    $tmpPath = $_FILES[$fileKey]['tmp_name'];
                    $imageHash = md5_file($tmpPath);
                    $dupStmt = $db->prepare("SELECT pi.product_id FROM product_images pi JOIN products p2 ON pi.product_id = p2.id WHERE pi.image_hash = ? AND p2.deleted_at IS NULL LIMIT 1");
                    $dupStmt->execute([$imageHash]);
                    $isDuplicate = $dupStmt->fetch() ? true : false;
                    if (move_uploaded_file($tmpPath, $targetDir . $filename)) {
                        $imageData = 'uploads/products/' . $filename;
                    }
                }
            }
            $nameEn = trim($p['name'] ?? '');
            if (!$nameEn) continue;
            $slug = slugify($nameEn);
            $baseSlug = $slug;
            $slugCounter = 1;
            $checkSlug = $db->prepare("SELECT id FROM products WHERE slug = ? AND deleted_at IS NULL");
            $checkSlug->execute([$slug]);
            while ($checkSlug->fetchColumn()) {
                $slug = $baseSlug . '-' . $slugCounter++;
                $checkSlug->execute([$slug]);
            }
            $sku = strtoupper(substr($nameEn, 0, 3)) . '-' . time() . '-' . $i;
            $status = $p['status'] ?? 'draft';
            $stmt = $db->prepare("INSERT INTO products (category_id, name_en, name_sw, slug, price, discount_price, quantity, brand, description_en, description_sw, featured, new_arrival, status, sizes, colors, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                !empty($p['cat']) ? (int)$p['cat'] : null,
                $nameEn,
                trim($p['name_sw'] ?? ''),
                $slug,
                (float)($p['price'] ?? 0),
                $p['discount'] !== '' ? (float)$p['discount'] : null,
                (int)($p['qty'] ?? 0),
                trim($p['brand'] ?? ''),
                trim($p['desc_en'] ?? ''),
                trim($p['desc_sw'] ?? ''),
                !empty($p['featured']) ? 1 : 0,
                !empty($p['arrival']) ? 1 : 0,
                $status,
                !empty($p['sizes']) ? json_encode($p['sizes']) : null,
                !empty($p['colors']) ? json_encode($p['colors']) : null,
                $sku,
            ]);
            $pid = $db->lastInsertId();
            if ($imageData) $db->prepare("INSERT INTO product_images (product_id, image_path, image_hash, is_primary) VALUES (?, ?, ?, 1)")->execute([$pid, $imageData, $imageHash]);
            $created++;
            if ($isDuplicate) $duplicates++;
        }
        $msg = "$created products created.";
        if ($duplicates) $msg .= " ($duplicates duplicate images flagged with Dup badge).";
        echo json_encode(['ok' => true, 'message' => $msg]);
        exit;
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
            exit;
        }
    } elseif ($actionPost === 'product_delete') {
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        redirect('index.php?action=products', 'Product deleted.');
    } elseif ($actionPost === 'product_multi_delete') {
        $ids = array_filter(explode(',', $_POST['ids'] ?? ''), fn($v) => $v > 0);
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("DELETE FROM products WHERE id IN ($placeholders)")->execute(array_values($ids));
        }
        redirect('index.php?action=products', count($ids) . ' product(s) deleted.');
    }
    // Categories
    elseif ($actionPost === 'category_save') {
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameSw = trim($_POST['name_sw'] ?? '');
        $slug = slugify($nameEn);
        $editId = (int)($_POST['id'] ?? 0);
        if ($editId) {
            $db->prepare("UPDATE categories SET name_en=?, name_sw=?, slug=? WHERE id=?")->execute([$nameEn, $nameSw, $slug, $editId]);
        } else {
            $db->prepare("INSERT INTO categories (name_en, name_sw, slug) VALUES (?, ?, ?)")->execute([$nameEn, $nameSw, $slug]);
        }
        redirect('index.php?action=categories', 'Category saved.');
    } elseif ($actionPost === 'category_delete') {
        $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        redirect('index.php?action=categories', 'Category deleted.');
    }
    // Orders
    elseif ($actionPost === 'order_status') {
        $status = $_POST['status'] ?? '';
        $db->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $id]);
        $description = 'Status updated to ' . $status;
        $db->prepare("INSERT INTO order_trackings (order_id, status, description) VALUES (?, ?, ?)")->execute([$id, $status, $description]);
        if ($status === 'delivered') {
            $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$id]);
        }
        require_once __DIR__ . '/../includes/notifications.php';
        notifyOrderUpdate($id, $status, $description);
        redirect('index.php?action=orders', 'Order status updated.');
    } elseif ($actionPost === 'order_delete') {
        $twoMonthsAgo = date('Y-m-d H:i:s', strtotime('-2 months'));
        $check = $db->prepare("SELECT created_at FROM orders WHERE id = ?");
        $check->execute([$id]); $order = $check->fetch();
        if ($order && $order['created_at'] <= $twoMonthsAgo) {
            $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM order_trackings WHERE order_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM payment_transactions WHERE order_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
            redirect('index.php?action=orders', 'Order deleted.');
        } else {
            redirect('index.php?action=orders', 'Order cannot be deleted yet (2 month minimum).');
        }
    }
    elseif ($actionPost === 'worker_assign') {
        $workerId = !empty($_POST['worker_id']) ? (int)$_POST['worker_id'] : null;
        $orderSt = $db->prepare("SELECT order_number, status FROM orders WHERE id = ?");
        $orderSt->execute([$id]);
        $orderRow = $orderSt->fetch();
        $orderNumber = $orderRow ? $orderRow['order_number'] : '';
        $orderStatus = $orderRow ? $orderRow['status'] : '';
        $db->prepare("UPDATE orders SET worker_id = ? WHERE id = ?")->execute([$workerId, $id]);
        if ($workerId) {
            $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$workerId]);
            $workerName = $stmt->fetchColumn() ?: 'Worker';
            $description = "Assigned to worker: $workerName";
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')")->execute([$workerId, "New Order #$orderNumber", "You have been assigned order #$orderNumber."]);
        } else {
            $description = 'Worker unassigned.';
            if (!empty($orderRow['worker_id'])) {
                $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')")->execute([$orderRow['worker_id'], "Order #$orderNumber Unassigned", "Order #$orderNumber has been unassigned from you."]);
            }
        }
        require_once __DIR__ . '/../includes/notifications.php';
        notifyOrderUpdate($id, $orderStatus, $description);
        redirect('index.php?action=orders&id=' . $id, 'Worker assignment updated.');
    }
    elseif ($actionPost === 'make_worker') {
        $db->prepare("UPDATE users SET role = 'worker' WHERE id = ? AND role = 'customer'")->execute([$id]);
        redirect('index.php?action=customers', 'Customer promoted to worker.');
    }
    elseif ($actionPost === 'create_worker') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$name || !$email || !$password) {
            redirect('index.php?action=workers', 'Name, email and password are required.', 'error');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $db->prepare("INSERT INTO users (name, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, 'worker', NOW())")->execute([$name, $email, $phone ?: null, $hash]);
            redirect('index.php?action=workers', "Worker $name created.");
        } catch (Exception $e) {
            redirect('index.php?action=workers', 'Email already exists.', 'error');
        }
    }
    // Coupons
    elseif ($actionPost === 'coupon_save') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['type'] ?? 'percentage';
        $value = (float)($_POST['value'] ?? 0);
        $minPurchase = !empty($_POST['min_purchase']) ? (float)$_POST['min_purchase'] : null;
        $usageLimit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $editId = (int)($_POST['id'] ?? 0);
        if ($editId) {
            $db->prepare("UPDATE coupons SET code=?, type=?, value=?, min_purchase=?, usage_limit=?, expires_at=? WHERE id=?")->execute([$code, $type, $value, $minPurchase, $usageLimit, $expiresAt, $editId]);
        } else {
            $db->prepare("INSERT INTO coupons (code, type, value, min_purchase, usage_limit, expires_at) VALUES (?, ?, ?, ?, ?, ?)")->execute([$code, $type, $value, $minPurchase, $usageLimit, $expiresAt]);
        }
        redirect('index.php?action=coupons', 'Coupon saved.');
    } elseif ($actionPost === 'coupon_delete') {
        $db->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
        redirect('index.php?action=coupons', 'Coupon deleted.');
    }
    // Settings
    elseif ($actionPost === 'settings_save') {
        foreach ($_POST as $key => $value) {
            if (in_array($key, ['csrf_token', 'admin_action']) || preg_match('/^vd_(min|max|pct)_\d+$/', $key)) continue;
            $stmt = $db->prepare("SELECT id FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            if ($stmt->fetch()) {
                $db->prepare("UPDATE settings SET `value` = ? WHERE `key` = ?")->execute([$value, $key]);
            } else {
                $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)")->execute([$key, $value]);
            }
        }
        redirect('index.php?action=settings', 'Settings saved.');
    }
    // Reviews
    elseif ($actionPost === 'review_approve') {
        $db->prepare("UPDATE product_reviews SET status = 'approved' WHERE id = ?")->execute([$id]);
        redirect('index.php?action=reviews', 'Review approved.');
    } elseif ($actionPost === 'review_reject') {
        $db->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$id]);
        redirect('index.php?action=reviews', 'Review deleted.');
    } elseif ($actionPost === 'mark_read') {
        $db->prepare("UPDATE contacts SET is_read = 1 WHERE id = ?")->execute([$id]);
        redirect('index.php?action=contacts', 'Message marked as read.');
    } elseif ($actionPost === 'contact_delete') {
        $db->prepare("DELETE FROM contacts WHERE id = ?")->execute([$id]);
        redirect('index.php?action=contacts', 'Message deleted.');
    } elseif ($actionPost === 'contact_delete_all') {
        $db->exec("DELETE FROM contacts");
        redirect('index.php?action=contacts', 'All messages deleted.');
    } elseif ($actionPost === 'profile_save') {
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        if (!$name) { redirect('index.php?action=profile', 'Name is required.', 'error'); }
        $db->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name, $_SESSION['user_id']]);
        $_SESSION['user_name'] = $name;
        if ($password) {
            if (strlen($password) < 6) { redirect('index.php?action=profile', 'Password must be at least 6 chars.', 'error'); }
            if ($password !== $confirm) { redirect('index.php?action=profile', 'Passwords do not match.', 'error'); }
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        }
        redirect('index.php?action=profile', 'Profile updated successfully.');
    }
    // Verify settings password
    elseif ($actionPost === 'verify_settings') {
        $password = $_POST['password'] ?? '';
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['settings_verified'] = true;
            sendJson(['ok' => true]);
        } else {
            sendJson(['ok' => false, 'error' => 'Wrong password.'], 403);
        }
    }
    // Broadcast Notification
    elseif ($actionPost === 'broadcast_send') {
        require_once __DIR__ . '/../includes/notifications.php';
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $recipientType = $_POST['recipient_type'] ?? 'all';
        $channels = $_POST['channels'] ?? ['inapp'];
        $productLink = trim($_POST['product_link'] ?? '');
        if (!$title || !$message) { redirect('index.php?action=broadcast', 'Title and message are required.', 'error'); }
        $stats = sendBroadcastNotification($title, $message, $recipientType, $channels, $productLink);
        $_SESSION['broadcast_result'] = $stats;
        redirect('index.php?action=broadcast', 'Broadcast sent successfully.');
    }
    // Themes
    elseif ($actionPost === 'theme_save') {
        $name = trim($_POST['name'] ?? '');
        $slug = slugify($name);
        if (!$name) { redirect('index.php?action=themes', 'Theme name is required.', 'error'); }
        $description = trim($_POST['description'] ?? '');
        $previewColor = trim($_POST['preview_color'] ?? '#FF8C00');
        $editId = (int)($_POST['id'] ?? 0);
        $autoSchedule = isset($_POST['auto_schedule']) ? 1 : 0;
        $scheduledFrom = !empty($_POST['scheduled_from']) ? $_POST['scheduled_from'] : null;
        $scheduledTo = !empty($_POST['scheduled_to']) ? $_POST['scheduled_to'] : null;

        // Build decorations JSON
        $decorations = [
            'enabled' => isset($_POST['decor_enabled']) ? true : false,
            'snowflakes' => isset($_POST['decor_snowflakes']) ? true : false,
            'confetti' => isset($_POST['decor_confetti']) ? true : false,
            'particles' => $_POST['decor_particles'] ?? 'none',
            'particle_count' => (int)($_POST['decor_particle_count'] ?? 50),
            'badge_enabled' => isset($_POST['badge_enabled']) ? true : false,
            'badge_text_en' => trim($_POST['badge_text_en'] ?? ''),
            'badge_text_sw' => trim($_POST['badge_text_sw'] ?? ''),
            'badge_icon' => trim($_POST['badge_icon'] ?? ''),
            'quick_styles' => [
                'bg_color' => trim($_POST['qs_bg_color'] ?? '#ffffff'),
                'text_color' => trim($_POST['qs_text_color'] ?? '#212529'),
                'link_color' => trim($_POST['qs_link_color'] ?? '#0011ff'),
                'heading_color' => trim($_POST['qs_heading_color'] ?? '#1a1a2e'),
                'btn_bg' => trim($_POST['qs_btn_bg'] ?? '#ff8c00'),
                'btn_text' => trim($_POST['qs_btn_text'] ?? '#ffffff'),
                'navbar_bg' => trim($_POST['qs_navbar_bg'] ?? '#ff8c00'),
                'card_bg' => trim($_POST['qs_card_bg'] ?? '#ffffff'),
                'dark_bg_color' => trim($_POST['qs_dark_bg_color'] ?? '#121212'),
                'dark_text_color' => trim($_POST['qs_dark_text_color'] ?? '#f5f0eb'),
                'dark_link_color' => trim($_POST['qs_dark_link_color'] ?? '#ff8c00'),
                'dark_heading_color' => trim($_POST['qs_dark_heading_color'] ?? '#f5f0eb'),
                'dark_btn_bg' => trim($_POST['qs_dark_btn_bg'] ?? '#ff8c00'),
                'dark_btn_text' => trim($_POST['qs_dark_btn_text'] ?? '#ffffff'),
                'dark_navbar_bg' => trim($_POST['qs_dark_navbar_bg'] ?? '#121212'),
                'dark_card_bg' => trim($_POST['qs_dark_card_bg'] ?? '#1e1e1e'),
                'border_radius' => trim($_POST['qs_border_radius'] ?? ''),
                'font_size' => trim($_POST['qs_font_size'] ?? ''),
            ],
        ];
        $decorationsJson = json_encode($decorations);

        if ($editId) {
            $stmt = $db->prepare("UPDATE themes SET name=?, slug=?, description=?, preview_color=?, auto_schedule=?, scheduled_from=?, scheduled_to=?, decorations=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$name, $slug, $description, $previewColor, $autoSchedule, $scheduledFrom, $scheduledTo, $decorationsJson, $editId]);
            redirect('index.php?action=themes', 'Theme updated.');
        } else {
            // Ensure unique slug
            $baseSlug = $slug;
            $counter = 1;
            $check = $db->prepare("SELECT id FROM themes WHERE slug = ?");
            $check->execute([$slug]);
            while ($check->fetchColumn()) {
                $slug = $baseSlug . '-' . $counter++;
                $check->execute([$slug]);
            }
            $stmt = $db->prepare("INSERT INTO themes (name, slug, description, preview_color, auto_schedule, scheduled_from, scheduled_to, decorations, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$name, $slug, $description, $previewColor, $autoSchedule, $scheduledFrom, $scheduledTo, $decorationsJson]);
            redirect('index.php?action=themes', 'Theme created.');
        }
    }
    elseif ($actionPost === 'theme_delete') {
        $stmt = $db->prepare("SELECT is_default FROM themes WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && $row['is_default']) {
            redirect('index.php?action=themes', 'Cannot delete the default theme.', 'error');
        }
        $db->prepare("DELETE FROM themes WHERE id = ?")->execute([$id]);
        redirect('index.php?action=themes', 'Theme deleted.');
    }
    elseif ($actionPost === 'theme_activate') {
        $db->prepare("UPDATE themes SET is_live = 0, is_staging = 0")->execute();
        $db->prepare("UPDATE themes SET is_live = 1 WHERE id = ?")->execute([$id]);
        redirect('index.php?action=themes', 'Theme activated as Live.');
    }
    elseif ($actionPost === 'theme_staging') {
        $db->prepare("UPDATE themes SET is_staging = 0")->execute();
        $db->prepare("UPDATE themes SET is_staging = 1 WHERE id = ?")->execute([$id]);
        redirect('index.php?action=themes', 'Theme set as Staging.');
    }
    elseif ($actionPost === 'theme_default') {
        $db->prepare("UPDATE themes SET is_default = 0")->execute();
        $db->prepare("UPDATE themes SET is_default = 1 WHERE id = ?")->execute([$id]);
        redirect('index.php?action=themes', 'Theme set as Default.');
    }
    elseif ($actionPost === 'theme_duplicate') {
        $stmt = $db->prepare("SELECT * FROM themes WHERE id = ?");
        $stmt->execute([$id]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orig) { redirect('index.php?action=themes', 'Theme not found.', 'error'); }
        $newName = $orig['name'] . ' (Copy)';
        $newSlug = slugify($newName);
        $check = $db->prepare("SELECT id FROM themes WHERE slug = ?");
        $check->execute([$newSlug]);
        $counter = 1;
        while ($check->fetchColumn()) {
            $newSlug = slugify($orig['name'] . ' (Copy ' . $counter++ . ')');
            $check->execute([$newSlug]);
        }
        $stmt = $db->prepare("INSERT INTO themes (name, slug, description, preview_color, css_variables, decorations, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$newName, $newSlug, $orig['description'], $orig['preview_color'], $orig['css_variables'], $orig['decorations']]);
        redirect('index.php?action=themes', 'Theme duplicated.');
    }
    elseif ($actionPost === 'theme_preview') {
        $_SESSION['preview_theme_id'] = $id;
        redirect(SITE_URL . '/index.php?theme_preview=1');
    }
}

require_once __DIR__ . '/includes/header.php';

switch ($action) {
    case 'dashboard': ?>
        <h2 class="fw-600 mb-4">Dashboard</h2>
        <div class="row g-3">
            <?php
            $stats = [
                ['Products', $db->query("SELECT COUNT(*) FROM products")->fetchColumn(), 'box', 'gold'],
                ['Orders', $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(), 'shopping-cart', 'dark'],
                ['Customers', $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(), 'users', 'primary'],
                ['Revenue', formatMoney($db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status='paid'")->fetchColumn()), 'money-bill', 'success'],
            ];
            foreach ($stats as [$label, $val, $icon, $color]): ?>
            <div class="col-md-3">
                <div class="border p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted text-uppercase"><?= $label ?></small>
                        <h4 class="fw-700 mt-1"><?= $val ?></h4>
                    </div>
                    <i class="fas fa-<?= $icon ?> fa-2x text-<?= $color ?> opacity-50"></i>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="row g-3 mt-3">
            <div class="col-md-6">
                <div class="border p-3">
                    <h6 class="fw-600 mb-3">Recent Orders</h6>
                    <?php $orders = $db->query("SELECT o.*, u.email as customer_email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5")->fetchAll(); ?>
                    <?php if ($orders): foreach ($orders as $o): ?>
                    <div class="d-flex justify-content-between border-bottom py-2 small">
                        <span><a href="index.php?action=orders&id=<?= $o['id'] ?>">#<?= escape($o['order_number']) ?></a></span>
                        <span class="badge bg-secondary"><?= ucfirst($o['status']) ?></span>
                        <span><?= formatMoney($o['total']) ?></span>
                    </div>
                    <?php endforeach; else: ?><p class="text-muted small">No orders yet.</p><?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border p-3">
                    <h6 class="fw-600 mb-3">Low Stock Products</h6>
                    <?php $lowStock = $db->query("SELECT * FROM products WHERE quantity < 10 AND status='active' ORDER BY quantity ASC LIMIT 5")->fetchAll(); ?>
                    <?php if ($lowStock): foreach ($lowStock as $p): ?>
                    <div class="d-flex justify-content-between border-bottom py-2 small">
                        <span><?= escape($p['name_en']) ?></span>
                        <span class="text-danger"><?= $p['quantity'] ?> left</span>
                    </div>
                    <?php endforeach; else: ?><p class="text-muted small">All products well stocked.</p><?php endif; ?>
                </div>
            </div>
        </div>
    <?php break;

    case 'products': ?>
        <style>
        .admin-page .size-selector input:checked + .size-option {
            border-color: #FF8C00 !important;
            background: #FFF3E0 !important;
            color: #5a3e00 !important;
            box-shadow: 0 0 0 3px rgba(255,140,0,0.2) !important;
        }
        </style>
        <?php $editProduct = null; $editImage = '';
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ?"); $stmt->execute([$id]); $editProduct = $stmt->fetch();
            if ($editProduct) {
                $imgStmt = $db->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC LIMIT 1");
                $imgStmt->execute([$id]);
                $editImage = $imgStmt->fetchColumn();
            }
        }
        $sort = $_GET['sort'] ?? 'newest';
        $orderMap = ['newest' => 'p.created_at DESC', 'oldest' => 'p.created_at ASC', 'az' => 'p.name_en ASC', 'za' => 'p.name_en DESC'];
        $orderBy = $orderMap[$sort] ?? 'p.created_at DESC';
        $products = $db->query("SELECT p.*, c.name_en as cat_name, pi.image_path as primary_image, pi.image_hash, (SELECT COUNT(*) FROM product_images pi2 JOIN products p2 ON pi2.product_id = p2.id WHERE pi2.image_hash = pi.image_hash AND pi2.image_hash IS NOT NULL AND p2.id != p.id AND p2.deleted_at IS NULL) as dup_count FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 ORDER BY $orderBy")->fetchAll();
        $cats = $db->query("SELECT * FROM categories WHERE status = 1 ORDER BY name_en")->fetchAll();
        $sizesList = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];
        $colorPalette = json_encode(colorPalette());
        $colorSearchIndex = [];
        foreach (colorPalette() as $en => $hex) {
            $colorSearchIndex[mb_strtolower($en)] = $en;
        }
        foreach (colorNames() as $en => $names) {
            $colorSearchIndex[mb_strtolower($names['sw'])] = $en;
        }
    ?>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <h4 class="fw-600 mb-0"><?= __t('products') ?> (<?= count($products) ?>)</h4>
                <select class="form-select form-select-sm" style="width:auto;font-size:12px" onchange="window.location='index.php?action=products&sort='+this.value">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>New → Old</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Old → New</option>
                    <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>A → Z</option>
                    <option value="za" <?= $sort === 'za' ? 'selected' : '' ?>>Z → A</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-gold-sm" onclick="showMultiCreator()"><i class="fas fa-layer-group me-1"></i>+ <?= __t('add_products') ?></button>
                <button class="btn btn-sm btn-outline-dark-custom" onclick="toggleBulkEditor()" id="bulkToggleBtn" style="display:none"><i class="fas fa-edit me-1"></i><?= __t('edit_product') ?> (<span id="bulkCount">0</span>)</button>
                <button class="btn btn-sm btn-outline-info" onclick="broadcastSelected()" id="broadcastBtn" style="display:none"><i class="fas fa-bullhorn me-1"></i>Broadcast</button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteSelected()" id="deleteBtn" style="display:none"><i class="fas fa-trash me-1"></i>Delete</button>
            </div>
        </div>

        <!-- Multi-Product Creator -->
        <div id="multiCreator" class="border p-4 mb-4" style="display:none">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-600 mb-0"><i class="fas fa-images me-2 text-gold"></i><?= __t('add_products') ?> <?= __t('products') ?> from Images</h5>
                <button class="btn btn-sm btn-outline-dark-custom" onclick="document.getElementById('multiCreator').style.display='none'"><?= __t('cancel') ?></button>
            </div>
            <div class="mb-3 p-4 text-center" id="dropZone" style="background:#c8bfa8;border:2px dashed #FF8C00;border-radius:12px;cursor:pointer" onclick="document.getElementById('multiFileInput').click()">
                <i class="fas fa-cloud-upload-alt fa-3x" style="color:#5a3e00;margin-bottom:2px"></i>
                <p class="fw-600 mb-1" style="color:#3a2a00">Click or drop product images here</p>
                <small style="color:#3a2a00">Select multiple images at once — each becomes a product entry</small>
                <input type="file" id="multiFileInput" accept="image/*" multiple style="display:none" onchange="handleMultiFiles(this.files)">
            </div>
            <div id="aiFillAllBar" class="mb-3 p-2 text-center" style="display:none;background:#FFF3E0;border:1px solid #FF8C00;border-radius:8px">
                <button class="btn btn-sm btn-outline-info" onclick="aiFillAllProducts()" id="aiFillAllBtn"><i class="fas fa-magic me-1"></i>Fill All with AI</button>
                <span id="aiFillAllStatus" class="ms-2 small"></span>
            </div>
            <div id="multiProductList"></div>
            <div class="text-end mt-3" id="multiSaveArea" style="display:none">
                <button class="btn btn-gold btn-lg" onclick="saveMultiProducts()"><i class="fas fa-save me-2"></i><?= __t('save') ?> <?= __t('products') ?></button>
            </div>
        </div>

        <?php if ($editProduct): ?>
        <div class="border p-4 mb-4" id="productForm">
            <h5 class="fw-600 mb-3"><?= __t('edit_product') ?></h5>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf() ?><input type="hidden" name="admin_action" value="product_save">
                <?php if ($editProduct): ?><input type="hidden" name="id" value="<?= $editProduct['id'] ?>"><?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label small">Name (EN)</label><input type="text" name="name_en" class="form-control" value="<?= escape($editProduct['name_en'] ?? '') ?>" required></div>
                    <div class="col-md-4"><label class="form-label small">Name (SW)</label><input type="text" name="name_sw" class="form-control" value="<?= escape($editProduct['name_sw'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label small">Category</label>
                        <select name="category_id" class="form-select"><option value="">None</option>
                        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= ($editProduct['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= escape($c['name_en']) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col-md-3"><label class="form-label small">Price</label><input type="number" step="0.01" name="price" class="form-control" value="<?= $editProduct['price'] ?? 0 ?>" required></div>
                    <div class="col-md-3"><label class="form-label small">Discount Price</label><input type="number" step="0.01" name="discount_price" class="form-control" value="<?= $editProduct['discount_price'] ?? '' ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Quantity</label><input type="number" name="quantity" class="form-control" value="<?= $editProduct['quantity'] ?? 0 ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Brand</label><input type="text" name="brand" class="form-control" value="<?= escape($editProduct['brand'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label small">Description (EN)</label><textarea name="description_en" rows="3" class="form-control"><?= escape($editProduct['description_en'] ?? '') ?></textarea></div>
                    <div class="col-md-6"><label class="form-label small">Description (SW)</label><textarea name="description_sw" rows="3" class="form-control"><?= escape($editProduct['description_sw'] ?? '') ?></textarea></div>
                    <div class="col-md-4">
                        <label class="form-label small">Sizes</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php $selectedSizes = $editProduct ? json_decode($editProduct['sizes'] ?? '[]', true) : []; ?>
                            <?php foreach ($sizesList as $s): ?>
                            <label class="size-selector">
                                <input type="checkbox" name="sizes[]" value="<?= $s ?>" <?= in_array($s, $selectedSizes) ? 'checked' : '' ?> class="d-none">
                                <span class="size-option"><?= $s ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Colors <small>(type to search)</small></label>
                        <div class="d-flex flex-wrap gap-1 align-items-center mb-1 edit-color-list" id="editColorList">
                            <?php $selectedColors = $editProduct ? json_decode($editProduct['colors'] ?? '[]', true) : []; $pal = colorPalette(); ?>
                            <?php foreach ($selectedColors as $sc): $hex = $pal[$sc] ?? '#ccc'; ?>
                            <span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded small" style="background:<?= $hex ?>20;border:1px solid <?= $hex ?>;font-size:11px" data-color="<?= $sc ?>">
                                <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?= $hex ?>;border:1px solid #999"></span> <?= $sc ?>
                                <span style="cursor:pointer;color:#999" onclick="editRemoveColor(this)">✕</span>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <div style="position:relative">
                            <input type="text" class="form-control form-control-sm" id="editColorInput" style="width:100%" placeholder="Search color..." oninput="editSearchColors(this)" onblur="setTimeout(function(){var d=document.getElementById('editColorDropdown');if(d)d.style.display='none';},200)" onfocus="editSearchColors(this)">
                            <input type="hidden" name="colors_edit" id="editColorsHidden" value='<?= json_encode($selectedColors) ?>'>
                            <div id="editColorDropdown" style="position:absolute;top:100%;left:0;right:0;z-index:10;background:#fff;border:1px solid #ddd;max-height:150px;overflow-y:auto;display:none"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= ($editProduct['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= __t('active') ?></option>
                            <option value="inactive" <?= ($editProduct['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= __t('inactive') ?></option>
                            <option value="draft" <?= ($editProduct['status'] ?? '') === 'draft' ? 'selected' : '' ?>><?= __t('draft') ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Image</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="file" name="image" class="form-control">
                            <?php if ($editImage): ?>
                            <img src="<?= SITE_URL . '/' . $editImage ?>" style="width:50px;height:50px;object-fit:cover;border-radius:6px;border:2px solid #FF8C00;flex-shrink:0" alt="Current image">
                            <?php else: ?>
                            <span style="width:50px;height:50px;border-radius:6px;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;font-size:10px;color:#999;flex-shrink:0;text-align:center">No<br>image</span>
                            <?php endif; ?>
                        </div>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="featured" class="form-check-input" id="featured" <?= ($editProduct['featured'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="featured"><?= __t('featured') ?></label>
                        </div>
                        <div class="form-check mt-1">
                            <input type="checkbox" name="new_arrival" class="form-check-input" id="new_arrival" <?= ($editProduct['new_arrival'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="new_arrival"><i class="fas fa-star text-warning me-1"></i><?= __t('new_arrival') ?></label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-gold mt-3"><?= __t('save') ?></button>
                <a href="index.php?action=products" class="btn btn-outline-dark-custom mt-3"><?= __t('cancel') ?></a>
            </form>
        </div>
        <?php endif; ?>

        <!-- Bulk Editor -->
        <div id="bulkEditor" class="border p-4 mb-4" style="display:none">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-600 mb-0"><i class="fas fa-edit me-2 text-gold"></i><?= __t('edit_product') ?></h5>
                <div>
                    <button class="btn btn-sm btn-outline-dark-custom me-2" onclick="toggleBulkEditor()"><?= __t('cancel') ?></button>
                    <button class="btn btn-gold-sm" onclick="document.getElementById('bulkForm').submit()"><i class="fas fa-save me-1"></i><?= __t('save') ?></button>
                </div>
            </div>
            <form method="POST" id="bulkForm">
                <?= csrf() ?><input type="hidden" name="admin_action" value="product_bulk_save">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="bulkTable">
                        <thead>
                            <tr>
                                <th style="width:180px"><?= __t('name') ?></th>
                                <th style="width:100px"><?= __t('price') ?></th>
                                <th style="width:100px"><?= __t('discount') ?></th>
                                <th style="width:70px"><?= __t('quantity') ?></th>
                                <th style="width:120px"><?= __t('brand') ?></th>
                                <th style="width:90px"><?= __t('status') ?></th>
                                <th style="width:70px"><?= __t('featured') ?></th>
                                <th style="width:70px"><?= __t('new_arrival') ?></th>
                            </tr>
                        </thead>
                        <tbody id="bulkBody"></tbody>
                    </table>
                </div>
            </form>
        </div>

        <div class="d-flex align-items-center gap-2 mb-2">
            <label class="small text-muted"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"> <?= __t('select_all') ?></label>
        </div>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th style="width:32px"></th><th style="width:55px">Photo</th><th>ID</th><th><?= __t('name') ?></th><th><?= __t('category') ?></th><th><?= __t('price') ?></th><th><?= __t('quantity') ?></th><th><?= __t('status') ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><input type="checkbox" class="product-checkbox" value="<?= $p['id'] ?>" data-name="<?= escape($p['name_en']) ?>" data-price="<?= $p['price'] ?>" data-discount="<?= $p['discount_price'] ?? '' ?>" data-qty="<?= $p['quantity'] ?>" data-brand="<?= escape($p['brand'] ?? '') ?>" data-status="<?= $p['status'] ?>" data-featured="<?= $p['featured'] ?>" data-arrival="<?= $p['new_arrival'] ?>" onchange="updateBulkBtn()"></td>
                    <td><?php if ($p['primary_image']): ?><img src="<?= SITE_URL . '/' . $p['primary_image'] ?>" style="width:45px;height:45px;object-fit:cover;border-radius:4px;border:1px solid #ddd"><?php else: ?><span style="display:inline-block;width:45px;height:45px;border-radius:4px;background:#f0f0f0;border:1px dashed #ccc;vertical-align:middle"></span><?php endif; ?></td>
                    <td><?= $p['id'] ?></td>
                    <td><a href="<?= SITE_URL ?>/shop/index.php?product=<?= escape($p['slug']) ?>" target="_blank"><?= escape($p['name_en']) ?></a></td>
                    <td><small><?= escape($p['cat_name'] ?? '') ?></small></td>
                    <td><?= formatMoney($p['discount_price'] ?: $p['price']) ?></td>
                    <td><?= $p['quantity'] ?></td>
                    <td>
                        <span class="badge bg-<?= $p['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $p['status'] ?></span>
                        <?php if (($p['dup_count'] ?? 0) > 0): ?><span class="badge bg-danger ms-1" title="Duplicate image">Dup</span><?php endif; ?>
                    </td>
                    <td>
                        <a href="index.php?action=products&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-dark-custom" title="Edit"><i class="fas fa-edit"></i></a>
                        <a href="index.php?action=broadcast&product_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" title="Notify Customers"><i class="fas fa-bullhorn"></i></a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')"><?= csrf() ?><input type="hidden" name="admin_action" value="product_delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button type="submit" class="btn btn-sm btn-danger-custom"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <script>
        var COLOR_PALETTE = <?= $colorPalette ?>;
        var COLOR_SEARCH = <?= json_encode($colorSearchIndex) ?>;
        var CATS = <?= json_encode(array_map(function($c){return ['id'=>$c['id'],'name'=>$c['name_en']];}, $cats)) ?>;
        var multiProducts = [];

        function showMultiCreator() {
            document.getElementById('multiCreator').style.display = 'block';
            document.getElementById('multiCreator').scrollIntoView({ behavior: 'smooth' });
        }

        function handleMultiFiles(files) {
            if (!files.length) return;
            var container = document.getElementById('multiProductList');
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var idx = multiProducts.length;
                multiProducts.push({ file: file, data: null, rendered: false });
                var reader = new FileReader();
                reader.idx = idx;
                reader.onload = (function(idx) {
                    return function(e) {
                        multiProducts[idx].data = e.target.result;
                        appendProductRow(container, idx);
                    };
                })(idx);
                reader.readAsDataURL(file);
            }
            document.getElementById('multiSaveArea').style.display = 'block';
            document.getElementById('aiFillAllBar').style.display = 'block';
        }

        function appendProductRow(container, idx) {
            var p = multiProducts[idx];
            if (!p.data || p.rendered) return;
            p.rendered = true;
            var div = document.createElement('div');
            div.className = 'border rounded p-3 mb-3';
            div.setAttribute('data-index', idx);
            div.style.cssText = 'border:2px solid #FF8C00;border-radius:12px;margin-bottom:1rem;background:#c8bfa8;';
            div.innerHTML = '<div class="row g-3 p-3">' +
                '<div class="col-md-3"><img src="' + p.data + '" style="width:100%;max-height:180px;object-fit:cover;border-radius:8px;border:1px solid #e0d5c0"></div>' +
                '<div class="col-md-9"><div class="row g-2">' +
                '<div class="col-6"><label class="small fw-600" style="color:#5a3e00">Name (EN)</label><input type="text" class="form-control form-control-sm mp-name" placeholder="e.g. Red Dress"></div>' +
                '<div class="col-6"><label class="small fw-600" style="color:#5a3e00">Name (SW)</label><input type="text" class="form-control form-control-sm mp-name-sw" placeholder="e.g. Gauni Jekundu"></div>' +
                '<div class="col-3"><label class="small fw-600" style="color:#5a3e00">Price</label><input type="number" step="0.01" class="form-control form-control-sm mp-price" placeholder="TZS"></div>' +
                '<div class="col-3"><label class="small fw-600" style="color:#5a3e00">Discount</label><input type="number" step="0.01" class="form-control form-control-sm mp-discount" placeholder="Optional"></div>' +
                '<div class="col-3"><label class="small fw-600" style="color:#5a3e00">Qty</label><input type="number" class="form-control form-control-sm mp-qty" value="10"></div>' +
                '<div class="col-3"><label class="small fw-600" style="color:#5a3e00">Brand</label><input type="text" class="form-control form-control-sm mp-brand" placeholder="INNOCE"></div>' +
                '<div class="col-6"><label class="small fw-600" style="color:#5a3e00">Description (EN)</label><textarea class="form-control form-control-sm mp-desc-en" rows="2" placeholder="Product description in English"></textarea></div>' +
                '<div class="col-6"><label class="small fw-600" style="color:#5a3e00">Description (SW)</label><textarea class="form-control form-control-sm mp-desc-sw" rows="2" placeholder="Maelezo ya bidhaa kwa Kiswahili"></textarea></div>' +
                '<div class="col-4"><label class="small fw-600" style="color:#5a3e00">Category</label><select class="form-select form-select-sm mp-cat"><option value="">None</option>' + CATS.map(function(c){return '<option value="'+c.id+'">'+c.name+'</option>';}).join('') + '</select></div>' +
                '<div class="col-4"><label class="small fw-600" style="color:#5a3e00">Status</label><select class="form-select form-select-sm mp-status"><option value="draft" selected>Draft</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>' +
                '<div class="col-4"><label class="small fw-600" style="color:#5a3e00">&nbsp;</label><div class="d-flex gap-2 pt-1"><div class="form-check"><input type="checkbox" class="form-check-input mp-featured"><label class="form-check-label small fw-600" style="color:#5a3e00">Featured</label></div><div class="form-check"><input type="checkbox" class="form-check-input mp-arrival"><label class="form-check-label small fw-600" style="color:#5a3e00">New</label></div></div></div>' +
                '<div class="col-md-6"><label class="small fw-600" style="color:#5a3e00">Sizes</label><div class="d-flex flex-wrap gap-1">' + ['XS','S','M','L','XL','XXL','3XL'].map(function(s){return '<label class="size-selector"><input type="checkbox" class="d-none mp-size" value="'+s+'"><span class="size-option" style="padding:2px 8px;font-size:11px;background:#fff;border:2px solid #e0e0e0">'+s+'</span></label>';}).join('') + '</div></div>' +
                '<div class="col-md-6"><label class="small fw-600" style="color:#5a3e00">Colors <small>(type to search)</small></label><div class="d-flex flex-wrap gap-1 align-items-center"><div class="mp-colors-list d-flex flex-wrap gap-1"></div><div style="position:relative"><input type="text" class="form-control form-control-sm mp-color-input" style="width:160px" placeholder="Search color..." oninput="searchColors(this, ' + idx + ')" onblur="setTimeout(function(){var d=this.parentNode.querySelector(\'.mp-color-dropdown\');if(d)d.style.display=\'none\';}.bind(this),200)" onfocus="searchColors(this, ' + idx + ')"><div class="mp-color-dropdown" style="position:absolute;top:100%;left:0;right:0;z-index:10;background:#fff;border:1px solid #ddd;max-height:150px;overflow-y:auto;display:none"></div></div></div></div>' +
                '</div></div>' +
                '<div class="row px-3 pb-3"><div class="col-12 text-end"><button class="btn btn-gold-sm btn-sm" onclick="saveSingleProduct(' + idx + ')"><i class="fas fa-save me-1"></i>Save</button></div></div></div>';
            container.appendChild(div);
        }

        function renderMultiProducts() {
            var container = document.getElementById('multiProductList');
            container.innerHTML = '';
            multiProducts.forEach(function(p, idx) {
                if (!p.data) return;
                var div = document.createElement('div');
                div.className = 'border rounded p-3 mb-3';
                div.setAttribute('data-index', idx);
                div.style.cssText = 'border:2px solid #FF8C00;border-radius:12px;margin-bottom:1rem;background:#c8bfa8;';
                div.innerHTML = '<div class="row g-3 p-3">' +
                    '<div class="col-md-3"><img src="' + p.data + '" style="width:100%;max-height:180px;object-fit:cover;border-radius:8px;border:1px solid #e0d5c0"></div>' +
                    '<div class="col-md-9"><div class="row g-2">' +
                    '<div class="col-6"><label class="small fw-600" style="color:#5a3e00">Name (EN)</label><input type="text" class="form-control form-control-sm mp-name" placeholder="e.g. Red Dress" value=""></div>' +
                    '<div class="col-6"><label class="small fw-600" style="color:#5a3e00">Name (SW)</label><input type="text" class="form-control form-control-sm mp-name-sw" placeholder="e.g. Gauni Jekundu"></div>' +
                    '<div class="col-3"><label class="small fw-600" style="color:#5a3e00">Price</label><input type="number" step="0.01" class="form-control form-control-sm mp-price" placeholder="TZS"></div>' +
                    '<div class="col-3"><label class="small fw-600" style="color:#5a3e00">Discount</label><input type="number" step="0.01" class="form-control form-control-sm mp-discount" placeholder="Optional"></div>' +
                    '<div class="col-3"><label class="small fw-600" style="color:#5a3e00">Qty</label><input type="number" class="form-control form-control-sm mp-qty" value="10"></div>' +
                    '<div class="col-3"><label class="small fw-600" style="color:#5a3e00">Brand</label><input type="text" class="form-control form-control-sm mp-brand" placeholder="INNOCE"></div>' +
                    '<div class="col-4"><label class="small fw-600" style="color:#5a3e00">Category</label><select class="form-select form-select-sm mp-cat"><option value="">None</option>' + CATS.map(function(c){return '<option value="'+c.id+'">'+c.name+'</option>';}).join('') + '</select></div>' +
                    '<div class="col-4"><label class="small fw-600" style="color:#5a3e00">Status</label><select class="form-select form-select-sm mp-status"><option value="draft" selected>Draft</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>' +
                    '<div class="col-4"><label class="small fw-600" style="color:#5a3e00">&nbsp;</label><div class="d-flex gap-2 pt-1"><div class="form-check"><input type="checkbox" class="form-check-input mp-featured"><label class="form-check-label small fw-600" style="color:#5a3e00">Featured</label></div><div class="form-check"><input type="checkbox" class="form-check-input mp-arrival"><label class="form-check-label small fw-600" style="color:#5a3e00">New</label></div></div></div>' +
                    '<div class="col-12"><label class="small fw-600" style="color:#5a3e00">Sizes</label><div class="d-flex flex-wrap gap-1">' + ['XS','S','M','L','XL','XXL','3XL'].map(function(s){return '<label class="size-selector"><input type="checkbox" class="d-none mp-size" value="'+s+'"><span class="size-option" style="padding:2px 8px;font-size:11px;background:#fff;border:2px solid #e0e0e0">'+s+'</span></label>';}).join('') + '</div></div>' +
                    '<div class="col-12"><label class="small fw-600" style="color:#5a3e00">Colors <small>(type to search)</small></label><div class="d-flex flex-wrap gap-1 align-items-center"><div class="mp-colors-list d-flex flex-wrap gap-1"></div><div style="position:relative"><input type="text" class="form-control form-control-sm mp-color-input" style="width:160px" placeholder="Search color..." oninput="searchColors(this, ' + idx + ')" onblur="setTimeout(function(){var d=this.parentNode.querySelector(\'.mp-color-dropdown\');if(d)d.style.display=\'none\';}.bind(this),200)" onfocus="searchColors(this, ' + idx + ')"><div class="mp-color-dropdown" style="position:absolute;top:100%;left:0;right:0;z-index:10;background:#fff;border:1px solid #ddd;max-height:150px;overflow-y:auto;display:none"></div></div></div></div>' +
                    '</div></div>' +
                '<div class="row px-3 pb-3"><div class="col-12 text-end"><button class="btn btn-sm btn-outline-info me-2" onclick="aiFillMultiProduct(' + idx + ')" id="aiFillBtn_' + idx + '"><i class="fas fa-magic me-1"></i>Fill with AI</button><span id="aiFillStatus_' + idx + '" class="me-2 small"></span><button class="btn btn-gold-sm btn-sm" onclick="saveSingleProduct(' + idx + ')"><i class="fas fa-save me-1"></i>Save</button></div></div></div>';
                container.appendChild(div);
            });
        }

        function searchColors(input, idx) {
            var q = input.value.toLowerCase();
            var dropdown = input.parentNode.querySelector('.mp-color-dropdown');
            if (!q) { dropdown.style.display = 'none'; return; }
            var matches = searchColorKeys(q);
            if (!matches.length) { dropdown.style.display = 'none'; return; }
            dropdown.innerHTML = '';
            dropdown.style.display = 'block';
            matches.forEach(function(c) {
                var hex = COLOR_PALETTE[c];
                var item = document.createElement('div');
                item.className = 'd-flex align-items-center gap-2 px-2 py-1';
                item.style.cursor = 'pointer';
                item.onmouseenter = function(){ this.style.background = '#f0f0f0'; };
                item.onmouseleave = function(){ this.style.background = ''; };
                item.onmousedown = function(e){ e.preventDefault(); addColorToProduct(idx, c); };
                item.innerHTML = '<span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:' + hex + ';border:1px solid #ccc"></span> <span class="small fw-600" style="color:#1a1a1a">' + c + '</span>';
                dropdown.appendChild(item);
            });
        }

        function addColorToProduct(idx, color) {
            var container = getProductContainer(idx);
            var list = container.querySelector('.mp-colors-list');
            if (list.querySelector('[data-color="' + color + '"]')) return;
            var badge = document.createElement('span');
            badge.className = 'd-inline-flex align-items-center gap-1 px-2 py-1 rounded small';
            badge.style.cssText = 'background:' + COLOR_PALETTE[color] + '20;border:1px solid ' + COLOR_PALETTE[color] + ';font-size:11px';
            badge.dataset.color = color;
            badge.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:' + COLOR_PALETTE[color] + ';border:1px solid #999"></span> ' + color + ' <span style="cursor:pointer;color:#999" onclick="this.parentElement.remove()">✕</span>';
            list.appendChild(badge);
            var input = container.querySelector('.mp-color-input');
            input.value = '';
            var dd = input.parentNode.querySelector('.mp-color-dropdown');
            if (dd) dd.style.display = 'none';
        }

        function saveMultiProducts() {
            var formData = new FormData();
            formData.append('admin_action', 'product_multi_save');
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

            var products = [];
            var container = document.getElementById('multiProductList');
            var rows = container.querySelectorAll('.row.g-3');

            rows.forEach(function(row) {
                var pIdx = parseInt(row.parentElement.getAttribute('data-index'));
                var name = row.querySelector('.mp-name').value.trim();
                if (!name) return;
                var colors = [];
                row.querySelectorAll('.mp-colors-list [data-color]').forEach(function(el){ colors.push(el.dataset.color); });
                var sizes = [];
                row.querySelectorAll('.mp-size:checked').forEach(function(el){ sizes.push(el.value); });
                var p = {
                    name: name,
                    name_sw: row.querySelector('.mp-name-sw').value.trim(),
                    price: row.querySelector('.mp-price').value,
                    discount: row.querySelector('.mp-discount').value,
                    qty: row.querySelector('.mp-qty').value,
                    brand: row.querySelector('.mp-brand').value.trim(),
                    desc_en: row.querySelector('.mp-desc-en').value.trim(),
                    desc_sw: row.querySelector('.mp-desc-sw').value.trim(),
                    cat: row.querySelector('.mp-cat').value,
                    status: row.querySelector('.mp-status').value,
                    featured: row.querySelector('.mp-featured').checked ? 1 : 0,
                    arrival: row.querySelector('.mp-arrival').checked ? 1 : 0,
                    sizes: sizes,
                    colors: colors,
                };
                products.push(p);
                if (multiProducts[pIdx] && multiProducts[pIdx].file) {
                    formData.append('product_image_' + pIdx, multiProducts[pIdx].file, multiProducts[pIdx].file.name);
                }
            });

            formData.append('products_json', JSON.stringify(products));

            var btn = document.querySelector('#multiSaveArea .btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';

            fetch('index.php', { method: 'POST', body: formData })
                .then(function(r) {
                    return r.text().then(function(text) {
                        try { return JSON.parse(text); }
                        catch(e) {
                            throw new Error('Server returned non-JSON. Status: ' + r.status + '\nResponse: ' + text.substring(0, 300));
                        }
                    });
                })
                .then(function(data) {
                    if (data.ok) {
                        document.getElementById('multiProductList').innerHTML = '';
                        document.getElementById('multiSaveArea').style.display = 'none';
                        document.getElementById('aiFillAllBar').style.display = 'none';
                        multiProducts = [];
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-save me-2"></i>Save All Products';
                    } else if (data.ask_confirm) {
                        if (confirm(data.message)) {
                            formData.append('force', '1');
                            btn.disabled = true;
                            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
                            fetch('index.php', { method: 'POST', body: formData })
                                .then(function(r2) {
                                    return r2.text().then(function(text) {
                                        try { return JSON.parse(text); }
                                        catch(e) { throw new Error('Server returned non-JSON. ' + text.substring(0, 300)); }
                                    });
                                })
                                .then(function(data2) {
                                    if (data2.ok) {
                                        document.getElementById('multiProductList').innerHTML = '';
                                        document.getElementById('multiSaveArea').style.display = 'none';
                                        document.getElementById('aiFillAllBar').style.display = 'none';
                                        multiProducts = [];
                                    } else {
                                        alert('Error: ' + (data2.message || 'Unknown error'));
                                    }
                                    btn.disabled = false;
                                    btn.innerHTML = '<i class="fas fa-save me-2"></i>Save All Products';
                                })
                                .catch(function(err2) {
                                    alert('Save failed:\n' + err2.message);
                                    btn.disabled = false;
                                    btn.innerHTML = '<i class="fas fa-save me-2"></i>Save All Products';
                                });
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-save me-2"></i>Save All Products';
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-save me-2"></i>Save All Products';
                    }
                })
                .catch(function(err) {
                    alert('Save failed:\n' + (err.message || 'Unknown error'));
                    console.error(err);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save me-2"></i>Save All Products';
                });
        }

        function getProductContainer(idx) {
            return document.querySelector('#multiProductList [data-index="' + idx + '"]');
        }

        function saveSingleProduct(idx) {
            var container = getProductContainer(idx);
            var row = container.querySelector('.row.g-3');
            var name = row.querySelector('.mp-name').value.trim();
            if (!name) { alert('Name is required'); return; }
            var colors = [];
            row.querySelectorAll('.mp-colors-list [data-color]').forEach(function(el){ colors.push(el.dataset.color); });
            var sizes = [];
            row.querySelectorAll('.mp-size:checked').forEach(function(el){ sizes.push(el.value); });
            var p = {
                name: name,
                name_sw: row.querySelector('.mp-name-sw').value.trim(),
                price: row.querySelector('.mp-price').value,
                discount: row.querySelector('.mp-discount').value,
                qty: row.querySelector('.mp-qty').value,
                brand: row.querySelector('.mp-brand').value.trim(),
                desc_en: row.querySelector('.mp-desc-en').value.trim(),
                desc_sw: row.querySelector('.mp-desc-sw').value.trim(),
                cat: row.querySelector('.mp-cat').value,
                status: row.querySelector('.mp-status').value,
                featured: row.querySelector('.mp-featured').checked ? 1 : 0,
                arrival: row.querySelector('.mp-arrival').checked ? 1 : 0,
                sizes: sizes,
                colors: colors,
            };
            var formData = new FormData();
            formData.append('admin_action', 'product_multi_save');
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
            formData.append('products_json', JSON.stringify([p]));
            if (multiProducts[idx] && multiProducts[idx].file) {
                formData.append('product_image_0', multiProducts[idx].file, multiProducts[idx].file.name);
            }
            var btn = container.querySelector('.btn-gold-sm');
            var origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            fetch('index.php', { method: 'POST', body: formData })
                .then(function(r) {
                    return r.text().then(function(text) {
                        try { return JSON.parse(text); }
                        catch(e) {
                            throw new Error('Server returned non-JSON. Status: ' + r.status + '\nResponse: ' + text.substring(0, 300));
                        }
                    });
                })
                .then(function(data) {
                    if (data.ok) {
                        container.remove();
                        multiProducts[idx] = null;
                    } else if (data.ask_confirm) {
                        if (confirm(data.message)) {
                            formData.append('force', '1');
                            btn.disabled = true;
                            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                            fetch('index.php', { method: 'POST', body: formData })
                                .then(function(r2) {
                                    return r2.text().then(function(text) {
                                        try { return JSON.parse(text); }
                                        catch(e) { throw new Error('Server returned non-JSON. ' + text.substring(0, 300)); }
                                    });
                                })
                                .then(function(data2) {
                                    if (data2.ok) {
                                        container.remove();
                                        multiProducts[idx] = null;
                                    } else {
                                        alert('Error: ' + (data2.message || 'Unknown error'));
                                    }
                                    btn.disabled = false;
                                    btn.innerHTML = origHtml;
                                })
                                .catch(function(err2) {
                                    alert('Save failed:\n' + err2.message);
                                    btn.disabled = false;
                                    btn.innerHTML = origHtml;
                                });
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = origHtml;
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                        btn.disabled = false;
                        btn.innerHTML = origHtml;
                    }
                })
                .catch(function(err) {
                    alert('Save failed:\n' + (err.message || 'Unknown error'));
                    console.error(err);
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                });
        }

        function searchColorKeys(q) {
            q = q.toLowerCase();
            var seen = {}, results = [];
            Object.keys(COLOR_SEARCH).forEach(function(name){
                if (name.indexOf(q) !== -1) {
                    var key = COLOR_SEARCH[name];
                    if (!seen[key]) { seen[key] = true; results.push(key); }
                }
            });
            return results;
        }
        function editSearchColors(input) {
            var q = input.value.toLowerCase();
            var dropdown = document.getElementById('editColorDropdown');
            if (!q) { dropdown.style.display = 'none'; return; }
            var matches = searchColorKeys(q);
            if (!matches.length) { dropdown.style.display = 'none'; return; }
            dropdown.innerHTML = '';
            dropdown.style.display = 'block';
            matches.forEach(function(c) {
                var hex = COLOR_PALETTE[c];
                var item = document.createElement('div');
                item.className = 'd-flex align-items-center gap-2 px-2 py-1';
                item.style.cursor = 'pointer';
                item.onmouseenter = function(){ this.style.background = '#f0f0f0'; };
                item.onmouseleave = function(){ this.style.background = ''; };
                item.onmousedown = function(e){ e.preventDefault(); editAddColor(c); };
                item.innerHTML = '<span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:' + hex + ';border:1px solid #ccc"></span> <span class="small fw-600" style="color:#1a1a1a">' + c + '</span>';
                dropdown.appendChild(item);
            });
        }
        function editAddColor(color) {
            var list = document.getElementById('editColorList');
            if (list.querySelector('[data-color="' + color + '"]')) return;
            var hex = COLOR_PALETTE[color];
            var badge = document.createElement('span');
            badge.className = 'd-inline-flex align-items-center gap-1 px-2 py-1 rounded small';
            badge.style.cssText = 'background:' + hex + '20;border:1px solid ' + hex + ';font-size:11px';
            badge.dataset.color = color;
            badge.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:' + hex + ';border:1px solid #999"></span> ' + color + ' <span style="cursor:pointer;color:#999" onclick="editRemoveColor(this)">✕</span>';
            list.appendChild(badge);
            editUpdateHidden();
            document.getElementById('editColorInput').value = '';
            document.getElementById('editColorDropdown').style.display = 'none';
        }
        function editRemoveColor(el) {
            el.parentElement.remove();
            editUpdateHidden();
        }
        function editUpdateHidden() {
            var colors = [];
            document.querySelectorAll('#editColorList [data-color]').forEach(function(el){ colors.push(el.dataset.color); });
            document.getElementById('editColorsHidden').value = JSON.stringify(colors);
        }
        function toggleSelectAll(el) {
            document.querySelectorAll('.product-checkbox').forEach(function(c) { c.checked = el.checked; });
            updateBulkBtn();
        }
        function updateBulkBtn() {
            var checked = document.querySelectorAll('.product-checkbox:checked');
            var count = checked.length;
            document.getElementById('bulkToggleBtn').style.display = count > 0 ? 'inline-block' : 'none';
            document.getElementById('broadcastBtn').style.display = count > 0 ? 'inline-block' : 'none';
            document.getElementById('deleteBtn').style.display = count > 0 ? 'inline-block' : 'none';
            document.getElementById('bulkCount').textContent = count;
        }
        function toggleBulkEditor() {
            var editor = document.getElementById('bulkEditor');
            if (editor.style.display !== 'none') {
                editor.style.display = 'none';
                return;
            }
            var checked = document.querySelectorAll('.product-checkbox:checked');
            if (checked.length === 0) return;
            var tbody = document.getElementById('bulkBody');
            tbody.innerHTML = '';
            checked.forEach(function(cb) {
                var id = cb.value;
                var name = cb.dataset.name;
                var price = cb.dataset.price;
                var discount = cb.dataset.discount;
                var qty = cb.dataset.qty;
                var brand = cb.dataset.brand;
                var status = cb.dataset.status;
                var featured = cb.dataset.featured === '1';
                var arrival = cb.dataset.arrival === '1';
                var tr = document.createElement('tr');
                tr.innerHTML = '<td><input type="hidden" name="ids[]" value="' + id + '"><input type="text" name="name_en[]" class="form-control form-control-sm" value="' + name + '"></td>' +
                    '<td><input type="number" step="0.01" name="price[]" class="form-control form-control-sm" value="' + price + '" required></td>' +
                    '<td><input type="number" step="0.01" name="discount_price[]" class="form-control form-control-sm" value="' + discount + '" placeholder="None"></td>' +
                    '<td><input type="number" name="quantity[]" class="form-control form-control-sm" value="' + qty + '"></td>' +
                    '<td><input type="text" name="brand[]" class="form-control form-control-sm" value="' + brand + '"></td>' +
                    '<td><select name="status[]" class="form-select form-select-sm"><option value="active"' + (status === 'active' ? ' selected' : '') + '>Active</option><option value="inactive"' + (status === 'inactive' ? ' selected' : '') + '>Inactive</option><option value="draft"' + (status === 'draft' ? ' selected' : '') + '>Draft</option></select></td>' +
                    '<td class="text-center"><input type="checkbox" name="featured[]" value="' + id + '"' + (featured ? ' checked' : '') + '></td>' +
                    '<td class="text-center"><input type="checkbox" name="new_arrival[]" value="' + id + '"' + (arrival ? ' checked' : '') + '></td>';
                tbody.appendChild(tr);
            });
            editor.style.display = 'block';
            editor.scrollIntoView({ behavior: 'smooth' });
        }
        function fillRowFromAI(idx, data) {
            var container = getProductContainer(idx);
            var row = container.querySelector('.row.g-3');
            if (data.name_en) row.querySelector('.mp-name').value = data.name_en;
            if (data.name_sw) row.querySelector('.mp-name-sw').value = data.name_sw;
            if (data.category_id) row.querySelector('.mp-cat').value = data.category_id;
            if (data.price) row.querySelector('.mp-price').value = data.price;
            if (data.brand) row.querySelector('.mp-brand').value = data.brand;
            if (data.description_en) row.querySelector('.mp-desc-en').value = data.description_en;
            if (data.description_sw) row.querySelector('.mp-desc-sw').value = data.description_sw;
            if (data.sizes && data.sizes.length) {
                row.querySelectorAll('.mp-size').forEach(function(cb) {
                    cb.checked = data.sizes.indexOf(cb.value) !== -1;
                });
            }
            if (data.colors && data.colors.length) {
                var list = row.querySelector('.mp-colors-list');
                list.innerHTML = '';
                data.colors.forEach(function(c) {
                    var hex = COLOR_PALETTE[c] || '#ccc';
                    var span = document.createElement('span');
                    span.className = 'd-inline-flex align-items-center gap-1 px-2 py-1 rounded small';
                    span.style.cssText = 'background:' + hex + '20;border:1px solid ' + hex + ';font-size:11px';
                    span.dataset.color = c;
                    span.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:' + hex + ';border:1px solid #999"></span> ' + c + ' <span style="cursor:pointer;color:#999" onclick="this.parentElement.remove()">✕</span>';
                    list.appendChild(span);
                });
            }
        }

        function aiFillMultiProduct(idx) {
            var p = multiProducts[idx];
            if (!p || !p.data) { alert('No image data for this product.'); return; }
            var btn = document.getElementById('aiFillBtn_' + idx);
            var status = document.getElementById('aiFillStatus_' + idx);
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>';
            status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin"></i></span>';
            fetch('<?= SITE_URL ?>/includes/ajax/ai_fill_product.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'image=' + encodeURIComponent(p.data)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    var errMsg = data.error;
                    if (data.details && data.details.length) errMsg += ' (' + data.details.join('; ') + ')';
                    status.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>' + errMsg + '</span>';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-magic me-1"></i>Fill with AI';
                    return;
                }
                fillRowFromAI(idx, data);
                status.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i></span>';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic me-1"></i>Fill with AI';
            })
            .catch(function(err) {
                status.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Error</span>';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic me-1"></i>Fill with AI';
            });
        }

        function aiFillAllProducts() {
            var btn = document.getElementById('aiFillAllBtn');
            var status = document.getElementById('aiFillAllStatus');
            var total = multiProducts.length;
            var done = 0;
            var errors = 0;
            var firstError = '';
            btn.disabled = true;
            status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i>Filling 0/' + total + '...</span>';
            function fillNext(i) {
                if (i >= total) {
                    var msg = 'Done! ' + done + '/' + total + ' filled' + (errors ? ', ' + errors + ' errors' : '');
                    if (firstError) msg += ' First error: ' + firstError;
                    status.innerHTML = '<span class="' + (errors ? 'text-warning' : 'text-success') + '"><i class="fas fa-check-circle me-1"></i>' + msg + '</span>';
                    btn.disabled = false;
                    return;
                }
                var p = multiProducts[i];
                if (!p || !p.data) { fillNext(i + 1); return; }
                fetch('<?= SITE_URL ?>/includes/ajax/ai_fill_product.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'image=' + encodeURIComponent(p.data)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.error) {
                        fillRowFromAI(i, data);
                        done++;
                    } else {
                        var errMsg = data.error;
                        if (data.details && data.details.length) errMsg += ' (' + data.details.join('; ') + ')';
                        if (!firstError) firstError = errMsg;
                        errors++;
                    }
                    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i>Filling ' + (done + errors) + '/' + total + '...</span>';
                    setTimeout(function() { fillNext(i + 1); }, 3000);
                })
                .catch(function(err) {
                    if (!firstError) firstError = err.message || 'Network error';
                    errors++;
                    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i>Filling ' + (done + errors) + '/' + total + '...</span>';
                    setTimeout(function() { fillNext(i + 1); }, 3000);
                });
            }
            fillNext(0);
        }

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.product-checkbox:checked')).map(function(cb){ return cb.value; });
        }

        function deleteSelected() {
            var ids = getSelectedIds();
            if (!ids.length) return;
            if (!confirm('Delete ' + ids.length + ' selected product(s)?')) return;
            var formData = new FormData();
            formData.append('admin_action', 'product_multi_delete');
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
            formData.append('ids', ids.join(','));
            var btn = document.getElementById('deleteBtn');
            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...';
            fetch('index.php', { method: 'POST', body: formData })
                .then(function(){ window.location.href = 'index.php?action=products'; })
                .catch(function(){ window.location.href = 'index.php?action=products'; });
        }

        function broadcastSelected() {
            var ids = getSelectedIds();
            if (!ids.length) return;
            window.location.href = 'index.php?action=broadcast&product_ids=' + ids.join(',');
        }
        </script>
    <?php break;

    case 'categories':
        $editCat = null;
        if ($id) { $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?"); $stmt->execute([$id]); $editCat = $stmt->fetch(); }
        $cats = $db->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as pc FROM categories c ORDER BY c.name_en")->fetchAll();
    ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-600 mb-0">Categories</h4>
            <button class="btn-gold-sm" onclick="showForm('cat', 'categories')">+ Add Category</button>
        </div>
        <?php if ($editCat || isset($_GET['show_form'])): ?>
        <div class="border p-4 mb-4" id="catForm">
            <h5 class="fw-600 mb-3"><?= $editCat ? 'Edit Category' : 'New Category' ?></h5>
            <form method="POST">
                <?= csrf() ?><input type="hidden" name="admin_action" value="category_save">
                <?php if ($editCat): ?><input type="hidden" name="id" value="<?= $editCat['id'] ?>"><?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-4"><input type="text" name="name_en" class="form-control" placeholder="Name (EN)" value="<?= escape($editCat['name_en'] ?? '') ?>" required></div>
                    <div class="col-md-4"><input type="text" name="name_sw" class="form-control" placeholder="Name (SW)" value="<?= escape($editCat['name_sw'] ?? '') ?>" required></div>
                </div>
                <button type="submit" class="btn btn-gold mt-3">Save</button>
                <a href="index.php?action=categories" class="btn btn-outline-dark-custom mt-3">Cancel</a>
            </form>
        </div>
        <?php endif; ?>
        <table class="table table-sm">
            <thead><tr><th>Name</th><th>Slug</th><th>Products</th><th></th></tr></thead>
            <tbody><?php foreach ($cats as $c): ?>
            <tr><td><?= escape($c['name_en']) ?></td><td><small><?= $c['slug'] ?></small></td><td><?= $c['pc'] ?></td>
                <td>
                    <a href="index.php?action=categories&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-dark-custom"><i class="fas fa-edit"></i></a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')"><?= csrf() ?><input type="hidden" name="admin_action" value="category_delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button type="submit" class="btn btn-sm btn-danger-custom"><i class="fas fa-trash"></i></button></form>
                </td>
            </tr><?php endforeach; ?></tbody>
        </table>
    <?php break;

    case 'orders':
        if ($id) {
            $stmt = $db->prepare("SELECT o.*, u.email as customer_email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
            $stmt->execute([$id]); $order = $stmt->fetch();
            $items = $db->prepare("SELECT oi.*, p.name_en FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
            $items->execute([$id]); $items = $items->fetchAll();
            $tracking = $db->prepare("SELECT * FROM order_trackings WHERE order_id = ? ORDER BY created_at DESC");
            $tracking->execute([$id]); $tracking = $tracking->fetchAll();
            $address = json_decode($order['shipping_address'], true);
            ?>
            <h4 class="fw-600 mb-3">Order #<?= escape($order['order_number']) ?></h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="border p-3">
                        <p><strong>Customer:</strong> <?= escape($order['customer_name'] ?: $order['customer_email'] ?: 'User #' . $order['user_id']) ?></p>
                        <p><strong>Email:</strong> <?= escape($order['customer_email'] ?? '—') ?></p>
                        <p><strong>Phone:</strong> <?= escape($order['customer_phone'] ?? '—') ?></p>
                        <p><strong>Total:</strong> <?= formatMoney($order['total']) ?></p>
                        <?php if ($order['volume_discount'] > 0): ?>
                        <p><strong>Volume Discount:</strong> -<?= formatMoney($order['volume_discount']) ?></p>
                        <?php endif; ?>
                        <p><strong>Status:</strong> <span class="badge bg-<?= $order['status'] === 'delivered' ? 'success' : 'secondary' ?>"><?= ucfirst($order['status']) ?></span></p>
                        <p><strong>Payment:</strong> <?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?> - <span class="text-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= $order['payment_status'] ?></span></p>
                        <p><strong>Placed:</strong> <?= date('M d, Y H:i', strtotime($order['created_at'])) ?></p>
                        <p><strong>Delivery:</strong> <?= ($order['delivery_method'] ?? 'delivery') === 'pickup' ? 'Pick up at shop' : escape(($address['street'] ?? '') . ', ' . ($address['city'] ?? '') . ', ' . ($address['region'] ?? '')) ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border p-3">
                        <h6 class="fw-600">Update Status</h6>
                        <form method="POST" class="d-flex gap-2"><?= csrf() ?><input type="hidden" name="admin_action" value="order_status">
                            <input type="hidden" name="id" value="<?= $order['id'] ?>">
                            <select name="status" class="form-select"><?php foreach (['pending','confirmed','processing','packed','shipped','delivered','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
                            <button type="submit" class="btn-gold-sm">Update</button>
                        </form>
                        <hr>
                        <h6 class="fw-600">Assign Worker</h6>
                        <form method="POST" class="d-flex gap-2"><?= csrf() ?><input type="hidden" name="admin_action" value="worker_assign">
                            <input type="hidden" name="id" value="<?= $order['id'] ?>">
                            <select name="worker_id" class="form-select" style="max-width:220px;">
                                <option value="">— None —</option>
                                <?php $workers = $db->query("SELECT id, name FROM users WHERE role='worker' ORDER BY name")->fetchAll(); ?>
                                <?php foreach ($workers as $w):
                                    $locW = $db->prepare("SELECT * FROM worker_locations WHERE worker_id = ?");
                                    $locW->execute([$w['id']]);
                                    $locWData = $locW->fetch();
                                    $isOnlineW = $locWData && strtotime($locWData['updated_at']) > time() - 120;
                                    $distFromShop = $locWData ? distanceKm(SHOP_LAT, SHOP_LNG, $locWData['latitude'], $locWData['longitude']) : null;
                                    $label = escape($w['name']);
                                    if ($locWData) {
                                        $label .= ' [' . ($isOnlineW ? 'ON' : 'OFF') . ']';
                                        if ($distFromShop !== null) {
                                            $label .= ' ' . ($distFromShop < 1 ? number_format($distFromShop * 1000, 0) . 'm' : number_format($distFromShop, 1) . 'km') . ' fr shop';
                                        }
                                    } else {
                                        $label .= ' [no GPS]';
                                    }
                                ?>
                                <option value="<?= $w['id'] ?>" <?= $order['worker_id'] == $w['id'] ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-gold-sm">Assign</button>
                        </form>
                        <?php $twoMonthsAgo = date('Y-m-d H:i:s', strtotime('-2 months')); ?>
                        <?php if ($order['created_at'] <= $twoMonthsAgo): ?>
                        <hr>
                        <form method="POST" onsubmit="return confirm('Permanently delete this order?')"><?= csrf() ?>
                            <input type="hidden" name="admin_action" value="order_delete">
                            <input type="hidden" name="id" value="<?= $order['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger-custom"><i class="fas fa-trash me-1"></i>Delete Order</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12">
                    <div class="border p-3">
                        <h6 class="fw-600">Items</h6>
                        <?php foreach ($items as $item): ?>
                        <div class="d-flex justify-content-between border-bottom py-1 small"><span><?= escape($item['name_en']) ?> x <?= $item['quantity'] ?></span><span><?= formatMoney($item['total']) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <a href="index.php?action=orders" class="btn btn-outline-dark-custom btn-sm mt-3">&larr; Back</a>
        <?php } else {
            $orders = $db->query("SELECT o.*, u.email as customer_email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC")->fetchAll(); ?>
            <h4 class="fw-600 mb-3">Orders (<?= count($orders) ?>)</h4>
            <table class="table table-sm">
                <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Worker</th><th>Date</th><th></th></tr></thead>
                <tbody><?php foreach ($orders as $o): ?>
                <?php $wn = null; if ($o['worker_id']) { $ws = $db->prepare("SELECT name FROM users WHERE id=?"); $ws->execute([$o['worker_id']]); $wn = $ws->fetchColumn(); } ?>
                <tr><td>#<?= escape($o['order_number']) ?></td><td><small><?= escape($o['customer_name'] ?: $o['customer_email'] ?: 'User #' . $o['user_id']) ?><br><?= escape($o['customer_phone']) ?></small></td><td><?= formatMoney($o['total']) ?></td><td><span class="small text-<?= $o['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= $o['payment_status'] ?></span></td><td><span class="badge bg-secondary"><?= ucfirst($o['status']) ?></span></td><td><small><?= $wn ? escape($wn) : '—' ?></small></td><td><small><?= date('M d, H:i', strtotime($o['created_at'])) ?></small></td><td><a href="index.php?action=orders&id=<?= $o['id'] ?>" class="btn btn-sm btn-dark-custom">View</a></td></tr>
                <?php endforeach; ?></tbody>
            </table>
        <?php } break;

    case 'coupons':
        $editCoupon = null;
        if ($id) { $stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?"); $stmt->execute([$id]); $editCoupon = $stmt->fetch(); }
        $coupons = $db->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();
    ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-600 mb-0">Coupons</h4>
            <button class="btn-gold-sm" onclick="showForm('coupon')">+ Add Coupon</button>
        </div>
        <?php if ($editCoupon || isset($_GET['show_form'])): ?>
        <div class="border p-4 mb-4" id="couponForm">
            <form method="POST"><?= csrf() ?><input type="hidden" name="admin_action" value="coupon_save">
                <?php if ($editCoupon): ?><input type="hidden" name="id" value="<?= $editCoupon['id'] ?>"><?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-3"><input type="text" name="code" class="form-control" placeholder="CODE" value="<?= escape($editCoupon['code'] ?? '') ?>" required></div>
                    <div class="col-md-2"><select name="type" class="form-select"><option value="percentage" <?= ($editCoupon['type'] ?? '') === 'percentage' ? 'selected' : '' ?>>%</option><option value="fixed" <?= ($editCoupon['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed</option></select></div>
                    <div class="col-md-2"><input type="number" step="0.01" name="value" class="form-control" placeholder="Value" value="<?= $editCoupon['value'] ?? 0 ?>" required></div>
                    <div class="col-md-2"><input type="number" step="0.01" name="min_purchase" class="form-control" placeholder="Min purchase" value="<?= $editCoupon['min_purchase'] ?? '' ?>"></div>
                    <div class="col-md-3"><input type="date" name="expires_at" class="form-control" value="<?= $editCoupon['expires_at'] ?? '' ?>"></div>
                </div>
                <button type="submit" class="btn btn-gold mt-3">Save</button>
                <a href="index.php?action=coupons" class="btn btn-outline-dark-custom mt-3">Cancel</a>
            </form>
        </div>
        <?php endif; ?>
        <table class="table table-sm"><thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Used</th><th>Expires</th><th></th></tr></thead>
        <tbody><?php foreach ($coupons as $c): ?>
        <tr><td><strong><?= escape($c['code']) ?></strong></td><td><?= $c['type'] ?></td><td><?= $c['type'] === 'percentage' ? $c['value'] . '%' : formatMoney($c['value']) ?></td><td><?= $c['used_count'] ?>/<?= $c['usage_limit'] ?: '&infin;' ?></td><td><small><?= $c['expires_at'] ? date('M d', strtotime($c['expires_at'])) : '-' ?></small></td>
        <td><a href="index.php?action=coupons&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-dark-custom"><i class="fas fa-edit"></i></a>
        <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')"><?= csrf() ?><input type="hidden" name="admin_action" value="coupon_delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button type="submit" class="btn btn-sm btn-danger-custom"><i class="fas fa-trash"></i></button></form></td></tr>
        <?php endforeach; ?></tbody></table>
    <?php break;

    case 'reviews':
        $reviews = $db->query("SELECT pr.*, u.name as user_name, p.name_en as product_name FROM product_reviews pr JOIN users u ON pr.user_id = u.id JOIN products p ON pr.product_id = p.id ORDER BY pr.created_at DESC")->fetchAll(); ?>
        <h4 class="fw-600 mb-3">Reviews (<?= count($reviews) ?>)</h4>
        <table class="table table-sm"><thead><tr><th>Product</th><th>User</th><th>Rating</th><th>Review</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach ($reviews as $r): ?>
        <tr><td><small><?= escape($r['product_name']) ?></small></td><td><small><?= escape($r['user_name']) ?></small></td><td><?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star<?= $i <= $r['rating'] ? ' text-gold' : ' text-muted' ?>" style="font-size:10px"></i><?php endfor; ?></td><td><small><?= escape(substr($r['review'], 0, 50)) ?></small></td><td><span class="badge bg-<?= $r['status'] === 'approved' ? 'success' : 'warning' ?>"><?= $r['status'] === 'approved' ? 'Approved' : ucfirst($r['status']) ?></span></td>
        <td>
            <?php if ($r['status'] !== 'approved'): ?>
            <form method="POST" class="d-inline"><?= csrf() ?><input type="hidden" name="admin_action" value="review_approve"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button></form>
            <?php endif; ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')"><?= csrf() ?><input type="hidden" name="admin_action" value="review_reject"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button></form>
        </td></tr>
        <?php endforeach; ?></tbody></table>
    <?php break;

    case 'contacts':
        $messages = $db->query("SELECT * FROM contacts ORDER BY created_at DESC")->fetchAll(); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-600 mb-0">Messages (<?= count($messages) ?>)</h4>
            <?php if ($messages): ?>
            <form method="POST" onsubmit="return confirm('Delete all messages?')"><?= csrf() ?><input type="hidden" name="admin_action" value="contact_delete_all"><button type="submit" class="btn btn-sm btn-danger-custom"><i class="fas fa-trash me-1"></i>Delete All</button></form>
            <?php endif; ?>
        </div>
        <table class="table table-sm"><thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Date</th><th>Read</th><th></th></tr></thead>
        <tbody><?php foreach ($messages as $m): ?>
        <tr><td><?= escape($m['name']) ?></td><td><small><?= escape($m['email']) ?></small></td><td><small><?= escape($m['subject'] ?: '-') ?></small></td><td><small><?= date('M d', strtotime($m['created_at'])) ?></small></td><td><span class="badge bg-<?= ($m['is_read'] ?? 0) ? 'success' : 'warning' ?>"><?= ($m['is_read'] ?? 0) ? 'Read' : 'New' ?></span></td>
        <td>
            <button class="btn btn-sm btn-outline-dark-custom" onclick="alert('From: <?= escape($m['name']) ?>\nEmail: <?= escape($m['email']) ?>\nMessage: <?= escape($m['message']) ?>')">View</button>
            <?php if (!($m['is_read'] ?? 0)): ?>
            <form method="POST" class="d-inline"><?= csrf() ?><input type="hidden" name="admin_action" value="mark_read"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button></form>
            <?php endif; ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this message?')"><?= csrf() ?><input type="hidden" name="admin_action" value="contact_delete"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button type="submit" class="btn btn-sm btn-danger-custom"><i class="fas fa-times"></i></button></form>
        </td></tr>
        <?php endforeach; ?></tbody></table>
    <?php break;

    case 'customers':
        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $stmt = $db->prepare("SELECT *, (last_activity >= NOW() - INTERVAL 5 MINUTE) as is_online FROM users WHERE role='customer' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?) ORDER BY created_at DESC");
            $like = "%$search%";
            $stmt->execute([$like, $like, $like]);
        } else {
            $stmt = $db->query("SELECT *, (last_activity >= NOW() - INTERVAL 5 MINUTE) as is_online FROM users WHERE role='customer' ORDER BY created_at DESC");
        }
        $customers = $stmt->fetchAll();
        $onlineCount = count(array_filter($customers, fn($c) => $c['is_online']));
        $totalCount = count($customers);
        $emailCount = count(array_filter($customers, fn($c) => $c['notify_email']));
        $smsCount = count(array_filter($customers, fn($c) => $c['notify_sms']));
        $inappCount = count(array_filter($customers, fn($c) => $c['notify_inapp']));
    ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-600 mb-0">Customers (<?= $totalCount ?>)</h4>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="action" value="customers">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, email, phone..." value="<?= escape($search) ?>" style="width:250px;">
                <button type="submit" class="btn btn-sm btn-gold"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                <a href="index.php?action=customers" class="btn btn-sm btn-outline-dark-custom"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="row g-2 mb-4">
            <div class="col-md-3">
                <div class="border p-3 d-flex align-items-center gap-3">
                    <i class="fas fa-envelope fa-lg text-primary opacity-75"></i>
                    <div><small class="text-muted text-uppercase">Email</small><h6 class="fw-700 mb-0"><?= $emailCount ?>/<?= $totalCount ?></h6></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 d-flex align-items-center gap-3">
                    <i class="fas fa-comment-dots fa-lg text-success opacity-75"></i>
                    <div><small class="text-muted text-uppercase">SMS</small><h6 class="fw-700 mb-0"><?= $smsCount ?>/<?= $totalCount ?></h6></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 d-flex align-items-center gap-3">
                    <i class="fas fa-bell fa-lg text-warning opacity-75"></i>
                    <div><small class="text-muted text-uppercase">In-App</small><h6 class="fw-700 mb-0"><?= $inappCount ?>/<?= $totalCount ?></h6></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 d-flex align-items-center gap-3">
                    <i class="fas fa-globe fa-lg text-info opacity-75"></i>
                    <div><small class="text-muted text-uppercase">Online Now</small><h6 class="fw-700 mb-0"><?= $onlineCount ?>/<?= $totalCount ?></h6></div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th style="width:130px;">Notifications</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($customers): foreach ($customers as $i => $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="customer-avatar rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:13px;font-weight:600;flex-shrink:0;">
                                    <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                </div>
                                <strong><?= escape($c['name']) ?></strong>
                            </div>
                        </td>
                        <td><small><?= escape($c['email']) ?></small></td>
                        <td><small><?= escape($c['phone'] ?: '-') ?></small></td>
                        <td>
                            <div class="d-flex gap-2 align-items-center">
                                <span title="Email" class="notif-badge <?= $c['notify_email'] ? 'on' : 'off' ?>"><i class="fas fa-envelope"></i></span>
                                <span title="SMS" class="notif-badge <?= $c['notify_sms'] ? 'on' : 'off' ?>"><i class="fas fa-comment-dots"></i></span>
                                <span title="In-App" class="notif-badge <?= $c['notify_inapp'] ? 'on' : 'off' ?>"><i class="fas fa-bell"></i></span>
                            </div>
                        </td>
                        <td><small><?= date('M d, Y', strtotime($c['created_at'])) ?></small></td>
                        <td>
                            <span class="status-dot <?= $c['is_online'] ? 'online' : 'offline' ?>"></span>
                            <small class="<?= $c['is_online'] ? 'text-success fw-600' : 'text-muted' ?>"><?= $c['is_online'] ? __t('online') : __t('offline') ?></small>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('<?= __t('make_worker_confirm') ?>')"><?= csrf() ?>
                                <input type="hidden" name="admin_action" value="make_worker">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="<?= __t('make_worker') ?>"><i class="fas fa-user-cog me-1"></i><?= __t('worker') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No customers found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php break;

    case 'workers':
        $workers = $db->query("SELECT id, name, email, phone, created_at FROM users WHERE role='worker' ORDER BY name")->fetchAll();
    ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-600 mb-0"><?= __t('workers') ?> (<?= count($workers) ?>)</h4>
            <button class="btn-gold-sm" onclick="document.getElementById('createWorkerForm').style.display='block'"><i class="fas fa-plus me-1"></i><?= __t('create_worker') ?></button>
        </div>
        <div class="border p-3 mb-4" id="createWorkerForm" style="display:none;">
            <h6 class="fw-600 mb-3"><i class="fas fa-user-plus me-2 text-gold"></i><?= __t('new_worker') ?></h6>
            <form method="POST" class="row g-2">
                <?= csrf() ?><input type="hidden" name="admin_action" value="create_worker">
                <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="<?= __t('full_name') ?>" required></div>
                <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="<?= __t('email_label') ?>" required></div>
                <div class="col-md-2"><input type="text" name="phone" class="form-control" placeholder="<?= __t('phone_optional') ?>"></div>
                <div class="col-md-2"><input type="password" name="password" class="form-control" placeholder="<?= __t('password') ?>" required></div>
                <div class="col-md-2"><button type="submit" class="btn btn-gold w-100"><i class="fas fa-save me-1"></i><?= __t('create') ?></button></div>
            </form>
        </div>
        <?php if ($workers): ?>
        <div class="row g-3">
            <?php foreach ($workers as $w):
                $locSt = $db->prepare("SELECT * FROM worker_locations WHERE worker_id = ?");
                $locSt->execute([$w['id']]);
                $loc = $locSt->fetch();
                $isOnline = $loc && strtotime($loc['updated_at']) > time() - 120;
                $assigned = $db->prepare("SELECT o.*, u.name as customer_name, u.phone as customer_phone FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.worker_id = ? AND o.status != 'delivered' ORDER BY o.created_at DESC");
                $assigned->execute([$w['id']]);
                $assignedOrders = $assigned->fetchAll();
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="border p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-700 mb-0"><i class="fas fa-user-cog me-2 text-gold"></i><?= escape($w['name']) ?></h6>
                        <div class="text-end">
                            <?php if ($loc): ?>
                            <span class="badge bg-<?= $isOnline ? 'success' : 'secondary' ?>"><?= $isOnline ? 'Live' : 'Offline' ?></span>
                            <?php endif; ?>
                            <small class="text-muted d-block">#<?= $w['id'] ?></small>
                        </div>
                    </div>
                    <p class="small text-muted mb-2">
                        <i class="fas fa-envelope me-1"></i><?= escape($w['email']) ?><br>
                        <i class="fas fa-phone me-1"></i><?= escape($w['phone'] ?: '-') ?>
                        <?php if ($loc):
                            $shopDist = distanceKm(SHOP_LAT, SHOP_LNG, $loc['latitude'], $loc['longitude']);
                        ?>
                        <br><i class="fas fa-map-pin me-1"></i><?= number_format($loc['latitude'], 4) ?>, <?= number_format($loc['longitude'], 4) ?>
                        <br><i class="fas fa-store me-1"></i><?= $shopDist < 1 ? number_format($shopDist * 1000, 0) . ' m' : number_format($shopDist, 1) . ' km' ?> from shop
                        <small class="text-muted d-block">(<?= date('M d, H:i', strtotime($loc['updated_at'])) ?>)</small>
                        <?php endif; ?>
                    </p>
                    <?php if ($assignedOrders): ?>
                    <hr>
                    <h6 class="fw-600 small text-uppercase text-muted mb-2">Active Assignments (<?= count($assignedOrders) ?>)</h6>
                    <?php foreach ($assignedOrders as $o): ?>
                    <div class="border-start border-3 border-gold ps-2 mb-2 small">
                        <div class="fw-600">#<?= escape($o['order_number']) ?> — <?= escape($o['customer_name'] ?: 'User #' . $o['user_id']) ?></div>
                        <div><i class="fas fa-phone me-1"></i><?= escape($o['customer_phone'] ?: $o['phone'] ?: '-') ?></div>
                        <div>
                            <span class="badge bg-secondary"><?= ucfirst($o['status']) ?></span>
                            <?php if ($loc && $o['delivery_method'] === 'delivery' && $o['latitude'] && $o['longitude']): ?>
                                <?php $wDist = distanceKm($loc['latitude'], $loc['longitude'], $o['latitude'], $o['longitude']); ?>
                                <span class="text-<?= $wDist < 1 ? 'success' : ($wDist < 5 ? 'warning' : 'danger') ?> fw-600"><i class="fas fa-road ms-1"></i> <?= $wDist < 1 ? number_format($wDist * 1000, 0) . ' m' : number_format($wDist, 1) . ' km' ?></span>
                                <a href="https://www.google.com/maps/dir/?api=1&origin=<?= $loc['latitude'] ?>,<?= $loc['longitude'] ?>&destination=<?= $o['latitude'] ?>,<?= $o['longitude'] ?>" target="_blank" class="btn btn-sm btn-outline-success mt-1"><i class="fas fa-route me-1"></i>Track</a>
                            <?php elseif ($o['delivery_method'] === 'delivery' && $o['latitude'] && $o['longitude']): ?>
                                <span class="text-muted"><i class="fas fa-hourglass ms-1"></i> Waiting for worker location...</span>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $o['latitude'] ?>,<?= $o['longitude'] ?>" target="_blank" class="btn btn-sm btn-outline-success mt-1"><i class="fas fa-route me-1"></i>Track</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p class="text-muted small mb-0">No active assignments.</p>
                    <?php endif; ?>
                    <?php if (!$loc): ?>
                    <hr>
                    <p class="text-muted small mb-0"><i class="fas fa-store me-1"></i>Shop → ? km <span class="text-muted">(no GPS)</span></p>
                    <p class="text-muted small mb-0"><i class="fas fa-map-marker-alt me-1"></i>Worker needs to open their orders page.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-user-cog fa-3x mb-3"></i>
            <p>No workers found. Assign a user role to 'worker' to see them here.</p>
        </div>
        <?php endif; ?>
    <?php break;

    case 'reports':
        $daily = $db->query("SELECT DATE(created_at) as date, COUNT(*) as count, COALESCE(SUM(total),0) as revenue FROM orders WHERE payment_status='paid' GROUP BY DATE(created_at) ORDER BY date DESC LIMIT 7")->fetchAll();
        $monthly = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(total),0) as revenue FROM orders WHERE payment_status='paid' GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll();
        ?>
        <h4 class="fw-600 mb-3">Reports</h4>
        <div class="row g-3">
            <div class="col-md-6"><div class="border p-3"><h6 class="fw-600">Daily Sales (7 days)</h6>
                <table class="table table-sm"><thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
                <tbody><?php foreach ($daily as $d): ?><tr><td><?= $d['date'] ?></td><td><?= $d['count'] ?></td><td><?= formatMoney($d['revenue']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
            <div class="col-md-6"><div class="border p-3"><h6 class="fw-600">Monthly Sales</h6>
                <table class="table table-sm"><thead><tr><th>Month</th><th>Orders</th><th>Revenue</th></tr></thead>
                <tbody><?php foreach ($monthly as $d): ?><tr><td><?= $d['month'] ?></td><td><?= $d['count'] ?></td><td><?= formatMoney($d['revenue']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        </div>
    <?php break;

    case 'notifications':
        require_once __DIR__ . '/../includes/notifications.php';
        $tab = $_GET['tab'] ?? 'received';
        $notifs = getNotifications($_SESSION['user_id'], 50);
        $broadcasts = getSentBroadcasts(50);
    ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-600 mb-0">Notifications</h4>
            <?php if ($tab === 'received' && $notifs): ?>
            <a href="<?= SITE_URL ?>/actions/notifications.php?action=mark_all_read" class="btn btn-sm btn-outline-dark-custom" onclick="event.preventDefault();fetch(this.href).then(function(){location.reload()});"><i class="fas fa-check-double me-1"></i>Mark All Read</a>
            <?php endif; ?>
        </div>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'received' ? 'active fw-600' : '' ?>" href="index.php?action=notifications&tab=received"><i class="fas fa-inbox me-1"></i>Received</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'sent' ? 'active fw-600' : '' ?>" href="index.php?action=notifications&tab=sent"><i class="fas fa-paper-plane me-1"></i>Sent Broadcasts</a>
            </li>
        </ul>

        <?php if ($tab === 'received'): ?>
            <?php if ($notifs): foreach ($notifs as $n): ?>
            <div class="border p-3 mb-2 d-flex justify-content-between align-items-center <?= $n['is_read'] ? '' : 'border-warning border-2' ?>" id="notif-<?= $n['id'] ?>">
                <div class="d-flex align-items-start gap-3">
                    <i class="fas <?= $n['type'] === 'order' ? 'fa-truck' : ($n['type'] === 'broadcast' ? 'fa-bullhorn' : 'fa-info-circle') ?> mt-1 text-gold"></i>
                    <div>
                        <strong><?= escape($n['title']) ?></strong>
                        <p class="small text-muted mb-0"><?= escape($n['message']) ?></p>
                        <small class="text-muted"><?= date('M d, H:i', strtotime($n['created_at'])) ?></small>
                    </div>
                </div>
                <div class="d-flex gap-1">
                    <?php if (!$n['is_read']): ?>
                    <a href="<?= SITE_URL ?>/actions/notifications.php?action=mark_read&id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-success" onclick="event.preventDefault();fetch(this.href).then(function(){location.reload()});" title="Mark read"><i class="fas fa-check"></i></a>
                    <?php endif; ?>
                    <a href="<?= SITE_URL ?>/actions/notifications.php?action=delete&id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="event.preventDefault();var p=this.parentElement.parentElement;fetch(this.href).then(function(){p.remove()});" title="Delete"><i class="fas fa-trash"></i></a>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                <p>No received notifications.</p>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($broadcasts): foreach ($broadcasts as $b): ?>
            <div class="border p-3 mb-2" id="broadcast-<?= md5($b['notif_ids']) ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-start gap-3">
                        <i class="fas fa-bullhorn mt-1 text-gold"></i>
                        <div>
                            <strong><?= escape($b['title']) ?></strong>
                            <p class="small text-muted mb-0"><?= escape($b['message']) ?></p>
                            <small class="text-muted">
                                <i class="fas fa-users me-1"></i><?= $b['recipient_count'] ?> recipients
                                &middot; <?= date('M d, Y H:i', strtotime($b['last_sent'])) ?>
                            </small>
                        </div>
                    </div>
                    <a href="<?= SITE_URL ?>/actions/notifications.php?action=delete_broadcast&ids=<?= urlencode($b['notif_ids']) ?>" class="btn btn-sm btn-outline-danger" onclick="event.preventDefault();if(!confirm('Delete this broadcast for all recipients?'))return;var p=this.parentElement.parentElement;fetch(this.href).then(function(){p.remove()});" title="Delete broadcast"><i class="fas fa-trash"></i></a>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-paper-plane fa-3x mb-3 opacity-25"></i>
                <p>No broadcast notifications sent yet.</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php break;

    case 'broadcast':
        require_once __DIR__ . '/../includes/notifications.php';
        $product = null; $productList = [];
        $prefillTitle = '';
        $prefillMessage = '';
        $prefillLink = '';
        if (isset($_GET['product_id'])) {
            $pid = (int)$_GET['product_id'];
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $product = $stmt->fetch();
            if ($product) {
                $prefillTitle = "New Arrival: {$product['name_en']}";
                $prefillMessage = "We are excited to announce a new arrival — {$product['name_en']}!" . ($product['name_sw'] ? " / Tunatangaza kwa furaha bidhaa mpya — {$product['name_sw']}!" : '') . " Check it out now!";
                $prefillLink = SITE_URL . '/shop/index.php?product=' . $product['slug'];
            }
        } elseif (isset($_GET['product_ids'])) {
            $pids = array_filter(array_map('intval', explode(',', $_GET['product_ids'])));
            if ($pids) {
                $placeholders = implode(',', array_fill(0, count($pids), '?'));
                $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
                $stmt->execute(array_values($pids));
                $productList = $stmt->fetchAll();
                if ($productList) {
                    $names = array_column($productList, 'name_en');
                    $prefillTitle = 'New Arrivals';
                    $prefillMessage = "We are excited to announce new arrivals: " . implode(', ', $names) . "! Check them out now!";
                    if (count($pids) === 1) {
                        $prefillLink = SITE_URL . '/shop/index.php?product=' . $productList[0]['slug'];
                    } else {
                        $prefillLink = SITE_URL . '/shop/new-arrivals.php';
                    }
                }
            }
        }
    ?>
        <h4 class="fw-600 mb-3"><i class="fas fa-bullhorn me-2"></i>Broadcast Notification</h4>
        <?php if ($product): ?>
        <div class="alert alert-info">Preparing notification for product: <strong><?= escape($product['name_en']) ?></strong></div>
        <?php elseif ($productList): ?>
        <div class="alert alert-info">Preparing notification for <strong><?= count($productList) ?> products</strong>: <?= escape(implode(', ', array_column($productList, 'name_en'))) ?></div>
        <?php endif; ?>
        <div class="border p-4" style="max-width: 700px;">
            <form method="POST">
                <?= csrf() ?><input type="hidden" name="admin_action" value="broadcast_send">
                <div class="mb-3">
                    <label class="form-label small fw-600">Title</label>
                    <input type="text" name="title" class="form-control" value="<?= escape($prefillTitle) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-600">Message</label>
                    <textarea name="message" rows="5" class="form-control" required><?= escape($prefillMessage) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-600">Product Link <small class="text-muted">(optional)</small></label>
                    <input type="url" name="product_link" class="form-control" value="<?= escape($prefillLink) ?>" placeholder="https://...">
                    <small class="text-muted">Will be appended to the notification message</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-600">Recipients</label>
                    <select name="recipient_type" class="form-select">
                        <option value="all">All Users</option>
                        <option value="customers" selected>All Customers</option>
                        <option value="opted_in">Only Opted-In Customers</option>
                        <option value="admins">Admins Only</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-600">Channels</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input type="checkbox" name="channels[]" value="inapp" class="form-check-input" id="ch_inapp" checked>
                            <label class="form-check-label" for="ch_inapp">In-App</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="channels[]" value="email" class="form-check-input" id="ch_email" checked>
                            <label class="form-check-label" for="ch_email">Email</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="channels[]" value="sms" class="form-check-input" id="ch_sms">
                            <label class="form-check-label" for="ch_sms">SMS</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-gold"><i class="fas fa-paper-plane me-2"></i>Send Notification</button>
                <a href="index.php" class="btn btn-outline-dark-custom">Cancel</a>
            </form>
        </div>
    <?php break;

    case 'themes':
        $themes = $db->query("SELECT * FROM themes ORDER BY is_live DESC, is_staging DESC, is_default DESC, name ASC")->fetchAll();
        $editTheme = null;
        $editDecorations = null;
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM themes WHERE id = ?");
            $stmt->execute([$id]);
            $editTheme = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($editTheme) {
                $editDecorations = json_decode($editTheme['decorations'] ?? '{}', true);
            }
        }
        ?>
        <style>
        #themeEditor .color-swatch {
            width: 56px;
            height: 56px;
            padding: 3px;
            cursor: pointer;
            flex-shrink: 0;
        }
        #themeEditor .color-swatch::-webkit-color-swatch-wrapper { padding: 0; }
        #themeEditor .color-swatch::-webkit-color-swatch { border: 2px solid #ddd; border-radius: 8px; }
        #themeEditor .color-swatch::-moz-color-swatch { border: 2px solid #ddd; border-radius: 8px; }
        </style>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-600 mb-0"><i class="fas fa-palette me-2 text-gold"></i><?= __t('themes') ?> (<?= count($themes) ?>)</h4>
            <button class="btn btn-gold-sm" onclick="document.getElementById('themeEditor').style.display='block';document.getElementById('themeEditor').scrollIntoView({behavior:'smooth'});document.querySelector('#themeEditorForm [name=name]').focus()"><i class="fas fa-plus me-1"></i><?= __t('add_category') ?></button>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ($themes as $t):
                $tc = escape($t['preview_color'] ?? '#FF8C00');
                $badges = [];
                if ($t['is_live']) $badges[] = ['Live', 'success'];
                if ($t['is_staging']) $badges[] = ['Staging', 'info'];
                if ($t['is_default']) $badges[] = ['Default', 'secondary'];
                if (!$t['is_live'] && !$t['is_staging'] && !$t['is_default']) $badges[] = ['Inactive', 'dark'];
            ?>
            <div class="col-md-4">
                <div class="border rounded-3 overflow-hidden" style="border-left:4px solid <?= $tc ?> !important;transition:all 0.2s" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.boxShadow=''">
                    <div class="p-3" style="background:linear-gradient(135deg, <?= $tc ?>15, #fff)">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-600 mb-1"><?= escape($t['name']) ?></h6>
                                <small class="text-muted"><?= escape($t['slug']) ?></small>
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($badges as [$label, $color]): ?>
                                    <span class="badge bg-<?= $color ?>"><?= $label ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if ($t['description']): ?>
                            <p class="small text-muted mb-2"><?= escape(mb_substr($t['description'], 0, 80)) ?><?= mb_strlen($t['description']) > 80 ? '...' : '' ?></p>
                        <?php endif; ?>
                        <div class="small text-muted mb-2">
                            <i class="far fa-clock me-1"></i>Updated: <?= date('M j, Y', strtotime($t['updated_at'])) ?>
                        </div>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="index.php?action=themes&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-dark-custom" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if (!$t['is_live']): ?>
                            <form method="POST" class="d-inline"><?= csrf() ?><input type="hidden" name="admin_action" value="theme_activate"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-success" title="Activate as Live"><i class="fas fa-check-circle"></i></button></form>
                            <?php endif; ?>
                            <?php if (!$t['is_staging']): ?>
                            <form method="POST" class="d-inline"><?= csrf() ?><input type="hidden" name="admin_action" value="theme_staging"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-info" title="Set as Staging"><i class="fas fa-flask"></i></button></form>
                            <?php endif; ?>
                            <?php if (!$t['is_default']): ?>
                            <form method="POST" class="d-inline"><?= csrf() ?><input type="hidden" name="admin_action" value="theme_default"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary" title="Set as Default"><i class="fas fa-star"></i></button></form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline"><?= csrf() ?><input type="hidden" name="admin_action" value="theme_duplicate"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-info" title="Duplicate"><i class="fas fa-copy"></i></button></form>
                            <form method="POST" class="d-inline"><?= csrf() ?><input type="hidden" name="admin_action" value="theme_preview"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-warning" title="Preview Site with this Theme"><i class="fas fa-eye"></i></button></form>
                            <?php if (!$t['is_default']): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this theme?')"><?= csrf() ?><input type="hidden" name="admin_action" value="theme_delete"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button></form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Theme Editor -->
        <div id="themeEditor" class="border p-4 mb-4" style="display:<?= $editTheme ? 'block' : 'none' ?>">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-600 mb-0"><i class="fas fa-palette me-2 text-gold"></i>Theme Editor</h5>
                <button type="button" class="btn btn-sm btn-outline-dark-custom" onclick="document.getElementById('themeEditor').style.display='none'"><?= __t('cancel') ?></button>
            </div>
            <form method="POST" id="themeEditorForm">
                <?= csrf() ?><input type="hidden" name="admin_action" value="theme_save">
                <?php if ($editTheme): ?><input type="hidden" name="id" value="<?= $editTheme['id'] ?>"><?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-600">Name</label>
                        <input type="text" name="name" class="form-control" value="<?= escape($editTheme['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-600"><?= __t('description') ?></label>
                        <input type="text" name="description" class="form-control" value="<?= escape($editTheme['description'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-600">Preview Color</label>
                        <input type="color" name="preview_color" class="form-control form-control-color" value="<?= escape($editTheme['preview_color'] ?? '#FF8C00') ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <div class="form-check">
                            <input type="checkbox" name="auto_schedule" class="form-check-input" id="auto_schedule" <?= ($editTheme['auto_schedule'] ?? 0) ? 'checked' : '' ?> onchange="document.getElementById('scheduleDates').style.display=this.checked?'flex':'none'">
                            <label class="form-check-label small" for="auto_schedule">Auto Schedule</label>
                        </div>
                    </div>
                </div>

                <div id="scheduleDates" class="row g-3 mt-1" style="display:<?= ($editTheme['auto_schedule'] ?? 0) ? 'flex' : 'none' ?>">
                    <div class="col-md-3">
                        <label class="form-label small">From</label>
                        <input type="date" name="scheduled_from" class="form-control" value="<?= escape($editTheme['scheduled_from'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">To</label>
                        <input type="date" name="scheduled_to" class="form-control" value="<?= escape($editTheme['scheduled_to'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <h6 class="fw-600 mb-2 border-bottom pb-1">Quick Styles — Light Mode</h6>
                    </div>
                    <?php
                    $qs = $editDecorations['quick_styles'] ?? [];
                    $lightFields = [
                        'qs_bg_color' => ['Bg Color', 'bg_color', '#ffffff'],
                        'qs_text_color' => ['Text Color', 'text_color', '#212529'],
                        'qs_link_color' => ['Link Color', 'link_color', '#0011ff'],
                        'qs_heading_color' => ['Heading Color', 'heading_color', '#1a1a2e'],
                        'qs_btn_bg' => ['Button Bg', 'btn_bg', '#ff8c00'],
                        'qs_btn_text' => ['Button Text', 'btn_text', '#ffffff'],
                        'qs_navbar_bg' => ['Navbar Bg', 'navbar_bg', '#ff8c00'],
                        'qs_card_bg' => ['Card Bg', 'card_bg', '#ffffff'],
                    ];
                    ?>
                    <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($lightFields as $key => [$label, $storedKey, $default]): ?>
                    <div class="text-center" style="width:64px">
                        <input type="color" name="<?= $key ?>" class="form-control color-swatch" value="<?= escape($qs[$storedKey] ?? $default) ?>" title="<?= $label ?>">
                        <span class="d-block text-muted mt-1" style="font-size:12px;line-height:1.2;font-weight:500"><?= $label ?></span>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-12">
                        <h6 class="fw-600 mb-2 border-bottom pb-1">Quick Styles — Dark Mode</h6>
                    </div>
                    <?php
                    $darkFields = [
                        'qs_dark_bg_color' => ['Bg Color', 'dark_bg_color', '#121212'],
                        'qs_dark_text_color' => ['Text Color', 'dark_text_color', '#f5f0eb'],
                        'qs_dark_link_color' => ['Link Color', 'dark_link_color', '#ff8c00'],
                        'qs_dark_heading_color' => ['Heading Color', 'dark_heading_color', '#f5f0eb'],
                        'qs_dark_btn_bg' => ['Button Bg', 'dark_btn_bg', '#ff8c00'],
                        'qs_dark_btn_text' => ['Button Text', 'dark_btn_text', '#ffffff'],
                        'qs_dark_navbar_bg' => ['Navbar Bg', 'dark_navbar_bg', '#121212'],
                        'qs_dark_card_bg' => ['Card Bg', 'dark_card_bg', '#1e1e1e'],
                    ];
                    ?>
                    <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($darkFields as $key => [$label, $storedKey, $default]): ?>
                    <div class="text-center" style="width:64px">
                        <input type="color" name="<?= $key ?>" class="form-control color-swatch" value="<?= escape($qs[$storedKey] ?? $default) ?>" title="<?= $label ?>">
                        <span class="d-block text-muted mt-1" style="font-size:12px;line-height:1.2;font-weight:500"><?= $label ?></span>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small">Border Radius</label>
                        <input type="text" name="qs_border_radius" class="form-control" value="<?= escape($qs['border_radius'] ?? '') ?>" placeholder="e.g. 8px">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small">Font Size</label>
                        <input type="text" name="qs_font_size" class="form-control" value="<?= escape($qs['font_size'] ?? '') ?>" placeholder="e.g. 16px">
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <h6 class="fw-600 mb-2 border-bottom pb-1">Decorations &amp; Effects</h6>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mb-2">
                            <input type="checkbox" name="decor_enabled" class="form-check-input" id="decor_enabled" <?= ($editDecorations['enabled'] ?? false) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="decor_enabled">Enable Decorations</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="decor_snowflakes" class="form-check-input" id="decor_snowflakes" <?= ($editDecorations['snowflakes'] ?? false) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="decor_snowflakes">Snowflakes ❄️</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="decor_confetti" class="form-check-input" id="decor_confetti" <?= ($editDecorations['confetti'] ?? false) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="decor_confetti">Confetti 🎉</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Particles</label>
                        <select name="decor_particles" class="form-select">
                            <option value="none" <?= ($editDecorations['particles'] ?? 'none') === 'none' ? 'selected' : '' ?>>None</option>
                            <option value="snow" <?= ($editDecorations['particles'] ?? '') === 'snow' ? 'selected' : '' ?>>Snow</option>
                            <option value="rain" <?= ($editDecorations['particles'] ?? '') === 'rain' ? 'selected' : '' ?>>Rain</option>
                            <option value="mist" <?= ($editDecorations['particles'] ?? '') === 'mist' ? 'selected' : '' ?>>Mist</option>
                            <option value="smoke" <?= ($editDecorations['particles'] ?? '') === 'smoke' ? 'selected' : '' ?>>Smoke</option>
                            <option value="stone_rain" <?= ($editDecorations['particles'] ?? '') === 'stone_rain' ? 'selected' : '' ?>>Stone Rain</option>
                            <option value="gold_dust" <?= ($editDecorations['particles'] ?? '') === 'gold_dust' ? 'selected' : '' ?>>Gold Dust</option>
                            <option value="stars" <?= ($editDecorations['particles'] ?? '') === 'stars' ? 'selected' : '' ?>>Stars</option>
                            <option value="hearts" <?= ($editDecorations['particles'] ?? '') === 'hearts' ? 'selected' : '' ?>>Hearts</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Particle Count</label>
                        <input type="number" name="decor_particle_count" class="form-control" value="<?= (int)($editDecorations['particle_count'] ?? 50) ?>" min="0" max="200">
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-12">
                        <h6 class="fw-600 mb-2 border-bottom pb-1">Themed Banner Badge</h6>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check pt-2">
                            <input type="checkbox" name="badge_enabled" class="form-check-input" id="badge_enabled" <?= ($editDecorations['badge_enabled'] ?? false) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="badge_enabled">Show Badge</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Badge Text (EN)</label>
                        <input type="text" name="badge_text_en" class="form-control" value="<?= escape($editDecorations['badge_text_en'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Badge Text (SW)</label>
                        <input type="text" name="badge_text_sw" class="form-control" value="<?= escape($editDecorations['badge_text_sw'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Badge Icon</label>
                        <input type="text" name="badge_icon" class="form-control" value="<?= escape($editDecorations['badge_icon'] ?? '') ?>" placeholder="fa-truck">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-gold"><i class="fas fa-save me-1"></i><?= __t('save') ?></button>
                    <button type="button" class="btn btn-outline-dark-custom" onclick="document.getElementById('themeEditor').style.display='none'"><?= __t('cancel') ?></button>
                </div>
            </form>
        </div>

        <div class="bg-light p-3 rounded-3 small">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="fas fa-info-circle text-gold"></i>
                <span class="fw-600"><?= __t('themes') ?> Guide</span>
            </div>
            <p class="mb-0 text-muted" style="white-space:pre-line"><?= __t('theme_guide') ?></p>
        </div>
    <?php break;

    case 'settings':
        $settings = $db->query("SELECT * FROM settings")->fetchAll();
        $settingsMap = [];
        foreach ($settings as $s) $settingsMap[$s['key']] = $s['value'];
        $locked = empty($_SESSION['settings_verified']);
    ?>
        <h4 class="fw-600 mb-3">Settings</h4>

        <?php if ($locked): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <span><i class="fas fa-lock me-2"></i>Sensitive sections (Payment, SMTP, SMS) are locked. <a href="#" onclick="showSettingsPassword();event.preventDefault();" class="fw-600">Verify admin password</a> to unlock.</span>
            <button class="btn btn-sm btn-dark-custom" onclick="showSettingsPassword()"><i class="fas fa-unlock me-1"></i>Unlock</button>
        </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf() ?><input type="hidden" name="admin_action" value="settings_save">
            <div class="border p-4 mb-3">
                <h6 class="fw-600 mb-3">General</h6>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label small">Site Name</label><input type="text" name="site_name" class="form-control" value="<?= escape($settingsMap['site_name'] ?? SITE_NAME) ?>"></div>
                    <div class="col-md-4"><label class="form-label small">Currency</label><select name="currency" class="form-select"><?php foreach (['TZS','USD','KES','UGX'] as $c): ?><option value="<?= $c ?>" <?= ($settingsMap['currency'] ?? CURRENCY) === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><label class="form-label small">Tax Rate (%)</label><input type="number" name="tax_rate" class="form-control" value="<?= $settingsMap['tax_rate'] ?? TAX_RATE ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Shipping Threshold (TZS)</label><input type="number" name="shipping_threshold" class="form-control" placeholder="e.g. 50000" value="<?= $settingsMap['shipping_threshold'] ?? SHIPPING_THRESHOLD ?>"><small class="text-muted">Orders below this amount pay default rate</small></div>
                    <div class="col-md-2"><label class="form-label small">Rate Below (%)</label><input type="number" step="0.1" name="shipping_rate_default" class="form-control" placeholder="e.g. 5" value="<?= $settingsMap['shipping_rate_default'] ?? SHIPPING_RATE_DEFAULT ?>"></div>
                    <div class="col-md-2"><label class="form-label small">Rate Above (%)</label><input type="number" step="0.1" name="shipping_rate_reduced" class="form-control" placeholder="e.g. 2" value="<?= $settingsMap['shipping_rate_reduced'] ?? SHIPPING_RATE_REDUCED ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Free Shipping Min</label><input type="number" name="free_shipping_min" class="form-control" value="<?= $settingsMap['free_shipping_min'] ?? FREE_SHIPPING_MIN ?>"></div>
                    <div class="col-md-4"><label class="form-label small">Default Payment</label><select name="default_payment" class="form-select"><?php foreach (['mpesa'=>'M-Pesa','airtel_money'=>'Airtel Money','tigo_pesa'=>'Tigo Pesa','halopesa'=>'HaloPesa'] as $k=>$v): ?><option value="<?= $k ?>" <?= ($settingsMap['default_payment'] ?? 'mpesa') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?><option value="beem" <?= ($settingsMap['default_payment'] ?? 'mpesa') === 'beem' ? 'selected' : '' ?>>Beem (All Networks)</option></select></div>
                </div>
            </div>

            <div class="border p-4 mb-3 position-relative <?= $locked ? 'opacity-50' : '' ?>" id="paymentSection">
                <?php if ($locked): ?>
                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="z-index:2;background:rgba(255,255,255,0.5);">
                    <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i>Locked — verify password above</span>
                </div>
                <?php endif; ?>
                <h6 class="fw-600 mb-3"><i class="fas fa-credit-card me-2"></i>AzamPay</h6>
                <p class="small text-muted mb-3">Mobile money payments via AzamPay (M-Pesa, Airtel, Tigo, HaloPesa)</p>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label small">Client ID</label><input type="text" name="azampay_client_id" class="form-control" value="<?= escape($settingsMap['azampay_client_id'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-4"><label class="form-label small">Client Secret</label><input type="password" name="azampay_client_secret" class="form-control" value="<?= escape($settingsMap['azampay_client_secret'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-4"><label class="form-label small">App Name (Token)</label><input type="text" name="azampay_app_name" class="form-control" value="<?= escape($settingsMap['azampay_app_name'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-6"><label class="form-label small">Environment</label><select name="azampay_environment" class="form-select" <?= $locked ? 'disabled' : '' ?>>
                        <option value="sandbox" <?= ($settingsMap['azampay_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                        <option value="production" <?= ($settingsMap['azampay_environment'] ?? '') === 'production' ? 'selected' : '' ?>>Production</option>
                    </select></div>
                    <div class="col-md-6"><label class="form-label small">Callback URL</label><input type="text" class="form-control" value="<?= SITE_URL ?>/payment/callback.php" disabled></div>
                </div>
            </div>

            <div class="border p-4 mb-3 position-relative <?= $locked ? 'opacity-50' : '' ?>" id="smtpSection">
                <?php if ($locked): ?>
                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="z-index:2;background:rgba(255,255,255,0.5);">
                    <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i>Locked — verify password above</span>
                </div>
                <?php endif; ?>
                <h6 class="fw-600 mb-3"><i class="fas fa-envelope me-2"></i>Email (SMTP) Settings</h6>
                <p class="small text-muted mb-3">Used for PHPMailer (password resets, order updates, contact notifications)</p>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label small">SMTP Host</label><input type="text" name="smtp_host" class="form-control" value="<?= escape($settingsMap['smtp_host'] ?? 'smtp.gmail.com') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-4"><label class="form-label small">SMTP Username</label><input type="text" name="smtp_username" class="form-control" value="<?= escape($settingsMap['smtp_username'] ?? 'mahuvechriss@gmail.com') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-4"><label class="form-label small">SMTP Password</label><input type="password" name="smtp_password" class="form-control" value="<?= escape($settingsMap['smtp_password'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-3"><label class="form-label small">Encryption</label><select name="smtp_encryption" class="form-select" <?= $locked ? 'disabled' : '' ?>><option value="tls" <?= ($settingsMap['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option><option value="ssl" <?= ($settingsMap['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option></select></div>
                    <div class="col-md-2"><label class="form-label small">Port</label><input type="number" name="smtp_port" class="form-control" value="<?= $settingsMap['smtp_port'] ?? 587 ?>" <?= $locked ? 'disabled' : '' ?>></div>
                </div>
            </div>

            <div class="border p-4 mb-3 position-relative <?= $locked ? 'opacity-50' : '' ?>" id="beemSection">
                <?php if ($locked): ?>
                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="z-index:2;background:rgba(255,255,255,0.5);">
                    <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i>Locked — verify password above</span>
                </div>
                <?php endif; ?>
                <h6 class="fw-600 mb-3"><i class="fas fa-comment-dots me-2"></i>SMS Settings</h6>
                <p class="small text-muted mb-3">Used for SMS notifications (order updates, alerts)</p>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label small">API Key</label><input type="text" name="beem_api_key" class="form-control" value="<?= escape($settingsMap['beem_api_key'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-4"><label class="form-label small">Secret Key</label><input type="password" name="beem_secret_key" class="form-control" value="<?= escape($settingsMap['beem_secret_key'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-4"><label class="form-label small">Sender ID</label><input type="text" name="beem_sender_id" class="form-control" value="<?= escape($settingsMap['beem_sender_id'] ?? 'CHILDAFYA') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                </div>
            </div>

            <div class="border p-4 mb-3 position-relative <?= $locked ? 'opacity-50' : '' ?>" id="beemPaymentSection">
                <?php if ($locked): ?>
                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="z-index:2;background:rgba(255,255,255,0.5);">
                    <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i>Locked — verify password above</span>
                </div>
                <?php endif; ?>
                <h6 class="fw-600 mb-3"><i class="fas fa-credit-card me-2"></i>Beem Payments</h6>
                <p class="small text-muted mb-3">Mobile money payments via Beem Payment Checkout (M-Pesa, Airtel, Tigo, HaloPesa)</p>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label small">Payment API Key</label><input type="text" name="beem_payment_api_key" class="form-control" value="<?= escape($settingsMap['beem_payment_api_key'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-4"><label class="form-label small">Payment Secret Key</label><input type="password" name="beem_payment_secret_key" class="form-control" value="<?= escape($settingsMap['beem_payment_secret_key'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-4"><label class="form-label small">Reference Prefix</label><input type="text" name="beem_reference_prefix" class="form-control" value="<?= escape($settingsMap['beem_reference_prefix'] ?? 'INNOCE') ?>" <?= $locked ? 'disabled' : '' ?>></div>
                    <div class="col-md-6"><label class="form-label small">Environment</label><select name="beem_payment_environment" class="form-select" <?= $locked ? 'disabled' : '' ?>>
                        <option value="sandbox" <?= ($settingsMap['beem_payment_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                        <option value="production" <?= ($settingsMap['beem_payment_environment'] ?? '') === 'production' ? 'selected' : '' ?>>Production</option>
                    </select></div>
                    <div class="col-md-6"><label class="form-label small">Callback URL</label><input type="text" class="form-control" value="<?= SITE_URL ?>/payment/callback.php" disabled></div>
                </div>
            </div>

            <div class="border p-4 mb-3">
                <h6 class="fw-600 mb-3"><i class="fas fa-layer-group me-2"></i>Volume Discount Tiers</h6>
                <p class="small text-muted mb-3">Automatic discounts based on total cart quantity. Applied before coupons.</p>
                <?php $vdTiers = json_decode(getSetting('volume_discount_tiers', VOLUME_DISCOUNT_TIERS), true); ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="vdTiersTable">
                        <thead><tr><th>Min Qty</th><th>Max Qty</th><th>Discount (%)</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($vdTiers as $i => $tier): ?>
                            <tr>
                                <td><input type="number" name="vd_min_<?= $i ?>" class="form-control form-control-sm" value="<?= $tier[0] ?>" style="width:80px"></td>
                                <td><input type="number" name="vd_max_<?= $i ?>" class="form-control form-control-sm" value="<?= $tier[1] ?>" style="width:80px"></td>
                                <td><input type="number" step="0.1" name="vd_pct_<?= $i ?>" class="form-control form-control-sm" value="<?= $tier[2] ?>" style="width:80px"></td>
                                <td><button type="button" class="btn btn-sm btn-danger-custom" onclick="this.closest('tr').remove()"><i class="fas fa-trash"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-dark-custom" onclick="addVdTier()"><i class="fas fa-plus me-1"></i>Add Tier</button>
                <input type="hidden" name="volume_discount_tiers" id="volume_discount_tiers_input">
            </div>

            <button type="submit" class="btn btn-gold" onclick="serializeVdTiers()">Save All Settings</button>
        </form>
        <script>
        function addVdTier() {
            var tbody = document.querySelector('#vdTiersTable tbody');
            var idx = tbody.children.length;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td><input type="number" name="vd_min_' + idx + '" class="form-control form-control-sm" value="0" style="width:80px"></td>' +
                '<td><input type="number" name="vd_max_' + idx + '" class="form-control form-control-sm" value="0" style="width:80px"></td>' +
                '<td><input type="number" step="0.1" name="vd_pct_' + idx + '" class="form-control form-control-sm" value="0" style="width:80px"></td>' +
                '<td><button type="button" class="btn btn-sm btn-danger-custom" onclick="this.closest(\'tr\').remove()"><i class="fas fa-trash"></i></button></td>';
            tbody.appendChild(tr);
        }
        function serializeVdTiers() {
            var tiers = [];
            document.querySelectorAll('#vdTiersTable tbody tr').forEach(function(tr) {
                var inputs = tr.querySelectorAll('input');
                if (inputs.length === 3) {
                    var min = parseInt(inputs[0].value);
                    var max = parseInt(inputs[1].value);
                    var pct = parseFloat(inputs[2].value);
                    if (!isNaN(min) && !isNaN(max) && !isNaN(pct)) {
                        tiers.push([min, max, pct]);
                    }
                }
            });
            document.getElementById('volume_discount_tiers_input').value = JSON.stringify(tiers);
        }
        </script>

        <!-- Password Verification Modal -->
        <div class="modal fade" id="settingsPasswordModal" tabindex="-1">
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title"><i class="fas fa-lock me-2 text-gold"></i>Verify Admin Password</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form onsubmit="verifySettingsPassword(event)">
                        <div class="modal-body">
                            <p class="small text-muted">Enter your admin password to unlock sensitive settings.</p>
                            <input type="password" id="settingsPasswordInput" class="form-control" placeholder="Your password" required autocomplete="off">
                            <div id="settingsPasswordError" class="text-danger small mt-2 d-none">Wrong password. Try again.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-dark-custom btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-gold btn-sm" id="settingsPasswordBtn"><i class="fas fa-check me-1"></i>Verify</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        function showSettingsPassword() {
            var modal = new bootstrap.Modal(document.getElementById('settingsPasswordModal'));
            modal.show();
            document.getElementById('settingsPasswordInput').value = '';
            document.getElementById('settingsPasswordError').classList.add('d-none');
        }

        function verifySettingsPassword(e) {
            e.preventDefault();
            var btn = document.getElementById('settingsPasswordBtn');
            var input = document.getElementById('settingsPasswordInput');
            var error = document.getElementById('settingsPasswordError');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Verifying...';
            error.classList.add('d-none');

            var form = new FormData();
            form.append('admin_action', 'verify_settings');
            form.append('password', input.value);
            form.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

            fetch('index.php', { method: 'POST', body: form })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        location.reload();
                    } else {
                        error.classList.remove('d-none');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check me-1"></i>Verify';
                    }
                })
                .catch(function() {
                    error.textContent = 'Error connecting. Try again.';
                    error.classList.remove('d-none');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check me-1"></i>Verify';
                });
        }
        </script>
    <?php break;

    case 'profile':
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $admin = $stmt->fetch();
    ?>
        <h4 class="fw-600 mb-3">My Profile</h4>
        <div class="border p-4" style="max-width: 500px;">
            <form method="POST">
                <?= csrf() ?><input type="hidden" name="admin_action" value="profile_save">
                <div class="mb-3">
                    <label class="form-label small fw-600">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= escape($admin['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-600">Email</label>
                    <input type="email" class="form-control" value="<?= escape($admin['email']) ?>" disabled>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label small fw-600">New Password <small class="text-muted">(leave empty to keep current)</small></label>
                    <input type="password" name="password" class="form-control" minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-600">Confirm Password</label>
                    <input type="password" name="password_confirm" class="form-control">
                </div>
                <button type="submit" class="btn btn-gold"><?= __t('save') ?></button>
            </form>
        </div>
    <?php break;
}
require_once __DIR__ . '/includes/footer.php'; ?>
