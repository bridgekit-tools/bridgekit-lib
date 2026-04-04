<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\DTOs;

use BridgeKit\DTOs\WebhookPayload;
use BridgeKit\Enums\Provider;
use BridgeKit\Enums\WebhookEvent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WebhookPayloadTest extends TestCase
{
    public function test_constructor_assigns_properties(): void
    {
        $ts = new DateTimeImmutable();
        $payload = new WebhookPayload(
            provider: Provider::Google,
            event: WebhookEvent::FileMoved,
            resourceId: 'abc123',
            resourceType: 'file',
            data: ['key' => 'value'],
            timestamp: $ts,
            raw: ['raw' => 'data'],
            changes: ['parent_from' => 'old', 'parent_to' => 'new'],
        );

        self::assertSame(Provider::Google, $payload->provider);
        self::assertSame(WebhookEvent::FileMoved, $payload->event);
        self::assertSame('abc123', $payload->resourceId);
        self::assertSame('file', $payload->resourceType);
        self::assertSame('value', $payload->data['key']);
        self::assertSame($ts, $payload->timestamp);
        self::assertSame('old', $payload->getChange('parent_from'));
        self::assertSame('new', $payload->getChange('parent_to'));
        self::assertTrue($payload->hasChanges());
    }

    public function test_get_change_returns_default(): void
    {
        $payload = new WebhookPayload(
            provider: Provider::Google,
            event: WebhookEvent::FileCreated,
        );

        self::assertNull($payload->getChange('nonexistent'));
        self::assertSame('default', $payload->getChange('nonexistent', 'default'));
        self::assertFalse($payload->hasChanges());
    }

    public function test_from_array(): void
    {
        $payload = WebhookPayload::fromArray([
            'provider' => 'meta',
            'event' => 'post.published',
            'resource_id' => 'post-123',
            'resource_type' => 'post',
            'data' => ['content' => 'Hello'],
            'timestamp' => '2026-04-01T12:00:00+00:00',
            'changes' => ['verb' => 'add'],
        ]);

        self::assertSame(Provider::Meta, $payload->provider);
        self::assertSame(WebhookEvent::PostPublished, $payload->event);
        self::assertSame('post-123', $payload->resourceId);
        self::assertSame('add', $payload->getChange('verb'));
    }

    public function test_json_serialize(): void
    {
        $payload = new WebhookPayload(
            provider: Provider::Microsoft,
            event: WebhookEvent::EventUpdated,
            resourceId: 'ev-456',
            resourceType: 'calendar_event',
        );

        $json = $payload->jsonSerialize();

        self::assertSame('microsoft', $json['provider']);
        self::assertSame('calendar.event.updated', $json['event']);
        self::assertSame('ev-456', $json['resource_id']);
    }
}
