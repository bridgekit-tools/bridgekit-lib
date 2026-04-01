<?php

declare(strict_types=1);

namespace BridgeKit\Support;

use BridgeKit\Concerns\HasHttpClient;
use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\Contracts\ProviderInterface;
use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Exceptions\AuthenticationException;

abstract class AbstractProvider implements ProviderInterface
{
    use HasHttpClient;

    protected ?OAuthToken $token = null;

    /** @var array<string, object> */
    protected array $resolvedServices = [];

    public function __construct(
        protected readonly array $config = [],
    ) {}

    public function setToken(OAuthToken $token): static
    {
        $this->token = $token;
        $this->resolvedServices = [];

        return $this;
    }

    public function getToken(): ?OAuthToken
    {
        return $this->token;
    }

    protected function getProviderName(): string
    {
        return $this->getName();
    }

    protected function getAccessToken(): string
    {
        if ($this->token === null) {
            throw new AuthenticationException("No token set for provider [{$this->getName()}]. Call setToken() first.");
        }

        return $this->token->accessToken;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    protected function resolveService(string $key, callable $factory): object
    {
        if (! isset($this->resolvedServices[$key])) {
            $this->resolvedServices[$key] = $factory();
        }

        return $this->resolvedServices[$key];
    }
}
