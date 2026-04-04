<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\DTOs;

use BridgeKit\DTOs\MultiPostResult;
use BridgeKit\DTOs\SocialPostResult;
use BridgeKit\Enums\Provider;
use PHPUnit\Framework\TestCase;

final class MultiPostResultTest extends TestCase
{
    public function test_full_success(): void
    {
        $result = new MultiPostResult(
            succeeded: [
                'meta' => new SocialPostResult(id: '1', provider: Provider::Meta, url: 'https://fb.com/1'),
                'x' => new SocialPostResult(id: '2', provider: Provider::X, url: 'https://x.com/2'),
            ],
        );

        self::assertTrue($result->isFullSuccess());
        self::assertFalse($result->isPartialSuccess());
        self::assertFalse($result->isFullFailure());
        self::assertCount(2, $result->succeededProviders());
        self::assertCount(0, $result->failedProviders());
    }

    public function test_partial_success(): void
    {
        $result = new MultiPostResult(
            succeeded: [
                'meta' => new SocialPostResult(id: '1', provider: Provider::Meta, url: ''),
            ],
            failed: [
                'x' => new \RuntimeException('API down'),
            ],
        );

        self::assertFalse($result->isFullSuccess());
        self::assertTrue($result->isPartialSuccess());
        self::assertFalse($result->isFullFailure());
    }

    public function test_full_failure(): void
    {
        $result = new MultiPostResult(
            failed: [
                'meta' => new \RuntimeException('err1'),
                'x' => new \RuntimeException('err2'),
            ],
        );

        self::assertFalse($result->isFullSuccess());
        self::assertFalse($result->isPartialSuccess());
        self::assertTrue($result->isFullFailure());
    }

    public function test_get_result_by_provider(): void
    {
        $metaResult = new SocialPostResult(id: '1', provider: Provider::Meta, url: 'https://fb.com/1');
        $result = new MultiPostResult(succeeded: ['meta' => $metaResult]);

        self::assertSame($metaResult, $result->getResult(Provider::Meta));
        self::assertSame($metaResult, $result->getResult('meta'));
        self::assertNull($result->getResult('x'));
    }

    public function test_get_error_by_provider(): void
    {
        $error = new \RuntimeException('fail');
        $result = new MultiPostResult(failed: ['x' => $error]);

        self::assertSame($error, $result->getError(Provider::X));
        self::assertNull($result->getError('meta'));
    }

    public function test_json_serialize(): void
    {
        $result = new MultiPostResult(
            succeeded: [
                'meta' => new SocialPostResult(id: '1', provider: Provider::Meta, url: 'https://fb.com/1'),
            ],
            failed: [
                'x' => new \RuntimeException('API error', 500),
            ],
        );

        $json = $result->jsonSerialize();

        self::assertSame(2, $json['total']);
        self::assertSame(1, $json['success_count']);
        self::assertSame(1, $json['failure_count']);
        self::assertSame('API error', $json['failed']['x']['error']);
    }
}
