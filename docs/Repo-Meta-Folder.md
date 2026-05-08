# The .wp-meta Folder

The `.wp-meta` folder is an optional but recommended component for both plugins and themes in UnrePress. It contains additional assets and metadata that enhance your package's presentation in The Index.

## Folder Structure

```
.wp-meta/
├── wp-meta.json
├── screenshot-*.(png|jpg) (01 to 04)
├── banner-772x250.(png|jpg)
├── banner-1544x500.(png|jpg)
├── icon-256x256.(png|jpg)
├── icon-512x512.(png|jpg)
```

## Required Files

### wp-meta.json

This is the main configuration file that provides detailed metadata about your package. Here's an example structure:

```json
{
    "schema_version": 1,
    "package": "estebanforge/unrepress",
    "type": "plugin",
    "update_from": "tags",
    "license": "GPL-2.0-or-later",
    "name": "UnrePress",
    "slug": "unrepress",
    "version": "1.0.0",
    "wp_min": "6.5",
    "wp_tested": "6.7",
    "php_min": "8.1",
    "description": "WordPress plugin to obtain updates directly from git providers (like GitHub, BitBucket or GitLab)",
    "homepage": "https://github.com/EstebanForge/UnrePress",
    "authors": [
        {
            "name": "Esteban Cuevas",
            "email": "esteban@attitude.cl",
            "homepage": "https://actitud.xyz"
        }
    ],
    "icons": {
        "256": "icon-256x256.png",
        "512": "icon-512x512.png"
    },
    "screenshots": [
        "screenshot-01.png",
        "screenshot-02.png",
        "screenshot-03.png",
        "screenshot-04.png"
    ],
    "banners": {
        "low": "banner-772x250.jpg",
        "high": "banner-1544x500.jpg"
    },
    "support": {
        "issues": "https://github.com/EstebanForge/UnrePress/issues",
        "discussions": "https://github.com/EstebanForge/UnrePress/discussions"
    }
}
```

#### JSON Schema Properties

- `schema_version`: Must be 1
- `package`: Your package identifier (vendor/name format)
- `type`: Either "plugin" or "theme"
- `update_from`: Source for updates (currently only "tags" is supported)
- `license`: Package license (SPDX identifier)
- `name`: Display name
- `slug`: Unique identifier for the package (lowercase, dashes for spaces)
- `version`: Current version of the package (semantic versioning)
- `wp_min`: Minimum required WordPress version
- `wp_tested`: Latest WordPress version tested with
- `php_min`: Minimum required PHP version
- `description`: Brief package description
- `homepage`: Package website or repository URL
- `authors`: Array of author information
- `icons`: Object containing icon sizes (256x256 and 512x512)
- `screenshots`: Array of screenshot filenames
- `banners`: Object containing banner images in low (772x250) and high (1544x500) resolutions
- `support`: Object containing support URLs for issues and discussions

## Image Assets

### Screenshots
- Format: PNG or JPG
- Filename pattern: `screenshot-XX.png` or `screenshot-XX.jpg` where XX is a number from 01 to 04
- Recommended size: 1200x900 pixels
- Purpose: Showcase your package's features and interface

### Banners
- Format: PNG or JPG
- Two required sizes:
  - `banner-772x250.(png|jpg)`: Standard resolution (low)
  - `banner-1544x500.(png|jpg)`: High resolution (2x)
- Purpose: Marketing header images

### Icons
- Format: PNG or JPG
- Two required sizes:
  - `icon-256x256.(png|jpg)`: Standard resolution
  - `icon-512x512.(png|jpg)`: High resolution
- Purpose: Identification in listings

## Best Practices

1. **Image Quality**
   - Use high-quality, compressed images
   - Ensure screenshots are clear and informative
   - Maintain consistent aspect ratios

2. **Metadata**
   - Keep descriptions concise and accurate
   - Provide valid support URLs
   - Use proper SPDX license identifiers

3. **File Organization**
   - Follow the exact filename patterns
   - Include all recommended image sizes
   - Maintain a clean folder structure

## Implementation

1. Create a `.wp-meta` folder in your package root
2. Add the required `.wp-meta.json` file with its structure and properties
3. Include appropriate image assets
4. Declare your plugin or theme support for `.wp-meta` folder in The Index, by setting the `wp-meta` property to `true`
