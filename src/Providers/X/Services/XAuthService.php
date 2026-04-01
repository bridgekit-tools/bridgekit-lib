<?php

declare(strict_types=1);

namespace BridgeKit\Providers\X\Services;

use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Enums\OAuthGrantType;
use BridgeKit\Exceptions\AuthenticationException;
use BridgeKit\Providers\X\XProvider;
use BridgeKit\Support\AbstractAuthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;

class XAuthService extends AbstractAuthService
{
    private const string AUTH_URL = 'https://twitter.com/i/oauth2/authorize';

    private const string TOKEN_URL = 'https://api.x.com/2/oauth2/token';

    private const string REVOKE_URL = 'https://api.x.com/2/oauth2/revoke';

    public function __construct(
        array $config,
        XProvider $provider,
    ) {
        parent::__construct($config, $provider);
    }

    public function getAuthorizationUrl(array $scopes = [], string $state = ''): string
    {
        $verifier = $this->generateCodeVerifier();
        $challenge = $this->codeChallengeS256($verifier);
        $stateKey = $state !== '' ? $state : bin2hex(random_bytes(16));

        Cache::put($this->pkceCacheKey($stateKey), $verifier, 600);

        $query = array_filter([
            'response_type' => 'code',
            'client_id' => $this->configValue('client_id'),
            'redirect_uri' => $this->configValue('redirect_uri'),
            'scope' => implode(' ', $scopes),
            'state' => $stateKey,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return self::AUTH_URL.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function handleCallback(string $code): OAuthToken
    {
        $stateKey = Request::query('state', '');
        if ($stateKey === '') {
            throw new AuthenticationException('Missing OAuth state for X PKCE.');
        }

        $verifier = Cache::pull($this->pkceCacheKey($stateKey));
        if (! is_string($verifier) || $verifier === '') {
            throw new AuthenticationException('Missing or expired PKCE code_verifier for X. Retry authorization.');
        }

        $payload = array_filter([
            'grant_type' => OAuthGrantType::AuthorizationCode->value,
            'code' => $code,
            'redirect_uri' => $this->configValue('redirect_uri'),
            'client_id' => $this->configValue('client_id'),
            'code_verifier' => $verifier,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        $secret = $this->configValue('client_secret');
        if (is_string($secret) && $secret !== '') {
            $payload['client_secret'] = $secret;
        }

        $response = $this->http()->asForm()->post(self::TOKEN_URL, $payload);

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $this->normalizeToken($data);
    }

    public function refreshToken(string $refreshToken): OAuthToken
    {
        $payload = [
            'grant_type' => OAuthGrantType::RefreshToken->value,
            'refresh_token' => $refreshToken,
            'client_id' => $this->configValue('client_id'),
        ];

        $secret = $this->configValue('client_secret');
        if (is_string($secret) && $secret !== '') {
            $payload['client_secret'] = $secret;
        }

        $response = $this->http()->asForm()->post(self::TOKEN_URL, $payload);

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $this->normalizeToken($data, $refreshToken);
    }

    public function revokeToken(string $token): bool
    {
        $payload = array_filter([
            'token' => $token,
            'client_id' => $this->configValue('client_id'),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        $secret = $this->configValue('client_secret');
        if (is_string($secret) && $secret !== '') {
            $payload['client_secret'] = $secret;
        }

        $response = $this->http()->asForm()->post(self::REVOKE_URL, $payload);

        return $response->successful();
    }

    private function pkceCacheKey(string $stateKey): string
    {
        return 'bridgekit.x.pkce.'.$stateKey;
    }

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function codeChallengeS256(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
