<?php

declare(strict_types=1);

namespace BridgeKit\Providers\X\Services;

use BridgeKit\Contracts\Webhook\WebhookInterface;
use BridgeKit\DTOs\WebhookPayload;
use BridgeKit\DTOs\WebhookRegistration;
use BridgeKit\Enums\Provider;
use BridgeKit\Enums\WebhookEvent;
use BridgeKit\Providers\X\XProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * X/Twitter Account Activity API webhooks.
 *
 * @see https://developer.x.com/en/docs/twitter-api/enterprise/account-activity-api
 */
class XWebhookService extends AbstractService implements WebhookInterface
{
    public function __construct(XProvider $provider)
    {
        parent::__construct($provider);
    }

    public function subscribe(string $callbackUrl, array $events = [], array $options = []): WebhookRegistration
    {
        $envName = $options['env_name'] ?? 'production';

        $response = $this->authenticatedHttp()->post(
            "https://api.twitter.com/1.1/account_activity/all/{$envName}/webhooks.json",
            ['url' => $callbackUrl]
        );

        $json = $response->json();

        return new WebhookRegistration(
            id: $json['id'] ?? '',
            provider: Provider::X,
            callbackUrl: $callbackUrl,
            events: $events ?: ['tweet_create_events', 'favorite_events', 'follow_events'],
            metadata: [
                'env_name' => $envName,
                'valid' => $json['valid'] ?? false,
            ],
        );
    }

    public function unsubscribe(string $registrationId): bool
    {
        $envName = 'production';

        $response = $this->authenticatedHttp()->delete(
            "https://api.twitter.com/1.1/account_activity/all/{$envName}/webhooks/{$registrationId}.json"
        );

        return $response->successful();
    }

    public function verify(Request $request): bool
    {
        $signature = $request->header('X-Twitter-Webhooks-Signature', '');
        if ($signature === '') {
            return $request->has('crc_token');
        }

        $consumerSecret = $this->getConfig('client_secret') ?? '';
        $expectedHash = 'sha256=' . base64_encode(
            hash_hmac('sha256', $request->getContent(), $consumerSecret, true)
        );

        return hash_equals($expectedHash, $signature);
    }

    public function parse(Request $request): WebhookPayload
    {
        $body = $request->all();
        $forUserId = $body['for_user_id'] ?? '';

        $event = WebhookEvent::Unknown;
        $resourceId = '';
        $data = [];

        if (! empty($body['tweet_create_events'])) {
            $event = WebhookEvent::PostPublished;
            $tweet = $body['tweet_create_events'][0];
            $resourceId = $tweet['id_str'] ?? '';
            $data = $tweet;
        } elseif (! empty($body['tweet_delete_events'])) {
            $event = WebhookEvent::PostDeleted;
            $del = $body['tweet_delete_events'][0]['status'] ?? [];
            $resourceId = $del['id_str'] ?? '';
            $data = $del;
        } elseif (! empty($body['favorite_events'])) {
            $event = WebhookEvent::ReactionReceived;
            $fav = $body['favorite_events'][0];
            $resourceId = $fav['favorited_status']['id_str'] ?? '';
            $data = $fav;
        } elseif (! empty($body['follow_events'])) {
            $followEvent = $body['follow_events'][0];
            $event = ($followEvent['type'] ?? '') === 'follow'
                ? WebhookEvent::FollowerGained
                : WebhookEvent::FollowerLost;
            $resourceId = $followEvent['target']['id_str'] ?? '';
            $data = $followEvent;
        } elseif (! empty($body['direct_message_events'])) {
            $event = WebhookEvent::MessageReceived;
            $dm = $body['direct_message_events'][0];
            $resourceId = $dm['id'] ?? '';
            $data = $dm;
        }

        return new WebhookPayload(
            provider: Provider::X,
            event: $event,
            resourceId: $resourceId,
            resourceType: $event->category(),
            data: $data,
            timestamp: new DateTimeImmutable(),
            raw: $body,
            changes: [
                'for_user_id' => $forUserId,
            ],
        );
    }

    public function handleVerification(Request $request): ?string
    {
        $crcToken = $request->input('crc_token');
        if ($crcToken === null) {
            return null;
        }

        $consumerSecret = $this->getConfig('client_secret') ?? '';
        $responseToken = base64_encode(
            hash_hmac('sha256', $crcToken, $consumerSecret, true)
        );

        return json_encode(['response_token' => 'sha256=' . $responseToken]);
    }

    private function getConfig(string $key): ?string
    {
        $config = (new \ReflectionProperty($this->provider, 'config'))->getValue($this->provider);

        return $config[$key] ?? null;
    }
}
