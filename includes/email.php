<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail(string $to, string $subject, string $body, ?string $altBody = null): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = getSetting('smtp_host', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = getSetting('smtp_username', 'mahuvechriss@gmail.com');
        $mail->Password   = getSetting('smtp_password', '');
        $mail->SMTPSecure = getSetting('smtp_encryption', 'tls');
        $mail->Port       = (int)getSetting('smtp_port', 587);

        $mail->setFrom($mail->Username, getSetting('site_name', 'INNOCE OUTFITS'));
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?? strip_tags($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send failed: " . $e->getMessage());
        return false;
    }
}

function sendPasswordResetEmail(string $to, string $token): bool {
    $siteUrl = SITE_URL;
    $link = "$siteUrl/auth/forgot-password.php?token=$token&email=" . urlencode($to);
    $subject = "Password Reset - " . getSetting('site_name', 'INNOCE OUTFITS');
    $body = "
        <h3>Password Reset Request</h3>
        <p>Click the link below to reset your password:</p>
        <p><a href='$link' style='display:inline-block;padding:12px 24px;background:#FF8C00;color:#fff;text-decoration:none;border-radius:4px;'>Reset Password</a></p>
        <p>If you did not request this, please ignore this email.</p>
        <p>— INNOCE OUTFITS</p>
    ";
    return sendEmail($to, $subject, $body);
}

function sendOrderStatusEmail(string $to, string $orderNumber, string $status): bool {
    $subject = "Order #$orderNumber Updated - " . getSetting('site_name', 'INNOCE OUTFITS');
    $body = <<<HTML
        <h2>Order Status Update</h2>
        <p>Your order <strong>#$orderNumber</strong> has been updated to: <strong>$status</strong>.</p>
        <p>Thank you for shopping with us!</p>
        <p>— INNOCE OUTFITS</p>
    HTML;
    return sendEmail($to, $subject, $body);
}

function sendNewContactEmail(string $fromName, string $fromEmail, string $message): bool {
    $adminEmail = getSetting('smtp_username', 'mahuvechriss@gmail.com');
    $subject = "New Contact Message from $fromName";
    $body = <<<HTML
        <h2>New Contact Message</h2>
        <p><strong>From:</strong> $fromName ($fromEmail)</p>
        <p><strong>Message:</strong></p>
        <p>$message</p>
        <hr>
        <p>— INNOCE OUTFITS Notification</p>
    HTML;
    return sendEmail($adminEmail, $subject, $body);
}

function buildBroadcastEmailBody(string $title, string $message, ?string $productLink = null, array $newProducts = []): string {
    $siteName = getSetting('site_name', 'INNOCE OUTFITS');
    $siteUrl = SITE_URL;

    $logoPath = __DIR__ . '/../assets/images/logo.png';
    $logoSrc = $siteUrl . '/assets/images/logo.png';
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoSrc = 'data:image/png;base64,' . $logoData;
    }

    $buttonsHtml = <<<HTML
        <div style="text-align:center;margin:30px 0 20px;">
            <a href="{$siteUrl}/shop/new-arrivals.php" style="display:inline-block;padding:14px 40px;background:#D4A017;color:#0a0a0a;font-size:16px;font-weight:700;text-decoration:none;border-radius:6px;text-transform:uppercase;letter-spacing:1px;">
                Browse New Arrivals
            </a>
        </div>
HTML;
    if ($productLink) {
        $buttonsHtml .= <<<HTML
            <div style="text-align:center;margin:20px 0;">
                <a href="$productLink" style="display:inline-block;padding:12px 32px;background:#0a0a0a;color:#D4A017;font-size:14px;font-weight:600;text-decoration:none;border-radius:6px;text-transform:uppercase;letter-spacing:1px;border:1px solid #D4A017;">
                    Shop This Product
                </a>
            </div>
HTML;
    }

    $productsHtml = '';
    if ($newProducts) {
        $productsHtml = '<tr><td style="padding:0 40px 30px;"><h3 style="margin:0 0 20px;font-size:18px;color:#0a0a0a;text-align:center;">New Arrivals</h3><table width="100%" cellpadding="0" cellspacing="0">';
        $chunks = array_chunk($newProducts, 2);
        foreach ($chunks as $chunk) {
            $productsHtml .= '<tr>';
            foreach ($chunk as $prod) {
                $prodName = htmlspecialchars($prod['name'] ?? '', ENT_QUOTES, 'UTF-8');
                $prodUrl = $prod['url'] ?? '#';
                $imgUrl = $prod['image'] ?? 'https://placehold.co/200x250/121212/D4A017?text=INNOCE';
                $price = $prod['price'] ?? '';
                $productsHtml .= <<<HTML
                    <td width="50%" style="padding:8px;vertical-align:top;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9f9f9;border-radius:8px;overflow:hidden;">
                            <tr><td><a href="$prodUrl"><img src="$imgUrl" style="display:block;width:100%;height:auto;" alt="$prodName"></a></td></tr>
                            <tr><td style="padding:10px;text-align:center;">
                                <a href="$prodUrl" style="color:#0a0a0a;text-decoration:none;font-size:13px;font-weight:600;">$prodName</a>
                                <div style="color:#D4A017;font-size:14px;font-weight:700;margin-top:4px;">$price</div>
                            </td></tr>
                        </table>
                    </td>
HTML;
            }
            $productsHtml .= '</tr>';
        }
        $productsHtml .= '</table></td></tr>';
    }

    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 10px;">
        <tr><td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
                <tr><td style="background:#0a0a0a;padding:30px 40px;text-align:center;">
                    <img src="{$logoSrc}" alt="INNOCE" width="50" height="50" style="display:inline-block;vertical-align:middle;margin-right:10px;border-radius:50%;">
                    <h1 style="margin:0;font-size:28px;font-weight:900;color:#D4A017;letter-spacing:3px;text-transform:uppercase;display:inline-block;vertical-align:middle;">$siteName</h1>
                    <p style="margin:5px 0 0;color:#666;font-size:12px;letter-spacing:1px;">Premium Outfits</p>
                </td></tr>
                <tr><td style="padding:40px 40px 20px;">
                    <h2 style="margin:0 0 20px;font-size:22px;color:#0a0a0a;">$title</h2>
                    <div style="color:#444;font-size:15px;line-height:1.7;">$safeMessage</div>
                    $buttonsHtml
                </td></tr>
                $productsHtml
                <tr><td style="background:#0a0a0a;padding:25px 40px;text-align:center;">
                    <p style="margin:0;color:#666;font-size:12px;">&copy; 2026 $siteName. All rights reserved.</p>
                    <p style="margin:5px 0 0;color:#555;font-size:11px;">You received this email because you opted in for notifications.</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
HTML;
}

