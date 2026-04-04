<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Enums;

use BridgeKit\Enums\Provider;
use PHPUnit\Framework\TestCase;

final class ProviderTest extends TestCase
{
    public function test_storage_only_providers(): void
    {
        self::assertTrue(Provider::Ftp->isStorageOnly());
        self::assertTrue(Provider::S3->isStorageOnly());
        self::assertTrue(Provider::Sftp->isStorageOnly());
    }

    public function test_oauth_providers_are_not_storage_only(): void
    {
        self::assertFalse(Provider::Google->isStorageOnly());
        self::assertFalse(Provider::Microsoft->isStorageOnly());
        self::assertFalse(Provider::Meta->isStorageOnly());
        self::assertFalse(Provider::LinkedIn->isStorageOnly());
        self::assertFalse(Provider::X->isStorageOnly());
    }

    public function test_requires_oauth(): void
    {
        self::assertTrue(Provider::Google->requiresOAuth());
        self::assertFalse(Provider::Ftp->requiresOAuth());
        self::assertFalse(Provider::S3->requiresOAuth());
        self::assertFalse(Provider::Sftp->requiresOAuth());
    }

    public function test_backed_values(): void
    {
        self::assertSame('ftp', Provider::Ftp->value);
        self::assertSame('s3', Provider::S3->value);
        self::assertSame('sftp', Provider::Sftp->value);
    }
}
