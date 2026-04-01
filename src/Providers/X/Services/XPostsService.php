<?php

declare(strict_types=1);

namespace BridgeKit\Providers\X\Services;

use BridgeKit\Contracts\Social\PostPublisherInterface;
use BridgeKit\DTOs\MediaContent;
use BridgeKit\DTOs\SocialPost;
use BridgeKit\DTOs\SocialPostResult;
use BridgeKit\Enums\Provider;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\X\XProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

class XPostsService extends AbstractService implements PostPublisherInterface
{
    private const string API_BASE = 'https://api.x.com/2';

    private const string MEDIA_UPLOAD_URL = 'https://upload.twitter.com/1.1/media/upload.json';

    private const int SIMPLE_UPLOAD_LIMIT = 5 * 1024 * 1024;

    public function __construct(
        protected readonly array $config,
        XProvider $provider,
    ) {
        parent::__construct($provider);
    }

    public function publish(SocialPost $post): SocialPostResult
    {
        $mediaIds = [];
        foreach ($post->resolveMedia() as $media) {
            $mediaIds[] = $this->uploadMedia($media);
        }

        $payload = ['text' => $post->content];
        if ($mediaIds !== []) {
            $payload['media'] = ['media_ids' => $mediaIds];
        }

        $response = $this->authenticatedHttp()
            ->post(self::API_BASE.'/tweets', $payload);

        $data = $response->json('data');
        if (! is_array($data) || ! isset($data['id']) || ! is_string($data['id'])) {
            throw new ProviderException(
                message: 'X publish did not return a tweet id.',
                provider: $this->provider->getName(),
            );
        }

        $id = $data['id'];

        return new SocialPostResult(
            id: $id,
            provider: Provider::X,
            content: $post->content,
            url: 'https://x.com/i/web/status/'.$id,
            publishedAt: new DateTimeImmutable(),
            metadata: $response->json() ?? [],
        );
    }

    public function uploadMedia(MediaContent $media): string
    {
        $content = $this->resolveMediaBinary($media);
        $size = strlen($content);

        if ($media->isVideo() || $size > self::SIMPLE_UPLOAD_LIMIT) {
            return $this->chunkedUpload($content, $media);
        }

        return $this->simpleUpload($content, $media);
    }

    public function deletePost(string $postId): bool
    {
        $response = Http::acceptJson()
            ->withToken($this->getAccessToken())
            ->delete(self::API_BASE.'/tweets/'.$postId);

        return $response->successful();
    }

    public function getPost(string $postId): ?SocialPostResult
    {
        $response = Http::acceptJson()
            ->withToken($this->getAccessToken())
            ->get(self::API_BASE.'/tweets/'.$postId, [
                'tweet.fields' => 'created_at,text',
            ]);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new ProviderException(
                message: "HTTP {$response->status()}: {$response->body()}",
                provider: $this->provider->getName(),
                code: $response->status(),
            );
        }

        $data = $response->json('data');
        if (! is_array($data) || ! isset($data['id'])) {
            return null;
        }

        $publishedAt = null;
        if (isset($data['created_at']) && is_string($data['created_at'])) {
            $publishedAt = new DateTimeImmutable($data['created_at']);
        }

        $text = is_string($data['text'] ?? null) ? $data['text'] : '';

