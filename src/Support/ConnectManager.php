<?php

declare(strict_types=1);

namespace BridgeKit\Support;

use BridgeKit\Contracts\ProviderInterface;
use BridgeKit\Enums\Provider;
use BridgeKit\Exceptions\InvalidConfigException;
use BridgeKit\Providers\Ftp\FtpProvider;
use BridgeKit\Providers\Google\GoogleProvider;
use BridgeKit\Providers\LinkedIn\LinkedInProvider;
use BridgeKit\Providers\Meta\MetaProvider;
use BridgeKit\Providers\Microsoft\MicrosoftProvider;
use BridgeKit\Providers\S3\S3Provider;
use BridgeKit\Providers\Sftp\SftpProvider;
use BridgeKit\Providers\X\XProvider;

class ConnectManager
{
    /** @var array<string, class-string<ProviderInterface>> */
    protected array $providerMap = [
        Provider::Google->value => GoogleProvider::class,
        Provider::Microsoft->value => MicrosoftProvider::class,
        Provider::Meta->value => MetaProvider::class,
        Provider::LinkedIn->value => LinkedInProvider::class,
        Provider::X->value => XProvider::class,
        Provider::Ftp->value => FtpProvider::class,
        Provider::S3->value => S3Provider::class,
        Provider::Sftp->value => SftpProvider::class,
    ];

    /** @var array<string, ProviderInterface> */
    protected array $resolved = [];

    public function __construct(
        protected readonly array $config = [],
    ) {}

    public function provider(Provider|string $name): ProviderInterface
    {
        $key = $name instanceof Provider ? $name->value : $name;

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        if (! isset($this->providerMap[$key])) {
            throw new InvalidConfigException("Unknown provider [{$key}]. Registered providers: " . implode(', ', array_keys($this->providerMap)));
        }

        $providerConfig = $this->config['providers'][$key] ?? [];
        $providerClass = $this->providerMap[$key];

        $this->resolved[$key] = new $providerClass($providerConfig);

        return $this->resolved[$key];
    }

    public function google(?array $config = null): GoogleProvider
    {
        if ($config !== null) {
            return new GoogleProvider($config);
        }

        /** @var GoogleProvider */
        return $this->provider(Provider::Google);
    }

    public function microsoft(?array $config = null): MicrosoftProvider
    {
        if ($config !== null) {
            return new MicrosoftProvider($config);
        }

        /** @var MicrosoftProvider */
        return $this->provider(Provider::Microsoft);
    }

    public function meta(?array $config = null): MetaProvider
    {
        if ($config !== null) {
            return new MetaProvider($config);
        }

        /** @var MetaProvider */
        return $this->provider(Provider::Meta);
    }

    public function linkedin(?array $config = null): LinkedInProvider
    {
        if ($config !== null) {
            return new LinkedInProvider($config);
        }

        /** @var LinkedInProvider */
        return $this->provider(Provider::LinkedIn);
    }

    public function x(?array $config = null): XProvider
    {
        if ($config !== null) {
            return new XProvider($config);
        }

        /** @var XProvider */
        return $this->provider(Provider::X);
    }

    public function ftp(?array $config = null): FtpProvider
    {
        if ($config !== null) {
            return new FtpProvider($config);
        }

        /** @var FtpProvider */
        return $this->provider(Provider::Ftp);
    }

    public function s3(?array $config = null): S3Provider
    {
        if ($config !== null) {
            return new S3Provider($config);
        }

        /** @var S3Provider */
        return $this->provider(Provider::S3);
    }

    public function sftp(?array $config = null): SftpProvider
    {
        if ($config !== null) {
            return new SftpProvider($config);
        }

        /** @var SftpProvider */
        return $this->provider(Provider::Sftp);
    }

    /**
     * @param  class-string<ProviderInterface>  $providerClass
     */
    public function extend(string $name, string $providerClass): static
    {
        $this->providerMap[$name] = $providerClass;

        return $this;
    }

    /**
     * @return array<string, class-string<ProviderInterface>>
     */
    public function getRegisteredProviders(): array
    {
        return $this->providerMap;
    }

    public function flush(): void
    {
        $this->resolved = [];
    }
}
