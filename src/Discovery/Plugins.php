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
        $this->updater   = new UpdatePlugins();
        $this->unrepress = new UnrePress();

        add_filter('plugins_api_result', [$this, 'featuredPlugins'], 10, 3);
        add_filter('plugins_api_result', [$this, 'handleSearch'], 10, 3);
        add_filter('plugins_api_result', [$this, 'handlePluginInformation'], 10, 3);
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

        $url = UNREPRESS_INDEX . 'main/plugins/' . substr($plugin_slug, 0, 1) . "/{$plugin_slug}.json";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            unrepress_debug('Error fetching plugin data: ' . $response->get_error_message());
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $plugin_data = json_decode($body);

        if (!$plugin_data || !is_object($plugin_data)) {
            unrepress_debug('Invalid plugin data for ' . $plugin_slug);
            return [];
        }

        unrepress_debug('Raw plugin data for ' . $plugin_slug . ':');
        unrepress_debug($plugin_data);

        // Get the latest version from tags
        $version = $this->getLatestVersion($plugin_data);

        // Prepare the plugin data array with all required fields
        $processed_data = [
            'name' => $plugin_data->name ?? '',
            'slug' => $plugin_data->slug ?? '',
            'version' => $version,
            'author' => $plugin_data->author ?? '',
            'author_profile' => $plugin_data->author_url ?? '',
            'requires' => $plugin_data->requires ?? '5.0',
            'tested' => $plugin_data->tested ?? '6.4',
            'requires_php' => $plugin_data->requires_php ?? '7.4',
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

    private function getLatestVersion($plugin_data)
    {
        if (!empty($plugin_data->unrepress_meta->tags)) {
            $tags_url = $plugin_data->unrepress_meta->tags;
            $response = wp_remote_get($tags_url);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $tags = json_decode(wp_remote_retrieve_body($response));
                if (is_array($tags) && !empty($tags)) {
                    // Get the first tag (most recent)
                    return ltrim($tags[0]->name, 'v');
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
                // Convert HTTPS URL to archive URL
                $repo = str_replace('github.com', 'api.github.com/repos', $repo);
                return $repo . '/zipball/' . $version;
            }
        }

        return '';
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

            // Debug log
            unrepress_debug('Search Results:');
            unrepress_debug(print_r($plugins_data, true));

            $result = (object) [
                'info' => [
                    'page'    => 1,
                    'pages'   => 1,
                    'results' => count($plugins_data),
                ],
                'plugins' => $plugins_data,
            ];

            // Debug log the final result
            unrepress_debug('Final Result:');
            unrepress_debug(print_r($result, true));
        }

        return $result;
    }

    /**
     * Handle plugin information request.
     *
     * @param object $result The current result.
     * @param string $action The action being performed.
     * @param object $args The arguments for the action.
     *
     * @return object The updated result.
     */
    public function handlePluginInformation(object $result, string $action, object $args): object
    {
        unrepress_debug('Action: ' . $action);
        unrepress_debug('Args: ' . print_r($args, true));

        if ($action === 'plugin_information' && !empty($args->slug)) {
            $plugin_data = $this->getPluginData($args->slug);
            unrepress_debug('Plugin Data for ' . $args->slug . ':');
            unrepress_debug(print_r($plugin_data, true));

            if (!empty($plugin_data)) {
                return (object) $plugin_data;
            }
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
