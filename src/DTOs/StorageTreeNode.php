<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use Generator;
use JsonSerializable;

/**
 * Recursive tree node representing a folder or a file in a storage hierarchy.
 *
 * - A file node has `children = []` (or null when not yet expanded).
 * - A folder node carries its sub-folders and files in `children`.
 *
 * The node always wraps a `StorageFile` so all metadata (id, name, size,
 * webUrl, mimeType, ...) stays available alongside the tree structure.
 */
final readonly class StorageTreeNode implements JsonSerializable
{
    /**
     * @param  array<int, StorageTreeNode>  $children
     */
    public function __construct(
        public StorageFile $file,
        public array $children = [],
        public int $depth = 0,
    ) {}

    public function isFolder(): bool
    {
        return $this->file->isFolder;
    }

    public function isFile(): bool
    {
        return ! $this->file->isFolder;
    }

    /**
     * Total number of descendants (folders + files), recursively.
     */
    public function countDescendants(): int
    {
        $count = count($this->children);
        foreach ($this->children as $child) {
            $count += $child->countDescendants();
        }

        return $count;
    }

    /**
     * Cumulative byte size of this node and all its descendants.
     */
    public function totalSize(): int
    {
        $size = $this->file->size;
        foreach ($this->children as $child) {
            $size += $child->totalSize();
        }

        return $size;
    }

    /**
     * Depth-first iteration over every node in the tree (self first, then children).
     *
     * @return Generator<int, StorageTreeNode>
     */
    public function walk(): Generator
    {
        yield $this;
        foreach ($this->children as $child) {
            yield from $child->walk();
        }
    }

    /**
     * Render the tree as a human-readable ASCII representation, similar to the
     * `tree` Unix command. Useful for logs, debug output and documentation.
     */
    public function toAscii(string $prefix = '', bool $isLast = true, bool $isRoot = true): string
    {
        $lines = [];

        if ($isRoot) {
            $lines[] = $this->file->name === '' ? '.' : $this->file->name;
            $childPrefix = '';
        } else {
            $connector = $isLast ? '└── ' : '├── ';
            $lines[] = $prefix . $connector . $this->file->name . ($this->file->isFolder ? '/' : '');
            $childPrefix = $prefix . ($isLast ? '    ' : '│   ');
        }

        $count = count($this->children);
        foreach ($this->children as $i => $child) {
            $lines[] = $child->toAscii($childPrefix, $i === $count - 1, false);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'file' => $this->file->jsonSerialize(),
            'depth' => $this->depth,
            'children' => array_map(
                static fn (self $child): array => $child->jsonSerialize(),
                $this->children,
            ),
        ];
    }
}
