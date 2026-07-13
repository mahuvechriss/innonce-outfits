<?php
require_once __DIR__ . '/config.php';
if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/account/dashboard.php');
    exit;
}
$pageTitle = __('home');

$featured = $db->query("SELECT p.*, pi.image_path as primary_image FROM products p LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 WHERE p.featured = 1 AND p.status = 'active' ORDER BY p.created_at DESC LIMIT 8")->fetchAll();

$newArrivals = $db->query("SELECT p.*, pi.image_path as primary_image FROM products p LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 WHERE p.status = 'active' ORDER BY p.created_at DESC LIMIT 8")->fetchAll();

$categories = $db->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active') as products_count FROM categories c WHERE c.status = 1 AND c.parent_id IS NULL ORDER BY c.name_en LIMIT 6")->fetchAll();
$totalCategories = $db->query("SELECT COUNT(*) FROM categories WHERE status = 1 AND parent_id IS NULL")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container hero-content">
        <div class="row">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-3 fade-in">
                    <span class="pulse-dot"></span>
                    <span class="text-gold small fw-600 text-uppercase tracking-wide"><?= __('new_collection') ?></span>
                </div>
                <h1 class="fade-in"><?= __('dress_with_confidence') ?></h1>
                <p class="slide-up fs-5 mb-3 text-white-50" style="animation-delay: .2s;"><?= __('hero_subtitle') ?></p>
                <p class="fs-5 text-glow mb-4 slide-up" style="animation-delay: .3s;"><i class="fas fa-quote-left me-2 opacity-50"></i><?= __('hero_sw') ?><i class="fas fa-quote-right ms-2 opacity-50"></i></p>
                <div class="d-flex flex-wrap gap-3 slide-up" style="animation-delay: .4s;">
                    <a href="<?= SITE_URL ?>/shop/index.php" class="btn btn-gold btn-lg"><i class="fas fa-shopping-bag me-2"></i><?= __('shop_now') ?></a>
                    <a href="<?= SITE_URL ?>/shop/index.php?sort=newest" class="btn btn-outline-gold btn-lg"><i class="fas fa-sparkles me-2"></i><?= __('new_arrivals') ?></a>
                    <a href="https://www.google.com/maps/dir//INNOCE+OUTFITS,+One+way,+Tenth+Rd,+Dodoma" target="_blank" class="btn btn-outline-light btn-lg"><i class="fas fa-location-dot me-2"></i><?= __('visit_us') ?></a>
                </div>
                <div class="d-flex gap-4 mt-5 slide-up" style="animation-delay: .5s;">
                    <div><span class="fw-700 fs-5 text-gold">500+</span><br><small class="text-white-50"><?= __('products') ?></small></div>
                    <div><span class="fw-700 fs-5 text-gold">10K+</span><br><small class="text-white-50"><?= __('happy_customers') ?></small></div>
                    <div><span class="fw-700 fs-5 text-gold">24/7</span><br><small class="text-white-50"><?= __('support') ?></small></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($categories): ?>
