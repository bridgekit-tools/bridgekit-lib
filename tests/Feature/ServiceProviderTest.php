<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Feature;

use BridgeKit\Facades\BridgeKit;
use BridgeKit\Support\ConnectManager;
use BridgeKit\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        self::assertNotNull(config('bridgekit.providers.google'));
    }

    public function test_connect_manager_is_bound(): void
    {
        self::assertInstanceOf(ConnectManager::class, app(ConnectManager::class));
    }

    public function test_alias_is_registered(): void
    {
        self::assertInstanceOf(ConnectManager::class, app('bridgekit'));
    }

    public function test_facade_works(): void
    {
        self::assertCount(9, BridgeKit::getRegisteredProviders());
    }
}
