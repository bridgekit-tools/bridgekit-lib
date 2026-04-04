<?php

declare(strict_types=1);

namespace BridgeKit\Providers\S3;

use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\Providers\S3\Services\S3StorageService;
use BridgeKit\Support\AbstractStorageProvider;

class S3Provider extends AbstractStorageProvider
{
    public function getName(): string
    {
        return 's3';
    }

    public function storage(): FileStorageInterface
    {
        return $this->resolveService('storage', fn () => new S3StorageService($this->config));
    }

    public function getAvailableServices(): array
    {
        return [
            'storage' => S3StorageService::class,
        ];
    }
}
