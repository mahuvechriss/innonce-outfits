</main>

<footer class="bg-dark text-white pt-5 pb-3 mt-5" data-bs-theme="dark">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="INNOCE OUTFITS" style="height: 64px; width: 64px; border-radius: 50%; vertical-align: middle;" class="mb-3">
                <span class="fw-bold" style="font-family: 'Playfair Display', serif; font-size: 1.3rem;"><span class="text-gold">INNOCE</span> OUTFITS</span>
                <p class="text-muted small"><i class="fas fa-quote-left me-1 text-gold opacity-50"></i><?= __('footer_tagline') ?><i class="fas fa-quote-right ms-1 text-gold opacity-50"></i></p>
                <div class="d-flex gap-2 mt-3">
                    <a href="#" class="btn btn-outline-light btn-sm" style="width:36px;height:36px;border-radius:50%;padding:0;display:flex;align-items:center;justify-content:center;"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm" style="width:36px;height:36px;border-radius:50%;padding:0;display:flex;align-items:center;justify-content:center;"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm" style="width:36px;height:36px;border-radius:50%;padding:0;display:flex;align-items:center;justify-content:center;"><i class="fab fa-tiktok"></i></a>
                    <a href="https://wa.me/255752263474" target="_blank" class="btn btn-outline-light btn-sm" style="width:36px;height:36px;border-radius:50%;padding:0;display:flex;align-items:center;justify-content:center;"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            <div class="col-lg-2">
                <h6 class="fw-bold mb-3"><i class="fas fa-link me-1 text-gold"></i><?= __('quick_links') ?></h6>
                <div class="d-flex flex-column gap-2 small">
                    <a href="<?= SITE_URL ?>/index.php" class="text-muted text-decoration-none footer-link"><i class="fas fa-chevron-right me-1" style="font-size:8px;"></i><?= __('home') ?></a>
                    <a href="<?= SITE_URL ?>/shop/index.php" class="text-muted text-decoration-none footer-link"><i class="fas fa-chevron-right me-1" style="font-size:8px;"></i><?= __('shop') ?></a>
                    <a href="<?= SITE_URL ?>/shop/cart.php" class="text-muted text-decoration-none footer-link"><i class="fas fa-chevron-right me-1" style="font-size:8px;"></i><?= __('cart') ?></a>
                    <a href="<?= SITE_URL ?>/pages/contact.php" class="text-muted text-decoration-none footer-link"><i class="fas fa-chevron-right me-1" style="font-size:8px;"></i><?= __('contact') ?></a>
                    <?php if (isAdmin()): ?>
                    <a href="<?= SITE_URL ?>/admin/index.php" class="text-gold text-decoration-none footer-link fw-600"><i class="fas fa-cog me-1" style="font-size:8px;"></i><?= __('admin_panel') ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-address-card me-1 text-gold"></i><?= __('contact_info') ?></h6>
                <div class="d-flex flex-column gap-2 small text-muted">
                    <span><a href="https://wa.me/255752263474" target="_blank" class="text-muted text-decoration-none"><i class="fab fa-whatsapp me-2 text-gold"></i>+255 752 263 474</a></span>
                    <span><a href="https://wa.me/255683086608" target="_blank" class="text-muted text-decoration-none"><i class="fab fa-whatsapp me-2 text-gold"></i>+255 683 086 608</a></span>
                    <span><i class="fas fa-envelope me-2 text-gold"></i>info@innonce.com</span>
                    <span><a href="https://www.google.com/maps/dir//INNOCE+OUTFITS,+One+way,+Tenth+Rd,+Dodoma" target="_blank" class="text-muted text-decoration-none footer-link"><i class="fas fa-map-marker-alt me-2 text-gold"></i>Dodoma <i class="fas fa-external-link-alt ms-1" style="font-size:10px;"></i></a></span>
                    <span><i class="fas fa-clock me-2 text-gold"></i>Mon-Sat 9AM-7PM</span>
                </div>
            </div>
            <div class="col-lg-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-envelope-open-text me-1 text-gold"></i><?= __('newsletter') ?></h6>
                <p class="small text-muted"><?= __('newsletter_desc') ?></p>
                <form action="<?= SITE_URL ?>/actions/newsletter.php" method="POST" class="d-flex">
                    <?= csrf() ?>
                    <input type="email" name="email" class="form-control form-control-sm" placeholder="<?= __('newsletter_placeholder') ?>" required>
                    <button type="submit" class="btn-gold-sm" title="<?= __('subscribe') ?>"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
        <hr class="border-secondary my-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <p class="text-muted small mb-0">&copy; <?= date('Y') ?> INNOCE OUTFITS. <?= __('all_rights') ?></p>
            <div class="d-flex gap-3 small">
                <a href="#" class="text-muted text-decoration-none"><?= __('privacy_policy') ?></a>
                <a href="#" class="text-muted text-decoration-none"><?= __('terms_of_service') ?></a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>var SITE_URL = '<?= SITE_URL ?>'; var CHAT_LANG = '<?= currentLang() ?>';</script>
