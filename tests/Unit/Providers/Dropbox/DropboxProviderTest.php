<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Providers\Dropbox;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\Providers\Dropbox\DropboxProvider;
use BridgeKit\Providers\Dropbox\Services\DropboxAuthService;
use BridgeKit\Providers\Dropbox\Services\DropboxStorageService;
use PHPUnit\Framework\TestCase;

final class DropboxProviderTest extends TestCase
{
    public function test_name_returns_dropbox(): void
    {
        $provider = new DropboxProvider();
        self::assertSame('dropbox', $provider->getName());
    }

    public function test_auth_returns_oauth_interface(): void
    {
        $provider = new DropboxProvider();
        self::assertInstanceOf(OAuthInterface::class, $provider->auth());
        self::assertInstanceOf(DropboxAuthService::class, $provider->auth());
    }

    public function test_storage_returns_file_storage_interface(): void
    {
        $provider = new DropboxProvider();
        self::assertInstanceOf(FileStorageInterface::class, $provider->storage());
        self::assertInstanceOf(DropboxStorageService::class, $provider->storage());
    }

    public function test_services_are_cached(): void
    {
        $provider = new DropboxProvider();
        self::assertSame($provider->storage(), $provider->storage());
        self::assertSame($provider->auth(), $provider->auth());
    }

    public function test_available_services(): void
    {
        $provider = new DropboxProvider();
        $services = $provider->getAvailableServices();

        self::assertCount(2, $services);
        self::assertArrayHasKey('auth', $services);
        self::assertArrayHasKey('storage', $services);
    }
}
