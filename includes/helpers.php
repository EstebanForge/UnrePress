<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') or die();

if (!function_exists('unrepress_debug')) {
    /**
     * Helper function to log debug messages using the UnrePress Debugger.
     * Only logs when WP_DEBUG is true.
     *
     * @param mixed ...$args Multiple arguments to log
     * @return void
     */
    function unrepress_debug(...$args): void
    {
        UnrePress\Debugger::log(...$args);
    }
}

if (!function_exists('unrepress_get_github_token')) {
    /**
     * Get GitHub token from various sources in order of priority:
     * 1. Environment variable
     * 2. wp-config.php constant
     * 3. Filter hook
     * 4. WordPress option
     *
     * @return string The GitHub token or empty string if not found
     */
    function unrepress_get_github_token(): string
    {
        // 1. Check environment variable first (most secure)
        $env_token = getenv('UNREPRESS_GITHUB_TOKEN');
        if (!empty($env_token)) {
            //unrepress_debug('GitHub token found in environment variable');
            return $env_token;
        }

        // 2. Check if defined in wp-config.php and not empty
        if (defined('UNREPRESS_TOKEN_GITHUB') && !empty(UNREPRESS_TOKEN_GITHUB)) {
            //unrepress_debug('GitHub token found in UNREPRESS_TOKEN_GITHUB constant:', 'Length: ' . strlen(UNREPRESS_TOKEN_GITHUB));
            return UNREPRESS_TOKEN_GITHUB;
        }

        // 3. Apply filter for custom implementations
        $filtered_token = apply_filters('unrepress_github_token', '');
        if (!empty($filtered_token)) {
            //unrepress_debug('GitHub token found via filter');
            return $filtered_token;
        }

        // 4. Check WordPress option
        $option_token = get_option('unrepress_github_token', '');
        if (!empty($option_token)) {
            //unrepress_debug('GitHub token found in WordPress option');
            return $option_token;
        }

        //unrepress_debug('No GitHub token found in any source');
        return '';
    }
}
