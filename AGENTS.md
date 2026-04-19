# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

UnrePress is a WordPress plugin that replaces WordPress.org updates with git provider updates (GitHub, BitBucket, GitLab). It fetches WordPress core updates from the official WordPress GitHub repository and plugin/theme updates from a community-maintained index, liberating the WordPress ecosystem from centralized control.

**Requirements:** PHP 8.1+, WordPress 6.5+

## Architecture

### Entry Point & Bootstrap
- **`unrepress.php`**: Plugin bootstrap file
  - Defines constants (version, paths, blocked hosts, index URL)
  - Loads Composer autoloader from `vendor/autoload.php`
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

- **`Updater/`**: Update orchestration with security integration
  - `UpdateCore` - WordPress core updates from GitHub with security validation
  - `UpdatePlugins` - Plugin updates from git providers with security validation
  - `UpdateThemes` - Theme updates from git providers with security validation
  - `UpdateLock` - Update locking mechanism

- **`UpdaterProvider/`**: Modern Git provider implementations
  - `GitHub` - GitHub provider using modern API client (knplabs/github-api)
  - `GitLab` - GitLab provider using modern API client (m4tthumphrey/php-gitlab-api)
  - `BitBucket` - Bitbucket provider using modern API client (bitbucket/client)
  - `GitProviderWrapper` - Unified wrapper for all git providers with auto-detection

- **`Security/`**: Security modules for input validation and capability checking
  - `SecurityMiddleware` - Main security orchestration with CSRF/capability checks
  - `CapabilityChecker` - WordPress capability verification (update_core, update_plugins, etc.)
  - `InputValidator` - Input sanitization (slugs, URLs, versions, JSON, file extensions)
  - `XssProtection` - XSS attack prevention
  - `SqlInjectionProtection` - SQL injection prevention
  - `PathTraversalProtection` - Directory traversal prevention

- **`GitProviders/`**: Git provider API clients (modern replacements for manual wp_remote_* calls)
  - `GitHubProvider` - GitHub API client implementation
  - `GitLabProvider` - GitLab API client implementation
  - `BitbucketProvider` - Bitbucket API client implementation
  - `GitProviderFactory` - Factory pattern for provider instantiation
  - `ProviderInterface` - Common interface for all git providers

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
# Run all Pest tests (modern replacement for PHPUnit)
vendor/bin/pest

# Run specific test suite
vendor/bin/pest --filter=<TestName>

# Run tests in specific directory
vendor/bin/pest tests/Unit/UpdaterProvider/
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
- **Testing**: Pest v4 (modern replacement for PHPUnit - see `phpunit.xml`)

## Modern Architecture & Design Patterns

### Provider Pattern
- **GitProviderInterface**: Common interface for all git providers
- **Factory Pattern**: `GitProviderFactory` for provider instantiation
- **Wrapper Pattern**: `GitProviderWrapper` for unified API access
- **Auto-detection**: Automatic provider detection from repository URLs

### Security Architecture
- **Defense in Depth**: Multiple layers of security validation
- **Input Validation**: All user inputs validated via `InputValidator`
- **Capability Checking**: WordPress capability verification via `CapabilityChecker`
- **CSRF Protection**: Nonce verification for all state-changing operations
- **XSS Prevention**: Output escaping and sanitization
- **SQL Injection Prevention**: Prepared statements and input sanitization
- **Path Traversal Prevention**: File path validation and sanitization

### Performance Optimizations
- **Modern API Clients**: Replaced manual `wp_remote_*` calls with dedicated API clients
  - `knplabs/github-api` (v3.16.0) for GitHub
  - `m4tthumphrey/php-gitlab-api` (v12.0.0) for GitLab
  - `bitbucket/client` (v5.0.0) for Bitbucket
- **Caching**: Transient caching for index data and version information
- **Lazy Loading**: Providers instantiated only when needed
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

### Security Best Practices
- **Never Trust User Input**: Always validate and sanitize via `InputValidator`
- **Capability Checks**: Always verify user capabilities before operations
- **Nonce Verification**: Use nonces for all state-changing operations
- **Prepared Statements**: Never use raw SQL - always use prepared statements
- **Output Escaping**: Escape all output to prevent XSS attacks
- **File Validation**: Validate file paths and extensions to prevent path traversal
- **Error Handling**: Never expose internal errors to users

### Git Provider Integration
- **Auto-detection**: Providers automatically detected from repository URLs
- **Factory Pattern**: Use `GitProviderFactory::createFromUrl()` for instantiation
- **Unified Interface**: All providers implement `GitProviderInterface`
- **Error Handling**: Graceful fallbacks for API failures
- **Caching**: API responses cached to minimize requests
- **Authentication**: Token support for private repositories

### Hook Integration
- Plugin hooks into `wp_version_check` for core updates
- Uses standard WP hooks for plugin/theme updates
- Filters applied for customization (e.g., `unrepress_github_token`)

## Build Process

1. **Code style fixes**: `composer cs:fix`
2. **Autoloader optimization**: `composer dump-autoload --optimize --no-dev`
3. **Version bump**: Update `unrepress.php` and `composer.json`
4. **Production build**: `composer production` (fixes CS, installs prod dependencies, optimizes autoloader)
