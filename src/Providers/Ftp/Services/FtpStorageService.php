<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Ftp\Services;

use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\DTOs\StorageFile;
use BridgeKit\Exceptions\ProviderException;
use DateTimeImmutable;
use Generator;

class FtpStorageService implements FileStorageInterface
{
    /** @var \FTP\Connection|null */
    private mixed $connection = null;

    public function __construct(
        private readonly array $config,
    ) {}

    public function listFiles(string $folderId = '', array $options = []): array
    {
        return iterator_to_array($this->listFilesLazy($folderId, $options), false);
    }

    public function listFilesLazy(string $folderId = '', array $options = []): Generator
    {
        $conn = $this->connect();
        $path = $folderId !== '' ? $folderId : ($this->config['root'] ?? '/');
        $list = @ftp_mlsd($conn, $path);

        if ($list === false) {
            $raw = @ftp_nlist($conn, $path);
            if (! is_array($raw)) {
                return;
            }
            foreach ($raw as $name) {
                $basename = basename($name);
                if ($basename === '.' || $basename === '..') {
                    continue;
                }
                yield new StorageFile(
                    id: ltrim($path . '/' . $basename, '/'),
                    name: $basename,
                    mimeType: '',
                    size: (int) @ftp_size($conn, $path . '/' . $basename),
                    isFolder: @ftp_size($conn, $path . '/' . $basename) === -1,
                    parentId: $path,
                );
            }

            return;
        }

        foreach ($list as $entry) {
            $name = $entry['name'] ?? '';
            if ($name === '.' || $name === '..') {
                continue;
            }

            $fullPath = ltrim($path . '/' . $name, '/');
            $type = $entry['type'] ?? '';
            $isDir = $type === 'dir' || $type === 'cdir' || $type === 'pdir';
            $modifiedAt = isset($entry['modify']) ? DateTimeImmutable::createFromFormat('YmdHis', $entry['modify']) : null;

            yield new StorageFile(
                id: $fullPath,
                name: $name,
                mimeType: $isDir ? 'directory' : '',
                size: (int) ($entry['size'] ?? 0),
                isFolder: $isDir,
                parentId: $path,
                modifiedAt: $modifiedAt ?: null,
            );
        }
    }

    public function getFile(string $fileId): StorageFile
    {
        $conn = $this->connect();
        $size = @ftp_size($conn, $fileId);
        $mdtm = @ftp_mdtm($conn, $fileId);

        return new StorageFile(
            id: $fileId,
            name: basename($fileId),
            mimeType: '',
            size: $size >= 0 ? $size : 0,
            isFolder: $size === -1,
            parentId: dirname($fileId),
            modifiedAt: $mdtm > 0 ? (new DateTimeImmutable())->setTimestamp($mdtm) : null,
        );
    }

    public function uploadFile(string $name, mixed $content, string $mimeType = '', string $folderId = ''): StorageFile
    {
        $conn = $this->connect();
        $remotePath = ($folderId !== '' ? rtrim($folderId, '/') . '/' : '') . $name;

        $tmpStream = fopen('php://temp', 'r+b');
        if ($tmpStream === false) {
            throw new ProviderException('Cannot create temp stream.', 'ftp');
        }

        $data = is_string($content) ? $content : stream_get_contents($content);
        fwrite($tmpStream, $data ?: '');
        rewind($tmpStream);

        if (! @ftp_fput($conn, $remotePath, $tmpStream, FTP_BINARY)) {
            fclose($tmpStream);
            throw new ProviderException("FTP upload failed: {$remotePath}", 'ftp');
        }

        fclose($tmpStream);

        return new StorageFile(
            id: $remotePath,
            name: $name,
            mimeType: $mimeType,
            size: strlen($data ?: ''),
            isFolder: false,
            parentId: $folderId,
        );
    }

    public function uploadLargeFile(string $name, mixed $filePathOrStream, string $mimeType = '', string $folderId = '', int $chunkSize = 5 * 1024 * 1024): StorageFile
    {
        $conn = $this->connect();
        $remotePath = ($folderId !== '' ? rtrim($folderId, '/') . '/' : '') . $name;

        $stream = is_string($filePathOrStream) ? fopen($filePathOrStream, 'rb') : $filePathOrStream;
        if (! is_resource($stream)) {
            throw new ProviderException('Cannot open file for FTP upload.', 'ftp');
        }

        if (! @ftp_fput($conn, $remotePath, $stream, FTP_BINARY)) {
            throw new ProviderException("FTP upload failed: {$remotePath}", 'ftp');
        }

        $size = @ftp_size($conn, $remotePath);
        if (is_string($filePathOrStream)) {
            fclose($stream);
        }

        return new StorageFile(
            id: $remotePath,
            name: $name,
            mimeType: $mimeType,
            size: $size >= 0 ? $size : 0,
            isFolder: false,
            parentId: $folderId,
        );
    }

    public function downloadFile(string $fileId): string
    {
        $conn = $this->connect();
        $tmpStream = fopen('php://temp', 'r+b');

        if ($tmpStream === false || ! @ftp_fget($conn, $tmpStream, $fileId, FTP_BINARY)) {
            throw new ProviderException("FTP download failed: {$fileId}", 'ftp');
        }

        rewind($tmpStream);
        $content = stream_get_contents($tmpStream);
        fclose($tmpStream);

        return $content !== false ? $content : '';
    }

    public function downloadStream(string $fileId): mixed
    {
        $conn = $this->connect();
        $tmpStream = fopen('php://temp', 'r+b');

        if ($tmpStream === false || ! @ftp_fget($conn, $tmpStream, $fileId, FTP_BINARY)) {
            throw new ProviderException("FTP download failed: {$fileId}", 'ftp');
        }

        rewind($tmpStream);

        return $tmpStream;
    }

    public function deleteFile(string $fileId): bool
    {
        $conn = $this->connect();

        if (@ftp_size($conn, $fileId) === -1) {
            return @ftp_rmdir($conn, $fileId);
        }

        return @ftp_delete($conn, $fileId);
    }

    public function createFolder(string $name, string $parentId = ''): StorageFile
    {
        $conn = $this->connect();
        $path = ($parentId !== '' ? rtrim($parentId, '/') . '/' : '') . $name;

        if (@ftp_mkdir($conn, $path) === false) {
            throw new ProviderException("FTP mkdir failed: {$path}", 'ftp');
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

    /** @return \FTP\Connection */
    private function connect(): mixed
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $host = $this->config['host'] ?? 'localhost';
        $port = (int) ($this->config['port'] ?? 21);
        $ssl = (bool) ($this->config['ssl'] ?? false);
        $timeout = (int) ($this->config['timeout'] ?? 30);

        $conn = $ssl ? @ftp_ssl_connect($host, $port, $timeout) : @ftp_connect($host, $port, $timeout);
        if ($conn === false) {
            throw new ProviderException("Cannot connect to FTP server {$host}:{$port}", 'ftp');
        }

        $username = $this->config['username'] ?? 'anonymous';
        $password = $this->config['password'] ?? '';

        if (! @ftp_login($conn, $username, $password)) {
            throw new ProviderException("FTP login failed for user {$username}", 'ftp');
        }

        if ((bool) ($this->config['passive'] ?? true)) {
            ftp_pasv($conn, true);
        }

        $this->connection = $conn;

        return $conn;
    }
}
