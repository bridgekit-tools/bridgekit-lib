<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Google\Services;

use BridgeKit\Contracts\Webhook\WebhookInterface;
use BridgeKit\DTOs\WebhookPayload;
use BridgeKit\DTOs\WebhookRegistration;
use BridgeKit\Enums\Provider;
use BridgeKit\Enums\WebhookEvent;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Providers\Google\GoogleProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * Google Drive Push Notifications (Changes API) + Calendar Push.
 *
 * @see https://developers.google.com/drive/api/guides/push
 * @see https://developers.google.com/calendar/api/guides/push
 */
class GoogleWebhookService extends AbstractService implements WebhookInterface
{
    private const string DRIVE_BASE = 'https://www.googleapis.com/drive/v3';

    private const string CALENDAR_BASE = 'https://www.googleapis.com/calendar/v3';

    public function __construct(GoogleProvider $provider)
    {
        parent::__construct($provider);
    }

    public function subscribe(string $callbackUrl, array $events = [], array $options = []): WebhookRegistration
    {
        $channelId = $options['channel_id'] ?? 'bk-' . bin2hex(random_bytes(8));
        $type = $options['type'] ?? 'drive';
        $ttl = $options['ttl'] ?? 86400;
        $token = $options['token'] ?? null;

        $body = [
            'id' => $channelId,
            'type' => 'web_hook',
            'address' => $callbackUrl,
            'expiration' => (string) ((int) (microtime(true) * 1000) + ($ttl * 1000)),
        ];

        if ($token !== null) {
            $body['token'] = $token;
        }

        if ($type === 'calendar') {
            $calendarId = $options['calendar_id'] ?? 'primary';
            $url = self::CALENDAR_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/watch';
        } else {
            $resourceId = $options['resource_id'] ?? null;
            if ($resourceId !== null) {
                $url = self::DRIVE_BASE . '/files/' . rawurlencode($resourceId) . '/watch';
            } else {
                $startPageToken = $this->getStartPageToken();
                $url = self::DRIVE_BASE . '/changes/watch?pageToken=' . rawurlencode($startPageToken);
            }
        }

        $response = $this->authenticatedHttp()->post($url, $body);
        $json = $response->json();

        $expirationMs = (int) ($json['expiration'] ?? 0);

        return new WebhookRegistration(
            id: $json['id'] ?? $channelId,
            provider: Provider::Google,
            callbackUrl: $callbackUrl,
            events: $events,
            expiresAt: $expirationMs > 0
                ? (new DateTimeImmutable())->setTimestamp((int) ($expirationMs / 1000))
                : null,
            metadata: [
                'resource_id' => $json['resourceId'] ?? '',
                'resource_uri' => $json['resourceUri'] ?? '',
                'type' => $type,
            ],
        );
    }

    public function unsubscribe(string $registrationId): bool
    {
        $response = $this->authenticatedHttp()->post(
            'https://www.googleapis.com/drive/v3/channels/stop',
            [
                'id' => $registrationId,
                'resourceId' => $registrationId,
            ]
        );

        return $response->successful();
    }

    public function verify(Request $request): bool
    {
        return $request->hasHeader('X-Goog-Channel-ID')
            && $request->hasHeader('X-Goog-Resource-State');
    }

    public function parse(Request $request): WebhookPayload
    {
        $state = $request->header('X-Goog-Resource-State', '');
        $resourceId = $request->header('X-Goog-Resource-ID', '');
        $channelId = $request->header('X-Goog-Channel-ID', '');
        $changed = $request->header('X-Goog-Changed', '');

        $event = $this->mapGoogleState($state, $changed);

        $changes = [];
        if ($changed !== '') {
            foreach (explode(',', $changed) as $field) {
                $changes[trim($field)] = true;
            }
        }

        return new WebhookPayload(
            provider: Provider::Google,
            event: $event,
            resourceId: $resourceId,
            resourceType: $request->header('X-Goog-Resource-URI', '') !== '' ? 'file' : 'change',
            data: [
                'channel_id' => $channelId,
                'message_number' => $request->header('X-Goog-Message-Number', ''),
            ],
            timestamp: new DateTimeImmutable(),
            raw: $request->all(),
            changes: $changes,
        );
    }

    public function handleVerification(Request $request): ?string
    {
        if ($request->header('X-Goog-Resource-State') === 'sync') {
            return 'OK';
        }

        return null;
    }

    private function getStartPageToken(): string
    {
        $response = $this->authenticatedHttp()->get(self::DRIVE_BASE . '/changes/startPageToken');

        return $response->json('startPageToken')
            ?? throw new ProviderException('Cannot get Drive start page token.', 'google');
    }

    private function mapGoogleState(string $state, string $changed): WebhookEvent
    {
        if ($state === 'trash' || $state === 'trashed') {
            return WebhookEvent::FileTrashed;
        }

        if ($state === 'remove' || $state === 'deleted') {
            return WebhookEvent::FileDeleted;
        }

        if ($state === 'add' || $state === 'created') {
            return WebhookEvent::FileCreated;
        }

        if ($state === 'update' || $state === 'change') {
            if (str_contains($changed, 'parents')) {
                return WebhookEvent::FileMoved;
            }
            if (str_contains($changed, 'title') || str_contains($changed, 'name')) {
                return WebhookEvent::FileRenamed;
            }
            if (str_contains($changed, 'sharing') || str_contains($changed, 'permissions')) {
                return WebhookEvent::FileShared;
            }

            return WebhookEvent::FileUpdated;
        }

        return WebhookEvent::Unknown;
    }
}
