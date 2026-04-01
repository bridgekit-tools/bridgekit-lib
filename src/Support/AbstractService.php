<?php

declare(strict_types=1);

namespace BridgeKit\Support;

use BridgeKit\Concerns\HasHttpClient;
use BridgeKit\Exceptions\AuthenticationException;

abstract class AbstractService
{
    use HasHttpClient;

    public function __construct(
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
}
