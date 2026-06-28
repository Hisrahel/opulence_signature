<?php
// config/mailer.php — Sends transactional emails (password-change alerts, etc.)
//
// REQUIRES PHPMailer. Install once via Composer from your project root:
//   composer require phpmailer/phpmailer
//
// This creates a /vendor folder and vendor/autoload.php, which this file
// loads automatically if present. If PHPMailer is NOT installed yet,
// every function below fails safely (returns false, logs to error_log)
// instead of crashing the page — so changing your password still works
// even before you've set up email.
//
// ------------------------------------------------------------------
// SMTP CONFIGURATION — fill these in with your real provider details.
// Gmail: use an "App Password" (not your normal Gmail password) —
// generate one at https://myaccount.google.com/apppasswords
// (requires 2-Step Verification to be enabled on the Google account).
// Alternatively use a transactional provider (Brevo, SendGrid, Mailgun, etc.)
// which tends to be more reliable for automated mail than personal Gmail.
// ------------------------------------------------------------------

// Defined defensively here (not just in admin/_layout.php) because this
// file is also required directly by pages outside the admin layout —
// login.php, forgot-password.php, reset-password.php — which run before
// any admin session exists and never load _layout.php.

// Load .env file if it exists
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $val;
    }
}

define('SMTP_PASSWORD',   $_ENV['SMTP_PASSWORD']   ?? '');
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_USERNAME',   'opulencesignature001@gmail.com'); 
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
define('SMTP_FROM_EMAIL', 'olayemisrael5@gmail.com');   // ← change this
define('SMTP_FROM_NAME',  'Opulence Signature Admin');

/**
 * Sends an email via SMTP using PHPMailer.
 * Returns true on success. On failure, returns false AND stores the real
 * error reason in $GLOBALS['last_mail_error'] so callers (like
 * settings.php) can surface it to the admin instead of a generic message.
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody = ''): bool
{
    $GLOBALS['last_mail_error'] = '';

    if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['last_mail_error'] = "Invalid recipient email '$toEmail'";
        error_log("sendMail: " . $GLOBALS['last_mail_error']);
        return false;
    }

    $autoload = APP_ROOT . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        $GLOBALS['last_mail_error'] = 'PHPMailer not installed. Run "composer require phpmailer/phpmailer" in the project root.';
        error_log('sendMail: ' . $GLOBALS['last_mail_error']);
        return false;
    }
    require_once $autoload;

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $GLOBALS['last_mail_error'] = 'PHPMailer class not found after autoload. Try running "composer install" again.';
        error_log('sendMail: ' . $GLOBALS['last_mail_error']);
        return false;
    }

    if (in_array(SMTP_PASSWORD, ['your-16-char-app-password', '---', ''], true)) {
        $GLOBALS['last_mail_error'] = 'SMTP_PASSWORD in config/mailer.php is still a placeholder — set your real Gmail App Password.';
        error_log('sendMail: ' . $GLOBALS['last_mail_error']);
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody !== '' ? $plainBody : strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        $GLOBALS['last_mail_error'] = $mail->ErrorInfo ?: $e->getMessage();
        error_log('sendMail failed: ' . $GLOBALS['last_mail_error']);
        return false;
    }
}

/**
 * Sends the "your admin password was changed" security notification.
 *
 * Per the site owner's explicit request, this email includes the actual
 * new plaintext password so they can confirm exactly what it was changed
 * to. This is a deliberate trade-off the owner has accepted: the email
 * becomes a standing, indefinite copy of the live admin password sitting
 * in an inbox (synced across devices, searchable, outside this app's
 * control). If that risk ever stops being acceptable, drop the
 * $newPassword param/block below and keep just the time/IP confirmation.
 */
function sendPasswordChangedEmail(string $toEmail, string $adminName, string $username, string $newPassword): bool
{
    $time = date('l, d F Y \a\t H:i:s');
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $subject = 'Your Opulence Admin password was changed';

    $html = "
        <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#faf8f3;border-radius:10px'>
          <h2 style='color:#0d0d0d;margin-bottom:4px'>Password Changed</h2>
          <p style='color:#5a5a5a;font-size:14px;line-height:1.6'>
            Hi " . htmlspecialchars($adminName) . ",<br><br>
            This is a confirmation that the password for admin account
            <strong>" . htmlspecialchars($username) . "</strong> was just changed.
          </p>
          <p style='font-size:14px;color:#5a5a5a;margin:16px 0'>
            <strong>New password:</strong><br>
            <span style='display:inline-block;margin-top:6px;background:#f5e9c8;color:#0d0d0d;
              padding:8px 14px;border-radius:6px;font-family:monospace;font-size:15px;letter-spacing:.5px'>"
        . htmlspecialchars($newPassword) . "</span>
          </p>
          <table style='width:100%;font-size:13px;color:#5a5a5a;margin:16px 0'>
            <tr><td style='padding:4px 0'><strong>Time:</strong></td><td>" . htmlspecialchars($time) . "</td></tr>
            <tr><td style='padding:4px 0'><strong>IP address:</strong></td><td>" . htmlspecialchars($ip) . "</td></tr>
          </table>
          <p style='color:#b42318;font-size:13px;line-height:1.6;background:#fdeceb;padding:12px;border-radius:8px'>
            If you did not make this change, your account may be compromised.
            Contact your developer immediately and consider taking the site offline
            (Settings → Maintenance Mode) until the issue is resolved.
          </p>
          <p style='color:#999;font-size:11px;margin-top:20px'>Opulence Signature Admin Panel — automated security notice.</p>
        </div>
    ";

    return sendMail($toEmail, $adminName, $subject, $html);
}
