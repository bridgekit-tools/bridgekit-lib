<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Google\Services;

use BridgeKit\Concerns\BuildsFileTree;
use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\DTOs\StorageFile;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\Google\GoogleProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;
use Generator;
use Illuminate\Support\Facades\Http;

class GoogleDriveService extends AbstractService implements FileStorageInterface
{
    use BuildsFileTree;

    private const string BASE_URL = 'https://www.googleapis.com/drive/v3';

    private const string UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3';

    private const string FILE_FIELDS = 'id,name,mimeType,size,parents,webViewLink,webContentLink,createdTime,modifiedTime';

    public function __construct(
        GoogleProvider $provider,
    ) {
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
        $q = $folderId === ''
            ? "'root' in parents and trashed = false"
            : "'{$folderId}' in parents and trashed = false";

        $params = array_merge([
            'q' => $q,
            'pageSize' => 100,
            'fields' => 'files(' . self::FILE_FIELDS . '),nextPageToken',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ], $options);

        $pageToken = null;

        do {
            if ($pageToken !== null) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->authenticatedHttp()->get(self::BASE_URL . '/files', $params);
            $json = $response->json();

            foreach ($json['files'] ?? [] as $file) {
                yield $this->mapDriveFile($file);
            }

            $pageToken = $json['nextPageToken'] ?? null;
        } while ($pageToken !== null);
    }

    // ──────────────────────────────────────────────
    //  GET
    // ──────────────────────────────────────────────

    public function getFile(string $fileId): StorageFile
    {
        $response = $this->authenticatedHttp()->get(self::BASE_URL . '/files/' . rawurlencode($fileId), [
            'fields' => self::FILE_FIELDS,
            'supportsAllDrives' => 'true',
        ]);

        return $this->mapDriveFile($response->json());
    }

    // ──────────────────────────────────────────────
    //  UPLOAD (small -- multipart, single request)
    // ──────────────────────────────────────────────

    public function uploadFile(string $name, mixed $content, string $mimeType = '', string $folderId = ''): StorageFile
    {
        $mimeType = $mimeType !== '' ? $mimeType : 'application/octet-stream';
        $body = is_string($content) ? $content : stream_get_contents($content);
        if ($body === false) {
            $body = '';
        }

        $metadata = ['name' => $name];
        if ($folderId !== '') {
            $metadata['parents'] = [$folderId];
        }

        $boundary = 'bk_' . bin2hex(random_bytes(16));
        $jsonPart = json_encode($metadata, JSON_THROW_ON_ERROR);
        $multipart = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $jsonPart . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$mimeType}\r\n\r\n"
            . $body . "\r\n"
            . "--{$boundary}--\r\n";

        $url = self::UPLOAD_URL . '/files?uploadType=multipart&fields=' . rawurlencode(self::FILE_FIELDS) . '&supportsAllDrives=true';

        $response = $this->authenticatedHttp()
            ->withBody($multipart, 'multipart/related; boundary=' . $boundary)
            ->post($url);

