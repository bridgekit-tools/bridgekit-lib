<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use DateTimeImmutable;
use JsonSerializable;

final readonly class OAuthToken implements JsonSerializable
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken = '',
        public string $tokenType = 'Bearer',
        public ?int $expiresIn = null,
        public ?DateTimeImmutable $expiresAt = null,
        public array $scopes = [],
    ) {}

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt <= new DateTimeImmutable();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public static function fromArray(array $data): self
    {
        $expiresAt = null;
        if (isset($data['expires_in'])) {
            $expiresAt = (new DateTimeImmutable())->modify("+{$data['expires_in']} seconds");
        } elseif (isset($data['expires_at'])) {
            $expiresAt = new DateTimeImmutable($data['expires_at']);
        }

        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? '',
            tokenType: $data['token_type'] ?? 'Bearer',
            expiresIn: $data['expires_in'] ?? null,
            expiresAt: $expiresAt,
            scopes: $data['scope'] ?? $data['scopes'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
            'expires_at' => $this->expiresAt?->format('c'),
            'scopes' => $this->scopes,
        ];
    }
}
