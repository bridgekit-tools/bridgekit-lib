<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use BridgeKit\Enums\Visibility;
use JsonSerializable;

final readonly class SocialPost implements JsonSerializable
{
    /**
     * @param  array<int, string>  $mediaUrls  URLs or local paths (legacy, prefer $media)
     * @param  array<int, MediaContent>  $media  Typed media attachments
     * @param  array<string, mixed>  $metadata  Provider-specific options
     */
    public function __construct(
        public string $content,
        public array $mediaUrls = [],
        public array $media = [],
        public Visibility $visibility = Visibility::Public,
        public array $metadata = [],
    ) {}

    public function hasMedia(): bool
    {
        return $this->media !== [] || $this->mediaUrls !== [];
    }

    /**
     * Resolve all media to MediaContent objects, converting legacy mediaUrls.
     *
     * @return array<int, MediaContent>
     */
    public function resolveMedia(): array
    {
        if ($this->media !== []) {
            return $this->media;
        }

        return array_map(
            fn (string $url) => MediaContent::fromUrl($url),
            $this->mediaUrls,
        );
    }

    public static function fromArray(array $data): self
    {
        $media = [];
        if (isset($data['media']) && is_array($data['media'])) {
            foreach ($data['media'] as $m) {
                if ($m instanceof MediaContent) {
                    $media[] = $m;
                }
            }
        }

        return new self(
            content: $data['content'],
            mediaUrls: $data['media_urls'] ?? [],
            media: $media,
            visibility: Visibility::tryFrom($data['visibility'] ?? '') ?? Visibility::Public,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'content' => $this->content,
            'media_urls' => $this->mediaUrls,
            'media' => array_map(fn (MediaContent $m) => $m->jsonSerialize(), $this->media),
            'visibility' => $this->visibility->value,
            'metadata' => $this->metadata,
        ];
    }
}
