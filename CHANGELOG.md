# UnrePress changelog

# 0.8.0 - 2025-05-08

### Added
* Security layer: `InputValidator`, `SecurityMiddleware`, `CapabilityChecker`, `SecureFileOperations` — input sanitization, nonce verification, capability checks, and safe file operations across all update flows.
* Git provider abstraction: `GitProviderInterface`, `GitHubProvider`, `GitLabProvider`, `BitbucketProvider`, `GitProviderFactory` — unified API for fetching versions and download URLs from any supported Git host.
* `GitProviderWrapper` — thin wrapper connecting the provider factory to the updater classes.
* `ServiceContainer` — dependency injection container for core services.
* BitBucket and GitLab updater providers (`UpdaterProvider/BitBucket.php`, `UpdaterProvider/GitLab.php`).
* Jetpack Autoloader (`automattic/jetpack-autoloader ^2`) — resolves shared dependency conflicts when multiple WP plugins bundle the same packages.
* GitHub tags fallback: when a repo has no GitHub Releases (e.g. `WordPress/WordPress`), falls back to the Tags API.
* Transient clearing: `clearUpdateTransients()` now dynamically queries and clears all `unrepress_updates_plugin_*` and `unrepress_updates_theme_*` transients, plus standard WP update transients.
* Test suite: Pest tests for security modules, container, updaters, providers, and helpers with Brain Monkey mock fixtures.
* `index.html` sentinel files in all directories to prevent directory listing.

### Fixed
* Namespace resolution: bare `stdClass`, `Exception`, `InvalidArgumentException` inside `namespace UnrePress\*` resolved to non-existent namespaced classes. Added leading `\` for root resolution (6 files).
* `InputValidator` static calls: all methods are instance-based but were called with `::` syntax. Switched to `$this->inputValidator->` instance calls.
* `validateVersion()` returns `bool`, not the version string — stored `true` as tag name (`"1"`), producing broken download URLs like `/zipball/1`. Fixed in `UpdateCore`, `UpdatePlugins`, and `UpdateThemes`.
* `SecurityMiddleware::verifyAdminNonce()` passed `bool $die` as `check_admin_referer()`'s second argument (`$query_arg`), causing nonce verification to always fail.
* `SecurityMiddleware::verifyAjaxNonce()` — `check_ajax_referer()` returns `int|false`, method declared `:bool` return type. PHP 8.3 TypeError. Added `(bool)` cast.
* `api.github.com` blocked by download host allowlist — only `github.com` and `codeload.github.com` were permitted.
* Stale plugin/theme tag transients never cleared on force-check, serving cached `version: 1` data indefinitely.
* PSR-12 variable naming: `$original_tag_name` → `$originalTagName`, `$repo_url` → `$repoUrl`, `$latest_version` → `$latestVersion` in updater classes.

### Changed
* Moved autoloader from `vendor-dist/` to `vendor/` with Jetpack Autoloader integration.
* Updated AGENTS.md to reflect current codebase (PHP 8.3+, namespace rules, file structure).

# 0.7.0 - 2025-05-24
* Themes and Plugin discovery, search and install inside wp-admin, now is ready.
* Improved the WordPress core update logic.
* Now blocking wp.org and all the related "personal" domains by default. [This is too much](https://www.reddit.com/r/Wordpress/comments/1ktpzv3/wordpress_68_seems_to_be_breaking_update/). Will offer an options page to disable this. This can already be done by using the `UNREPRESS_BLOCK_WPORG` constant tho.
* Improved `unrepress_debug()` function.

# 0.5.0 - 2025-02-16
* UnrePress now supports Plugins and Themes installation.
* Fixed plugin and theme installation issues with correct slug detection.
* Added early slug capture from install button data and URL parameters.
* Improved source directory handling during installation process.

# 0.4.1 - 2025-01-03
* Added blocking of requests to "his ecommerce website". Reason: [Sybre post](https://x.com/SybreWaaijer/status/1875230654054752374). There is just [too much data](https://x.com/SybreWaaijer/status/1875230672756858980) being sent to that guy by his plugin.

# 0.4.0 - 2024-12-31
* Core update fallback. UnrePress will prioritize updates using its Index. But, if for some reason the Index is not available, it will fallback to use wp.org repository instead.

## 0.3.0 - 2024-12-24
* Working themes updater.
* Working plugins updater.
* Tweaked core updater.
* Spread the word!

## 0.2.0 - 2024-12-23
* Working plugins updater.

## 0.1.0 - 2024-11-03
* Initial public release.
* UnrePress can update WordPress core from GitHub. TODO: Add support for BitBucket and GitLab.

## 0.0.1 - 2024-10-03
* Project started
