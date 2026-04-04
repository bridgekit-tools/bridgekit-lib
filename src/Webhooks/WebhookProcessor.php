<?php

declare(strict_types=1);

namespace BridgeKit\Webhooks;

use BridgeKit\DTOs\WebhookPayload;
use BridgeKit\Enums\WebhookEvent;
use BridgeKit\Events\Calendar\CalendarEventCancelled;
use BridgeKit\Events\Calendar\CalendarEventCreated;
use BridgeKit\Events\Calendar\CalendarEventUpdated;
use BridgeKit\Events\Social\CommentReceived;
use BridgeKit\Events\Social\EngagementReceived;
use BridgeKit\Events\Social\PostDeleted;
use BridgeKit\Events\Social\PostPublished;
use BridgeKit\Events\Storage\FileCreated;
use BridgeKit\Events\Storage\FileDeleted;
use BridgeKit\Events\Storage\FileMoved;
use BridgeKit\Events\Storage\FileTrashed;
use BridgeKit\Events\Storage\FileUpdated;
use BridgeKit\Events\WebhookReceived;

class WebhookProcessor
{
    /**
     * Dispatch Laravel events for the given webhook payload.
     * Always dispatches WebhookReceived, then the specific event.
     */
    public function process(WebhookPayload $payload): void
    {
        WebhookReceived::dispatch($payload);

        $specific = $this->resolveEvent($payload);
        if ($specific !== null) {
            $specific::dispatch($payload);
        }
    }

    /**
     * @return class-string|null
     */
    private function resolveEvent(WebhookPayload $payload): ?string
    {
        return match ($payload->event) {
            // Storage
            WebhookEvent::FileCreated => FileCreated::class,
            WebhookEvent::FileUpdated => FileUpdated::class,
            WebhookEvent::FileDeleted => FileDeleted::class,
            WebhookEvent::FileMoved => FileMoved::class,
            WebhookEvent::FileTrashed => FileTrashed::class,

            // Social
            WebhookEvent::PostPublished => PostPublished::class,
            WebhookEvent::PostDeleted => PostDeleted::class,
            WebhookEvent::CommentReceived => CommentReceived::class,
            WebhookEvent::ReactionReceived,
            WebhookEvent::MentionReceived,
            WebhookEvent::FollowerGained,
            WebhookEvent::FollowerLost => EngagementReceived::class,

            // Calendar
            WebhookEvent::EventCreated => CalendarEventCreated::class,
            WebhookEvent::EventUpdated => CalendarEventUpdated::class,
            WebhookEvent::EventCancelled => CalendarEventCancelled::class,

            default => null,
        };
    }
}
