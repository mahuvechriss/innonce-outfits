<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/azampay.php';
require_once __DIR__ . '/../includes/beem.php';
requireLogin();
$pageTitle = 'Checkout';

$userId = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT c.*, p.name_en, p.name_sw, p.slug, p.price, p.discount_price, pi.image_path as primary_image FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 WHERE c.user_id = ?");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

if (!$items) { redirect('cart.php', 'Your cart is empty.', 'error'); }

$subtotal = 0;
$totalQty = 0;
foreach ($items as $item) {
    $price = $item['discount_price'] ?: $item['price'];
    $subtotal += $price * $item['quantity'];
    $totalQty += $item['quantity'];
}
$volumeDiscountPct = calculateVolumeDiscount($totalQty);
$volumeDiscount = $subtotal * $volumeDiscountPct / 100;
$discount = $_SESSION['coupon']['discount'] ?? 0;
$deliveryMethod = $_POST['delivery_method'] ?? $_SESSION['delivery_method'] ?? 'delivery';
$shippingThreshold = (float)getSetting('shipping_threshold', SHIPPING_THRESHOLD);
$shippingRate = $subtotal >= $shippingThreshold
    ? (float)getSetting('shipping_rate_reduced', SHIPPING_RATE_REDUCED)
    : (float)getSetting('shipping_rate_default', SHIPPING_RATE_DEFAULT);
$shipping = $deliveryMethod === 'pickup' ? 0 : $subtotal * $shippingRate / 100;
$freeShippingMin = (float)getSetting('free_shipping_min', FREE_SHIPPING_MIN);
if ($subtotal >= $freeShippingMin) $shipping = 0;
$taxRate = (float)(getSetting('tax_rate', TAX_RATE));
$tax = $subtotal * $taxRate / 100;
$total = $subtotal + $tax + $shipping - $volumeDiscount - $discount;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { redirect('checkout.php', 'Invalid token.', 'error'); }

    // Delivery method quick-switch (from mini-form)
    if (isset($_POST['delivery_method']) && empty($_POST['name'])) {
        $_SESSION['delivery_method'] = $_POST['delivery_method'] === 'pickup' ? 'pickup' : 'delivery';
        header('Location: checkout.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'mpesa';

    if (empty($name) || empty($email) || empty($phone) || empty($city) || empty($street)) {
        $_SESSION['error'] = 'Please fill in all required fields.';
        header('Location: checkout.php');
        exit;
    }

    try {
        $db->beginTransaction();

        $orderNumber = generateOrderNumber();
        $shippingAddress = json_encode(['country' => $country, 'region' => $region, 'city' => $city, 'street' => $street]);
        $couponCode = $_SESSION['coupon']['code'] ?? null;
        $couponDiscount = $discount;

        $stmt = $db->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, order_number, status, subtotal, tax, shipping, delivery_method, discount, volume_discount, total, payment_method, payment_status, currency, shipping_address, coupon_code, coupon_discount) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', 'TZS', ?, ?, ?)");
        $stmt->execute([$userId, $name, $phone, $orderNumber, $subtotal, $tax, $shipping, $deliveryMethod, $discount, $volumeDiscount, $total, $paymentMethod, $shippingAddress, $couponCode, $couponDiscount]);
        $orderId = $db->lastInsertId();

        foreach ($items as $item) {
            $price = $item['discount_price'] ?: $item['price'];
            $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, total, size, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $price, $price * $item['quantity'], $item['size'], $item['color']]);
        }

        $stmt = $db->prepare("INSERT INTO order_trackings (order_id, status, description) VALUES (?, 'pending', 'Order placed successfully.')");
        $stmt->execute([$orderId]);

        // Create payment transaction
        $ref = generateReference();
        $stmt = $db->prepare("INSERT INTO payment_transactions (order_id, user_id, payment_method, reference, amount, currency, phone, status) VALUES (?, ?, ?, ?, ?, 'TZS', ?, 'pending')");
        $stmt->execute([$orderId, $userId, $paymentMethod, $ref, $total, $phone]);

        // Clear cart
        $db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
        unset($_SESSION['coupon']);

        $db->commit();

        // Initiate payment
        if ($paymentMethod === 'beem') {
            $txnId = bin2hex(random_bytes(16));
            $txnId = substr($txnId, 0, 8) . '-' . substr($txnId, 8, 4) . '-4' . substr($txnId, 12, 3) . '-' . dechex(hexdec(substr($txnId, 15, 4)) & 0x3fff | 0x8000) . '-' . substr($txnId, 19, 12);
            $beemResult = beemInitiatePayment($total, $phone, $ref, $txnId);

            if ($beemResult['success']) {
                $db->prepare("UPDATE payment_transactions SET transaction_id = ?, response_data = ? WHERE reference = ?")
                    ->execute([$beemResult['transaction_id'], json_encode($beemResult['raw']), $ref]);
                $_SESSION['beem_checkout_url'] = $beemResult['checkout_url'];
                $_SESSION['beem_order_number'] = $orderNumber;
                header("Location: " . SITE_URL . "/payment/beem_redirect.php");
                exit;
            } else {
                $db->prepare("UPDATE payment_transactions SET status = 'failed', response_data = ? WHERE reference = ?")
                    ->execute([json_encode($beemResult['raw']), $ref]);
                $_SESSION['info'] = 'Order placed but payment could not be initiated. You can retry payment from your orders.';
            }
        } else {
            $azamResult = azampayInitiatePayment($total, $phone, $ref, $paymentMethod);

            if ($azamResult['success']) {
                $db->prepare("UPDATE payment_transactions SET transaction_id = ?, response_data = ? WHERE reference = ?")
                    ->execute([$azamResult['transaction_id'], json_encode($azamResult['raw']), $ref]);
                $_SESSION['success'] = 'Order placed! Check your phone to complete payment.';
            } else {
                $db->prepare("UPDATE payment_transactions SET status = 'failed', response_data = ? WHERE reference = ?")
                    ->execute([json_encode($azamResult['raw']), $ref]);
                $_SESSION['info'] = 'Order placed but payment could not be initiated. You can retry payment from your orders.';
            }
        }

        header("Location: " . SITE_URL . "/account/orders.php?order=$orderNumber");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error placing order: ' . $e->getMessage();
        header('Location: checkout.php');
        exit;
    }
}

