<?php

declare(strict_types=1);

namespace BridgeKit\Contracts\Webhook;

use BridgeKit\DTOs\WebhookPayload;
use BridgeKit\DTOs\WebhookRegistration;
use Illuminate\Http\Request;

interface WebhookInterface
{
    /**
     * Register a webhook subscription with the provider.
     *
     * @param  string  $callbackUrl  The URL the provider will POST to
     * @param  array<string>  $events  Events to subscribe to (provider-specific)
     * @param  array<string, mixed>  $options  Extra options (resourceId, secret, ttl, etc.)
     */
    public function subscribe(string $callbackUrl, array $events = [], array $options = []): WebhookRegistration;

    /**
     * Remove a webhook subscription.
     */
    public function unsubscribe(string $registrationId): bool;

    /**
     * Verify that the incoming request is genuinely from the provider.
     */
    public function verify(Request $request): bool;

    /**
     * Parse the raw incoming webhook request into a typed WebhookPayload.
     */
    public function parse(Request $request): WebhookPayload;

    /**
     * Handle the provider's verification challenge (Meta hub.verify, Google sync, etc.)
     * Returns the response body or null if this request is not a verification challenge.
     */
    public function handleVerification(Request $request): ?string;
}
