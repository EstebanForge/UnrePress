<?php

namespace UnrePress\Updater;

use UnrePress\Helpers;
use UnrePress\Updater\Debugger;
use UnrePress\UnrePress;

class UpdatePlugins
{
    private $helpers;

    private $provider = 'github';

    public $version;

    public $cache_key;

    public $cache_results;

    private $updateInfo = [];

    private UnrePress $unrepress;

    public function __construct()
    {
        $this->helpers = new Helpers();
        $this->unrepress = new UnrePress();
        $this->version = '';
        $this->cache_key = UNREPRESS_PREFIX . 'updates_plugin_';
        $this->cache_results = true;

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
        if (!function_exists('get_plugins')) {
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
            return;
        }

        $installedVersion = $this->getInstalledVersion($slug);
        $latestVersion = $this->getRemoteVersion($slug);

        if ($remoteData && $installedVersion && $latestVersion) {
            if (version_compare($installedVersion, $latestVersion, '<')) {
                $updateInfo = new \stdClass();

                $updateInfo->requires = $remoteData->requires ?? '6.5';
                $updateInfo->tested = $remoteData->tested ?? '6.7';
                $updateInfo->requires_php = $remoteData->requires_php ?? '8.1';
                $updateInfo->name = $remoteData->name;
                $updateInfo->plugin_uri = $remoteData->homepage;
                $updateInfo->description = $remoteData->sections->description;
                $updateInfo->author = $remoteData->author;
                $updateInfo->author_profile = $remoteData->author_url;
                $updateInfo->banner = $remoteData->banners;
                $updateInfo->icon = $remoteData->icons;

                $updateInfo->last_updated = $remoteData->last_updated ?? time();
                $updateInfo->changelog = $remoteData->sections->changelog;

                $updateInfo->version = $latestVersion;
                $updateInfo->download_link = get_transient($this->cache_key . 'download-url-' . $slug);
                $updateInfo->package = get_transient($this->cache_key . 'download-url-' . $slug);

                // Store this information for later use
                $this->updateInfo[$slug] = $updateInfo;
            }
        }
    }

