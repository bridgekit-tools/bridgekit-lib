<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Sftp;

use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\Providers\Sftp\Services\SftpStorageService;
use BridgeKit\Support\AbstractStorageProvider;

class SftpProvider extends AbstractStorageProvider
{
    public function getName(): string
    {
        return 'sftp';
    }

    public function storage(): FileStorageInterface
    {
        return $this->resolveService('storage', fn () => new SftpStorageService($this->config));
    }

    public function getAvailableServices(): array
    {
        return [
            'storage' => SftpStorageService::class,
        ];
    }
}
