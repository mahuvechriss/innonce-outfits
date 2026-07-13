<?php
// Sidebar counts for badges
$pendingOrders     = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$unreadMessages    = $db->query("SELECT COUNT(*) FROM contacts WHERE is_read = 0")->fetchColumn();
$pendingReviews    = $db->query("SELECT COUNT(*) FROM product_reviews WHERE status = 'pending'")->fetchColumn();
$draftProducts     = $db->query("SELECT COUNT(*) FROM products WHERE status = 'draft'")->fetchColumn();
$onlineCustomers   = $db->query("SELECT COUNT(*) FROM users WHERE role='customer' AND last_activity >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/lang.php';
$unreadNotifs = getUnreadNotificationCount($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= SITE_URL ?>/assets/images/logo.png">
    <title><?= escape($pageTitle ?? 'Admin') ?> - INNOCE Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body class="bg-light admin-page">
<nav class="navbar navbar-expand-lg bg-dark navbar-dark">
    <div class="container">
        <button class="btn btn-outline-light btn-sm me-2" id="sidebarToggle" title="Toggle sidebar" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand fw-bold" href="index.php"><span style="color: var(--orange, #FF8C00);">INNOCE</span> Admin</a>
        <div class="d-flex align-items-center gap-3 ms-auto">
            <a href="lang_switch.php?lang=<?= $admin_lang === 'sw' ? 'en' : 'sw' ?>&redirect=<?= urlencode($_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']) ?>" class="btn btn-outline-light btn-sm fw-bold" style="font-size:13px;letter-spacing:0.5px">
                <i class="fas fa-language"></i> <?= $admin_lang === 'sw' ? 'EN' : 'SW' ?>
            </a>
            <button class="theme-toggle" id="themeToggle" title="Toggle dark/light mode" onclick="toggleTheme()">
                <i class="fas fa-moon"></i>
            </button>
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm position-relative" data-bs-toggle="dropdown" id="adminNotifBell">
                    <i class="fas fa-bell"></i>
                    <span class="badge bg-danger rounded-pill" id="adminNotifCount" style="font-size:8px;display:none;">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow" style="width:300px;max-height:350px;overflow-y:auto;" id="adminNotifDropdown">
                    <div class="px-3 py-2 border-bottom"><strong class="small">Notifications</strong></div>
                    <div id="adminNotifList" class="py-1 text-center text-muted small py-3">Loading...</div>
                </div>
            </div>
            <a href="<?= SITE_URL ?>/index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-external-link-alt"></i> <?= __t('view_site') ?></a>
            <a href="<?= SITE_URL ?>/auth/logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> <?= __t('logout') ?></a>
        </div>
    </div>
</nav>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 bg-dark p-3 admin-sidebar" style="min-height: calc(100vh - 56px);">
            <nav class="nav flex-column position-relative">
                <a class="nav-link text-white-50 <?= $action === 'dashboard' ? 'text-white fw-600' : '' ?>" href="index.php"><i class="fas fa-tachometer-alt me-2"></i><?= __t('dashboard') ?></a>
                <a class="nav-link text-white-50 <?= $action === 'products' ? 'text-white fw-600' : '' ?>" href="index.php?action=products"><i class="fas fa-box me-2"></i><?= __t('products') ?><?php if ($draftProducts): ?><span class="badge bg-warning text-dark ms-auto"><?= $draftProducts ?></span><?php endif; ?></a>
                <a class="nav-link text-white-50 <?= $action === 'categories' ? 'text-white fw-600' : '' ?>" href="index.php?action=categories"><i class="fas fa-tags me-2"></i><?= __t('categories') ?></a>
                <a class="nav-link text-white-50 <?= $action === 'orders' ? 'text-white fw-600' : '' ?>" href="index.php?action=orders"><i class="fas fa-shopping-cart me-2"></i><?= __t('orders') ?><?php if ($pendingOrders): ?><span class="badge bg-warning text-dark ms-auto"><?= $pendingOrders ?></span><?php endif; ?></a>
                <a class="nav-link text-white-50 <?= $action === 'coupons' ? 'text-white fw-600' : '' ?>" href="index.php?action=coupons"><i class="fas fa-percent me-2"></i><?= __t('coupons') ?></a>
                <a class="nav-link text-white-50 <?= $action === 'reviews' ? 'text-white fw-600' : '' ?>" href="index.php?action=reviews"><i class="fas fa-star me-2"></i><?= __t('reviews') ?><?php if ($pendingReviews): ?><span class="badge bg-warning text-dark ms-auto"><?= $pendingReviews ?></span><?php endif; ?></a>
                <a class="nav-link text-white-50 <?= $action === 'contacts' ? 'text-white fw-600' : '' ?>" href="index.php?action=contacts"><i class="fas fa-envelope me-2"></i><?= __t('messages') ?><?php if ($unreadMessages): ?><span class="badge bg-danger ms-auto"><?= $unreadMessages ?></span><?php endif; ?></a>
                <a class="nav-link text-white-50 <?= $action === 'customers' ? 'text-white fw-600' : '' ?>" href="index.php?action=customers"><i class="fas fa-users me-2"></i><?= __t('customers') ?><?php if ($onlineCustomers): ?><span class="badge bg-success ms-auto"><?= $onlineCustomers ?> <?= __t('online') ?></span><?php endif; ?></a>
                <a class="nav-link text-white-50 <?= $action === 'reports' ? 'text-white fw-600' : '' ?>" href="index.php?action=reports"><i class="fas fa-chart-bar me-2"></i><?= __t('reports') ?></a>
                <a class="nav-link text-white-50 <?= $action === 'notifications' ? 'text-white fw-600' : '' ?>" href="index.php?action=notifications"><i class="fas fa-bell me-2"></i><?= __t('notifications') ?><?php if ($unreadNotifs): ?><span class="badge bg-danger ms-auto"><?= $unreadNotifs ?></span><?php endif; ?></a>
                <a class="nav-link text-white-50 <?= $action === 'broadcast' ? 'text-white fw-600' : '' ?>" href="index.php?action=broadcast"><i class="fas fa-bullhorn me-2"></i><?= __t('broadcast') ?></a>
                <a class="nav-link text-white-50 <?= $action === 'settings' ? 'text-white fw-600' : '' ?>" href="index.php?action=settings"><i class="fas fa-cog me-2"></i><?= __t('settings') ?></a>
                <hr class="border-secondary my-2">
                <a class="nav-link text-white-50 <?= $action === 'profile' ? 'text-white fw-600' : '' ?>" href="index.php?action=profile"><i class="fas fa-user me-2"></i><?= __t('profile') ?></a>
            </nav>
        </div>
        <div class="col-md-10 p-4 admin-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= escape($_SESSION['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['success']); endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= escape($_SESSION['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['error']); endif; ?>
