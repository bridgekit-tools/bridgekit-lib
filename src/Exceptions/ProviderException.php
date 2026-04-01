<?php

declare(strict_types=1);

namespace BridgeKit\Exceptions;

class ProviderException extends BridgeKitException
{
    public function __construct(
        string $message,
        public readonly string $provider = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
