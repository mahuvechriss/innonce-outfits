<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/images/logo.png">
    <link rel="apple-touch-icon" href="<?= SITE_URL ?>/assets/images/logo.png">
    <meta name="theme-color" content="<?= $activeThemeCssVars['--orange'] ?? '#FF8C00' ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="INNOCE">
    <link rel="manifest" href="<?= SITE_URL ?>/manifest.json">
    <script>
        (function() {
            var theme = 'light';
            try { theme = localStorage.getItem('innonce-theme'); } catch(e) {}
            if (!theme) {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <title><?= escape($pageTitle ?? SITE_NAME) ?> - INNOCE OUTFITS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=2.0">
    <?= renderThemeCss() ?>
    <?= renderThemeDecorations() ?>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= SITE_URL ?>/index.php" style="font-family: 'Playfair Display', serif; font-size: 1.5rem;">
            <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="INNOCE OUTFITS" style="height: 64px; width: 64px; border-radius: 50%; vertical-align: middle;">
            <span class="text-gold ms-2">INNOCE</span> OUTFITS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/index.php"><i class="fas fa-home me-1"></i><?= __('home') ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/shop/index.php"><i class="fas fa-store me-1"></i><?= __('shop') ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/shop/categories.php"><i class="fas fa-th-large me-1"></i><?= __('categories') ?></a></li>
                <li class="nav-item"><a class="nav-link text-gold fw-600" href="<?= SITE_URL ?>/shop/new-arrivals.php"><i class="fas fa-star me-1"></i><?= __('new_arrivals') ?></a></li>
                <?php if (isLoggedIn()): ?>
                <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/account/orders.php"><i class="fas fa-box me-1"></i><?= __('orders') ?></a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/pages/contact.php"><i class="fas fa-envelope me-1"></i><?= __('contact') ?></a></li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group">
                    <a href="<?= SITE_URL ?>/actions/lang.php?lang=en" class="btn <?= currentLang() === 'en' ? 'btn-dark-custom' : 'btn-outline-dark-custom' ?>"><i class="fas fa-globe me-1"></i>EN</a>
                    <a href="<?= SITE_URL ?>/actions/lang.php?lang=sw" class="btn <?= currentLang() === 'sw' ? 'btn-dark-custom' : 'btn-outline-dark-custom' ?>">SW</a>
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle dark/light mode" onclick="toggleTheme()">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="<?= SITE_URL ?>/shop/search.php" class="btn btn-link text-dark p-1 nav-icon-link" title="<?= __('search') ?>"><i class="fas fa-search"></i></a>
                <?php if (isLoggedIn()):
                if (empty($_SESSION['user_photo'])) {
                    $stmt = $db->prepare("SELECT profile_photo, photo_align FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $u = $stmt->fetch();
                    if ($u) {
                        $_SESSION['user_photo'] = $u['profile_photo'];
                        $_SESSION['user_align'] = $u['photo_align'] ?? 'center';
                    }
                }
                ?>
                <div class="dropdown d-inline-block">
                    <button class="btn btn-link text-dark p-0 nav-icon-link position-relative" data-bs-toggle="dropdown" title="<?= escape($_SESSION['user_name'] ?? '') ?>" style="text-decoration:none;">
                        <?php if (!empty($_SESSION['user_photo'])): ?>
                            <img src="<?= SITE_URL . '/' . $_SESSION['user_photo'] ?>" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;object-position:<?= escape($_SESSION['user_align'] ?? 'center') ?>;border:2px solid var(--gold);vertical-align:middle;">
                        <?php else: ?>
                            <i class="fas fa-user-circle" style="font-size:28px;color:var(--gold);vertical-align:middle;"></i>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li class="dropdown-header text-center small text-muted border-bottom pb-2"><?= escape($_SESSION['user_name'] ?? '') ?><br><?= escape($_SESSION['user_email'] ?? '') ?></li>
                        <li><a class="dropdown-item" href="<?= SITE_URL ?>/account/dashboard.php"><i class="fas fa-home me-2 text-gold"></i><?= __('dashboard') ?></a></li>
                        <li><a class="dropdown-item" href="<?= SITE_URL ?>/account/profile.php"><i class="fas fa-user me-2 text-gold"></i><?= __('profile') ?></a></li>
                        <li><a class="dropdown-item" href="<?= SITE_URL ?>/account/orders.php"><i class="fas fa-box me-2 text-gold"></i><?= __('orders') ?></a></li>
                        <li><a class="dropdown-item" href="<?= SITE_URL ?>/shop/wishlist.php"><i class="fas fa-heart me-2 text-gold"></i><?= __('wishlist') ?></a></li>
                        <li><a class="dropdown-item" href="<?= SITE_URL ?>/shop/cart.php"><i class="fas fa-shopping-cart me-2 text-gold"></i><?= __('cart') ?> <span class="badge bg-gold rounded-pill" id="cartBadge"><?= cartCount() ?></span></a></li>
                        <li><a class="dropdown-item" href="<?= SITE_URL ?>/account/notifications.php"><i class="fas fa-bell me-2 text-gold"></i><?= __('notifications') ?> <span class="badge bg-danger rounded-pill" id="notifCount" style="display:none;">0</span></a></li>
                        <?php if (isAdmin()): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-gold fw-600" href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-cog me-2"></i><?= __('admin_panel') ?></a></li>
                        <?php endif; ?>
                        <?php if (isWorker()): ?>
                        <li><a class="dropdown-item" href="<?= SITE_URL ?>/worker/orders.php"><i class="fas fa-clipboard-list me-2 text-gold"></i><?= __('worker_orders') ?></a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= SITE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i><?= __('logout') ?></a></li>
                    </ul>
                </div>
                <?php else: ?>
                <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-outline-dark-custom btn-sm"><i class="fas fa-sign-in-alt me-1"></i><?= __('login') ?></a>
                <a href="<?= SITE_URL ?>/auth/register.php" class="btn-gold-sm"><i class="fas fa-user-plus me-1"></i><?= __('register') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main>
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 mb-0 rounded-0">
        <div class="container"><?= escape($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php unset($_SESSION['success']); endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 mb-0 rounded-0">
        <div class="container"><?= escape($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php unset($_SESSION['error']); endif; ?>
<?php if (isset($_SESSION['info'])): ?>
    <div class="alert alert-info alert-dismissible fade show border-0 mb-0 rounded-0">
        <div class="container"><?= escape($_SESSION['info']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php unset($_SESSION['info']); endif; ?>
