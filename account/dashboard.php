<?php
require_once __DIR__ . '/../config.php';
requireLogin();
$pageTitle = __('dashboard');

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect(SITE_URL . '/auth/login.php', 'Session expired. Please login again.', 'error');
}

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$orderCount = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$wishlistCount = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentOrders = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0 mb-0">
            <li class="breadcrumb-item active text-gold" aria-current="page"><i class="fas fa-home me-1"></i><?= __('dashboard') ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="form-card p-4 text-center">
                <div class="mb-3">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="<?= SITE_URL . '/' . $user['profile_photo'] ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;object-position:<?= escape($user['photo_align'] ?? 'center') ?>;border:3px solid var(--gold);">
                    <?php else: ?>
                        <i class="fas fa-user-circle" style="font-size:4rem;color:var(--gold);"></i>
                    <?php endif; ?>
                </div>
                <h4 class="fw-700 mb-1"><?= __('welcome_back') ?>, <?= escape($user['name']) ?>!</h4>
                <p class="text-muted small mb-0"><?= escape($user['email']) ?></p>
                <hr>
                <a href="profile.php" class="btn btn-outline-dark-custom btn-sm w-100 mb-2"><i class="fas fa-user-cog me-2"></i><?= __('my_profile') ?></a>
                <a href="orders.php" class="btn btn-outline-dark-custom btn-sm w-100 mb-2"><i class="fas fa-box me-2"></i><?= __('my_orders') ?></a>
                <a href="../shop/wishlist.php" class="btn btn-outline-dark-custom btn-sm w-100"><i class="fas fa-heart me-2"></i><?= __('my_wishlist') ?></a>
            </div>
        </div>

        <div class="col-md-8">
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="form-card p-3 text-center">
                        <div class="text-gold fs-3 fw-700"><?= $orderCount ?></div>
                        <div class="text-muted small"><?= __('total_orders') ?></div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-card p-3 text-center">
                        <div class="text-gold fs-3 fw-700"><?= $wishlistCount ?></div>
                        <div class="text-muted small"><?= __('wishlist_items') ?></div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-card p-3 text-center">
                        <div class="text-gold fs-3 fw-700"><?= cartCount() ?></div>
                        <div class="text-muted small"><?= __('cart_items') ?></div>
                    </div>
                </div>
            </div>

            <?php if ($recentOrders): ?>
            <div class="form-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-700 mb-0"><i class="fas fa-clock me-2 text-gold"></i><?= __('recent_orders') ?></h5>
                    <a href="orders.php" class="btn btn-outline-dark-custom btn-sm"><?= __('view_all') ?> <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th><?= __('order_number') ?></th>
                                <th><?= __('date') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('total') ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td><span class="fw-600 small">#<?= escape($o['order_number']) ?></span></td>
                                <td class="small text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= match($o['status']) {
                                        'pending' => 'warning',
                                        'confirmed' => 'info',
                                        'shipped' => 'primary',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'secondary'
                                    } ?>"><?= escape(ucfirst($o['status'])) ?></span>
                                </td>
                                <td class="fw-600"><?= formatMoney($o['total']) ?></td>
                                <td><a href="orders.php?order=<?= escape($o['order_number']) ?>" class="btn btn-sm btn-outline-dark-custom"><i class="fas fa-eye"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="form-card p-5 text-center">
                <i class="fas fa-shopping-bag" style="font-size:3rem;color:var(--gold);opacity:0.5;"></i>
                <h5 class="fw-600 mt-3 mb-2"><?= __('no_orders_yet') ?></h5>
                <p class="text-muted small"><?= __('start_shopping') ?></p>
                <a href="<?= SITE_URL ?>/shop/index.php" class="btn btn-gold mt-2"><i class="fas fa-store me-2"></i><?= __('shop_now') ?></a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
