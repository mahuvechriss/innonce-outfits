<?php
require_once __DIR__ . '/../config.php';
requireLogin();

if (!needsProfileCompletion()) {
    header('Location: ' . SITE_URL . '/account/dashboard.php');
    exit;
}

$pageTitle = t('Complete Profile', 'Kamilisha Wasifu');

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $name = trim($_POST['name'] ?? $user['name']);

    if (empty($phone)) {
        $_SESSION['error'] = t('Phone number is required.', 'Namba ya simu inahitajika.');
    } else {
        require_once __DIR__ . '/../includes/beem.php';
        if (!isValidTzPhone($phone)) {
            $_SESSION['error'] = t('Invalid phone format. Use 0712 345 678 or +255 712 345 678.', 'Fomati ya namba si sahihi. Tumia 0712 345 678 au +255 712 345 678.');
        } else {
            $phone = formatSmsPhone($phone);
            $stmt = $db->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $_SESSION['user_id']]);

            if (!empty($_SESSION['_new_oauth_user'])) {
                unset($_SESSION['_new_oauth_user']);
                unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_photo'], $_SESSION['user_align'], $_SESSION['user_phone'], $_SESSION['clerk_sid']);
                $_SESSION['success'] = t('Account created successfully! You can now login.', 'Akaunti imeundwa kwa mafanikio! Sasa unaweza kuingia.');
                header('Location: ' . SITE_URL . '/auth/login.php');
                exit;
            }

            $_SESSION['user_name'] = $name;
            $_SESSION['user_phone'] = $phone;
            $_SESSION['success'] = t('Profile completed! Welcome to INNOCE OUTFITS.', 'Wasifu umekamilika! Karibu INNOCE OUTFITS.');
            header('Location: ' . SITE_URL . '/account/dashboard.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5" style="min-height:80vh;">
    <div class="row justify-content-center align-items-center" style="min-height:70vh;">
        <div class="col-md-6 col-lg-5">
            <div class="form-card p-4" style="animation:fadeInUp .5s ease-out;">
                <div class="form-card-header">
                    <div class="auth-icon"><i class="fas fa-user-check"></i></div>
                    <h3><?= t('Complete Your Profile', 'Kamilisha Wasifu Wako') ?></h3>
                    <p><?= t('One more step! Please provide your phone number to continue.', 'Hatua moja zaidi! Tafadhali toa namba yako ya simu kuendelea.') ?></p>
                </div>
                <form method="POST">
                    <?= csrf() ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user me-1"></i><?= __('full_name') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= escape($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-envelope me-1"></i><?= __('email') ?></label>
                        <input type="email" class="form-control" value="<?= escape($user['email']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-phone me-1"></i><?= __('phone') ?> <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" class="form-control" placeholder="0712 345 678" required pattern="[\+\d\s\-]{9,15}" title="<?= t('Tanzanian phone: 0712 345 678 or +255 712 345 678', 'Namba ya simu ya Tanzania: 0712 345 678 au +255 712 345 678') ?>">
                        <small style="color:var(--text-secondary);"><?= t('Tanzanian phone number', 'Namba ya simu ya Tanzania') ?></small>
                    </div>
                    <button type="submit" class="btn btn-gold w-100"><i class="fas fa-check-circle me-2"></i><?= t('Complete Profile', 'Kamilisha Wasifu') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
