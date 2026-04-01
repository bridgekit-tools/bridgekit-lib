<?php

declare(strict_types=1);

namespace BridgeKit\Enums;

enum Visibility: string
{
    case Public = 'public';
    case Connections = 'connections';
    case Private = 'private';
}
