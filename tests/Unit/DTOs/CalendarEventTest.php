<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\DTOs;

use BridgeKit\DTOs\CalendarEvent;
use BridgeKit\Enums\EventStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CalendarEventTest extends TestCase
{
    public function test_constructor_with_defaults(): void
    {
        $event = new CalendarEvent();

        self::assertSame('', $event->id);
        self::assertSame('', $event->title);
        self::assertSame('', $event->description);
        self::assertNull($event->startAt);
        self::assertNull($event->endAt);
        self::assertFalse($event->allDay);
        self::assertSame('', $event->location);
        self::assertSame('UTC', $event->timezone);
        self::assertNull($event->status);
        self::assertSame([], $event->attendees);
        self::assertSame('', $event->webUrl);
        self::assertSame([], $event->metadata);
    }

    public function test_constructor_with_values(): void
    {
        $start = new DateTimeImmutable('2026-04-01T10:00:00+00:00');
        $end = new DateTimeImmutable('2026-04-01T11:00:00+00:00');

        $event = new CalendarEvent(
            id: 'evt-123',
            title: 'Standup',
            description: 'Daily standup meeting',
            startAt: $start,
            endAt: $end,
            allDay: false,
            location: 'Room 42',
            timezone: 'Europe/Paris',
            status: EventStatus::Confirmed,
            attendees: ['alice@example.com', 'bob@example.com'],
            webUrl: 'https://calendar.example.com/evt-123',
            metadata: ['provider_specific' => true],
        );

        self::assertSame('evt-123', $event->id);
        self::assertSame('Standup', $event->title);
        self::assertSame('Daily standup meeting', $event->description);
        self::assertSame($start, $event->startAt);
        self::assertSame($end, $event->endAt);
        self::assertFalse($event->allDay);
        self::assertSame('Room 42', $event->location);
        self::assertSame('Europe/Paris', $event->timezone);
        self::assertSame(EventStatus::Confirmed, $event->status);
        self::assertSame(['alice@example.com', 'bob@example.com'], $event->attendees);
        self::assertSame('https://calendar.example.com/evt-123', $event->webUrl);
        self::assertSame(['provider_specific' => true], $event->metadata);
    }

    public function test_all_day_event(): void
    {
        $event = new CalendarEvent(
            id: 'evt-all-day',
            title: 'Holiday',
            allDay: true,
            startAt: new DateTimeImmutable('2026-12-25'),
            endAt: new DateTimeImmutable('2026-12-26'),
        );

        self::assertTrue($event->allDay);
    }

    public function test_from_array(): void
    {
        $data = [
            'id' => 'evt-456',
            'title' => 'Lunch',
            'description' => 'Team lunch',
            'start_at' => '2026-05-10T12:00:00+00:00',
            'end_at' => '2026-05-10T13:00:00+00:00',
            'all_day' => false,
            'location' => 'Cafeteria',
            'timezone' => 'America/New_York',
            'status' => 'tentative',
            'attendees' => ['charlie@example.com'],
            'web_url' => 'https://calendar.example.com/evt-456',
            'metadata' => ['recurring' => false],
        ];

        $event = CalendarEvent::fromArray($data);

        self::assertSame('evt-456', $event->id);
        self::assertSame('Lunch', $event->title);
        self::assertSame('Team lunch', $event->description);
        self::assertInstanceOf(DateTimeImmutable::class, $event->startAt);
        self::assertInstanceOf(DateTimeImmutable::class, $event->endAt);
        self::assertFalse($event->allDay);
        self::assertSame('Cafeteria', $event->location);
        self::assertSame('America/New_York', $event->timezone);
        self::assertSame(EventStatus::Tentative, $event->status);
        self::assertSame(['charlie@example.com'], $event->attendees);
        self::assertSame('https://calendar.example.com/evt-456', $event->webUrl);
        self::assertSame(['recurring' => false], $event->metadata);
    }

    public function test_from_array_with_minimal_data(): void
    {
        $event = CalendarEvent::fromArray([]);

        self::assertSame('', $event->id);
        self::assertSame('', $event->title);
        self::assertNull($event->startAt);
        self::assertNull($event->endAt);
        self::assertSame('UTC', $event->timezone);
        self::assertNull($event->status);
        self::assertSame([], $event->attendees);
    }

    public function test_json_serialize(): void
    {
        $start = new DateTimeImmutable('2026-06-15T09:00:00+00:00');

        $event = new CalendarEvent(
            id: 'evt-json',
            title: 'Review',
            startAt: $start,
            timezone: 'Europe/London',
            attendees: ['dev@example.com'],
        );

        $json = $event->jsonSerialize();

        self::assertSame('evt-json', $json['id']);
        self::assertSame('Review', $json['title']);
        self::assertSame($start->format('c'), $json['start_at']);
        self::assertNull($json['end_at']);
        self::assertFalse($json['all_day']);
        self::assertSame('Europe/London', $json['timezone']);
        self::assertSame(['dev@example.com'], $json['attendees']);
        self::assertArrayHasKey('description', $json);
        self::assertArrayHasKey('location', $json);
        self::assertNull($json['status']);
        self::assertArrayHasKey('web_url', $json);
        self::assertArrayHasKey('metadata', $json);
    }

    public function test_json_encode_roundtrip(): void
    {
        $event = new CalendarEvent(
            id: 'rt-1',
            title: 'Roundtrip',
            startAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );

        $encoded = json_encode($event, JSON_THROW_ON_ERROR);
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('rt-1', $decoded['id']);
        self::assertSame('Roundtrip', $decoded['title']);
    }
}
