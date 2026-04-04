<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Support;

use BridgeKit\Contracts\Social\PostPublisherInterface;
use BridgeKit\DTOs\SocialPost;
use BridgeKit\DTOs\SocialPostResult;
use BridgeKit\Enums\Provider;
use BridgeKit\Enums\Visibility;
use BridgeKit\Support\MultiPoster;
use PHPUnit\Framework\TestCase;

final class MultiPosterTest extends TestCase
{
    public function test_publishes_to_multiple_providers(): void
    {
        $metaPublisher = $this->createMock(PostPublisherInterface::class);
        $metaPublisher->method('publish')->willReturn(new SocialPostResult(id: 'meta-1', provider: Provider::Meta, url: ''));

        $xPublisher = $this->createMock(PostPublisherInterface::class);
        $xPublisher->method('publish')->willReturn(new SocialPostResult(id: 'x-1', provider: Provider::X, url: ''));

        $poster = new MultiPoster();
        $result = $poster
            ->on(Provider::Meta, $metaPublisher)
            ->on(Provider::X, $xPublisher)
            ->publish(new SocialPost(content: 'Hello world!'));

        self::assertTrue($result->isFullSuccess());
        self::assertSame('meta-1', $result->getResult('meta')?->id);
        self::assertSame('x-1', $result->getResult('x')?->id);
    }

    public function test_handles_partial_failure(): void
    {
        $metaPublisher = $this->createMock(PostPublisherInterface::class);
        $metaPublisher->method('publish')->willReturn(new SocialPostResult(id: 'ok', provider: Provider::Meta, url: ''));

        $xPublisher = $this->createMock(PostPublisherInterface::class);
        $xPublisher->method('publish')->willThrowException(new \RuntimeException('X API down'));

        $poster = new MultiPoster();
        $result = $poster
            ->on('meta', $metaPublisher)
            ->on('x', $xPublisher)
            ->publish(new SocialPost(content: 'Test'));

        self::assertTrue($result->isPartialSuccess());
        self::assertSame('ok', $result->getResult('meta')?->id);
        self::assertSame('X API down', $result->getError('x')?->getMessage());
    }

    public function test_truncates_content_for_x(): void
    {
        $longContent = str_repeat('a', 300);

        $xPublisher = $this->createMock(PostPublisherInterface::class);
        $xPublisher->expects(self::once())
            ->method('publish')
            ->with(self::callback(function (SocialPost $post) {
                return mb_strlen($post->content) <= 280;
            }))
            ->willReturn(new SocialPostResult(id: '1', provider: Provider::X, url: ''));

        $poster = new MultiPoster();
        $poster->on('x', $xPublisher)
            ->publish(new SocialPost(content: $longContent));
    }

    public function test_limits_media_count_per_platform(): void
    {
        $mediaUrls = array_fill(0, 8, 'https://example.com/photo.jpg');

        $xPublisher = $this->createMock(PostPublisherInterface::class);
        $xPublisher->expects(self::once())
            ->method('publish')
            ->with(self::callback(function (SocialPost $post) {
                return count($post->mediaUrls) <= 4;
            }))
            ->willReturn(new SocialPostResult(id: '1', provider: Provider::X, url: ''));

        $poster = new MultiPoster();
        $poster->on('x', $xPublisher)
            ->publish(new SocialPost(content: 'Photos', mediaUrls: $mediaUrls));
    }

    public function test_does_not_truncate_for_meta(): void
    {
        $content = str_repeat('a', 3000);

        $metaPublisher = $this->createMock(PostPublisherInterface::class);
        $metaPublisher->expects(self::once())
            ->method('publish')
            ->with(self::callback(function (SocialPost $post) use ($content) {
                return $post->content === $content;
            }))
            ->willReturn(new SocialPostResult(id: '1', provider: Provider::Meta, url: ''));

        $poster = new MultiPoster();
        $poster->on('meta', $metaPublisher)
            ->publish(new SocialPost(content: $content));
    }
}
