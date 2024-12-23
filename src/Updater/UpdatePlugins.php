<?php

namespace UnrePress\Updater;

use UnrePress\Debugger;
use UnrePress\Helpers;

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
        add_filter('upgrader_source_selection', [$this, 'maybeFixSourceDir'], 10, 4);
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

        // If we can't get plugin info, skip this plugin
        if (!$remoteData) {
            Debugger::log("UnrePress: Skipping plugin update check for {$slug} - no remote info available");
            return;
        }

        $installedVersion = $this->getInstalledVersion($slug);
        $latestVersion = $this->getRemoteVersion($slug);

        Debugger::log('UnrePress: checking for plugin updates for ' . $slug);
        Debugger::log($remoteData);
        Debugger::log($installedVersion);
        Debugger::log($latestVersion);

        if ($remoteData && $installedVersion && $latestVersion) {
            if (version_compare($installedVersion, $latestVersion, '<')) {
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

    public function requestRemoteInfo($slug)
    {
        // Convert slug to lowercase for URL construction
        $slug = strtolower($slug);
        $url = "https://raw.githubusercontent.com/estebanforge/unrepress-index/main/plugins/" . substr($slug, 0, 1) . "/{$slug}.json";
        Debugger::log("UnrePress: requesting remote info for {$slug} from URL: {$url}");

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            Debugger::log("UnrePress: Error getting plugin info: " . $response->get_error_message());
            return false;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        if ($responseCode !== 200) {
            Debugger::log("UnrePress: Non-200 response getting plugin info: {$responseCode} for URL: {$url}");
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        // Remove any trailing commas before the closing brace or bracket
        $body = preg_replace('/,(\s*[\]}])/m', '$1', $body);

        $data = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Debugger::log("UnrePress: JSON decode error: " . json_last_error_msg());
            Debugger::log("UnrePress: Response body: " . $body);
            return false;
        }

        return $data;
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

        if (! $remote) {
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

        return $response;
    }

    public function hasUpdate($transient)
    {
        // If there's no checked plugins, initialize it
        if (! is_object($transient)) {
            $transient = new \stdClass();
        }

        if (empty($transient->checked)) {
            $transient->checked = [];
            // Get all plugins
            if (! function_exists('get_plugins')) {
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
                    if (! isset($transient->response)) {
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

        if ($remoteVersion === false || !$this->cache_results) {
            $first_letter = mb_strtolower(mb_substr($slug, 0, 1));

            // Get plugin info from UnrePress index
            $remote = wp_remote_get(UNREPRESS_INDEX . 'plugins/' . $first_letter . '/' . $slug . '.json', [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($remote)) {
                Debugger::log('UnrePress: Error getting plugin info: ' . $remote->get_error_message());
                return false;
            }

            if (200 !== wp_remote_retrieve_response_code($remote)) {
                Debugger::log('UnrePress: Non-200 response getting plugin info: ' . wp_remote_retrieve_response_code($remote));
                return false;
            }

            $body = wp_remote_retrieve_body($remote);
            if (empty($body)) {
                Debugger::log('UnrePress: Empty response body getting plugin info');
                return false;
            }

            $pluginInfo = json_decode($body);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Debugger::log('UnrePress: JSON decode error for plugin info: ' . json_last_error_msg());
                Debugger::log('UnrePress: Response body: ' . $body);
                return false;
            }

            // Get tag information from GitHub
            $tagUrl = $pluginInfo->tags ?? '';
            if (empty($tagUrl)) {
                Debugger::log('UnrePress: No tags URL found in plugin info');
                return false;
            }

            Debugger::log('UnrePress: Getting tags from: ' . $tagUrl);

            $remote = wp_remote_get($tagUrl, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($remote)) {
                Debugger::log('UnrePress: Error getting tags: ' . $remote->get_error_message());
                return false;
            }

            if (200 !== wp_remote_retrieve_response_code($remote)) {
                Debugger::log('UnrePress: Non-200 response getting tags: ' . wp_remote_retrieve_response_code($remote));
                return false;
            }

            $tagBody = wp_remote_retrieve_body($remote);
            if (empty($tagBody)) {
                Debugger::log('UnrePress: Empty response body getting tags');
                return false;
            }

            $tags = json_decode($tagBody);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Debugger::log('UnrePress: JSON decode error for tags: ' . json_last_error_msg());
                Debugger::log('UnrePress: Tags response body: ' . $tagBody);
                return false;
            }

            if (!is_array($tags) || empty($tags)) {
                Debugger::log('UnrePress: No tags found');
                return false;
            }

            // Get the newest version from tags
            $latestTag = $this->helpers->getNewestVersionFromTags($tags);
            if (!$latestTag) {
                Debugger::log('UnrePress: No latest tag found');
                return false;
            }

            $remoteVersion = $latestTag->name;
            if (strpos($remoteVersion, 'v') === 0) {
                $remoteVersion = substr($remoteVersion, 1);
            }

            // Store the download URL
            $downloadUrl = $latestTag->zipball_url;
            set_transient($this->cache_key . 'download-url-' . $slug, $downloadUrl, DAY_IN_SECONDS);

            // Store the version
            set_transient($this->cache_key . 'remote-version-' . $slug, $remoteVersion, DAY_IN_SECONDS);

            Debugger::log('UnrePress: Found version ' . $remoteVersion . ' for ' . $slug);
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
     * @return string|WP_Error
     */
    public function maybeFixSourceDir($source, $remote_source, $upgrader, $args)
    {
        global $wp_filesystem;

        if (! is_object($wp_filesystem)) {
            return $source;
        }

        // Check if we're dealing with a plugin update
        if (! isset($args['plugin'])) {
            return $source;
        }

        // Get the desired slug based on the plugin file path
        $plugin_file = $args['plugin'];
        $desired_slug = dirname($plugin_file); // e.g., 'unrepress'

        // Get the current directory name without trailing slash
        $subdir_name = untrailingslashit(str_replace(trailingslashit($remote_source), '', $source));

        if (empty($subdir_name)) {
            return $source;
        }

        // Only rename if the directory name is different from what we want
        if ($subdir_name !== $desired_slug) {
            $from_path = untrailingslashit($source);
            $to_path = trailingslashit($remote_source) . $desired_slug;

            if (true === $wp_filesystem->move($from_path, $to_path)) {
                return trailingslashit($to_path);
            }

            return new \WP_Error(
                'rename_failed',
                sprintf(
                    'The plugin package directory "%s" could not be renamed to match the slug "%s"',
                    $subdir_name,
                    $desired_slug
                ),
                [
                    'found' => $subdir_name,
                    'expected' => $desired_slug,
                ]
            );
        }

        return $source;
    }
}
