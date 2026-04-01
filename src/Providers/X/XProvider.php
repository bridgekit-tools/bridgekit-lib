<?php

declare(strict_types=1);

namespace BridgeKit\Providers\X;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\Contracts\Social\PostPublisherInterface;
use BridgeKit\Providers\X\Services\XAuthService;
use BridgeKit\Providers\X\Services\XPostsService;
use BridgeKit\Support\AbstractProvider;

class XProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'x';
    }

    public function auth(): OAuthInterface
    {
        return $this->resolveService('auth', fn () => new XAuthService($this->config, $this));
    }

    public function posts(): PostPublisherInterface
    {
        return $this->resolveService('posts', fn () => new XPostsService($this->config, $this));
    }

    public function getAvailableServices(): array
    {
        return [
            'auth' => XAuthService::class,
            'posts' => XPostsService::class,
        ];
    }
}
