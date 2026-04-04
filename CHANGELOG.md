# Changelog

All notable changes to BridgeKit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-04-04

### Added

- **Webhooks** — `WebhookInterface`, `WebhookEvent` enum, `WebhookPayload` / `WebhookRegistration` DTOs, `WebhookProcessor`, and `WebhookController` HTTP endpoint. Provider services: Google (Drive/Calendar push), Microsoft Graph subscriptions, Meta (`X-Hub-Signature-256`), X (CRC + signature). Laravel events for storage, social, and calendar. Config: `bridgekit.webhooks` (`enabled`, `path`, `middleware`). Routes: `POST/GET /webhooks/bridgekit/{provider}`.
- **Token auto-refresh** — `HasHttpClient` detects expired tokens (with 60s buffer) and automatically calls `refreshToken()` before each API request. Zero config, works for all OAuth providers.
- **Retry + Rate limiting** — built-in retry with exponential backoff for 429/5xx errors. `RateLimitException` surfaces `retryAfter` seconds. Configurable via `withRetry(maxRetries, baseDelayMs)`.
- **Multi-posting** — `MultiPoster` broadcasts a `SocialPost` across multiple providers in one call. Auto-adapts content per platform (X: 280 chars, Meta: 63k, LinkedIn: 3k). Returns `MultiPostResult` with success/failure per provider.
- **Dropbox provider** — full OAuth + `FileStorageInterface`. Upload sessions for large files, search, folder creation. Configured via `BRIDGEKIT_DROPBOX_*` env vars.
- **`MultiPostResult` DTO** — `isFullSuccess()`, `isPartialSuccess()`, `isFullFailure()`, `getResult(provider)`, `getError(provider)`.
- **`RateLimitException`** — extends `ProviderException` with `retryAfter` property and HTTP 429 code.
- **`ConnectManager::multiPost()`** — factory shortcut for `MultiPoster`.
- **`ConnectManager::dropbox()`** — shortcut for Dropbox provider.
- **Google, Microsoft, Meta, X** — `webhooks()` service on each OAuth provider.
- **176 tests, 448 assertions** — coverage for webhooks, multi-post, Dropbox, HTTP client behavior, and providers.

### Changed

- `HasHttpClient` now handles retry, rate limiting, and token auto-refresh automatically.
- `BridgeKitServiceProvider` registers webhook routes and `WebhookProcessor` singleton.
- Updated provider count from 8 to 9 in `ServiceProviderTest`.

## [1.1.0] - 2026-03-29

### Added

- **3 new storage providers**: FTP/FTPS, S3 (AWS + compatible), SFTP — all implement `FileStorageInterface`
- **`AbstractStorageProvider`** — new base class for credential-based providers (no OAuth required)
- **`FtpProvider`** + `FtpStorageService` — FTP/FTPS with SSL, passive mode, `ftp_mlsd` listing
- **`S3Provider`** + `S3StorageService` — native AWS Signature V4, multipart uploads, compatible with MinIO, DigitalOcean Spaces, Cloudflare R2
- **`SftpProvider`** + `SftpStorageService` — password or SSH public key authentication, chunked streaming
- **Inline config support** — all storage providers can be instantiated with config arrays, no `.env` required
- **`Provider` enum**: 3 new cases (`Ftp`, `S3`, `Sftp`) + helper methods `isStorageOnly()`, `requiresOAuth()`
- **`ServiceType::Storage`** enum case
- **`ConnectManager`** shortcuts: `ftp()`, `s3()`, `sftp()`
- **`composer.json`**: `suggest` for `ext-ssh2` and `ext-ftp`
- **128 tests, 322 assertions** — full coverage for all new providers, enum helpers, and ConnectManager

### Changed

- Updated provider count from 5 to 8 in `ServiceProviderTest`
- Architecture diagram now shows two provider hierarchies (OAuth vs Credentials)

## [1.0.0] - 2026-03-29

### Added

- **5 providers**: Google, Microsoft, Meta, LinkedIn, X (Twitter)
- **15 services** across all providers:
  - **Google**: Drive (storage), Gmail (email), Calendar, OAuth
  - **Microsoft**: OneDrive (storage), Outlook (email), Calendar, OAuth
  - **Meta**: Posts (social), OAuth
  - **LinkedIn**: Posts (social), OAuth
  - **X**: Posts (social), OAuth
- **6 contracts**: `ProviderInterface`, `OAuthInterface`, `FileStorageInterface`, `EmailSenderInterface`, `PostPublisherInterface`, `CalendarInterface`
- **6 typed DTOs** (final readonly): `OAuthToken`, `StorageFile`, `EmailMessage`, `CalendarEvent`, `SocialPost`, `SocialPostResult`
- **6 backed enums**: `Provider`, `Visibility`, `EventStatus`, `MailFolder`, `OAuthGrantType`, `ServiceType`
- **Streaming downloads** via `downloadStream()` — zero memory overhead for large files
- **Resumable chunked uploads** via `uploadLargeFile()` — fault-tolerant for files of any size
- **Lazy generators** via `listFilesLazy()` — memory-efficient paginated iteration
- **Abstract base classes**: `AbstractProvider`, `AbstractService`, `AbstractAuthService` — eliminate boilerplate across providers
- **`HasHttpClient` trait** with built-in error handling via Laravel HTTP Client
- **`ConnectManager`** — central entry point with `google()`, `microsoft()`, `meta()`, `linkedin()`, `x()` shortcuts
- **`BridgeKitServiceProvider`** — Laravel auto-discovery, config merging, singleton binding
- **`BridgeKit` facade** — static access to ConnectManager
- **Config publishing** via `php artisan vendor:publish --tag=bridgekit-config`
- **4 exception types**: `BridgeKitException`, `AuthenticationException`, `ProviderException`, `InvalidConfigException`
- **Extensible architecture** — register custom providers via `ConnectManager::extend()`
- **76 tests, 195 assertions** — unit tests for all DTOs, providers, ConnectManager, and ServiceProvider
- Requires PHP 8.3+, Laravel 13, PHPUnit 11, Orchestra Testbench 11
