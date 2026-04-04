<?php

declare(strict_types=1);

namespace BridgeKit\Exceptions;

class RateLimitException extends ProviderException
{
    public function __construct(
        string $provider = '',
        public readonly int $retryAfter = 60,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: "Rate limit exceeded for provider [{$provider}]. Retry after {$retryAfter}s.",
            provider: $provider,
            code: 429,
            previous: $previous,
        );
    }
}
