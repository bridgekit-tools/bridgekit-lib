<?php

declare(strict_types=1);

namespace BridgeKit\Contracts\Storage;

use BridgeKit\DTOs\StorageFile;
use Generator;

interface FileStorageInterface
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<int, StorageFile>
     */
    public function listFiles(string $folderId = '', array $options = []): array;

    /**
     * Auto-paginating generator that yields one StorageFile at a time.
     * Fetches pages lazily -- zero memory overhead for large directories.
     *
     * @param  array<string, mixed>  $options
     * @return Generator<int, StorageFile>
     */
    public function listFilesLazy(string $folderId = '', array $options = []): Generator;

    public function getFile(string $fileId): StorageFile;

    /**
     * @param  string|resource  $content
     */
    public function uploadFile(string $name, mixed $content, string $mimeType = '', string $folderId = ''): StorageFile;

    /**
     * Resumable/chunked upload for large files.
     * Accepts a file path or a readable stream. Never loads the full file into memory.
     *
     * @param  string|resource  $filePathOrStream  Local file path or open stream
     * @param  int  $chunkSize  Bytes per chunk (default 5 MiB)
     */
    public function uploadLargeFile(
        string $name,
        mixed $filePathOrStream,
        string $mimeType = '',
        string $folderId = '',
        int $chunkSize = 5 * 1024 * 1024,
    ): StorageFile;

    /**
     * Download the full file content into a string.
     * Fine for small files; for large files use downloadStream() instead.
     */
    public function downloadFile(string $fileId): string;

    /**
     * Stream download -- returns a PHP stream resource.
     * The file is never loaded into memory; read it with fread()/stream_copy_to_stream().
     *
     * @return resource
     */
    public function downloadStream(string $fileId): mixed;

    public function deleteFile(string $fileId): bool;

    public function createFolder(string $name, string $parentId = ''): StorageFile;

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, StorageFile>
     */
    public function searchFiles(string $query, array $options = []): array;
}
