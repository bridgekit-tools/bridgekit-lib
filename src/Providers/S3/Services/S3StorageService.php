<?php

declare(strict_types=1);

namespace BridgeKit\Providers\S3\Services;

use BridgeKit\Concerns\BuildsFileTree;
use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\DTOs\StorageFile;
use BridgeKit\Exceptions\ProviderException;
use DateTimeImmutable;
use Generator;
use Illuminate\Support\Facades\Http;

class S3StorageService implements FileStorageInterface
{
    use BuildsFileTree;

    private readonly string $bucket;

    private readonly string $region;

    private readonly string $key;

    private readonly string $secret;

    private readonly string $endpoint;

    private readonly bool $usePathStyle;

    public function __construct(array $config)
    {
        $this->bucket = $config['bucket'] ?? throw new ProviderException('S3 bucket is required.', 's3');
        $this->region = $config['region'] ?? 'us-east-1';
        $this->key = $config['key'] ?? throw new ProviderException('S3 access key is required.', 's3');
        $this->secret = $config['secret'] ?? throw new ProviderException('S3 secret key is required.', 's3');
        $this->endpoint = rtrim($config['endpoint'] ?? "https://s3.{$this->region}.amazonaws.com", '/');
        // Path-style is required for most S3-compatible providers (MinIO, R2, ...)
        // and when an explicit endpoint is supplied. AWS itself supports both.
        $this->usePathStyle = (bool) ($config['use_path_style'] ?? isset($config['endpoint']));
    }

    public function listFiles(string $folderId = '', array $options = []): array
    {
        return iterator_to_array($this->listFilesLazy($folderId, $options), false);
    }

    public function listFilesLazy(string $folderId = '', array $options = []): Generator
    {
        $prefix = $folderId !== '' ? rtrim($folderId, '/') . '/' : '';
        $continuationToken = null;

        do {
            $query = [
                'list-type' => '2',
                'prefix' => $prefix,
                'delimiter' => '/',
                'max-keys' => $options['max_keys'] ?? 1000,
            ];

            if ($continuationToken !== null) {
                $query['continuation-token'] = $continuationToken;
            }

            $response = $this->signedRequest('GET', '/', $query);
            $xml = simplexml_load_string($response->body());

            if ($xml === false) {
                throw new ProviderException('Failed to parse S3 response.', 's3');
            }

            foreach ($xml->CommonPrefixes ?? [] as $prefixNode) {
                $folderPath = rtrim((string) $prefixNode->Prefix, '/');
                yield new StorageFile(
                    id: $folderPath,
                    name: basename($folderPath),
                    mimeType: 'directory',
                    size: 0,
                    isFolder: true,
                    parentId: $folderId,
                    webUrl: $this->buildObjectUrl($folderPath . '/'),
                );
            }

            foreach ($xml->Contents ?? [] as $content) {
                $key = (string) $content->Key;
                if ($key === $prefix) {
                    continue;
                }

                yield new StorageFile(
                    id: $key,
                    name: basename($key),
                    mimeType: $this->guessMimeType($key),
                    size: (int) (string) $content->Size,
                    isFolder: false,
                    parentId: $folderId,
                    webUrl: $this->buildObjectUrl($key),
                    modifiedAt: new DateTimeImmutable((string) $content->LastModified),
                    metadata: [
                        'etag' => trim((string) $content->ETag, '"'),
                        'storage_class' => (string) ($content->StorageClass ?? 'STANDARD'),
                    ],
                );
            }

            $continuationToken = isset($xml->NextContinuationToken) ? (string) $xml->NextContinuationToken : null;
        } while ($continuationToken !== null);
    }

    public function getFile(string $fileId): StorageFile
    {
        $response = $this->signedRequest('HEAD', '/' . ltrim($fileId, '/'));

        return new StorageFile(
            id: $fileId,
            name: basename($fileId),
            mimeType: $response->header('Content-Type') ?? '',
            size: (int) ($response->header('Content-Length') ?? 0),
            isFolder: false,
            parentId: dirname($fileId) !== '.' ? dirname($fileId) : '',
            webUrl: $this->buildObjectUrl($fileId),
            modifiedAt: $response->header('Last-Modified')
                ? new DateTimeImmutable($response->header('Last-Modified'))
                : null,
            metadata: [
                'etag' => trim($response->header('ETag') ?? '', '"'),
            ],
        );
    }

