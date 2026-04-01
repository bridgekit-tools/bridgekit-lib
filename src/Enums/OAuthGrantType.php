<?php

declare(strict_types=1);

namespace BridgeKit\Enums;

enum OAuthGrantType: string
{
    case AuthorizationCode = 'authorization_code';
    case RefreshToken = 'refresh_token';
    case ClientCredentials = 'client_credentials';
    case FbExchangeToken = 'fb_exchange_token';
}
