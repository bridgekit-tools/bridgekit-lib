<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Providers\S3;

use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\S3\S3Provider;
use BridgeKit\Providers\S3\Services\S3StorageService;
use PHPUnit\Framework\TestCase;

final class S3ProviderTest extends TestCase
{
    public function test_name_returns_s3(): void
    {
        $provider = new S3Provider();
        self::assertSame('s3', $provider->getName());
    }

    public function test_storage_returns_s3_storage_service(): void
    {
        $provider = new S3Provider([
            'bucket' => 'test-bucket',
            'key' => 'AKID',
            'secret' => 'secret',
            'region' => 'us-east-1',
        ]);

        self::assertInstanceOf(S3StorageService::class, $provider->storage());
    }

    public function test_storage_throws_without_required_config(): void
    {
        $provider = new S3Provider([]);

        $this->expectException(ProviderException::class);
        $provider->storage();
    }

    public function test_auth_throws(): void
    {
        $provider = new S3Provider();

        $this->expectException(ProviderException::class);
        $provider->auth();
    }

    public function test_available_services(): void
    {
        $provider = new S3Provider();
        $services = $provider->getAvailableServices();

        self::assertCount(1, $services);
        self::assertArrayHasKey('storage', $services);
    }
}
