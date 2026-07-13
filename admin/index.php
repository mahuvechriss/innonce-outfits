<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Admin Dashboard';

$action = $_GET['action'] ?? 'dashboard';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { redirect('index.php', 'Invalid token.', 'error'); }
    $actionPost = $_POST['admin_action'] ?? '';

    // Products
    if ($actionPost === 'product_save') {
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameSw = trim($_POST['name_sw'] ?? '');
        $slug = slugify($nameEn);
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
        $editId = (int)($_POST['id'] ?? 0);

        if ($editId) {
            $oldStmt = $db->prepare("SELECT new_arrival FROM products WHERE id = ?");
            $oldStmt->execute([$editId]);
            $oldArrival = $oldStmt->fetchColumn();
            $stmt = $db->prepare("UPDATE products SET category_id=?, name_en=?, name_sw=?, slug=?, price=?, discount_price=?, quantity=?, brand=?, description_en=?, description_sw=?, featured=?, new_arrival=?, status=?, sizes=? WHERE id=?");
            $stmt->execute([$categoryId, $nameEn, $nameSw, $slug, $price, $discountPrice, $quantity, $brand, $descEn, $descSw, $featured, $newArrival, $status, $sizes, $editId]);
            if (!empty($_FILES['image']['name'])) {
                $path = uploadFile($_FILES['image']);
                if ($path) {
                    $db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$editId]);
                    $stmt = $db->prepare("SELECT id FROM product_images WHERE product_id = ? AND is_primary = 1");
                    $stmt->execute([$editId]);
                    if (!$stmt->fetch()) {
                        $db->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 1)")->execute([$editId, $path]);
                    }
                }
            }
            if ($newArrival && !$oldArrival) {
                redirect('index.php?action=broadcast&product_id=' . $editId, 'Product updated! Send a new arrival notification to customers.');
            }
            redirect('index.php?action=products', 'Product updated.');
        } else {
            $stmt = $db->prepare("INSERT INTO products (category_id, name_en, name_sw, slug, price, discount_price, quantity, brand, description_en, description_sw, featured, new_arrival, status, sizes, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $sku = strtoupper(substr($nameEn, 0, 3)) . '-' . time();
            $stmt->execute([$categoryId, $nameEn, $nameSw, $slug, $price, $discountPrice, $quantity, $brand, $descEn, $descSw, $featured, $newArrival, $status, $sizes, $sku]);
            $pid = $db->lastInsertId();
            if (!empty($_FILES['image']['name'])) {
                $path = uploadFile($_FILES['image']);
                if ($path) $db->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 1)")->execute([$pid, $path]);
            }
            if ($newArrival) {
                redirect('index.php?action=broadcast&product_id=' . $pid, 'Product created! Send a new arrival notification to customers.');
            }
            redirect('index.php?action=products', 'Product created.');
        }
    } elseif ($actionPost === 'product_delete') {
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        redirect('index.php?action=products', 'Product deleted.');
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
            if (in_array($key, ['csrf_token', 'admin_action'])) continue;
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
        $sent = sendBroadcastNotification($title, $message, $recipientType, $channels, $productLink);
        redirect('index.php?action=broadcast', "Broadcast sent! $sent in-app notifications delivered.");
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
                    <?php $orders = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll(); ?>
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

    case 'products':
        $editProduct = null;
        if ($id) { $stmt = $db->prepare("SELECT * FROM products WHERE id = ?"); $stmt->execute([$id]); $editProduct = $stmt->fetch(); }
        $products = $db->query("SELECT p.*, c.name_en as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC")->fetchAll();
        $cats = $db->query("SELECT * FROM categories WHERE status = 1 ORDER BY name_en")->fetchAll();
        $sizesList = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];
    ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-600 mb-0">Products (<?= count($products) ?>)</h4>
            <button class="btn-gold-sm" onclick="showForm('product')">+ Add Product</button>
        </div>
        <?php if ($editProduct || isset($_GET['show_form'])): ?>
        <div class="border p-4 mb-4" id="productForm">
            <h5 class="fw-600 mb-3"><?= $editProduct ? 'Edit Product' : 'New Product' ?></h5>
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
                        <label class="form-label small">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= ($editProduct['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($editProduct['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="draft" <?= ($editProduct['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Image</label>
                        <input type="file" name="image" class="form-control">
                        <div class="form-check mt-2">
                            <input type="checkbox" name="featured" class="form-check-input" id="featured" <?= ($editProduct['featured'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="featured">Featured</label>
                        </div>
                        <div class="form-check mt-1">
                            <input type="checkbox" name="new_arrival" class="form-check-input" id="new_arrival" <?= ($editProduct['new_arrival'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="new_arrival"><i class="fas fa-star text-warning me-1"></i>New Arrival</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-gold mt-3">Save Product</button>
                <a href="index.php?action=products" class="btn btn-outline-dark-custom mt-3">Cancel</a>
            </form>
        </div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Qty</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><a href="<?= SITE_URL ?>/shop/index.php?product=<?= escape($p['slug']) ?>" target="_blank"><?= escape($p['name_en']) ?></a></td>
                    <td><small><?= escape($p['cat_name'] ?? '') ?></small></td>
                    <td><?= formatMoney($p['discount_price'] ?: $p['price']) ?></td>
                    <td><?= $p['quantity'] ?></td>
                    <td><span class="badge bg-<?= $p['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $p['status'] ?></span></td>
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
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
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
                        <p><strong>Customer:</strong> <?= escape($order['user_id']) ?> (ID)</p>
                        <p><strong>Total:</strong> <?= formatMoney($order['total']) ?></p>
                        <p><strong>Status:</strong> <span class="badge bg-<?= $order['status'] === 'delivered' ? 'success' : 'secondary' ?>"><?= ucfirst($order['status']) ?></span></p>
                        <p><strong>Payment:</strong> <?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?> - <span class="text-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= $order['payment_status'] ?></span></p>
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
            $orders = $db->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll(); ?>
            <h4 class="fw-600 mb-3">Orders (<?= count($orders) ?>)</h4>
            <table class="table table-sm">
                <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody><?php foreach ($orders as $o): ?>
                <tr><td>#<?= escape($o['order_number']) ?></td><td><small>User #<?= $o['user_id'] ?></small></td><td><?= formatMoney($o['total']) ?></td><td><span class="small text-<?= $o['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= $o['payment_status'] ?></span></td><td><span class="badge bg-secondary"><?= ucfirst($o['status']) ?></span></td><td><small><?= date('M d', strtotime($o['created_at'])) ?></small></td><td><a href="index.php?action=orders&id=<?= $o['id'] ?>" class="btn btn-sm btn-dark-custom">View</a></td></tr>
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
                            <small class="<?= $c['is_online'] ? 'text-success fw-600' : 'text-muted' ?>"><?= $c['is_online'] ? 'Online' : 'Offline' ?></small>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No customers found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
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
        $product = null;
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
        }
    ?>
        <h4 class="fw-600 mb-3"><i class="fas fa-bullhorn me-2"></i>Broadcast Notification</h4>
        <?php if ($product): ?>
        <div class="alert alert-info">Preparing notification for product: <strong><?= escape($product['name_en']) ?></strong></div>
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
                    <div class="col-md-2"><label class="form-label small">Shipping Fee</label><input type="number" name="shipping_fee" class="form-control" value="<?= $settingsMap['shipping_fee'] ?? SHIPPING_FEE ?>"></div>
                    <div class="col-md-4"><label class="form-label small">Free Shipping Min</label><input type="number" name="free_shipping_min" class="form-control" value="<?= $settingsMap['free_shipping_min'] ?? FREE_SHIPPING_MIN ?>"></div>
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
                    <div class="col-md-4"><label class="form-label small">Sender ID</label><input type="text" name="beem_sender_id" class="form-control" value="<?= escape($settingsMap['beem_sender_id'] ?? 'INNOCE') ?>" <?= $locked ? 'disabled' : '' ?>></div>
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

            <button type="submit" class="btn btn-gold">Save All Settings</button>
        </form>

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
                <button type="submit" class="btn btn-gold">Save Changes</button>
            </form>
        </div>
    <?php break;
}
require_once __DIR__ . '/includes/footer.php'; ?>
