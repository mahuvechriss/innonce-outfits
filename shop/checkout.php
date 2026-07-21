<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/beem.php';
require_once __DIR__ . '/../includes/pawapay.php';
require_once __DIR__ . '/../includes/stakaba.php';
$defaultPayment = getSetting('default_payment', 'pawapay');
$stakabaKey = stakabaApiKey();
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
    $paymentMethod = $_POST['payment_method'] ?? $defaultPayment;

    if (empty($name) || empty($email) || empty($phone) || empty($city) || empty($street)) {
        $_SESSION['error'] = 'Please fill in all required fields.';
        header('Location: checkout.php');
        exit;
    }

    if (!isValidTzPhone($phone)) {
        $_SESSION['error'] = 'Enter a valid phone number (e.g. 0712 345 678 or +255 712 345 678).';
        header('Location: checkout.php');
        exit;
    }

    try {
        $db->beginTransaction();

        $orderNumber = generateOrderNumber();
        $shippingAddress = json_encode(['country' => $country, 'region' => $region, 'city' => $city, 'street' => $street]);
        $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
        $couponCode = $_SESSION['coupon']['code'] ?? null;
        $couponDiscount = $discount;

        $stmt = $db->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, order_number, status, subtotal, tax, shipping, delivery_method, discount, volume_discount, total, payment_method, payment_status, currency, shipping_address, latitude, longitude, coupon_code, coupon_discount) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', 'TZS', ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $name, $phone, $orderNumber, $subtotal, $tax, $shipping, $deliveryMethod, $discount, $volumeDiscount, $total, $paymentMethod, $shippingAddress, $latitude, $longitude, $couponCode, $couponDiscount]);
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
        if ($paymentMethod === 'stakaba') {
            $skResult = stakabaCheckout($total, $orderNumber, $orderId, $email, $phone, $name);

            if ($skResult['success']) {
                $db->prepare("UPDATE payment_transactions SET transaction_id = ?, response_data = ? WHERE reference = ?")
                    ->execute([$skResult['internal_reference'], json_encode($skResult['raw']), $ref]);
                $_SESSION['stakaba_ref'] = $skResult['internal_reference'];
                $_SESSION['stakaba_order_number'] = $orderNumber;
                header("Location: " . $skResult['redirect_url']);
                exit;
            } else {
                $db->prepare("UPDATE payment_transactions SET status = 'failed', response_data = ? WHERE reference = ?")
                    ->execute([json_encode($skResult['raw']), $ref]);
                $_SESSION['info'] = 'Order placed but payment could not be initiated. You can retry payment from your orders.';
            }
        } else {
            $ppResult = pawapayInitiateCheckout($total, $orderNumber, $orderId);

            if ($ppResult['success']) {
                $db->prepare("UPDATE payment_transactions SET transaction_id = ?, response_data = ? WHERE reference = ?")
                    ->execute([$ppResult['checkout_id'], json_encode($ppResult['raw']), $ref]);
                $_SESSION['pawapay_checkout_id'] = $ppResult['checkout_id'];
                $_SESSION['pawapay_order_number'] = $orderNumber;
                header("Location: " . $ppResult['redirect_url']);
                exit;
            } else {
                $db->prepare("UPDATE payment_transactions SET status = 'failed', response_data = ? WHERE reference = ?")
                    ->execute([json_encode($ppResult['raw']), $ref]);
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
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
                            <input type="tel" name="phone" class="form-control" value="<?= escape($user['phone'] ?? '') ?>" pattern="[\+\d\s\-]{9,15}" title="Tanzanian phone: 0712 345 678 or +255 712 345 678" placeholder="0712 345 678" required>
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
                    <div id="locationPicker" class="mt-3" style="<?= $deliveryMethod !== 'delivery' ? 'display:none' : '' ?>">
                        <label class="form-label"><i class="fas fa-map-pin me-1"></i><?= t('Pin your exact location', 'Weka alama mahali ulipo') ?></label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" id="detectBtn" class="btn btn-sm btn-outline-dark-custom"><i class="fas fa-crosshairs me-1"></i><?= t('Detect my location', 'Pata eneo langu') ?></button>
                            <small class="text-muted align-self-center"><?= t('Drop a pin on the map or click Detect', 'Weka alama ramanini au bofya Pata eneo langu') ?></small>
                        </div>
                        <div id="map" style="height:280px;border-radius:12px;border:1px solid rgba(255,255,255,0.1);z-index:0;"></div>
                        <input type="hidden" name="latitude" id="latitude" value="">
                        <input type="hidden" name="longitude" id="longitude" value="">
                        <div id="coordsDisplay" class="small text-muted mt-1"></div>
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
                        <div class="col-md-6">
                            <label class="payment-option">
                                <input class="form-check-input" type="radio" name="payment_method" value="pawapay" <?= $defaultPayment === 'pawapay' ? 'checked' : '' ?>>
                                <div class="d-flex align-items-center">
                                    <div class="payment-icon" style="background:#E91E6315;color:#E91E63;"><i class="fas fa-mobile-alt"></i></div>
                                    <div>
                                        <strong>Mobile Money</strong>
                                        <br><small class="text-muted">M-Pesa, Airtel, Tigo, HaloPesa</small>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <?php if ($stakabaKey): ?>
                        <div class="col-md-6">
                            <label class="payment-option">
                                <input class="form-check-input" type="radio" name="payment_method" value="stakaba" <?= $defaultPayment === 'stakaba' ? 'checked' : '' ?>>
                                <div class="d-flex align-items-center">
                                    <div class="payment-icon" style="background:#3366FF15;color:#3366FF;"><i class="fas fa-credit-card"></i></div>
                                    <div>
                                        <strong>Credit / Debit Card</strong>
                                        <br><small class="text-muted">Visa, Mastercard</small>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <?php endif; ?>
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

// Location picker map
var mapEl = document.getElementById('map');
var latInput = document.getElementById('latitude');
var lngInput = document.getElementById('longitude');
var coordsDisplay = document.getElementById('coordsDisplay');
var detectBtn = document.getElementById('detectBtn');
var locationPicker = document.getElementById('locationPicker');
var marker = null;
var map = null;

function initMap(lat, lng) {
    if (map) map.remove();
    map = L.map('map').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    marker.on('dragend', function() {
        var pos = marker.getLatLng();
        updateCoords(pos.lat, pos.lng);
    });
    map.on('click', function(e) {
        if (marker) map.removeLayer(marker);
        marker = L.marker([e.latlng.lat, e.latlng.lng], { draggable: true }).addTo(map);
        marker.on('dragend', function() {
            var pos = marker.getLatLng();
            updateCoords(pos.lat, pos.lng);
        });
        updateCoords(e.latlng.lat, e.latlng.lng);
    });
    updateCoords(lat, lng);
}

function updateCoords(lat, lng) {
    latInput.value = lat.toFixed(7);
    lngInput.value = lng.toFixed(7);
    coordsDisplay.innerHTML = lat.toFixed(5) + ', ' + lng.toFixed(5);
}

detectBtn.addEventListener('click', function() {
    if (!navigator.geolocation) { alert('Geolocation not supported'); return; }
    detectBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Detecting...';
    detectBtn.disabled = true;
    navigator.geolocation.getCurrentPosition(function(pos) {
        initMap(pos.coords.latitude, pos.coords.longitude);
        detectBtn.innerHTML = '<i class="fas fa-crosshairs me-1"></i><?= t('Detect my location', 'Pata eneo langu') ?>';
        detectBtn.disabled = false;
    }, function() {
        alert('<?= t('Could not detect location. Please drop a pin on the map.', 'Haikuweza kupata eneo. Tafadhali weka alama ramanini.') ?>');
        initMap(-6.162, 35.752); // Default Dodoma
        detectBtn.innerHTML = '<i class="fas fa-crosshairs me-1"></i><?= t('Detect my location', 'Pata eneo langu') ?>';
        detectBtn.disabled = false;
    }, { enableHighAccuracy: true, timeout: 10000 });
});

// Initialize with default location (Dodoma)
initMap(-6.162, 35.752);

// Toggle map visibility with delivery method
document.querySelectorAll('input[name="delivery_method"]').forEach(function(el) {
    el.addEventListener('change', function() {
        locationPicker.style.display = this.value === 'delivery' ? 'block' : 'none';
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
