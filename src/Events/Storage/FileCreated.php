<?php

declare(strict_types=1);

namespace BridgeKit\Events\Storage;

use BridgeKit\DTOs\WebhookPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WebhookPayload $payload,
    ) {}
}
