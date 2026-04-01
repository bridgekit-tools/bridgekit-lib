<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Microsoft\Services;

use BridgeKit\Contracts\Messaging\EmailSenderInterface;
use BridgeKit\DTOs\EmailMessage;
use BridgeKit\Enums\MailFolder;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\Microsoft\MicrosoftProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;

class MicrosoftOutlookService extends AbstractService implements EmailSenderInterface
{
    private const string BASE_URL = 'https://graph.microsoft.com/v1.0/me';

    public function __construct(MicrosoftProvider $provider)
    {
        parent::__construct($provider);
    }

    public function send(EmailMessage $message): string
    {
        $payload = [
            'message' => $this->buildSendMailMessage($message),
            'saveToSentItems' => true,
        ];

        $response = $this->authenticatedHttp()
            ->post(self::BASE_URL.'/sendMail', $payload);

        if ($response->failed()) {
            throw new ProviderException(
                message: "Outlook sendMail failed: {$response->body()}",
                provider: $this->getProviderName(),
                code: $response->status(),
            );
        }

        return '';
    }

    public function listMessages(MailFolder|string $folder = MailFolder::Inbox, int $limit = 50): array
    {
        $folderStr = $folder instanceof MailFolder ? $folder->value : $folder;
        $folderSegment = strtoupper($folderStr) === 'INBOX'
            ? 'inbox'
            : $folderStr;

        $url = self::BASE_URL.'/mailFolders/'.rawurlencode($folderSegment).'/messages';
        $response = $this->authenticatedHttp()->get($url, [
            '$top' => $limit,
            '$orderby' => 'receivedDateTime desc',
        ]);

        /** @var array{value?: array<int, array<string, mixed>>} $data */
        $data = $response->json();
        $messages = $data['value'] ?? [];

        return array_map(fn (array $m): EmailMessage => $this->mapGraphMessageToEmailMessage($m), $messages);
    }

    public function getMessage(string $messageId): EmailMessage
    {
        $url = self::BASE_URL.'/messages/'.rawurlencode($messageId);
        $response = $this->authenticatedHttp()->get($url);

        /** @var array<string, mixed> $m */
        $m = $response->json();

        return $this->mapGraphMessageToEmailMessage($m);
    }

    public function deleteMessage(string $messageId): bool
    {
        $url = self::BASE_URL.'/messages/'.rawurlencode($messageId);
        $response = $this->authenticatedHttp()->delete($url);

        return $response->successful();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSendMailMessage(EmailMessage $message): array
    {
        $payload = [
            'subject' => $message->subject,
            'body' => [
                'contentType' => $message->isHtml ? 'HTML' : 'Text',
                'content' => $message->body,
            ],
            'toRecipients' => $this->mapAddressesToRecipients($message->to),
            'ccRecipients' => $this->mapAddressesToRecipients($message->cc),
            'bccRecipients' => $this->mapAddressesToRecipients($message->bcc),
        ];

        if ($message->from !== '') {
            $payload['from'] = [
                'emailAddress' => ['address' => $message->from],
            ];
        }

        if ($message->attachments !== []) {
            $payload['attachments'] = $this->mapAttachments($message->attachments);
        }

        return $payload;
    }

    /**
     * @param  array<int, string>  $addresses
     * @return array<int, array{emailAddress: array{address: string}}>
     */
    private function mapAddressesToRecipients(array $addresses): array
    {
        return array_values(array_map(
            static fn (string $email): array => [
                'emailAddress' => ['address' => $email],
            ],
            $addresses,
        ));
    }

    /**
     * @param  array<int, array{name: string, content: string, mime_type: string}>  $attachments
     * @return array<int, array<string, mixed>>
     */
    private function mapAttachments(array $attachments): array
    {
        $out = [];
        foreach ($attachments as $att) {
            $out[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $att['name'],
                'contentType' => $att['mime_type'],
                'contentBytes' => base64_encode($att['content']),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $m
     */
    private function mapGraphMessageToEmailMessage(array $m): EmailMessage
    {
        $body = $m['body'] ?? null;
        $content = '';
        $isHtml = true;
        if (is_array($body)) {
            $content = (string) ($body['content'] ?? '');
            $isHtml = strtoupper((string) ($body['contentType'] ?? 'HTML')) === 'HTML';
        }

        $to = $this->extractRecipientAddresses($m['toRecipients'] ?? null);
        $cc = $this->extractRecipientAddresses($m['ccRecipients'] ?? null);
        $bcc = $this->extractRecipientAddresses($m['bccRecipients'] ?? null);

        $from = '';
        if (isset($m['from']) && is_array($m['from'])) {
            $from = (string) ($m['from']['emailAddress']['address'] ?? '');
        }

        $date = null;
        if (isset($m['receivedDateTime'])) {
            $date = new DateTimeImmutable((string) $m['receivedDateTime']);
        } elseif (isset($m['sentDateTime'])) {
            $date = new DateTimeImmutable((string) $m['sentDateTime']);
        }

        $graphAttachments = [];
        if (isset($m['attachments']) && is_array($m['attachments'])) {
            foreach ($m['attachments'] as $att) {
                if (! is_array($att)) {
                    continue;
                }
                $name = (string) ($att['name'] ?? 'attachment');
                $mime = (string) ($att['contentType'] ?? 'application/octet-stream');
                $bytes = isset($att['contentBytes']) ? base64_decode((string) $att['contentBytes'], true) : false;
                $graphAttachments[] = [
                    'name' => $name,
                    'content' => $bytes !== false ? $bytes : '',
                    'mime_type' => $mime,
                ];
            }
        }

        return new EmailMessage(
            subject: (string) ($m['subject'] ?? ''),
            body: $content,
            to: $to,
            from: $from,
            cc: $cc,
            bcc: $bcc,
            isHtml: $isHtml,
            attachments: $graphAttachments,
            messageId: isset($m['id']) ? (string) $m['id'] : null,
            date: $date,
            metadata: $m,
        );
    }

    /**
     * @param  mixed  $recipients
     * @return array<int, string>
     */
    private function extractRecipientAddresses(mixed $recipients): array
    {
        if (! is_array($recipients)) {
            return [];
        }

        $emails = [];
        foreach ($recipients as $r) {
            if (! is_array($r)) {
                continue;
            }
            $addr = $r['emailAddress']['address'] ?? null;
            if (is_string($addr) && $addr !== '') {
                $emails[] = $addr;
            }
        }

        return $emails;
    }
}
