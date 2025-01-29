<?php

namespace UnrePress\Discovery;

use UnrePress\UnrePress;
use UnrePress\Updater\UpdatePlugins;

class Plugins
{
    private UpdatePlugins $updater;
    private UnrePress $unrepress;

    public function __construct()
    {
        $this->updater = new UpdatePlugins();
        $this->unrepress = new UnrePress();
        add_filter('plugins_api_result', [$this, 'featuredPlugins'], 10, 3);
        add_filter('plugins_api_result', [$this, 'handleSearch'], 10, 3);
    }

    /**
     * Get plugin data from UnrePress index.
     *
     * @param string $plugin_slug Plugin slug
     * @return array Plugin data
     */
    private function getPluginData(string $plugin_slug): array
    {
        // Sanitize
        $plugin_slug = sanitize_key($plugin_slug);

        $transient_key = UNREPRESS_PREFIX . 'plugin_data_' . $plugin_slug;
        $cached_data = get_transient($transient_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        // Get plugin data
        $plugin_data = $this->updater->requestRemoteInfo($plugin_slug);

        if (!$plugin_data) {
            return [];
        }

        $processed_data = [
            'name'              => $plugin_data->name,
            'slug'              => $plugin_data->slug,
            'version'           => $plugin_data->version ?? '1.0.0',
            'author'            => $plugin_data->author,
            'author_profile'    => $plugin_data->author_url,
            'requires'          => $plugin_data->requires ?? '6.0',
            'tested'            => $plugin_data->tested ?? '6.5',
            'rating'            => 100,
            'num_ratings'       => 0,
            'active_installs'   => 1000,
            'last_updated'      => $plugin_data->last_updated ?? date('Y-m-d H:i:s'),
            'short_description' => wp_trim_words($plugin_data->sections->description ?? '', 20),
            'download_link'     => $plugin_data->download_url ?? '',
            'sections'          => [
                'description'  => $plugin_data->sections->description ?? '',
                'installation' => $plugin_data->sections->installation ?? '',
                'changelog'    => $plugin_data->sections->changelog ?? '',
            ],
            'banners'           => [
                'low'  => $plugin_data->banners->low ?? '',
                'high' => $plugin_data->banners->high ?? '',
            ],
            'icons'             => [
                '1x'       => $plugin_data->icons->low ?? '',
                '2x'       => $plugin_data->icons->high ?? '',
                'default'  => $plugin_data->icons->default ?? '',
            ],
        ];

        // Check if we have valid Banners URLs
        if (empty($processed_data['banners']['low']) || !wp_http_validate_url($processed_data['banners']['low'])) {
            $processed_data['banners']['low'] = UNREPRESS_INDEX . 'main/assets/images/banner-1544x500.webp';
        }

        if (empty($processed_data['banners']['high']) || !wp_http_validate_url($processed_data['banners']['high'])) {
            $processed_data['banners']['high'] = UNREPRESS_INDEX . 'main/assets/images/banner-1544x500.webp';
        }

        // Check if we have valid Icons URLs
        if (empty($processed_data['icons']['low']) || !wp_http_validate_url($processed_data['icons']['low'])) {
            $processed_data['icons']['low'] = UNREPRESS_INDEX . 'main/assets/images/icon-256.webp';
        }

        if (empty($processed_data['icons']['high']) || !wp_http_validate_url($processed_data['icons']['high'])) {
            $processed_data['icons']['high'] = UNREPRESS_INDEX . 'main/assets/images/icon-1024.webp';
        }

        if (empty($processed_data['icons']['default']) || !wp_http_validate_url($processed_data['icons']['default'])) {
            $processed_data['icons']['default'] = UNREPRESS_INDEX . 'main/assets/images/icon-256.webp';
        }

        set_transient($transient_key, $processed_data, 12 * HOUR_IN_SECONDS);

        return $processed_data;
    }

    /**
     * Filter featured plugins in the WordPress plugin directory.
     *
     * @param object $result The result object
     * @param string $action The type of information being requested from the Plugin Installation API
     * @param object $args Plugin API arguments
     * @return object Modified result
     */
    public function featuredPlugins(object $result, string $action, object $args): object
    {
        if ($action === 'query_plugins' && empty($args->search)) {
            // Get main index
            $main_index = $this->unrepress->index();

            if (!$main_index || !isset($main_index['plugins']['featured'])) {
                return $result;
            }

            $transient_key = UNREPRESS_PREFIX . 'discovery_featured_plugins';
            $response = get_transient($transient_key);

            if (false === $response) {
                $response = wp_remote_get($main_index['plugins']['featured']);
                if (!is_wp_error($response)) {
                    set_transient($transient_key, $response, 6 * HOUR_IN_SECONDS);
                }
            }

            if (is_wp_error($response)) {
                return $result;
            }

            $featured_plugins = json_decode(wp_remote_retrieve_body($response), true);

            if (is_array($featured_plugins) && isset($featured_plugins['featured'])) {
                $plugins_data = array_map([$this, 'getPluginData'], $featured_plugins['featured']);
                // Filter out any empty results
                $plugins_data = array_filter($plugins_data);

                $result = (object) [
                    'info' => [
                        'page'    => 1,
                        'pages'   => 1,
                        'results' => count($plugins_data),
                    ],
                    'plugins' => $plugins_data,
                ];
            }
        }

        return $result;
    }

    /**
     * Handle the search action.
     *
     * @param object $result The current result.
     * @param string $action The action being performed.
     * @param object $args The arguments for the action.
     *
     * @return object The updated result.
     */
    public function handleSearch(object $result, string $action, object $args): object
    {
        if ($action === 'query_plugins' && !empty($args->search)) {
            $term = sanitize_text_field($args->search);
            $plugins_data = $this->searchPlugins($term);

            return (object) [
                'info' => [
                    'page'    => 1,
                    'pages'   => 1,
                    'results' => count($plugins_data),
                ],
                'plugins' => $plugins_data,
            ];
        }

        return $result;
    }

    /**
     * Search for plugins in the plugins-index.json file.
     *
     * @param string $term The term to search for.
     *
     * @return array An array of plugins matching the search term.
     */
    public function searchPlugins(string $term): array
    {
        $main_index = $this->unrepress->index();

        if (!$main_index || !isset($main_index['plugins']['index'])) {
            return [];
        }

        // Fetch and cache plugins-index.json
        $transient_key = UNREPRESS_PREFIX . 'discovery_plugins_index';
        $response = get_transient($transient_key);

        if (false === $response) {
            $response = wp_remote_get($main_index['plugins']['index']);
            if (!is_wp_error($response)) {
                set_transient($transient_key, $response, 24 * HOUR_IN_SECONDS);
            }
        }

        if (is_wp_error($response)) {
            return [];
        }

        $plugins_list = json_decode(wp_remote_retrieve_body($response), true);

        // Validate JSON decode and required structure
        if (empty($plugins_list) || !is_array($plugins_list) || !isset($plugins_list['plugins']) || !is_array($plugins_list['plugins'])) {
            return [];
        }

        $term = strtolower(trim($term));

        $matches = array_filter($plugins_list['plugins'], function ($plugin) use ($term) {
            // Validate plugin array structure
            if (!is_array($plugin) ||
                !isset($plugin['name'], $plugin['slug'], $plugin['description']) ||
                !isset($plugin['tags']) || !is_array($plugin['tags'])) {
                return false;
            }

            // Search in name, slug, description, and tags
            $fields_to_search = [
                strtolower($plugin['name']),
                strtolower($plugin['slug']),
                strtolower($plugin['description']),
                implode(' ', array_map('strtolower', $plugin['tags'])),
            ];
            $combined_text = implode(' ', $fields_to_search);

            return stripos($combined_text, $term) !== false;
        });

        // Get FULL data for matched plugins
        $plugins_data = array_map(function ($plugin) {
            return $this->getPluginData($plugin['slug']);
        }, array_values($matches));

        return array_filter($plugins_data); // Remove empty entries
    }
}
