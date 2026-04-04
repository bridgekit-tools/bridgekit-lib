<?php

declare(strict_types=1);

namespace BridgeKit\Concerns;

use BridgeKit\Exceptions\ProviderException;
use BridgeKit\Exceptions\RateLimitException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

trait HasHttpClient
{
    protected ?PendingRequest $httpClient = null;

    protected int $maxRetries = 3;

    protected int $retryBaseDelayMs = 500;

    protected function http(): PendingRequest
    {
        if ($this->httpClient === null) {
            $this->httpClient = Http::acceptJson()
                ->contentType('application/json')
                ->retry(
                    times: $this->maxRetries,
                    sleepMilliseconds: $this->retryBaseDelayMs,
                    when: fn (?\Exception $e, PendingRequest $request): bool => $this->shouldRetry($e),
                    throw: false,
                )
                ->throw(function (Response $response) {
                    if ($response->status() === 429) {
                        $retryAfter = (int) ($response->header('Retry-After') ?: 60);

                        throw new RateLimitException(
                            provider: $this->getProviderName(),
                            retryAfter: $retryAfter,
                        );
                    }

                    throw new ProviderException(
                        message: "HTTP {$response->status()}: {$response->body()}",
                        provider: $this->getProviderName(),
                        code: $response->status(),
                    );
                });
        }

        return $this->httpClient;
    }

    protected function authenticatedHttp(): PendingRequest
    {
        $this->autoRefreshTokenIfNeeded();

        $token = $this->getAccessToken();

        return $this->http()->withToken($token);
    }

    /**
     * Automatically refresh the OAuth token if it's expired or about to expire.
     * Provides a 60-second buffer before actual expiration.
     */
    protected function autoRefreshTokenIfNeeded(): void
    {
        if (! method_exists($this, 'provider')) {
            if (property_exists($this, 'provider') && method_exists($this->provider, 'getToken')) {
                $this->refreshOnProvider($this->provider);
            }

            return;
        }
    }

    private function refreshOnProvider(object $provider): void
    {
        $token = $provider->getToken();
        if ($token === null || $token->refreshToken === '') {
            return;
        }

        if ($token->expiresAt === null) {
            return;
        }

        $buffer = new \DateTimeImmutable('+60 seconds');
        if ($token->expiresAt > $buffer) {
            return;
        }

        if (! method_exists($provider, 'auth')) {
            return;
        }

        $newToken = $provider->auth()->refreshToken($token->refreshToken);
        $provider->setToken($newToken);

        $this->httpClient = null;
    }

    protected function shouldRetry(?\Exception $e): bool
    {
        if ($e === null) {
            return false;
        }

        if ($e instanceof RateLimitException) {
            return true;
        }

        if ($e instanceof ProviderException) {
            $code = $e->getCode();

            return $code === 429 || $code === 500 || $code === 502 || $code === 503 || $code === 504;
        }

        return false;
    }

    /**
     * Configure retry behavior.
     */
    protected function withRetry(int $maxRetries = 3, int $baseDelayMs = 500): static
    {
        $this->maxRetries = $maxRetries;
        $this->retryBaseDelayMs = $baseDelayMs;
        $this->httpClient = null;

        return $this;
    }

    abstract protected function getProviderName(): string;

    abstract protected function getAccessToken(): string;
}
