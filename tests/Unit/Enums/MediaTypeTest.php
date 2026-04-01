<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Enums;

use BridgeKit\Enums\MediaType;
use PHPUnit\Framework\TestCase;

final class MediaTypeTest extends TestCase
{
    public function test_from_mime_type_image(): void
    {
        $this->assertSame(MediaType::Image, MediaType::fromMimeType('image/jpeg'));
        $this->assertSame(MediaType::Image, MediaType::fromMimeType('image/png'));
        $this->assertSame(MediaType::Image, MediaType::fromMimeType('image/webp'));
    }

    public function test_from_mime_type_gif(): void
    {
        $this->assertSame(MediaType::Gif, MediaType::fromMimeType('image/gif'));
    }

    public function test_from_mime_type_video(): void
    {
        $this->assertSame(MediaType::Video, MediaType::fromMimeType('video/mp4'));
        $this->assertSame(MediaType::Video, MediaType::fromMimeType('video/quicktime'));
    }

    public function test_from_mime_type_document(): void
    {
        $this->assertSame(MediaType::Document, MediaType::fromMimeType('application/pdf'));
        $this->assertSame(MediaType::Document, MediaType::fromMimeType('text/plain'));
    }

    public function test_from_extension(): void
    {
        $this->assertSame(MediaType::Image, MediaType::fromExtension('jpg'));
        $this->assertSame(MediaType::Image, MediaType::fromExtension('.png'));
        $this->assertSame(MediaType::Gif, MediaType::fromExtension('gif'));
        $this->assertSame(MediaType::Video, MediaType::fromExtension('mp4'));
        $this->assertSame(MediaType::Video, MediaType::fromExtension('mov'));
        $this->assertSame(MediaType::Document, MediaType::fromExtension('pdf'));
        $this->assertSame(MediaType::Document, MediaType::fromExtension('unknown'));
    }

    public function test_mime_types_returns_expected_arrays(): void
    {
        $this->assertContains('image/jpeg', MediaType::Image->mimeTypes());
        $this->assertContains('image/gif', MediaType::Gif->mimeTypes());
        $this->assertContains('video/mp4', MediaType::Video->mimeTypes());
        $this->assertContains('application/pdf', MediaType::Document->mimeTypes());
    }

    public function test_backed_values(): void
    {
        $this->assertSame('image', MediaType::Image->value);
        $this->assertSame('video', MediaType::Video->value);
        $this->assertSame('gif', MediaType::Gif->value);
        $this->assertSame('document', MediaType::Document->value);
    }
}
