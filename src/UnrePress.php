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
        $updaterCore = new Updater\UpdateCore();

        // Hook into WordPress native cron-based core update checks
        add_action('wp_version_check', [$updaterCore, 'checkCoreUpdatesFromGitHub'], 5);
    }



    /**
     * Get our main index.json content. Cached.
     * Returns it as an array.
     *
     * @return array|false The main index content, or false on error.
     */
    public function index(): array|false
    {
        unrepress_debug('UnrePress::index() called');

        $transient_key = UNREPRESS_PREFIX . 'main_index';
        $cachedIndex = get_transient($transient_key);

        if (false !== $cachedIndex) {
            unrepress_debug('UnrePress::index() - Returning cached index: ' . print_r($cachedIndex, true));
            return $cachedIndex;
        }

        unrepress_debug('UnrePress::index() - No cached index found, fetching from remote');

        // Get main index first
        $mainIndexUrl = UNREPRESS_INDEX . 'main/index.json';
        unrepress_debug('UnrePress::index() - Main index URL: ' . $mainIndexUrl);

        $mainIndexResponse = wp_remote_get($mainIndexUrl);

        if (is_wp_error($mainIndexResponse)) {
            unrepress_debug('UnrePress::index() - WP Error: ' . $mainIndexResponse->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($mainIndexResponse);
        unrepress_debug('UnrePress::index() - Response code: ' . $response_code);

        if ($response_code !== 200) {
            unrepress_debug('UnrePress::index() - Invalid response code: ' . $response_code);
            return false;
        }

        $body = wp_remote_retrieve_body($mainIndexResponse);
        unrepress_debug('UnrePress::index() - Response body: ' . $body);

        $mainIndex = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            unrepress_debug('UnrePress::index() - JSON decode error: ' . json_last_error_msg());
            return false;
        }

        if (empty($mainIndex) || !is_array($mainIndex)) {
            unrepress_debug('UnrePress::index() - Empty or invalid main index');
            return false;
        }

        unrepress_debug('UnrePress::index() - Main index loaded successfully: ' . print_r($mainIndex, true));

        if ($mainIndex) {
            set_transient($transient_key, $mainIndex, 30 * DAY_IN_SECONDS);
            unrepress_debug('UnrePress::index() - Main index cached for 30 days');
        }

        return $mainIndex;
    }
}
