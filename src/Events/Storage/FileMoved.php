<?php

declare(strict_types=1);

namespace BridgeKit\Events\Storage;

use BridgeKit\DTOs\WebhookPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileMoved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WebhookPayload $payload,
    ) {}

    public function fromFolder(): string
    {
        return $this->payload->getChange('parent_from', '');
    }

    public function toFolder(): string
    {
        return $this->payload->getChange('parent_to', '');
    }
}
