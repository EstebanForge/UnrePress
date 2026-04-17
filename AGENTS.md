# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

UnrePress is a WordPress plugin that replaces WordPress.org updates with git provider updates (GitHub, BitBucket, GitLab). It fetches WordPress core updates from the official WordPress GitHub repository and plugin/theme updates from a community-maintained index, liberating the WordPress ecosystem from centralized control.

**Requirements:** PHP 8.1+, WordPress 6.5+

## Architecture

### Entry Point & Bootstrap
- **`unrepress.php`**: Plugin bootstrap file
  - Defines constants (version, paths, blocked hosts, index URL)
  - Loads Composer autoloader from `vendor-dist/autoload.php` (Strauss-prefixed dependencies)
  - Initializes `UnrePress\UnrePress::run()` on `plugins_loaded` hook

### Core Components (src/)
- **`UnrePress.php`**: Main plugin class
  - Entry point: `run()` method
  - Fetches and caches main index from `UNREPRESS_INDEX` (30-day transient)
  - Initializes all subsystems
  - Only runs in admin context (not frontend/REST API)

- **`EgoBlocker.php`**: Blocks requests to WordPress.org domains
- **`Helpers.php`**: Utility functions (debugging, filesystem operations, API requests)

### Subsystems
- **`Admin/`**: Admin interface
  - `Hider` - Hides WordPress.org references in admin
  - `UpdaterPages` - Customizes update UI pages

- **`Index/`**: Community-maintained index management
  - `Index` - Main index handler
  - `PluginsIndex` - Plugin index operations
  - `ThemesIndex` - Theme index operations

- **`Updater/`**: Update orchestration
  - `UpdateCore` - WordPress core updates from GitHub
  - `UpdatePlugins` - Plugin updates from git providers
  - `UpdateThemes` - Theme updates from git providers
  - `UpdateLock` - Update locking mechanism

- **`UpdaterProvider/`**: Git provider implementations (GitHub, BitBucket, GitLab)

### Dependency Management
- Uses **Strauss** to namespace-prefix vendor dependencies
- Dependencies are prefixed with `EstebanForge\UnrePress\` and classmap prefix `ESFR_`
- Prefixed dependencies output to `vendor-dist/` (not `vendor/`)
- Run `composer prefix-namespaces` after dependency changes

## Development Commands

### Code Style
```bash
# Check code style (PSR-12 + custom rules)
composer cs:check

# Fix code style automatically
composer cs:fix
```

### Testing
```bash
# Run all PHPUnit tests
phpunit

# Run specific test suite
phpunit --testsuite <name>
```

### Release Preparation
```bash
# Prepare for release: fix CS + prefix namespaces
composer release

# Manually run Strauss namespace prefixing
composer prefix-namespaces
```

### Version Bumping
```bash
# Bump version (interactive)
composer version-bump

# Bump version to specific version
composer version-bump 1.2.3
```

### Composer Autoloading
```bash
# Regenerate autoloader (after adding classes)
composer dump-autoload

# Optimized autoloader (for production)
composer dump-autoload --optimize --no-dev
```

## Configuration Constants

Define in `wp-config.php` or via environment to customize behavior:

- **`UNREPRESS_INDEX`**: URL to community index (default: `https://raw.githubusercontent.com/EstebanForge/UnrePress-index/`)
- **`UNREPRESS_TOKEN_GITHUB`**: GitHub API token for private repos/authenticated requests (use `unrepress_github_token` filter)
- **`UNREPRESS_TRANSIENT_EXPIRATION`**: Transient cache expiration (default: 60 minutes)
- **`UNREPRESS_BLOCK_WPORG`**: Enable/disable WordPress.org blocking (default: `true`)
- **`UNREPRESS_BLOCKED_HOSTS`**: Comma-separated list of blocked domains

## Code Standards

- **PHP**: 8.1+, strict types enabled, PSR-12 coding style
- **Autoloading**: PSR-4 (`UnrePress\` namespace → `src/` directory)
- **Formatting**: PHP CS Fixer (see `.php-cs-fixer.php`)
- **Testing**: PHPUnit (see `phpunit.xml`)
- **No closing PHP tags** in files (PSR-12)
- **Always declare strict types**: `declare(strict_types=1);`
- **Use early returns** and **guard clauses**
- **WordPress coding standards**: Use WP functions over PHP equivalents (e.g., `wp_sprintf` vs `sprintf`)

## Key Patterns

### Transient Caching
Index data is cached in transients for performance:
```php
$cachedIndex = get_transient($transient_key);
if (false !== $cachedIndex) {
    return $cachedIndex;
}
// Fetch data...
set_transient($transient_key, $data, 30 * DAY_IN_SECONDS);
```

### Debug Logging
Use `unrepress_debug()` helper function (controlled by WP_DEBUG).

### WordPress Filesystem API
Always use WP Filesystem API for file operations (respecting filesystem credentials).

### Hook Integration
- Plugin hooks into `wp_version_check` for core updates
- Uses standard WP hooks for plugin/theme updates
- Filters applied for customization (e.g., `unrepress_github_token`)

## Build Process

1. **Code style fixes**: `composer cs:fix`
2. **Dependency prefixing**: Strauss prefixes all vendor dependencies
3. **Autoloader optimization**: `composer dump-autoload --optimize --no-dev`
4. **Version bump**: Update `unrepress.php` and `composer.json`

The `vendor/` directory is for development only. The plugin loads from `vendor-dist/` in production.
