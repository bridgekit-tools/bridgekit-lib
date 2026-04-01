<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Microsoft;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\Contracts\Calendar\CalendarInterface;
use BridgeKit\Providers\Microsoft\Services\MicrosoftAuthService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftCalendarService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftOneDriveService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftOutlookService;
use BridgeKit\Support\AbstractProvider;

class MicrosoftProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'microsoft';
    }

    public function auth(): OAuthInterface
    {
        /** @var OAuthInterface */
        return $this->resolveService(
            'auth',
            fn (): MicrosoftAuthService => new MicrosoftAuthService($this->config, $this),
        );
    }

    public function onedrive(): MicrosoftOneDriveService
    {
        return $this->resolveService(
            'onedrive',
            fn (): MicrosoftOneDriveService => new MicrosoftOneDriveService($this),
        );
    }

    public function outlook(): MicrosoftOutlookService
    {
        return $this->resolveService(
            'outlook',
            fn (): MicrosoftOutlookService => new MicrosoftOutlookService($this),
        );
    }

    public function calendar(): CalendarInterface
    {
        return $this->resolveService(
            'calendar',
            fn (): MicrosoftCalendarService => new MicrosoftCalendarService($this),
        );
    }

    /**
     * @return array<string, class-string>
     */
    public function getAvailableServices(): array
    {
        return [
            'auth' => MicrosoftAuthService::class,
            'onedrive' => MicrosoftOneDriveService::class,
            'outlook' => MicrosoftOutlookService::class,
            'calendar' => MicrosoftCalendarService::class,
        ];
    }

    public function getTenant(): string
    {
        return (string) $this->config('tenant', 'common');
    }
}
