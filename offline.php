<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'You\'re Offline';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/images/logo.png">
    <meta name="theme-color" content="#FF8C00">
    <title>Offline - INNOCE OUTFITS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <style>
        .offline-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #121212 0%, #1a1a1a 100%);
            padding: 2rem;
        }
        .offline-card {
            text-align: center;
            max-width: 480px;
            padding: 3rem 2rem;
        }
        .offline-icon {
            font-size: 4rem;
            color: var(--gold);
            margin-bottom: 1rem;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .offline-title {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.75rem;
        }
        .offline-text {
            color: rgba(255,255,255,0.6);
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }
        .offline-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .reconnect-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            background: rgba(255,140,0,0.12);
            border: 1px solid rgba(255,140,0,0.3);
            color: var(--gold-light);
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }
        .pulse-dot-offline {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ffc107;
            animation: pulse-warning 2s infinite;
        }
        .cached-pages {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .cached-pages h6 {
            color: rgba(255,255,255,0.5);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.75rem;
        }
        .cached-pages a {
            display: block;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .cached-pages a:hover {
            background: rgba(255,255,255,0.05);
            color: var(--gold);
        }
        .cached-pages a i {
            width: 20px;
            color: var(--gold);
        }
    </style>
</head>
<body>
    <div class="offline-page">
        <div class="offline-card">
            <div class="offline-icon">
                <i class="fas fa-wifi-slash"></i>
            </div>
            <div class="reconnect-badge">
                <span class="pulse-dot-offline"></span>
                No internet connection
            </div>
            <h2 class="offline-title">You're Offline</h2>
            <p class="offline-text">
                It looks like you've lost your internet connection. 
                You can still browse the pages you've visited recently.
            </p>
            <div class="offline-actions">
                <button class="btn btn-gold btn-lg" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt me-2"></i> Try Reconnecting
                </button>
                <a href="<?= SITE_URL ?>/index.php" class="btn btn-outline-gold btn-lg">
                    <i class="fas fa-home me-2"></i> Go to Homepage
                </a>
            </div>
            <div class="cached-pages">
                <h6><i class="fas fa-bookmark me-1"></i> Browsed Pages</h6>
                <a href="<?= SITE_URL ?>/index.php"><i class="fas fa-home"></i> Home</a>
                <a href="<?= SITE_URL ?>/shop/index.php"><i class="fas fa-store"></i> Shop</a>
                <a href="<?= SITE_URL ?>/shop/categories.php"><i class="fas fa-th-large"></i> Categories</a>
                <a href="<?= SITE_URL ?>/pages/contact.php"><i class="fas fa-envelope"></i> Contact</a>
            </div>
        </div>
    </div>

    <script>
        // Auto-reconnect detection
        function checkConnection() {
            if (navigator.onLine) {
                window.location.reload();
            }
        }
        window.addEventListener('online', checkConnection);
    </script>
</body>
</html>
