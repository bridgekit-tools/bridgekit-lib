<?php

declare(strict_types=1);

namespace BridgeKit\Webhooks;

use BridgeKit\Contracts\Webhook\WebhookInterface;
use BridgeKit\Enums\Provider;
use BridgeKit\Exceptions\InvalidConfigException;
use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Support\ConnectManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function __construct(
        private readonly ConnectManager $manager,
        private readonly WebhookProcessor $processor,
    ) {}

    public function __invoke(Request $request, string $provider): Response
    {
        $providerEnum = $this->resolveProvider($provider);
        $webhookService = $this->resolveWebhookService($providerEnum);

        // Handle verification challenges (Meta hub.verify, Google sync, X CRC)
        $verificationResponse = $webhookService->handleVerification($request);
        if ($verificationResponse !== null) {
            return new Response($verificationResponse, 200);
        }

        // Verify signature / authenticity
        if (! $webhookService->verify($request)) {
            return new Response('Unauthorized', 403);
        }

        // Parse and dispatch
        $payload = $webhookService->parse($request);
        $this->processor->process($payload);

        return new Response('OK', 200);
    }

    private function resolveProvider(string $provider): Provider
    {
        $enum = Provider::tryFrom($provider);
        if ($enum === null) {
            throw new InvalidConfigException("Unknown webhook provider [{$provider}].");
        }

        return $enum;
    }

    private function resolveWebhookService(Provider $provider): WebhookInterface
    {
        $instance = $this->manager->provider($provider);

        if (! method_exists($instance, 'webhooks')) {
            throw new ProviderException(
                message: "Provider [{$provider->value}] does not support webhooks.",
                provider: $provider->value,
            );
        }

        $service = $instance->webhooks();
        if (! $service instanceof WebhookInterface) {
            throw new ProviderException(
                message: "Provider [{$provider->value}] webhooks() must return WebhookInterface.",
                provider: $provider->value,
            );
        }

        return $service;
    }
}
