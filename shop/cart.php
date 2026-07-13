<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Cart';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid token.';
        header('Location: cart.php');
        exit;
    }
    // Handle delivery method change (no redirect needed, continues below)
    if (isset($_POST['delivery_method'])) {
        $_SESSION['delivery_method'] = $_POST['delivery_method'] === 'pickup' ? 'pickup' : 'delivery';
    }
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);
    $userId = $_SESSION['user_id'];

    if ($action === 'add' && $productId) {
        $size = $_POST['size'] ?? null;
        $color = $_POST['color'] ?? null;
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $stmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size <=> ? AND color <=> ?");
        $stmt->execute([$userId, $productId, $size, $color]);
        $existing = $stmt->fetch();
        if ($existing) {
            $stmt = $db->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$qty, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantity, size, color) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $productId, $qty, $size, $color]);
        }
        $_SESSION['success'] = 'Item added to cart!';
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$qty, $id, $userId]);
    } elseif ($action === 'remove') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    } elseif ($action === 'apply_coupon') {
        $code = trim($_POST['coupon'] ?? '');
        $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND status = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND (usage_limit IS NULL OR used_count < usage_limit)");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();
        if ($coupon) {
            $total = cartTotal($userId);
            if ($coupon['min_purchase'] && $total < $coupon['min_purchase']) {
                $_SESSION['error'] = 'Minimum purchase of ' . formatMoney($coupon['min_purchase']) . ' required.';
            } else {
                $discount = $coupon['type'] === 'percentage' ? ($total * $coupon['value'] / 100) : $coupon['value'];
                $_SESSION['coupon'] = ['code' => $coupon['code'], 'discount' => $discount];
                $_SESSION['success'] = 'Coupon applied!';
            }
        } else {
            $_SESSION['error'] = 'Invalid or expired coupon.';
        }
    }
    header('Location: cart.php');
    exit;
}

$items = [];
$subtotal = 0;
if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT c.*, p.name_en, p.name_sw, p.slug, p.price, p.discount_price, p.quantity as stock, pi.image_path as primary_image FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 WHERE c.user_id = ?");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
    foreach ($items as $item) {
        $price = $item['discount_price'] ?: $item['price'];
        $subtotal += $price * $item['quantity'];
    }
}

$discount = $_SESSION['coupon']['discount'] ?? 0;
$deliveryMethod = $_SESSION['delivery_method'] ?? 'delivery';
$shippingThreshold = (float)getSetting('shipping_threshold', SHIPPING_THRESHOLD);
$shippingRate = $subtotal >= $shippingThreshold
    ? (float)getSetting('shipping_rate_reduced', SHIPPING_RATE_REDUCED)
    : (float)getSetting('shipping_rate_default', SHIPPING_RATE_DEFAULT);
