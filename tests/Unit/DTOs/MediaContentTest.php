<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\DTOs;

use BridgeKit\DTOs\MediaContent;
use BridgeKit\Enums\MediaType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MediaContentTest extends TestCase
{
    public function test_from_url_detects_image_type(): void
    {
        $media = MediaContent::fromUrl('https://example.com/photo.jpg');

        $this->assertSame(MediaType::Image, $media->type);
        $this->assertSame('image/jpeg', $media->mimeType);
        $this->assertSame('https://example.com/photo.jpg', $media->source);
        $this->assertSame('photo.jpg', $media->filename);
        $this->assertTrue($media->isUrl());
        $this->assertFalse($media->isLocalFile());
        $this->assertFalse($media->isBinary());
        $this->assertTrue($media->isImage());
        $this->assertFalse($media->isVideo());
    }

    public function test_from_url_detects_video_type(): void
    {
        $media = MediaContent::fromUrl('https://cdn.example.com/clip.mp4');

        $this->assertSame(MediaType::Video, $media->type);
        $this->assertSame('video/mp4', $media->mimeType);
        $this->assertTrue($media->isVideo());
        $this->assertFalse($media->isImage());
    }

    public function test_from_url_detects_gif_type(): void
    {
        $media = MediaContent::fromUrl('https://example.com/anim.gif');

        $this->assertSame(MediaType::Gif, $media->type);
        $this->assertSame('image/gif', $media->mimeType);
        $this->assertTrue($media->isImage());
    }

    public function test_from_url_with_override_type(): void
    {
        $media = MediaContent::fromUrl('https://example.com/file.bin', MediaType::Video);

        $this->assertSame(MediaType::Video, $media->type);
    }

    public function test_from_url_with_alt_text(): void
    {
        $media = MediaContent::fromUrl('https://example.com/pic.png', altText: 'A description');

        $this->assertSame('A description', $media->altText);
    }

    public function test_from_binary(): void
    {
        $data = 'fake-binary-content';
        $media = MediaContent::fromBinary($data, 'test.jpg', 'image/jpeg');

        $this->assertSame(MediaType::Image, $media->type);
        $this->assertSame('image/jpeg', $media->mimeType);
        $this->assertSame('test.jpg', $media->filename);
        $this->assertTrue($media->isBinary());
        $this->assertFalse($media->isUrl());
        $this->assertFalse($media->isLocalFile());
        $this->assertSame($data, $media->getContent());
        $this->assertSame(strlen($data), $media->getSize());
    }

    public function test_from_binary_with_type_override(): void
    {
        $media = MediaContent::fromBinary('data', 'clip.mp4', 'video/mp4', MediaType::Video, 'Alt text');

        $this->assertSame(MediaType::Video, $media->type);
        $this->assertSame('Alt text', $media->altText);
    }

    public function test_constructor_throws_without_source_or_binary(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MediaContent(
            type: MediaType::Image,
            mimeType: 'image/jpeg',
        );
    }

    public function test_json_serialize(): void
    {
        $media = MediaContent::fromUrl('https://example.com/pic.png', altText: 'Alt');

        $json = $media->jsonSerialize();

        $this->assertSame('image', $json['type']);
        $this->assertSame('image/png', $json['mime_type']);
        $this->assertSame('https://example.com/pic.png', $json['source']);
        $this->assertSame('pic.png', $json['filename']);
        $this->assertSame('Alt', $json['alt_text']);
    }

    public function test_from_path_throws_on_nonexistent_file(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MediaContent::fromPath('/nonexistent/file.jpg');
    }
}
