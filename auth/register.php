<?php
require_once __DIR__ . '/../config.php';
$pageTitle = t('Register', 'Jisajili');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirmation'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $_SESSION['error'] = t('Please fill in all required fields.', 'Tafadhali jaza sehemu zote zinazohitajika.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = t('Invalid email.', 'Barua pepe si sahihi.');
    } elseif (strlen($password) < 6) {
        $_SESSION['error'] = t('Password must be at least 6 characters.', 'Neno la siri lazima liwe na angalau herufi 6.');
    } elseif ($password !== $confirm) {
        $_SESSION['error'] = t('Passwords do not match.', 'Nyuzi za siri hazifanani.');
    } elseif (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = t('Invalid token.', 'Tokeni batili.');
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = t('Email already registered.', 'Barua pepe tayari imesajiliwa.');
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            require_once __DIR__ . '/../includes/beem.php';
            $phone = formatSmsPhone($phone);
            $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role, notify_sms) VALUES (?, ?, ?, ?, 'customer', 1)");
            $stmt->execute([$name, $email, $phone, $hashed]);
            $_SESSION['success'] = t('Registration successful! Please login.', 'Usajili umefanikiwa! Tafadhali ingia.');
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
                    <a href="https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=<?= GOOGLE_CLIENT_ID ?>&redirect_uri=<?= rawurlencode(GOOGLE_REDIRECT_URI) ?>&scope=openid+profile+email&state=register&prompt=select_account" class="btn w-100" style="display:flex;align-items:center;justify-content:center;gap:12px;padding:10px 16px;font-size:14px;font-weight:500;background:#fff;color:#1f1f1f;border:1px solid #dadce0;border-radius:24px;text-decoration:none;transition:background 0.2s;" onmouseover="this.style.background='#f8faff'" onmouseout="this.style.background='#fff'">
                        <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#ea4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285f4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#fbbc05" d="M10.54 28.59A14.5 14.5 0 0 1 9.5 24c0-1.59.28-3.14.76-4.59l-7.98-6.19A23.99 23.99 0 0 0 0 24c0 3.77.87 7.35 2.56 10.56l7.98-5.97z"/><path fill="#34a853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 5.97C6.51 42.62 14.62 48 24 48z"/></svg>
                        <span><?= t('Register with Google', 'Jisajili na Google') ?></span>
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
