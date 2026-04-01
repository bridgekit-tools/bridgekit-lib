<?php

declare(strict_types=1);

namespace BridgeKit\Enums;

enum EventStatus: string
{
    case Confirmed = 'confirmed';
    case Tentative = 'tentative';
    case Cancelled = 'cancelled';
}
