<?php

declare(strict_types=1);

namespace HealNest;

use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;

final class GoogleServices
{
    /**
     * @return list<array{id: string, subject: string, from: string, date: string, snippet: string}>
     */
    public static function listGmailMessages(GoogleClient $client, int $max = 25): array
    {
        $gmail = new Gmail($client);
        $list = $gmail->users_messages->listUsersMessages('me', [
            'maxResults' => $max,
            'labelIds' => ['INBOX'],
        ]);

        $messages = $list->getMessages() ?? [];
        $out = [];

        foreach ($messages as $ref) {
            $id = $ref->getId();
            if (!$id) {
                continue;
            }
            $row = self::messageHeaders($gmail, $id);
            if ($row !== null) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @return array{id: string, subject: string, from: string, to: string, date: string, snippet: string, body: string, threadId: string, messageIdHeader: string}|null
     */
    public static function getGmailMessage(GoogleClient $client, string $messageId): ?array
    {
        $gmail = new Gmail($client);
        $msg = $gmail->users_messages->get('me', $messageId, ['format' => 'full']);
        $headers = self::headersMap($msg->getPayload()?->getHeaders() ?? []);
        $body = self::extractBody($msg->getPayload());

        return [
            'id' => $messageId,
            'subject' => $headers['Subject'] ?? '(no subject)',
            'from' => $headers['From'] ?? '',
            'to' => $headers['To'] ?? '',
            'date' => $headers['Date'] ?? '',
            'snippet' => $msg->getSnippet() ?? '',
            'body' => $body,
            'threadId' => (string) ($msg->getThreadId() ?? ''),
            'messageIdHeader' => $headers['Message-ID'] ?? $headers['Message-Id'] ?? '',
        ];
    }

    public static function sendGmail(
        GoogleClient $client,
        string $to,
        string $subject,
        string $body,
        ?string $threadId = null,
        ?string $inReplyTo = null
    ): void {
        $oauth2 = new \Google\Service\Oauth2($client);
        $configEmail = (string) ($oauth2->userinfo->get()->getEmail() ?? 'me');

        $lines = [
            'To: ' . $to,
            'From: ' . $configEmail,
            'Subject: ' . self::encodeHeaderSubject($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 7bit',
        ];
        if ($inReplyTo !== null && $inReplyTo !== '') {
            $lines[] = 'In-Reply-To: ' . $inReplyTo;
            $lines[] = 'References: ' . $inReplyTo;
        }
        $raw = implode("\r\n", $lines) . "\r\n\r\n" . $body;

        $gmail = new Gmail($client);
        $message = new Message();
        $message->setRaw(self::base64UrlEncode($raw));
        if ($threadId !== null && $threadId !== '') {
            $message->setThreadId($threadId);
        }
        $gmail->users_messages->send('me', $message);
    }

    public static function trashGmailMessage(GoogleClient $client, string $messageId): void
    {
        $gmail = new Gmail($client);
        $gmail->users_messages->trash('me', $messageId);
    }

    public static function deleteGmailMessage(GoogleClient $client, string $messageId): void
    {
        $gmail = new Gmail($client);
        $gmail->users_messages->delete('me', $messageId);
    }

    /**
     * @return array{id: string, subject: string, from: string, date: string, snippet: string}|null
     */
    private static function messageHeaders(Gmail $gmail, string $id): ?array
    {
        $msg = $gmail->users_messages->get('me', $id, [
            'format' => 'metadata',
            'metadataHeaders' => ['Subject', 'From', 'Date'],
        ]);
        $map = self::headersMap($msg->getPayload()?->getHeaders() ?? []);

        return [
            'id' => $id,
            'subject' => $map['Subject'] ?? '(no subject)',
            'from' => $map['From'] ?? '',
            'date' => $map['Date'] ?? '',
            'snippet' => $msg->getSnippet() ?? '',
        ];
    }

    /**
     * @param list<\Google\Service\Gmail\MessagePartHeader>|array $headers
     * @return array<string, string>
     */
    private static function headersMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $h) {
            $name = $h->getName();
            if ($name !== null) {
                $map[$name] = (string) $h->getValue();
            }
        }

        return $map;
    }

    private static function extractBody(?\Google\Service\Gmail\MessagePart $part): string
    {
        if ($part === null) {
            return '';
        }

        $body = $part->getBody();
        $data = $body?->getData();
        if ($data !== null && $data !== '') {
            return self::decodeBody($data);
        }

        foreach ($part->getParts() ?? [] as $sub) {
            $mime = (string) ($sub->getMimeType() ?? '');
            if ($mime === 'text/plain') {
                $plain = $sub->getBody()?->getData();
                if ($plain) {
                    return self::decodeBody($plain);
                }
            }
        }
        foreach ($part->getParts() ?? [] as $sub) {
            if ((string) ($sub->getMimeType() ?? '') === 'text/html') {
                $html = $sub->getBody()?->getData();
                if ($html) {
                    return strip_tags(self::decodeBody($html));
                }
            }
        }
        foreach ($part->getParts() ?? [] as $sub) {
            $nested = self::extractBody($sub);
            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }

    private static function decodeBody(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded !== false ? $decoded : '';
    }

    private static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function encodeHeaderSubject(string $subject): string
    {
        if (preg_match('/[^\x20-\x7E]/', $subject)) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }

        return $subject;
    }
}
