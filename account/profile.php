<?php
require_once __DIR__ . '/../config.php';
requireLogin();
$pageTitle = 'My Profile';

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect(SITE_URL . '/auth/login.php', 'Session expired. Please login again.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid token.';
    } elseif ($action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($phone !== '') {
            require_once __DIR__ . '/../includes/beem.php';
            if (!isValidTzPhone($phone)) {
                $_SESSION['error'] = 'Invalid phone format. Use 0712 345 678 or +255 712 345 678.';
                header('Location: profile.php');
                exit;
            }
        }
        $notifyEmail = isset($_POST['notify_email']) ? 1 : 0;
        $notifySms = isset($_POST['notify_sms']) ? 1 : 0;
        $notifyInapp = isset($_POST['notify_inapp']) ? 1 : 0;
        $photoPath = $user['profile_photo'];
        $photoAlign = $user['photo_align'] ?? 'center';
        if (!empty($_FILES['profile_photo']['name'])) {
            $uploaded = uploadFile($_FILES['profile_photo'], 'profiles');
            if ($uploaded) {
                $photoPath = $uploaded;
                $photoAlign = trim($_POST['photo_align'] ?? 'center');
            }
        }
        $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, profile_photo = ?, photo_align = ?, notify_email = ?, notify_sms = ?, notify_inapp = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $photoPath, $photoAlign, $notifyEmail, $notifySms, $notifyInapp, $_SESSION['user_id']]);
        $_SESSION['user_name'] = $name;
        $_SESSION['user_photo'] = $photoPath;
        $_SESSION['user_align'] = $photoAlign;
        $_SESSION['success'] = 'Profile updated.';
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password'])) {
            $_SESSION['error'] = 'Current password is wrong.';
        } elseif (strlen($new) < 6) {
            $_SESSION['error'] = 'Password must be at least 6 chars.';
        } elseif ($new !== $confirm) {
            $_SESSION['error'] = 'Passwords do not match.';
        } else {
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            $_SESSION['success'] = 'Password updated.';
        }
    }
    header('Location: profile.php');
    exit;
}

