<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Contact Us';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { redirect('contact.php', 'Invalid token.', 'error'); }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (empty($name) || empty($email) || empty($message)) { $_SESSION['error'] = 'Please fill required fields.'; }
    else {
        $stmt = $db->prepare("INSERT INTO contacts (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $subject, $message]);
        require_once __DIR__ . '/../includes/notifications.php';
        notifyAdmin("New Contact Message", "From: $name ($email)\nSubject: $subject\nMessage: $message");
        redirect('contact.php', 'Message sent successfully!');
    }
}

require_once __DIR__ . '/../includes/header.php'; ?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0 mb-0">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php" class="text-muted"><i class="fas fa-home me-1"></i><?= __('home') ?></a></li>
            <li class="breadcrumb-item active text-gold" aria-current="page"><i class="fas fa-envelope me-1"></i><?= __('contact_us') ?></li>
        </ol>
    </nav>

    <div class="section-header justify-content-center mb-4">
        <div class="section-icon bg-gold"><i class="fas fa-envelope-open-text"></i></div>
        <h2 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= __('contact_us') ?></h2>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="feature-card text-center p-3">
                        <div class="feature-icon"><i class="fas fa-phone"></i></div>
                        <h6 class="fw-600 small"><?= __('phone') ?></h6>
                        <p class="small text-muted mb-0">+255 712 345 678</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center p-3">
                        <div class="feature-icon"><i class="fas fa-envelope"></i></div>
                        <h6 class="fw-600 small"><?= __('email') ?></h6>
                        <p class="small text-muted mb-0">info@innonce.com</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center p-3">
                        <div class="feature-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <h6 class="fw-600 small"><?= __('location') ?></h6>
                        <p class="small text-muted mb-0">Mkonze, Dodoma</p>
                        <a href="https://www.google.com/maps/dir//INNOCE+OUTFITS,+One+way,+Tenth+Rd,+Dodoma" target="_blank" class="small text-decoration-none text-gold fw-600"><i class="fas fa-location-dot me-1"></i><?= __('get_directions') ?> <i class="fas fa-external-link-alt" style="font-size:9px;"></i></a>
                    </div>
                </div>
            </div>

            <div class="feature-card text-center p-4 mb-4">
                <div class="feature-icon mx-auto" style="width:64px;height:64px;font-size:1.5rem;"><i class="fas fa-store"></i></div>
                <h5 class="fw-700"><?= __('visit_us') ?></h5>
                <p class="text-muted small mb-3">Mkonze, Dodoma</p>
                <a href="https://www.google.com/maps/dir//INNOCE+OUTFITS,+One+way,+Tenth+Rd,+Dodoma" target="_blank" class="btn btn-gold"><i class="fas fa-location-dot me-2"></i><?= __('get_directions') ?></a>
            </div>

            <div class="form-card p-4">
                <h5 class="fw-700 mb-3"><i class="fas fa-paper-plane me-2 text-gold"></i><?= __('send_message') ?></h5>
                <form method="POST">
                    <?= csrf() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-user me-1"></i><?= __('name') ?> <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="<?= __('name_placeholder') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-envelope me-1"></i><?= __('email') ?> <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="<?= __('email_placeholder') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-phone me-1"></i><?= __('phone') ?></label>
                            <input type="tel" name="phone" class="form-control" placeholder="<?= __('phone_placeholder') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-tag me-1"></i><?= __('subject') ?></label>
                            <input type="text" name="subject" class="form-control" placeholder="<?= __('subject_placeholder') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="fas fa-comment me-1"></i><?= __('message') ?> <span class="text-danger">*</span></label>
                            <textarea name="message" rows="5" class="form-control" placeholder="<?= __('message_placeholder') ?>" required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-gold w-100"><i class="fas fa-paper-plane me-2"></i><?= __('send_message') ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
