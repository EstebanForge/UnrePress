<?php

namespace UnrePress\Updater;

use stdClass;
use UnrePress\Helpers;
use UnrePress\UpdaterProvider\GitHub;

class UpdatePlugin
{
    private $helpers;

    private $updateLock;

    private $provider = 'github';

    private $base_url = UNREPRESS_INDEX;

    private $cache_time = DAY_IN_SECONDS;

    public function __construct()
    {
        $this->helpers = new Helpers();
        $this->updateLock = new UpdateLock();

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
    }

    /**
     * Downloads remote data from $url and returns the JSON decoded response.
     *
     * @param string $url The URL to download data from.
     *
     * @return array|false The JSON decoded data or false if the request fails.
     */
    private function get_remote_data($url)
    {
        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/Plugin-Update-Checker',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Get the latest version data of a given plugin from the GitHub releases API.
     *
     * @param string $tags_url The URL to the GitHub releases API.
     *
     * @return array|false The version data or false if the request fails.
     */
    private function get_latest_version($tags_url)
    {
        // Get cached tags
        $cache_key = UNREPRESS_PREFIX . 'plugin_tags_' . md5($tags_url);
        $tags = get_transient($cache_key);

        if (false === $tags) {
            $tags = $this->get_remote_data($tags_url);
            if ($tags) {
                set_transient($cache_key, $tags, $this->cache_time);
            }
        }

        if (empty($tags) || ! is_array($tags)) {
            return false;
        }

        // Sort tags by version number (newest first)
        usort($tags, function ($a, $b) {
            return version_compare($b['name'], $a['name']);
        });

        // Return latest version info
        $latest = $tags[0];

        return [
            'version' => ltrim($latest['name'], 'v'),
            'download_url' => $latest['zipball_url'],
            'tag_data' => $latest,
        ];
    }

    /**
     * Checks for available updates to plugins.
     *
     * Checks against the custom GitHub JSON metadata repository.
     *
     * @since 1.0.0
     *
     * @param object $transient The update transient object.
     *
     * @return object The update transient object.
     */
    public function check_for_updates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $installed_plugins = get_plugins();

        foreach ($installed_plugins as $plugin_file => $plugin_data) {
            $slug = dirname(plugin_basename($plugin_file));
            if ($slug === '.') {
                continue;
            }

            // Get first letter of slug for the directory structure
            $first_letter = strtolower(substr($slug, 0, 1));
            $json_url = $this->base_url . $first_letter . '/' . $slug . '.json';

            // Get cached metadata
            $metadata = get_transient(UNREPRESS_PREFIX . 'plugin_metadata_' . $slug);
            if (false === $metadata) {
                $metadata = $this->get_remote_data($json_url);
                if ($metadata) {
                    set_transient(UNREPRESS_PREFIX . 'plugin_metadata_' . $slug, $metadata, $this->cache_time);
                }
            }

            if (empty($metadata) || empty($metadata['tags'])) {
                continue;
            }

            // Get latest version from GitHub tags
            $version_info = $this->get_latest_version($metadata['tags']);
            if (! $version_info) {
                continue;
            }

            $current_version = $plugin_data['Version'];
            if (version_compare($version_info['version'], $current_version, '>')) {
                $item = new stdClass();
                $item->slug = $slug;
                $item->plugin = $plugin_file;
                $item->new_version = $version_info['version'];
                $item->url = $metadata['homepage'];
                $item->package = $version_info['download_url'];

                $transient->response[$plugin_file] = $item;
            }
        }

        return $transient;
    }

    /**
     * Filters the plugin information for the WordPress.org Plugin Directory.
     *
     * Used to add custom metadata to the plugin information object.
     *
     * @since 1.0.0
     *
     * @param array  $result The plugin information object.
     * @param string $action The type of information being requested.
     * @param object $args   Additional arguments passed to the API request.
     *
     * @return array The modified plugin information object.
     */
    public function plugin_info($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (! isset($args->slug)) {
            return $result;
        }

        // Get first letter of slug
        $first_letter = strtolower(substr($args->slug, 0, 1));
        $json_url = $this->base_url . $first_letter . '/' . $args->slug . '.json';

        // Get cached metadata
        $metadata = get_transient(UNREPRESS_PREFIX . 'plugin_metadata_' . $args->slug);
        if (false === $metadata) {
            $metadata = $this->get_remote_data($json_url);
            if ($metadata) {
                set_transient(UNREPRESS_PREFIX . 'plugin_metadata_' . $args->slug, $metadata, $this->cache_time);
            }
        }

        if (empty($metadata) || empty($metadata['tags'])) {
            return $result;
        }

        // Get latest version from GitHub tags
        $version_info = $this->get_latest_version($metadata['tags']);
        if (! $version_info) {
            return $result;
        }

        // Create the plugin info object
        $info = new stdClass();
        $info->name = $metadata['name'];
        $info->slug = $args->slug;
        $info->version = $version_info['version'];
        $info->author = '<a href="' . esc_url($metadata['author_url']) . '">' . esc_html($metadata['author']) . '</a>';
        $info->homepage = $metadata['homepage'];
        $info->download_link = $version_info['download_url'];
        $info->sections = [
            'description' => $metadata['description'],
        ];

        // Add extra metadata
        $info->last_updated = date('Y-m-d');
        $info->requires = '5.0'; // Set default or fetch from readme
        $info->tested = get_bloginfo('version');
        $info->requires_php = '7.0'; // Set default or fetch from readme

        // Add additional metadata from your JSON
        $info->added = $metadata['date_added'];
        $info->license = $metadata['license'];
        $info->license_url = $metadata['license_url'];
        $info->free = $metadata['free'];
        $info->paid_features = $metadata['paid_features'];

        return $info;
    }
}
