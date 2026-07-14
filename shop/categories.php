<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Categories';
$cats = $db->query("SELECT c.*,
    (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active') as products_count,
    (SELECT pi.image_path FROM products p JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 WHERE p.category_id = c.id AND p.status = 'active' ORDER BY p.price DESC LIMIT 1) as product_image
    FROM categories c WHERE c.status = 1 ORDER BY c.name_en")->fetchAll();
require_once __DIR__ . '/../includes/header.php'; ?>
<div class="container py-5">
    <h3 class="fw-600 text-center mb-4" style="font-family: 'Playfair Display', serif;">Shop by Category</h3>
    <div class="row g-4">
        <?php foreach ($cats as $cat): ?>
        <div class="col-lg-4 col-md-6">
            <a href="index.php?category=<?= escape($cat['slug']) ?>" class="text-decoration-none">
                <div class="category-card">
                    <?php $catImg = $cat['image'] ? SITE_URL . '/' . $cat['image'] : ($cat['product_image'] ? SITE_URL . '/' . $cat['product_image'] : 'https://placehold.co/600x400/121212/FF8C00?text=' . urlencode(t($cat['name_en'], $cat['name_sw']))); ?>
                    <img src="<?= $catImg ?>" alt="<?= escape(t($cat['name_en'], $cat['name_sw'])) ?>">
                    <div class="category-overlay"></div>
                    <h3 class="category-title"><?= escape(t($cat['name_en'], $cat['name_sw'])) ?></h3>
                    <span class="category-count"><?= $cat['products_count'] ?> Items</span>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
