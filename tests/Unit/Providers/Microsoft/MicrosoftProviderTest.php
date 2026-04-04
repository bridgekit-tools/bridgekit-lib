<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Providers\Microsoft;

use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Providers\Microsoft\MicrosoftProvider;
use BridgeKit\Contracts\Calendar\CalendarInterface;
use BridgeKit\Providers\Microsoft\Services\MicrosoftAuthService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftCalendarService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftOneDriveService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftOutlookService;
use PHPUnit\Framework\TestCase;

final class MicrosoftProviderTest extends TestCase
{
    public function test_name_returns_microsoft(): void
    {
        $provider = new MicrosoftProvider();

        self::assertSame('microsoft', $provider->getName());
    }

    public function test_auth_returns_microsoft_auth_service(): void
    {
        $provider = new MicrosoftProvider();

        self::assertInstanceOf(MicrosoftAuthService::class, $provider->auth());
    }

    public function test_onedrive_returns_onedrive_service(): void
    {
        $provider = new MicrosoftProvider();

        self::assertInstanceOf(MicrosoftOneDriveService::class, $provider->onedrive());
    }

    public function test_outlook_returns_outlook_service(): void
    {
        $provider = new MicrosoftProvider();

        self::assertInstanceOf(MicrosoftOutlookService::class, $provider->outlook());
    }

    public function test_calendar_returns_calendar_interface(): void
    {
        $provider = new MicrosoftProvider();

        self::assertInstanceOf(CalendarInterface::class, $provider->calendar());
        self::assertInstanceOf(MicrosoftCalendarService::class, $provider->calendar());
    }

    public function test_webhooks_returns_webhook_service(): void
    {
        $provider = new MicrosoftProvider();

        self::assertInstanceOf(\BridgeKit\Contracts\Webhook\WebhookInterface::class, $provider->webhooks());
    }

    public function test_available_services(): void
    {
        $provider = new MicrosoftProvider();

        self::assertCount(5, $provider->getAvailableServices());
        self::assertArrayHasKey('webhooks', $provider->getAvailableServices());
    }

    public function test_tenant_defaults_to_common(): void
    {
        $provider = new MicrosoftProvider();

        self::assertSame('common', $provider->getTenant());
    }

    public function test_tenant_from_config(): void
    {
        $provider = new MicrosoftProvider(['tenant' => 'contoso.onmicrosoft.com']);

        self::assertSame('contoso.onmicrosoft.com', $provider->getTenant());
    }

    public function test_set_and_get_token(): void
    {
        $provider = new MicrosoftProvider();
        $token = new OAuthToken(accessToken: 'test-token', refreshToken: 'test-refresh');

        $provider->setToken($token);

        self::assertSame($token, $provider->getToken());
        self::assertSame('test-token', $provider->getToken()?->accessToken);
        self::assertSame('test-refresh', $provider->getToken()?->refreshToken);
    }
}
