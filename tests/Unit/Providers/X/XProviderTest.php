<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Providers\X;

use BridgeKit\Providers\X\Services\XAuthService;
use BridgeKit\Providers\X\Services\XPostsService;
use BridgeKit\Providers\X\XProvider;
use PHPUnit\Framework\TestCase;

final class XProviderTest extends TestCase
{
    public function test_name_returns_x(): void
    {
        $provider = new XProvider();

        self::assertSame('x', $provider->getName());
    }

    public function test_auth_returns_x_auth_service(): void
    {
        $provider = new XProvider();

        self::assertInstanceOf(XAuthService::class, $provider->auth());
    }

    public function test_posts_returns_x_posts_service(): void
    {
        $provider = new XProvider();

        self::assertInstanceOf(XPostsService::class, $provider->posts());
    }

    public function test_webhooks_returns_webhook_service(): void
    {
        $provider = new XProvider();

        self::assertInstanceOf(\BridgeKit\Contracts\Webhook\WebhookInterface::class, $provider->webhooks());
    }

    public function test_available_services(): void
    {
        $provider = new XProvider();

        self::assertCount(3, $provider->getAvailableServices());
        self::assertArrayHasKey('webhooks', $provider->getAvailableServices());
    }
}
