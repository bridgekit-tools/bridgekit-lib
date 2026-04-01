<?php

declare(strict_types=1);

namespace BridgeKit\Enums;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Gif = 'gif';
    case Document = 'document';

    /**
     * @return array<int, string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/webp', 'image/heic'],
            self::Video => ['video/mp4', 'video/quicktime', 'video/webm', 'video/mpeg'],
            self::Gif => ['image/gif'],
            self::Document => ['application/pdf'],
        };
    }

    public static function fromMimeType(string $mimeType): self
    {
        $mimeType = strtolower(trim($mimeType));

        if ($mimeType === 'image/gif') {
            return self::Gif;
        }

        if (str_starts_with($mimeType, 'image/')) {
            return self::Image;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return self::Video;
        }

        return self::Document;
    }

    public static function fromExtension(string $extension): self
    {
        return match (strtolower(ltrim($extension, '.'))) {
            'jpg', 'jpeg', 'png', 'webp', 'heic' => self::Image,
            'gif' => self::Gif,
            'mp4', 'mov', 'webm', 'mpeg' => self::Video,
            default => self::Document,
        };
    }
}
