<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Microsoft\Services;

use BridgeKit\Contracts\Calendar\CalendarInterface;
use BridgeKit\DTOs\CalendarEvent;
use BridgeKit\Enums\EventStatus;
use BridgeKit\Providers\Microsoft\MicrosoftProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;

class MicrosoftCalendarService extends AbstractService implements CalendarInterface
{
    private const string BASE_URL = 'https://graph.microsoft.com/v1.0/me';

    public function __construct(MicrosoftProvider $provider)
    {
        parent::__construct($provider);
    }

    public function listEvents(string $calendarId = 'primary', array $options = []): array
    {
        $url = $calendarId === 'primary'
            ? self::BASE_URL . '/events'
            : self::BASE_URL . '/calendars/' . rawurlencode($calendarId) . '/events';

        $query = array_merge([
            '$top' => 50,
            '$orderby' => 'start/dateTime',
        ], $options);

        $response = $this->authenticatedHttp()->get($url, $query);
        $items = $response->json('value') ?? [];

        return array_map(fn (array $item): CalendarEvent => $this->mapEvent($item), $items);
    }

    public function getEvent(string $eventId, string $calendarId = 'primary'): CalendarEvent
    {
        $url = $calendarId === 'primary'
            ? self::BASE_URL . '/events/' . rawurlencode($eventId)
            : self::BASE_URL . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId);

        $response = $this->authenticatedHttp()->get($url);

        return $this->mapEvent($response->json());
    }

    public function createEvent(CalendarEvent $event, string $calendarId = 'primary'): CalendarEvent
    {
        $url = $calendarId === 'primary'
            ? self::BASE_URL . '/events'
            : self::BASE_URL . '/calendars/' . rawurlencode($calendarId) . '/events';

        $response = $this->authenticatedHttp()->post($url, $this->buildEventPayload($event));

        return $this->mapEvent($response->json());
    }

    public function updateEvent(string $eventId, CalendarEvent $event, string $calendarId = 'primary'): CalendarEvent
    {
        $url = $calendarId === 'primary'
            ? self::BASE_URL . '/events/' . rawurlencode($eventId)
            : self::BASE_URL . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId);

        $response = $this->authenticatedHttp()->patch($url, $this->buildEventPayload($event));

        return $this->mapEvent($response->json());
    }

    public function deleteEvent(string $eventId, string $calendarId = 'primary'): bool
    {
        $url = $calendarId === 'primary'
            ? self::BASE_URL . '/events/' . rawurlencode($eventId)
            : self::BASE_URL . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId);

        $response = $this->authenticatedHttp()->delete($url);

        return $response->successful();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapEvent(array $data): CalendarEvent
    {
        $startAt = null;
        $endAt = null;
        $allDay = $data['isAllDay'] ?? false;

        if (isset($data['start']['dateTime'])) {
            $tz = $data['start']['timeZone'] ?? 'UTC';
            $startAt = new DateTimeImmutable($data['start']['dateTime'], new \DateTimeZone($tz));
        }

        if (isset($data['end']['dateTime'])) {
            $tz = $data['end']['timeZone'] ?? 'UTC';
            $endAt = new DateTimeImmutable($data['end']['dateTime'], new \DateTimeZone($tz));
        }

        $timezone = $data['start']['timeZone'] ?? $data['end']['timeZone'] ?? 'UTC';

        $attendees = [];
        foreach ($data['attendees'] ?? [] as $attendee) {
            $email = $attendee['emailAddress']['address'] ?? null;
            if ($email !== null) {
                $attendees[] = $email;
            }
        }

        return new CalendarEvent(
            id: (string) ($data['id'] ?? ''),
            title: (string) ($data['subject'] ?? ''),
            description: (string) ($data['bodyPreview'] ?? $data['body']['content'] ?? ''),
            startAt: $startAt,
            endAt: $endAt,
            allDay: (bool) $allDay,
            location: (string) ($data['location']['displayName'] ?? ''),
            timezone: (string) $timezone,
            status: EventStatus::tryFrom(strtolower((string) ($data['showAs'] ?? ''))),
            attendees: $attendees,
            webUrl: (string) ($data['webLink'] ?? ''),
            metadata: $data,
        );
    }

    private function buildEventPayload(CalendarEvent $event): array
    {
        $payload = [];

        if ($event->title !== '') {
            $payload['subject'] = $event->title;
        }

        if ($event->description !== '') {
            $payload['body'] = [
                'contentType' => 'text',
                'content' => $event->description,
            ];
        }

        if ($event->location !== '') {
            $payload['location'] = ['displayName' => $event->location];
        }

        $payload['isAllDay'] = $event->allDay;

        if ($event->startAt !== null) {
            $payload['start'] = [
                'dateTime' => $event->startAt->format('Y-m-d\TH:i:s'),
                'timeZone' => $event->timezone,
            ];
        }

        if ($event->endAt !== null) {
            $payload['end'] = [
                'dateTime' => $event->endAt->format('Y-m-d\TH:i:s'),
                'timeZone' => $event->timezone,
            ];
        }

        if ($event->attendees !== []) {
            $payload['attendees'] = array_map(
                fn (string $email): array => [
                    'emailAddress' => ['address' => $email],
                    'type' => 'required',
                ],
                $event->attendees
            );
        }

        return $payload;
    }
}
