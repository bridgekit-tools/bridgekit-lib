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

        'dropbox' => [
            'client_id' => env('BRIDGEKIT_DROPBOX_CLIENT_ID'),
            'client_secret' => env('BRIDGEKIT_DROPBOX_CLIENT_SECRET'),
            'redirect_uri' => env('BRIDGEKIT_DROPBOX_REDIRECT_URI'),
        ],

        'ftp' => [
            'host' => env('BRIDGEKIT_FTP_HOST', 'localhost'),
            'port' => env('BRIDGEKIT_FTP_PORT', 21),
            'username' => env('BRIDGEKIT_FTP_USERNAME'),
            'password' => env('BRIDGEKIT_FTP_PASSWORD'),
            'ssl' => env('BRIDGEKIT_FTP_SSL', false),
            'passive' => env('BRIDGEKIT_FTP_PASSIVE', true),
            'root' => env('BRIDGEKIT_FTP_ROOT', '/'),
            'timeout' => env('BRIDGEKIT_FTP_TIMEOUT', 30),
        ],

        's3' => [
            'key' => env('BRIDGEKIT_S3_KEY'),
            'secret' => env('BRIDGEKIT_S3_SECRET'),
            'region' => env('BRIDGEKIT_S3_REGION', 'us-east-1'),
            'bucket' => env('BRIDGEKIT_S3_BUCKET'),
            'endpoint' => env('BRIDGEKIT_S3_ENDPOINT'),
        ],

        'sftp' => [
            'host' => env('BRIDGEKIT_SFTP_HOST', 'localhost'),
            'port' => env('BRIDGEKIT_SFTP_PORT', 22),
            'username' => env('BRIDGEKIT_SFTP_USERNAME'),
            'password' => env('BRIDGEKIT_SFTP_PASSWORD'),
            'private_key' => env('BRIDGEKIT_SFTP_PRIVATE_KEY'),
            'public_key' => env('BRIDGEKIT_SFTP_PUBLIC_KEY'),
            'passphrase' => env('BRIDGEKIT_SFTP_PASSPHRASE'),
            'root' => env('BRIDGEKIT_SFTP_ROOT', '/'),
        ],

    ],

    'webhooks' => [
        'enabled' => env('BRIDGEKIT_WEBHOOKS_ENABLED', true),
        'path' => env('BRIDGEKIT_WEBHOOKS_PATH', 'webhooks/bridgekit'),
        'middleware' => [],
    ],

];
