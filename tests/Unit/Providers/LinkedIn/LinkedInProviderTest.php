<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Providers\LinkedIn;

use BridgeKit\Providers\LinkedIn\LinkedInProvider;
use BridgeKit\Providers\LinkedIn\Services\LinkedInAuthService;
use BridgeKit\Providers\LinkedIn\Services\LinkedInPostsService;
use PHPUnit\Framework\TestCase;

final class LinkedInProviderTest extends TestCase
{
    public function test_name_returns_linkedin(): void
    {
        $provider = new LinkedInProvider();

        self::assertSame('linkedin', $provider->getName());
    }

    public function test_auth_returns_linkedin_auth_service(): void
    {
        $provider = new LinkedInProvider();

        self::assertInstanceOf(LinkedInAuthService::class, $provider->auth());
    }

    public function test_posts_returns_linkedin_posts_service(): void
    {
        $provider = new LinkedInProvider();

        self::assertInstanceOf(LinkedInPostsService::class, $provider->posts());
    }

    public function test_available_services(): void
    {
        $provider = new LinkedInProvider();

        self::assertCount(2, $provider->getAvailableServices());
    }
}
