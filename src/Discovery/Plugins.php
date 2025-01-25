<?php

namespace UnrePress\Discovery;

use UnrePress\Updater\UpdatePlugins;

class Plugins
{
    private UpdatePlugins $updater;

    public function __construct()
    {
        $this->updater = new UpdatePlugins();
        add_filter('plugins_api_result', [$this, 'featuredPlugins'], 10, 3);
    }

    /**
     * Get plugin data from UnrePress index
     *
     * @param string $plugin_slug Plugin slug
     * @return array Plugin data
     */
    private function getPluginData(string $plugin_slug): array
    {
        $plugin_data = $this->updater->requestRemoteInfo($plugin_slug);

        if (!$plugin_data) {
            return [];
        }

        return [
            'name'              => $plugin_data->name,
            'slug'              => $plugin_data->slug,
            'version'           => $plugin_data->version ?? '1.0.0',
            'author'            => $plugin_data->author,
            'author_profile'    => $plugin_data->author_profile,
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
                '1x'       => $plugin_data->icons->{'1x'} ?? '',
                '2x'       => $plugin_data->icons->{'2x'} ?? '',
                'default'  => $plugin_data->icons->default ?? '',
            ],
        ];
    }

    /**
     * Filter featured plugins in the WordPress plugin directory
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
            $main_index_url = rtrim(UNREPRESS_INDEX, '/') . '/index.json';
            $main_index_response = wp_remote_get($main_index_url);

            if (is_wp_error($main_index_response)) {
                return $result;
            }

            $main_index = json_decode(wp_remote_retrieve_body($main_index_response), true);

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
}