        return $this->mapDriveFile($response->json());
    }

    // ──────────────────────────────────────────────
    //  UPLOAD LARGE (resumable -- chunked, zero-copy)
    // ──────────────────────────────────────────────

    public function uploadLargeFile(
        string $name,
        mixed $filePathOrStream,
        string $mimeType = '',
        string $folderId = '',
        int $chunkSize = 5 * 1024 * 1024,
    ): StorageFile {
        $mimeType = $mimeType !== '' ? $mimeType : 'application/octet-stream';

        $stream = is_string($filePathOrStream)
            ? fopen($filePathOrStream, 'rb')
            : $filePathOrStream;

        if (! is_resource($stream)) {
            throw new ProviderException('Cannot open file for upload.', $this->getProviderName());
        }

        $stat = fstat($stream);
        $totalSize = $stat['size'] ?? 0;

        $metadata = ['name' => $name];
        if ($folderId !== '') {
            $metadata['parents'] = [$folderId];
        }

        // 1. Initiate resumable upload session
        $initUrl = self::UPLOAD_URL . '/files?uploadType=resumable&fields=' . rawurlencode(self::FILE_FIELDS) . '&supportsAllDrives=true';

        $initResponse = Http::withToken($this->getAccessToken())
            ->contentType('application/json; charset=UTF-8')
            ->withHeaders(['X-Upload-Content-Type' => $mimeType, 'X-Upload-Content-Length' => (string) $totalSize])
            ->post($initUrl, $metadata);

        $uploadUri = $initResponse->header('Location');
        if ($uploadUri === null || $uploadUri === '') {
            throw new ProviderException('Google Drive did not return a resumable upload URI.', $this->getProviderName());
        }

        // 2. Send chunks
        $offset = 0;
        $lastResponse = null;

        while (! feof($stream)) {
            $chunk = fread($stream, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $chunkLen = strlen($chunk);
            $rangeEnd = $offset + $chunkLen - 1;
            $contentRange = "bytes {$offset}-{$rangeEnd}/{$totalSize}";

            $lastResponse = Http::withToken($this->getAccessToken())
                ->withBody($chunk, $mimeType)
                ->withHeaders(['Content-Range' => $contentRange])
                ->put($uploadUri);

            $offset += $chunkLen;
        }

        if (is_string($filePathOrStream)) {
            fclose($stream);
        }

        if ($lastResponse === null || ! $lastResponse->successful()) {
            throw new ProviderException(
                'Google Drive resumable upload failed' . ($lastResponse ? ": {$lastResponse->body()}" : '.'),
                $this->getProviderName(),
            );
        }

        return $this->mapDriveFile($lastResponse->json());
    }

    // ──────────────────────────────────────────────
    //  DOWNLOAD
    // ──────────────────────────────────────────────

    public function downloadFile(string $fileId): string
    {
        $response = $this->authenticatedHttp()->get(self::BASE_URL . '/files/' . rawurlencode($fileId), [
            'alt' => 'media',
            'supportsAllDrives' => 'true',
        ]);

        return $response->body();
    }

    public function downloadStream(string $fileId): mixed
    {
        $tmpStream = fopen('php://temp', 'r+b');
        if ($tmpStream === false) {
            throw new ProviderException('Cannot open temp stream for download.', $this->getProviderName());
        }

        $url = self::BASE_URL . '/files/' . rawurlencode($fileId) . '?' . http_build_query([
            'alt' => 'media',
            'supportsAllDrives' => 'true',
        ]);

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
        $url = self::BASE_URL . '/files/' . rawurlencode($fileId) . '?' . http_build_query([
            'supportsAllDrives' => 'true',
        ]);

        $response = $this->authenticatedHttp()->delete($url);

        return $response->successful();
    }

    public function createFolder(string $name, string $parentId = ''): StorageFile
    {
        $body = [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ];
        if ($parentId !== '') {
            $body['parents'] = [$parentId];
        }

        $response = $this->authenticatedHttp()->post(
            self::BASE_URL . '/files?fields=' . rawurlencode(self::FILE_FIELDS) . '&supportsAllDrives=true',
            $body
        );

        return $this->mapDriveFile($response->json());
    }

    public function searchFiles(string $query, array $options = []): array
    {
        $escaped = str_replace('\\', '\\\\', str_replace("'", "\\'", $query));
        $q = "fullText contains '{$escaped}' and trashed = false";

        $params = array_merge([
            'q' => $q,
            'pageSize' => 100,
            'fields' => 'files(' . self::FILE_FIELDS . '),nextPageToken',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ], $options);

        $response = $this->authenticatedHttp()->get(self::BASE_URL . '/files', $params);
        $files = $response->json('files') ?? [];

        return array_map(fn (array $f): StorageFile => $this->mapDriveFile($f), $files);
    }

    // ──────────────────────────────────────────────
    //  INTERNALS
    // ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $file
     */
    private function mapDriveFile(array $file): StorageFile
    {
        $parents = $file['parents'] ?? [];
        $parentId = is_array($parents) && $parents !== [] ? (string) $parents[0] : '';
        $mime = (string) ($file['mimeType'] ?? '');

        return new StorageFile(
            id: (string) ($file['id'] ?? ''),
            name: (string) ($file['name'] ?? ''),
            mimeType: $mime,
            size: (int) ($file['size'] ?? 0),
            isFolder: $mime === 'application/vnd.google-apps.folder',
            parentId: $parentId,
            webUrl: (string) ($file['webViewLink'] ?? $file['webContentLink'] ?? ''),
            createdAt: isset($file['createdTime']) ? new DateTimeImmutable((string) $file['createdTime']) : null,
            modifiedAt: isset($file['modifiedTime']) ? new DateTimeImmutable((string) $file['modifiedTime']) : null,
            metadata: $file,
        );
    }
}
