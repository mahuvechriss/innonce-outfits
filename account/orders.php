<?php
require_once __DIR__ . '/../config.php';
requireLogin();
$pageTitle = 'My Orders';
$userId = $_SESSION['user_id'];
$orderNumber = $_GET['order'] ?? '';

if ($orderNumber) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->execute([$orderNumber, $userId]);
    $order = $stmt->fetch();
    if (!$order) { redirect('orders.php', 'Order not found.', 'error'); }
    $items = $db->prepare("SELECT oi.*, p.name_en, p.slug FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $items->execute([$order['id']]);
    $items = $items->fetchAll();
    $tracking = $db->prepare("SELECT * FROM order_trackings WHERE order_id = ? ORDER BY created_at ASC");
    $tracking->execute([$order['id']]);
    $tracking = $tracking->fetchAll();
    $address = json_decode($order['shipping_address'], true);

    // Retry payment for unpaid/failed orders
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retry_payment'])) {
        require_once __DIR__ . '/../includes/azampay.php';
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            redirect("orders.php?order={$order['order_number']}", 'Invalid token.', 'error');
        }
        $newRef = generateReference();
        $prevTx = $db->prepare("SELECT phone FROM payment_transactions WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $prevTx->execute([$order['id']]);
        $prevPhone = $prevTx->fetchColumn() ?: '';
        $stmt = $db->prepare("INSERT INTO payment_transactions (order_id, user_id, payment_method, reference, amount, currency, phone, status) VALUES (?, ?, ?, ?, ?, 'TZS', ?, 'pending')");
        $stmt->execute([$order['id'], $userId, $order['payment_method'], $newRef, $order['total'], $prevPhone]);

        $azamResult = azampayInitiatePayment($order['total'], $prevPhone, $newRef, $order['payment_method']);
        if ($azamResult['success']) {
            $db->prepare("UPDATE payment_transactions SET transaction_id = ?, response_data = ? WHERE reference = ?")
                ->execute([$azamResult['transaction_id'], json_encode($azamResult['raw']), $newRef]);
            $_SESSION['success'] = 'Payment request sent! Check your phone.';
        } else {
            $db->prepare("UPDATE payment_transactions SET status = 'failed', response_data = ? WHERE reference = ?")
                ->execute([json_encode($azamResult['raw']), $newRef]);
            $_SESSION['info'] = 'Payment retry failed. Please try again later.';
        }
        header("Location: orders.php?order={$order['order_number']}");
        exit;
    }

    require_once __DIR__ . '/../includes/header.php'; ?>
    <div class="container py-5">
        <a href="orders.php" class="btn btn-outline-dark-custom btn-sm mb-3"><i class="fas fa-arrow-left me-1"></i><?= __('back_to_orders') ?></a>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h4 class="fw-700 mb-1" style="font-family: 'Playfair Display', serif;"><?= __('order') ?> #<?= escape($order['order_number']) ?></h4>
                <small class="text-muted"><i class="fas fa-calendar me-1"></i><?= __('placed_on') ?> <?= date('F d, Y \a\t h:i A', strtotime($order['created_at'])) ?></small>
            </div>
            <span class="order-status order-status-<?= $order['status'] ?> fs-6"><?= ucfirst($order['status']) ?></span>
        </div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="form-card p-4 mb-4">
                    <h6 class="fw-700 mb-3"><i class="fas fa-clock me-2 text-gold"></i><?= __('order_timeline') ?></h6>
                    <div class="timeline">
                        <?php $statuses = ['pending', 'confirmed', 'processing', 'packed', 'shipped', 'delivered']; $idx = array_search($order['status'], $statuses); ?>
                        <?php foreach ($statuses as $i => $s): ?>
                        <div class="timeline-item <?= $i <= $idx ? 'completed' : '' ?>">
                            <div class="timeline-dot <?= $i <= $idx ? 'bg-success' : '' ?>">
                                <?php if ($i < $idx): ?><i class="fas fa-check"></i><?php elseif ($i === $idx): ?><i class="fas fa-dot-circle"></i><?php else: ?><i class="far fa-circle"></i><?php endif; ?>
                            </div>
                            <div class="timeline-content">
                                <strong class="small"><?= ucfirst($s) ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-card p-4">
                    <h6 class="fw-700 mb-3"><i class="fas fa-box me-2 text-gold"></i><?= __('order_items') ?></h6>
                    <?php foreach ($items as $item): ?>
                    <div class="order-item-row">
                        <div class="flex-grow-1">
                            <a href="../shop/index.php?product=<?= escape($item['slug']) ?>" class="text-dark text-decoration-none fw-600"><?= escape($item['name_en']) ?></a>
                            <?php if ($item['size']): ?><br><small class="text-muted"><i class="fas fa-ruler me-1"></i><?= escape($item['size']) ?></small><?php endif; ?>
                        </div>
                        <div class="text-end">
                            <small><?= $item['quantity'] ?> <i class="fas fa-times mx-1"></i> <?= formatMoney($item['price']) ?></small>
                            <br><strong class="text-gold"><?= formatMoney($item['total']) ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="form-card p-4 mb-3">
                    <h6 class="fw-700 mb-3"><i class="fas fa-map-marker-alt me-2 text-gold"></i><?= __('shipping_address') ?></h6>
                    <div class="d-flex align-items-start gap-2">
                        <i class="fas fa-location-dot text-gold mt-1"></i>
                        <div class="small">
                            <p class="mb-1"><?= escape($address['street'] ?? '') ?></p>
                            <p class="mb-1"><?= escape($address['city'] ?? '') ?>, <?= escape($address['region'] ?? '') ?></p>
                            <p class="mb-0"><?= escape($address['country'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
                <div class="form-card p-4 mb-3">
                    <h6 class="fw-700 mb-3"><i class="fas fa-credit-card me-2 text-gold"></i><?= __('payment') ?></h6>
                    <div class="small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted"><?= __('method') ?></span>
                            <span><i class="fas fa-mobile-alt me-1"></i><?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted"><?= __('status') ?></span>
                            <span class="text-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?> fw-600"><?= ucfirst($order['payment_status']) ?></span>
                        </div>
                    </div>
                </div>
                <?php if ($order['payment_status'] !== 'paid'): ?>
                <div class="form-card p-4 mb-3">
                    <form method="POST">
                        <?= csrf() ?>
                        <input type="hidden" name="retry_payment" value="<?= escape($order['order_number']) ?>">
                        <button type="submit" class="btn btn-gold w-100"><i class="fas fa-credit-card me-2"></i>Retry Payment (<?= formatMoney($order['total']) ?>)</button>
                    </form>
                </div>
                <?php endif; ?>
                <div class="form-card p-4">
                    <h6 class="fw-700 mb-3"><i class="fas fa-receipt me-2 text-gold"></i><?= __('total_breakdown') ?></h6>
                    <div class="d-flex justify-content-between small mb-1"><span class="text-muted"><?= __('subtotal') ?></span><span><?= formatMoney($order['subtotal']) ?></span></div>
                    <div class="d-flex justify-content-between small mb-1"><span class="text-muted"><?= __('shipping') ?></span><span><?= $order['shipping'] ? formatMoney($order['shipping']) : '<span class="text-success">' . __('free') . '</span>' ?></span></div>
                    <div class="d-flex justify-content-between small mb-1"><span class="text-muted"><?= __('tax') ?></span><span><?= formatMoney($order['tax']) ?></span></div>
                    <?php if ($order['discount']): ?><div class="d-flex justify-content-between small mb-1"><span class="text-muted"><?= __('discount') ?></span><span class="text-danger">-<?= formatMoney($order['discount']) ?></span></div><?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between fw-700 fs-5"><span><?= __('total') ?></span><span class="text-gold"><?= formatMoney($order['total']) ?></span></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0 mb-0">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php" class="text-muted"><i class="fas fa-home me-1"></i><?= __('home') ?></a></li>
            <li class="breadcrumb-item active text-gold" aria-current="page"><i class="fas fa-box me-1"></i><?= __('my_orders') ?></li>
        </ol>
    </nav>

    <div class="section-header mb-4">
        <div class="section-icon bg-gold"><i class="fas fa-box"></i></div>
        <h3 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('my_orders') ?></h3>
        <?php if ($orders): ?><span class="text-muted ms-auto small"><?= count($orders) ?> <?= __('total_orders') ?></span><?php endif; ?>
    </div>

    <?php if (!$orders): ?>
    <div class="empty-state py-5">
        <div class="empty-icon"><i class="fas fa-box-open"></i></div>
        <h5><?= __('no_orders') ?></h5>
        <p class="text-muted"><?= __('no_orders_desc') ?></p>
        <a href="../shop/index.php" class="btn btn-gold"><i class="fas fa-shopping-bag me-2"></i><?= __('start_shopping') ?></a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($orders as $order): ?>
        <div class="col-md-6 col-lg-4">
            <div class="order-card p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="order-card-number">#<?= escape($order['order_number']) ?></span>
                    <span class="order-status order-status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-2">
                    <span><i class="fas fa-calendar me-1"></i><?= date('M d, Y', strtotime($order['created_at'])) ?></span>
                    <span><i class="fas fa-credit-card me-1"></i><?= ucfirst($order['payment_status']) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="fw-700 fs-5 text-gold"><?= formatMoney($order['total']) ?></span>
                    <a href="orders.php?order=<?= escape($order['order_number']) ?>" class="btn btn-outline-dark-custom btn-sm"><i class="fas fa-eye me-1"></i><?= __('view') ?></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
