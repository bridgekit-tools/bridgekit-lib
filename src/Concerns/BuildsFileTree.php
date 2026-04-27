<?php

declare(strict_types=1);

namespace BridgeKit\Concerns;

use BridgeKit\DTOs\StorageFile;
use BridgeKit\DTOs\StorageTreeNode;

/**
 * Default `listTree()` implementation built on top of `listFilesLazy()`.
 *
 * Any class implementing `FileStorageInterface` can `use BuildsFileTree`
 * and instantly gain a recursive tree walker without code duplication.
 *
 * Options (all optional):
 *   - `max_depth`        (int)  default 10  -- hard recursion safety guard
 *   - `include_files`    (bool) default true
 *   - `include_folders`  (bool) default true
 *   - `root_name`        (string) override the synthetic root node name
 */
trait BuildsFileTree
{
    public function listTree(string $folderId = '', array $options = []): StorageTreeNode
    {
        $maxDepth = (int) ($options['max_depth'] ?? 10);
        $includeFiles = (bool) ($options['include_files'] ?? true);
        $includeFolders = (bool) ($options['include_folders'] ?? true);
        $rootName = (string) ($options['root_name'] ?? ($folderId !== '' ? basename($folderId) : '/'));

        $rootFile = new StorageFile(
            id: $folderId,
            name: $rootName,
            mimeType: 'directory',
            size: 0,
            isFolder: true,
        );

        return new StorageTreeNode(
            file: $rootFile,
            children: $this->buildChildren($folderId, $options, 1, $maxDepth, $includeFiles, $includeFolders),
            depth: 0,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, StorageTreeNode>
     */
    private function buildChildren(
        string $folderId,
        array $options,
        int $depth,
        int $maxDepth,
        bool $includeFiles,
        bool $includeFolders,
    ): array {
        if ($depth > $maxDepth) {
            return [];
        }

        $children = [];

        foreach ($this->listFilesLazy($folderId, $options) as $file) {
            if ($file->isFolder) {
                if (! $includeFolders) {
                    continue;
                }

                $subChildren = $this->buildChildren(
                    $file->id,
                    $options,
                    $depth + 1,
                    $maxDepth,
                    $includeFiles,
                    $includeFolders,
                );

                $children[] = new StorageTreeNode(
                    file: $file,
                    children: $subChildren,
                    depth: $depth,
                );

                continue;
            }

            if (! $includeFiles) {
                continue;
            }

            $children[] = new StorageTreeNode(
                file: $file,
                children: [],
                depth: $depth,
            );
        }

        return $children;
    }
}
