<?php

declare(strict_types=1);

namespace BridgeKit\Enums;

enum ServiceType: string
{
    case Auth = 'auth';
    case Drive = 'drive';
    case OneDrive = 'onedrive';
    case Gmail = 'gmail';
    case Outlook = 'outlook';
    case Calendar = 'calendar';
    case Posts = 'posts';
    case Storage = 'storage';
}
