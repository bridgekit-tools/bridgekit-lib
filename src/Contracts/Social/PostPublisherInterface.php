<?php

declare(strict_types=1);

namespace BridgeKit\Contracts\Social;

use BridgeKit\DTOs\MediaContent;
use BridgeKit\DTOs\SocialPost;
use BridgeKit\DTOs\SocialPostResult;

interface PostPublisherInterface
{
    public function publish(SocialPost $post): SocialPostResult;

    public function deletePost(string $postId): bool;

    public function getPost(string $postId): ?SocialPostResult;

    /**
     * Upload a media file and return its provider-specific media ID.
     */
    public function uploadMedia(MediaContent $media): string;
}
