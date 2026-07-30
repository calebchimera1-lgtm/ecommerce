<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Core\Logger;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Thin transactional email wrapper around PHPMailer. When no SMTP host
 * is configured (typical for local development without real mail
 * credentials), messages are written to storage/logs/mail-*.log
 * instead of being sent, so auth flows (verification, password reset)
 * stay fully testable without a mail server.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $view, array $data = []): bool
    {
        $html = self::renderView($view, $data);

        if ((string) config('mail.host') === '') {
            return self::logInsteadOfSending($to, $subject, $html);
        }

        $mailer = new PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host = (string) config('mail.host');
            $mailer->Port = (int) config('mail.port');
            $mailer->SMTPAuth = true;
            $mailer->Username = (string) config('mail.username');
            $mailer->Password = (string) config('mail.password');
            $mailer->SMTPSecure = (string) config('mail.encryption');
            $mailer->setFrom((string) config('mail.from_address'), (string) config('mail.from_name'));
            $mailer->addAddress($to);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $html;
            $mailer->AltBody = strip_tags($html);
            $mailer->send();

            return true;
        } catch (PHPMailerException $e) {
            Logger::error('Mail send failed: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);

            return false;
        }
    }

    private static function renderView(string $view, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . '/Views/emails/' . $view . '.php';

        return (string) ob_get_clean();
    }

    private static function logInsteadOfSending(string $to, string $subject, string $html): bool
    {
        $dir = dirname(__DIR__, 3) . '/storage/logs';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir . '/mail-' . date('Y-m-d') . '.log';
        $entry = sprintf(
            "[%s] To: %s | Subject: %s\n%s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            str_repeat('-', 60),
            strip_tags($html)
        );

        file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);

        return true;
    }
}
