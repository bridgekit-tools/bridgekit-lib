<?php

declare(strict_types=1);

namespace BridgeKit\Support;

use BridgeKit\Contracts\Social\PostPublisherInterface;
use BridgeKit\DTOs\MultiPostResult;
use BridgeKit\DTOs\SocialPost;
use BridgeKit\DTOs\SocialPostResult;
use BridgeKit\Enums\Provider;

class MultiPoster
{
    /** @var array<string, PostPublisherInterface> */
    private array $publishers = [];

    /** @var array<string, array{max_length: int, max_media: int}> */
    private static array $platformLimits = [
        'x' => ['max_length' => 280, 'max_media' => 4],
        'meta' => ['max_length' => 63206, 'max_media' => 10],
        'linkedin' => ['max_length' => 3000, 'max_media' => 9],
    ];

    /**
     * Add a provider's post publisher to the broadcast list.
     */
    public function on(Provider|string $provider, PostPublisherInterface $publisher): static
    {
        $key = $provider instanceof Provider ? $provider->value : $provider;
        $this->publishers[$key] = $publisher;

        return $this;
    }

    /**
     * Publish the post to all registered providers.
     * Adapts content per platform (truncation, media limits).
     * Failures on one provider don't stop the others.
     */
    public function publish(SocialPost $post): MultiPostResult
    {
        $succeeded = [];
        $failed = [];

        foreach ($this->publishers as $providerKey => $publisher) {
            try {
                $adapted = $this->adaptForPlatform($post, $providerKey);
                $result = $publisher->publish($adapted);
                $succeeded[$providerKey] = $result;
            } catch (\Throwable $e) {
                $failed[$providerKey] = $e;
            }
        }

        return new MultiPostResult(
            succeeded: $succeeded,
            failed: $failed,
        );
    }

    /**
     * Adapt a post for a specific platform's constraints.
     */
    private function adaptForPlatform(SocialPost $post, string $provider): SocialPost
    {
        $limits = self::$platformLimits[$provider] ?? null;
        if ($limits === null) {
            return $post;
        }

        $content = $post->content;
        $maxLength = $limits['max_length'];
        $maxMedia = $limits['max_media'];

        if (mb_strlen($content) > $maxLength) {
            $content = mb_substr($content, 0, $maxLength - 1) . '…';
        }

        $mediaUrls = $post->mediaUrls;
        if (count($mediaUrls) > $maxMedia) {
            $mediaUrls = array_slice($mediaUrls, 0, $maxMedia);
        }

        $media = $post->media;
        if (count($media) > $maxMedia) {
            $media = array_slice($media, 0, $maxMedia);
        }

        return new SocialPost(
            content: $content,
            mediaUrls: $mediaUrls,
            visibility: $post->visibility,
            metadata: $post->metadata,
            media: $media,
        );
    }
}
