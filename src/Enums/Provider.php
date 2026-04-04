<?php

declare(strict_types=1);

namespace BridgeKit\Enums;

enum Provider: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';
    case Meta = 'meta';
    case LinkedIn = 'linkedin';
    case X = 'x';
    case Ftp = 'ftp';
    case S3 = 's3';
    case Sftp = 'sftp';

    public function isStorageOnly(): bool
    {
        return match ($this) {
            self::Ftp, self::S3, self::Sftp => true,
            default => false,
        };
    }

    public function requiresOAuth(): bool
    {
        return ! $this->isStorageOnly();
    }
}
