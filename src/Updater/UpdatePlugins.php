<?php

namespace UnrePress\Updater;

use UnrePress\Helpers;
use UnrePress\Debugger;

class UpdatePlugins
{
    private $helpers;

    private $provider = 'github';

    public $version;

    public $cache_key;

    public $cache_results;

    private $updateInfo = [];

    public function __construct()
    {
        $this->helpers = new Helpers();
        $this->version = '';
        $this->cache_key = UNREPRESS_PREFIX . 'updates_plugin_';
        $this->cache_results = true;

        // If force-check=1 and page=unrepress-updates, then empty all update transients
        if (isset($_GET['force-check']) && $_GET['force-check'] === '1' && isset($_GET['page']) && $_GET['page'] === 'unrepress-updater') {
            $this->deleteAllUpdateTransients();
        }

        $this->checkforUpdates();

        add_filter('plugins_api', [$this, 'getInformation'], 20, 3);
        add_filter('site_transient_update_plugins', [$this, 'hasUpdate']);
        add_action('upgrader_process_complete', [$this, 'cleanAfterUpdate'], 10, 2);
        // Add filter to handle GitHub folder renaming
        add_filter('upgrader_source_selection', [$this, 'fixSourceDir'], 10, 4);
    }

    /**
     * Check for plugin updates
     * This metod will check for updates on every installed plugin.
     *
     * @return void
     */
    private function checkforUpdates()
    {
        // Get all installed plugins
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();

        foreach ($plugins as $key => $plugin) {
            // Check for update, by slug
            // Re-create the slug from the file path. Like WP does: $plugin_slug = dirname( plugin_basename( $plugin_file ) );
            $slug = basename(dirname($key));

            $this->checkForPluginUpdate($slug);
        }
    }

    private function checkForPluginUpdate($slug)
    {
        $remoteData = $this->requestRemoteInfo($slug);
        $installedVersion = $this->getInstalledVersion($slug);
        $latestVersion = $this->getRemoteVersion($slug);

        //Debugger::log(sprintf('UnrePress: Checking for updates for %s. Installed version: %s. Latest version: %s', $slug, $installedVersion, $latestVersion));

        if ($remoteData && $installedVersion && $latestVersion) {
            if (version_compare($installedVersion, $latestVersion, '<')) {
                //Debugger::log('UnrePress: setting update info for ' . $slug);

                $updateInfo = new \stdClass();

                $updateInfo->requires = $remoteData->requires;
                $updateInfo->tested = $remoteData->tested;
                $updateInfo->requires_php = $remoteData->requires_php;
                $updateInfo->name = $remoteData->name;
                $updateInfo->plugin_uri = $remoteData->homepage;
                $updateInfo->description = $remoteData->sections->description;
                $updateInfo->author = $remoteData->author;
                $updateInfo->author_uri = $remoteData->author_profile;
                $updateInfo->banner = $remoteData->banners;

                $updateInfo->last_updated = $remoteData->last_updated;
                $updateInfo->changelog = $remoteData->sections->changelog;

                $updateInfo->version = $latestVersion;
                $updateInfo->download_link = get_transient($this->cache_key . 'download-url-' . $slug);

                // Store this information for later use
                $this->updateInfo[$slug] = $updateInfo;
            }
        }
    }

    private function getInstalledVersion($slug)
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();

