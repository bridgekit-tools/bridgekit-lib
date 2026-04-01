<?php

declare(strict_types=1);

namespace BridgeKit\Providers\LinkedIn\Services;

use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Enums\OAuthGrantType;
use BridgeKit\Providers\LinkedIn\LinkedInProvider;
use BridgeKit\Support\AbstractAuthService;

class LinkedInAuthService extends AbstractAuthService
{
    private const string AUTH_URL = 'https://www.linkedin.com/oauth/v2/authorization';

    private const string TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    private const string REVOKE_URL = 'https://www.linkedin.com/oauth/v2/revoke';

    public function __construct(
        array $config,
        LinkedInProvider $provider,
    ) {
        parent::__construct($config, $provider);
    }

    public function getAuthorizationUrl(array $scopes = [], string $state = ''): string
    {
        $query = array_filter([
            'response_type' => 'code',
            'client_id' => $this->configValue('client_id'),
            'redirect_uri' => $this->configValue('redirect_uri'),
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return self::AUTH_URL.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function handleCallback(string $code): OAuthToken
    {
        $response = $this->http()->asForm()->post(self::TOKEN_URL, [
            'grant_type' => OAuthGrantType::AuthorizationCode->value,
            'code' => $code,
            'redirect_uri' => $this->configValue('redirect_uri'),
            'client_id' => $this->configValue('client_id'),
            'client_secret' => $this->configValue('client_secret'),
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $this->normalizeToken($data);
    }

    public function refreshToken(string $refreshToken): OAuthToken
    {
        $response = $this->http()->asForm()->post(self::TOKEN_URL, [
            'grant_type' => OAuthGrantType::RefreshToken->value,
            'refresh_token' => $refreshToken,
            'client_id' => $this->configValue('client_id'),
            'client_secret' => $this->configValue('client_secret'),
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $this->normalizeToken($data, $refreshToken);
    }

    public function revokeToken(string $token): bool
    {
        $response = $this->http()->asForm()->post(self::REVOKE_URL, [
            'token' => $token,
            'client_id' => $this->configValue('client_id'),
            'client_secret' => $this->configValue('client_secret'),
        ]);

        return $response->successful();
    }
}
