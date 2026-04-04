<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Enums;

use BridgeKit\Enums\WebhookEvent;
use PHPUnit\Framework\TestCase;

final class WebhookEventTest extends TestCase
{
    public function test_storage_events_have_correct_category(): void
    {
        self::assertSame('storage', WebhookEvent::FileCreated->category());
        self::assertSame('storage', WebhookEvent::FileMoved->category());
        self::assertSame('storage', WebhookEvent::FileDeleted->category());
        self::assertSame('storage', WebhookEvent::FileTrashed->category());
    }

    public function test_social_events_have_correct_category(): void
    {
        self::assertSame('social', WebhookEvent::PostPublished->category());
        self::assertSame('social', WebhookEvent::CommentReceived->category());
        self::assertSame('social', WebhookEvent::ReactionReceived->category());
        self::assertSame('social', WebhookEvent::FollowerGained->category());
    }

    public function test_calendar_events_have_correct_category(): void
    {
        self::assertSame('calendar', WebhookEvent::EventCreated->category());
        self::assertSame('calendar', WebhookEvent::EventUpdated->category());
        self::assertSame('calendar', WebhookEvent::EventCancelled->category());
    }

    public function test_is_storage_event(): void
    {
        self::assertTrue(WebhookEvent::FileCreated->isStorageEvent());
        self::assertFalse(WebhookEvent::PostPublished->isStorageEvent());
    }

    public function test_is_social_event(): void
    {
        self::assertTrue(WebhookEvent::PostPublished->isSocialEvent());
        self::assertFalse(WebhookEvent::FileCreated->isSocialEvent());
    }

    public function test_is_calendar_event(): void
    {
        self::assertTrue(WebhookEvent::EventCreated->isCalendarEvent());
        self::assertFalse(WebhookEvent::FileCreated->isCalendarEvent());
    }

    public function test_backed_values(): void
    {
        self::assertSame('file.created', WebhookEvent::FileCreated->value);
        self::assertSame('file.moved', WebhookEvent::FileMoved->value);
        self::assertSame('post.published', WebhookEvent::PostPublished->value);
        self::assertSame('calendar.event.created', WebhookEvent::EventCreated->value);
        self::assertSame('unknown', WebhookEvent::Unknown->value);
    }
}
