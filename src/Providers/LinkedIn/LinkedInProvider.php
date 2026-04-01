<?php

declare(strict_types=1);

namespace BridgeKit\Providers\LinkedIn;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\Contracts\Social\PostPublisherInterface;
use BridgeKit\Providers\LinkedIn\Services\LinkedInAuthService;
use BridgeKit\Providers\LinkedIn\Services\LinkedInPostsService;
use BridgeKit\Support\AbstractProvider;

class LinkedInProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'linkedin';
    }

    public function auth(): OAuthInterface
    {
        return $this->resolveService('auth', fn () => new LinkedInAuthService($this->config, $this));
    }

    public function posts(): PostPublisherInterface
    {
        return $this->resolveService('posts', fn () => new LinkedInPostsService($this->config, $this));
    }

    public function getAvailableServices(): array
    {
        return [
            'auth' => LinkedInAuthService::class,
            'posts' => LinkedInPostsService::class,
        ];
    }
}
