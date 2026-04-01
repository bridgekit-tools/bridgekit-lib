<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use DateTimeImmutable;
use JsonSerializable;

final readonly class StorageFile implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $mimeType = '',
        public int $size = 0,
        public bool $isFolder = false,
        public string $parentId = '',
        public string $webUrl = '',
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $modifiedAt = null,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            mimeType: $data['mime_type'] ?? '',
            size: $data['size'] ?? 0,
            isFolder: $data['is_folder'] ?? false,
            parentId: $data['parent_id'] ?? '',
            webUrl: $data['web_url'] ?? '',
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            modifiedAt: isset($data['modified_at']) ? new DateTimeImmutable($data['modified_at']) : null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'is_folder' => $this->isFolder,
            'parent_id' => $this->parentId,
            'web_url' => $this->webUrl,
            'created_at' => $this->createdAt?->format('c'),
            'modified_at' => $this->modifiedAt?->format('c'),
            'metadata' => $this->metadata,
        ];
    }
}
