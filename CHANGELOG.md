# Changelog

All notable changes to BridgeKit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
