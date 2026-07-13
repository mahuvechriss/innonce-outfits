<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Register';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirmation'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $_SESSION['error'] = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email.';
    } elseif (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $_SESSION['error'] = 'Passwords do not match.';
    } elseif (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid token.';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Email already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')");
            $stmt->execute([$name, $email, $phone, $hashed]);
            $_SESSION['success'] = 'Registration successful! Please login.';
            header('Location: login.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5" style="min-height: 70vh;">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="form-card">
                <div class="form-card-header">
                    <div class="auth-icon"><i class="fas fa-user-plus"></i></div>
                    <h3><?= __('create_account') ?></h3>
                    <p><?= __('register_subtitle') ?></p>
                </div>
                <form method="POST">
                    <?= csrf() ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user me-1"></i><?= __('full_name') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="<?= __('name_placeholder') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-envelope me-1"></i><?= __('email') ?> <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="<?= __('email_placeholder') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-phone me-1"></i><?= __('phone') ?></label>
                        <input type="tel" name="phone" class="form-control" placeholder="<?= __('phone_placeholder') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock me-1"></i><?= __('password') ?> <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="regPassword" class="form-control" placeholder="<?= __('password_min') ?>" required minlength="6">
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleRegPass('regPassword', this)" style="border-color:rgba(255,255,255,0.15);color:rgba(255,255,255,0.5);">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-check-circle me-1"></i><?= __('confirm_password') ?> <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password_confirmation" id="regPasswordConfirm" class="form-control" placeholder="<?= __('confirm_placeholder') ?>" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleRegPass('regPasswordConfirm', this)" style="border-color:rgba(255,255,255,0.15);color:rgba(255,255,255,0.5);">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-gold w-100"><i class="fas fa-user-plus me-2"></i><?= __('create_account') ?></button>
                    <div class="auth-divider"><span><?= __('or') ?></span></div>
                    <a href="https://promoted-seasnail-45.accounts.dev/sign-up?redirect_url=http%3A%2F%2Flocalhost%3A8080%2Finnonce-outfits%2Findex.php%3Fclerk_action%3Dregister" class="btn btn-outline-dark-custom w-100" style="font-size:13px;display:flex;align-items:center;justify-content:center;gap:10px;">
                        <span style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <img src="<?= SITE_URL ?>/assets/images/world.png" alt="" style="width:32px;height:32px;border-radius:50%;display:block;border:2px solid rgba(255,255,255,0.2);box-shadow:0 0 8px rgba(255,255,255,0.15);">
                        </span>
                        <?= t('Register with other platform', 'Jisajili na platform nyingine') ?>
                    </a>
                    <div class="text-center small mt-3">
                        <?= __('have_account') ?> <a href="login.php" class="text-gold fw-600"><?= __('login') ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function toggleRegPass(id, btn) {
    var inp = document.getElementById(id);
    var icon = btn.querySelector('i');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
