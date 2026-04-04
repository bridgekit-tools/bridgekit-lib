<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Dropbox;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\Providers\Dropbox\Services\DropboxAuthService;
use BridgeKit\Providers\Dropbox\Services\DropboxStorageService;
use BridgeKit\Support\AbstractProvider;

class DropboxProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'dropbox';
    }

    public function auth(): OAuthInterface
    {
        return $this->resolveService('auth', fn () => new DropboxAuthService($this->config, $this));
    }

    public function storage(): FileStorageInterface
    {
        return $this->resolveService('storage', fn () => new DropboxStorageService($this));
    }

    public function getAvailableServices(): array
    {
        return [
            'auth' => DropboxAuthService::class,
            'storage' => DropboxStorageService::class,
        ];
    }
}
