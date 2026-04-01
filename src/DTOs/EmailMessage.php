<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use DateTimeImmutable;
use JsonSerializable;

final readonly class EmailMessage implements JsonSerializable
{
    /**
     * @param  array<int, string>  $to
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  array<int, array{name: string, content: string, mime_type: string}>  $attachments
     */
    public function __construct(
        public string $subject,
        public string $body,
        public array $to = [],
        public string $from = '',
        public array $cc = [],
        public array $bcc = [],
        public bool $isHtml = true,
        public array $attachments = [],
        public ?string $messageId = null,
        public ?DateTimeImmutable $date = null,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            subject: $data['subject'] ?? '',
            body: $data['body'] ?? '',
            to: (array) ($data['to'] ?? []),
            from: $data['from'] ?? '',
            cc: (array) ($data['cc'] ?? []),
            bcc: (array) ($data['bcc'] ?? []),
            isHtml: $data['is_html'] ?? true,
            attachments: $data['attachments'] ?? [],
            messageId: $data['message_id'] ?? null,
            date: isset($data['date']) ? new DateTimeImmutable($data['date']) : null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'subject' => $this->subject,
            'body' => $this->body,
            'to' => $this->to,
            'from' => $this->from,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'is_html' => $this->isHtml,
            'attachments' => $this->attachments,
            'message_id' => $this->messageId,
            'date' => $this->date?->format('c'),
            'metadata' => $this->metadata,
        ];
    }
}
