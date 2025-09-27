# Service Layer Refactor Plan

## Goals
- Encapsulate feed fetching, caching, and parsing logic into testable classes.
- Reduce global state and side effects in `functions.php`.
- Provide clear extension points for adding sources, alternate cache stores, or mocking during tests.

## Proposed Structure
- `src/Config/FeedRepository.php` — loads feed sources from configuration (reuses `config/feeds.php`).
- `src/Cache/CacheRepository.php` — handles read/write/cleanup of cached feed payloads.
- `src/Http/FeedClient.php` — wraps cURL multi handle usage and abstracts HTTP concerns.
- `src/Services/FeedAggregator.php` — orchestrates fetch, parse, normalize, and returns DTOs for articles.
- `src/Support/TimeFormatter.php` — shared helpers for relative time formatting.

## Migration Steps
1. Introduce Composer autoloading (PSR-4) for `src/` namespace.
2. Move existing helper functions into appropriate classes while preserving procedural wrappers for backwards compatibility.
3. Replace direct file path usage with injected `StoragePath` configuration to centralize runtime directories.
4. Add unit tests covering feed aggregation with mocked client and cache repositories.
5. Update entry points (`index.php`, API endpoints) to resolve services via lightweight container or factory functions.

## Open Questions
- Should caching remain JSON-file based or migrate to a generic storage interface (filesystem vs redis)?
- Do we enforce typed value objects (e.g., Article DTO) or continue using associative arrays initially?
- Are we targeting compatibility with PHP 7.x or can we rely on PHP 8 features like union types?

## Risks & Mitigations
- **Risk:** Large change set could introduce regressions. Mitigation: keep procedural fallbacks during transition and add integration tests.
- **Risk:** Hosting stack may lack Composer. Mitigation: include Composer-built autoloader in repo and document deployment steps.
