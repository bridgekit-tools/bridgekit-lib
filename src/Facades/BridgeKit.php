<?php

declare(strict_types=1);

namespace BridgeKit\Facades;

use BridgeKit\Contracts\ProviderInterface;
use BridgeKit\Providers\Google\GoogleProvider;
use BridgeKit\Providers\LinkedIn\LinkedInProvider;
use BridgeKit\Providers\Meta\MetaProvider;
use BridgeKit\Providers\Microsoft\MicrosoftProvider;
use BridgeKit\Providers\X\XProvider;
use BridgeKit\Support\ConnectManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ProviderInterface provider(string $name)
 * @method static GoogleProvider google(?array $config = null)
 * @method static MicrosoftProvider microsoft(?array $config = null)
 * @method static MetaProvider meta(?array $config = null)
 * @method static LinkedInProvider linkedin(?array $config = null)
 * @method static XProvider x(?array $config = null)
 * @method static ConnectManager extend(string $name, string $providerClass)
 * @method static array getRegisteredProviders()
 * @method static void flush()
 *
 * @see ConnectManager
 */
class BridgeKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConnectManager::class;
    }
}
