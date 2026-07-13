<?php
require_once __DIR__ . '/../config.php';

$categorySlug = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$productSlug = $_GET['product'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

$isNewArrivals = isset($_GET['sort']) && $_GET['sort'] === 'newest' && !$categorySlug && !$search;
$pageTitle = $isNewArrivals ? 'New Arrivals' : 'Shop';

// Single product view
if ($productSlug) {
    $stmt = $db->prepare("SELECT p.*, c.name_en as category_name, c.slug as category_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = ? AND p.status = 'active'");
    $stmt->execute([$productSlug]);
    $product = $stmt->fetch();
    if (!$product) { header('Location: index.php'); exit; }

    $images = $db->prepare("SELECT * FROM product_images WHERE product_id = ?");
    $images->execute([$product['id']]);
    $images = $images->fetchAll();

    $reviews = $db->prepare("SELECT pr.*, u.name as user_name FROM product_reviews pr JOIN users u ON pr.user_id = u.id WHERE pr.product_id = ? AND pr.status = 'approved' ORDER BY pr.created_at DESC");
    $reviews->execute([$product['id']]);
    $reviews = $reviews->fetchAll();

    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="container py-5">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb bg-transparent p-0 mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php" class="text-muted"><i class="fas fa-home me-1"></i><?= __('home') ?></a></li>
                <li class="breadcrumb-item"><a href="index.php" class="text-muted"><i class="fas fa-store me-1"></i><?= __('shop') ?></a></li>
                <?php if ($product['category_name']): ?>
                <li class="breadcrumb-item"><a href="index.php?category=<?= escape($product['category_slug']) ?>" class="text-muted"><i class="fas fa-th-large me-1"></i><?= escape($product['category_name']) ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active text-gold" aria-current="page"><?= escape($product['name_en']) ?></li>
            </ol>
        </nav>
        <div class="row g-5">
            <div class="col-md-6">
                <div class="position-relative">
                    <?php $primary = array_filter($images, fn($i) => $i['is_primary']); $primary = reset($primary) ?: ($images[0] ?? null); ?>
                    <img src="<?= $primary && !empty($primary['image_path']) ? SITE_URL . '/' . $primary['image_path'] : 'https://placehold.co/500x600/121212/FF8C00?text=' . urlencode($product['name_en']) ?>" class="w-100 rounded-3" style="height: 450px; object-fit: cover;" alt="<?= escape($product['name_en']) ?>" id="mainImage">
                    <?php if ($product['discount_price']): ?>
                    <span class="ribbon"><i class="fas fa-tag me-1"></i><?= round((1 - $product['discount_price'] / $product['price']) * 100) ?>% OFF</span>
                    <?php endif; ?>
                </div>
                <?php if (count($images) > 1): ?>
                <div class="d-flex gap-2 mt-2">
                    <?php foreach ($images as $img): ?>
                    <img src="<?= SITE_URL . '/' . $img['image_path'] ?>" class="border rounded-2" style="width: 70px; height: 80px; object-fit: cover; cursor: pointer; opacity: 0.7; transition: opacity 0.3s;" onmouseover="this.style.opacity='1';document.getElementById('mainImage').src = this.src" onmouseout="this.style.opacity='0.7'">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <span class="badge bg-dark text-uppercase small mb-2"><i class="fas fa-tag me-1"></i><?= escape($product['brand'] ?? 'INNOCE') ?></span>
                <h3 class="fw-700" style="font-family: 'Playfair Display', serif;"><?= escape($product['name_en']) ?></h3>
                <p class="text-muted small"><i class="fas fa-language me-1"></i><?= escape($product['name_sw']) ?></p>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="fs-4 fw-700">
                        <?php if ($product['discount_price']): ?>
                        <span class="text-gold"><?= formatMoney($product['discount_price']) ?></span>
                        <span class="text-muted text-decoration-line-through ms-2 fs-6"><?= formatMoney($product['price']) ?></span>
                        <?php else: ?>
                        <span><?= formatMoney($product['price']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($product['quantity'] > 0): ?>
                    <span class="badge bg-success small"><i class="fas fa-check-circle me-1"></i><?= __('in_stock') ?></span>
                    <?php else: ?>
                    <span class="badge bg-danger small"><i class="fas fa-times-circle me-1"></i><?= __('out_of_stock') ?></span>
                    <?php endif; ?>
                </div>
                <p class="text-muted"><?= nl2br(escape($product['description_en'])) ?></p>
                <form action="<?= SITE_URL ?>/shop/cart.php" method="POST" class="mt-4">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <?php $sizes = json_decode($product['sizes'] ?? '[]', true); if ($sizes): ?>
                    <div class="mb-3">
                        <label class="form-label fw-600"><i class="fas fa-ruler me-1"></i><?= __('size') ?></label>
                        <div class="d-flex gap-2 mt-1">
                            <?php foreach ($sizes as $size): ?>
                            <label class="size-selector">
                                <input type="radio" name="size" value="<?= escape($size) ?>" class="d-none">
                                <span class="size-option"><?= escape($size) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php $colors = json_decode($product['colors'] ?? '[]', true); if ($colors): ?>
                    <div class="mb-3">
                        <label class="form-label fw-600"><i class="fas fa-palette me-1"></i><?= __('color') ?></label>
                        <div class="d-flex gap-2 mt-1">
                            <?php foreach ($colors as $col): $cNames = colorNames(); $cName = $cNames[$col] ?? ['en' => $col, 'sw' => $col]; ?>
                            <label class="size-selector">
                                <input type="radio" name="color" value="<?= escape($col) ?>" class="d-none">
                                <span class="size-option"><?= escape(t($cName['en'], $cName['sw'])) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="qty-selector">
                            <button type="button" class="qty-btn" onclick="var q=this.parentNode.querySelector('input');if(parseInt(q.value)>1)q.value--"><i class="fas fa-minus"></i></button>
                            <input type="number" name="quantity" value="1" min="1" max="<?= $product['quantity'] ?>" class="qty-input" readonly>
                            <button type="button" class="qty-btn" onclick="var q=this.parentNode.querySelector('input');if(parseInt(q.value)<<?= $product['quantity'] ?>)q.value++"><i class="fas fa-plus"></i></button>
                        </div>
                        <button type="submit" class="btn btn-gold px-4 flex-grow-1"><i class="fas fa-shopping-cart me-2"></i><?= __('add_to_cart') ?></button>
                        <a href="<?= SITE_URL ?>/shop/wishlist.php?action=add&product_id=<?= $product['id'] ?>" class="btn btn-outline-dark-custom" title="<?= __('add_to_wishlist') ?>"><i class="far fa-heart"></i></a>
                    </div>
                </form>
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex gap-4 small text-muted">
                        <span><i class="fas fa-truck me-1"></i><?= __('free_shipping') ?> <?= formatMoney(getSetting('free_shipping_min', FREE_SHIPPING_MIN)) ?></span>
                        <span><i class="fas fa-undo me-1"></i><?= __('easy_returns') ?></span>
                        <span><i class="fas fa-shield-alt me-1"></i><?= __('secure_payment') ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($reviews): ?>
        <div class="mt-5">
            <div class="section-header">
                <div class="section-icon bg-gold"><i class="fas fa-star"></i></div>
                <h5 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('reviews') ?> (<?= count($reviews) ?>)</h5>
            </div>
            <?php $avgRating = round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1); ?>
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="stars-display"><?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star<?= $i <= $avgRating ? ' text-gold' : ' text-muted' ?>"></i><?php endfor; ?></div>
                <span class="fw-600"><?= $avgRating ?></span>
                <span class="text-muted">(<?= count($reviews) ?> <?= __('reviews') ?>)</span>
            </div>
            <?php foreach ($reviews as $rv): ?>
            <div class="review-card">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div class="review-avatar"><?= strtoupper(substr($rv['user_name'], 0, 1)) ?></div>
                    <div>
                        <strong class="small"><?= escape($rv['user_name']) ?></strong>
                        <div class="stars-display"><?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star<?= $i <= $rv['rating'] ? ' text-gold' : ' text-muted' ?>" style="font-size:12px;"></i><?php endfor; ?></div>
                    </div>
                    <small class="text-muted ms-auto"><?= date('M d, Y', strtotime($rv['created_at'])) ?></small>
                </div>
                <p class="mb-0 small"><?= nl2br(escape($rv['review'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (isLoggedIn()): ?>
        <div class="mt-5 form-card">
            <h5 class="fw-600 mb-3"><i class="fas fa-pen me-2 text-gold"></i><?= __('write_review') ?></h5>
            <form action="<?= SITE_URL ?>/actions/review.php" method="POST">
                <?= csrf() ?>
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <div class="mb-3">
                    <label class="form-label fw-600"><?= __('rating') ?></label>
                    <select name="rating" class="form-select" style="width:auto;" required>
                        <?php for($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= $i ?> <?= __('star') ?><?= $i > 1 ? 's' : '' ?></option><?php endfor; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600"><?= __('your_review') ?></label>
                    <textarea name="review" class="form-control" rows="4" placeholder="<?= __('write_review_placeholder') ?>" required></textarea>
                </div>
                <button type="submit" class="btn-gold-sm"><i class="fas fa-paper-plane me-2"></i><?= __('submit_review') ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Build query for product listing
$where = ["p.status = 'active'"];
$params = [];

if ($categorySlug) {
    $where[] = "c.slug = ?";
    $params[] = $categorySlug;
}
if ($search) {
    $colorWhere = ["p.name_en LIKE ?", "p.name_sw LIKE ?", "p.brand LIKE ?"];
    $colorParams = ["%$search%", "%$search%", "%$search%"];
    $matchedColors = expandSearchWithColors($search);
    foreach ($matchedColors as $c) {
        $colorWhere[] = "p.colors LIKE ?";
        $colorParams[] = '%"' . $c . '"%';
    }
    $where[] = '(' . implode(' OR ', $colorWhere) . ')';
    $params = array_merge($params, $colorParams);
}
if ($minPrice) { $where[] = "p.price >= ?"; $params[] = $minPrice; }
if ($maxPrice) { $where[] = "p.price <= ?"; $params[] = $maxPrice; }
if ($isNewArrivals) {
    $where[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$orderBy = match($sort) {
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc' => 'p.name_en ASC',
    'name_desc' => 'p.name_en DESC',
    default => 'p.created_at DESC',
};

$whereClause = implode(' AND ', $where);
$countStmt = $db->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $whereClause");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / ITEMS_PER_PAGE);

$stmt = $db->prepare("SELECT p.*, pi.image_path as primary_image, c.name_en as category_name, c.slug as category_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 WHERE $whereClause ORDER BY $orderBy LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [ITEMS_PER_PAGE, $offset]));
$products = $stmt->fetchAll();

$cats = $db->query("SELECT * FROM categories WHERE status = 1 ORDER BY name_en")->fetchAll();

$categoryName = '';
if ($categorySlug) {
    $stmt = $db->prepare("SELECT name_en, name_sw FROM categories WHERE slug = ?");
    $stmt->execute([$categorySlug]);
    $catRow = $stmt->fetch();
    if ($catRow) {
        $categoryName = t($catRow['name_en'], $catRow['name_sw']);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <?php if ($isNewArrivals): ?>
    <div class="text-center mb-5">
        <div class="section-header justify-content-center mb-3">
            <div class="section-icon bg-gold"><i class="fas fa-sparkles"></i></div>
            <h2 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('new_arrivals') ?></h2>
        </div>
        <p class="text-muted"><i class="fas fa-clock me-2"></i><?= __('new_arrivals_subtitle') ?></p>
    </div>
    <?php endif; ?>
    <div class="row g-4">
        <!-- Filter toggle for mobile -->
        <div class="col-12 d-lg-none mb-2">
            <button class="btn btn-dark-custom w-100" type="button" onclick="document.querySelector('.filter-sidebar').classList.toggle('show')">
                <i class="fas fa-sliders-h me-2"></i><?= __('filters') ?> <i class="fas fa-chevron-down ms-1"></i>
            </button>
        </div>
        <div class="col-lg-3">
            <div class="filter-sidebar">
                <div class="form-card p-4 sticky-sidebar">
                    <form method="GET" action="index.php" id="filterForm">
                        <div class="position-relative">
                            <label class="form-label fw-600"><i class="fas fa-search me-1"></i><?= __('search_products') ?></label>
                            <input type="text" name="search" class="form-control search-trigger" placeholder="<?= __('search_placeholder') ?>" value="<?= escape($search) ?>" oninput="filterCategoryList(this)" onfocus="openFilterPanel()" onkeydown="if(event.key==='Enter'){document.getElementById('filterForm').submit()}" autocomplete="off">
                        </div>
                        <div class="filter-panel" id="filterPanel">
                            <div class="mb-3 pt-3">
                                <label class="form-label fw-600"><i class="fas fa-th-large me-1"></i><?= __('category') ?></label>
                                <div class="category-list" id="categoryList">
                                    <a href="index.php<?= $search ? '?search=' . urlencode($search) : '' ?>" class="category-list-item<?= !$categorySlug ? ' active' : '' ?>" data-slug="">
                                        <i class="fas fa-th-list"></i>
                                        <span><?= __('all_categories') ?></span>
                                    </a>
                                    <?php foreach ($cats as $cat): ?>
                                    <a href="index.php?category=<?= escape($cat['slug']) ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="category-list-item<?= $categorySlug === $cat['slug'] ? ' active' : '' ?>" data-slug="<?= escape($cat['slug']) ?>">
                                        <i class="fas fa-tag"></i>
                                        <span><?= escape(t($cat['name_en'], $cat['name_sw'])) ?></span>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-600"><i class="fas fa-dollar-sign me-1"></i><?= __('price_range') ?></label>
                                <div class="d-flex gap-2 mt-1">
                                    <input type="number" name="min_price" class="form-control" placeholder="<?= __('min') ?>" value="<?= escape($minPrice) ?>">
                                    <input type="number" name="max_price" class="form-control" placeholder="<?= __('max') ?>" value="<?= escape($maxPrice) ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-dark-custom w-100"><i class="fas fa-search me-2"></i><?= __('apply_filters') ?></button>
                            <a href="index.php" class="btn btn-outline-dark-custom w-100 mt-2"><i class="fas fa-undo me-2"></i><?= __('clear') ?></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-white rounded-3 shadow-sm flex-wrap gap-2">
                <?php if ($categoryName): ?><span class="fw-700 fs-4 me-4"><i class="fas fa-th-large me-1 text-gold"></i><?= escape($categoryName) ?></span><?php endif; ?>
                <span class="fs-6"><i class="fas fa-box me-1 text-gold"></i><?= $total ?> <?= __('products_found') ?></span>
                <form method="GET" class="d-flex gap-2">
                    <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= escape($categorySlug) ?>"><?php endif; ?>
                    <?php if ($search): ?><input type="hidden" name="search" value="<?= escape($search) ?>"><?php endif; ?>
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-600"><?= __('sort_by') ?>:</span>
                        <div class="input-group" style="min-width:200px;">
                            <span class="input-group-text bg-gold border-gold text-dark fw-600"><i class="fas fa-sort"></i></span>
                            <select name="sort" class="form-select border-gold" onchange="this.form.submit()">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= __('newest') ?></option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>><?= __('price_low_high') ?></option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= __('price_high_low') ?></option>
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>><?= __('name_az') ?></option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <?php if ($products): ?>
            <div class="row g-4">
                <?php foreach ($products as $product): ?>
                <div class="col-xl-4 col-lg-4 col-md-6">
                    <div class="card product-card">
                        <div class="position-relative">
                            <a href="index.php?product=<?= escape($product['slug']) ?>">
                                <img src="<?= $product['primary_image'] ? SITE_URL . '/' . $product['primary_image'] : 'https://placehold.co/300x400/121212/FF8C00?text=INNOCE' ?>" alt="<?= escape($product['name_en']) ?>">
                            </a>
                            <?php if ($product['discount_price']): ?>
                            <span class="ribbon"><i class="fas fa-tag me-1"></i><?= round((1 - $product['discount_price'] / $product['price']) * 100) ?>% OFF</span>
                            <?php endif; ?>
                            <div class="card-badges">
                                <button class="card-badge" onclick="location.href='wishlist.php?action=add&product_id=<?= $product['id'] ?>'"><i class="far fa-heart"></i></button>
                            </div>
                        </div>
                        <div class="card-body">
                            <small class="text-muted text-uppercase small"><i class="fas fa-tag me-1"></i><?= escape($product['brand'] ?? 'INNOCE') ?></small>
                            <h6 class="mt-1"><a href="index.php?product=<?= escape($product['slug']) ?>" class="text-dark text-decoration-none"><?= escape($product['name_en']) ?></a></h6>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?php if ($product['discount_price']): ?>
                                <span class="price-current text-gold"><?= formatMoney($product['discount_price']) ?></span>
                                <span class="price-old"><?= formatMoney($product['price']) ?></span>
                                <?php else: ?>
                                <span class="price-current"><?= formatMoney($product['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <form action="cart.php" method="POST" class="mt-2">
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
            <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="index.php?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php else: ?>
            <div class="empty-state py-5">
                <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                <h5><?= __('no_products_found') ?></h5>
                <p class="text-muted"><?= __('try_different_filters') ?></p>
                <a href="index.php" class="btn-gold-sm"><i class="fas fa-undo me-2"></i><?= __('clear_filters') ?></a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
