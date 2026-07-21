<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Login';

if (isset($_GET['exit_account'])) {
    session_destroy();
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Please fill in all fields.';
    } elseif (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid token.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_phone'] = $user['phone'] ?? '';
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_photo'] = $user['profile_photo'];
            $_SESSION['user_align'] = $user['photo_align'] ?? 'center';
            $_SESSION['success'] = 'Welcome back, ' . $user['name'] . '!';
            header('Location: ' . SITE_URL . '/index.php');
            exit;
        } else {
            $_SESSION['error'] = 'Invalid email or password.';
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
                    <div class="auth-icon"><i class="fas fa-user-circle"></i></div>
                    <h3><?= __('welcome_back') ?></h3>
                    <p><?= __('login_subtitle') ?></p>
                </div>
                <?php if (isset($_GET['email_exists'])): ?>
                    <div style="padding:18px;margin-bottom:18px;border-radius:12px;background:rgba(255,152,0,0.12);border:1px solid rgba(255,152,0,0.25);color:#ffa726;text-align:center;font-size:14px;">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= t('An account with this email already exists. Please login below.', 'Akaunti iliyo na barua pepe hii tayari ipo. Tafadhali ingia hapa chini.') ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['account_created'])): ?>
                    <div style="padding:18px;margin-bottom:18px;border-radius:12px;background:rgba(46,204,113,0.12);border:1px solid rgba(46,204,113,0.25);color:#2ecc71;text-align:center;font-size:14px;">
                        <i class="fas fa-check-circle me-2"></i><?= t('Account created successfully! Welcome.', 'Akaunti imeundwa kwa mafanikio! Karibu.') ?>
                    </div>
                    <a href="<?= SITE_URL ?>/index.php" class="btn btn-gold w-100 mb-2"><i class="fas fa-home me-2"></i><?= t('Continue to Home', 'Endelea Nyumbani') ?></a>
                    <a href="?account_created=1&exit_account=1" class="btn btn-outline-danger w-100" style="border-color:rgba(255,255,255,0.08);color:rgba(255,255,255,0.4);font-size:13px;"><i class="fas fa-sign-out-alt me-2"></i><?= t('Exit (not now)', 'Toka (sio sasa)') ?></a>
                <?php else: ?>
                <form method="POST">
                    <?= csrf() ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-envelope me-1"></i><?= __('email') ?></label>
                        <input type="email" name="email" class="form-control" placeholder="<?= __('email_placeholder') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock me-1"></i><?= __('password') ?></label>
                        <div class="input-group">
                            <input type="password" name="password" id="loginPassword" class="form-control" placeholder="<?= __('password_placeholder') ?>" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('loginPassword', this)" style="border-color:rgba(255,255,255,0.15);color:rgba(255,255,255,0.5);">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <label class="form-check">
                            <input type="checkbox" class="form-check-input">
                            <span class="form-check-label small"><?= __('remember_me') ?></span>
                        </label>
                        <a href="forgot-password.php" class="small text-gold"><?= __('forgot_password') ?></a>
                    </div>
                    <button type="submit" class="btn btn-gold w-100"><i class="fas fa-sign-in-alt me-2"></i><?= __('login') ?></button>

                    <div class="auth-divider"><span><?= __('or') ?></span></div>
                    <a href="https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=<?= GOOGLE_CLIENT_ID ?>&redirect_uri=<?= rawurlencode(GOOGLE_REDIRECT_URI) ?>&scope=openid+profile+email&state=login&prompt=select_account" class="btn w-100" style="display:flex;align-items:center;justify-content:center;gap:12px;padding:10px 16px;font-size:14px;font-weight:500;background:#fff;color:#1f1f1f;border:1px solid #dadce0;border-radius:24px;text-decoration:none;transition:background 0.2s;" onmouseover="this.style.background='#f8faff'" onmouseout="this.style.background='#fff'">
                        <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#ea4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285f4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#fbbc05" d="M10.54 28.59A14.5 14.5 0 0 1 9.5 24c0-1.59.28-3.14.76-4.59l-7.98-6.19A23.99 23.99 0 0 0 0 24c0 3.77.87 7.35 2.56 10.56l7.98-5.97z"/><path fill="#34a853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 5.97C6.51 42.62 14.62 48 24 48z"/></svg>
                        <span><?= t('Sign in with Google', 'Ingia na Google') ?></span>
                    </a>
                    <div class="text-center small mt-3">
                        <?= __('no_account') ?> <a href="register.php" class="text-gold fw-600"><?= __('register') ?></a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
function togglePassword(id, btn) {
    var inp = document.getElementById(id);
    var icon = btn.querySelector('i');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
