<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Google\Services;

use BridgeKit\Contracts\Calendar\CalendarInterface;
use BridgeKit\DTOs\CalendarEvent;
use BridgeKit\Enums\EventStatus;
use BridgeKit\Providers\Google\GoogleProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;

class GoogleCalendarService extends AbstractService implements CalendarInterface
{
    private const string BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        GoogleProvider $provider,
    ) {
        parent::__construct($provider);
    }

    public function listEvents(string $calendarId = 'primary', array $options = []): array
    {
        $query = array_merge([
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
        ], $options);

        $response = $this->authenticatedHttp()->get(
            self::BASE_URL . '/calendars/' . rawurlencode($calendarId) . '/events',
            $query
        );

        $items = $response->json('items') ?? [];

        return array_map(fn (array $item): CalendarEvent => $this->mapEvent($item), $items);
    }

    public function getEvent(string $eventId, string $calendarId = 'primary'): CalendarEvent
    {
        $response = $this->authenticatedHttp()->get(
            self::BASE_URL . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId)
        );

        return $this->mapEvent($response->json());
    }

    public function createEvent(CalendarEvent $event, string $calendarId = 'primary'): CalendarEvent
    {
        $response = $this->authenticatedHttp()->post(
            self::BASE_URL . '/calendars/' . rawurlencode($calendarId) . '/events',
            $this->buildEventPayload($event)
        );

        return $this->mapEvent($response->json());
    }

    public function updateEvent(string $eventId, CalendarEvent $event, string $calendarId = 'primary'): CalendarEvent
    {
        $response = $this->authenticatedHttp()->put(
            self::BASE_URL . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId),
            $this->buildEventPayload($event)
        );

        return $this->mapEvent($response->json());
    }

    public function deleteEvent(string $eventId, string $calendarId = 'primary'): bool
    {
        $response = $this->authenticatedHttp()->delete(
            self::BASE_URL . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId)
        );

        return $response->successful();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapEvent(array $data): CalendarEvent
    {
        $startAt = null;
        $endAt = null;
        $allDay = false;

        if (isset($data['start']['date'])) {
            $allDay = true;
            $startAt = new DateTimeImmutable($data['start']['date']);
        } elseif (isset($data['start']['dateTime'])) {
            $startAt = new DateTimeImmutable($data['start']['dateTime']);
        }

        if (isset($data['end']['date'])) {
            $endAt = new DateTimeImmutable($data['end']['date']);
        } elseif (isset($data['end']['dateTime'])) {
            $endAt = new DateTimeImmutable($data['end']['dateTime']);
        }

        $timezone = $data['start']['timeZone'] ?? $data['end']['timeZone'] ?? 'UTC';

        $attendees = [];
        foreach ($data['attendees'] ?? [] as $attendee) {
            if (isset($attendee['email'])) {
                $attendees[] = $attendee['email'];
            }
        }

        return new CalendarEvent(
            id: (string) ($data['id'] ?? ''),
            title: (string) ($data['summary'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            startAt: $startAt,
            endAt: $endAt,
            allDay: $allDay,
            location: (string) ($data['location'] ?? ''),
            timezone: (string) $timezone,
            status: EventStatus::tryFrom((string) ($data['status'] ?? '')),
            attendees: $attendees,
            webUrl: (string) ($data['htmlLink'] ?? ''),
            metadata: $data,
        );
    }

    private function buildEventPayload(CalendarEvent $event): array
    {
        $payload = array_filter([
            'summary' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
        ], fn (string $v): bool => $v !== '');

        if ($event->allDay) {
            if ($event->startAt !== null) {
                $payload['start'] = ['date' => $event->startAt->format('Y-m-d')];
            }
            if ($event->endAt !== null) {
                $payload['end'] = ['date' => $event->endAt->format('Y-m-d')];
            }
        } else {
            if ($event->startAt !== null) {
                $payload['start'] = ['dateTime' => $event->startAt->format('c'), 'timeZone' => $event->timezone];
            }
            if ($event->endAt !== null) {
                $payload['end'] = ['dateTime' => $event->endAt->format('c'), 'timeZone' => $event->timezone];
            }
        }

        if ($event->attendees !== []) {
            $payload['attendees'] = array_map(
                fn (string $email): array => ['email' => $email],
                $event->attendees
            );
        }

        return $payload;
    }
}
