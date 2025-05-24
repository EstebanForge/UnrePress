<?php

/**
 * Plugin Name: UnrePress for WordPress
 * Plugin URI: https://github.com/EstebanForge/UnrePress
 * Description: Liberate WordPress ecosystem. Core, Plugins and Themes updates, directly from their developers. Using git providers like GitHub, BitBucket or GitLab.
 * Version: 0.5.0
 * Author: Esteban Cuevas
 * Author URI: https://actitud.xyz
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: unrepress
 * Domain Path: /languages.
 */

// No direct access
defined('ABSPATH') or die();

// Define plugin constants
define('UNREPRESS_VERSION', get_file_data(__FILE__, ['Version' => 'Version'], false)['Version']);
define('UNREPRESS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('UNREPRESS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UNREPRESS_PREFIX', 'unrepress_');
define('UNREPRESS_TEMP_PATH', WP_CONTENT_DIR . '/upgrade/');
define('UNREPRESS_BLOCK_WPORG', true);
define('UNREPRESS_BLOCKED_HOSTS', 'api.wordpress.org,*.wordpress.org,*.wordpress.com,*.wordpress.net,*.wordpress.tv,*.woocommerce.com,tracking.woocommerce.com,*.woocommerce.org,*.wp.com,*.wp.org,*.wp.net');
define('UNREPRESS_FILE', __FILE__);

// Define: UnrePress Index
if (!defined('UNREPRESS_INDEX')) {
    define('UNREPRESS_INDEX', 'https://raw.githubusercontent.com/EstebanForge/UnrePress-index/');
}

// Define: GitHub API Token
if (!defined('UNREPRESS_TOKEN_GITHUB')) {
    define('UNREPRESS_TOKEN_GITHUB', apply_filters('unrepress_github_token', ''));
}

// Define transient expiration time (60 minutes by default)
if (!defined('UNREPRESS_TRANSIENT_EXPIRATION')) {
    define('UNREPRESS_TRANSIENT_EXPIRATION', 60 * MINUTE_IN_SECONDS);
}

// Composer autoloader
if (file_exists(UNREPRESS_PLUGIN_PATH . 'vendor-dist/autoload.php')) {
    require_once UNREPRESS_PLUGIN_PATH . 'vendor-dist/autoload.php';
    // Helpers
    require_once UNREPRESS_PLUGIN_PATH . 'includes/helpers.php';
} else {
    // Log error or display admin notice
    add_action('admin_notices', function () {
        echo '<div class="error"><p>' . __('UnrePress: Composer autoloader not found. Please run composer install.', 'unrepress') . '</p></div>';
    });

    return;
}

// Initialize the plugin
function unrepress_init(): void
{
    try {
        $plugin = new UnrePress\UnrePress();
        $plugin->run();
    } catch (Exception $e) {
        // Log error
        error_log('UnrePress Error: ' . $e->getMessage());

        // Display admin notice
        add_action('admin_notices', function () use ($e) {
            echo '<div class="error"><p>UnrePress Error: ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
}
add_action('plugins_loaded', 'unrepress_init');
