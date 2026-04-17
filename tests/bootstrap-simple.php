<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for UnrePress Tests
 *
 * This bootstrap allows running unit tests with or without BrainMonkey
 * for WordPress function mocking.
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

// Try to load BrainMonkey for WordPress function mocking
$brainMonkeyLoaded = false;
try {
    $brainMonkeyApi = PROJECT_ROOT . '/vendor/brain/monkey/inc/api.php';
    if (file_exists($brainMonkeyApi)) {
        require_once $brainMonkeyApi;
        $brainMonkeyLoaded = true;
    }
} catch (\Throwable $e) {
    // BrainMonkey not available, continue without it
    $brainMonkeyLoaded = false;
}

// Define basic WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('UNREPRESS_PREFIX')) {
    define('UNREPRESS_PREFIX', 'unrepress_');
}

if (!defined('UNREPRESS_TRANSIENT_EXPIRATION')) {
    define('UNREPRESS_TRANSIENT_EXPIRATION', 3600);
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

// Load BrainMonkey helper classes if available
if ($brainMonkeyLoaded) {
    // BrainMonkey is loaded and ready to use
    require_once TESTS_DIR . '/Helpers/WordPressTestHelper.php';
}

// Define WP_Error class for tests (needed regardless of BrainMonkey)
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public string $code = '';
        public string $message = '';

        public function __construct($code, $message)
        {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}

// Basic WordPress function stubs (used when BrainMonkey is not active)
if (!$brainMonkeyLoaded) {
    if (!function_exists('__')) {
        function __($text, $domain = 'default') {
            return $text;
        }
    }

    if (!function_exists('_e')) {
        function _e($text, $domain = 'default') {
            echo $text;
        }
    }

    if (!function_exists('_x')) {
        function _x($text, $context, $domain = 'default') {
            return $text;
        }
    }

    if (!function_exists('esc_html__')) {
        function esc_html__($text, $domain = 'default') {
            return htmlspecialchars($text);
        }
    }

    if (!function_exists('esc_attr')) {
        function esc_attr($text) {
            return htmlspecialchars($text, ENT_QUOTES);
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html($text) {
            return htmlspecialchars($text);
        }
    }

    if (!function_exists('esc_url')) {
        function esc_url($url) {
            return filter_var($url, FILTER_SANITIZE_URL);
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) {
            return htmlspecialchars(strip_tags($str));
        }
    }

    if (!function_exists('wp_sprintf')) {
        function wp_sprintf($pattern, ...$args) {
            return vsprintf($pattern, $args);
        }
    }

    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing) {
            return false;
        }
    }

    if (!function_exists('wp_remote_get')) {
        function wp_remote_get($url, $args = []) {
            return ['body' => '', 'response' => ['code' => 200]];
        }
    }

    if (!function_exists('wp_remote_retrieve_body')) {
        function wp_remote_retrieve_body($response) {
            return is_array($response) ? ($response['body'] ?? '') : '';
        }
    }

    if (!function_exists('wp_remote_retrieve_response_code')) {
        function wp_remote_retrieve_response_code($response) {
            return is_array($response) ? ($response['response']['code'] ?? 200) : 200;
        }
    }
}

$statusMessage = "UnrePress test environment loaded successfully.\n";
$statusMessage .= "PHPUnit 12.5.22 ";
$statusMessage .= $brainMonkeyLoaded ? "with BrainMonkey 2.7.0 for WordPress mocking.\n" : "(without BrainMonkey - basic stubs only).\n";
$statusMessage .= "Full WordPress integration: Use your Docker environment.\n";

echo $statusMessage;
