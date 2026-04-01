<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Providers\Meta;

use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Providers\Meta\MetaProvider;
use BridgeKit\Providers\Meta\Services\MetaAuthService;
use BridgeKit\Providers\Meta\Services\MetaPostsService;
use PHPUnit\Framework\TestCase;

final class MetaProviderTest extends TestCase
{
    public function test_name_returns_meta(): void
    {
        $provider = new MetaProvider();

        self::assertSame('meta', $provider->getName());
    }

    public function test_auth_returns_meta_auth_service(): void
    {
        $provider = new MetaProvider();

        self::assertInstanceOf(MetaAuthService::class, $provider->auth());
    }

    public function test_posts_returns_meta_posts_service(): void
    {
        $provider = new MetaProvider();

        self::assertInstanceOf(MetaPostsService::class, $provider->posts());
    }

    public function test_available_services(): void
    {
        $provider = new MetaProvider();

        self::assertCount(2, $provider->getAvailableServices());
    }

    public function test_set_and_get_token(): void
    {
        $provider = new MetaProvider();
        $token = new OAuthToken(accessToken: 'test-token', refreshToken: 'test-refresh');

        $provider->setToken($token);

        self::assertSame($token, $provider->getToken());
        self::assertSame('test-token', $provider->getToken()?->accessToken);
        self::assertSame('test-refresh', $provider->getToken()?->refreshToken);
    }
}
