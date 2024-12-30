# UnrePress Documentation

## Project Overview
UnrePress is a WordPress plugin designed to replace WordPress update (core, themes, plguins) functionality with git-based updates from different git providers (like GitHub, BitBucket or GitLab).

## Directory Structure

The project follows a clean, modular structure under the `./src/` directory:

```
src/
├── Admin/               # Admin-related functionality
├── Index/              # Indexing functionality
├── Updater/            # Update management
├── UpdaterProvider/    # Update provider implementations
├── Debugger.php        # Debugging utilities
├── EgoBlocker.php      # Request blocking functionality
├── Helpers.php         # Common helper functions
└── UnrePress.php       # Main plugin class
```

Views are stored in the `./views/` directory:

```
views/
├── index.html          # Directory index protection
└── updater/           # Update-related views
    ├── unrepress-doing-core-update.php  # Core update process view
    └── unrepress-updater.php            # Main updater interface
```

## Core Components

### UnrePress.php
The main plugin class that bootstraps all functionality. It:
- Initializes core components
- Manages admin-only functionality
- Coordinates update management
- Handles indexing operations

### EgoBlocker.php
Security component that manages external request blocking:
- Blocks requests to specified hosts via `UNREPRESS_BLOCKED_HOSTS` constant
- Handles SSL verification in debug mode
- Implements WordPress's `pre_http_request` filter
- Supports wildcard domain blocking

### Admin Components
Located in the `Admin/` directory:
- `Hider`: Manages admin interface visibility
- `UpdaterPages`: Handles update-related admin pages

### Index Components
Located in the `Index/` directory:
- `Index`: Core indexing functionality
- `PluginsIndex`: Plugin-specific indexing
- `ThemesIndex`: Theme-specific indexing

### Updater Components
Located in the `Updater/` directory:
- `UpdateLock`: Manages update locking mechanism
- `UpdatePlugins`: Handles plugin updates
- `UpdateThemes`: Handles theme updates

### Helpers

Located in `./src/Helpers.php`, this class provides essential utility functions for the plugin's operation:

#### File System Operations
- Directory management (create, copy, move, remove) using both native PHP and WordPress filesystem API
- Safe file operations through WordPress's `WP_Filesystem` abstraction
- Temporary directory cleanup and management

#### Git Integration
- GitHub API URL normalization
- Version tag parsing and comparison
- Repository source directory fixing for WordPress compatibility

#### Update Management
- Update log writing and clearing
- WordPress update transients management
- Post-update cleanup routines

# The Index

The Index uses JSON files to define both plugins and themes. Main UnrePress index is located at `https://github.com/EstebanForge/UnrePress-index`.

## JSON Schemas
The following schemas define the required format for plugin and theme definitions in UnrePress:

### Plugin Schema
Example from `example-plugin_unrepress.json`:
```json
{
  "schema_version": 1,
  "type": "plugin",
  "name": "Plugin Name",
  "slug": "plugin-slug", // Same slug format as WordPress
  "author": "Author Name",
  "author_profile": "https://author-website.com",
  "sections": {
    "description": "Plugin description",
    "installation": "", // Installation instructions, if needed
    "changelog": "URL to changelog"
  },
  "banners": {
    "low": "URL to 772x250 banner",
    "high": "URL to 1544x500 banner"
  },
  "wp-meta": true,           // Does this repository contains a .wp-meta directory with more info?
  "free": true,               // Is it free?
  "paid_features": false,     // Does it have premium features?
  "date_added": "YYYY-MM-DD",
  "homepage": "Plugin website URL",
  "repository": "Git repository URL",
  "tags": "Git tags API URL",
  "update_from": "tags",      // Update source (tags/releases)
  "readme_md": "URL to README.md",
  "readme_txt": "URL to readme.txt"
}
```

### Theme Schema
Example from `example-theme_generatepress.json`:
```json
{
  "schema_version": 1,
  "type": "theme",
  "name": "Theme Name",
  "slug": "theme-slug", // Same slug format as WordPress
  "author": "Author Name",
  "author_profile": "Author website",
  "sections": {
    "description": "Theme description",
    "installation": "", // Installation instructions, if needed
    "changelog": "URL to changelog"
  },
  "banners": {
    "low": "URL to 772x250 banner",
    "high": "URL to 1544x500 banner"
  },
  "wp-meta": false,           // Does this repository contains a .wp-meta directory with more info?
  "free": true,               // Is it free?
  "paid_features": true,      // Does it have premium features?
  "date_added": "YYYY-MM-DD",
  "homepage": "Theme website URL",
  "repository": "Git repository URL",
  "tags": "Git tags URL",
  "update_from": "tags",      // Update source (tags/releases)
  "readme_md": "URL to README.md",
  "readme_txt": "URL to readme.txt"
}
```