    private function getInstalledVersion($slug)
    {
        if (!function_exists('get_plugins')) {
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
        $url = UNREPRESS_INDEX . 'main/plugins/' . substr($slug, 0, 1) . "/{$slug}.json";

        unrepress_debug('Requesting plugin info from URL: ' . $url);

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            unrepress_debug('Error getting remote info for ' . $slug . ': ' . $response->get_error_message());
            return false;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        unrepress_debug('Response code for ' . $slug . ': ' . $responseCode);

        if ($responseCode !== 200) {
            unrepress_debug('Invalid response code for ' . $slug);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        unrepress_debug('Raw response body for ' . $slug . ':');
        unrepress_debug($body);

        // Remove any trailing commas before the closing brace or bracket
        $body = preg_replace('/,(\s*[\]}])/m', '$1', $body);

        $data = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            unrepress_debug('JSON decode error for ' . $slug . ': ' . json_last_error_msg());
            return false;
        }

        unrepress_debug('Decoded plugin data for ' . $slug . ':');
        unrepress_debug(print_r($data, true));

        return $data;
    }

    public function getInformation($response, $action, $args)
    {
        unrepress_debug('Plugin information request - Action: ' . $action);
        if (!empty($args->slug)) {
            unrepress_debug('Plugin information request for slug: ' . $args->slug);
        }

        // Handle both plugin_information and query_plugins actions
        if ($action !== 'plugin_information' && $action !== 'query_plugins') {
            return $response;
        }

        // For query_plugins action, handle search or featured plugins
        if ($action === 'query_plugins') {
            // Get main index
            $main_index = $this->unrepress->index();

            if (!$main_index || !isset($main_index['plugins']['featured'])) {
                return $response;
            }

            // If search term is provided, search plugins
            if (!empty($args->search)) {
                $term = sanitize_text_field($args->search);
                $plugins_data = $this->searchPlugins($term);
            } else {
                // Otherwise show featured plugins
                $transient_key = UNREPRESS_PREFIX . 'discovery_featured_plugins';
                $featured_response = get_transient($transient_key);

                if (false === $featured_response) {
                    $featured_response = wp_remote_get($main_index['plugins']['featured']);
                    if (!is_wp_error($featured_response)) {
                        set_transient($transient_key, $featured_response, 6 * HOUR_IN_SECONDS);
                    }
                }

                if (!is_wp_error($featured_response)) {
                    $featured_plugins = json_decode(wp_remote_retrieve_body($featured_response), true);
                    if (is_array($featured_plugins) && isset($featured_plugins['featured'])) {
                        $plugins_data = array_map([$this, 'getPluginData'], $featured_plugins['featured']);
                        // Filter out any empty results
                        $plugins_data = array_filter($plugins_data);
                    }
                }
            }

            return (object) [
                'info' => [
                    'page'    => 1,
                    'pages'   => 1,
                    'results' => count($plugins_data ?? []),
                ],
                'plugins' => $plugins_data ?? [],
            ];
        }

        // For plugin_information action, handle single plugin info
        if (empty($args->slug)) {
            return $response;
        }

        // get updates
        $remote = $this->requestRemoteInfo($args->slug);

        if (!$remote) {
            unrepress_debug('No remote data found for ' . $args->slug);
            return $response;
        }

        $response = new \stdClass();

        // Get the latest version from tags
        $version = $this->getLatestVersion($remote);

        // Get the download URL
        $download_url = $this->getDownloadUrl($remote, $version);

        unrepress_debug('Version for ' . $args->slug . ': ' . $version);
        unrepress_debug('Download URL for ' . $args->slug . ': ' . $download_url);

        // Last updated, now
        $remote->last_updated = time();
        // Changelog
        $remote->sections ??= new \stdClass();
        $remote->sections->changelog = $remote->changelog ?? '';

        $response->name = $remote->name ?? '';
        $response->slug = $remote->slug ?? '';
        $response->version = $version;
        $response->author = sprintf('<a href="%s">%s</a>', $remote->author_url ?? '#', $remote->author ?? '');
        $response->author_profile = $remote->author_url ?? '';
        $response->requires = $remote->requires ?? '5.0';
        $response->tested = $remote->tested ?? '6.4';
        $response->requires_php = $remote->requires_php ?? '7.4';
        $response->homepage = $remote->homepage ?? '';
        $response->sections = [
            'description' => $remote->sections->description ?? '',
            'installation' => $remote->sections->installation ?? '',
            'changelog' => $remote->sections->changelog ?? '',
        ];
        $response->banners = [
            'low' => (!empty($remote->banners->low)) ? $remote->banners->low : UNREPRESS_PLUGIN_URL . 'assets/images/banner-772x250.webp',
            'high' => (!empty($remote->banners->high)) ? $remote->banners->high : UNREPRESS_PLUGIN_URL . 'assets/images/banner-1544x500.webp',
        ];
        $response->icons = [
            'default' => (!empty($remote->icons->high)) ? $remote->icons->high : UNREPRESS_PLUGIN_URL . 'assets/images/icon-256.webp',
            'low' => (!empty($remote->icons->low)) ? $remote->icons->low : UNREPRESS_PLUGIN_URL . 'assets/images/icon-256.webp',
            'high' => (!empty($remote->icons->high)) ? $remote->icons->high : UNREPRESS_PLUGIN_URL . 'assets/images/icon-1024.webp',
        ];
        $response->download_link = $download_url;
        $response->package = $download_url;
        $response->trunk = $download_url;
        $response->last_updated = date('Y-m-d H:i:s', $remote->last_updated);
        $response->rating = 100;
        $response->num_ratings = 1;
        $response->active_installs = 1000;
        $response->downloaded = 1000;
        $response->external = true;

        // Set compatibility information
        $wp_version = get_bloginfo('version');
        $response->compatibility = [
            $wp_version => [
                'compatible' => true,
                'requires_php' => $remote->requires_php ?? '7.4',
            ]
        ];

        unrepress_debug('Processed plugin information for ' . $args->slug . ':');
        unrepress_debug(print_r($response, true));

        return $response;
    }

    protected function getLatestVersion($plugin_data)
    {
        unrepress_debug('Getting latest version for ' . json_encode($plugin_data));
        unrepress_debug('Data type received: ' . gettype($plugin_data));

        // Check if we have a cached version first
        $transient_key = $this->cache_key . 'latest_tag_' . $plugin_data->slug;
        $cached_tag = get_transient($transient_key);

        if ($cached_tag !== false) {
            unrepress_debug('Using cached tag for ' . $plugin_data->slug . ': ' . $cached_tag);
            return $cached_tag;
        }

        if (!empty($plugin_data->unrepress_meta->tags)) {
            $tags_url = $this->helpers->normalizeTagUrl($plugin_data->unrepress_meta->tags);

            $response = wp_remote_get($tags_url);

            unrepress_debug('Tags URL: ' . $tags_url);
            unrepress_debug('Tags response: ' . print_r($response, true));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $tags = json_decode(wp_remote_retrieve_body($response));

                unrepress_debug('Tags: ' . print_r($tags, true));

                if (is_array($tags) && !empty($tags)) {
                    $latest_tag = ltrim($tags[0]->name, 'v');

                    unrepress_debug('Latest tag: ' . $latest_tag);

                    // Cache the tag for 3 hours
                    set_transient($transient_key, $latest_tag, 3 * HOUR_IN_SECONDS);

                    // Get the first tag (most recent)
                    return $latest_tag;
                }
            }
        }

        // Fallback version if no tags found
        return '1.0.0';
    }

