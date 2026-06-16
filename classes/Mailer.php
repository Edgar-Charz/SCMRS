<?php
require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

class Mailer
{
    /**
     * Send a single HTML email. Returns true on success, throws on failure.
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $cfg = require __DIR__ . '/../config/email.php';

        $mail = new PHPMailer(true); // true = throw exceptions on error

        // Server settings
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->Port       = $cfg['port'];
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        if (!empty($cfg['username'])) {
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['username'];
            $mail->Password   = $cfg['password'];
        }

        if (!empty($cfg['encryption'])) {
            $mail->SMTPSecure = $cfg['encryption'];
        } else {
            $mail->SMTPAutoTLS = false;
        }

        // Sender / recipient
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();
        return true;
    }

    /**
     * Build the standard HTML email body used for all notification emails.
     */
    public static function buildBody(
        string $recipientName,
        string $message,
        ?string $link = null,
        string $linkText = 'View Details'
    ): string {
        $cfg     = require __DIR__ . '/../config/email.php';
        $fullUrl = $link ? rtrim($cfg['app_url'], '/') . '/' . ltrim($link, '/') : null;
        $year    = date('Y');

        $buttonHtml = $fullUrl
            ? '<a href="' . htmlspecialchars($fullUrl) . '"
                  style="display:inline-block;padding:11px 28px;background:#001a52;color:#fff;
                         text-decoration:none;border-radius:6px;font-size:14px;font-weight:600;
                         margin-top:18px;">' . htmlspecialchars($linkText) . '</a>'
            : '';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">
        </head>
        <body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:32px 0;">
                <tr><td align="center">
                    <table width="600" cellpadding="0" cellspacing="0"
                           style="background:#fff;border-radius:10px;overflow:hidden;
                                  box-shadow:0 2px 12px rgba(0,0,0,.08);max-width:600px;width:100%;">

                        <!-- Header -->
                        <tr>
                            <td style="background:#001a52;padding:28px 32px;text-align:center;">
                                <h1 style="margin:0;color:#fff;font-size:22px;letter-spacing:.5px;">
                                    UDSM SCMRS
                                </h1>
                                <p style="margin:4px 0 0;color:rgba(255,255,255,.7);font-size:12px;">
                                    Student Complaint Management &amp; Reporting System
                                </p>
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style="padding:36px 40px;">
                                <p style="margin:0 0 10px;font-size:15px;color:#333;">
                                    Hello, <strong>{$recipientName}</strong>
                                </p>
                                <p style="margin:0;font-size:15px;color:#444;line-height:1.7;">
                                    {$message}
                                </p>
                                {$buttonHtml}
                            </td>
                        </tr>

                        <!-- Divider -->
                        <tr><td style="padding:0 40px;">
                            <hr style="border:none;border-top:1px solid #e8ecf4;margin:0;">
                        </td></tr>

                        <!-- Footer -->
                        <tr>
                            <td style="padding:20px 40px;text-align:center;">
                                <p style="margin:0;font-size:12px;color:#999;">
                                    This is an automated message from the UDSM Student Complaint
                                    Management &amp; Reporting System. Please do not reply to this email.
                                </p>
                                <p style="margin:8px 0 0;font-size:12px;color:#bbb;">
                                    &copy; {$year} University of Dar es Salaam
                                </p>
                            </td>
                        </tr>

                    </table>
                </td></tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
