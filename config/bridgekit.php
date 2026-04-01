<?php

declare(strict_types=1);

return [

    'providers' => [

        'google' => [
            'client_id' => env('BRIDGEKIT_GOOGLE_CLIENT_ID'),
            'client_secret' => env('BRIDGEKIT_GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('BRIDGEKIT_GOOGLE_REDIRECT_URI'),
            'scopes' => [],
        ],

        'microsoft' => [
            'client_id' => env('BRIDGEKIT_MICROSOFT_CLIENT_ID'),
            'client_secret' => env('BRIDGEKIT_MICROSOFT_CLIENT_SECRET'),
            'redirect_uri' => env('BRIDGEKIT_MICROSOFT_REDIRECT_URI'),
            'tenant' => env('BRIDGEKIT_MICROSOFT_TENANT', 'common'),
            'scopes' => [],
        ],

        'meta' => [
            'client_id' => env('BRIDGEKIT_META_CLIENT_ID'),
            'client_secret' => env('BRIDGEKIT_META_CLIENT_SECRET'),
            'redirect_uri' => env('BRIDGEKIT_META_REDIRECT_URI'),
            'graph_version' => env('BRIDGEKIT_META_GRAPH_VERSION', 'v21.0'),
            'scopes' => [],
        ],

        'linkedin' => [
            'client_id' => env('BRIDGEKIT_LINKEDIN_CLIENT_ID'),
            'client_secret' => env('BRIDGEKIT_LINKEDIN_CLIENT_SECRET'),
            'redirect_uri' => env('BRIDGEKIT_LINKEDIN_REDIRECT_URI'),
            'scopes' => [],
        ],

        'x' => [
            'client_id' => env('BRIDGEKIT_X_CLIENT_ID'),
            'client_secret' => env('BRIDGEKIT_X_CLIENT_SECRET'),
            'redirect_uri' => env('BRIDGEKIT_X_REDIRECT_URI'),
            'scopes' => [],
        ],

    ],

];
