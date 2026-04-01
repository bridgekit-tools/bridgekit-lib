<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\DTOs;

use BridgeKit\DTOs\MediaContent;
use BridgeKit\DTOs\SocialPost;
use BridgeKit\Enums\MediaType;
use BridgeKit\Enums\Visibility;
use PHPUnit\Framework\TestCase;

final class SocialPostTest extends TestCase
{
    public function test_constructor_assigns_properties(): void
    {
        $post = new SocialPost(
            content: 'Hello',
            mediaUrls: ['https://x/a.jpg'],
            visibility: Visibility::Connections,
            metadata: ['tag' => 'news'],
        );

        $this->assertSame('Hello', $post->content);
        $this->assertSame(['https://x/a.jpg'], $post->mediaUrls);
        $this->assertSame(Visibility::Connections, $post->visibility);
        $this->assertSame(['tag' => 'news'], $post->metadata);
    }

    public function test_constructor_with_media_contents(): void
    {
        $media = MediaContent::fromUrl('https://example.com/pic.png');
        $post = new SocialPost(
            content: 'With media',
            media: [$media],
        );

        $this->assertCount(1, $post->media);
        $this->assertSame($media, $post->media[0]);
        $this->assertSame([], $post->mediaUrls);
    }

    public function test_has_media_with_media_contents(): void
    {
        $post = new SocialPost(
            content: 'Test',
            media: [MediaContent::fromUrl('https://example.com/pic.png')],
        );

        $this->assertTrue($post->hasMedia());
    }

    public function test_has_media_with_legacy_urls(): void
    {
        $post = new SocialPost(
            content: 'Test',
            mediaUrls: ['https://example.com/pic.png'],
        );

        $this->assertTrue($post->hasMedia());
    }

    public function test_has_media_returns_false_when_empty(): void
    {
        $post = new SocialPost(content: 'Test');

        $this->assertFalse($post->hasMedia());
    }

    public function test_resolve_media_prefers_media_over_urls(): void
    {
        $media = MediaContent::fromUrl('https://example.com/typed.png');
        $post = new SocialPost(
            content: 'Test',
            mediaUrls: ['https://example.com/legacy.jpg'],
            media: [$media],
        );

        $resolved = $post->resolveMedia();

        $this->assertCount(1, $resolved);
        $this->assertSame($media, $resolved[0]);
    }

    public function test_resolve_media_converts_legacy_urls(): void
    {
        $post = new SocialPost(
            content: 'Test',
            mediaUrls: ['https://example.com/photo.jpg', 'https://example.com/clip.mp4'],
        );

        $resolved = $post->resolveMedia();

        $this->assertCount(2, $resolved);
        $this->assertInstanceOf(MediaContent::class, $resolved[0]);
        $this->assertSame(MediaType::Image, $resolved[0]->type);
        $this->assertInstanceOf(MediaContent::class, $resolved[1]);
        $this->assertSame(MediaType::Video, $resolved[1]->type);
    }

    public function test_resolve_media_returns_empty_when_no_media(): void
    {
        $post = new SocialPost(content: 'Test');

        $this->assertSame([], $post->resolveMedia());
    }

    public function test_from_array_maps_keys(): void
    {
        $post = SocialPost::fromArray([
            'content' => 'Body',
            'media_urls' => ['m1', 'm2'],
            'visibility' => 'private',
            'metadata' => ['a' => 1],
        ]);

        $this->assertSame('Body', $post->content);
        $this->assertSame(['m1', 'm2'], $post->mediaUrls);
        $this->assertSame(Visibility::Private, $post->visibility);
        $this->assertSame(['a' => 1], $post->metadata);
    }

    public function test_from_array_uses_defaults(): void
    {
        $post = SocialPost::fromArray([
            'content' => 'Only',
        ]);

        $this->assertSame([], $post->mediaUrls);
        $this->assertSame([], $post->media);
        $this->assertSame(Visibility::Public, $post->visibility);
        $this->assertSame([], $post->metadata);
    }

    public function test_json_serialize(): void
    {
        $post = new SocialPost(
            content: 'C',
            mediaUrls: ['u'],
            visibility: Visibility::Public,
            metadata: ['m' => true],
        );

        $json = $post->jsonSerialize();

        $this->assertSame('C', $json['content']);
        $this->assertSame(['u'], $json['media_urls']);
        $this->assertSame([], $json['media']);
        $this->assertSame('public', $json['visibility']);
        $this->assertSame(['m' => true], $json['metadata']);
    }

    public function test_json_serialize_with_media_contents(): void
    {
        $media = MediaContent::fromUrl('https://example.com/pic.png', altText: 'A picture');
        $post = new SocialPost(
            content: 'C',
            media: [$media],
        );

        $json = $post->jsonSerialize();

        $this->assertCount(1, $json['media']);
        $this->assertSame('image', $json['media'][0]['type']);
        $this->assertSame('https://example.com/pic.png', $json['media'][0]['source']);
        $this->assertSame('A picture', $json['media'][0]['alt_text']);
    }
}
