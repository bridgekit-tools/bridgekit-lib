<?php

declare(strict_types=1);

namespace BridgeKit\Enums;

enum WebhookEvent: string
{
    // Storage
    case FileCreated = 'file.created';
    case FileUpdated = 'file.updated';
    case FileDeleted = 'file.deleted';
    case FileMoved = 'file.moved';
    case FileRenamed = 'file.renamed';
    case FileShared = 'file.shared';
    case FileTrashed = 'file.trashed';

    // Social
    case PostPublished = 'post.published';
    case PostDeleted = 'post.deleted';
    case CommentReceived = 'comment.received';
    case ReactionReceived = 'reaction.received';
    case MentionReceived = 'mention.received';
    case FollowerGained = 'follower.gained';
    case FollowerLost = 'follower.lost';
    case MessageReceived = 'message.received';

    // Calendar
    case EventCreated = 'calendar.event.created';
    case EventUpdated = 'calendar.event.updated';
    case EventCancelled = 'calendar.event.cancelled';
    case EventRsvp = 'calendar.event.rsvp';

    // Email
    case EmailReceived = 'email.received';

    // Auth
    case TokenRefreshed = 'token.refreshed';
    case TokenRevoked = 'token.revoked';

    // Generic
    case Unknown = 'unknown';

    public function category(): string
    {
        return match (true) {
            str_starts_with($this->value, 'file.') => 'storage',
            str_starts_with($this->value, 'post.'),
            str_starts_with($this->value, 'comment.'),
            str_starts_with($this->value, 'reaction.'),
            str_starts_with($this->value, 'mention.'),
            str_starts_with($this->value, 'follower.'),
            str_starts_with($this->value, 'message.') => 'social',
            str_starts_with($this->value, 'calendar.') => 'calendar',
            str_starts_with($this->value, 'email.') => 'email',
            str_starts_with($this->value, 'token.') => 'auth',
            default => 'unknown',
        };
    }

    public function isStorageEvent(): bool
    {
        return $this->category() === 'storage';
    }

    public function isSocialEvent(): bool
    {
        return $this->category() === 'social';
    }

    public function isCalendarEvent(): bool
    {
        return $this->category() === 'calendar';
    }
}
