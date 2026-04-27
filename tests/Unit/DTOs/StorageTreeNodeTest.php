<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\DTOs;

use BridgeKit\DTOs\StorageFile;
use BridgeKit\DTOs\StorageTreeNode;
use PHPUnit\Framework\TestCase;

final class StorageTreeNodeTest extends TestCase
{
    public function test_constructor_defaults(): void
    {
        $node = new StorageTreeNode(
            file: new StorageFile(id: 'root', name: '/', isFolder: true),
        );

        self::assertSame('root', $node->file->id);
        self::assertSame([], $node->children);
        self::assertSame(0, $node->depth);
        self::assertTrue($node->isFolder());
        self::assertFalse($node->isFile());
    }

    public function test_count_descendants_walks_recursively(): void
    {
        $tree = $this->buildSampleTree();

        // children: docs/, photos/, README.md => 3
        // docs/ : guide.pdf, faq.md => 2
        // photos/ : 2025/ , logo.png => 2
        // photos/2025/ : team.jpg => 1
        // total = 3 + 2 + 2 + 1 = 8
        self::assertSame(8, $tree->countDescendants());
    }

    public function test_total_size_sums_children(): void
    {
        $tree = $this->buildSampleTree();

        // sizes: README=10 + guide=100 + faq=20 + logo=50 + team=200 = 380
        self::assertSame(380, $tree->totalSize());
    }

    public function test_walk_yields_self_then_descendants_depth_first(): void
    {
        $tree = $this->buildSampleTree();

        $names = [];
        foreach ($tree->walk() as $node) {
            $names[] = $node->file->name;
        }

        self::assertSame([
            '/',
            'docs',
            'guide.pdf',
            'faq.md',
            'photos',
            '2025',
            'team.jpg',
            'logo.png',
            'README.md',
        ], $names);
    }

    public function test_to_ascii_renders_tree_layout(): void
    {
        $tree = $this->buildSampleTree();

        $ascii = $tree->toAscii();

        $expected = implode("\n", [
            '/',
            '├── docs/',
            '│   ├── guide.pdf',
            '│   └── faq.md',
            '├── photos/',
            '│   ├── 2025/',
            '│   │   └── team.jpg',
            '│   └── logo.png',
            '└── README.md',
        ]);

        self::assertSame($expected, $ascii);
    }

    public function test_json_serialize_is_recursive(): void
    {
        $tree = $this->buildSampleTree();
        $json = $tree->jsonSerialize();

        self::assertArrayHasKey('file', $json);
        self::assertArrayHasKey('children', $json);
        self::assertSame('/', $json['file']['name']);
        self::assertCount(3, $json['children']);
        self::assertSame('docs', $json['children'][0]['file']['name']);
        self::assertSame('guide.pdf', $json['children'][0]['children'][0]['file']['name']);
    }

    private function buildSampleTree(): StorageTreeNode
    {
        $guide = new StorageTreeNode(
            new StorageFile(id: 'guide', name: 'guide.pdf', mimeType: 'application/pdf', size: 100),
            depth: 2,
        );
        $faq = new StorageTreeNode(
            new StorageFile(id: 'faq', name: 'faq.md', size: 20),
            depth: 2,
        );
        $docs = new StorageTreeNode(
            new StorageFile(id: 'docs', name: 'docs', isFolder: true),
            children: [$guide, $faq],
            depth: 1,
        );

        $teamPhoto = new StorageTreeNode(
            new StorageFile(id: 'team', name: 'team.jpg', size: 200),
            depth: 3,
        );
        $year2025 = new StorageTreeNode(
            new StorageFile(id: '2025', name: '2025', isFolder: true),
            children: [$teamPhoto],
            depth: 2,
        );
        $logo = new StorageTreeNode(
            new StorageFile(id: 'logo', name: 'logo.png', size: 50),
            depth: 2,
        );
        $photos = new StorageTreeNode(
            new StorageFile(id: 'photos', name: 'photos', isFolder: true),
            children: [$year2025, $logo],
            depth: 1,
        );

        $readme = new StorageTreeNode(
            new StorageFile(id: 'readme', name: 'README.md', size: 10),
            depth: 1,
        );

        return new StorageTreeNode(
            new StorageFile(id: '', name: '/', isFolder: true),
            children: [$docs, $photos, $readme],
        );
    }
}
