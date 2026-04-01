<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use BridgeKit\Enums\MediaType;
use InvalidArgumentException;
use JsonSerializable;

final readonly class MediaContent implements JsonSerializable
{
    /**
     * @param  string  $source  URL, local file path, or empty when $binary is provided
     * @param  string  $binary  Raw binary content (alternative to $source)
     * @param  string  $altText  Accessibility text for the media
     */
    public function __construct(
        public MediaType $type,
        public string $mimeType,
        public string $source = '',
        public string $binary = '',
        public string $filename = '',
        public string $altText = '',
    ) {
        if ($source === '' && $binary === '') {
            throw new InvalidArgumentException('MediaContent requires either a source (URL/path) or binary data.');
        }
    }

    public static function fromUrl(string $url, ?MediaType $type = null, string $altText = ''): self
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
        $resolvedType = $type ?? MediaType::fromExtension($extension ?: 'jpg');

        return new self(
            type: $resolvedType,
            mimeType: self::guessMimeFromExtension($extension ?: 'jpg'),
            source: $url,
            filename: basename(parse_url($url, PHP_URL_PATH) ?: 'media'),
            altText: $altText,
        );
    }

    public static function fromPath(string $path, ?MediaType $type = null, string $altText = ''): self
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException("File does not exist: {$path}");
        }

        $mimeType = mime_content_type($path) ?: 'application/octet-stream';
        $resolvedType = $type ?? MediaType::fromMimeType($mimeType);

        return new self(
            type: $resolvedType,
            mimeType: $mimeType,
            source: $path,
            filename: basename($path),
            altText: $altText,
        );
    }

    public static function fromBinary(string $data, string $filename, string $mimeType, ?MediaType $type = null, string $altText = ''): self
    {
        return new self(
            type: $type ?? MediaType::fromMimeType($mimeType),
            mimeType: $mimeType,
            binary: $data,
            filename: $filename,
            altText: $altText,
        );
    }

    public function isUrl(): bool
    {
        return $this->source !== '' && str_starts_with($this->source, 'http');
    }

    public function isLocalFile(): bool
    {
        return $this->source !== '' && ! $this->isUrl();
    }

    public function isBinary(): bool
    {
        return $this->binary !== '';
    }

    public function isVideo(): bool
    {
        return $this->type === MediaType::Video;
    }

    public function isImage(): bool
    {
        return $this->type === MediaType::Image || $this->type === MediaType::Gif;
    }

    /**
     * Get the binary content, resolving from source if needed.
     */
    public function getContent(): string
    {
        if ($this->binary !== '') {
            return $this->binary;
        }

        if ($this->isLocalFile()) {
            $content = file_get_contents($this->source);
            if ($content === false) {
                throw new InvalidArgumentException("Cannot read file: {$this->source}");
            }

            return $content;
        }

        return '';
    }

    public function getSize(): int
    {
        if ($this->binary !== '') {
            return strlen($this->binary);
        }

        if ($this->isLocalFile() && file_exists($this->source)) {
            return (int) filesize($this->source);
        }

        return 0;
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'mime_type' => $this->mimeType,
            'source' => $this->source,
            'filename' => $this->filename,
            'alt_text' => $this->altText,
        ];
    }

    private static function guessMimeFromExtension(string $ext): string
    {
        return match (strtolower($ext)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mpeg' => 'video/mpeg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
