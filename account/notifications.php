<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/notifications.php';
requireLogin();
$pageTitle = 'Notifications';

if (isset($_GET['mark_all_read'])) {
    markAllNotificationsRead($_SESSION['user_id']);
    redirect('notifications.php', 'All marked as read.');
}

$notifs = getNotifications($_SESSION['user_id'], 100);
$unread = getUnreadNotificationCount($_SESSION['user_id']);

require_once __DIR__ . '/../includes/header.php'; ?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0 mb-0">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php" class="text-muted"><i class="fas fa-home me-1"></i><?= __('home') ?></a></li>
            <li class="breadcrumb-item"><a href="profile.php" class="text-muted"><?= __('profile') ?></a></li>
            <li class="breadcrumb-item active text-gold" aria-current="page"><i class="fas fa-bell me-1"></i><?= __('notifications') ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><i class="fas fa-bell me-2 text-gold"></i><?= __('notifications') ?></h4>
            <small class="text-muted"><?= $unread ?> unread</small>
        </div>
        <?php if ($unread > 0): ?>
        <a href="?mark_all_read=1" class="btn btn-outline-dark-custom btn-sm"><i class="fas fa-check-double me-1"></i><?= __('mark_all_read') ?></a>
        <?php endif; ?>
    </div>

    <?php if ($notifs): ?>
    <div class="list-group">
        <?php foreach ($notifs as $n): ?>
        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start <?= $n['is_read'] ? '' : 'border-warning border-2' ?>" style="cursor:pointer;" onclick="fetch('<?= SITE_URL ?>/actions/notifications.php?action=mark_read&id=<?= $n['id'] ?>');this.classList.remove('border-warning','border-2');">
            <div class="ms-2 me-auto">
                <div class="fw-600 small"><?= escape($n['title']) ?></div>
                <p class="small text-muted mb-0"><?= preg_replace('/https?:\/\/\S+/', '<a href="$0" class="text-gold" target="_blank">$0</a>', escape($n['message'])) ?></p>
                <small class="text-muted"><?= date('M d, Y H:i', strtotime($n['created_at'])) ?></small>
            </div>
            <span class="badge bg-<?= $n['type'] === 'order' ? 'warning' : 'info' ?> rounded-pill"><?= $n['type'] === 'order' ? __('notification_order') : __('notification_info') ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
        <p class="text-muted"><?= __('no_notifications') ?></p>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>