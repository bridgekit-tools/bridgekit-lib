<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Google\Services;

use BridgeKit\Contracts\Messaging\EmailSenderInterface;
use BridgeKit\DTOs\EmailMessage;
use BridgeKit\Enums\MailFolder;
use BridgeKit\Providers\Google\GoogleProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;

class GoogleGmailService extends AbstractService implements EmailSenderInterface
{
    private const string BASE_URL = 'https://gmail.googleapis.com/gmail/v1/users/me';

    public function __construct(
        GoogleProvider $provider,
    ) {
        parent::__construct($provider);
    }

    public function send(EmailMessage $message): string
    {
        $raw = $this->buildRfc2822Message($message);
        $encoded = $this->base64UrlEncode($raw);

        $response = $this->authenticatedHttp()->post(self::BASE_URL.'/messages/send', [
            'raw' => $encoded,
        ]);

        return (string) ($response->json('id') ?? '');
    }

    public function listMessages(MailFolder|string $folder = MailFolder::Inbox, int $limit = 50): array
    {
        $folderStr = $folder instanceof MailFolder ? $folder->value : $folder;
        $list = $this->authenticatedHttp()->get(self::BASE_URL.'/messages', [
            'labelIds' => $folderStr,
            'maxResults' => $limit,
        ]);

        $ids = $list->json('messages') ?? [];
        $out = [];
        foreach ($ids as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }
            $out[] = $this->getMessage((string) $row['id']);
        }