    private function getDownloadUrl($plugin_data, $version)
    {
        if (!empty($plugin_data->unrepress_meta->repository)) {
            $repo = $plugin_data->unrepress_meta->repository;
            if (strpos($repo, 'github.com') !== false) {
                // Extract owner and repo name from the GitHub URL
                $parts = explode('/', rtrim($repo, '/'));
                $owner = $parts[count($parts) - 2] ?? '';
                $repo_name = $parts[count($parts) - 1] ?? '';

                if ($owner && $repo_name) {
                    // Use the archive download URL format
                    return "https://api.github.com/repos/{$owner}/{$repo_name}/zipball/{$version}";
                }
            }
        }

        return '';
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
        $this->helpers->cleanAfterUpdate($upgrader, $options, $this->cache_key);
    }

    /**
     * Get the latest available version from the remote tags.
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
            $remote = wp_remote_get(UNREPRESS_INDEX . 'main/plugins/' . $first_letter . '/' . $slug . '.json', [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($remote)) {
                return false;
            }

            if (200 !== wp_remote_retrieve_response_code($remote)) {
                return false;
            }

            $body = wp_remote_retrieve_body($remote);
            if (empty($body)) {
                return false;
            }

            $pluginInfo = json_decode($body);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }

            // Get tag information from GitHub
            $tagUrl = $pluginInfo->unrepress_meta->tags ?? '';
            if (empty($tagUrl)) {
                return false;
            }

            $remote = wp_remote_get($tagUrl, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($remote)) {
                return false;
            }

            if (200 !== wp_remote_retrieve_response_code($remote)) {
                return false;
            }

            $tagBody = wp_remote_retrieve_body($remote);
            if (empty($tagBody)) {
                return false;
            }

            $tags = json_decode($tagBody);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }

            if (!is_array($tags) || empty($tags)) {
                return false;
            }

            // Get the newest version from tags
            $latestTag = $this->helpers->getNewestVersionFromTags($tags);
            if (!$latestTag) {
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
        }

        return $remoteVersion;
    }

    /**
     * Fix the source directory name during plugin updates
     * This prevents GitHub's repository naming format from being used.
     *
     * @param string       $source        File source location
     * @param string       $remote_source Remote file source location
     * @param WP_Upgrader $upgrader      WordPress Upgrader instance
     * @param array       $args          Extra arguments passed to hooked filters
     * @return string|WP_Error
     */
    public function maybeFixSourceDir($source, $remote_source, $upgrader, $args)
    {
        if (!isset($args['plugin'])) {
            return $source;
        }

        return $this->helpers->fixSourceDir($source, $remote_source, $args['plugin'], 'plugin');
    }

