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