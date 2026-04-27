<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Sftp\Services;

use BridgeKit\Concerns\BuildsFileTree;
use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\DTOs\StorageFile;
use BridgeKit\Exceptions\ProviderException;
use DateTimeImmutable;
use Generator;

class SftpStorageService implements FileStorageInterface
{
    use BuildsFileTree;

    /** @var resource|null */
    private mixed $sshConnection = null;

    /** @var resource|null */
    private mixed $sftp = null;

    public function __construct(
        private readonly array $config,
    ) {}

    public function listFiles(string $folderId = '', array $options = []): array
    {
        return iterator_to_array($this->listFilesLazy($folderId, $options), false);
    }

    public function listFilesLazy(string $folderId = '', array $options = []): Generator
    {
        $sftp = $this->connect();
        $path = $folderId !== '' ? $folderId : ($this->config['root'] ?? '/');
        $handle = @opendir("ssh2.sftp://{$sftp}{$path}");

        if ($handle === false) {
            return;
        }

        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = rtrim($path, '/') . '/' . $entry;
            $stat = @\ssh2_sftp_stat($sftp, $fullPath);

            $isDir = $stat !== false && ($stat['mode'] & 0040000) !== 0;
            $size = $stat['size'] ?? 0;
            $mtime = $stat['mtime'] ?? 0;

            yield new StorageFile(
                id: $fullPath,
                name: $entry,
                mimeType: $isDir ? 'directory' : '',
                size: (int) $size,
                isFolder: $isDir,
                parentId: $path,
                modifiedAt: $mtime > 0 ? (new DateTimeImmutable())->setTimestamp($mtime) : null,
            );
        }

