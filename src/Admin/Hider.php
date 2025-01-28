<?php

namespace UnrePress\Admin;

use WP_Error;

class Hider
{
    public function __construct()
    {
        // If debug is on, don't hide WP updates menu
        if (defined('WP_DEBUG') && WP_DEBUG) {
            //return;
        }

        add_action('admin_menu', [$this, 'removeMenus']);
        add_action('admin_head', [$this, 'removeMenus']);
        add_action('current_screen', [$this, 'redirectWPCoreUpdate']);
        add_action('install_plugins_tabs', [$this, 'hideTabs'], 10, 1);
        add_filter('plugins_api_result', [$this, 'hidePluginCardElements'], 10, 3);
        add_action('admin_head', [$this, 'hideRatingStyles']);
    }

    /**
     * Hide "Updates" update-core.php menu.
     *
     * @return void
     */
    public function removeMenus(): void
    {
        remove_submenu_page('index.php', 'update-core.php');
    }

    /**
     * Redirects update-core.php to our new "Updates" page.
     *
     * @return void
     */
    public function redirectWPCoreUpdate($screen): void
    {
        if ($screen->base == 'update-core') {
            // Let requests with action=do-plugin-upgrade and action=do-theme-upgrade go through
            if (isset($_GET['action']) && in_array($_GET['action'], ['do-plugin-upgrade', 'do-theme-upgrade'])) {
                return;
            }

            // Otherwise, redirect to our new "Updates" page
            $query_params = [];

            $query_params['page'] = 'unrepress-updater';

            // Preserve force-check parameter if present
            if (isset($_GET['force-check'])) {
                $query_params['force-check'] = $_GET['force-check'];
            }

            wp_redirect(add_query_arg($query_params, admin_url('index.php')));
            exit;
        }
    }

    /**
     * Hides tabs:
     * - Popular / plugin-install.php?tab=popular
     * - Recommended / plugin-install.php?tab=recommended
     * - Favorites / plugin-install.php?tab=favorites
     * From plugin-install.php
     *
     * Until we implement this functionality in UnrePress
     *
     * @return array
     */
    public function hideTabs(array $tabs): array
    {
        unset($tabs['popular']);
        unset($tabs['recommended']);
        unset($tabs['favorites']);

        return $tabs;
    }

    /**
     * Hide specific elements from plugin cards in the WordPress plugin directory
     * Currently hides:
     * - Star ratings
     * - Number of ratings
     * - Active installs count
     * - Popular tags
     *
     * @param object|WP_Error $response Response object or WP_Error.
     * @param string $action The type of information being requested from the Plugin Installation API.
     * @param object $args Plugin API arguments.
     * @return mixed Modified response
     */
    public function hidePluginCardElements($response, string $action, object $args): mixed
    {
        // Handle plugin listings
        if ($action !== 'hot_tags' && !is_wp_error($response) && isset($response->plugins) && is_array($response->plugins)) {
            foreach ($response->plugins as $plugin) {
                // Remove rating related data
                $plugin->rating = 0;
                $plugin->num_ratings = 0;
                $plugin->ratings = [];
                $plugin->active_installs = 0; // Also hide active installs count
            }
        }

        // Handle popular tags request
        if ($action === 'hot_tags' && !is_wp_error($response)) {
            return []; // Return empty array to hide all tags
        }

        return $response;
    }

    /**
     * Add CSS to hide rating elements from plugin cards
     * This serves as a fallback in case filters don't catch everything
     */
    public function hideRatingStyles(): void
    {
        $screen = get_current_screen();

        if (!$screen || $screen->id !== 'plugin-install') {
            return;
        }

        echo '<style>
            .plugin-card .column-rating,
            .plugin-card .column-downloaded,
            .plugin-card .vers.column-rating,
            .plugin-card .num-ratings,
            .plugins-popular-tags-wrapper {
                display: none !important;
            }
        </style>';
    }
}
