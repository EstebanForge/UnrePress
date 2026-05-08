<?php

declare(strict_types=1);

/**
 * Pest WordPress Test Bootstrap.
 *
 * This file sets up the WordPress testing environment for Pest tests.
 */

// Define test environment constants
define('TESTS_DIR', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

// Load Composer autoloader
if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require_once PROJECT_ROOT . '/vendor/autoload.php';
} else {
    die('Composer autoloader not found. Run: composer install');
}

// Define WordPress test constants if not already defined
if (!defined('WP_TESTS_DIR')) {
    define('WP_TESTS_DIR', '/tmp/wordpress-tests-lib');
}

if (!defined('WP_CORE_DIR')) {
    define('WP_CORE_DIR', '/tmp/wordpress');
}

// Check if WordPress test environment is set up
if (!file_exists(WP_TESTS_DIR . '/includes/functions.php')) {
    die(
        "WordPress test environment not set up.\n"
        . "Run: bash bin/install-wp-tests.sh wordpress_test root localhost latest\n"
    );
}

// Load WordPress test environment
require_once WP_TESTS_DIR . '/includes/functions.php';

// Manually load the plugin being tested
function _manually_load_plugin()
{
    // Define WordPress constants that would normally be defined by WP
    if (!defined('ABSPATH')) {
        define('ABSPATH', WP_CORE_DIR . '/');
    }

    if (!defined('WP_PLUGIN_DIR')) {
        define('WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins');
    }

    if (!defined('WP_CONTENT_DIR')) {
        define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
    }

    // Load the plugin
    require PROJECT_ROOT . '/unrepress.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require WP_TESTS_DIR . '/includes/bootstrap.php';

// Activate the plugin (if needed)
// activate_plugin('unrepress/unrepress.php');

echo "WordPress test environment loaded successfully.\n";
echo 'Project Root: ' . PROJECT_ROOT . "\n";
echo 'Tests Directory: ' . TESTS_DIR . "\n";
echo 'WordPress Core: ' . WP_CORE_DIR . "\n";
echo 'WordPress Tests: ' . WP_TESTS_DIR . "\n";
