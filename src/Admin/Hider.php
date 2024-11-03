<?php

namespace UnrePress\Admin;

class Hider
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'removeMenus']);
        add_action('admin_head', [$this, 'removeMenus']);
        add_action('current_screen', [$this, 'redirectWPCoreUpdate']);
    }

    /**
     * Hide "Updates" update-core.php menu
     *
     * @return void
     */
    public function removeMenus(): void
    {
        remove_submenu_page('index.php', 'update-core.php');
    }

    /**
     * Redirects update-core.php to our new "Updates" page
     *
     * @return void
     */
    public function redirectWPCoreUpdate($screen): void
    {
        if ($screen->base == 'update-core') {
            wp_redirect(admin_url('index.php?page=unrepress-updater'));
            exit;
        }
    }
}
