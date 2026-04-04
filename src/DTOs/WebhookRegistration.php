<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use BridgeKit\Enums\Provider;
use DateTimeImmutable;
use JsonSerializable;

final readonly class WebhookRegistration implements JsonSerializable
{
    public function __construct(
        public string $id,
        public Provider $provider,
        public string $callbackUrl,
        public array $events = [],
        public ?DateTimeImmutable $expiresAt = null,
        public string $secret = '',
        public array $metadata = [],
    ) {}

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider->value,
            'callback_url' => $this->callbackUrl,
            'events' => $this->events,
            'expires_at' => $this->expiresAt?->format('c'),
            'metadata' => $this->metadata,
        ];
    }
}
