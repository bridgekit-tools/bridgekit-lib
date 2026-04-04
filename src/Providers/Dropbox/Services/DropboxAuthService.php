<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Dropbox\Services;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Providers\Dropbox\DropboxProvider;
use BridgeKit\Support\AbstractAuthService;
use Illuminate\Support\Facades\Http;

class DropboxAuthService extends AbstractAuthService implements OAuthInterface
{
    private const string AUTH_URL = 'https://www.dropbox.com/oauth2/authorize';

    private const string TOKEN_URL = 'https://api.dropboxapi.com/oauth2/token';

    public function __construct(array $config, DropboxProvider $provider)
    {
        parent::__construct($config, $provider);
    }

    public function getAuthorizationUrl(array $scopes = [], string $state = ''): string
    {
        $params = [
            'client_id' => $this->config['client_id'] ?? '',
            'redirect_uri' => $this->config['redirect_uri'] ?? '',
            'response_type' => 'code',
            'token_access_type' => 'offline',
        ];

        if ($state !== '') {
            $params['state'] = $state;
        }

        if ($scopes !== []) {
            $params['scope'] = implode(' ', $scopes);
        }

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public function handleCallback(string $code): OAuthToken
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => $this->config['client_id'] ?? '',
            'client_secret' => $this->config['client_secret'] ?? '',
            'redirect_uri' => $this->config['redirect_uri'] ?? '',
        ]);

        return OAuthToken::fromArray($response->json());
    }

    public function refreshToken(string $refreshToken): OAuthToken
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'client_id' => $this->config['client_id'] ?? '',
            'client_secret' => $this->config['client_secret'] ?? '',
        ]);

        $data = $response->json();
        $data['refresh_token'] = $data['refresh_token'] ?? $refreshToken;

        return OAuthToken::fromArray($data);
    }

    public function revokeToken(string $token): bool
    {
        $response = Http::withToken($token)
            ->post('https://api.dropboxapi.com/2/auth/token/revoke');

        return $response->successful();
    }
}
