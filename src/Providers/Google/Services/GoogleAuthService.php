<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Google\Services;

use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Enums\OAuthGrantType;
use BridgeKit\Providers\Google\GoogleProvider;
use BridgeKit\Support\AbstractAuthService;

class GoogleAuthService extends AbstractAuthService
{

    private const string AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const string REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public function __construct(
        array $config,
        GoogleProvider $provider,
    ) {
        parent::__construct($config, $provider);
    }

    public function getAuthorizationUrl(array $scopes = [], string $state = ''): string
    {
        $query = array_filter([
            'client_id' => $this->configValue('client_id'),
            'redirect_uri' => $this->configValue('redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return self::AUTH_URL.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function handleCallback(string $code): OAuthToken
    {
        $response = $this->http()->asForm()->post(self::TOKEN_URL, [
            'grant_type' => OAuthGrantType::AuthorizationCode->value,
            'code' => $code,
            'client_id' => $this->configValue('client_id'),
            'client_secret' => $this->configValue('client_secret'),
            'redirect_uri' => $this->configValue('redirect_uri'),
        ]);

        return $this->normalizeToken($response->json() ?? []);
    }

    public function refreshToken(string $refreshToken): OAuthToken
    {
        $response = $this->http()->asForm()->post(self::TOKEN_URL, [
            'grant_type' => OAuthGrantType::RefreshToken->value,
            'refresh_token' => $refreshToken,
            'client_id' => $this->configValue('client_id'),
            'client_secret' => $this->configValue('client_secret'),
        ]);

        return $this->normalizeToken($response->json() ?? [], $refreshToken);
    }

    public function revokeToken(string $token): bool
    {
        $response = $this->http()->asForm()->post(self::REVOKE_URL, [
            'token' => $token,
        ]);

        return $response->successful();
    }
}
