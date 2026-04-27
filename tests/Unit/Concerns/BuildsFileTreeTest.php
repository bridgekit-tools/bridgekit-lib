<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Concerns;

use BridgeKit\Concerns\BuildsFileTree;
use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\DTOs\StorageFile;
use BridgeKit\DTOs\StorageTreeNode;
use Generator;
use PHPUnit\Framework\TestCase;

final class BuildsFileTreeTest extends TestCase
{
    public function test_list_tree_builds_recursive_structure(): void
    {
        $storage = $this->makeStorage([
            '' => [
                $this->folder('docs', 'docs'),
                $this->file('readme.md', 'readme.md', 10),
            ],
            'docs' => [
                $this->file('guide.pdf', 'docs/guide.pdf', 100),
                $this->folder('archive', 'docs/archive'),
            ],
            'docs/archive' => [
                $this->file('old.txt', 'docs/archive/old.txt', 5),
            ],
        ]);

        $tree = $storage->listTree();

        self::assertSame('/', $tree->file->name);
        self::assertCount(2, $tree->children);
        self::assertSame('docs', $tree->children[0]->file->name);
        self::assertCount(2, $tree->children[0]->children);
        self::assertSame('archive', $tree->children[0]->children[1]->file->name);
        self::assertSame('old.txt', $tree->children[0]->children[1]->children[0]->file->name);
        self::assertSame(115, $tree->totalSize());
    }

    public function test_max_depth_caps_recursion(): void
    {
        $storage = $this->makeStorage([
            '' => [$this->folder('a', 'a')],
            'a' => [$this->folder('b', 'a/b')],
            'a/b' => [$this->folder('c', 'a/b/c')],
            'a/b/c' => [$this->file('deep.txt', 'a/b/c/deep.txt', 1)],
        ]);

        $tree = $storage->listTree('', ['max_depth' => 2]);
        $a = $tree->children[0];
        $b = $a->children[0];

        self::assertSame('a', $a->file->name);
        self::assertSame('b', $b->file->name);
        // depth=3 children should NOT be expanded.
        self::assertSame([], $b->children);
    }

    public function test_include_files_false_yields_folders_only(): void
    {
        $storage = $this->makeStorage([
            '' => [
                $this->folder('docs', 'docs'),
                $this->file('readme.md', 'readme.md', 10),
            ],
            'docs' => [
                $this->file('guide.pdf', 'docs/guide.pdf', 100),
            ],
        ]);

        $tree = $storage->listTree('', ['include_files' => false]);

        self::assertCount(1, $tree->children);
        self::assertSame('docs', $tree->children[0]->file->name);
        self::assertSame([], $tree->children[0]->children);
    }

    public function test_root_name_option_overrides_default(): void
    {
        $storage = $this->makeStorage(['' => []]);

        $tree = $storage->listTree('', ['root_name' => 'My Workspace']);

        self::assertSame('My Workspace', $tree->file->name);
    }

    private function file(string $name, string $id, int $size): StorageFile
    {
        return new StorageFile(id: $id, name: $name, mimeType: 'text/plain', size: $size);
    }

    private function folder(string $name, string $id): StorageFile
    {
        return new StorageFile(id: $id, name: $name, mimeType: 'directory', isFolder: true);
    }

    /**
     * @param  array<string, array<int, StorageFile>>  $tree
     */
    private function makeStorage(array $tree): FileStorageInterface
    {
        return new class($tree) implements FileStorageInterface {
            use BuildsFileTree;

            public function __construct(private readonly array $tree) {}

            public function listFiles(string $folderId = '', array $options = []): array
            {
                return iterator_to_array($this->listFilesLazy($folderId, $options), false);
            }

            public function listFilesLazy(string $folderId = '', array $options = []): Generator
            {
                foreach ($this->tree[$folderId] ?? [] as $entry) {
                    yield $entry;
                }
            }

            public function getFile(string $fileId): StorageFile
            {
                return new StorageFile(id: $fileId, name: $fileId);
            }

            public function uploadFile(string $name, mixed $content, string $mimeType = '', string $folderId = ''): StorageFile
            {
                return new StorageFile(id: $name, name: $name);
            }

            public function uploadLargeFile(string $name, mixed $filePathOrStream, string $mimeType = '', string $folderId = '', int $chunkSize = 5_242_880): StorageFile
            {
                return new StorageFile(id: $name, name: $name);
            }

            public function downloadFile(string $fileId): string
            {
                return '';
            }

            public function downloadStream(string $fileId): mixed
            {
                return fopen('php://memory', 'r+b');
            }

            public function deleteFile(string $fileId): bool
            {
                return true;
            }

            public function createFolder(string $name, string $parentId = ''): StorageFile
            {
                return new StorageFile(id: $name, name: $name, isFolder: true);
            }

            public function searchFiles(string $query, array $options = []): array
            {
                return [];
            }
        };
    }
}
