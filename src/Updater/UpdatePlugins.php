<?php

namespace UnrePress\Updater;

use UnrePress\Debugger;
use UnrePress\Helpers;
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
        add_filter('upgrader_pre_download', [$this, 'capturePluginSlug'], 10, 3);
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

        if (!$remoteData) {
            // Debugger::log('checkForPluginUpdate: No remote data for slug: ' . $slug);
            return;
        }

        $installedVersion = $this->getInstalledVersion($slug);
        if (!$installedVersion) {
            // Debugger::log('checkForPluginUpdate: Could not get installed version for slug: ' . $slug);
            return;
        }

        $latest_tag_object = $this->getLatestVersion($remoteData);

        if (!is_object($latest_tag_object) || !isset($latest_tag_object->name)) {
            // Debugger::log('checkForPluginUpdate: Could not determine latest tag object or name for slug: ' . $slug);
            return;
        }

        $actual_tag_name_for_url = $latest_tag_object->name;
        $latest_version_string = ltrim($actual_tag_name_for_url, 'v');

        $download_url = $this->getDownloadUrl($remoteData, $actual_tag_name_for_url);

        if (!$download_url) {
            // Debugger::log('checkForPluginUpdate: Could not determine download URL for slug: ' . $slug . ' version: ' . $latest_version_string);
            return;
        }

        if (version_compare($installedVersion, $latest_version_string, '<')) {
            // Debugger::log('checkForPluginUpdate: Update available for ' . $slug . '. Installed: ' . $installedVersion . ', Latest: ' . $latest_version_string);
            $updateInfo = new \stdClass();

            // Populate with data from $remoteData and our determined values
            $updateInfo->slug = $slug; // Ensure slug is set for the update array key
            $updateInfo->name = $remoteData->name ?? $slug;
            $updateInfo->version = $latest_version_string; // This is the new version
            $updateInfo->package = $download_url; // This is the correct download URL
            $updateInfo->download_link = $download_url; // Redundant but often set

            $updateInfo->requires = $remoteData->requires ?? '5.0'; // Default or from remote
            $updateInfo->tested = $remoteData->tested ?? (defined('get_bloginfo') ? get_bloginfo('version') : '0.0');
            $updateInfo->requires_php = $remoteData->requires_php ?? '7.4';

            $updateInfo->plugin_uri = $remoteData->homepage ?? '';

            if (isset($remoteData->sections) && is_object($remoteData->sections)) {
                $updateInfo->description = $remoteData->sections->description ?? '';
                $updateInfo->changelog = $remoteData->sections->changelog ?? '';
            } else {
                $updateInfo->description = $remoteData->description ?? ''; // Fallback for flat description
                $updateInfo->changelog = $remoteData->changelog ?? ''; // Fallback for flat changelog
            }

            $updateInfo->author = $remoteData->author ?? '';
            $updateInfo->author_profile = $remoteData->author_url ?? '';

            // Banners and Icons with fallbacks
            $default_banner_low = UNREPRESS_PLUGIN_URL . 'assets/images/banner-772x250.webp';
            $default_banner_high = UNREPRESS_PLUGIN_URL . 'assets/images/banner-1544x500.webp';
            $default_icon = UNREPRESS_PLUGIN_URL . 'assets/images/icon-256.webp';

            $updateInfo->banners = new \stdClass();
            $updateInfo->banners->low = (!empty($remoteData->banners->low) && is_string($remoteData->banners->low)) ? $remoteData->banners->low : $default_banner_low;
            $updateInfo->banners->high = (!empty($remoteData->banners->high) && is_string($remoteData->banners->high)) ? $remoteData->banners->high : $default_banner_high;

            $updateInfo->icons = new \stdClass(); // WordPress expects icons as an object in some contexts, array in others. API returns array.
                                                // For site_transient_update_plugins, it's less critical but let's be consistent with $api_response in getInformation
            $updateInfo->icons->default = (!empty($remoteData->icons->default) && is_string($remoteData->icons->default)) ? $remoteData->icons->default : $default_icon;
            $updateInfo->icons->low = (!empty($remoteData->icons->low) && is_string($remoteData->icons->low)) ? $remoteData->icons->low : $default_icon;
            $updateInfo->icons->high = (!empty($remoteData->icons->high) && is_string($remoteData->icons->high)) ? $remoteData->icons->high : $default_icon;
            if (!empty($remoteData->icons->svg) && is_string($remoteData->icons->svg)) {
                 $updateInfo->icons->svg = $remoteData->icons->svg;
            }


            $updateInfo->last_updated = !empty($remoteData->last_updated) ? date('Y-m-d H:i:s', is_numeric($remoteData->last_updated) ? $remoteData->last_updated : strtotime($remoteData->last_updated)) : date('Y-m-d H:i:s');

            // Store this information for later use in hasUpdate filter
            $this->updateInfo[$slug] = $updateInfo;
            // Debugger::log('checkForPluginUpdate: Stored update info for ' . $slug . ': ' . print_r($updateInfo, true));
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

        Debugger::log('Requesting plugin info from URL: ' . $url);

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            Debugger::log('Error getting remote info for ' . $slug . ': ' . $response->get_error_message());
            return false;
        }

        $responseCode = wp_remote_retrieve_response_code($response);

        if ($responseCode !== 200) {
            Debugger::log('Invalid response code for ' . $slug . ': ' . $responseCode); // Keep response code here
            return false;
        }

        $body = wp_remote_retrieve_body($response);

        // Remove any trailing commas before the closing brace or bracket
        $body = preg_replace('/,(\s*[\]}])/m', '$1', $body);

        $data = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Debugger::log('JSON decode error for ' . $slug . ': ' . json_last_error_msg());
            return false;
        }

        return $data;
    }

    public function getInformation($response, $action, $args)
    {
        Debugger::log('Plugin information request - Action: ' . $action . (!empty($args->slug) ? ', Slug: ' . $args->slug : ''));

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
                Debugger::log('Search results for ' . $term . ': found ' . count($plugins_data ?? []) . ' items');
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

        $remote = $this->requestRemoteInfo($args->slug); // This is the plugin's data from UnrePress index

        if (!$remote) {
            Debugger::log('getInformation (Plugin): No remote data found for slug: ' . $args->slug);
            return $response; // Return original $response if UnrePress index doesn't have it
        }

        // This now returns the full tag object or false
        $latest_tag_object = $this->getLatestVersion($remote);

        // Extract the actual tag name string for use in URLs
        $actual_tag_name_for_url = (is_object($latest_tag_object) && isset($latest_tag_object->name)) ? $latest_tag_object->name : null;

        // Determine the version string for display (e.g., '1.2.3')
        $display_version = null;
        if ($actual_tag_name_for_url) {
            $display_version = ltrim($actual_tag_name_for_url, 'v');
        } elseif (isset($remote->version) && is_string($remote->version)) {
            $display_version = $remote->version;
        } else {
            $display_version = '0.0.0'; // Default display version
        }

        // Get the download URL using the actual tag name string
        $download_url = $this->getDownloadUrl($remote, $actual_tag_name_for_url);

        Debugger::log('getInformation (Plugin): Slug: ' . $args->slug . ' | Actual Tag: ' . ($actual_tag_name_for_url ?? 'N/A') . ' | Display Version: ' . $display_version . ' | Download URL: ' . ($download_url ?: 'N/A'));

        if (empty($download_url)) {
            Debugger::log('getInformation (Plugin): Could not determine download_url for: ' . $args->slug . '. Returning basic info.');
            // Fallback: Populate a minimal response object if we can't get a download URL
            // This ensures the plugin card might still show some info rather than breaking.
            $api_response = new \stdClass();
            $api_response->name = $remote->name ?? $args->slug;
            $api_response->slug = $args->slug;
            $api_response->version = $display_version;
            $api_response->author = $remote->author ?? '';
            // Add other essential fields like sections->description if you want a richer fallback display
            $api_response->sections = new \stdClass();
            $api_response->sections->description = $remote->sections->description ?? 'Description not available.';
            return $api_response;
        }

        // Construct the full $response object for WordPress plugins_api
        $api_response = new \stdClass();

        $api_response->name = $remote->name ?? $args->slug;
        $api_response->slug = $args->slug;
        $api_response->version = $display_version;
        $api_response->author = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($remote->author_url ?? '#'), esc_html($remote->author ?? 'Unknown Author'));
        $api_response->author_profile = $remote->author_url ?? '';

        $api_response->requires = $remote->requires ?? '5.0';
        $api_response->tested = $remote->tested ?? (defined('get_bloginfo') ? get_bloginfo('version') : '0.0');
        $api_response->requires_php = $remote->requires_php ?? '7.4';

        $api_response->homepage = $remote->homepage ?? '';
        $api_response->last_updated = !empty($remote->last_updated) ? date('Y-m-d H:i:s', is_numeric($remote->last_updated) ? $remote->last_updated : strtotime($remote->last_updated)) : date('Y-m-d H:i:s');

        // Sections
        $api_response->sections = new \stdClass();
        $default_description = 'No description provided.';
        if (isset($remote->sections) && is_object($remote->sections)) {
            $api_response->sections->description = $remote->sections->description ?? $default_description;
            $api_response->sections->installation = $remote->sections->installation ?? '';
            $api_response->sections->changelog = $remote->sections->changelog ?? '';
        } else {
            $api_response->sections->description = $remote->description ?? $default_description; // Fallback for flat description
        }

        // Banners & Icons (with fallbacks to UnrePress assets)
        $default_banner_low = UNREPRESS_PLUGIN_URL . 'assets/images/banner-772x250.webp';
        $default_banner_high = UNREPRESS_PLUGIN_URL . 'assets/images/banner-1544x500.webp';
        $default_icon = UNREPRESS_PLUGIN_URL . 'assets/images/icon-256.webp';

        $api_response->banners = [
            'low' => (!empty($remote->banners->low) && is_string($remote->banners->low)) ? $remote->banners->low : $default_banner_low,
            'high' => (!empty($remote->banners->high) && is_string($remote->banners->high)) ? $remote->banners->high : $default_banner_high,
        ];
        $api_response->icons = [
            'default' => (!empty($remote->icons->default) && is_string($remote->icons->default)) ? $remote->icons->default : $default_icon,
            '1x'      => (!empty($remote->icons->low) && is_string($remote->icons->low)) ? $remote->icons->low : $default_icon, // WordPress uses 1x often
            '2x'      => (!empty($remote->icons->high) && is_string($remote->icons->high)) ? $remote->icons->high : $default_icon, // WordPress uses 2x often
            // Preserving low/high for any other potential uses if absolutely necessary, but WP prefers 1x/2x/svg
            'low'     => (!empty($remote->icons->low) && is_string($remote->icons->low)) ? $remote->icons->low : $default_icon,
            'high'    => (!empty($remote->icons->high) && is_string($remote->icons->high)) ? $remote->icons->high : $default_icon,
        ];
        if(!empty($remote->icons->svg) && is_string($remote->icons->svg)){
            $api_response->icons['svg'] = $remote->icons->svg;
        }

        // Crucial: Download link and package
        $api_response->download_link = $download_url;
        $api_response->package = $download_url;
        $api_response->trunk = $download_url; // Often same as package

        // Other fields WordPress might expect or use for display
        $api_response->rating = $remote->rating ?? 0; // 0-100
        $api_response->num_ratings = $remote->num_ratings ?? 0;
        $api_response->active_installs = $remote->active_installs ?? 0;
        $api_response->downloaded = $remote->downloaded ?? 0;
        $api_response->tags = $remote->tags ?? [];
        if (is_object($api_response->tags)) { $api_response->tags = (array) $api_response->tags; }

        // WordPress compatibility section
        $wp_version_global = defined('get_bloginfo') ? get_bloginfo('version') : '0.0';
        $api_response->compatibility = new \stdClass(); // WordPress expects an object here
        $api_response->compatibility->$wp_version_global = new \stdClass();
        $api_response->compatibility->$wp_version_global->compatible = true; // Assuming compatibility if listed
        $api_response->compatibility->$wp_version_global->requires_php = $api_response->requires_php;
        // Add more versions if compatibility data is available per version.

        $api_response->external = true; // Mark as externally hosted

        // Debugger::log('getInformation (Plugin): Serving plugin_information for ' . $args->slug . ': ' . print_r($api_response, true));
        return $api_response;
    }

    protected function getLatestVersion($plugin_data)
    {
        if (!is_object($plugin_data) || !isset($plugin_data->slug)) {
            Debugger::log('getLatestVersion (Plugin): Invalid plugin_data object or slug missing.');
            return false;
        }

        // Check if we have a cached version first
        $transient_key = $this->cache_key . 'latest_tag_object_' . $plugin_data->slug; // Changed transient name for clarity
        $cached_tag_object = get_transient($transient_key);

        if ($cached_tag_object !== false) {
            Debugger::log('getLatestVersion (Plugin): Using cached tag object for ' . $plugin_data->slug);
            return $cached_tag_object;
        }

        // Ensure unrepress_meta and necessary sub-properties exist
        if (
            !isset($plugin_data->unrepress_meta) || !is_object($plugin_data->unrepress_meta) ||
            empty($plugin_data->unrepress_meta->tags) || !is_string($plugin_data->unrepress_meta->tags) || // Must be a string URL
            !isset($plugin_data->unrepress_meta->update_from) // update_from is checked by calling logic normally
        ) {
            Debugger::log('getLatestVersion (Plugin): unrepress_meta structure invalid, or tags URL missing for plugin: ' . ($plugin_data->slug ?? 'unknown'));
            // Fallback if only version is present in main plugin_data
            if (!empty($plugin_data->version) && is_string($plugin_data->version)) {
                $mock_tag = new \stdClass();
                $mock_tag->name = $plugin_data->version;
                Debugger::log('getLatestVersion (Plugin): Falling back to version from plugin JSON: ' . $plugin_data->version);
                set_transient($transient_key, $mock_tag, 3 * HOUR_IN_SECONDS); // Cache mock tag too
                return $mock_tag;
            }
            return false;
        }

        // Only proceed if update_from is 'tags', otherwise version is determined by release typically
        // However, this function's job is to get the latest from tags API if tags URL is present.
        // The calling function can decide if this version is used based on update_from strategy.

        $tags_url = $this->helpers->normalizeTagUrl($plugin_data->unrepress_meta->tags, $plugin_data->unrepress_meta->repository ?? '');
        Debugger::log('getLatestVersion (Plugin): Fetching tags from: ' . $tags_url . ' for plugin: ' . $plugin_data->slug);

        $response = wp_remote_get($tags_url, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            Debugger::log('getLatestVersion (Plugin): Error fetching tags for plugin: ' . $plugin_data->slug . ' Error: ' . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response)));
            return false;
        }

        $tags_body = json_decode(wp_remote_retrieve_body($response));
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($tags_body) || empty($tags_body)) {
            Debugger::log('getLatestVersion (Plugin): JSON decode error or empty tags array for plugin: ' . $plugin_data->slug . ' Error: ' . json_last_error_msg());
            return false;
        }

        $latestTagObject = $this->helpers->getNewestVersionFromTags($tags_body);

        if (!$latestTagObject || empty($latestTagObject->name)) {
            Debugger::log('getLatestVersion (Plugin): Could not determine newest tag for plugin: ' . $plugin_data->slug);
            return false;
        }

        Debugger::log('getLatestVersion (Plugin): Determined latest tag: ' . ($latestTagObject->name ?? 'N/A') . ' for plugin: ' . $plugin_data->slug);
        set_transient($transient_key, $latestTagObject, 3 * HOUR_IN_SECONDS); // Cache the full tag object
        return $latestTagObject;
    }

    private function getDownloadUrl($plugin_data, $original_tag_name)
    {
        if (empty($original_tag_name) || !is_object($plugin_data) || !isset($plugin_data->unrepress_meta) || !is_object($plugin_data->unrepress_meta)) {
            Debugger::log('getDownloadUrl (Plugin): Missing original_tag_name or invalid plugin_data/unrepress_meta for plugin: ' . ($plugin_data->slug ?? 'unknown'));
            return false;
        }

        $meta = $plugin_data->unrepress_meta;
        $repo_url = $meta->repository ?? '';
        $update_from = $meta->update_from ?? 'tags'; // Default to tags
        $download_url = false;

        if (empty($repo_url) || !is_string($repo_url)) {
            Debugger::log('getDownloadUrl (Plugin): Repository URL missing or not a string in unrepress_meta for plugin: ' . ($plugin_data->slug ?? 'unknown'));
            return false;
        }

        if ($update_from === 'release') {
            if (!empty($meta->release_asset) && is_string($meta->release_asset)){
                // The $original_tag_name is the release tag (e.g., "v1.2.3" or "1.2.3")
                $normalized_version_for_asset = ltrim($original_tag_name, 'v');
                $asset_name = str_replace('{version}', $normalized_version_for_asset, $meta->release_asset);
                $asset_name = str_replace('{slug}', $plugin_data->slug, $asset_name);

                if (strpos($repo_url, 'github.com') !== false) {
                    $download_url = rtrim($repo_url, '/') . '/releases/download/' . $original_tag_name . '/' . $asset_name;
                    Debugger::log('getDownloadUrl (Plugin): Constructed GitHub release asset URL: ' . $download_url);
                } else {
                    Debugger::log('getDownloadUrl (Plugin): Release asset downloads for non-GitHub providers not fully implemented. Repo: ' . $repo_url);
                    return !empty($meta->download_url) && is_string($meta->download_url) ? $meta->download_url : false;
                }
            } else {
                Debugger::log('getDownloadUrl (Plugin): update_from is \'release\' but release_asset is missing or invalid in unrepress_meta for plugin: ' . ($plugin_data->slug ?? 'unknown'));
                return false;
            }
        } elseif ($update_from === 'tags') {
            $provider = 'other';
            if (strpos($repo_url, 'github.com') !== false) {
                $provider = 'github';
            } // Add elif for gitlab, bitbucket etc. if needed

            $download_url = $this->helpers->getDownloadUrlForProviderTag($repo_url, $original_tag_name, $plugin_data->slug, $provider);
            Debugger::log('getDownloadUrl (Plugin): URL from getDownloadUrlForProviderTag (tags strategy): ' . $download_url);
        } else {
            Debugger::log('getDownloadUrl (Plugin): Unknown update_from strategy: [' . $update_from . '] or missing required unrepress_meta for plugin: ' . ($plugin_data->slug ?? 'unknown'));
            // Fallback: if a direct download_url is in unrepress_meta, use it.
            if (!empty($meta->download_url) && is_string($meta->download_url)) {
                Debugger::log('getDownloadUrl (Plugin): Falling back to direct download_url from unrepress_meta: ' . $meta->download_url);
                return $meta->download_url;
            }
            return false;
        }

        // Optional: $this->helpers->validate_download_url($download_url);
        // For now, assume constructed URL is usable if logic passed.

        return $download_url;
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
     * Fix the source directory name during plugin updates
     * This prevents GitHub's repository naming format from being used.
     *
     * @param string       $source        File source location
     * @param string       $remote_source Remote file source location
     * @param WP_Upgrader $upgrader      WordPress Upgrader instance
     * @param array       $args          Extra arguments passed to hooked filters
     * @return string|WP_Error
     */
    private $current_plugin_slug = null;

    /**
     * Capture the plugin slug from the AJAX request or URL parameters
     * This runs before the download starts
     */
    public function capturePluginSlug($response, $package, $upgrader)
    {
        if (!empty($_REQUEST['plugin']) && $_REQUEST['action'] === 'install-plugin') {
            $this->current_plugin_slug = sanitize_text_field($_REQUEST['plugin']);
        } elseif (!empty($_REQUEST['slug'])) {
            $this->current_plugin_slug = sanitize_text_field($_REQUEST['slug']);
        } elseif (!empty($_POST['slug'])) {
            $this->current_plugin_slug = sanitize_text_field($_POST['slug']);
        }

        return $response;
    }

    public function maybeFixSourceDir($source, $remote_source, $upgrader, $args)
    {
        // First try to get the slug from the captured AJAX/URL parameter
        if (!empty($this->current_plugin_slug)) {
            return $this->helpers->fixSourceDir($source, $remote_source, $this->current_plugin_slug, 'plugin');
        }

        // Fallback to args if available
        if (isset($args['plugin'])) {
            return $this->helpers->fixSourceDir($source, $remote_source, $args['plugin'], 'plugin');
        }

        if (isset($args['type']) && $args['type'] == 'plugin') {
            return $this->helpers->fixSourceDir($source, $remote_source, $args, 'plugin');
        }

        return $source;
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

        Debugger::log('Getting plugin data for ' . $plugin_slug);

        $plugin_data = $this->requestRemoteInfo($plugin_slug);

        if (!$plugin_data) {
            Debugger::log('Invalid plugin data for ' . $plugin_slug . ' in getPluginData'); // Added context
            return [];
        }

        // Get the latest version from tags (this returns the full tag object or false)
        $latest_tag_object = $this->getLatestVersion($plugin_data);

        // Extract the actual tag name string for use in URLs
        $actual_tag_name_for_url = (is_object($latest_tag_object) && isset($latest_tag_object->name)) ? $latest_tag_object->name : null;

        // Determine the version string for display (e.g., '1.2.3')
        $display_version = null;
        if ($actual_tag_name_for_url) {
            $display_version = ltrim($actual_tag_name_for_url, 'v');
        } elseif (isset($plugin_data->version) && is_string($plugin_data->version)) {
            $display_version = $plugin_data->version;
        } else {
            $display_version = '0.0.0'; // Default display version
        }

        // Get server's WordPress and PHP versions for compatibility
        $wp_version = get_bloginfo('version');
        $php_version = phpversion();

        Debugger::log('Remote version for ' . $plugin_slug . ' is ' . $display_version);
        Debugger::log('Server WordPress version is ' . $wp_version);
        Debugger::log('Server PHP version is ' . $php_version);

        // Prepare the plugin data array with all required fields
        $processed_data = [
            'name' => $plugin_data->name ?? '',
            'slug' => $plugin_data->slug ?? '',
            'version' => $display_version,
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
            'download_url' => $this->getDownloadUrl($plugin_data, $actual_tag_name_for_url),
            'download_link' => $this->getDownloadUrl($plugin_data, $actual_tag_name_for_url),
            'homepage' => $plugin_data->homepage ?? '',
            'short_description' => isset($plugin_data->sections->description) ? substr($plugin_data->sections->description, 0, 150) . '&hellip;' : (isset($plugin_data->description) ? substr($plugin_data->description, 0, 150) . '&hellip;' : ''),
            'rating' => $plugin_data->rating ?? 100,
            'num_ratings' => $plugin_data->num_ratings ?? 1,
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

        Debugger::log('Processed plugin data for ' . $plugin_slug . ':');
        Debugger::log($processed_data);

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
        Debugger::log('Searching for plugins with term: ' . $term);

        // Get plugins index
        $transient_key = UNREPRESS_PREFIX . 'plugins_index';
        $plugins_index = get_transient($transient_key);

        if (false === $plugins_index) {
            Debugger::log('No cached plugins index found, fetching from remote for search term: ' . $term);

            // Get main index first to get the plugins index URL
            $main_index = $this->unrepress->index();

            if (!$main_index || !isset($main_index['plugins']['index'])) {
                Debugger::log('Main index is empty or missing plugins index URL for search term: ' . $term);
                return [];
            }

            $index_url = $main_index['plugins']['index'];
            Debugger::log('Fetching plugins index from: ' . $index_url . ' for search term: ' . $term);

            $response = wp_remote_get($index_url);

            if (is_wp_error($response)) {
                Debugger::log('Failed to fetch plugins index for search: ' . $response->get_error_message());
                return [];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                Debugger::log('Invalid response code from plugins index for search: ' . $response_code);
                return [];
            }

            $plugins_index = json_decode($response_body, true);
            if (!$plugins_index || !isset($plugins_index['plugins'])) {
                Debugger::log('Invalid plugins index format for search');
                return [];
            }

            set_transient($transient_key, $plugins_index, 3 * HOUR_IN_SECONDS);
        }

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
                Debugger::log('Found matching plugin: ' . $plugin['name']);
                $matching_plugins[] = $plugin['slug'];
            }
        }

        Debugger::log('Found ' . count($matching_plugins) . ' matching plugins');

        if (empty($matching_plugins)) {
            return [];
        }

        // Get full plugin data for each matching plugin
        $plugins_data = array_map([$this, 'getPluginData'], $matching_plugins);
        $filtered_plugins = array_filter($plugins_data);

        Debugger::log('Search for ' . $term . ' processed: ' . count($matching_plugins) . ' initial matches, ' . count($filtered_plugins) . ' valid plugins returned.');

        return $filtered_plugins;
    }
}
