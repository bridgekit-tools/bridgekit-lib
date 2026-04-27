<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Dropbox\Services;

use BridgeKit\Concerns\BuildsFileTree;
use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\DTOs\StorageFile;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\Dropbox\DropboxProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;
use Generator;
use Illuminate\Support\Facades\Http;

class DropboxStorageService extends AbstractService implements FileStorageInterface
{
    use BuildsFileTree;

    private const string API_URL = 'https://api.dropboxapi.com/2';

    private const string CONTENT_URL = 'https://content.dropboxapi.com/2';

    public function __construct(DropboxProvider $provider)
    {
        parent::__construct($provider);
    }

    public function listFiles(string $folderId = '', array $options = []): array
    {
        return iterator_to_array($this->listFilesLazy($folderId, $options), false);
    }

    public function listFilesLazy(string $folderId = '', array $options = []): Generator
    {
        $path = $folderId !== '' ? $folderId : '';
        $limit = $options['limit'] ?? 2000;

        $response = $this->authenticatedHttp()->post(self::API_URL . '/files/list_folder', [
            'path' => $path,
            'recursive' => $options['recursive'] ?? false,
            'limit' => min($limit, 2000),
        ]);

        $json = $response->json();

        foreach ($json['entries'] ?? [] as $entry) {
            yield $this->mapEntry($entry);
        }

        $cursor = $json['cursor'] ?? null;
        $hasMore = $json['has_more'] ?? false;

        while ($hasMore && $cursor !== null) {
            $response = $this->authenticatedHttp()->post(
                self::API_URL . '/files/list_folder/continue',
                ['cursor' => $cursor]
            );

            $json = $response->json();

            foreach ($json['entries'] ?? [] as $entry) {
                yield $this->mapEntry($entry);
            }

            $cursor = $json['cursor'] ?? null;
            $hasMore = $json['has_more'] ?? false;
        }
    }

    public function getFile(string $fileId): StorageFile
    {
        $response = $this->authenticatedHttp()->post(self::API_URL . '/files/get_metadata', [
            'path' => $fileId,
            'include_media_info' => true,
        ]);

        return $this->mapEntry($response->json());
    }

