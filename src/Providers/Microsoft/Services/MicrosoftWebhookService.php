<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Microsoft\Services;

use BridgeKit\Contracts\Webhook\WebhookInterface;
use BridgeKit\DTOs\WebhookPayload;
use BridgeKit\DTOs\WebhookRegistration;
use BridgeKit\Enums\Provider;
use BridgeKit\Enums\WebhookEvent;
use BridgeKit\Providers\Microsoft\MicrosoftProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * Microsoft Graph Subscriptions (Change Notifications).
 *
 * @see https://learn.microsoft.com/en-us/graph/webhooks
 */
class MicrosoftWebhookService extends AbstractService implements WebhookInterface
{
    private const string GRAPH_URL = 'https://graph.microsoft.com/v1.0';

    public function __construct(MicrosoftProvider $provider)
    {
        parent::__construct($provider);
    }

    public function subscribe(string $callbackUrl, array $events = [], array $options = []): WebhookRegistration
    {
        $resource = $options['resource'] ?? 'me/drive/root';
        $changeType = $options['change_type'] ?? 'updated';
        $ttlMinutes = $options['ttl_minutes'] ?? 4230; // ~3 days max for drive

        $secret = $options['secret'] ?? bin2hex(random_bytes(16));
        $expiration = (new DateTimeImmutable())->modify("+{$ttlMinutes} minutes");

        $response = $this->authenticatedHttp()->post(self::GRAPH_URL . '/subscriptions', [
            'changeType' => $changeType,
            'notificationUrl' => $callbackUrl,
            'resource' => $resource,
            'expirationDateTime' => $expiration->format('Y-m-d\TH:i:s.000\Z'),
            'clientState' => $secret,
        ]);

        $json = $response->json();

        return new WebhookRegistration(
            id: $json['id'] ?? '',
            provider: Provider::Microsoft,
            callbackUrl: $callbackUrl,
            events: $events ?: [
                $changeType,
            ],
            expiresAt: isset($json['expirationDateTime'])
                ? new DateTimeImmutable($json['expirationDateTime'])
                : $expiration,
            secret: $secret,
            metadata: [
                'resource' => $json['resource'] ?? $resource,
                'change_type' => $json['changeType'] ?? $changeType,
            ],
        );
    }

    public function unsubscribe(string $registrationId): bool
    {
        $response = $this->authenticatedHttp()
            ->delete(self::GRAPH_URL . '/subscriptions/' . rawurlencode($registrationId));

        return $response->successful() || $response->status() === 404;
    }

    public function verify(Request $request): bool
    {
        if ($request->has('validationToken')) {
            return true;
        }

        $clientState = $request->input('value.0.clientState', '');

        return $clientState !== '';
    }

    public function parse(Request $request): WebhookPayload
    {
        $notifications = $request->input('value', []);
        $first = $notifications[0] ?? [];

        $changeType = $first['changeType'] ?? '';
        $resource = $first['resource'] ?? '';
        $resourceData = $first['resourceData'] ?? [];

        $event = match ($changeType) {
            'created' => $this->inferEventFromResource($resource, 'created'),
            'updated' => $this->inferEventFromResource($resource, 'updated'),
            'deleted' => $this->inferEventFromResource($resource, 'deleted'),
            default => WebhookEvent::Unknown,
        };

        return new WebhookPayload(
            provider: Provider::Microsoft,
            event: $event,
            resourceId: $resourceData['id'] ?? '',
            resourceType: $this->getResourceType($resource),
            data: [
                'change_type' => $changeType,
                'resource' => $resource,
                'tenant_id' => $first['tenantId'] ?? '',
                'subscription_id' => $first['subscriptionId'] ?? '',
                'client_state' => $first['clientState'] ?? '',
            ],
            timestamp: isset($first['changeTime']) ? new DateTimeImmutable($first['changeTime']) : new DateTimeImmutable(),
            raw: $notifications,
        );
    }

    public function handleVerification(Request $request): ?string
    {
        if ($request->has('validationToken')) {
            return $request->input('validationToken');
        }

        return null;
    }

    /**
     * Renew a subscription before it expires.
     */
    public function renew(string $subscriptionId, int $ttlMinutes = 4230): WebhookRegistration
    {
        $expiration = (new DateTimeImmutable())->modify("+{$ttlMinutes} minutes");

        $response = $this->authenticatedHttp()->patch(
            self::GRAPH_URL . '/subscriptions/' . rawurlencode($subscriptionId),
            ['expirationDateTime' => $expiration->format('Y-m-d\TH:i:s.000\Z')]
        );

        $json = $response->json();

        return new WebhookRegistration(
            id: $json['id'] ?? $subscriptionId,
            provider: Provider::Microsoft,
            callbackUrl: $json['notificationUrl'] ?? '',
            expiresAt: isset($json['expirationDateTime'])
                ? new DateTimeImmutable($json['expirationDateTime'])
                : $expiration,
            metadata: [
                'resource' => $json['resource'] ?? '',
                'change_type' => $json['changeType'] ?? '',
            ],
        );
    }

    private function inferEventFromResource(string $resource, string $changeType): WebhookEvent
    {
        if (str_contains($resource, 'drive') || str_contains($resource, 'items')) {
            return match ($changeType) {
                'created' => WebhookEvent::FileCreated,
                'updated' => WebhookEvent::FileUpdated,
                'deleted' => WebhookEvent::FileDeleted,
                default => WebhookEvent::Unknown,
            };
        }

        if (str_contains($resource, 'events') || str_contains($resource, 'calendar')) {
            return match ($changeType) {
                'created' => WebhookEvent::EventCreated,
                'updated' => WebhookEvent::EventUpdated,
                'deleted' => WebhookEvent::EventCancelled,
                default => WebhookEvent::Unknown,
            };
        }

        if (str_contains($resource, 'messages') || str_contains($resource, 'mail')) {
            return WebhookEvent::EmailReceived;
        }

        return WebhookEvent::Unknown;
    }

    private function getResourceType(string $resource): string
    {
        if (str_contains($resource, 'drive') || str_contains($resource, 'items')) {
            return 'file';
        }
        if (str_contains($resource, 'events') || str_contains($resource, 'calendar')) {
            return 'calendar_event';
        }
        if (str_contains($resource, 'messages')) {
            return 'email';
        }

        return 'unknown';
    }
}
