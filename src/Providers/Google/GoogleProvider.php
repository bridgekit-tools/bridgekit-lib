<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Google;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\Contracts\Calendar\CalendarInterface;
use BridgeKit\Providers\Google\Services\GoogleAuthService;
use BridgeKit\Providers\Google\Services\GoogleCalendarService;
use BridgeKit\Providers\Google\Services\GoogleDriveService;
use BridgeKit\Providers\Google\Services\GoogleGmailService;
use BridgeKit\Support\AbstractProvider;

class GoogleProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'google';
    }

    public function auth(): OAuthInterface
    {
        return $this->resolveService('auth', fn () => new GoogleAuthService($this->config, $this));
    }

    public function drive(): GoogleDriveService
    {
        return $this->resolveService('drive', fn () => new GoogleDriveService($this));
    }

    public function gmail(): GoogleGmailService
    {
        return $this->resolveService('gmail', fn () => new GoogleGmailService($this));
    }

    public function calendar(): CalendarInterface
    {
        return $this->resolveService('calendar', fn () => new GoogleCalendarService($this));
    }

    public function getAvailableServices(): array
    {
        return [
            'auth' => GoogleAuthService::class,
            'drive' => GoogleDriveService::class,
            'gmail' => GoogleGmailService::class,
            'calendar' => GoogleCalendarService::class,
        ];
    }
}
