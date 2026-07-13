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
                    <a href="https://promoted-seasnail-45.accounts.dev/sign-in?redirect_url=http%3A%2F%2Flocalhost%3A8080%2Finnonce-outfits%2Findex.php%3Fclerk_action%3Dlogin" class="btn btn-outline-dark-custom w-100" style="font-size:13px;display:flex;align-items:center;justify-content:center;gap:10px;">
                        <span style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <img src="<?= SITE_URL ?>/assets/images/world.png" alt="" style="width:32px;height:32px;border-radius:50%;display:block;border:2px solid rgba(255,255,255,0.2);box-shadow:0 0 8px rgba(255,255,255,0.15);">
                        </span>
                        <?= t('Sign in with other platform', 'Ingia na platform nyingine') ?>
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
