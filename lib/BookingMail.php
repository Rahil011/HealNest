<?php

declare(strict_types=1);

namespace HealNest;

final class BookingMail
{
    /**
     * @return array{ok: bool, error?: string}
     */
    public static function send(
        array $config,
        string $to,
        string $subject,
        string $body,
        string $replyName,
        string $replyEmail
    ): array {
        $method = strtolower(trim((string) ($config['booking_send_method'] ?? 'auto')));
        $replyTo = self::formatReplyTo($replyName, $replyEmail);

        if ($method === 'gmail_api' || $method === 'auto') {
            $viaGmail = self::sendViaGmailApi($config, $to, $subject, $body, $replyTo);
            if ($viaGmail['ok']) {
                return $viaGmail;
            }
            if ($method === 'gmail_api') {
                return $viaGmail;
            }
            $gmailError = $viaGmail['error'] ?? 'Gmail send unavailable.';
        } else {
            $gmailError = '';
        }

        $allowPhpMail = (bool) ($config['booking_fallback_php_mail'] ?? false);
        if ($method === 'php_mail' || ($method === 'auto' && $allowPhpMail)) {
            $viaMail = self::sendViaPhpMail($config, $to, $subject, $body, $replyTo);
            if ($viaMail['ok']) {
                return $viaMail;
            }
            if ($method === 'php_mail') {
                return $viaMail;
            }
            $mailError = $viaMail['error'] ?? 'PHP mail() failed.';
        } else {
            $mailError = 'PHP mail() fallback is disabled (set booking_fallback_php_mail => true to enable).';
        }

        if ($method === 'auto') {
            return [
                'ok' => false,
                'error' => trim($gmailError . ' ' . $mailError),
            ];
        }

        return ['ok' => false, 'error' => 'Invalid booking_send_method in config.'];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private static function sendViaGmailApi(
        array $config,
        string $to,
        string $subject,
        string $body,
        string $replyTo
    ): array {
        if (!GoogleAuth::isMailReadyForAdmin($config)) {
            return ['ok' => false, 'error' => 'Gmail is not connected on the server.'];
        }
        if (!GoogleAuth::hasGmailModifyScope($config)) {
            return [
                'ok' => false,
                'error' => 'Gmail is read-only. Reconnect with send/manage permission, or use booking_send_method php_mail.',
            ];
        }

        $client = GoogleAuth::clientWithToken($config);
        if ($client === null) {
            return ['ok' => false, 'error' => 'Gmail token expired. Reconnect at /admin/connect-mail.php.'];
        }

        try {
            GoogleServices::sendGmail($client, $to, $subject, $body, null, null, $replyTo);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Gmail API send failed: ' . $e->getMessage()];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private static function sendViaPhpMail(
        array $config,
        string $to,
        string $subject,
        string $body,
        string $replyTo
    ): array {
        $from = (string) ($config['booking_from_email'] ?? $to);
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: The Heal Nest <' . $from . '>',
            'Reply-To: ' . $replyTo,
        ];

        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$sent) {
            return [
                'ok' => false,
                'error' => 'PHP mail() is disabled or misconfigured on this host.',
            ];
        }

        return ['ok' => true];
    }

    private static function formatReplyTo(string $name, string $email): string
    {
        $safeName = str_replace(['"', "\r", "\n"], '', $name);

        return $safeName !== '' ? $safeName . ' <' . $email . '>' : $email;
    }
}
