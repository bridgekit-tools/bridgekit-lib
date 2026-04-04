<?php

declare(strict_types=1);

namespace BridgeKit\Support;

use BridgeKit\Contracts\Auth\OAuthInterface;
use BridgeKit\Contracts\ProviderInterface;
use BridgeKit\Contracts\Storage\FileStorageInterface;
use BridgeKit\DTOs\OAuthToken;
use BridgeKit\Exceptions\ProviderException;

/**
 * Base class for credential-based storage providers (FTP, S3, SFTP).
 * These providers do not use OAuth — auth() throws, setToken/getToken are no-ops.
 */
abstract class AbstractStorageProvider implements ProviderInterface
{
    /** @var array<string, object> */
    protected array $resolvedServices = [];

    public function __construct(
        protected readonly array $config = [],
    ) {}

    abstract public function storage(): FileStorageInterface;

    public function auth(): OAuthInterface
    {
        throw new ProviderException(
            message: "Provider [{$this->getName()}] uses credentials, not OAuth.",
            provider: $this->getName(),
        );
    }

    public function setToken(OAuthToken $token): static
    {
        return $this;
    }

    public function getToken(): ?OAuthToken
    {
        return null;
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
