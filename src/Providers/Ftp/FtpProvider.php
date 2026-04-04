<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Ftp;

use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\Providers\Ftp\Services\FtpStorageService;
use BridgeKit\Support\AbstractStorageProvider;

class FtpProvider extends AbstractStorageProvider
{
    public function getName(): string
    {
        return 'ftp';
    }

    public function storage(): FileStorageInterface
    {
        return $this->resolveService('storage', fn () => new FtpStorageService($this->config));
    }

    public function getAvailableServices(): array
    {
        return [
            'storage' => FtpStorageService::class,
        ];
    }
}