        return $out;
    }

    public function getMessage(string $messageId): EmailMessage
    {
        $response = $this->authenticatedHttp()->get(
            self::BASE_URL.'/messages/'.rawurlencode($messageId),
            ['format' => 'full']
        );

        return $this->mapGmailToEmailMessage($response->json());
    }

    public function deleteMessage(string $messageId): bool
    {
        $response = $this->authenticatedHttp()->delete(
            self::BASE_URL.'/messages/'.rawurlencode($messageId)
        );

        return $response->successful();
    }

    private function buildRfc2822Message(EmailMessage $message): string
    {
        $lines = [];
        if ($message->from !== '') {
            $lines[] = 'From: '.$message->from;
        }
        if ($message->to !== []) {
            $lines[] = 'To: '.implode(', ', $message->to);
        }
        if ($message->cc !== []) {
            $lines[] = 'Cc: '.implode(', ', $message->cc);
        }
        if ($message->bcc !== []) {
            $lines[] = 'Bcc: '.implode(', ', $message->bcc);
        }
        $lines[] = 'Subject: '.$message->subject;
        $lines[] = 'MIME-Version: 1.0';

        if ($message->attachments !== []) {
            $boundary = 'bk_'.bin2hex(random_bytes(12));
            $lines[] = 'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
            $lines[] = '';
            $lines[] = '--'.$boundary;
            $lines[] = $message->isHtml
                ? 'Content-Type: text/html; charset=UTF-8'
                : 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: quoted-printable';
            $lines[] = '';
            $lines[] = quoted_printable_encode($message->body);
            foreach ($message->attachments as $att) {
                $name = $att['name'] ?? 'attachment';
                $mime = $att['mime_type'] ?? 'application/octet-stream';
                $content = $att['content'] ?? '';
                $lines[] = '--'.$boundary;
                $lines[] = 'Content-Type: '.$mime.'; name="'.$name.'"';
                $lines[] = 'Content-Disposition: attachment; filename="'.$name.'"';
                $lines[] = 'Content-Transfer-Encoding: base64';
                $lines[] = '';
                $lines[] = chunk_split(base64_encode($content));
            }
            $lines[] = '--'.$boundary.'--';
        } else {
            $lines[] = $message->isHtml
                ? 'Content-Type: text/html; charset=UTF-8'
                : 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = '';
            $lines[] = $message->body;
        }

        return implode("\r\n", $lines);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>|null  $msg
     */
    private function mapGmailToEmailMessage(?array $msg): EmailMessage
    {
        if ($msg === null) {
            return new EmailMessage(subject: '', body: '');
        }

        $headers = $this->flattenHeaders($msg['payload'] ?? []);
        $subject = $headers['subject'] ?? '';
        $from = $headers['from'] ?? '';
        $to = $this->splitAddresses($headers['to'] ?? '');
        $cc = $this->splitAddresses($headers['cc'] ?? '');
        $bcc = $this->splitAddresses($headers['bcc'] ?? '');
        $dateStr = $headers['date'] ?? null;
        $date = $dateStr !== null && $dateStr !== '' ? new DateTimeImmutable($dateStr) : null;

        [$body, $isHtml] = $this->extractBodyFromPayload(
            $msg['payload'] ?? [],
            (string) ($msg['snippet'] ?? '')
        );

        return new EmailMessage(
            subject: $subject,
            body: $body,
            to: $to,
            from: $from,
            cc: $cc,
            bcc: $bcc,
            isHtml: $isHtml,
            attachments: [],
            messageId: isset($msg['id']) ? (string) $msg['id'] : null,
            date: $date,
            metadata: $msg,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function flattenHeaders(array $payload): array
    {
        $out = [];
        foreach ($payload['headers'] ?? [] as $h) {
            if (is_array($h) && isset($h['name'], $h['value'])) {
                $out[strtolower((string) $h['name'])] = (string) $h['value'];
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function splitAddresses(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $raw));

        return array_values(array_filter($parts, static fn (string $s): bool => $s !== ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: bool}
     */
    private function extractBodyFromPayload(array $payload, string $fallbackSnippet = ''): array
    {
        $mime = (string) ($payload['mimeType'] ?? '');
        if (str_starts_with($mime, 'multipart/')) {
            [$plain, $html] = $this->extractPartsRecursive($payload);
            if ($html !== '') {
                return [$html, true];
            }
            if ($plain !== '') {
                return [$plain, false];
            }
        }

        if (isset($payload['body']['data']) && is_string($payload['body']['data'])) {
            $decoded = $this->base64UrlDecode($payload['body']['data']);

            return [$decoded, str_contains($mime, 'html')];
        }

        $plain = '';
        $html = '';
        foreach ($payload['parts'] ?? [] as $part) {
            if (! is_array($part)) {
                continue;
            }
            $partMime = (string) ($part['mimeType'] ?? '');
            if (str_starts_with($partMime, 'multipart/')) {
                [$subPlain, $subHtml] = $this->extractPartsRecursive($part);
                if ($subPlain !== '') {
                    $plain = $subPlain;
                }
                if ($subHtml !== '') {
                    $html = $subHtml;
                }
            } elseif (isset($part['body']['data']) && is_string($part['body']['data'])) {
                $decoded = $this->base64UrlDecode($part['body']['data']);
                if (str_contains($partMime, 'html')) {
                    $html = $decoded;
                } else {
                    $plain = $decoded;
                }
            }
        }

        if ($html !== '') {
            return [$html, true];
        }
        if ($plain !== '') {
            return [$plain, false];
        }

        return [$fallbackSnippet, false];
    }

    /**
     * @param  array<string, mixed>  $part
     * @return array{0: string, 1: string}
     */
    private function extractPartsRecursive(array $part): array
    {
        $plain = '';
        $html = '';
        foreach ($part['parts'] ?? [] as $sub) {
            if (! is_array($sub)) {
                continue;
            }
            $mime = (string) ($sub['mimeType'] ?? '');
            if (str_starts_with($mime, 'multipart/')) {
                [$p, $h] = $this->extractPartsRecursive($sub);
                if ($p !== '') {
                    $plain = $p;
                }
                if ($h !== '') {
                    $html = $h;
                }
            } elseif (isset($sub['body']['data']) && is_string($sub['body']['data'])) {
                $decoded = $this->base64UrlDecode($sub['body']['data']);
                if (str_contains($mime, 'html')) {
                    $html = $decoded;
                } else {
                    $plain = $decoded;
                }
            }
        }

        return [$plain, $html];
    }

    private function base64UrlDecode(string $data): string
    {
        $b64 = strtr($data, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode($b64, true);
    }
}
