<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email.php';
$pageTitle = 'Reset Password';

$step = $_GET['step'] ?? '';
$email = $_GET['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid token.';
    } else {
        $postEmail = trim($_POST['email'] ?? '');
        $otp = trim($_POST['otp'] ?? '');
        $token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirmation'] ?? '';

        // Step 1: Send OTP
        if ($postEmail && !$otp && !$token) {
            if (!filter_var($postEmail, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'Invalid email.';
            } else {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$postEmail]);
                if ($stmt->fetch()) {
                    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$postEmail]);
                    $db->prepare("INSERT INTO password_resets (email, token, otp) VALUES (?, ?, ?)")->execute([$postEmail, bin2hex(random_bytes(32)), $code]);
                    sendEmail($postEmail, "Your OTP Code - INNOCE OUTFITS",
                        "<h2>Password Reset OTP</h2><p>Use the code below to reset your password:</p>
                        <h1 style='letter-spacing:8px;font-size:36px;color:#FF8C00;'>$code</h1>
                        <p>This code expires in 10 minutes.</p><p>If you did not request this, ignore this email.</p><p>— INNOCE OUTFITS</p>");
                    redirect("forgot-password.php?step=otp&email=" . urlencode($postEmail), "OTP sent to your email.");
                } else {
                    $_SESSION['error'] = 'Email not found.';
                }
            }
        }
        // Step 2: Verify OTP
        elseif ($otp && $postEmail) {
            $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = ? AND otp = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
            $stmt->execute([$postEmail, $otp]);
            $reset = $stmt->fetch();
            if (!$reset) {
                $_SESSION['error'] = 'Invalid or expired OTP.';
            } else {
                $newToken = bin2hex(random_bytes(32));
                $db->prepare("UPDATE password_resets SET token = ?, otp = NULL WHERE email = ?")->execute([$newToken, $postEmail]);
                redirect("forgot-password.php?step=reset&email=" . urlencode($postEmail) . "&token=$newToken", "OTP verified. Set your new password.");
            }
        }
        // Step 3: Reset password
        elseif ($token && $postEmail && $password) {
            $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
            $stmt->execute([$postEmail, $token]);
            $reset = $stmt->fetch();
            if (!$reset) {
                $_SESSION['error'] = 'Invalid or expired reset session.';
            } elseif (strlen($password) < 6) {
                $_SESSION['error'] = 'Password too short.';
            } elseif ($password !== $confirm) {
                $_SESSION['error'] = 'Passwords do not match.';
            } else {
                $db->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $postEmail]);
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$postEmail]);
                redirect('login.php', 'Password reset successful! Please login.');
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php'; ?>
<div class="container py-5" style="min-height: 70vh;">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="form-card">
                <div class="form-card-header">
                    <div class="auth-icon"><i class="fas fa-key"></i></div>
                    <?php if ($step === 'reset'): ?>
                        <h3><?= __('reset_password') ?></h3>
                        <p><?= __('reset_subtitle') ?></p>
                    <?php elseif ($step === 'otp'): ?>
                        <h3>Enter OTP</h3>
                        <p>A 6-digit code was sent to your email</p>
                    <?php else: ?>
                        <h3><?= __('reset_password') ?></h3>
                        <p><?= __('forgot_subtitle') ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($step === 'reset'): ?>
                <form method="POST">
                    <?= csrf() ?>
                    <input type="hidden" name="email" value="<?= escape($email) ?>">
                    <input type="hidden" name="token" value="<?= escape($_GET['token'] ?? '') ?>">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock me-1"></i><?= __('new_password') ?></label>
                        <input type="password" name="password" class="form-control" placeholder="<?= __('password_min') ?>" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-check-circle me-1"></i><?= __('confirm_password') ?></label>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="<?= __('confirm_placeholder') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-gold w-100"><i class="fas fa-sync me-2"></i><?= __('reset_password') ?></button>
                </form>

                <?php elseif ($step === 'otp'): ?>
                <form method="POST">
                    <?= csrf() ?>
                    <input type="hidden" name="email" value="<?= escape($email) ?>">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-shield-alt me-1"></i>OTP Code</label>
                        <input type="text" name="otp" class="form-control text-center" placeholder="000000" required maxlength="6" inputmode="numeric" pattern="[0-9]{6}" style="font-size:1.5rem;letter-spacing:8px;font-weight:700;">
                    </div>
                    <button type="submit" class="btn btn-gold w-100"><i class="fas fa-check me-2"></i>Verify OTP</button>
                    <div class="mt-3 text-center small">
                        <a href="forgot-password.php" class="text-gold"><i class="fas fa-arrow-left me-1"></i>Back to email</a>
                    </div>
                </form>

                <?php else: ?>
                <form method="POST">
                    <?= csrf() ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-envelope me-1"></i><?= __('email') ?></label>
                        <input type="email" name="email" class="form-control" placeholder="<?= __('email_placeholder') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-gold w-100"><i class="fas fa-paper-plane me-2"></i>Send OTP</button>
                    <div class="mt-3 text-center small">
                        <a href="login.php" class="text-gold"><i class="fas fa-arrow-left me-1"></i><?= __('back_to_login') ?></a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>