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
        add_filter('install_plugins_tabs', [$this, 'hideTabs'], 10, 1);
        add_filter('plugins_api_result', [$this, 'hidePluginCardElements'], 10, 3);
        add_action('admin_head', [$this, 'applyAdminHeadModifications']);
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
            foreach ($response->plugins as &$plugin) {
                // Convert to object if it's an array
                if (is_array($plugin)) {
                    $plugin = (object) $plugin;
                }

                // Remove rating related data
                $plugin->rating = 0;
                $plugin->num_ratings = 0;
                $plugin->ratings = [];
                $plugin->active_installs = 0; // Also hide active installs count
            }
        }

        // Handle single plugin info
        if ($action === 'plugin_information' && !is_wp_error($response)) {
            if (is_array($response)) {
                $response = (object) $response;
            }

            $response->rating = 0;
            $response->num_ratings = 0;
            $response->ratings = [];
            $response->active_installs = 0;
        }

        // Handle popular tags request
        if ($action === 'hot_tags' && !is_wp_error($response)) {
            return []; // Return empty array to hide all tags
        }

        return $response;
    }

    /**
     * Add CSS to hide rating elements from plugin cards and manage theme tabs.
     * This serves as a fallback in case filters don't catch everything.
     */
    public function applyAdminHeadModifications(): void
    {
        $screen = get_current_screen();

        // Hide plugin card elements
        if ($screen && $screen->id === 'plugin-install') {
            ?>
            <style>
                .plugin-card .column-rating,
                .plugin-card .column-downloaded,
                .plugin-card .vers.column-rating,
                .plugin-card .num-ratings,
                .plugins-popular-tags-wrapper {
                    display: none !important;
                }
            </style>
            <?php
        }

        // Modify theme install page tabs
        if ($screen && $screen->id === 'theme-install') {
            // CSS to hide unwanted tabs
            ?>
            <style>
                .wp-filter .filter-links li a[data-sort="new"],
                .wp-filter .filter-links li a[data-sort="latest"],
                .wp-filter .filter-links li a[data-sort="block-themes"],
                .wp-filter .filter-links li a[data-sort="favorites"] {
                    display: none !important;
                }
            </style>
            <?php

            // JavaScript to rename "Popular" to "Featured"
            $featured_text = esc_js(_x('Featured', 'themes'));
            ?>
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    var popularTabLink = document.querySelector('.wp-filter .filter-links li a[data-sort="popular"]');
                    if (popularTabLink) {
                        popularTabLink.textContent = '<?php echo $featured_text; ?>';
                        // unrepress_debug is a PHP function, cannot be called directly in JS
                        // console.log("UnrePress Hider: Renamed Popular tab to Featured via vanilla JS");
                    } else {
                        // console.log("UnrePress Hider: Popular tab link not found for vanilla JS rename");
                    }
                });
            </script>
            <?php
        }
    }
}
