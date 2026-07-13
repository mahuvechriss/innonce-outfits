<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Track Order';

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderNumber = trim($_POST['order_number'] ?? '');
    if ($orderNumber) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
        $stmt->execute([$orderNumber]);
        $result = $stmt->fetch();
        if (!$result) { $_SESSION['error'] = 'Order not found.'; }
    }
}

require_once __DIR__ . '/../includes/header.php'; ?>
<div class="container py-5">
    <h3 class="fw-600 text-center mb-4" style="font-family: 'Playfair Display', serif;">Track Your Order</h3>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <form method="POST" class="d-flex gap-2">
                <?= csrf() ?>
                <input type="text" name="order_number" class="form-control" placeholder="Enter order number (e.g. INV-2024-...)" required>
                <button type="submit" class="btn btn-gold"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <?php if ($result): ?>
    <div class="row justify-content-center mt-4">
        <div class="col-md-6">
            <div class="border p-4">
                <p><strong>Order:</strong> #<?= escape($result['order_number']) ?></p>
                <p><strong>Status:</strong> <span class="badge bg-<?= $result['status'] === 'delivered' ? 'success' : 'secondary' ?>"><?= ucfirst($result['status']) ?></span></p>
                <p><strong>Payment:</strong> <span class="text-<?= $result['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= ucfirst($result['payment_status']) ?></span></p>
                <p><strong>Total:</strong> <?= formatMoney($result['total']) ?></p>
                <p><strong>Date:</strong> <?= date('M d, Y', strtotime($result['created_at'])) ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
