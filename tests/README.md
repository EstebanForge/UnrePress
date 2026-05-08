# UnrePress Testing Guide

## Test Structure

```
tests/
├── Unit/                  # Unit tests for individual components
│   ├── Core/             # Core functionality tests
│   ├── Security/         # Security-related tests
│   └── Helpers/          # Helper class tests
├── Integration/          # Integration tests for workflows
├── Helpers/              # Test utilities and mocks
└── Fixtures/             # Test data and fixtures
```

## Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
composer test:unit
composer test:integration

# Run with coverage report
composer test:coverage

# Run specific test file
phpunit tests/Unit/Core/UpdateCoreTest.php

# Run specific test method
phpunit --filter test_get_download_url_for_specific_version
```

## Test Environment Setup

**Current Setup**: Tests use BrainMonkey for WordPress function mocking - no WordPress installation required.

**Docker Environment**: For full WordPress integration tests, user has separate Docker environment configured.

## Writing Tests

### Unit Test Example

```php
<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use UnrePress\Updater\UpdateCore;

class UpdateCoreTest extends TestCase
{
    private UpdateCore $updateCore;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Define required constants
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }
        
        $this->updateCore = new UpdateCore();
    }

    public function test_something(): void
    {
        $this->assertTrue(true);
    }
}
```

### Testing Private Methods

Use reflection to test private/protected methods:

```php
$reflection = new \ReflectionClass($this->updateCore);
$method = $reflection->getMethod('privateMethodName');
$method->setAccessible(true);
$result = $method->invoke($this->updateCore, $args);
```

### Mocking WordPress Functions

Since WordPress functions are global, you can mock them using the `uopz` extension or by creating wrapper functions in tests.

## Current Status

- ✅ PHPUnit infrastructure configured
- ✅ Test directory structure created
- ✅ First unit test written (UpdateCoreTest)
- ⏳ Additional test coverage needed
- ⏳ Integration tests needed
- ⏳ Mock utilities needed

## Next Steps

1. Write comprehensive unit tests for all core classes
2. Create test utilities for mocking WordPress functions
3. Add integration tests for complete workflows
4. Set up continuous integration for automated testing
5. Add test coverage reporting