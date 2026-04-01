<?php

declare(strict_types=1);

namespace BridgeKit\Providers\LinkedIn\Services;

use BridgeKit\Contracts\Social\PostPublisherInterface;
use BridgeKit\DTOs\MediaContent;
use BridgeKit\DTOs\SocialPost;
use BridgeKit\DTOs\SocialPostResult;
use BridgeKit\Enums\Provider;
use BridgeKit\Enums\Visibility;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\LinkedIn\LinkedInProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class LinkedInPostsService extends AbstractService implements PostPublisherInterface
{
    private const string API_BASE = 'https://api.linkedin.com/v2';

    private const string REST_BASE = 'https://api.linkedin.com/rest';

    public function __construct(
        protected readonly array $config,
        LinkedInProvider $provider,
    ) {
        parent::__construct($provider);
    }

    public function publish(SocialPost $post): SocialPostResult
    {
        $author = $this->resolveAuthorUrn($post);
        $mediaItems = $post->resolveMedia();

        if ($mediaItems !== []) {
            return $this->publishWithMedia($post, $author, $mediaItems);
        }

        $body = [
            'author' => $author,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $post->content],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $this->mapVisibility($post->visibility),
            ],
        ];

        $response = $this->linkedinHttp()
            ->post(self::API_BASE.'/ugcPosts', $body);

        $id = $response->json('id');
        if (! is_string($id) || $id === '') {
            throw new ProviderException(
                message: 'LinkedIn publish did not return a post id.',
                provider: $this->provider->getName(),
            );
        }

        return new SocialPostResult(
            id: $id,
            provider: Provider::LinkedIn,
            content: $post->content,
            url: '',
            publishedAt: new DateTimeImmutable(),
            metadata: $response->json() ?? [],
        );
    }

    public function uploadMedia(MediaContent $media): string
    {
        $author = $this->config['author_urn'] ?? '';

        return $this->registerAndUpload($author, $media);
    }

    public function deletePost(string $postId): bool
    {
        $encoded = rawurlencode($postId);
        $response = Http::acceptJson()
            ->withHeaders($this->linkedinHeaders())
            ->withToken($this->getAccessToken())
            ->delete(self::API_BASE.'/ugcPosts/'.$encoded);

        return $response->successful();
    }

    public function getPost(string $postId): ?SocialPostResult
    {
        $encoded = rawurlencode($postId);
        $response = Http::acceptJson()
            ->withHeaders($this->linkedinHeaders())
            ->withToken($this->getAccessToken())
            ->get(self::API_BASE.'/ugcPosts/'.$encoded);

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

        $content = '';
        $specific = $data['specificContent']['com.linkedin.ugc.ShareContent'] ?? null;
        if (is_array($specific)) {
            $commentary = $specific['shareCommentary'] ?? null;
            if (is_array($commentary) && isset($commentary['text']) && is_string($commentary['text'])) {
                $content = $commentary['text'];
            }
        }

        return new SocialPostResult(
            id: (string) $data['id'],
            provider: Provider::LinkedIn,
            content: $content,
            url: '',
            publishedAt: null,
            metadata: $data,
        );
    }

    /**
     * @param  array<int, MediaContent>  $mediaItems
     */
    private function publishWithMedia(SocialPost $post, string $author, array $mediaItems): SocialPostResult
    {
        $shareMedia = [];
        foreach ($mediaItems as $media) {
            $asset = $this->registerAndUpload($author, $media);
            $shareMedia[] = [
                'status' => 'READY',
                'description' => ['text' => $media->altText ?: $post->content],
                'media' => $asset,
                'title' => ['text' => $media->filename],
            ];
        }

        $category = $this->resolveMediaCategory($mediaItems);

        $body = [
            'author' => $author,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $post->content],
                    'shareMediaCategory' => $category,
                    'media' => $shareMedia,
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $this->mapVisibility($post->visibility),
            ],
        ];

        $response = $this->linkedinHttp()
            ->post(self::API_BASE.'/ugcPosts', $body);

        $id = $response->json('id');
        if (! is_string($id) || $id === '') {
            throw new ProviderException(
                message: 'LinkedIn publish did not return a post id.',
                provider: $this->provider->getName(),
            );
        }

        return new SocialPostResult(
            id: $id,
            provider: Provider::LinkedIn,
            content: $post->content,
            url: '',
            publishedAt: new DateTimeImmutable(),
            metadata: $response->json() ?? [],
        );
    }

    /**
     * Register an upload with LinkedIn and upload the binary.
     * Returns the asset URN.
     */
    private function registerAndUpload(string $ownerUrn, MediaContent $media): string
    {
        $recipe = $media->isVideo()
            ? 'urn:li:digitalmediaRecipe:feedshare-video'
            : 'urn:li:digitalmediaRecipe:feedshare-image';

        $registerBody = [
            'registerUploadRequest' => [
                'recipes' => [$recipe],
                'owner' => $ownerUrn,
                'serviceRelationships' => [
                    [
                        'relationshipType' => 'OWNER',
                        'identifier' => 'urn:li:userGeneratedContent',
                    ],
                ],
            ],
        ];

        $registerResponse = $this->linkedinHttp()
            ->post(self::API_BASE.'/assets?action=registerUpload', $registerBody);

        $uploadUrl = $registerResponse->json('value.uploadMechanism.com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest.uploadUrl');
        $asset = $registerResponse->json('value.asset');

        if (! is_string($uploadUrl) || $uploadUrl === '') {
            throw new ProviderException(
                message: 'LinkedIn media register did not return an upload URL.',
                provider: $this->provider->getName(),
            );
        }

        if (! is_string($asset) || $asset === '') {
            throw new ProviderException(
                message: 'LinkedIn media register did not return an asset URN.',
                provider: $this->provider->getName(),
            );
        }

        $content = $this->resolveMediaContent($media);

        Http::withToken($this->getAccessToken())
            ->withHeaders(['Content-Type' => $media->mimeType])
            ->withBody($content, $media->mimeType)
            ->put($uploadUrl);

        return $asset;
    }

    private function resolveMediaContent(MediaContent $media): string
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
                message: 'Could not download media for LinkedIn upload.',
                provider: $this->provider->getName(),
            );
        }

        return $response->body();
    }

    /**
     * @param  array<int, MediaContent>  $mediaItems
     */
    private function resolveMediaCategory(array $mediaItems): string
    {
        foreach ($mediaItems as $media) {
            if ($media->isVideo()) {
                return 'VIDEO';
            }
        }

        return 'IMAGE';
    }

    /**
     * @return array<string, string>
     */
    private function linkedinHeaders(): array
    {
        return [
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => '202304',
        ];
    }

    private function linkedinHttp(): PendingRequest
    {
        return $this->authenticatedHttp()->withHeaders($this->linkedinHeaders());
    }

    private function resolveAuthorUrn(SocialPost $post): string
    {
        $fromMeta = $post->metadata['author_urn'] ?? null;
        if (is_string($fromMeta) && $fromMeta !== '') {
            return $fromMeta;
        }

        $fromConfig = $this->config['author_urn'] ?? null;
        if (is_string($fromConfig) && $fromConfig !== '') {
            return $fromConfig;
        }

        throw new ProviderException(
            message: 'LinkedIn author_urn is required in provider config or post metadata.',
            provider: $this->provider->getName(),
        );
    }

    private function mapVisibility(Visibility $visibility): string
    {
        return match ($visibility) {
            Visibility::Public => 'PUBLIC',
            Visibility::Connections => 'CONNECTIONS',
            Visibility::Private => 'PUBLIC',
        };
    }
}
