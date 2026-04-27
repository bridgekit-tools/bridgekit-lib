<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Microsoft;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\Contracts\Calendar\CalendarInterface;
use BridgeKit\Contracts\Webhook\WebhookInterface;
use BridgeKit\Providers\Microsoft\Services\MicrosoftAuthService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftCalendarService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftOneDriveService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftOutlookService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftSharePointService;
use BridgeKit\Providers\Microsoft\Services\MicrosoftWebhookService;
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

    /**
     * SharePoint document library service.
     *
     * Pass an inline configuration array to target a specific site/library:
     *
     *   $sp = BridgeKit::microsoft()->setToken($token)->sharepoint([
     *       'site_path' => '/contoso.sharepoint.com:/sites/marketing',
     *       // optional: 'drive_id' => '...',
     *   ]);
     *
     * Without arguments, it falls back to the `sharepoint` section of the
     * provider config (config/bridgekit.php).
     *
     * @param  array<string, mixed>|null  $config
     */
    public function sharepoint(?array $config = null): MicrosoftSharePointService
    {
        if ($config !== null) {
            return new MicrosoftSharePointService($this, $config);
        }

        return $this->resolveService(
            'sharepoint',
            fn (): MicrosoftSharePointService => new MicrosoftSharePointService(
                $this,
                (array) $this->config('sharepoint', []),
            ),
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
    public function webhooks(): WebhookInterface
    {
        return $this->resolveService(
            'webhooks',
            fn (): MicrosoftWebhookService => new MicrosoftWebhookService($this),
        );
    }

    public function getAvailableServices(): array
    {
        return [
            'auth' => MicrosoftAuthService::class,
            'onedrive' => MicrosoftOneDriveService::class,
            'sharepoint' => MicrosoftSharePointService::class,
            'outlook' => MicrosoftOutlookService::class,
            'calendar' => MicrosoftCalendarService::class,
            'webhooks' => MicrosoftWebhookService::class,
        ];
    }

    public function getTenant(): string
    {
        return (string) $this->config('tenant', 'common');
    }
}
