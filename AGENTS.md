# AGENTS.md

Guidance for AI agents working with this codebase.

## Project Overview

UnrePress replaces WordPress.org updates with git provider updates (GitHub, BitBucket, GitLab). It fetches WordPress core from the official WordPress GitHub repo and plugin/theme updates from a community-maintained index.

**Requirements:** PHP 8.3+, WordPress 6.5+

## Architecture

### Entry Point & Bootstrap
- **`unrepress.php`**: Plugin bootstrap
  - Defines constants (version, paths, blocked hosts, index URL)
  - Loads `vendor/autoload_packages.php` (Jetpack Autoloader) with fallback to `vendor/autoload.php`
  - Initializes `UnrePress\UnrePress::run()` on `plugins_loaded` hook

### Core Components (`src/`)
- **`UnrePress.php`**: Main plugin class
  - Entry point: `run()` method
  - Fetches and caches main index from `UNREPRESS_INDEX` (30-day transient)
  - Initializes all subsystems
  - Only runs in admin context

- **`EgoBlocker.php`**: Blocks requests to WordPress.org domains
- **`Helpers.php`**: Utility functions (debugging, filesystem, API requests, transient clearing)
- **`Debugger.php`**: Debug logging via `Debugger::log()`

### Subsystems

#### `Admin/` — Admin interface
- `Hider` — Hides WordPress.org references in admin
- `UpdaterPages` — Customizes update UI pages

#### `Index/` — Index management
- `Index` — Main index handler
- `PluginsIndex` — Plugin index operations
- `ThemesIndex` — Theme index operations

#### `Updater/` — Update orchestration
- `UpdateCore` — WordPress core updates from GitHub
- `UpdatePlugins` — Plugin updates from git providers
- `UpdateThemes` — Theme updates from git providers
- `UpdateLock` — Prevents concurrent updates

#### `UpdaterProvider/` — Git provider implementations (modern API clients)
- `GitHub` — GitHub provider (knplabs/github-api v3)
- `GitLab` — GitLab provider (m4tthumphrey/php-gitlab-api v12)
- `BitBucket` — Bitbucket provider (bitbucket/client v5)
- `GitProviderWrapper` — Unified wrapper with auto-detection from URL
- `ProviderInterface` — Common interface

#### `GitProviders/` — Low-level Git provider API clients
- `GitHubProvider` — GitHub API client (knplabs)
- `GitLabProvider` — GitLab API client
- `BitbucketProvider` — Bitbucket API client
- `GitProviderFactory` — Factory for provider instantiation
- `GitProviderInterface` — Common interface

#### `Security/` — Security modules
- `SecurityMiddleware` — CSRF/capability checks
- `CapabilityChecker` — WordPress capability verification
- `InputValidator` — Input sanitization (slugs, URLs, versions, JSON, file extensions). **Instance methods only** — not static
- `SecureFileOperations` — Secure file handling
- `XssProtection` — XSS prevention (referenced but check usage)
- `SqlInjectionProtection` — SQL injection prevention (referenced but check usage)
- `PathTraversalProtection` — Path traversal prevention (referenced but check usage)

#### `Container/`
- `ServiceContainer` — DI container

### Views (`views/`)
- `updater/unrepress-updater.php` — Main updater page (handles force-check, core update UI)
- `updater/unrepress-doing-core-update.php` — Core update progress page

## Critical Coding Rules

### Namespace Resolution
All source files use `namespace UnrePress\*`. PHP builtins **must** use leading `\`:
```php
// WRONG — resolves to UnrePress\Updater\stdClass
new stdClass();
catch (Exception $e)

// CORRECT — resolves to global namespace
new \stdClass();
catch (\Exception $e)
```
Applies to: `stdClass`, `Exception`, `InvalidArgumentException`, `RuntimeException`, all PHP built-in classes.

### InputValidator — Instance, Not Static
All `InputValidator` methods are **instance methods**. Use `$this->inputValidator->method()`, never `InputValidator::method()`.

### Autoloading
Plugin uses **Jetpack Autoloader** (`automattic/jetpack-autoloader ^2`). Entry point is `vendor/autoload_packages.php`, which ensures the latest version of shared packages is loaded when multiple WP plugins bundle the same Composer dependencies.

## Development Commands

```bash
# Code style (PSR-12)
composer cs:check
composer cs:fix

# Tests (Pest v4)
vendor/bin/pest
vendor/bin/pest --filter=<TestName>

# Version bump
composer version-bump

# Production build
composer production
```

## Configuration Constants

Define in `wp-config.php` or via environment:

- **`UNREPRESS_INDEX`**: URL to community index (default: `https://raw.githubusercontent.com/EstebanForge/UnrePress-index/`)
- **`UNREPRESS_TOKEN_GITHUB`**: GitHub API token (use `unrepress_github_token` filter)
- **`UNREPRESS_TRANSIENT_EXPIRATION`**: Cache TTL (default: 60 minutes)
- **`UNREPRESS_BLOCK_WPORG`**: Enable/disable WP.org blocking (default: `true`)
- **`UNREPRESS_BLOCKED_HOSTS`**: Comma-separated blocked domains

## Transient Naming

All UnrePress transients use prefix `UNREPRESS_PREFIX` (`unrepress_`):
- `unrepress_main_index` — Main index cache
- `unrepress_updates_core_latest_version` — Latest core version
- `unrepress_updates_count` — Update count cache
- `unrepress_updates_plugin_latest_tag_object_<slug>` — Per-plugin cached tag
- `unrepress_updates_theme_*` — Per-theme caches

`Helpers::clearUpdateTransients()` clears all of the above (queries `wp_options` for matching keys, deletes via `delete_transient()` for cache-backend compatibility).

## Code Standards

- **PHP 8.3+**, `declare(strict_types=1)`, PSR-12
- **PSR-4**: `UnrePress\` namespace → `src/` directory
- **No closing PHP tags**
- **Early returns**, guard clauses
- `vendor/` is committed (WP plugin distribution needs it bundled)

## Key Patterns

### Debug Logging
- `unrepress_debug()` — Global helper (controlled by `WP_DEBUG`)
- `Debugger::log()` — Class-based logging

### Git Provider Flow
1. Index JSON provides repo URL + tags/releases URLs
2. `GitProviderFactory` auto-detects provider from URL
3. Provider fetches latest version (releases API first, tags fallback for GitHub)
4. `GitProviderWrapper` unifies the interface

### WordPress Core Update Flow
1. `UpdateCore::getLatestCoreVersion()` fetches version from GitHub tags
2. `checkCoreUpdatesFromGitHub()` populates `update_core` transient with GitHub download URL
3. WP native update UI triggers the actual update

### Plugin/Theme Update Flow
1. `UpdatePlugins::checkForPluginUpdate()` / `UpdateThemes` equivalent
2. `getLatestVersion()` fetches latest tag from git provider
3. Tag objects cached in transients per-plugin
4. `getDownloadUrl()` builds download URL from tag name
