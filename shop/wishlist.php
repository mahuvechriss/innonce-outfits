<?php
require_once __DIR__ . '/../config.php';
requireLogin();
$pageTitle = 'Wishlist';

$userId = $_SESSION['user_id'];

if ($_GET['action'] ?? '' === 'add' && ($_GET['product_id'] ?? 0)) {
    $pid = (int)$_GET['product_id'];
    $stmt = $db->prepare("SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $pid]);
    if (!$stmt->fetch()) {
        $db->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)")->execute([$userId, $pid]);
    }
    redirect('wishlist.php', 'Added to wishlist!');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { redirect('wishlist.php', 'Invalid token.', 'error'); }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'remove') {
        $db->prepare("DELETE FROM wishlists WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    } elseif ($action === 'move_to_cart') {
        $stmt = $db->prepare("SELECT product_id FROM wishlists WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $w = $stmt->fetch();
        if ($w) {
            $stmt = $db->prepare("SELECT id FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $w['product_id']]);
            if ($stmt->fetch()) {
                $db->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?")->execute([$userId, $w['product_id']]);
            } else {
                $db->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)")->execute([$userId, $w['product_id']]);
            }
            $db->prepare("DELETE FROM wishlists WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        }
    }
    redirect('wishlist.php');
}

$stmt = $db->prepare("SELECT w.*, p.name_en, p.slug, p.price, p.discount_price, pi.image_path as primary_image FROM wishlists w JOIN products p ON w.product_id = p.id LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 WHERE w.user_id = ? ORDER BY w.created_at DESC");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php'; ?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0 mb-0">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php" class="text-muted"><i class="fas fa-home me-1"></i><?= __('home') ?></a></li>
            <li class="breadcrumb-item active text-gold" aria-current="page"><i class="fas fa-heart me-1"></i><?= __('wishlist') ?></li>
        </ol>
    </nav>

    <div class="section-header mb-4">
        <div class="section-icon bg-gold"><i class="fas fa-heart"></i></div>
        <h3 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('my_wishlist') ?></h3>
        <?php if ($items): ?><span class="text-muted ms-auto small"><?= count($items) ?> <?= __('items') ?></span><?php endif; ?>
    </div>

    <?php if (!$items): ?>
    <div class="empty-state py-5">
        <div class="empty-icon"><i class="fas fa-heart-broken"></i></div>
        <h5><?= __('wishlist_empty') ?></h5>
        <p class="text-muted"><?= __('wishlist_empty_desc') ?></p>
        <a href="index.php" class="btn btn-gold"><i class="fas fa-shopping-bag me-2"></i><?= __('browse_products') ?></a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($items as $item): ?>
        <div class="col-md-4 col-lg-3">
            <div class="card product-card">
                <div class="position-relative">
                    <a href="index.php?product=<?= escape($item['slug']) ?>">
                        <img src="<?= $item['primary_image'] ? SITE_URL . '/' . $item['primary_image'] : 'https://placehold.co/300x400/121212/FF8C00?text=N' ?>" alt="<?= escape($item['name_en']) ?>">
                    </a>
                    <form action="wishlist.php" method="POST" class="position-absolute top-0 end-0 p-2">
                        <?= csrf() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <button type="submit" class="card-badge bg-danger border-0" title="<?= __('remove') ?>"><i class="fas fa-times"></i></button>
                    </form>
                </div>
                <div class="card-body">
                    <small class="text-muted text-uppercase small"><i class="fas fa-tag me-1"></i><?= __('product') ?></small>
                    <h6 class="mt-1"><a href="index.php?product=<?= escape($item['slug']) ?>" class="text-dark text-decoration-none"><?= escape($item['name_en']) ?></a></h6>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <?php if ($item['discount_price']): ?>
                        <span class="price-current text-gold"><?= formatMoney($item['discount_price']) ?></span>
                        <span class="price-old"><?= formatMoney($item['price']) ?></span>
                        <?php else: ?>
                        <span class="price-current"><?= formatMoney($item['price']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <form action="wishlist.php" method="POST" class="flex-grow-1">
                            <?= csrf() ?><input type="hidden" name="action" value="move_to_cart"><input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn-gold-sm w-100"><i class="fas fa-shopping-cart me-1"></i><?= __('move_to_cart') ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
