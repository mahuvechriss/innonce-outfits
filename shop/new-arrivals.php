<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'New Arrivals';

$stmt = $db->query("SELECT p.*, c.name_en as cat_name, c.slug as cat_slug,
    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
    FROM products p LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active' AND p.new_arrival = 1
    ORDER BY p.created_at DESC");
$products = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php'; ?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0 mb-0">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php" class="text-muted"><i class="fas fa-home me-1"></i><?= __('home') ?></a></li>
            <li class="breadcrumb-item"><a href="index.php" class="text-muted"><?= __('shop') ?></a></li>
            <li class="breadcrumb-item active text-gold" aria-current="page"><i class="fas fa-star me-1"></i><?= __('new_arrivals') ?></li>
        </ol>
    </nav>

    <div class="section-header mb-4">
        <div class="section-icon bg-gold"><i class="fas fa-star"></i></div>
        <h2 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('new_arrivals') ?></h2>
        <p class="text-muted"><?= __('new_arrivals_subtitle') ?></p>
    </div>

    <?php if ($products): ?>
    <div class="row g-3">
        <?php foreach ($products as $p):
            $price = $p['discount_price'] ?: $p['price'];
            $img = $p['image'] ? SITE_URL . '/' . $p['image'] : SITE_URL . '/assets/images/logo.png';
        ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card">
                <div class="product-image-wrapper">
                    <a href="index.php?product=<?= escape($p['slug']) ?>">
                        <img src="<?= $img ?>" alt="<?= escape($p['name_en']) ?>" class="product-image" loading="lazy">
                    </a>
                    <span class="product-badge bg-gold"><?= __('new') ?></span>
                    <?php if ($p['discount_price']): ?>
                        <span class="product-badge bg-danger" style="top:30px;">-<?= round((1 - $p['discount_price']/$p['price']) * 100) ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="product-info p-2">
                    <h6 class="product-title small fw-600 mb-1"><a href="index.php?product=<?= escape($p['slug']) ?>" class="text-decoration-none text-dark"><?= escape($p['name_en']) ?></a></h6>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-700 text-gold"><?= formatMoney($price) ?></span>
                        <a href="index.php?product=<?= escape($p['slug']) ?>" class="btn btn-sm btn-outline-dark-custom"><i class="fas fa-eye"></i></a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
        <p class="text-muted"><?= __('no_new_arrivals') ?></p>
        <a href="index.php" class="btn btn-gold mt-2"><?= __('browse_products') ?></a>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>