<?php

declare(strict_types=1);

namespace BridgeKit\Enums;

enum Provider: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';
    case Meta = 'meta';
    case LinkedIn = 'linkedin';
    case X = 'x';
}