        return new SocialPostResult(
            id: (string) $data['id'],
            provider: Provider::X,
            content: $text,
            url: 'https://x.com/i/web/status/'.$data['id'],
            publishedAt: $publishedAt,
            metadata: $response->json() ?? [],
        );
    }

    private function simpleUpload(string $content, MediaContent $media): string
    {
        $response = Http::withToken($this->getAccessToken())
            ->attach('media', $content, $media->filename)
            ->post(self::MEDIA_UPLOAD_URL);

        if (! $response->successful()) {
            throw new ProviderException(
                message: "X media upload failed: HTTP {$response->status()}",
                provider: $this->provider->getName(),
                code: $response->status(),
            );
        }

        $mediaId = $response->json('media_id_string');
        if (! is_string($mediaId) || $mediaId === '') {
            throw new ProviderException(
                message: 'X media upload did not return media_id_string.',
                provider: $this->provider->getName(),
            );
        }

        if ($media->altText !== '') {
            $this->setAltText($mediaId, $media->altText);
        }

        return $mediaId;
    }

    /**
     * Chunked upload for large files and videos (INIT → APPEND → FINALIZE).
     */
    private function chunkedUpload(string $content, MediaContent $media): string
    {
        $totalBytes = strlen($content);
        $mediaCategory = $this->resolveMediaCategory($media);

        $initResponse = Http::withToken($this->getAccessToken())
            ->asForm()
            ->post(self::MEDIA_UPLOAD_URL, [
                'command' => 'INIT',
                'total_bytes' => $totalBytes,
                'media_type' => $media->mimeType,
                'media_category' => $mediaCategory,
            ]);

        if (! $initResponse->successful()) {
            throw new ProviderException(
                message: "X chunked INIT failed: HTTP {$initResponse->status()}",
                provider: $this->provider->getName(),
                code: $initResponse->status(),
            );
        }

        $mediaId = $initResponse->json('media_id_string');
        if (! is_string($mediaId) || $mediaId === '') {
            throw new ProviderException(
                message: 'X chunked INIT did not return media_id_string.',
                provider: $this->provider->getName(),
            );
        }

        $chunkSize = 4 * 1024 * 1024;
        $segment = 0;
        $offset = 0;

        while ($offset < $totalBytes) {
            $chunk = substr($content, $offset, $chunkSize);

            $appendResponse = Http::withToken($this->getAccessToken())
                ->attach('media', $chunk, 'blob')
                ->post(self::MEDIA_UPLOAD_URL, [
                    'command' => 'APPEND',
                    'media_id' => $mediaId,
                    'segment_index' => $segment,
                ]);

            if (! $appendResponse->successful()) {
                throw new ProviderException(
                    message: "X chunked APPEND segment {$segment} failed: HTTP {$appendResponse->status()}",
                    provider: $this->provider->getName(),
                    code: $appendResponse->status(),
                );
            }

            $offset += $chunkSize;
            $segment++;
        }

        $finalizeResponse = Http::withToken($this->getAccessToken())
            ->asForm()
            ->post(self::MEDIA_UPLOAD_URL, [
                'command' => 'FINALIZE',
                'media_id' => $mediaId,
            ]);

        if (! $finalizeResponse->successful()) {
            throw new ProviderException(
                message: "X chunked FINALIZE failed: HTTP {$finalizeResponse->status()}",
                provider: $this->provider->getName(),
                code: $finalizeResponse->status(),
            );
        }

        $processingInfo = $finalizeResponse->json('processing_info');
        if (is_array($processingInfo)) {
            $this->waitForProcessing($mediaId, $processingInfo);
        }

        if ($media->altText !== '') {
            $this->setAltText($mediaId, $media->altText);
        }

        return $mediaId;
    }

    /**
     * @param  array<string, mixed>  $processingInfo
     */
    private function waitForProcessing(string $mediaId, array $processingInfo): void
    {
        $maxAttempts = 30;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $checkAfter = (int) ($processingInfo['check_after_secs'] ?? 5);
            sleep($checkAfter);

            $statusResponse = Http::withToken($this->getAccessToken())
                ->get(self::MEDIA_UPLOAD_URL, [
                    'command' => 'STATUS',
                    'media_id' => $mediaId,
                ]);

            $info = $statusResponse->json('processing_info');
            if (! is_array($info)) {
                return;
            }

            $state = $info['state'] ?? '';

            if ($state === 'succeeded') {
                return;
            }

            if ($state === 'failed') {
                $error = $info['error']['message'] ?? 'Unknown processing error';
                throw new ProviderException(
                    message: "X media processing failed: {$error}",
                    provider: $this->provider->getName(),
                );
            }

            $processingInfo = $info;
            $attempt++;
        }

        throw new ProviderException(
            message: 'X media processing timed out.',
            provider: $this->provider->getName(),
        );
    }

    private function setAltText(string $mediaId, string $altText): void
    {
        Http::withToken($this->getAccessToken())
            ->post(self::MEDIA_UPLOAD_URL.'/create', [
                'media_id' => $mediaId,
                'alt_text' => ['text' => $altText],
            ]);
    }

    private function resolveMediaCategory(MediaContent $media): string
    {
        if ($media->isVideo()) {
            return 'tweet_video';
        }

        if ($media->type === \BridgeKit\Enums\MediaType::Gif) {
            return 'tweet_gif';
        }

        return 'tweet_image';
    }

    private function resolveMediaBinary(MediaContent $media): string
    {
        if ($media->isBinary()) {
            return $media->binary;
        }

        if ($media->isLocalFile()) {
            return $media->getContent();
        }

        $response = Http::timeout(120)->get($media->source);
        if (! $response->successful()) {
            throw new ProviderException(
                message: 'Could not download media for X upload.',
                provider: $this->provider->getName(),
            );
        }

        return $response->body();
    }
}
