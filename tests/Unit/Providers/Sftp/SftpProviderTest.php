<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Providers\Sftp;

use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\Sftp\SftpProvider;
use BridgeKit\Providers\Sftp\Services\SftpStorageService;
use PHPUnit\Framework\TestCase;

final class SftpProviderTest extends TestCase
{
    public function test_name_returns_sftp(): void
    {
        $provider = new SftpProvider();
        self::assertSame('sftp', $provider->getName());
    }

    public function test_storage_returns_sftp_storage_service(): void
    {
        $provider = new SftpProvider(['host' => 'localhost']);
        self::assertInstanceOf(SftpStorageService::class, $provider->storage());
        self::assertInstanceOf(FileStorageInterface::class, $provider->storage());
    }

    public function test_storage_is_cached(): void
    {
        $provider = new SftpProvider();
        self::assertSame($provider->storage(), $provider->storage());
    }

    public function test_auth_throws_provider_exception(): void
    {
        $provider = new SftpProvider();

        $this->expectException(ProviderException::class);
        $provider->auth();
    }

    public function test_get_token_returns_null(): void
    {
        $provider = new SftpProvider();
        self::assertNull($provider->getToken());
    }

    public function test_available_services(): void
    {
        $provider = new SftpProvider();
        $services = $provider->getAvailableServices();

        self::assertCount(1, $services);
        self::assertArrayHasKey('storage', $services);
        self::assertSame(SftpStorageService::class, $services['storage']);
    }
}
