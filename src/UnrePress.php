<?php

namespace UnrePress;

class UnrePress
{

    /**
     * @var string
     */
    protected $mainIndex;

    /**
     * Constructor.
     *
     * @return void
     */
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
        if (!is_admin() || wp_is_serving_rest_request()) {
            return;
        }

        // Get our main index json contents as an array
        $mainIndex = $this->index();

        $adminHider = new Admin\Hider();
        $adminUpdaterPages = new Admin\UpdaterPages();
        $updateLock = new Updater\UpdateLock();
        $index = new Index\Index();
        $indexPlugins = new Index\PluginsIndex();
        $indexThemes = new Index\ThemesIndex();
        $updaterPlugins = new Updater\UpdatePlugins();
        $updaterThemes = new Updater\UpdateThemes();
        $pluginsDiscovery = new Discovery\Plugins();

        unrepress_debug('Classed initialized');
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
        $cachedIndex = get_transient($transient_key);

        if (false !== $cachedIndex) {
            return $cachedIndex;
        }

        // Get main index first
        $mainIndexUrl = UNREPRESS_INDEX . 'main/index.json';
        $mainIndexResponse = wp_remote_get($mainIndexUrl);

        if (is_wp_error($mainIndexResponse)) {
            return false;
        }

        $mainIndex = json_decode(wp_remote_retrieve_body($mainIndexResponse), true);

        if (empty($mainIndex) || !is_array($mainIndex)) {
            return false;
        }

        if ($mainIndex) {
            set_transient($transient_key, $mainIndex, 30 * DAY_IN_SECONDS);
        }

        return $mainIndex;
    }
}
