<?php

declare(strict_types=1);

namespace BridgeKit\Contracts\Calendar;

use BridgeKit\DTOs\CalendarEvent;

interface CalendarInterface
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<int, CalendarEvent>
     */
    public function listEvents(string $calendarId = 'primary', array $options = []): array;

    public function getEvent(string $eventId, string $calendarId = 'primary'): CalendarEvent;

    public function createEvent(CalendarEvent $event, string $calendarId = 'primary'): CalendarEvent;

    public function updateEvent(string $eventId, CalendarEvent $event, string $calendarId = 'primary'): CalendarEvent;

    public function deleteEvent(string $eventId, string $calendarId = 'primary'): bool;
}
