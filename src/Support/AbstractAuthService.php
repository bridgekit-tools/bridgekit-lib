<?php

declare(strict_types=1);

namespace BridgeKit\Support;

use BridgeKit\Concerns\HasHttpClient;
use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Exceptions\AuthenticationException;

abstract class AbstractAuthService implements OAuthInterface
{
    use HasHttpClient;

    public function __construct(
        protected readonly array $config,
        protected readonly AbstractProvider $provider,
    ) {}

    protected function getProviderName(): string
    {
        return $this->provider->getName();
    }

    protected function getAccessToken(): string
    {
        $token = $this->provider->getToken();
        if ($token === null) {
            throw new AuthenticationException(
                "No token set for provider [{$this->provider->getName()}]. Call setToken() first."
            );
        }

        return $token->accessToken;
    }

    protected function configValue(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }

    /**
     * Normalize an API token response into an OAuthToken.
     * Handles the scope/scopes inconsistency across providers.
     */
    protected function normalizeToken(array $data, string $preserveRefreshToken = ''): OAuthToken
    {
        if (! isset($data['access_token'])) {
            throw new AuthenticationException("Invalid token response from [{$this->getProviderName()}].");
        }

        $scopes = $this->normalizeScopes($data['scope'] ?? $data['scopes'] ?? []);
        $data['scopes'] = $scopes;
        unset($data['scope']);

        if ($preserveRefreshToken !== '' && empty($data['refresh_token'])) {
            $data['refresh_token'] = $preserveRefreshToken;
        }

        return OAuthToken::fromArray($data);
    }

    /**
     * @param  string|array<int, string>  $scopes
     * @return array<int, string>
     */
    protected function normalizeScopes(mixed $scopes): array
    {
        if (is_string($scopes)) {
            return preg_split('/[\s,]+/', trim($scopes), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return is_array($scopes) ? array_values($scopes) : [];
    }
}
