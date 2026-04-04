<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Meta;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\Contracts\Social\PostPublisherInterface;
use BridgeKit\Contracts\Webhook\WebhookInterface;
use BridgeKit\Providers\Meta\Services\MetaAuthService;
use BridgeKit\Providers\Meta\Services\MetaPostsService;
use BridgeKit\Providers\Meta\Services\MetaWebhookService;
use BridgeKit\Support\AbstractProvider;

class MetaProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'meta';
    }

    public function auth(): OAuthInterface
    {
        return $this->resolveService('auth', fn () => new MetaAuthService($this->config, $this));
    }

    public function posts(): PostPublisherInterface
    {
        return $this->resolveService('posts', fn () => new MetaPostsService($this->config, $this));
    }

    public function webhooks(): WebhookInterface
    {
        return $this->resolveService('webhooks', fn () => new MetaWebhookService($this));
    }

    public function getAvailableServices(): array
    {
        return [
            'auth' => MetaAuthService::class,
            'posts' => MetaPostsService::class,
            'webhooks' => MetaWebhookService::class,
        ];
    }
}
