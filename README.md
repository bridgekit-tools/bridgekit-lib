<p align="center">
  <img src="https://raw.githubusercontent.com/bridgekit-tools/bridgekit-lib/main/.github/logo.svg" width="200" alt="BridgeKit">
</p>

<p align="center">
  <strong>Universal Laravel Integration Library</strong><br>
  One library to connect Google, Microsoft, Meta, LinkedIn & X.
</p>

<p align="center">
  <a href="https://packagist.org/packages/bridgekit-tools/bridgekit-lib"><img src="https://img.shields.io/packagist/v/bridgekit-tools/bridgekit-lib.svg?style=flat-square" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/bridgekit-tools/bridgekit-lib"><img src="https://img.shields.io/packagist/dt/bridgekit-tools/bridgekit-lib.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square" alt="License"></a>
  <a href="https://github.com/bridgekit-tools/bridgekit-lib/actions"><img src="https://img.shields.io/github/actions/workflow/status/bridgekit-tools/bridgekit-lib/tests.yml?style=flat-square&label=tests" alt="Tests"></a>
</p>

---

## What is BridgeKit?

BridgeKit is a Laravel library that provides a **unified, typed API** for integrating with third-party providers. Instead of learning 5 different SDKs, you learn one interface.

```php
use BridgeKit\Facades\BridgeKit;

// Google Drive
$files = BridgeKit::google()->setToken($token)->drive()->listFiles();

// Microsoft OneDrive — same interface
$files = BridgeKit::microsoft()->setToken($token)->onedrive()->listFiles();

// Post to LinkedIn with media
$result = BridgeKit::linkedin()->setToken($token)->posts()->publish(
    new SocialPost(
        content: 'Hello from BridgeKit!',
        media: [MediaContent::fromUrl('https://example.com/photo.jpg')],
    )
);
```

## Features

- **5 providers** — Google, Microsoft, Meta, LinkedIn, X
- **15 services** — Drive, OneDrive, Gmail, Outlook, Calendar (×2), Posts (×3), OAuth (×5)
- **6 contracts** — Swap providers without changing your code
- **Typed DTOs** — `final readonly` classes with `JsonSerializable`
- **7 enums** — `Provider`, `MediaType`, `Visibility`, `EventStatus`, `MailFolder`, `OAuthGrantType`, `ServiceType`
- **Streaming** — Zero-memory downloads via `downloadStream()`
- **Chunked uploads** — Resumable uploads for files of any size
- **Lazy generators** — Memory-efficient paginated listing
- **Media support** — Upload images & videos from URL, path, or binary
- **PKCE** — Built-in for X/Twitter OAuth 2.0
- **Extensible** — Register custom providers via `extend()`

## Requirements

- PHP 8.3+
- Laravel 13+

## Installation

```bash
composer require bridgekit-tools/bridgekit-lib
```

The service provider and facade are auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=bridgekit-config
```

## Configuration

Add credentials to your `.env`:

```ini
# Google
BRIDGEKIT_GOOGLE_CLIENT_ID=
BRIDGEKIT_GOOGLE_CLIENT_SECRET=
BRIDGEKIT_GOOGLE_REDIRECT_URI=

# Microsoft
BRIDGEKIT_MICROSOFT_CLIENT_ID=
BRIDGEKIT_MICROSOFT_CLIENT_SECRET=
BRIDGEKIT_MICROSOFT_REDIRECT_URI=
BRIDGEKIT_MICROSOFT_TENANT=common

# Meta
BRIDGEKIT_META_CLIENT_ID=
BRIDGEKIT_META_CLIENT_SECRET=
BRIDGEKIT_META_REDIRECT_URI=

# LinkedIn
BRIDGEKIT_LINKEDIN_CLIENT_ID=
BRIDGEKIT_LINKEDIN_CLIENT_SECRET=
BRIDGEKIT_LINKEDIN_REDIRECT_URI=