        foreach ($plugins as $plugin_file => $plugin_data) {
            if (strpos($plugin_file, $slug . '/') === 0) {
                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file, false, false);

                return $plugin_data['Version'];
            }
        }

        return false;
    }

    public function requestRemoteInfo($slug = null)
    {
        if (! $slug) {
            return false;
        }

        $remote = get_transient($this->cache_key . $slug);

        // Get the first letter of the slug
        $first_letter = mb_strtolower(mb_substr($slug, 0, 1));

        if ($remote === false || ! $this->cache_results) {

            $remote = wp_remote_get(
                UNREPRESS_INDEX . 'plugins/' . $first_letter . '/' . $slug . '.json',
                [
                    'timeout' => 10,
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]
            );

            if (is_wp_error($remote) || 200 !== wp_remote_retrieve_response_code($remote) || empty(wp_remote_retrieve_body($remote))) {
                return false;
            }

            set_transient($this->cache_key . $slug, $remote, DAY_IN_SECONDS);
        }

        $remote = json_decode(wp_remote_retrieve_body($remote));

        return $remote;
    }

    public function getInformation($response, $action, $args)
    {
        // do nothing if you're not getting plugin information right now
        if ($action !== 'plugin_information') {
            return $response;
        }

        if (empty($args->slug)) {
            return $response;
        }

        // get updates
        $remote = $this->requestRemoteInfo($args->slug);

        if (!$remote) {
            return $response;
        }

        $response = new \stdClass();

        // Last updated, now
        $remote->last_updated = time();
        // Changelog
        $remote->sections->changelog = $remote->changelog;

        $response->name = $remote->name;
        $response->slug = $remote->slug;
        $response->version = $remote->version;
        $response->tested = $remote->tested;
        $response->requires = $remote->requires;
        $response->author = $remote->author;
        $response->author_profile = $remote->author_profile;
        $response->donate_link = $remote->donate_link;
        $response->homepage = $remote->homepage;
        $response->download_link = $remote->download_url;
        $response->trunk = $remote->download_url;
        $response->requires_php = $remote->requires_php;
        $response->last_updated = $remote->last_updated;

        $response->sections = [
            'description' => $remote->sections->description,
            'installation' => $remote->sections->installation,
            'changelog' => $remote->sections->changelog,
        ];

        if (! empty($remote->banners)) {
            $response->banners = [
                'low' => $remote->banners->low,
                'high' => $remote->banners->high,
            ];
        }

        //Debugger::log(sprintf('UnrePress: getInformation for %s', $args->slug));
        //Debugger::log(print_r($response, true));

        return $response;
    }

    public function hasUpdate($transient)
    {
        // If there's no checked plugins, initialize it
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }

        if (empty($transient->checked)) {
            $transient->checked = [];
            // Get all plugins
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugins = get_plugins();
            foreach ($plugins as $plugin_file => $plugin_data) {
                $transient->checked[$plugin_file] = $plugin_data['Version'];
            }
            // Now that we've populated checked, run our update check
            $this->checkforUpdates();
        }

        foreach ($transient->checked as $plugin => $version) {
            $slug = dirname($plugin);
            if (isset($this->updateInfo[$slug])) {
                $updateInfo = $this->updateInfo[$slug];
                if (version_compare($version, $updateInfo->version, '<')) {
                    $response = new \stdClass();
                    $response->slug = $slug;
                    $response->plugin = $plugin;
                    $response->new_version = $updateInfo->version;
                    $response->tested = $updateInfo->tested;
                    $response->package = $updateInfo->download_link;
                    if (!isset($transient->response)) {
                        $transient->response = [];
                    }
                    $transient->response[$plugin] = $response;
                }
            }
        }

        return $transient;
    }

    public function cleanAfterUpdate($upgrader, $options)
    {
        if ($this->cache_results && $options['action'] === 'update' && $options['type'] === 'plugin') {
            // Get the updated plugin slug
            $slug = $options['plugins'][0];

            Debugger::log(sprintf('UnrePress: cleanAfterUpdate for %s', $slug));

            // Clean the cache for this plugin
            delete_transient($this->cache_key . $slug);
            delete_transient($this->cache_key . 'remote-version-' . $slug);
            delete_transient($this->cache_key . 'download-url-' . $slug);
        }
    }

    /**
     * Get the latest available version from the remote tags
     *
     * @param string $slug Plugin slug
     *
     * @return string|false Version string or false on failure
     */
    private function getRemoteVersion($slug)
    {
        $remoteVersion = get_transient($this->cache_key . 'remote-version-' . $slug);

        if ($remoteVersion === false) {
            $first_letter = mb_strtolower(mb_substr($slug, 0, 1));

            // Get plugin info from UnrePress index
            $remote = wp_remote_get(UNREPRESS_INDEX . 'plugins/' . $first_letter . '/' . $slug . '.json', [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($remote)) {
                //Debugger::log(sprintf('UnrePress: Error fetching plugin info for %s: %s', $slug, $remote->get_error_message()));
                return false;
            }

            if (200 !== wp_remote_retrieve_response_code($remote)) {
                //Debugger::log(sprintf('UnrePress: Invalid response code %d when fetching plugin info for %s', wp_remote_retrieve_response_code($remote), $slug));
                return false;
            }

            if (empty(wp_remote_retrieve_body($remote))) {
                //Debugger::log(sprintf('UnrePress: Empty response body when fetching plugin info for %s', $slug));
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($remote));

            if (is_wp_error($body)) {
                //Debugger::log(sprintf('UnrePress: Error decoding plugin info JSON for %s: %s', $slug, $body->get_error_message()));
                return false;
            }

            $tagUrl = $body->tags ?? '';

            if (empty($tagUrl)) {
                //Debugger::log(sprintf('UnrePress: No tags URL found for plugin %s', $slug));
                return false;
            }

            // Get tag information
            $remote = wp_remote_get($tagUrl, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($remote)) {
                //Debugger::log(sprintf('UnrePress: Error fetching tag info for %s: %s', $slug, $remote->get_error_message()));
                return false;
            }

            if (200 !== wp_remote_retrieve_response_code($remote)) {
                //Debugger::log(sprintf('UnrePress: Invalid response code %d when fetching tag info for %s', wp_remote_retrieve_response_code($remote), $slug));
                return false;
            }

            if (empty(wp_remote_retrieve_body($remote))) {
                //Debugger::log(sprintf('UnrePress: Empty response body when fetching tag info for %s', $slug));
                return false;
            }

            $tagBody = json_decode(wp_remote_retrieve_body($remote));

            if (! is_array($tagBody) || empty($tagBody)) {
                //Debugger::log(sprintf('UnrePress: Invalid or empty tags array for plugin %s', $slug));
                return false;
            }

            // Get the newest version from tags
            $latestTag = $tagBody[0];
            $remoteVersion = $latestTag->name;
            $remoteZip = $latestTag->zipball_url;

            // Store version and download information
            if ($remoteVersion) {
                // Clean version number (remove 'v' prefix if present)
                $remoteVersion = ltrim($remoteVersion, 'v');

                set_transient($this->cache_key . 'download-url-' . $slug, $remoteZip, DAY_IN_SECONDS);
                set_transient($this->cache_key . 'remote-version-' . $slug, $remoteVersion, DAY_IN_SECONDS);

                // Log
                Debugger::log(sprintf('UnrePress: Found version %s for plugin %s', $remoteVersion, $slug));
            } else {
                //Debugger::log(sprintf('UnrePress: No version found in latest tag for plugin %s', $slug));
                return false;
            }
        }

        return $remoteVersion;
    }

    /**
     * Deletes all transients used by the updates API.
     *
     * All transients used by the updates API have a name that begins with
     * UNREPRESS_PREFIX . 'updates_plugin'. This method will delete all
     * transients with names that match this pattern.
     *
     * @since 1.0.0
     */
    private function deleteAllUpdateTransients()
    {
        global $wpdb;

        Debugger::log('UnrePress: deleting all update transients');

        // Delete both transients and their timeout entries
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
                OR option_name LIKE %s",
                '_transient_' . $this->cache_key . '%',
                '_transient_timeout_' . $this->cache_key . '%'
            )
        );
    }

    /**
     * Fix the source directory name during plugin updates
     * This prevents GitHub's repository naming format from being used
     *
     * @param string       $source        File source location
     * @param string       $remote_source Remote file source location
     * @param WP_Upgrader $upgrader      WordPress Upgrader instance
     * @param array       $args          Extra arguments passed to hooked filters
     * @return string
     */
    public function fixSourceDir($source, $remote_source, $upgrader, $args)
    {
        if (!is_object($upgrader->skin)) {
            return $source;
        }

        // Check if we're dealing with a plugin update
        if (!isset($args['plugin'])) {
            return $source;
        }

        // Get the plugin slug from the plugin file path (e.g., 'my-plugin/my-plugin.php' -> 'my-plugin')
        $plugin_slug = dirname($args['plugin']);

        // Get the current plugin directory name
        $current_dir = basename($source);

        // If it's already the correct name, return
        if ($current_dir === $plugin_slug) {
            return $source;
        }

        // Get parent directory
        $parent_dir = dirname($source);
        $new_source = $parent_dir . '/' . $plugin_slug;

        // If target exists, remove it first
        if (is_dir($new_source)) {
            $this->helpers->removeDirectoryWPFS($new_source);
        }

        // Try to rename using WP_Filesystem
        if ($this->helpers->renameDirectoryWPFS($source, $new_source)) {
            Debugger::log('Plugin Update: Successfully renamed update directory from ' . $current_dir . ' to ' . $plugin_slug);
            return $new_source;
        }

        Debugger::log('Plugin Update: Failed to rename update directory from ' . $current_dir . ' to ' . $plugin_slug);
        return $source;
    }
}