<section class="py-5">
    <div class="container">
        <div class="section-header justify-content-center mb-4">
            <div class="section-icon bg-gold"><i class="fas fa-th-large"></i></div>
            <h2 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('shop_by_category') ?></h2>
        </div>
        <div class="row g-4">
            <?php $icons = ['fa-tshirt', 'fa-gem', 'fa-clock', 'fa-shoe-prints', 'fa-hand-sparkles', 'fa-gift']; ?>
            <?php foreach ($categories as $i => $cat): ?>
            <div class="col-lg-4 col-md-6">
                <a href="<?= SITE_URL ?>/shop/index.php?category=<?= escape($cat['slug']) ?>" class="text-decoration-none">
                    <div class="category-card">
                        <img src="<?= $cat['image'] ? SITE_URL . '/' . $cat['image'] : 'https://placehold.co/600x400/121212/FF8C00?text=' . urlencode($cat['name_en']) ?>" alt="<?= escape(t($cat['name_en'], $cat['name_sw'])) ?>">
                        <div class="category-overlay"></div>
                        <div class="position-absolute top-0 start-0 p-3" style="z-index:2;">
                            <div class="icon-badge"><i class="fas <?= $icons[$i % count($icons)] ?>"></i></div>
                        </div>
                        <h3 class="category-title"><?= escape(t($cat['name_en'], $cat['name_sw'])) ?></h3>
                        <span class="category-count"><i class="fas fa-box me-1"></i><?= $cat['products_count'] ?> <?= __('items') ?></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($totalCategories > 6): ?>
        <div class="text-center mt-4">
            <a href="<?= SITE_URL ?>/shop/categories.php" class="btn btn-outline-dark-custom"><i class="fas fa-th-large me-2"></i><?= __('view_all') ?> <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="section-header justify-content-center mb-4">
            <div class="section-icon bg-gold"><i class="fas fa-star"></i></div>
            <h2 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('featured_products') ?></h2>
        </div>
        <div class="row g-4">
            <?php foreach ($featured as $product): ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card product-card">
                    <div class="position-relative">
                        <a href="<?= SITE_URL ?>/shop/index.php?product=<?= escape($product['slug']) ?>">
                            <img src="<?= $product['primary_image'] ? SITE_URL . '/' . $product['primary_image'] : 'https://placehold.co/300x400/121212/FF8C00?text=INNOCE' ?>" class="card-img-top" alt="<?= escape($product['name_en']) ?>">
                        </a>
                        <?php if ($product['discount_price']): ?>
                        <span class="ribbon"><i class="fas fa-tag me-1"></i><?= round((1 - $product['discount_price'] / $product['price']) * 100) ?>% OFF</span>
                        <?php endif; ?>
                        <div class="card-badges">
                            <button class="card-badge" onclick="location.href='<?= SITE_URL ?>/shop/wishlist.php?action=add&product_id=<?= $product['id'] ?>'"><i class="far fa-heart"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <small class="text-muted text-uppercase small"><i class="fas fa-tag me-1"></i><?= escape($product['brand'] ?? 'INNOCE') ?></small>
                        <h6 class="mt-1"><a href="<?= SITE_URL ?>/shop/index.php?product=<?= escape($product['slug']) ?>" class="text-dark text-decoration-none"><?= escape(t($product['name_en'], $product['name_sw'])) ?></a></h6>
                        <div class="mb-2">
                            <?php if ($product['discount_price']): ?>
                            <span class="price-current text-gold"><?= formatMoney($product['discount_price']) ?></span>
                            <span class="price-old"><?= formatMoney($product['price']) ?></span>
                            <span class="price-saved"><i class="fas fa-check-circle me-1"></i><?= __('save') ?></span>
                            <?php else: ?>
                            <span class="price-current"><?= formatMoney($product['price']) ?></span>
                            <?php endif; ?>
                        </div>
                        <form action="<?= SITE_URL ?>/shop/cart.php" method="POST" class="mt-2">
                            <?= csrf() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <button type="submit" class="btn-gold-sm w-100"><i class="fas fa-shopping-cart me-2"></i><?= __('add_to_cart') ?></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?= SITE_URL ?>/shop/index.php" class="btn btn-dark-custom btn-lg"><i class="fas fa-arrow-right me-2"></i><?= __('view_all_products') ?></a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($newArrivals): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="section-header mb-0">
                <div class="section-icon bg-gold"><i class="fas fa-sparkles"></i></div>
                <h2 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('new_arrivals') ?></h2>
            </div>
            <a href="<?= SITE_URL ?>/shop/index.php?sort=newest" class="btn btn-outline-dark-custom btn-sm"><i class="fas fa-arrow-right me-1"></i><?= __('view_all') ?></a>
        </div>
        <div class="row g-4">
            <?php foreach ($newArrivals as $product): ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card product-card">
                    <div class="position-relative">
                        <a href="<?= SITE_URL ?>/shop/index.php?product=<?= escape($product['slug']) ?>">
                            <img src="<?= $product['primary_image'] ? SITE_URL . '/' . $product['primary_image'] : 'https://placehold.co/300x400/121212/FF8C00?text=INNOCE' ?>" class="card-img-top" alt="<?= escape(t($product['name_en'], $product['name_sw'])) ?>">
                        </a>
                        <span class="ribbon ribbon-gold"><i class="fas fa-clock me-1"></i><?= __('new') ?></span>
                        <div class="card-badges">
                            <button class="card-badge" onclick="location.href='<?= SITE_URL ?>/shop/wishlist.php?action=add&product_id=<?= $product['id'] ?>'"><i class="far fa-heart"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <small class="text-muted text-uppercase small"><i class="fas fa-tag me-1"></i><?= escape($product['brand'] ?? 'INNOCE') ?></small>
                        <h6 class="mt-1"><a href="<?= SITE_URL ?>/shop/index.php?product=<?= escape($product['slug']) ?>" class="text-dark text-decoration-none"><?= escape(t($product['name_en'], $product['name_sw'])) ?></a></h6>
                        <div class="mb-2">
                            <span class="price-current"><?= formatMoney($product['discount_price'] ?: $product['price']) ?></span>
                            <?php if ($product['discount_price']): ?>
                            <span class="price-old"><?= formatMoney($product['price']) ?></span>
                            <?php endif; ?>
                        </div>
                        <form action="<?= SITE_URL ?>/shop/cart.php" method="POST" class="mt-2">
                            <?= csrf() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <button type="submit" class="btn-gold-sm w-100"><i class="fas fa-shopping-cart me-2"></i><?= __('add_to_cart') ?></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>



<?php require_once __DIR__ . '/includes/footer.php'; ?>
