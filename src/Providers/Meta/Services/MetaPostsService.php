<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Meta\Services;

use BridgeKit\Contracts\Social\PostPublisherInterface;
use BridgeKit\DTOs\MediaContent;
use BridgeKit\DTOs\SocialPost;
use BridgeKit\DTOs\SocialPostResult;
use BridgeKit\Enums\Provider;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\Meta\MetaProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

class MetaPostsService extends AbstractService implements PostPublisherInterface
{
    public function __construct(
        protected readonly array $config,
        MetaProvider $provider,
    ) {
        parent::__construct($provider);
    }

    public function publish(SocialPost $post): SocialPostResult
    {
        $pageId = $this->resolvePageId($post);
        $base = $this->graphBase();

        $mediaItems = $post->resolveMedia();

        if ($mediaItems !== []) {
            $hasVideo = false;
            foreach ($mediaItems as $m) {
                if ($m->isVideo()) {
                    $hasVideo = true;
                    break;
                }
            }

            if ($hasVideo) {
                $response = $this->publishWithVideo($pageId, $base, $post, $mediaItems);
            } else {
                $response = $this->publishWithPhotos($pageId, $base, $post, $mediaItems);
            }
        } else {
            $response = $this->authenticatedHttp()
                ->asForm()
                ->post("{$base}/{$pageId}/feed", [
                    'message' => $post->content,
                ]);
        }

        $id = $response->json('id');
        if (! is_string($id) || $id === '') {
            throw new ProviderException(
                message: 'Meta publish did not return a post id.',
                provider: $this->provider->getName(),
            );
        }

        return new SocialPostResult(
            id: $id,
            provider: Provider::Meta,
            content: $post->content,
            url: '',
            publishedAt: new DateTimeImmutable(),
            metadata: $response->json() ?? [],
        );
    }

    public function uploadMedia(MediaContent $media): string
    {
        $pageId = $this->config['page_id'] ?? '';
        $base = $this->graphBase();

        if ($media->isVideo()) {
            return $this->uploadVideoToMeta($pageId, $base, $media);
        }

        return $this->uploadPhotoToMeta($pageId, $base, $media);
    }

    public function deletePost(string $postId): bool
    {
        $response = Http::acceptJson()
            ->withToken($this->getAccessToken())
            ->delete("{$this->graphBase()}/{$postId}");

        return $response->successful();
    }

    public function getPost(string $postId): ?SocialPostResult
    {
        $response = Http::acceptJson()
            ->withToken($this->getAccessToken())
            ->get("{$this->graphBase()}/{$postId}", [
                'fields' => 'id,message,created_time,permalink_url',
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

        $data = $response->json();
        if (! is_array($data) || ! isset($data['id'])) {
            return null;
        }

        $publishedAt = null;
        if (isset($data['created_time']) && is_string($data['created_time'])) {
            $publishedAt = new DateTimeImmutable($data['created_time']);
        }

        return new SocialPostResult(
            id: (string) $data['id'],
            provider: Provider::Meta,
            content: is_string($data['message'] ?? null) ? $data['message'] : '',
            url: is_string($data['permalink_url'] ?? null) ? $data['permalink_url'] : '',
            publishedAt: $publishedAt,
            metadata: $data,
        );
    }

    /**
     * @param  array<int, MediaContent>  $mediaItems
     */
    private function publishWithPhotos(string $pageId, string $base, SocialPost $post, array $mediaItems): \Illuminate\Http\Client\Response
    {
        $attached = [];
        foreach ($mediaItems as $media) {
            $photoId = $this->uploadPhotoToMeta($pageId, $base, $media);
            $attached[] = ['media_fbid' => $photoId];
        }

        return $this->authenticatedHttp()
            ->asForm()
            ->post("{$base}/{$pageId}/feed", [
                'message' => $post->content,
                'attached_media' => json_encode($attached, JSON_THROW_ON_ERROR),
            ]);
    }

    /**
     * @param  array<int, MediaContent>  $mediaItems
     */
    private function publishWithVideo(string $pageId, string $base, SocialPost $post, array $mediaItems): \Illuminate\Http\Client\Response
    {
        $video = $mediaItems[0];
        $params = ['description' => $post->content];

        if ($video->isUrl()) {
            $params['file_url'] = $video->source;

            return $this->authenticatedHttp()
                ->asForm()
                ->post("{$base}/{$pageId}/videos", $params);
        }

        $content = $video->getContent();

        return $this->authenticatedHttp()
            ->attach('source', $content, $video->filename)
            ->post("{$base}/{$pageId}/videos", $params);
    }

    private function uploadPhotoToMeta(string $pageId, string $base, MediaContent $media): string
    {
        if ($media->isUrl()) {
            $response = $this->authenticatedHttp()
                ->asForm()
                ->post("{$base}/{$pageId}/photos", [
                    'url' => $media->source,
                    'published' => 'false',
                ]);
        } else {
            $content = $media->getContent();

            $response = $this->authenticatedHttp()
                ->attach('source', $content, $media->filename)
                ->post("{$base}/{$pageId}/photos", [
                    'published' => 'false',
                ]);
        }

        $photoId = $response->json('id');
        if (! is_string($photoId) || $photoId === '') {
            throw new ProviderException(
                message: 'Meta photo upload did not return a media id.',
                provider: $this->provider->getName(),
            );
        }

        return $photoId;
    }

    private function uploadVideoToMeta(string $pageId, string $base, MediaContent $media): string
    {
        if ($media->isUrl()) {
            $response = $this->authenticatedHttp()
                ->asForm()
                ->post("{$base}/{$pageId}/videos", [
                    'file_url' => $media->source,
                    'published' => 'false',
                ]);
        } else {
            $content = $media->getContent();

            $response = $this->authenticatedHttp()
                ->attach('source', $content, $media->filename)
                ->post("{$base}/{$pageId}/videos", [
                    'published' => 'false',
                ]);
        }

        $videoId = $response->json('id');
        if (! is_string($videoId) || $videoId === '') {
            throw new ProviderException(
                message: 'Meta video upload did not return a media id.',
                provider: $this->provider->getName(),
            );
        }

        return $videoId;
    }

    private function graphBase(): string
    {
        $version = (string) ($this->config['graph_version'] ?? 'v21.0');

        return "https://graph.facebook.com/{$version}";
    }

    private function resolvePageId(SocialPost $post): string
    {
        $fromMeta = $post->metadata['page_id'] ?? null;
        if (is_string($fromMeta) && $fromMeta !== '') {
            return $fromMeta;
        }

        $fromConfig = $this->config['page_id'] ?? null;
        if (is_string($fromConfig) && $fromConfig !== '') {
            return $fromConfig;
        }

        throw new ProviderException(
            message: 'Meta page_id is required in provider config or post metadata.',
            provider: $this->provider->getName(),
        );
    }
}
