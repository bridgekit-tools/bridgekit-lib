<?php

declare(strict_types=1);

namespace BridgeKit\Contracts;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\DTOs\OAuthToken;

interface ProviderInterface
{
    public function getName(): string;

    public function auth(): OAuthInterface;

    public function setToken(OAuthToken $token): static;

    public function getToken(): ?OAuthToken;

    /**
     * @return array<string, class-string>
     */
    public function getAvailableServices(): array;
}
