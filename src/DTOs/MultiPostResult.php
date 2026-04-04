<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use BridgeKit\Enums\Provider;
use JsonSerializable;

final readonly class MultiPostResult implements JsonSerializable
{
    /**
     * @param  array<string, SocialPostResult>  $succeeded  provider => result
     * @param  array<string, \Throwable>  $failed  provider => exception
     */
    public function __construct(
        public array $succeeded = [],
        public array $failed = [],
    ) {}

    public function isFullSuccess(): bool
    {
        return $this->failed === [];
    }

    public function isPartialSuccess(): bool
    {
        return $this->succeeded !== [] && $this->failed !== [];
    }

    public function isFullFailure(): bool
    {
        return $this->succeeded === [] && $this->failed !== [];
    }

    public function getResult(Provider|string $provider): ?SocialPostResult
    {
        $key = $provider instanceof Provider ? $provider->value : $provider;

        return $this->succeeded[$key] ?? null;
    }

    public function getError(Provider|string $provider): ?\Throwable
    {
        $key = $provider instanceof Provider ? $provider->value : $provider;

        return $this->failed[$key] ?? null;
    }

    /**
     * @return array<string>
     */
    public function succeededProviders(): array
    {
        return array_keys($this->succeeded);
    }

    /**
     * @return array<string>
     */
    public function failedProviders(): array
    {
        return array_keys($this->failed);
    }

    public function jsonSerialize(): array
    {
        $failed = [];
        foreach ($this->failed as $provider => $exception) {
            $failed[$provider] = [
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ];
        }

        return [
            'succeeded' => $this->succeeded,
            'failed' => $failed,
            'total' => count($this->succeeded) + count($this->failed),
            'success_count' => count($this->succeeded),
            'failure_count' => count($this->failed),
        ];
    }
}