$recentOrders = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$recentOrders->execute([$_SESSION['user_id']]);
$recentOrders = $recentOrders->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0 mb-0">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php" class="text-muted"><i class="fas fa-home me-1"></i><?= __('home') ?></a></li>
            <li class="breadcrumb-item active text-gold" aria-current="page"><i class="fas fa-user me-1"></i><?= __('my_profile') ?></li>
        </ol>
    </nav>

    <div class="section-header mb-4">
        <div class="section-icon bg-gold"><i class="fas fa-user-circle"></i></div>
        <h3 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('my_profile') ?></h3>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="form-card p-4 text-center">
                <div class="profile-avatar mb-3" style="position:relative;display:inline-block;cursor:pointer;" onclick="document.getElementById('profilePhotoInput').click();" title="Click to change photo">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="<?= SITE_URL . '/' . $user['profile_photo'] ?>" alt="Photo" id="profileAvatarImg" style="width:100px;height:100px;border-radius:50%;object-fit:cover;object-position:<?= escape($user['photo_align'] ?? 'center') ?>;border:3px solid var(--gold);">
                    <?php else: ?>
                        <i class="fas fa-user-circle" id="profileAvatarPlaceholder" style="font-size: 4rem; color: var(--gold);"></i>
                        <img src="" alt="Photo" id="profileAvatarImg" style="width:100px;height:100px;border-radius:50%;object-fit:cover;object-position:center;border:3px solid var(--gold);display:none;">
                    <?php endif; ?>
                    <div style="position:absolute;bottom:0;right:0;background:var(--gold);border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;">
                        <i class="fas fa-camera" style="font-size:14px;color:#fff;"></i>
                    </div>
                </div>
                <h5 class="fw-600 mb-0"><?= escape($user['name']) ?></h5>
                <p class="text-muted small"><?= escape($user['email']) ?></p>
                <hr>
                <div class="nav flex-column nav-pills gap-1">
                    <a href="profile.php" class="nav-link active bg-gold text-white"><i class="fas fa-user me-2"></i><?= __('profile') ?></a>
                    <a href="orders.php" class="nav-link text-dark"><i class="fas fa-box me-2"></i><?= __('orders') ?></a>
                    <a href="../shop/wishlist.php" class="nav-link text-dark"><i class="fas fa-heart me-2"></i><?= __('wishlist') ?></a>
                    <a href="notifications.php" class="nav-link text-dark"><i class="fas fa-bell me-2"></i><?= __('notifications') ?></a>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-card p-4 mb-4">
                <h5 class="fw-700 mb-3"><i class="fas fa-address-card me-2 text-gold"></i><?= __('account_details') ?></h5>
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/*" style="display:none;">
                    <input type="hidden" name="photo_align" id="photoAlignInput" value="<?= escape($user['photo_align'] ?? 'center') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-user me-1"></i><?= __('name') ?></label>
                            <input type="text" name="name" class="form-control" value="<?= escape($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-envelope me-1"></i><?= __('email') ?></label>
                            <input type="email" class="form-control" value="<?= escape($user['email']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-phone me-1"></i><?= __('phone') ?></label>
                            <input type="tel" name="phone" class="form-control" value="<?= escape($user['phone'] ?? '') ?>" pattern="[\+\d\s\-]{9,15}" title="Tanzanian phone: 0712 345 678 or +255 712 345 678" placeholder="0712 345 678">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-gold mt-3"><i class="fas fa-save me-2"></i><?= __('save_changes') ?></button>
                </form>
            </div>

            <div class="form-card p-4 mb-4">
                <h5 class="fw-700 mb-3"><i class="fas fa-lock me-2 text-gold"></i><?= __('change_password') ?></h5>
                <form method="POST">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label"><?= __('current_password') ?></label>
                            <input type="password" name="current_password" class="form-control" placeholder="<?= __('current_password_placeholder') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('new_password') ?></label>
                            <input type="password" name="new_password" class="form-control" placeholder="<?= __('password_min') ?>" required minlength="6">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('confirm_password') ?></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="<?= __('confirm_placeholder') ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-dark-custom mt-3"><i class="fas fa-sync me-2"></i><?= __('update_password') ?></button>
                </form>
            </div>

            <div class="form-card p-4 mb-4">
                <h5 class="fw-700 mb-3"><i class="fas fa-bell me-2 text-gold"></i><?= __('notification_preferences') ?></h5>
                <form method="POST">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="name" value="<?= escape($user['name']) ?>">
                    <input type="hidden" name="phone" value="<?= escape($user['phone'] ?? '') ?>">
                    <div class="d-flex flex-column gap-2">
                        <div class="form-check">
                            <input type="checkbox" name="notify_inapp" class="form-check-input" id="notify_inapp" value="1" <?= ($user['notify_inapp'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notify_inapp"><?= __('notify_inapp') ?></label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="notify_email" class="form-check-input" id="notify_email" value="1" <?= ($user['notify_email'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notify_email"><?= __('notify_email_label') ?></label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="notify_sms" class="form-check-input" id="notify_sms" value="1" <?= ($user['notify_sms'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notify_sms"><?= __('notify_sms_label') ?></label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-dark-custom mt-3 btn-sm"><i class="fas fa-save me-1"></i><?= __('save_preferences') ?></button>
                </form>
            </div>

            <?php if ($recentOrders): ?>
            <div class="form-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-700 mb-0"><i class="fas fa-clock me-2 text-gold"></i><?= __('recent_orders') ?></h5>
                    <a href="orders.php" class="btn btn-outline-dark-custom btn-sm"><i class="fas fa-arrow-right me-1"></i><?= __('view_all') ?></a>
                </div>
                <?php foreach ($recentOrders as $order): ?>
                <div class="order-row">
                    <div>
                        <strong class="text-gold">#<?= escape($order['order_number']) ?></strong>
                        <br><small class="text-muted"><i class="fas fa-calendar me-1"></i><?= date('M d, Y', strtotime($order['created_at'])) ?></small>
                    </div>
                    <div class="text-end">
                        <span class="order-status order-status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                        <br><small class="fw-600"><?= formatMoney($order['total']) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Align Photo Modal -->
<div class="modal fade" id="alignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-600"><i class="fas fa-crop me-2 text-gold"></i>Position Your Photo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="small text-muted mb-3">Drag the image to position it within the circle</p>
                <div id="cropContainer" style="width:250px;height:250px;border-radius:50%;overflow:hidden;margin:0 auto;position:relative;border:3px solid var(--gold);cursor:grab;">
                    <img id="cropImage" src="" alt="Crop" style="position:absolute;top:0;left:0;max-width:none;cursor:grab;user-select:none;-webkit-user-drag:none;">
                </div>
                <div class="mt-2 d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-outline-dark-custom" onclick="resetCrop()"><i class="fas fa-undo me-1"></i>Reset</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark-custom btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-gold btn-sm" id="confirmCropBtn" onclick="confirmCrop()"><i class="fas fa-check me-1"></i>Apply & Save</button>
            </div>
        </div>
    </div>
</div>

<script>
var cropX = 0, cropY = 0, cropW = 0, cropH = 0;
var isDragging = false, dragStartX, dragStartY, dragImgX, dragImgY;

document.getElementById('profilePhotoInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(ev) {
        var img = document.getElementById('cropImage');
        img.onload = function() {
            cropW = img.naturalWidth;
            cropH = img.naturalHeight;
            var container = document.getElementById('cropContainer');
            var cw = container.offsetWidth;
            var ch = container.offsetHeight;
            var scale = Math.max(cw / cropW, ch / cropH);
            img.style.width = (cropW * scale) + 'px';
            img.style.height = (cropH * scale) + 'px';
            cropX = (cw - img.offsetWidth) / 2;
            cropY = (ch - img.offsetHeight) / 2;
            img.style.left = cropX + 'px';
            img.style.top = cropY + 'px';
        };
        img.src = ev.target.result;
        var modal = new bootstrap.Modal(document.getElementById('alignModal'));
        modal.show();
    };
    reader.readAsDataURL(file);
});

function resetCrop() {
    var img = document.getElementById('cropImage');
    var container = document.getElementById('cropContainer');
    var cw = container.offsetWidth;
    var ch = container.offsetHeight;
    var scale = Math.max(cw / cropW, ch / cropH);
    img.style.width = (cropW * scale) + 'px';
    img.style.height = (cropH * scale) + 'px';
    cropX = (cw - img.offsetWidth) / 2;
    cropY = (ch - img.offsetHeight) / 2;
    img.style.left = cropX + 'px';
    img.style.top = cropY + 'px';
}

// Drag to reposition
var cropContainer = document.getElementById('cropContainer');
cropContainer.addEventListener('mousedown', function(e) {
    var img = document.getElementById('cropImage');
    isDragging = true;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    dragImgX = parseInt(img.style.left) || 0;
    dragImgY = parseInt(img.style.top) || 0;
    cropContainer.style.cursor = 'grabbing';
    e.preventDefault();
});

document.addEventListener('mousemove', function(e) {
    if (!isDragging) return;
    var img = document.getElementById('cropImage');
    var container = document.getElementById('cropContainer');
    var dx = e.clientX - dragStartX;
    var dy = e.clientY - dragStartY;
    var nx = dragImgX + dx;
    var ny = dragImgY + dy;
    var maxX = 0;
    var maxY = 0;
    var minX = container.offsetWidth - img.offsetWidth;
    var minY = container.offsetHeight - img.offsetHeight;
    if (minX > 0) minX = 0;
    if (minY > 0) minY = 0;
    nx = Math.min(maxX, Math.max(minX, nx));
    ny = Math.min(maxY, Math.max(minY, ny));
    img.style.left = nx + 'px';
    img.style.top = ny + 'px';
    cropX = nx;
    cropY = ny;
});

document.addEventListener('mouseup', function() {
    if (isDragging) {
        isDragging = false;
        cropContainer.style.cursor = 'grab';
    }
});

function confirmCrop() {
    var img = document.getElementById('cropImage');
    var container = document.getElementById('cropContainer');
    var cw = container.offsetWidth;
    var ch = container.offsetHeight;
    var iw = img.offsetWidth;
    var ih = img.offsetHeight;

    var px = Math.round((-cropX / (iw - cw)) * 100);
    var py = Math.round((-cropY / (ih - ch)) * 100);
    if (iw <= cw) px = 50;
    if (ih <= ch) py = 50;
    px = Math.max(0, Math.min(100, px));
    py = Math.max(0, Math.min(100, py));

    var align = px + '% ' + py + '%';
    document.getElementById('photoAlignInput').value = align;

    var preview = document.getElementById('profileAvatarImg');
    var placeholder = document.getElementById('profileAvatarPlaceholder');
    if (preview) {
        preview.style.objectPosition = align;
        preview.src = img.src;
        preview.style.display = '';
    }
    if (placeholder) placeholder.style.display = 'none';

    var modal = bootstrap.Modal.getInstance(document.getElementById('alignModal'));
    modal.hide();

    document.getElementById('profileForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
