<?php

declare(strict_types=1);

namespace BridgeKit\Tests;

use BridgeKit\BridgeKitServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [BridgeKitServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'BridgeKit' => \BridgeKit\Facades\BridgeKit::class,
        ];
    }
}
