<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use BridgeKit\Enums\Provider;
use BridgeKit\Enums\WebhookEvent;
use DateTimeImmutable;
use JsonSerializable;

final readonly class WebhookPayload implements JsonSerializable
{
    public function __construct(
        public Provider $provider,
        public WebhookEvent $event,
        public string $resourceId = '',
        public string $resourceType = '',
        public array $data = [],
        public ?DateTimeImmutable $timestamp = null,
        public array $raw = [],
        public array $changes = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            provider: Provider::from($data['provider']),
            event: WebhookEvent::from($data['event']),
            resourceId: $data['resource_id'] ?? '',
            resourceType: $data['resource_type'] ?? '',
            data: $data['data'] ?? [],
            timestamp: isset($data['timestamp']) ? new DateTimeImmutable($data['timestamp']) : null,
            raw: $data['raw'] ?? [],
            changes: $data['changes'] ?? [],
        );
    }

    /**
     * Get what changed (e.g., for file.moved: ['parent_from' => 'old', 'parent_to' => 'new'])
     */
    public function getChange(string $key, mixed $default = null): mixed
    {
        return $this->changes[$key] ?? $default;
    }

    public function hasChanges(): bool
    {
        return $this->changes !== [];
    }

    public function jsonSerialize(): array
    {
        return [
            'provider' => $this->provider->value,
            'event' => $this->event->value,
            'resource_id' => $this->resourceId,
            'resource_type' => $this->resourceType,
            'data' => $this->data,
            'timestamp' => $this->timestamp?->format('c'),
            'changes' => $this->changes,
        ];
    }
}
