<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Exceptions;

use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Exceptions\RateLimitException;
use PHPUnit\Framework\TestCase;

final class RateLimitExceptionTest extends TestCase
{
    public function test_extends_provider_exception(): void
    {
        $e = new RateLimitException('google', 120);

        self::assertInstanceOf(ProviderException::class, $e);
        self::assertSame('google', $e->provider);
        self::assertSame(429, $e->getCode());
        self::assertSame(120, $e->retryAfter);
        self::assertStringContainsString('Rate limit', $e->getMessage());
        self::assertStringContainsString('120s', $e->getMessage());
    }

    public function test_default_retry_after(): void
    {
        $e = new RateLimitException('meta');

        self::assertSame(60, $e->retryAfter);
    }
}