$shipping = $deliveryMethod === 'pickup' ? 0 : $subtotal * $shippingRate / 100;
$freeShippingMin = (float)getSetting('free_shipping_min', FREE_SHIPPING_MIN);
if ($subtotal >= $freeShippingMin) $shipping = 0;
$taxRate = (float)(getSetting('tax_rate', TAX_RATE));
$tax = $subtotal * $taxRate / 100;
$total = $subtotal + $tax + $shipping - $discount;

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0 mb-0">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php" class="text-muted"><i class="fas fa-home me-1"></i><?= __('home') ?></a></li>
            <li class="breadcrumb-item"><a href="index.php" class="text-muted"><i class="fas fa-store me-1"></i><?= __('shop') ?></a></li>
            <li class="breadcrumb-item active text-gold" aria-current="page"><i class="fas fa-shopping-cart me-1"></i><?= __('cart') ?></li>
        </ol>
    </nav>

    <?php if ($items): ?>
    <div class="section-header mb-4">
        <div class="section-icon bg-gold"><i class="fas fa-shopping-cart"></i></div>
        <h3 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('shopping_cart') ?></h3>
        <span class="text-muted ms-auto small"><?= count($items) ?> <?= __('items') ?></span>
    </div>
    <?php endif; ?>

    <?php if (!$items): ?>
    <div class="empty-state py-5">
        <div class="empty-icon"><i class="fas fa-shopping-cart"></i></div>
        <h5><?= __('cart_empty') ?></h5>
        <p class="text-muted"><?= __('cart_empty_desc') ?></p>
        <a href="index.php" class="btn btn-gold"><i class="fas fa-arrow-left me-2"></i><?= __('continue_shopping') ?></a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <?php foreach ($items as $item): ?>
            <div class="cart-item">
                <img src="<?= $item['primary_image'] ? SITE_URL . '/' . $item['primary_image'] : 'https://placehold.co/100x130/121212/FF8C00?text=INNOCE' ?>" class="cart-item-img">
                <div class="flex-grow-1">
                    <h6 class="mb-1"><a href="index.php?product=<?= escape($item['slug']) ?>" class="text-dark text-decoration-none"><?= escape($item['name_en']) ?></a></h6>
                    <?php if ($item['size']): ?><span class="cart-item-meta"><i class="fas fa-ruler me-1"></i><?= escape($item['size']) ?></span><?php endif; ?>
                    <?php if ($item['color']): ?><span class="cart-item-meta ms-2"><i class="fas fa-palette me-1"></i><?= escape($item['color']) ?></span><?php endif; ?>
                    <div class="d-flex align-items-center gap-3 mt-2">
                        <form action="cart.php" method="POST">
                            <?= csrf() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <div class="qty-selector">
                                <button type="button" class="qty-btn" onclick="var q=this.parentNode.querySelector('input');if(parseInt(q.value)>1){q.value--;q.form.submit()}"><i class="fas fa-minus"></i></button>
                                <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>" class="qty-input" readonly>
                                <button type="button" class="qty-btn" onclick="var q=this.parentNode.querySelector('input');if(parseInt(q.value)<<?= $item['stock'] ?>){q.value++;q.form.submit()}"><i class="fas fa-plus"></i></button>
                            </div>
                        </form>
                        <span class="cart-item-price"><?= formatMoney(($item['discount_price'] ?: $item['price']) * $item['quantity']) ?></span>
                        <form action="cart.php" method="POST" class="ms-auto">
                            <?= csrf() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger-custom" title="<?= __('remove') ?>"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="text-end mt-3">
                <a href="index.php" class="btn btn-outline-dark-custom btn-sm"><i class="fas fa-arrow-left me-1"></i><?= __('continue_shopping') ?></a>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="form-card p-4 sticky-sidebar">
                <h6 class="fw-700 mb-3"><i class="fas fa-receipt me-2 text-gold"></i><?= __('order_summary') ?></h6>
                <form action="cart.php" method="POST" class="mb-3">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="apply_coupon">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent"><i class="fas fa-ticket-alt text-gold"></i></span>
                        <input type="text" name="coupon" class="form-control" placeholder="<?= __('coupon_code') ?>">
                        <button type="submit" class="btn btn-outline-dark-custom"><?= __('apply') ?></button>
                    </div>
                </form>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted small"><?= __('subtotal') ?></span><span class="small"><?= formatMoney($subtotal) ?></span></div>
                <form action="cart.php" method="POST" class="mb-2">
                    <?= csrf() ?>
                    <div class="d-flex gap-3 small">
                        <label class="d-flex align-items-center gap-1" style="cursor:pointer">
                            <input type="radio" name="delivery_method" value="delivery" <?= $deliveryMethod === 'delivery' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span class="text-muted">Delivery</span>
                        </label>
                        <label class="d-flex align-items-center gap-1" style="cursor:pointer">
                            <input type="radio" name="delivery_method" value="pickup" <?= $deliveryMethod === 'pickup' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span class="text-muted">Pick up at shop</span>
                        </label>
                    </div>
                </form>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted small"><?= __('shipping') ?></span><span class="small"><?= $shipping ? formatMoney($shipping) : '<span class="text-success"><i class="fas fa-check-circle me-1"></i>' . __('free') . '</span>' ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted small"><?= __('tax') ?> (<?= $taxRate ?>%)</span><span class="small"><?= formatMoney($tax) ?></span></div>
                <?php if ($discount > 0): ?>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted small"><?= __('discount') ?></span><span class="text-danger small">-<?= formatMoney($discount) ?></span></div>
                <?php endif; ?>
                <hr>
                <div class="d-flex justify-content-between fw-700 fs-5"><span><?= __('total') ?></span><span class="text-gold"><?= formatMoney($total) ?></span></div>
                <a href="checkout.php" class="btn btn-gold w-100 mt-3"><i class="fas fa-lock me-2"></i><?= __('proceed_checkout') ?></a>
                <?php if ($shipping > 0): ?>
                <div class="mt-2 small text-muted text-center">
                    <i class="fas fa-info-circle me-1"></i><?= __('free_shipping_note') ?> <?= formatMoney($freeShippingMin - $subtotal) ?> <?= __('more') ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
