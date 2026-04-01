<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Microsoft\Services;

use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\DTOs\StorageFile;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\Microsoft\MicrosoftProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;
use Generator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MicrosoftOneDriveService extends AbstractService implements FileStorageInterface
{
    private const string BASE_URL = 'https://graph.microsoft.com/v1.0/me/drive';

    public function __construct(MicrosoftProvider $provider)
    {
        parent::__construct($provider);
    }

    // ──────────────────────────────────────────────
    //  LIST
    // ──────────────────────────────────────────────

    public function listFiles(string $folderId = '', array $options = []): array
    {
        return iterator_to_array($this->listFilesLazy($folderId, $options), false);
    }

    public function listFilesLazy(string $folderId = '', array $options = []): Generator
    {
        $url = $folderId === ''
            ? self::BASE_URL . '/root/children'
            : self::BASE_URL . '/items/' . rawurlencode($folderId) . '/children';

        do {
            $response = $this->authenticatedHttp()->get($url, $options);
            $json = $response->json();

            foreach ($json['value'] ?? [] as $item) {
                yield $this->mapDriveItem($item);
            }

            $url = $json['@odata.nextLink'] ?? null;
            $options = [];
        } while ($url !== null);
    }

    // ──────────────────────────────────────────────
    //  GET
    // ──────────────────────────────────────────────

    public function getFile(string $fileId): StorageFile
    {
        $response = $this->authenticatedHttp()->get(
            self::BASE_URL . '/items/' . rawurlencode($fileId)
        );

        return $this->mapDriveItem($response->json());
    }

    // ──────────────────────────────────────────────
    //  UPLOAD (small -- single PUT, <=4 MiB)
    // ──────────────────────────────────────────────

    public function uploadFile(string $name, mixed $content, string $mimeType = '', string $folderId = ''): StorageFile
    {
        if (is_resource($content)) {
            $content = stream_get_contents($content);
            if ($content === false) {
                throw new ProviderException('Failed to read upload stream.', $this->getProviderName());
            }
        }

        $mime = $mimeType !== '' ? $mimeType : 'application/octet-stream';

        $path = $folderId === ''
            ? self::BASE_URL . '/root:/' . rawurlencode($name) . ':/content'
            : self::BASE_URL . '/items/' . rawurlencode($folderId) . ':/' . rawurlencode($name) . ':/content';

        $response = Http::withToken($this->getAccessToken())
            ->withBody($content, $mime)
            ->put($path);

        if ($response->failed()) {
            throw new ProviderException(
                "OneDrive upload failed: {$response->body()}",
                $this->getProviderName(),
                $response->status(),
            );
        }

        return $this->mapDriveItem($response->json());
    }

    // ──────────────────────────────────────────────
    //  UPLOAD LARGE (upload session -- chunked, zero-copy)
    // ──────────────────────────────────────────────

    public function uploadLargeFile(
        string $name,
        mixed $filePathOrStream,
        string $mimeType = '',
        string $folderId = '',
        int $chunkSize = 5 * 1024 * 1024,
    ): StorageFile {
        // Graph API requires chunks to be multiples of 320 KiB
        $align = 320 * 1024;
        $chunkSize = (int) (floor($chunkSize / $align) * $align);
        if ($chunkSize < $align) {
            $chunkSize = $align;
        }

        $stream = is_string($filePathOrStream)
            ? fopen($filePathOrStream, 'rb')
            : $filePathOrStream;

        if (! is_resource($stream)) {
            throw new ProviderException('Cannot open file for upload.', $this->getProviderName());
        }

        $stat = fstat($stream);
        $totalSize = $stat['size'] ?? 0;

        // 1. Create upload session
        $sessionPath = $folderId === ''
            ? self::BASE_URL . '/root:/' . rawurlencode($name) . ':/createUploadSession'
            : self::BASE_URL . '/items/' . rawurlencode($folderId) . ':/' . rawurlencode($name) . ':/createUploadSession';

        $sessionResponse = $this->authenticatedHttp()->post($sessionPath, [
            'item' => [
                '@microsoft.graph.conflictBehavior' => 'rename',
                'name' => $name,
            ],
        ]);

        $uploadUrl = $sessionResponse->json('uploadUrl');
        if (! is_string($uploadUrl) || $uploadUrl === '') {
            throw new ProviderException('OneDrive did not return an upload session URL.', $this->getProviderName());
        }

        // 2. Upload chunks (no auth header needed -- token is in the URL)
        $offset = 0;
        $lastResponse = null;

        while (! feof($stream)) {
            $chunk = fread($stream, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $chunkLen = strlen($chunk);
            $rangeEnd = $offset + $chunkLen - 1;

            $lastResponse = Http::withBody($chunk, 'application/octet-stream')
                ->withHeaders([
                    'Content-Length' => (string) $chunkLen,
                    'Content-Range' => "bytes {$offset}-{$rangeEnd}/{$totalSize}",
                ])
                ->put($uploadUrl);

            $offset += $chunkLen;
        }

        if (is_string($filePathOrStream)) {
            fclose($stream);
        }

        if ($lastResponse === null || ! $lastResponse->successful()) {
            throw new ProviderException(
                'OneDrive resumable upload failed' . ($lastResponse ? ": {$lastResponse->body()}" : '.'),
                $this->getProviderName(),
            );
        }

        return $this->mapDriveItem($lastResponse->json());
    }

    // ──────────────────────────────────────────────
    //  DOWNLOAD
    // ──────────────────────────────────────────────

    public function downloadFile(string $fileId): string
    {
        $path = self::BASE_URL . '/items/' . rawurlencode($fileId) . '/content';

        $response = Http::withToken($this->getAccessToken())->get($path);

        if ($response->failed()) {
            throw new ProviderException(
                "OneDrive download failed: {$response->body()}",
                $this->getProviderName(),
                $response->status(),
            );
        }

        return $response->body();
    }

    public function downloadStream(string $fileId): mixed
    {
        $tmpStream = fopen('php://temp', 'r+b');
        if ($tmpStream === false) {
            throw new ProviderException('Cannot open temp stream for download.', $this->getProviderName());
        }

        $url = self::BASE_URL . '/items/' . rawurlencode($fileId) . '/content';

        Http::withToken($this->getAccessToken())
            ->sink($tmpStream)
            ->get($url);

        rewind($tmpStream);

        return $tmpStream;
    }

    // ──────────────────────────────────────────────
    //  DELETE / FOLDER / SEARCH
    // ──────────────────────────────────────────────

    public function deleteFile(string $fileId): bool
    {
        $response = $this->authenticatedHttp()->delete(
            self::BASE_URL . '/items/' . rawurlencode($fileId)
        );

        return $response->successful();
    }

    public function createFolder(string $name, string $parentId = ''): StorageFile
    {
        $body = [
            'name' => $name,
            'folder' => new \stdClass,
            '@microsoft.graph.conflictBehavior' => 'rename',
        ];

        $path = $parentId === ''
            ? self::BASE_URL . '/root/children'
            : self::BASE_URL . '/items/' . rawurlencode($parentId) . '/children';

        $response = $this->authenticatedHttp()->post($path, $body);

        return $this->mapDriveItem($response->json());
    }

    public function searchFiles(string $query, array $options = []): array
    {
        $escaped = str_replace("'", "''", $query);
        $path = self::BASE_URL . "/root/search(q='{$escaped}')";

        $response = $this->authenticatedHttp()->get($path);

        return $this->mapDriveItemsResponse($response);
    }

    // ──────────────────────────────────────────────
    //  INTERNALS
    // ──────────────────────────────────────────────

    /**
     * @return array<int, StorageFile>
     */
    private function mapDriveItemsResponse(Response $response): array
    {
        $items = $response->json('value') ?? [];

        return array_map(fn (array $item): StorageFile => $this->mapDriveItem($item), $items);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapDriveItem(array $item): StorageFile
    {
        $isFolder = isset($item['folder']);
        $mimeType = '';
        if (! $isFolder && isset($item['file']) && is_array($item['file'])) {
            $mimeType = (string) ($item['file']['mimeType'] ?? '');
        }

        $parentRef = $item['parentReference'] ?? null;
        $parentId = '';
        if (is_array($parentRef) && isset($parentRef['id'])) {
            $parentId = (string) $parentRef['id'];
        }

        return new StorageFile(
            id: (string) ($item['id'] ?? ''),
            name: (string) ($item['name'] ?? ''),
            mimeType: $mimeType,
            size: (int) ($item['size'] ?? 0),
            isFolder: $isFolder,
            parentId: $parentId,
            webUrl: (string) ($item['webUrl'] ?? ''),
            createdAt: isset($item['createdDateTime']) ? new DateTimeImmutable((string) $item['createdDateTime']) : null,
            modifiedAt: isset($item['lastModifiedDateTime']) ? new DateTimeImmutable((string) $item['lastModifiedDateTime']) : null,
            metadata: $item,
        );
    }
}
