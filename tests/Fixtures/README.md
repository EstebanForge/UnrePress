# Test Fixtures

This directory contains test data and sample responses for unit and integration tests.

## Fixture Files

### github-api-responses.php
Sample GitHub API responses for testing:
- Latest release information
- Tags/versions lists
- Repository information
- Plugin and theme releases
- Error responses (rate limit, not found, timeout)

### plugin-theme-data.php
Sample plugin and theme metadata:
- Standard plugins and themes
- Plugins with custom branches
- Plugins with .git extension in repository URLs
- Plugin headers (standard, minimal, full)
- Theme style.css headers (standard, child themes)

### index-data.php
Sample index data for plugins and themes:
- Plugins index with multiple entries
- Themes index with multiple entries
- Main index referencing plugin/theme indexes

### update-objects.php
Sample WordPress update objects:
- Core update objects
- Plugin update objects
- Theme update objects
- Update offers (shown to users)
- Translation update objects

## Usage

```php
// Load fixtures
$githubResponses = require __DIR__ . '/github-api-responses.php';
$pluginData = require __DIR__ . '/plugin-theme-data.php';
$indexData = require __DIR__ . '/index-data.php';
$updateObjects = require __DIR__ . '/update-objects.php';

// Use in tests
$latestRelease = $githubResponses['latest_release'];
$standardPlugin = $pluginData['plugins']['standard_plugin'];
$pluginUpdate = $updateObjects['plugin_update'];
```

## Adding New Fixtures

When adding new test scenarios, create corresponding fixture data here:

1. Choose appropriate fixture file or create new one
2. Add descriptive array keys
3. Include all necessary fields
4. Document in this README
