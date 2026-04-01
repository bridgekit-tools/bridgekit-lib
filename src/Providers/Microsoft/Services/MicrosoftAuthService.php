<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Microsoft\Services;

use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Enums\OAuthGrantType;
use BridgeKit\Exceptions\AuthenticationException;
use BridgeKit\Providers\Microsoft\MicrosoftProvider;
use BridgeKit\Support\AbstractAuthService;
use Illuminate\Support\Facades\Http;

class MicrosoftAuthService extends AbstractAuthService
{
    public function __construct(
        array $config,
        MicrosoftProvider $provider,
    ) {
        parent::__construct($config, $provider);
    }

    /**
     * @param  array<int, string>  $scopes
     */
    public function getAuthorizationUrl(array $scopes = [], string $state = ''): string
    {
        $tenant = $this->microsoftProvider()->getTenant();
        $base = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/authorize', rawurlencode($tenant));

        $query = array_filter([
            'client_id' => $this->configValue('client_id'),
            'redirect_uri' => $this->configValue('redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state !== '' ? $state : null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return $base.'?'.http_build_query($query);
    }

    public function handleCallback(string $code): OAuthToken
    {
        $tenant = $this->microsoftProvider()->getTenant();
        $tokenUrl = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', rawurlencode($tenant));

        $response = Http::asForm()->post($tokenUrl, [
            'client_id' => $this->configValue('client_id') ?? '',
            'client_secret' => $this->configValue('client_secret') ?? '',
            'code' => $code,
            'redirect_uri' => $this->configValue('redirect_uri') ?? '',
            'grant_type' => OAuthGrantType::AuthorizationCode->value,
        ]);

        if ($response->failed()) {
            throw new AuthenticationException(
                'Microsoft OAuth token exchange failed: '.$response->body(),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $this->normalizeToken($data);
    }

    public function refreshToken(string $refreshToken): OAuthToken
    {
        $tenant = $this->microsoftProvider()->getTenant();
        $tokenUrl = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', rawurlencode($tenant));

        $response = Http::asForm()->post($tokenUrl, [
            'client_id' => $this->configValue('client_id') ?? '',
            'client_secret' => $this->configValue('client_secret') ?? '',
            'refresh_token' => $refreshToken,
            'grant_type' => OAuthGrantType::RefreshToken->value,
        ]);

        if ($response->failed()) {
            throw new AuthenticationException(
                'Microsoft OAuth token refresh failed: '.$response->body(),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $this->normalizeToken($data, $refreshToken);
    }

    public function revokeToken(string $token): bool
    {
        return true;
    }

    private function microsoftProvider(): MicrosoftProvider
    {
        $p = $this->provider;
        if (! $p instanceof MicrosoftProvider) {
            throw new \LogicException('MicrosoftAuthService requires MicrosoftProvider.');
        }

        return $p;
    }
}
