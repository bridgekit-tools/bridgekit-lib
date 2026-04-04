<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Meta\Services;

use BridgeKit\Contracts\Webhook\WebhookInterface;
use BridgeKit\DTOs\WebhookPayload;
use BridgeKit\DTOs\WebhookRegistration;
use BridgeKit\Enums\Provider;
use BridgeKit\Enums\WebhookEvent;
use BridgeKit\Providers\Meta\MetaProvider;
use BridgeKit\Support\AbstractService;
use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * Meta Webhooks for Pages (feed, mentions, comments, reactions).
 *
 * @see https://developers.facebook.com/docs/graph-api/webhooks
 */
class MetaWebhookService extends AbstractService implements WebhookInterface
{
    public function __construct(MetaProvider $provider)
    {
        parent::__construct($provider);
    }

    /**
     * Meta webhooks are registered via the App Dashboard, not via API.
     * This method stores the config and returns a registration record.
     */
    public function subscribe(string $callbackUrl, array $events = [], array $options = []): WebhookRegistration
    {
        $verifyToken = $options['verify_token'] ?? bin2hex(random_bytes(16));

        return new WebhookRegistration(
            id: 'meta-webhook-' . bin2hex(random_bytes(4)),
            provider: Provider::Meta,
            callbackUrl: $callbackUrl,
            events: $events ?: ['feed', 'mention', 'messages'],
            secret: $verifyToken,
            metadata: [
                'verify_token' => $verifyToken,
                'app_id' => $options['app_id'] ?? '',
                'note' => 'Configure this webhook URL and verify token in the Meta App Dashboard → Webhooks.',
            ],
        );
    }

    public function unsubscribe(string $registrationId): bool
    {
        return true;
    }

    public function verify(Request $request): bool
    {
        $appSecret = $this->provider->getToken()?->accessToken
            ? ($this->getConfig('app_secret') ?? '')
            : '';

        $signature = $request->header('X-Hub-Signature-256', '');
        if ($signature === '' || $appSecret === '') {
            return $request->has('hub_verify_token');
        }

        $expectedHash = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedHash, $signature);
    }

    public function parse(Request $request): WebhookPayload
    {
        $body = $request->all();
        $entry = $body['entry'][0] ?? [];
        $changes = $entry['changes'][0] ?? [];

        $field = $changes['field'] ?? '';
        $value = $changes['value'] ?? [];

        $event = match ($field) {
            'feed' => $this->parseFeedEvent($value),
            'mention' => WebhookEvent::MentionReceived,
            'messages', 'messaging' => WebhookEvent::MessageReceived,
            'ratings', 'reactions' => WebhookEvent::ReactionReceived,
            default => WebhookEvent::Unknown,
        };

        return new WebhookPayload(
            provider: Provider::Meta,
            event: $event,
            resourceId: $value['post_id'] ?? $value['comment_id'] ?? $entry['id'] ?? '',
            resourceType: $field,
            data: $value,
            timestamp: isset($value['created_time'])
                ? new DateTimeImmutable('@' . $value['created_time'])
                : new DateTimeImmutable(),
            raw: $body,
            changes: [
                'field' => $field,
                'item' => $value['item'] ?? '',
                'verb' => $value['verb'] ?? '',
            ],
        );
    }

    public function handleVerification(Request $request): ?string
    {
        if ($request->input('hub_mode') === 'subscribe') {
            $verifyToken = $this->getConfig('webhook_verify_token') ?? '';
            if ($request->input('hub_verify_token') === $verifyToken) {
                return $request->input('hub_challenge', '');
            }
        }

        return null;
    }

    private function parseFeedEvent(array $value): WebhookEvent
    {
        $item = $value['item'] ?? '';
        $verb = $value['verb'] ?? '';

        if ($item === 'comment') {
            return WebhookEvent::CommentReceived;
        }

        if ($item === 'reaction' || $item === 'like') {
            return WebhookEvent::ReactionReceived;
        }

        if ($item === 'post' || $item === 'status' || $item === 'photo' || $item === 'video') {
            return match ($verb) {
                'add', 'edited' => WebhookEvent::PostPublished,
                'remove' => WebhookEvent::PostDeleted,
                default => WebhookEvent::Unknown,
            };
        }

        return WebhookEvent::Unknown;
    }

    private function getConfig(string $key): ?string
    {
        $config = (new \ReflectionProperty($this->provider, 'config'))->getValue($this->provider);

        return $config[$key] ?? null;
    }
}