    public function uploadFile(string $name, mixed $content, string $mimeType = '', string $folderId = ''): StorageFile
    {
        $path = ($folderId !== '' ? rtrim($folderId, '/') . '/' : '') . $name;
        $mimeType = $mimeType !== '' ? $mimeType : ($this->guessMimeType($name) ?: 'application/octet-stream');
        $body = is_string($content) ? $content : (stream_get_contents($content) ?: '');

        $this->signedRequest('PUT', '/' . ltrim($path, '/'), [], [
            'Content-Type' => $mimeType,
        ], $body);

        return new StorageFile(
            id: $path,
            name: $name,
            mimeType: $mimeType,
            size: strlen($body),
            isFolder: false,
            parentId: $folderId,
            webUrl: $this->buildObjectUrl($path),
        );
    }

    public function uploadLargeFile(string $name, mixed $filePathOrStream, string $mimeType = '', string $folderId = '', int $chunkSize = 5 * 1024 * 1024): StorageFile
    {
        $path = ($folderId !== '' ? rtrim($folderId, '/') . '/' : '') . $name;
        $mimeType = $mimeType !== '' ? $mimeType : ($this->guessMimeType($name) ?: 'application/octet-stream');
        $objectKey = '/' . ltrim($path, '/');

        $stream = is_string($filePathOrStream) ? fopen($filePathOrStream, 'rb') : $filePathOrStream;
        if (! is_resource($stream)) {
            throw new ProviderException('Cannot open file for S3 upload.', 's3');
        }

        // 1. Initiate multipart upload
        $initResponse = $this->signedRequest('POST', $objectKey, ['uploads' => ''], [
            'Content-Type' => $mimeType,
        ]);
        $initXml = simplexml_load_string($initResponse->body());
        $uploadId = (string) ($initXml->UploadId ?? '');

        if ($uploadId === '') {
            throw new ProviderException('S3 multipart initiation failed.', 's3');
        }

        // 2. Upload parts
        $partNumber = 1;
        $parts = [];
        $totalSize = 0;

        while (! feof($stream)) {
            $chunk = fread($stream, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $partResponse = $this->signedRequest('PUT', $objectKey, [
                'partNumber' => (string) $partNumber,
                'uploadId' => $uploadId,
            ], [], $chunk);

            $etag = trim($partResponse->header('ETag') ?? '', '"');
            $parts[] = ['PartNumber' => $partNumber, 'ETag' => $etag];
            $totalSize += strlen($chunk);
            $partNumber++;
        }

        if (is_string($filePathOrStream)) {
            fclose($stream);
        }

        // 3. Complete multipart upload
        $xmlBody = '<CompleteMultipartUpload>';
        foreach ($parts as $part) {
            $xmlBody .= '<Part><PartNumber>' . $part['PartNumber'] . '</PartNumber><ETag>' . $part['ETag'] . '</ETag></Part>';
        }
        $xmlBody .= '</CompleteMultipartUpload>';

        $this->signedRequest('POST', $objectKey, ['uploadId' => $uploadId], [
            'Content-Type' => 'application/xml',
        ], $xmlBody);

        return new StorageFile(
            id: $path,
            name: $name,
            mimeType: $mimeType,
            size: $totalSize,
            isFolder: false,
            parentId: $folderId,
            webUrl: $this->buildObjectUrl($path),
        );
    }

    public function downloadFile(string $fileId): string
    {
        $response = $this->signedRequest('GET', '/' . ltrim($fileId, '/'));

        return $response->body();
    }

    public function downloadStream(string $fileId): mixed
    {
        $tmpStream = fopen('php://temp', 'r+b');
        if ($tmpStream === false) {
            throw new ProviderException('Cannot create temp stream.', 's3');
        }

        $body = $this->downloadFile($fileId);
        fwrite($tmpStream, $body);
        rewind($tmpStream);

        return $tmpStream;
    }

    public function deleteFile(string $fileId): bool
    {
        $response = $this->signedRequest('DELETE', '/' . ltrim($fileId, '/'));

        return $response->status() === 204 || $response->successful();
    }

    public function createFolder(string $name, string $parentId = ''): StorageFile
    {
        $path = ($parentId !== '' ? rtrim($parentId, '/') . '/' : '') . $name . '/';

        $this->signedRequest('PUT', '/' . ltrim($path, '/'), [], [
            'Content-Type' => 'application/x-directory',
        ], '');

        return new StorageFile(
            id: rtrim($path, '/'),
            name: $name,
            mimeType: 'directory',
            size: 0,
            isFolder: true,
            parentId: $parentId,
            webUrl: $this->buildObjectUrl($path),
        );
    }

    /**
     * Generate a presigned (time-limited) URL for the given object key.
     *
     * Use this whenever you need an authenticated download/preview link for a
     * private bucket. For public buckets, `webUrl` on `StorageFile` already
     * contains the canonical URL.
     */
    public function getPresignedUrl(string $fileId, int $expiresIn = 900): string
    {
        $now = new DateTimeImmutable('UTC');
        $dateStamp = $now->format('Ymd');
        $amzDate = $now->format('Ymd\THis\Z');
        $service = 's3';

        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        [$canonicalUri, $bareUrl] = $this->buildPathAndUrl($fileId);

        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => "{$this->key}/{$credentialScope}",
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) max(1, $expiresIn),
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($query);
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $canonicalRequest = implode("\n", [
            'GET',
            $canonicalUri,
            $queryString,
            "host:{$host}\n",
            'host',
            'UNSIGNED-PAYLOAD',
        ]);

        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', $service,
                hash_hmac('sha256', $this->region,
                    hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secret, true),
                    true),
                true),
            true);

        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        return $bareUrl . '?' . $queryString . '&X-Amz-Signature=' . $signature;
    }

    /**
     * Build the canonical, public web URL of an object inside the bucket.
     *
     * - AWS / virtual-host style: `https://{bucket}.s3.{region}.amazonaws.com/{key}`
     * - Path style (MinIO, R2, custom endpoint): `{endpoint}/{bucket}/{key}`
     */
    private function buildObjectUrl(string $key): string
    {
        [, $url] = $this->buildPathAndUrl($key);

        return $url;
    }

    /**
     * @return array{0: string, 1: string} [canonicalPath, fullUrl]
     */
    private function buildPathAndUrl(string $key): array
    {
        $key = ltrim($key, '/');
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));

        if ($this->usePathStyle) {
            $path = '/' . $this->bucket . ($encodedKey === '' ? '' : '/' . $encodedKey);

            return [$path, $this->endpoint . $path];
        }

        $host = "{$this->bucket}." . parse_url($this->endpoint, PHP_URL_HOST);
        $scheme = parse_url($this->endpoint, PHP_URL_SCHEME) ?: 'https';
        $path = '/' . $encodedKey;

        return [$path, "{$scheme}://{$host}{$path}"];
    }

    public function searchFiles(string $query, array $options = []): array
    {
        $prefix = $options['prefix'] ?? '';
        $results = [];

        foreach ($this->listFilesLazy($prefix) as $file) {
            if (stripos($file->name, $query) !== false) {
                $results[] = $file;
            }
        }

        return $results;
    }

    // ──────────────────────────────────────────────
    //  AWS Signature V4
    // ──────────────────────────────────────────────

    private function signedRequest(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        string $body = '',
    ): \Illuminate\Http\Client\Response {
        $now = new DateTimeImmutable('UTC');
        $dateStamp = $now->format('Ymd');
        $amzDate = $now->format('Ymd\THis\Z');
        $service = 's3';

        $encodedPath = '/' . $this->bucket . $path;
        ksort($query);
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $payloadHash = hash('sha256', $body);

        $headers['host'] = parse_url($this->endpoint, PHP_URL_HOST);
        $headers['x-amz-date'] = $amzDate;
        $headers['x-amz-content-sha256'] = $payloadHash;

        ksort($headers);
        $signedHeaders = implode(';', array_map('strtolower', array_keys($headers)));
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n";
        }

        $canonicalRequest = implode("\n", [
            $method,
            $encodedPath,
            $queryString,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', $service,
                hash_hmac('sha256', $this->region,
                    hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secret, true),
                    true),
                true),
            true);

        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $url = $this->endpoint . $encodedPath;
        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        $pending = Http::withHeaders($headers)->timeout(120);

        return match ($method) {
            'GET' => $pending->get($url),
            'HEAD' => $pending->head($url),
            'PUT' => $pending->withBody($body, $headers['Content-Type'] ?? 'application/octet-stream')->put($url),
            'POST' => $pending->withBody($body, $headers['Content-Type'] ?? 'application/xml')->post($url),
            'DELETE' => $pending->delete($url),
            default => throw new ProviderException("Unsupported HTTP method: {$method}", 's3'),
        };
    }

    private function guessMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'html', 'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            default => '',
        };
    }
}
