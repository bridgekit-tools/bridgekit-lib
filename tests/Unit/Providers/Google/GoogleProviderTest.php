<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Providers\Google;

use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Providers\Google\GoogleProvider;
use BridgeKit\Providers\Google\Services\GoogleAuthService;
use BridgeKit\Contracts\Calendar\CalendarInterface;
use BridgeKit\Providers\Google\Services\GoogleCalendarService;
use BridgeKit\Providers\Google\Services\GoogleDriveService;
use BridgeKit\Providers\Google\Services\GoogleGmailService;
use PHPUnit\Framework\TestCase;

final class GoogleProviderTest extends TestCase
{
    public function test_name_returns_google(): void
    {
        $provider = new GoogleProvider();

        self::assertSame('google', $provider->getName());
    }

    public function test_auth_returns_google_auth_service(): void
    {
        $provider = new GoogleProvider();

        self::assertInstanceOf(GoogleAuthService::class, $provider->auth());
    }

    public function test_drive_returns_google_drive_service(): void
    {
        $provider = new GoogleProvider();

        self::assertInstanceOf(GoogleDriveService::class, $provider->drive());
    }

    public function test_gmail_returns_google_gmail_service(): void
    {
        $provider = new GoogleProvider();

        self::assertInstanceOf(GoogleGmailService::class, $provider->gmail());
    }

    public function test_calendar_returns_calendar_interface(): void
    {
        $provider = new GoogleProvider();

        self::assertInstanceOf(CalendarInterface::class, $provider->calendar());
        self::assertInstanceOf(GoogleCalendarService::class, $provider->calendar());
    }

    public function test_available_services(): void
    {
        $provider = new GoogleProvider();

        self::assertCount(4, $provider->getAvailableServices());
    }

    public function test_services_are_cached(): void
    {
        $provider = new GoogleProvider();

        self::assertSame($provider->drive(), $provider->drive());
    }

    public function test_set_and_get_token(): void
    {
        $provider = new GoogleProvider();
        $token = new OAuthToken(accessToken: 'test-token', refreshToken: 'test-refresh');

        $provider->setToken($token);

        self::assertSame($token, $provider->getToken());
        self::assertSame('test-token', $provider->getToken()?->accessToken);
        self::assertSame('test-refresh', $provider->getToken()?->refreshToken);
    }

    public function test_token_is_null_by_default(): void
    {
        $provider = new GoogleProvider();

        self::assertNull($provider->getToken());
    }
}
