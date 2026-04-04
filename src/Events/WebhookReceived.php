<?php

declare(strict_types=1);

namespace BridgeKit\Events;

use BridgeKit\DTOs\WebhookPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic event dispatched for every incoming webhook, regardless of type.
 * Listen to this for a catch-all handler.
 */
class WebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WebhookPayload $payload,
    ) {}
}