# X (Twitter)
BRIDGEKIT_X_CLIENT_ID=
BRIDGEKIT_X_CLIENT_SECRET=
BRIDGEKIT_X_REDIRECT_URI=
```

## Quick Start

### OAuth Flow

```php
// 1. Redirect to consent screen
$url = BridgeKit::google()->auth()->getAuthorizationUrl([
    'https://www.googleapis.com/auth/drive.readonly',
]);
return redirect($url);

// 2. Handle callback
$token = BridgeKit::google()->auth()->handleCallback($request->code);

// 3. Use services
$google = BridgeKit::google()->setToken($token);
$files = $google->drive()->listFiles();
$events = $google->calendar()->listEvents();
```

### File Storage

```php
$drive = BridgeKit::google()->setToken($token)->drive();

$files = $drive->listFiles();
$file = $drive->uploadFile('doc.txt', 'Hello world', 'text/plain');
$stream = $drive->downloadStream('file-id');

// Large file upload (chunked, resumable)
$file = $drive->uploadLargeFile('backup.zip', '/path/to/file.zip', 'application/zip');

// Memory-efficient listing
foreach ($drive->listFilesLazy() as $file) {
    echo $file->name;
}
```

### Social Publishing

```php
use BridgeKit\DTOs\{SocialPost, MediaContent};
use BridgeKit\Enums\Visibility;

$result = BridgeKit::x()->setToken($token)->posts()->publish(
    new SocialPost(
        content: 'Posted via BridgeKit!',
        media: [
            MediaContent::fromUrl('https://example.com/photo.jpg', altText: 'A photo'),
            MediaContent::fromPath('/local/video.mp4'),
        ],
        visibility: Visibility::Public,
    )
);

echo $result->url;
```

### Email

```php
use BridgeKit\DTOs\EmailMessage;

BridgeKit::google()->setToken($token)->gmail()->send(new EmailMessage(
    subject: 'Welcome',
    body: '<h1>Hello!</h1>',
    to: ['user@example.com'],
    isHtml: true,
));
```

### Calendar

```php
use BridgeKit\DTOs\CalendarEvent;

$event = BridgeKit::google()->setToken($token)->calendar()->createEvent(
    new CalendarEvent(
        title: 'Team Standup',
        startAt: new DateTimeImmutable('2026-04-01T09:00:00'),
        endAt: new DateTimeImmutable('2026-04-01T09:30:00'),
        timezone: 'Europe/Paris',
        attendees: ['alice@company.com'],
    )
);
```

### Custom Providers

```php
use BridgeKit\Support\AbstractProvider;

class DropboxProvider extends AbstractProvider
{
    public function getName(): string { return 'dropbox'; }
    // ... implement services
}

// Register
BridgeKit::extend('dropbox', DropboxProvider::class);
```

## Available Services

| Provider | Storage | Email | Calendar | Social | OAuth |
|----------|---------|-------|----------|--------|-------|
| Google | `drive()` | `gmail()` | `calendar()` | — | `auth()` |
| Microsoft | `onedrive()` | `outlook()` | `calendar()` | — | `auth()` |
| Meta | — | — | — | `posts()` | `auth()` |
| LinkedIn | — | — | — | `posts()` | `auth()` |
| X | — | — | — | `posts()` | `auth()` |

## Architecture

```
src/
├── Contracts/          # Interfaces (FileStorage, Email, Calendar, Social, OAuth)
├── DTOs/               # Typed value objects (OAuthToken, StorageFile, MediaContent, ...)
├── Enums/              # Provider, MediaType, Visibility, EventStatus, ...
├── Exceptions/         # BridgeKitException, AuthenticationException, ...
├── Providers/          # Google, Microsoft, Meta, LinkedIn, X
│   └── */Services/     # Concrete implementations
├── Support/            # AbstractProvider, AbstractService, ConnectManager
├── Concerns/           # HasHttpClient trait
├── Facades/            # BridgeKit facade
└── BridgeKitServiceProvider.php
```

## Testing

```bash
composer test
```

101 tests, 276 assertions.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
