<?php

declare(strict_types=1);

namespace BridgeKit\Contracts\Auth;

use BridgeKit\DTOs\OAuthToken;

interface OAuthInterface
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function getAuthorizationUrl(array $scopes = [], string $state = ''): string;

    public function handleCallback(string $code): OAuthToken;

    public function refreshToken(string $refreshToken): OAuthToken;

    public function revokeToken(string $token): bool;
}
