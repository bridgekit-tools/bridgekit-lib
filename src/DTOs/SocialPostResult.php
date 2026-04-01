<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use BridgeKit\Enums\Provider;
use DateTimeImmutable;
use JsonSerializable;

final readonly class SocialPostResult implements JsonSerializable
{
    public function __construct(
        public string $id,
        public Provider $provider,
        public string $content = '',
        public string $url = '',
        public ?DateTimeImmutable $publishedAt = null,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            provider: Provider::from($data['provider']),
            content: $data['content'] ?? '',
            url: $data['url'] ?? '',
            publishedAt: isset($data['published_at']) ? new DateTimeImmutable($data['published_at']) : null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider->value,
            'content' => $this->content,
            'url' => $this->url,
            'published_at' => $this->publishedAt?->format('c'),
            'metadata' => $this->metadata,
        ];
    }
}