        closedir($handle);
    }

    public function getFile(string $fileId): StorageFile
    {
        $sftp = $this->connect();
        $stat = @\ssh2_sftp_stat($sftp, $fileId);

        if ($stat === false) {
            throw new ProviderException("SFTP: file not found: {$fileId}", 'sftp');
        }

        $isDir = ($stat['mode'] & 0040000) !== 0;

        return new StorageFile(
            id: $fileId,
            name: basename($fileId),
            mimeType: $isDir ? 'directory' : '',
            size: (int) ($stat['size'] ?? 0),
            isFolder: $isDir,
            parentId: dirname($fileId),
            modifiedAt: isset($stat['mtime']) && $stat['mtime'] > 0
                ? (new DateTimeImmutable())->setTimestamp($stat['mtime'])
                : null,
        );
    }

    public function uploadFile(string $name, mixed $content, string $mimeType = '', string $folderId = ''): StorageFile
    {
        $sftp = $this->connect();
        $remotePath = ($folderId !== '' ? rtrim($folderId, '/') . '/' : '') . $name;
        $data = is_string($content) ? $content : (stream_get_contents($content) ?: '');

        $stream = @fopen("ssh2.sftp://{$sftp}{$remotePath}", 'wb');
        if ($stream === false) {
            throw new ProviderException("SFTP: cannot open remote file for write: {$remotePath}", 'sftp');
        }

        fwrite($stream, $data);
        fclose($stream);

        return new StorageFile(
            id: $remotePath,
            name: $name,
            mimeType: $mimeType,
            size: strlen($data),
            isFolder: false,
            parentId: $folderId,
        );
    }

    public function uploadLargeFile(string $name, mixed $filePathOrStream, string $mimeType = '', string $folderId = '', int $chunkSize = 5 * 1024 * 1024): StorageFile
    {
        $sftp = $this->connect();
        $remotePath = ($folderId !== '' ? rtrim($folderId, '/') . '/' : '') . $name;

        $source = is_string($filePathOrStream) ? fopen($filePathOrStream, 'rb') : $filePathOrStream;
        if (! is_resource($source)) {
            throw new ProviderException('Cannot open file for SFTP upload.', 'sftp');
        }

        $dest = @fopen("ssh2.sftp://{$sftp}{$remotePath}", 'wb');
        if ($dest === false) {
            throw new ProviderException("SFTP: cannot open remote file: {$remotePath}", 'sftp');
        }

        $totalSize = 0;
        while (! feof($source)) {
            $chunk = fread($source, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
            fwrite($dest, $chunk);
            $totalSize += strlen($chunk);
        }

        fclose($dest);
        if (is_string($filePathOrStream)) {
            fclose($source);
        }

        return new StorageFile(
            id: $remotePath,
            name: $name,
            mimeType: $mimeType,
            size: $totalSize,
            isFolder: false,
            parentId: $folderId,
        );
    }

    public function downloadFile(string $fileId): string
    {
        $sftp = $this->connect();
        $stream = @fopen("ssh2.sftp://{$sftp}{$fileId}", 'rb');

        if ($stream === false) {
            throw new ProviderException("SFTP: cannot read file: {$fileId}", 'sftp');
        }

        $content = stream_get_contents($stream);
        fclose($stream);

        return $content !== false ? $content : '';
    }

    public function downloadStream(string $fileId): mixed
    {
        $sftp = $this->connect();
        $source = @fopen("ssh2.sftp://{$sftp}{$fileId}", 'rb');

        if ($source === false) {
            throw new ProviderException("SFTP: cannot read file: {$fileId}", 'sftp');
        }

        $tmpStream = fopen('php://temp', 'r+b');
        if ($tmpStream === false) {
            fclose($source);
            throw new ProviderException('Cannot create temp stream.', 'sftp');
        }

        stream_copy_to_stream($source, $tmpStream);
        fclose($source);
        rewind($tmpStream);

        return $tmpStream;
    }

    public function deleteFile(string $fileId): bool
    {
        $sftp = $this->connect();
        $stat = @\ssh2_sftp_stat($sftp, $fileId);

        if ($stat === false) {
            return false;
        }

        if (($stat['mode'] & 0040000) !== 0) {
            return @\ssh2_sftp_rmdir($sftp, $fileId);
        }

        return @\ssh2_sftp_unlink($sftp, $fileId);
    }

    public function createFolder(string $name, string $parentId = ''): StorageFile
    {
        $sftp = $this->connect();
        $path = ($parentId !== '' ? rtrim($parentId, '/') . '/' : '') . $name;

        if (! @\ssh2_sftp_mkdir($sftp, $path, 0755, true)) {
            throw new ProviderException("SFTP: mkdir failed: {$path}", 'sftp');
        }

        return new StorageFile(
            id: $path,
            name: $name,
            mimeType: 'directory',
            size: 0,
            isFolder: true,
            parentId: $parentId,
        );
    }

    public function searchFiles(string $query, array $options = []): array
    {
        $root = $options['path'] ?? $this->config['root'] ?? '/';
        $results = [];

        foreach ($this->listFilesLazy($root) as $file) {
            if (stripos($file->name, $query) !== false) {
                $results[] = $file;
            }
        }

        return $results;
    }

    /** @return resource SSH2 SFTP subsystem resource */
    private function connect(): mixed
    {
        if ($this->sftp !== null) {
            return $this->sftp;
        }

        $host = $this->config['host'] ?? 'localhost';
        $port = (int) ($this->config['port'] ?? 22);

        $conn = @\ssh2_connect($host, $port);
        if ($conn === false) {
            throw new ProviderException("Cannot connect to SFTP server {$host}:{$port}", 'sftp');
        }

        $privateKey = $this->config['private_key'] ?? null;
        $passphrase = $this->config['passphrase'] ?? null;

        if ($privateKey !== null) {
            $publicKey = $this->config['public_key'] ?? $privateKey . '.pub';
            if (! @\ssh2_auth_pubkey_file($conn, $this->config['username'] ?? '', $publicKey, $privateKey, $passphrase ?? '')) {
                throw new ProviderException('SFTP public key authentication failed.', 'sftp');
            }
        } else {
            $username = $this->config['username'] ?? '';
            $password = $this->config['password'] ?? '';
            if (! @\ssh2_auth_password($conn, $username, $password)) {
                throw new ProviderException("SFTP password authentication failed for user {$username}", 'sftp');
            }
        }

        $sftp = @\ssh2_sftp($conn);
        if ($sftp === false) {
            throw new ProviderException('Could not initialize SFTP subsystem.', 'sftp');
        }

        $this->sshConnection = $conn;
        $this->sftp = $sftp;

        return $sftp;
    }
}
