<?php

declare(strict_types=1);

namespace BridgeKit\DTOs;

use BridgeKit\Enums\EventStatus;
use DateTimeImmutable;
use JsonSerializable;

final readonly class CalendarEvent implements JsonSerializable
{
    /**
     * @param  array<int, string>  $attendees
     * @param  array<string, mixed>  $metadata  Provider-specific data
     */
    public function __construct(
        public string $id = '',
        public string $title = '',
        public string $description = '',
        public ?DateTimeImmutable $startAt = null,
        public ?DateTimeImmutable $endAt = null,
        public bool $allDay = false,
        public string $location = '',
        public string $timezone = 'UTC',
        public ?EventStatus $status = null,
        public array $attendees = [],
        public string $webUrl = '',
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            title: $data['title'] ?? '',
            description: $data['description'] ?? '',
            startAt: isset($data['start_at']) ? new DateTimeImmutable($data['start_at']) : null,
            endAt: isset($data['end_at']) ? new DateTimeImmutable($data['end_at']) : null,
            allDay: $data['all_day'] ?? false,
            location: $data['location'] ?? '',
            timezone: $data['timezone'] ?? 'UTC',
            status: EventStatus::tryFrom($data['status'] ?? ''),
            attendees: $data['attendees'] ?? [],
            webUrl: $data['web_url'] ?? '',
            metadata: $data['metadata'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'start_at' => $this->startAt?->format('c'),
            'end_at' => $this->endAt?->format('c'),
            'all_day' => $this->allDay,
            'location' => $this->location,
            'timezone' => $this->timezone,
            'status' => $this->status?->value,
            'attendees' => $this->attendees,
            'web_url' => $this->webUrl,
            'metadata' => $this->metadata,
        ];
    }
}