    /**
     * Get plugin data from UnrePress index.
     *
     * @param string $plugin_slug Plugin slug
     * @return array Plugin data
     */
    private function getPluginData($plugin_slug)
    {
        if (empty($plugin_slug)) {
            return [];
        }

        unrepress_debug('Getting plugin data for ' . $plugin_slug);

        $plugin_data = $this->requestRemoteInfo($plugin_slug);

        if (!$plugin_data) {
            unrepress_debug('Invalid plugin data for ' . $plugin_slug);
            return [];
        }

        unrepress_debug('Raw plugin data for ' . $plugin_slug . ':');
        unrepress_debug($plugin_data);

        // Get the latest version from tags
        $version = $this->getLatestVersion($plugin_data);

        // Get server's WordPress and PHP versions for compatibility
        $wp_version = get_bloginfo('version');
        $php_version = phpversion();

        unrepress_debug('Remote version for ' . $plugin_slug . ' is ' . $version);
        unrepress_debug('Server WordPress version is ' . $wp_version);
        unrepress_debug('Server PHP version is ' . $php_version);

        // Prepare the plugin data array with all required fields
        $processed_data = [
            'name' => $plugin_data->name ?? '',
            'slug' => $plugin_data->slug ?? '',
            'version' => $version,
            'author' => $plugin_data->author ?? '',
            'author_profile' => $plugin_data->author_url ?? '',
            'requires' => $plugin_data->requires ?? $wp_version,
            'tested' => $plugin_data->tested ?? $wp_version,
            'requires_php' => $plugin_data->requires_php ?? $php_version,
            'sections' => [
                'description' => $plugin_data->sections->description ?? '',
                'installation' => $plugin_data->sections->installation ?? '',
                'changelog' => $plugin_data->sections->changelog ?? '',
            ],
            'banners' => [
                'low' => (!empty($plugin_data->banners->low)) ? $plugin_data->banners->low : UNREPRESS_PLUGIN_URL . 'assets/images/banner-772x250.webp',
                'high' => (!empty($plugin_data->banners->high)) ? $plugin_data->banners->high : UNREPRESS_PLUGIN_URL . 'assets/images/banner-1544x500.webp',
            ],
            'icons' => [
                'default' => (!empty($plugin_data->icons->high)) ? $plugin_data->icons->high : UNREPRESS_PLUGIN_URL . 'assets/images/icon-256.webp',
                'low' => (!empty($plugin_data->icons->low)) ? $plugin_data->icons->low : UNREPRESS_PLUGIN_URL . 'assets/images/icon-256.webp',
                'high' => (!empty($plugin_data->icons->high)) ? $plugin_data->icons->high : UNREPRESS_PLUGIN_URL . 'assets/images/icon-1024.webp',
            ],
            'download_url' => $this->getDownloadUrl($plugin_data, $version),
            'homepage' => $plugin_data->homepage ?? '',
            'short_description' => substr($plugin_data->sections->description ?? '', 0, 150) . '&hellip;',
            'rating' => 100,
            'num_ratings' => 1,
            'support_threads' => 0,
            'support_threads_resolved' => 0,
            'active_installs' => 1000,
            'last_updated' => time(),
            'added' => date('Y-m-d'),
            'tags' => [],
            'compatibility' => [
                get_bloginfo('version') => [
                    'compatible' => true,
                    'requires_php' => $plugin_data->requires_php ?? '7.4',
                ]
            ],
            'contributors' => [],
            'screenshots' => [],
            'external' => true
        ];

        unrepress_debug('Processed plugin data for ' . $plugin_slug . ':');
        unrepress_debug($processed_data);

        return $processed_data;
    }

    /**
     * Search for plugins in the UnrePress index.
     *
     * @param string $term The search term
     * @return array Array of plugin data
     */
    private function searchPlugins($term)
    {
        unrepress_debug('Searching for plugins with term: ' . $term);

        // Get plugins index
        $transient_key = UNREPRESS_PREFIX . 'plugins_index';
        $plugins_index = get_transient($transient_key);

        if (false === $plugins_index) {
            unrepress_debug('No cached plugins index found, fetching from remote');

            // Get main index first to get the plugins index URL
            $main_index = $this->unrepress->index();

            if (!$main_index || !isset($main_index['plugins']['index'])) {
                unrepress_debug('Main index is empty or missing plugins index URL');
                return [];
            }

            $index_url = $main_index['plugins']['index'];
            unrepress_debug('Fetching plugins index from: ' . $index_url);

            $response = wp_remote_get($index_url);

            if (is_wp_error($response)) {
                unrepress_debug('Failed to fetch plugins index: ' . $response->get_error_message());
                return [];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                unrepress_debug('Invalid response code from plugins index: ' . $response_code);
                return [];
            }

            $plugins_index = json_decode($response_body, true);
            if (!$plugins_index || !isset($plugins_index['plugins'])) {
                unrepress_debug('Invalid plugins index format');
                return [];
            }

            set_transient($transient_key, $plugins_index, 3 * HOUR_IN_SECONDS);
            unrepress_debug('Cached plugins index for 3 hours');
        } else {
            unrepress_debug('Using cached plugins index');
        }

        unrepress_debug('Searching through ' . count($plugins_index['plugins']) . ' plugins');

        // Convert search term to lowercase for case-insensitive search
        $term = strtolower($term);
        $matching_plugins = [];

        foreach ($plugins_index['plugins'] as $plugin) {
            // Search in name, description, and tags
            $searchable_text = strtolower(
                $plugin['name'] . ' ' .
                $plugin['description'] . ' ' .
                implode(' ', $plugin['tags'])
            );

            if (strpos($searchable_text, $term) !== false) {
                unrepress_debug('Found matching plugin: ' . $plugin['name']);
                $matching_plugins[] = $plugin['slug'];
            }
        }

        unrepress_debug('Found ' . count($matching_plugins) . ' matching plugins');

        if (empty($matching_plugins)) {
            return [];
        }

        // Get full plugin data for each matching plugin
        $plugins_data = array_map([$this, 'getPluginData'], $matching_plugins);
        $filtered_plugins = array_filter($plugins_data);

        unrepress_debug('After processing: ' . count($filtered_plugins) . ' valid plugins');

        return $filtered_plugins;
    }
}
