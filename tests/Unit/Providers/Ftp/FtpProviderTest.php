<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Providers\Ftp;

use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\Ftp\FtpProvider;
use BridgeKit\Providers\Ftp\Services\FtpStorageService;
use PHPUnit\Framework\TestCase;

final class FtpProviderTest extends TestCase
{
    public function test_name_returns_ftp(): void
    {
        $provider = new FtpProvider();
        self::assertSame('ftp', $provider->getName());
    }

    public function test_storage_returns_ftp_storage_service(): void
    {
        $provider = new FtpProvider(['host' => 'localhost']);
        self::assertInstanceOf(FtpStorageService::class, $provider->storage());
        self::assertInstanceOf(FileStorageInterface::class, $provider->storage());
    }

    public function test_storage_is_cached(): void
    {
        $provider = new FtpProvider();
        self::assertSame($provider->storage(), $provider->storage());
    }

    public function test_auth_throws_provider_exception(): void
    {
        $provider = new FtpProvider();

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('credentials, not OAuth');

        $provider->auth();
    }

    public function test_get_token_returns_null(): void
    {
        $provider = new FtpProvider();
        self::assertNull($provider->getToken());
    }

    public function test_available_services(): void
    {
        $provider = new FtpProvider();
        $services = $provider->getAvailableServices();

        self::assertCount(1, $services);
        self::assertArrayHasKey('storage', $services);
        self::assertSame(FtpStorageService::class, $services['storage']);
    }
}
