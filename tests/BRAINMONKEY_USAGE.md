# BrainMonkey Usage Guide

## What is BrainMonkey?

BrainMonkey is a library that allows you to mock WordPress functions in your unit tests. This is essential for testing UnrePress since the plugin relies heavily on WordPress functions.

## Installation

BrainMonkey 2.7.0 is already installed via Composer:
```bash
composer require --dev brain/monkey:^2.7
```

## Basic Usage

### 1. Extending the Test Helper

Create test classes that extend `WordPressTestHelper`:

```php
<?php

namespace UnrePress\Tests\Unit\Core;

use UnrePress\Tests\Helpers\WordPressTestHelper;

class UpdateCoreTest extends WordPressTestHelper
{
    public function test_something(): void
    {
        // WordPress functions are available and can be mocked
    }
}
```

### 2. Mocking WordPress Functions

```php
public function test_plugin_update_with_mocked_functions(): void
{
    // Mock a WordPress function
    Functions::when('get_transient')->return('cached_data');
    
    // Mock with expected arguments
    Functions::expect('get_transient')
        ->once()
        ->with('unrepress_updates_core_latest_version')
        ->return('6.5.0');
    
    // Your test code here
    $result = get_transient('unrepress_updates_core_latest_version');
    $this->assertEquals('6.5.0', $result);
}
```

### 3. Mocking HTTP Requests

```php
public function test_github_api_request(): void
{
    $mockResponse = [
        'body' => '{"tag_name": "v6.5.0"}',
        'response' => ['code' => 200],
    ];
    
    $this->mockHttpFunctions($mockResponse);
    
    // Test code that makes wp_remote_get calls
    $response = wp_remote_get('https://api.github.com/repos/WordPress/WordPress/tags');
    $this->assertEquals('{"tag_name": "v6.5.0"}', $response['body']);
}
```

### 4. Mocking File System Operations

```php
public function test_file_operations(): void
{
    $this->mockFilesystem();
    
    // Mock copy_dir to prevent actual file operations
    Functions::expect('copy_dir')
        ->once()
        ->with('/source/path', '/dest/path')
        ->andReturn(true);
    
    // Test your file operation code
    $result = copy_dir('/source/path', '/dest/path');
    $this->assertTrue($result);
}
```

## Common WordPress Functions to Mock

### Transients
```php
Functions::when('get_transient')->return('cached_value');
Functions::when('set_transient')->return(true);
Functions::when('delete_transient')->return(true);
```

### HTTP Requests
```php
Functions::when('wp_remote_get')->return($mockResponse);
Functions::when('wp_remote_retrieve_body')->return('response_body');
Functions::when('wp_remote_retrieve_response_code')->return(200);
Functions::when('is_wp_error')->return(false);
```

### File System
```php
Functions::when('WP_Filesystem')->return(true);
Functions::when('copy_dir')->return(true);
Functions::when('unzip_file')->return(true);
Functions::when('wp_delete_file')->return(true);
```

### Plugins/Themes
```php
Functions::when('get_plugins')->return($pluginList);
Functions::when('wp_get_theme')->return($themeObject);
Functions::when('get_theme_data')->return($themeData);
```

### Updates
```php
Functions::when('get_core_updates')->return([]);
Functions::when('get_site_transient')->return($updateObject);
Functions::when('set_site_transient')->return(true);
```

## Example Tests

### Testing UpdateCore with Mocked Functions

```php
<?php

namespace UnrePress\Tests\Unit\Core;

use UnrePress\Tests\Helpers\WordPressTestHelper;

class UpdateCoreWithMocksTest extends WordPressTestHelper
{
    public function test_get_latest_version_with_mocked_http(): void
    {
        $mockTags = [
            (object) ['name' => 'v6.5.0'],
            (object) ['name' => 'v6.4.0'],
        ];
        
        // Mock HTTP request to return tags
        Functions::when('wp_remote_get')->return([
            'body' => json_encode($mockTags),
        ]);
        Functions::when('wp_remote_retrieve_response_code')->return(200);
        Functions::when('wp_remote_retrieve_body')->return(json_encode($mockTags));
        Functions::when('is_wp_error')->return(false);
        
        // Mock transient to cache the result
        Functions::expect('set_transient')
            ->once()
            ->with('unrepress_updates_core_latest_version', '6.5.0', 3600);
        
        // Test the actual method
        $updateCore = new \UnrePress\Updater\UpdateCore();
        $latestVersion = $updateCore->getLatestCoreVersion();
        
        $this->assertEquals('6.5.0', $latestVersion);
    }
    
    public function test_update_with_mocked_filesystem(): void
    {
        // Mock file system operations
        $this->mockFilesystem();
        
        // Mock HTTP download
        Functions::when('wp_remote_get')->return([
            'body' => 'zip_content',
        ]);
        Functions::when('wp_remote_retrieve_response_code')->return(200);
        Functions::when('is_wp_error')->return(false);
        
        // Test update process (won't actually download files)
        // $updateCore = new \UnrePress\Updater\UpdateCore();
        // $result = $updateCore->update('core');
        // $this->assertTrue($result);
    }
}
```

## Benefits of Using BrainMonkey

1. **Isolation**: Test your code without actual WordPress installation
2. **Speed**: Unit tests run much faster than integration tests
3. **Control**: Mock specific return values for different test scenarios
4. **Reliability**: Tests don't depend on external services (GitHub API, etc.)
5. **Coverage**: Can test edge cases and error conditions easily

## Best Practices

1. **Always use the helper**: Extend `WordPressTestHelper` for consistent behavior
2. **Mock only what you need**: Don't mock functions your test doesn't use
3. **Use specific expectations**: `Functions::expect()->once()` vs `Functions::expect()->never()`
4. **Clean up**: The `tearDown()` method automatically clears all mocks
5. **Test error conditions**: Mock error responses to test error handling

## Available Helper Methods

The `WordPressTestHelper` class provides these methods:

- `mockTransients()` - Mock WordPress transient functions
- `mockHttpFunctions(array)` - Mock HTTP request functions
- `mockFilesystem()` - Mock filesystem operations
- `mockPluginFunctions(array)` - Mock plugin-related functions
- `mockUpdateFunctions()` - Mock update-related functions
- `createMockPost(array)` - Create mock WordPress post object
- `createMockTerm(array)` - Create mock WordPress term object
- `createMockPluginUpdate(string, string)` - Create mock plugin update object
- `createMockThemeUpdate(string, string)` - Create mock theme update object

## Next Steps

Start using BrainMonkey in your tests to:
1. Mock GitHub API responses for version checking
2. Mock WordPress filesystem operations
3. Test error handling scenarios
4. Create predictable test conditions

This will significantly improve test reliability and speed while allowing comprehensive testing of UnrePress functionality.
