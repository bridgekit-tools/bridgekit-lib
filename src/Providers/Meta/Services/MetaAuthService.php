<?php

declare(strict_types=1);

namespace BridgeKit\Providers\Meta\Services;

use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Enums\OAuthGrantType;
use BridgeKit\Providers\Meta\MetaProvider;
use BridgeKit\Support\AbstractAuthService;

class MetaAuthService extends AbstractAuthService
{
    public function __construct(
        array $config,
        MetaProvider $provider,
    ) {
        parent::__construct($config, $provider);
    }

    public function getAuthorizationUrl(array $scopes = [], string $state = ''): string
    {
        $version = $this->graphVersion();
        $query = array_filter([
            'client_id' => $this->configValue('client_id'),
            'redirect_uri' => $this->configValue('redirect_uri'),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return "https://www.facebook.com/{$version}/dialog/oauth?".http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function handleCallback(string $code): OAuthToken
    {
        $version = $this->graphVersion();
        $response = $this->http()->get("https://graph.facebook.com/{$version}/oauth/access_token", [
            'client_id' => $this->configValue('client_id'),
            'client_secret' => $this->configValue('client_secret'),
            'redirect_uri' => $this->configValue('redirect_uri'),
            'code' => $code,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $this->normalizeToken($data);
    }

    public function refreshToken(string $refreshToken): OAuthToken
    {
        $version = $this->graphVersion();
        $response = $this->http()->get("https://graph.facebook.com/{$version}/oauth/access_token", [
            'grant_type' => OAuthGrantType::FbExchangeToken->value,
            'client_id' => $this->configValue('client_id'),
            'client_secret' => $this->configValue('client_secret'),
            'fb_exchange_token' => $refreshToken,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $this->normalizeToken($data, $refreshToken);
    }

    public function revokeToken(string $token): bool
    {
        $version = $this->graphVersion();
        $response = $this->http()
            ->withToken($token)
            ->delete("https://graph.facebook.com/{$version}/me/permissions");

        return $response->successful();
    }

    private function graphVersion(): string
    {
        return (string) ($this->config['graph_version'] ?? 'v21.0');
    }
}
