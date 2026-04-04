<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\DTOs;

use BridgeKit\DTOs\WebhookRegistration;
use BridgeKit\Enums\Provider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WebhookRegistrationTest extends TestCase
{
    public function test_constructor_assigns_properties(): void
    {
        $reg = new WebhookRegistration(
            id: 'reg-123',
            provider: Provider::Google,
            callbackUrl: 'https://app.test/webhooks/google',
            events: ['file.created', 'file.updated'],
            secret: 'secret-abc',
        );

        self::assertSame('reg-123', $reg->id);
        self::assertSame(Provider::Google, $reg->provider);
        self::assertSame('https://app.test/webhooks/google', $reg->callbackUrl);
        self::assertCount(2, $reg->events);
        self::assertSame('secret-abc', $reg->secret);
    }

    public function test_is_expired_returns_false_when_null(): void
    {
        $reg = new WebhookRegistration(
            id: '1',
            provider: Provider::Meta,
            callbackUrl: '',
        );

        self::assertFalse($reg->isExpired());
    }

    public function test_is_expired_returns_true_when_past(): void
    {
        $reg = new WebhookRegistration(
            id: '1',
            provider: Provider::Meta,
            callbackUrl: '',
            expiresAt: new DateTimeImmutable('-1 hour'),
        );

        self::assertTrue($reg->isExpired());
    }

    public function test_is_expired_returns_false_when_future(): void
    {
        $reg = new WebhookRegistration(
            id: '1',
            provider: Provider::Meta,
            callbackUrl: '',
            expiresAt: new DateTimeImmutable('+1 hour'),
        );

        self::assertFalse($reg->isExpired());
    }

    public function test_json_serialize(): void
    {
        $reg = new WebhookRegistration(
            id: 'reg-x',
            provider: Provider::X,
            callbackUrl: 'https://app.test/wh',
            events: ['post.published'],
            metadata: ['env' => 'prod'],
        );

        $json = $reg->jsonSerialize();

        self::assertSame('reg-x', $json['id']);
        self::assertSame('x', $json['provider']);
        self::assertSame(['env' => 'prod'], $json['metadata']);
    }
}
