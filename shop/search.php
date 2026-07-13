<?php
require_once __DIR__ . '/../config.php';

$q = $_GET['q'] ?? '';
if ($q) {
    $colorWhere = ["p.name_en LIKE ?", "p.name_sw LIKE ?", "p.brand LIKE ?"];
    $colorParams = ["%$q%", "%$q%", "%$q%"];
    foreach (expandSearchWithColors($q) as $c) {
        $colorWhere[] = "p.colors LIKE ?";
        $colorParams[] = '%"' . $c . '"%';
    }
    $stmt = $db->prepare("SELECT p.id, p.name_en, p.name_sw, p.slug, p.price, p.discount_price, pi.image_path as primary_image FROM products p LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 WHERE p.status = 'active' AND (" . implode(' OR ', $colorWhere) . ") LIMIT 10");
    $stmt->execute($colorParams);
    $results = $stmt->fetchAll();
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJson($results);
    }
    $pageTitle = 'Search: ' . $q;
    require_once __DIR__ . '/../includes/header.php'; ?>
    <div class="container py-5">
        <h4 class="fw-600 mb-4">Search results for "<?= escape($q) ?>"</h4>
        <?php if ($results): ?>
        <div class="row g-4">
            <?php foreach ($results as $p): ?>
            <div class="col-md-3">
                <div class="card product-card">
                    <a href="index.php?product=<?= escape($p['slug']) ?>">
                        <img src="<?= $p['primary_image'] ? SITE_URL . '/' . $p['primary_image'] : 'https://placehold.co/300x400/121212/FF8C00?text=N' ?>" alt="<?= escape($p['name_en']) ?>">
                    </a>
                    <div class="card-body">
                        <h6><a href="index.php?product=<?= escape($p['slug']) ?>" class="text-dark text-decoration-none"><?= escape($p['name_en']) ?></a></h6>
                        <div class="fw-600"><?= formatMoney($p['discount_price'] ?: $p['price']) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted">No products found.</p>
        <?php endif; ?>
    </div>
    <?php require_once __DIR__ . '/../includes/footer.php';
} else {
    header('Location: index.php');
    exit;
} ?>
