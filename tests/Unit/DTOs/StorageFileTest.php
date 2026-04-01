<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\DTOs;

use BridgeKit\DTOs\StorageFile;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class StorageFileTest extends TestCase
{
    public function test_constructor_assigns_properties(): void
    {
        $created = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $modified = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
        $file = new StorageFile(
            id: '1',
            name: 'doc.pdf',
            mimeType: 'application/pdf',
            size: 1024,
            isFolder: false,
            parentId: 'p1',
            webUrl: 'https://example.com/doc',
            createdAt: $created,
            modifiedAt: $modified,
            metadata: ['k' => 'v'],
        );

        $this->assertSame('1', $file->id);
        $this->assertSame('doc.pdf', $file->name);
        $this->assertSame('application/pdf', $file->mimeType);
        $this->assertSame(1024, $file->size);
        $this->assertFalse($file->isFolder);
        $this->assertSame('p1', $file->parentId);
        $this->assertSame('https://example.com/doc', $file->webUrl);
        $this->assertSame($created, $file->createdAt);
        $this->assertSame($modified, $file->modifiedAt);
        $this->assertSame(['k' => 'v'], $file->metadata);
    }

    public function test_from_array_maps_keys(): void
    {
        $file = StorageFile::fromArray([
            'id' => 'x',
            'name' => 'folder',
            'mime_type' => '',
            'size' => 0,
            'is_folder' => true,
            'parent_id' => 'root',
            'web_url' => 'https://drive.example/x',
            'created_at' => '2026-03-01T12:00:00+00:00',
            'modified_at' => '2026-03-02T12:00:00+00:00',
            'metadata' => ['foo' => 1],
        ]);

        $this->assertSame('x', $file->id);
        $this->assertSame('folder', $file->name);
        $this->assertTrue($file->isFolder);
        $this->assertSame('root', $file->parentId);
        $this->assertSame('https://drive.example/x', $file->webUrl);
        $this->assertSame('2026-03-01T12:00:00+00:00', $file->createdAt?->format('c'));
        $this->assertSame('2026-03-02T12:00:00+00:00', $file->modifiedAt?->format('c'));
        $this->assertSame(['foo' => 1], $file->metadata);
    }

    public function test_json_serialize_returns_expected_array(): void
    {
        $created = new DateTimeImmutable('2026-01-10T08:00:00+00:00');
        $modified = new DateTimeImmutable('2026-01-11T09:00:00+00:00');
        $file = new StorageFile(
            id: 'id1',
            name: 'n',
            mimeType: 'text/plain',
            size: 10,
            isFolder: false,
            parentId: 'p',
            webUrl: 'u',
            createdAt: $created,
            modifiedAt: $modified,
            metadata: [],
        );

        $this->assertSame([
            'id' => 'id1',
            'name' => 'n',
            'mime_type' => 'text/plain',
            'size' => 10,
            'is_folder' => false,
            'parent_id' => 'p',
            'web_url' => 'u',
            'created_at' => $created->format('c'),
            'modified_at' => $modified->format('c'),
            'metadata' => [],
        ], $file->jsonSerialize());
    }
}
