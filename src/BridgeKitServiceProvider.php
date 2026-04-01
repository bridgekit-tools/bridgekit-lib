<?php

declare(strict_types=1);

namespace BridgeKit;

use BridgeKit\Support\ConnectManager;
use Illuminate\Support\ServiceProvider;

class BridgeKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bridgekit.php', 'bridgekit');

        $this->app->singleton(ConnectManager::class, function ($app) {
            return new ConnectManager($app['config']->get('bridgekit', []));
        });

        $this->app->alias(ConnectManager::class, 'bridgekit');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bridgekit.php' => config_path('bridgekit.php'),
            ], 'bridgekit-config');
        }
    }
}