<script src="<?= SITE_URL ?>/assets/js/app.js?v=2.0"></script>
<script src="<?= SITE_URL ?>/assets/js/chatbot.js?v=3.0"></script>

<!-- INNOCEshow Chatbot -->
<div id="innoceshow-btn" onclick="toggleChat()" style="position:fixed;bottom:24px;right:24px;z-index:9999;width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#FF8C00,#FF6600);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 20px rgba(255,140,0,0.4);transition:transform 0.2s" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
    <i class="fas fa-comment-dots fa-lg"></i>
</div>

<div id="innoceshow-window" style="position:fixed;bottom:96px;right:16px;z-index:9999;width:min(360px,calc(100vw - 32px));max-height:min(520px,calc(100vh - 120px));background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,0.15);display:none;flex-direction:column;overflow:hidden;border:2px solid #FF8C00">
    <div style="background:linear-gradient(135deg,#FF8C00,#FF6600);color:#fff;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
        <div class="d-flex align-items-center gap-2">
            <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="" style="height:22px;width:22px;border-radius:50%;margin-right:6px">
            <span style="font-weight:700;font-size:16px">INNOCEshow</span>
            <span style="display:flex;gap:3px;margin-left:6px">
                <button onclick="chatSwitchLang('en')" id="chat-lang-en" style="background:<?= currentLang() === 'en' ? 'rgba(255,255,255,0.35)' : 'rgba(255,255,255,0.12)' ?>;border:none;color:#fff;font-size:9px;padding:1px 6px;border-radius:4px;cursor:pointer;font-weight:<?= currentLang() === 'en' ? '700' : '400' ?>">EN</button>
                <button onclick="chatSwitchLang('sw')" id="chat-lang-sw" style="background:<?= currentLang() === 'sw' ? 'rgba(255,255,255,0.35)' : 'rgba(255,255,255,0.12)' ?>;border:none;color:#fff;font-size:9px;padding:1px 6px;border-radius:4px;cursor:pointer;font-weight:<?= currentLang() === 'sw' ? '700' : '400' ?>">SW</button>
            </span>
        </div>
        <button onclick="toggleChat()" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;padding:0;line-height:1">&times;</button>
    </div>
    <div id="chat-messages" style="flex:1;overflow-y:auto;padding:14px;background:#f8f6f2;display:flex;flex-direction:column;gap:10px;min-height:300px;max-height:360px">
        <div class="chat-msg chat-bot" style="align-self:flex-start;background:#fff;border-radius:0 12px 12px 12px;padding:10px 14px;max-width:85%;font-size:13px;line-height:1.5;color:#333;box-shadow:0 1px 3px rgba(0,0,0,0.06);white-space:pre-wrap"><?= t("I'm <strong>INNOCEshow</strong> what can I help you with today, our humble customer?", "Mimi ni <strong>INNOCEshow</strong> nikusaidie nini leo, mteja wetu mnyenyekevu?") ?></div>
    </div>
    <div style="display:flex;border-top:1px solid #e8e0d0;flex-shrink:0;background:#fff">
        <input type="text" id="chat-input" placeholder="<?= t('Ask INNOCEshow...', 'Muulize INNOCEshow...') ?>" style="flex:1;border:none;padding:12px 14px;font-size:13px;outline:none" onkeydown="if(event.key==='Enter')sendChat()">
        <button onclick="sendChat()" style="background:#FF8C00;border:none;color:#fff;padding:0 18px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;border-radius:0;flex-shrink:0"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

</body>
</html>
<?php $db = null; ?>