    public function uploadFile(string $name, mixed $content, string $mimeType = '', string $folderId = ''): StorageFile
    {
        $path = ($folderId !== '' ? rtrim($folderId, '/') : '') . '/' . $name;
        $body = is_string($content) ? $content : (stream_get_contents($content) ?: '');

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode([
                    'path' => $path,
                    'mode' => 'add',
                    'autorename' => true,
                    'mute' => false,
                ]),
                'Content-Type' => 'application/octet-stream',
            ])
            ->withBody($body, 'application/octet-stream')
            ->post(self::CONTENT_URL . '/files/upload');

        return $this->mapEntry($response->json());
    }

    public function uploadLargeFile(string $name, mixed $filePathOrStream, string $mimeType = '', string $folderId = '', int $chunkSize = 5 * 1024 * 1024): StorageFile
    {
        $path = ($folderId !== '' ? rtrim($folderId, '/') : '') . '/' . $name;

        $stream = is_string($filePathOrStream) ? fopen($filePathOrStream, 'rb') : $filePathOrStream;
        if (! is_resource($stream)) {
            throw new ProviderException('Cannot open file for Dropbox upload.', 'dropbox');
        }

        // 1. Start upload session
        $firstChunk = fread($stream, $chunkSize) ?: '';
        $startResponse = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode(['close' => false]),
                'Content-Type' => 'application/octet-stream',
            ])
            ->withBody($firstChunk, 'application/octet-stream')
            ->post(self::CONTENT_URL . '/files/upload_session/start');

        $sessionId = $startResponse->json('session_id')
            ?? throw new ProviderException('Dropbox upload session start failed.', 'dropbox');

        $offset = strlen($firstChunk);

        // 2. Append chunks
        while (! feof($stream)) {
            $chunk = fread($stream, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }

            Http::withToken($this->getAccessToken())
                ->withHeaders([
                    'Dropbox-API-Arg' => json_encode([
                        'cursor' => ['session_id' => $sessionId, 'offset' => $offset],
                        'close' => false,
                    ]),
                    'Content-Type' => 'application/octet-stream',
                ])
                ->withBody($chunk, 'application/octet-stream')
                ->post(self::CONTENT_URL . '/files/upload_session/append_v2');

            $offset += strlen($chunk);
        }

        if (is_string($filePathOrStream)) {
            fclose($stream);
        }

        // 3. Finish
        $finishResponse = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode([
                    'cursor' => ['session_id' => $sessionId, 'offset' => $offset],
                    'commit' => [
                        'path' => $path,
                        'mode' => 'add',
                        'autorename' => true,
                        'mute' => false,
                    ],
                ]),
                'Content-Type' => 'application/octet-stream',
            ])
            ->withBody('', 'application/octet-stream')
            ->post(self::CONTENT_URL . '/files/upload_session/finish');

        return $this->mapEntry($finishResponse->json());
    }

    public function downloadFile(string $fileId): string
    {
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode(['path' => $fileId]),
            ])
            ->post(self::CONTENT_URL . '/files/download');

        return $response->body();
    }

    public function downloadStream(string $fileId): mixed
    {
        $tmpStream = fopen('php://temp', 'r+b');
        if ($tmpStream === false) {
            throw new ProviderException('Cannot create temp stream.', 'dropbox');
        }

        $body = $this->downloadFile($fileId);
        fwrite($tmpStream, $body);
        rewind($tmpStream);

        return $tmpStream;
    }

    public function deleteFile(string $fileId): bool
    {
        $response = $this->authenticatedHttp()->post(self::API_URL . '/files/delete_v2', [
            'path' => $fileId,
        ]);

        return $response->successful();
    }

    public function createFolder(string $name, string $parentId = ''): StorageFile
    {
        $path = ($parentId !== '' ? rtrim($parentId, '/') : '') . '/' . $name;

        $response = $this->authenticatedHttp()->post(self::API_URL . '/files/create_folder_v2', [
            'path' => $path,
            'autorename' => false,
        ]);

        $metadata = $response->json('metadata') ?? $response->json();

        return $this->mapEntry($metadata);
    }

    public function searchFiles(string $query, array $options = []): array
    {
        $body = [
            'query' => $query,
            'options' => [
                'max_results' => $options['limit'] ?? 100,
                'file_status' => 'active',
            ],
        ];

        if (isset($options['path'])) {
            $body['options']['path'] = $options['path'];
        }

        $response = $this->authenticatedHttp()->post(self::API_URL . '/files/search_v2', $body);

        $results = [];
        foreach ($response->json('matches') ?? [] as $match) {
            $metadata = $match['metadata']['metadata'] ?? $match['metadata'] ?? [];
            if ($metadata !== []) {
                $results[] = $this->mapEntry($metadata);
            }
        }

        return $results;
    }

    private function mapEntry(array $entry): StorageFile
    {
        $tag = $entry['.tag'] ?? '';
        $isFolder = $tag === 'folder';

        $path = $entry['path_display'] ?? $entry['path_lower'] ?? '';

        return new StorageFile(
            id: $entry['id'] ?? $path,
            name: $entry['name'] ?? basename($path),
            mimeType: $isFolder ? 'directory' : '',
            size: (int) ($entry['size'] ?? 0),
            isFolder: $isFolder,
            parentId: dirname($path) !== '.' ? dirname($path) : '',
            webUrl: $entry['sharing_info']['shared_folder_id'] ?? '',
            modifiedAt: isset($entry['server_modified'])
                ? new DateTimeImmutable($entry['server_modified'])
                : (isset($entry['client_modified']) ? new DateTimeImmutable($entry['client_modified']) : null),
            metadata: $entry,
        );
    }
}
