<?php

namespace UnrePress\Admin;

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
}