$user = $db->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0 mb-0">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php" class="text-muted"><i class="fas fa-home me-1"></i><?= __('home') ?></a></li>
            <li class="breadcrumb-item"><a href="index.php" class="text-muted"><i class="fas fa-store me-1"></i><?= __('shop') ?></a></li>
            <li class="breadcrumb-item"><a href="cart.php" class="text-muted"><i class="fas fa-shopping-cart me-1"></i><?= __('cart') ?></a></li>
            <li class="breadcrumb-item active text-gold" aria-current="page"><i class="fas fa-credit-card me-1"></i><?= __('checkout') ?></li>
        </ol>
    </nav>

    <div class="section-header justify-content-center mb-4">
        <div class="section-icon bg-gold"><i class="fas fa-credit-card"></i></div>
        <h3 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('checkout') ?></h3>
    </div>

    <form method="POST">
        <?= csrf() ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="form-card p-4 mb-4">
                    <h5 class="fw-700 mb-3"><i class="fas fa-user me-2 text-gold"></i><?= __('customer_details') ?></h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-user-circle me-1"></i><?= __('full_name') ?> <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= escape($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-phone me-1"></i><?= __('phone') ?> <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control" value="<?= escape($user['phone'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-envelope me-1"></i><?= __('email') ?> <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= escape($user['email']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-card p-4 mb-4">
                    <h5 class="fw-700 mb-3"><i class="fas fa-map-marker-alt me-2 text-gold"></i><?= __('delivery_address') ?></h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-globe me-1"></i><?= __('country') ?></label>
                            <select name="country" class="form-select">
                                <option value="Tanzania">🇹🇿 Tanzania</option>
                                <option value="Kenya">🇰🇪 Kenya</option>
                                <option value="Uganda">🇺🇬 Uganda</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-map-marked me-1"></i><?= __('region') ?></label>
                            <select name="region" class="form-select">
                                <option>Dar es Salaam</option>
                                <option>Arusha</option>
                                <option>Mwanza</option>
                                <option>Mbeya</option>
                                <option>Zanzibar</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-city me-1"></i><?= __('city') ?> <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control" placeholder="<?= __('city_placeholder') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-road me-1"></i><?= __('street') ?> <span class="text-danger">*</span></label>
                            <input type="text" name="street" class="form-control" placeholder="<?= __('street_placeholder') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-card p-4 mb-4">
                    <h5 class="fw-700 mb-3"><i class="fas fa-truck me-2 text-gold"></i>Delivery Method</h5>
                    <form id="deliveryForm" method="POST" style="display:none"><?= csrf() ?><input type="hidden" name="delivery_method" id="deliveryInput"></form>
                    <div class="d-flex gap-4">
                        <label class="payment-option flex-grow-1" style="cursor:pointer">
                            <input type="radio" name="delivery_method" value="delivery" <?= $deliveryMethod === 'delivery' ? 'checked' : '' ?> onchange="document.getElementById('deliveryInput').value=this.value;document.getElementById('deliveryForm').submit()">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-truck fa-lg" style="color:#FF8C00"></i>
                                <div>
                                    <strong>Delivery</strong>
                                    <br><small class="text-muted">Ship to my address</small>
                                </div>
                            </div>
                        </label>
                        <label class="payment-option flex-grow-1" style="cursor:pointer">
                            <input type="radio" name="delivery_method" value="pickup" <?= $deliveryMethod === 'pickup' ? 'checked' : '' ?> onchange="document.getElementById('deliveryInput').value=this.value;document.getElementById('deliveryForm').submit()">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-store fa-lg" style="color:#FF8C00"></i>
                                <div>
                                    <strong>Pick up at shop</strong>
                                    <br><small class="text-muted">No delivery fee</small>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-card p-4">
                    <h5 class="fw-700 mb-3"><i class="fas fa-credit-card me-2 text-gold"></i><?= __('payment_method') ?></h5>
                    <div class="row g-3">
                        <?php $methods = [
                            'mpesa' => ['M-Pesa', '#4CAF50'],
                            'airtel_money' => ['Airtel Money', '#E53935'],
                            'tigo_pesa' => ['Tigo Pesa', '#1565C0'],
                            'halopesa' => ['HaloPesa', '#FF6F00'],
                        ]; ?>
                        <?php foreach ($methods as $key => [$name, $color]): ?>
                        <div class="col-md-6">
                            <label class="payment-option">
                                <input class="form-check-input" type="radio" name="payment_method" value="<?= $key ?>" <?= $key === 'mpesa' ? 'checked' : '' ?>>
                                <div class="d-flex align-items-center">
                                    <div class="payment-icon" style="background:<?= $color ?>15;color:<?= $color ?>;"><i class="fas fa-mobile-alt"></i></div>
                                    <div>
                                        <strong><?= $name ?></strong>
                                        <br><small class="text-muted"><?= __('pay_with') ?> <?= $name ?></small>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                        <div class="col-md-6">
                            <label class="payment-option">
                                <input class="form-check-input" type="radio" name="payment_method" value="beem">
                                <div class="d-flex align-items-center">
                                    <div class="payment-icon" style="background:#6C2BD915;color:#6C2BD9;"><i class="fas fa-credit-card"></i></div>
                                    <div>
                                        <strong>Beem (All Networks)</strong>
                                        <br><small class="text-muted">Pay via M-Pesa, Airtel, Tigo, HaloPesa</small>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="form-card p-4 sticky-sidebar">
                    <h6 class="fw-700 mb-3"><i class="fas fa-receipt me-2 text-gold"></i><?= __('order_summary') ?></h6>
                    <?php foreach ($items as $item): ?>
                    <div class="d-flex gap-2 mb-2 align-items-center">
                        <img src="<?= $item['primary_image'] ? SITE_URL . '/' . $item['primary_image'] : 'https://placehold.co/60x75/121212/FF8C00?text=N' ?>" style="width: 50px; height: 60px; object-fit: cover; border-radius: 8px;">
                        <div class="small flex-grow-1">
                            <div class="fw-600"><?= escape(t($item['name_en'], $item['name_sw'])) ?></div>
                            <small class="text-muted"><i class="fas fa-times me-1"></i><?= $item['quantity'] ?></small>
                        </div>
                        <div class="small fw-600"><?= formatMoney(($item['discount_price'] ?: $item['price']) * $item['quantity']) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="d-flex justify-content-between small mb-1"><span class="text-muted"><?= __('subtotal') ?></span><span><?= formatMoney($subtotal) ?></span></div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Delivery</span>
                        <span class="small"><?= $deliveryMethod === 'pickup' ? 'Pick up at shop' : 'Delivery' ?></span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1"><span class="text-muted"><?= __('shipping') ?></span><span><?= $shipping ? formatMoney($shipping) : '<span class="text-success"><i class="fas fa-check-circle me-1"></i>' . __('free') . '</span>' ?></span></div>
                    <div class="d-flex justify-content-between small mb-1"><span class="text-muted"><?= __('tax') ?></span><span><?= formatMoney($tax) ?></span></div>
                    <?php if ($volumeDiscount > 0): ?>
                    <div class="d-flex justify-content-between small mb-1"><span class="text-muted"><?= __('volume_discount') ?> (<?= $volumeDiscountPct ?>%)</span><span class="text-danger">-<?= formatMoney($volumeDiscount) ?></span></div>
                    <?php endif; ?>
                    <?php if ($discount > 0): ?>
                    <div class="d-flex justify-content-between small mb-1"><span class="text-muted"><?= __('discount') ?></span><span class="text-danger">-<?= formatMoney($discount) ?></span></div>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between fw-700 fs-5"><span><?= __('total') ?></span><span class="text-gold"><?= formatMoney($total) ?></span></div>
                    <button type="submit" class="btn btn-gold w-100 mt-3"><i class="fas fa-lock me-2"></i><?= __('place_order') ?></button>
                    <div class="mt-2 small text-muted text-center">
                        <i class="fas fa-shield-alt me-1"></i><?= __('secure_checkout') ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
document.querySelectorAll('.form-check').forEach(el => {
    el.addEventListener('click', function() { this.querySelector('.form-check-input').checked = true; });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
