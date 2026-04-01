<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Support;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Exceptions\InvalidConfigException;
use BridgeKit\Providers\Google\GoogleProvider;
use BridgeKit\Providers\LinkedIn\LinkedInProvider;
use BridgeKit\Providers\Meta\MetaProvider;
use BridgeKit\Providers\Microsoft\MicrosoftProvider;
use BridgeKit\Providers\X\XProvider;
use BridgeKit\Support\AbstractProvider;
use BridgeKit\Support\ConnectManager;
use PHPUnit\Framework\TestCase;

final class ConnectManagerTest extends TestCase
{
    public function test_constructor_accepts_config_array(): void
    {
        $config = ['providers' => ['google' => ['client_id' => 'x']]];
        $manager = new ConnectManager($config);

        $this->assertInstanceOf(GoogleProvider::class, $manager->provider('google'));
    }

    public function test_provider_returns_google(): void
    {
        $manager = new ConnectManager([]);

        $this->assertInstanceOf(GoogleProvider::class, $manager->provider('google'));
    }

    public function test_provider_returns_microsoft(): void
    {
        $manager = new ConnectManager([]);

        $this->assertInstanceOf(MicrosoftProvider::class, $manager->provider('microsoft'));
    }

    public function test_provider_returns_meta(): void
    {
        $manager = new ConnectManager([]);

        $this->assertInstanceOf(MetaProvider::class, $manager->provider('meta'));
    }

    public function test_provider_returns_linkedin(): void
    {
        $manager = new ConnectManager([]);

        $this->assertInstanceOf(LinkedInProvider::class, $manager->provider('linkedin'));
    }

    public function test_provider_returns_x(): void
    {
        $manager = new ConnectManager([]);

        $this->assertInstanceOf(XProvider::class, $manager->provider('x'));
    }

    public function test_provider_unknown_throws_invalid_config_exception(): void
    {
        $manager = new ConnectManager([]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Unknown provider [unknown]');

        $manager->provider('unknown');
    }

    public function test_google_shortcut_returns_google_provider(): void
    {
        $manager = new ConnectManager([]);

        $this->assertInstanceOf(GoogleProvider::class, $manager->google());
    }

    public function test_microsoft_shortcut_returns_microsoft_provider(): void
    {
        $manager = new ConnectManager([]);

        $this->assertInstanceOf(MicrosoftProvider::class, $manager->microsoft());
    }

    public function test_same_instance_on_repeated_provider_calls(): void
    {
        $manager = new ConnectManager([]);

        $a = $manager->provider('google');
        $b = $manager->provider('google');

        $this->assertSame($a, $b);
    }

    public function test_extend_registers_custom_provider(): void
    {
        $manager = new ConnectManager([]);
        $manager->extend('custom', ConnectManagerStubProvider::class);

        $provider = $manager->provider('custom');

        $this->assertInstanceOf(ConnectManagerStubProvider::class, $provider);
    }

    public function test_get_registered_providers_returns_map_including_extended(): void
    {
        $manager = new ConnectManager([]);
        $manager->extend('custom', ConnectManagerStubProvider::class);

        $map = $manager->getRegisteredProviders();

        $this->assertArrayHasKey('custom', $map);
        $this->assertSame(ConnectManagerStubProvider::class, $map['custom']);
        $this->assertSame(GoogleProvider::class, $map['google']);
    }

    public function test_flush_clears_resolved_instances(): void
    {
        $manager = new ConnectManager([]);
        $first = $manager->provider('google');
        $manager->flush();
        $second = $manager->provider('google');

        $this->assertNotSame($first, $second);
        $this->assertInstanceOf(GoogleProvider::class, $second);
    }
}

final class ConnectManagerStubOAuth implements OAuthInterface
{
    public function getAuthorizationUrl(array $scopes = [], string $state = ''): string
    {
        return '';
    }

    public function handleCallback(string $code): OAuthToken
    {
        throw new \LogicException('not used');
    }

    public function refreshToken(string $refreshToken): OAuthToken
    {
        throw new \LogicException('not used');
    }

    public function revokeToken(string $token): bool
    {
        return false;
    }
}

final class ConnectManagerStubProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'custom';
    }

    public function auth(): OAuthInterface
    {
        return new ConnectManagerStubOAuth();
    }

    public function getAvailableServices(): array
    {
        return [];
    }
}
