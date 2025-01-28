<?php

namespace UnrePress;

class UnrePress
{
    public function __construct() {}

    /**
     * Main entry point for UnrePress.
     *
     * @return void
     */
    public function run(): void
    {
        $egoBlocker = new EgoBlocker();

        // Stop on frontend or rest api
        if (! is_admin() || wp_is_serving_rest_request()) {
            return;
        }

        $adminHider        = new Admin\Hider();
        $adminUpdaterPages = new Admin\UpdaterPages();
        $updateLock        = new Updater\UpdateLock();
        $index             = new Index\Index();
        $indexPlugins      = new Index\PluginsIndex();
        $indexThemes       = new Index\ThemesIndex();
        $updaterPlugins    = new Updater\UpdatePlugins();
        $updaterThemes     = new Updater\UpdateThemes();
        $pluginsDiscovery  = new Discovery\Plugins();
    }

    /**
     * Get our main index.json content. Cached.
     * Returns it as an array.
     *
     * @return array|false The main index content, or false on error.
     */
    public function index(): array|false
    {
        $transient_key = UNREPRESS_PREFIX . 'main_index';
        $cached_index = get_transient($transient_key);

        if (false !== $cached_index) {
            return $cached_index;
        }

        // Get main index first
        $main_index_url = rtrim(UNREPRESS_INDEX, '/') . 'main/index.json';
        $main_index_response = wp_remote_get($main_index_url);

        if (is_wp_error($main_index_response)) {
            return false;
        }

        $main_index = json_decode(wp_remote_retrieve_body($main_index_response), true);

        if ($main_index) {
            set_transient($transient_key, $main_index, 7 * DAY_IN_SECONDS);
        }

        return $main_index;
    }
}
