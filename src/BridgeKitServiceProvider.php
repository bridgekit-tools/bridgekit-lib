<?php

declare(strict_types=1);

namespace BridgeKit;

use BridgeKit\Support\ConnectManager;
use BridgeKit\Webhooks\WebhookController;
use BridgeKit\Webhooks\WebhookProcessor;
use Illuminate\Support\Facades\Route;
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

        $this->app->singleton(WebhookProcessor::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bridgekit.php' => config_path('bridgekit.php'),
            ], 'bridgekit-config');
        }

        $this->registerWebhookRoutes();
    }

    private function registerWebhookRoutes(): void
    {
        $webhookConfig = $this->app['config']->get('bridgekit.webhooks', []);

        if (! ($webhookConfig['enabled'] ?? true)) {
            return;
        }

        $prefix = $webhookConfig['path'] ?? 'webhooks/bridgekit';
        $middleware = $webhookConfig['middleware'] ?? [];

        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(function () {
                Route::match(['get', 'post'], '/{provider}', WebhookController::class)
                    ->name('bridgekit.webhooks');
            });
    }
}
