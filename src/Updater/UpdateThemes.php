<?php

namespace UnrePress\Updater;

use UnrePress\Helpers;

class UpdateThemes
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
        $this->cache_key = UNREPRESS_PREFIX . 'updates_theme_';
        $this->cache_results = true;

        $this->checkforUpdates();

        unrepress_debug('UpdateThemes::__construct - Registering themes_api filter');
        add_filter('themes_api', [$this, 'getInformation'], 20, 3);
        add_filter('site_transient_update_themes', [$this, 'hasUpdate']);
        add_action('upgrader_process_complete', [$this, 'cleanAfterUpdate'], 10, 2);
        add_filter('upgrader_source_selection', [$this, 'maybeFixSourceDir'], 10, 4);
        add_filter('upgrader_pre_download', [$this, 'captureThemeSlug'], 10, 3);

        unrepress_debug('UpdateThemes::__construct - All filters and actions registered');
    }

    /**
     * Check for theme updates
     * This method will check for updates on every installed theme.
     *
     * @return void
     */
    private function checkforUpdates()
    {
        $themes = wp_get_themes();

        foreach ($themes as $slug => $theme) {
            $this->checkForThemeUpdate($slug);
        }
    }

    private function checkForThemeUpdate($slug)
    {
        $remoteData = $this->requestRemoteInfo($slug);

        // If we can't get theme info, skip this theme
        if (!$remoteData) {
            return;
        }

        $installedVersion = $this->getInstalledVersion($slug);
        $latestVersion = $this->getRemoteVersion($slug);

        if ($remoteData && $installedVersion && $latestVersion) {
            if (version_compare($installedVersion, $latestVersion, '<')) {
                $updateInfo = new \stdClass();

                $theme = wp_get_theme($slug);

                // Get data from the remote source
                $updateInfo->requires = $remoteData->requires ?? '6.5';
                $updateInfo->tested = $remoteData->tested ?? '6.7';
                $updateInfo->requires_php = $remoteData->requires_php ?? '8.1';

                // Get data from the local theme object
                $updateInfo->name = $theme->get('Name');
                $updateInfo->theme_uri = $theme->get('ThemeURI');
                $updateInfo->description = $theme->get('Description');
                $updateInfo->author = $theme->get('Author');
                $updateInfo->author_profile = $theme->get('AuthorURI');
                $updateInfo->tags = $theme->get('Tags');
                $updateInfo->textdomain = $theme->get('TextDomain');
                $updateInfo->template = $theme->get_template();

                // Remote data for updates
                $updateInfo->last_updated = $remoteData->last_updated ?? time();
                $updateInfo->changelog = $remoteData->sections->changelog ?? '';
                $updateInfo->screenshot = $remoteData->screenshot_url ?? '';

                $updateInfo->version = $latestVersion;
                $updateInfo->url = get_transient($this->cache_key . 'download-url-' . $slug);
                $updateInfo->download_url = get_transient($this->cache_key . 'download-url-' . $slug);
                $updateInfo->download_link = get_transient($this->cache_key . 'download-url-' . $slug);
                $updateInfo->package = get_transient($this->cache_key . 'download-url-' . $slug);

                // Store this information for later use
                $this->updateInfo[$slug] = $updateInfo;
            }
        }
    }

    private function getInstalledVersion($slug)
    {
        $theme = wp_get_theme($slug);
        $version = $theme->get('Version');

        return !empty($version) ? $version : false;
    }

    public function requestRemoteInfo($slug = null)
    {
        if (!$slug) {
            return false;
        }

        $remote = get_transient($this->cache_key . $slug);

        // Get the first letter of the slug
        $first_letter = mb_strtolower(mb_substr($slug, 0, 1));

        if ($remote === false || !$this->cache_results) {
            $remote = wp_remote_get(
                UNREPRESS_INDEX . 'main/themes/' . $first_letter . '/' . $slug . '.json',
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
        unrepress_debug('UpdateThemes::getInformation called with action: ' . $action);
        unrepress_debug('UpdateThemes::getInformation args: ' . print_r($args, true));

        // Handle both theme_information and query_themes actions
        if ($action !== 'theme_information' && $action !== 'query_themes') {
            unrepress_debug('UpdateThemes::getInformation - Action not supported: ' . $action);
            return $response;
        }

        // For query_themes action, handle search, featured, popular, and latest themes
        if ($action === 'query_themes') {
            unrepress_debug('UpdateThemes::getInformation - Handling query_themes action');

            $themesIndex = new \UnrePress\Index\ThemesIndex();

            // Default values for pagination
            $page = isset($args->page) ? intval($args->page) : 1;
            $per_page = isset($args->per_page) ? intval($args->per_page) : 24;

            unrepress_debug('UpdateThemes::getInformation - Pagination: page=' . $page . ', per_page=' . $per_page);

            // Handle search - always return empty since search is not supported
            if (!empty($args->search)) {
                $search_term = sanitize_text_field($args->search);
                unrepress_debug('UpdateThemes::getInformation - Search term: ' . $search_term . ' - Attempting search.');

                $themes_data = $themesIndex->searchThemes($search_term, $page, $per_page);
                unrepress_debug('UpdateThemes::getInformation - Search result from ThemesIndex: ' . print_r($themes_data, true));

                if ($themes_data && isset($themes_data->themes) && !empty($themes_data->themes)) {
                    $formatted_response = $this->formatThemesResponse($themes_data, $action);
                    unrepress_debug('UpdateThemes::getInformation - Returning search response with ' . count($formatted_response->themes) . ' themes');
                    return $formatted_response;
                } else {
                    unrepress_debug('UpdateThemes::getInformation - Search returned no data or empty themes array from ThemesIndex');
                    return $this->getEmptyThemesResponse(); // Return an empty response if no themes found
                }
            }
            // Handle browse categories
            elseif (!empty($args->browse)) {
                $browse_type = sanitize_text_field($args->browse);
                unrepress_debug('UpdateThemes::getInformation - Browse type: ' . $browse_type);

                switch ($browse_type) {
                    case 'featured':
                        unrepress_debug('UpdateThemes::getInformation - Getting featured themes');
                        $themes_data = $themesIndex->getFeaturedThemes($page, $per_page);
                        break;
                    case 'popular':
                        unrepress_debug('UpdateThemes::getInformation - Popular tab renamed to Featured, returning featured themes');
                        $themes_data = $themesIndex->getFeaturedThemes($page, $per_page);
                        break;
                    case 'new':
                    case 'latest':
                        unrepress_debug('UpdateThemes::getInformation - Latest/new themes not available, returning empty');
                        return $this->getEmptyThemesResponse();
                    default:
                        unrepress_debug('UpdateThemes::getInformation - Unknown browse type, defaulting to featured');
                        $themes_data = $themesIndex->getFeaturedThemes($page, $per_page);
                        break;
                }

                unrepress_debug('UpdateThemes::getInformation - Browse result: ' . print_r($themes_data, true));

                if ($themes_data) {
                    $formatted_response = $this->formatThemesResponse($themes_data, $action);
                    unrepress_debug('UpdateThemes::getInformation - Returning browse response with ' . count($formatted_response->themes) . ' themes');
                    unrepress_debug('UpdateThemes::getInformation - Exact structure of formatted_response: ' . print_r($formatted_response, true));
                    return $formatted_response;
                } else {
                    unrepress_debug('UpdateThemes::getInformation - Browse returned no data');
                }
            }
            // Default to featured themes
            else {
                unrepress_debug('UpdateThemes::getInformation - No search or browse specified, defaulting to featured');
                $themes_data = $themesIndex->getFeaturedThemes($page, $per_page);

                unrepress_debug('UpdateThemes::getInformation - Default featured result: ' . print_r($themes_data, true));

                if ($themes_data) {
                    $formatted_response = $this->formatThemesResponse($themes_data, $action);
                    unrepress_debug('UpdateThemes::getInformation - Returning default response with ' . count($formatted_response->themes) . ' themes');
                    unrepress_debug('UpdateThemes::getInformation - Exact structure of formatted_response (default): ' . print_r($formatted_response, true));
                    return $formatted_response;
                } else {
                    unrepress_debug('UpdateThemes::getInformation - Default featured returned no data');
                }
            }

            // Return empty response if no data found
            unrepress_debug('UpdateThemes::getInformation - Returning empty response');
            return $this->getEmptyThemesResponse();
        }

        // Handle theme_information action (existing functionality)
        unrepress_debug('UpdateThemes::getInformation - Handling theme_information action');

        if (empty($args->slug)) {
            unrepress_debug('UpdateThemes::getInformation - No slug provided for theme_information');
            return $response;
        }

        unrepress_debug('UpdateThemes::getInformation - Getting theme info for slug: ' . $args->slug);

        // Try to get theme information from index first
        $themesIndex = new \UnrePress\Index\ThemesIndex();
        $theme_info = $themesIndex->getThemeInformation($args->slug);

        if ($theme_info) {
            unrepress_debug('UpdateThemes::getInformation - Found theme info in index: ' . print_r($theme_info, true));
            return $theme_info;
        }

        unrepress_debug('UpdateThemes::getInformation - Theme not found in index, trying fallback method');

        // Fallback to existing method for backward compatibility
        $remote = $this->requestRemoteInfo($args->slug);

        if (!$remote) {
            unrepress_debug('UpdateThemes::getInformation - Fallback method also failed');
            return $response;
        }

        unrepress_debug('UpdateThemes::getInformation - Fallback method found data: ' . print_r($remote, true));

        $response = new \stdClass();

        // Last updated, now
        $remote->last_updated = time();
        // Changelog
        if (isset($remote->sections) && isset($remote->sections->changelog)) {
            $remote->sections->changelog = $remote->sections->changelog;
        }

        $response->name = $remote->name ?? '';
        $response->slug = $remote->slug ?? $args->slug;
        $response->version = $remote->version ?? '';
        $response->tested = $remote->tested ?? '';
        $response->requires = $remote->requires ?? '';
        $response->author = $remote->author ?? '';
        $response->author_profile = $remote->author_url ?? '';
        $response->donate_link = $remote->donate_link ?? '';
        $response->homepage = $remote->homepage ?? '';
        $response->download_url = $remote->download_url ?? '';
        $response->download_link = $remote->download_url ?? '';
        $response->package = $remote->download_url ?? '';
        $response->trunk = $remote->download_url ?? '';
        $response->requires_php = $remote->requires_php ?? '';
        $response->last_updated = $remote->last_updated ?? time();
        $response->screenshot_url = $remote->screenshot_url ?? '';

        if (isset($remote->sections)) {
            $response->sections = [
                'description' => $remote->sections->description ?? '',
                'installation' => $remote->sections->installation ?? '',
                'changelog' => $remote->sections->changelog ?? '',
            ];
        }

        unrepress_debug('UpdateThemes::getInformation - Returning fallback response: ' . print_r($response, true));
        return $response;
    }

    /**
     * Format themes data response for query_themes action
     *
     * @param object $themes_data Raw themes data from index
     * @param string $action The API action
     * @return object Formatted response
     */
    private function formatThemesResponse($themes_data, $action)
    {
        $response = new \stdClass();
        $response->info = new \stdClass();
        $response->themes = [];

        // Set pagination info
        $response->info->page = $themes_data->page ?? 1;
        $response->info->pages = $themes_data->pages ?? 1;
        $response->info->results = $themes_data->total ?? 0;

        // Format each theme
        if (isset($themes_data->themes) && is_array($themes_data->themes)) {
            foreach ($themes_data->themes as $theme_item_from_index) {
                $theme_data_object = (object) $theme_item_from_index;

                $theme = new \stdClass();

                $theme->name = $theme_data_object->name ?? '';
                $theme->slug = $theme_data_object->slug ?? '';
                $theme->version = $theme_data_object->version ?? '1.0.0';
                $theme->author = ['display_name' => $theme_data_object->author ?? ''];
                $theme->author_profile = $theme_data_object->author_url ?? '';
                $theme->preview_url = $theme_data_object->preview_url ?? '';
                $theme->screenshot_url = $theme_data_object->screenshot_url ?? '';
                $theme->rating = intval($theme_data_object->rating ?? 0);
                $theme->num_ratings = intval($theme_data_object->num_ratings ?? 0);
                $theme->downloaded = intval($theme_data_object->downloaded ?? 0);
                $theme->last_updated = $theme_data_object->last_updated ?? '';
                $theme->requires = $theme_data_object->requires ?? '6.0';
                $theme->tested = $theme_data_object->tested ?? '6.7';
                $theme->requires_php = $theme_data_object->requires_php ?? '8.1';
                $theme->tags = $theme_data_object->tags ?? [];
                $theme->homepage = $theme_data_object->homepage ?? '';
                $theme->download_link = $theme_data_object->download_url ?? '';
                $theme->package = $theme_data_object->download_url ?? '';

                // Populate theme description
                if (isset($theme_data_object->description) && !empty($theme_data_object->description)) {
                    $theme->description = $theme_data_object->description;
                } elseif (isset($theme_data_object->sections)) {
                    $sections_data = (object) $theme_data_object->sections; // Ensure sections is an object for consistent access
                    if (isset($sections_data->description)) {
                        $theme->description = $sections_data->description;
                    } else {
                        $theme->description = ''; // Default to empty if not found in sections
                    }
                } else {
                    $theme->description = ''; // Default to empty if no description sources
                }

                // Ensure sections are correctly formatted if they exist
                if (isset($theme_data_object->sections) && (is_array($theme_data_object->sections) || is_object($theme_data_object->sections))) {
                    $theme->sections = (object) $theme_data_object->sections;
                } else {
                    // If no sections are provided in the source, but we have a main description, use that for sections->description
                    $theme->sections = (object) ['description' => $theme->description ?? ''];
                }

                $response->themes[] = $theme;
            }
        }

        return $response;
    }

    /**
     * Get empty themes response for when no themes are found
     *
     * @return object Empty response
     */
    private function getEmptyThemesResponse()
    {
        $response = new \stdClass();
        $response->info = new \stdClass();
        $response->info->page = 1;
        $response->info->pages = 0;
        $response->info->results = 0;
        $response->themes = [];

        return $response;
    }

    public function hasUpdate($transient)
    {
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }

        if (empty($transient->checked)) {
            $transient->checked = [];
            // Get all themes and their versions
            $themes = wp_get_themes();
            foreach ($themes as $slug => $theme) {
                $transient->checked[$slug] = $theme->get('Version');
            }
        }

        foreach ($this->updateInfo as $slug => $updateInfo) {
            if (isset($transient->checked[$slug])) {
                $currentVersion = $transient->checked[$slug];

                if (
                    !empty($currentVersion) && !empty($updateInfo->version) &&
                    version_compare($currentVersion, $updateInfo->version, '<')
                ) {
                    if (!isset($transient->response)) {
                        $transient->response = [];
                    }

                    // Format response according to WordPress theme update structure
                    $transient->response[$slug] = [
                        'theme' => $slug,
                        'new_version' => $updateInfo->version,
                        'url' => $updateInfo->theme_uri ?? '',
                        'package' => $updateInfo->download_link,
                        'download_url' => $updateInfo->download_link,
                        'download_link' => $updateInfo->download_link,
                        'requires' => $updateInfo->requires ?? '',
                        'requires_php' => $updateInfo->requires_php ?? '',
                    ];
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
     * @param string $slug Theme slug
     *
     * @return string|false Version string or false on failure
     */
    private function getRemoteVersion($slug)
    {
        $remoteVersion = get_transient($this->cache_key . 'remote-version-' . $slug);

        if ($remoteVersion === false || !$this->cache_results) {
            $first_letter = mb_strtolower(mb_substr($slug, 0, 1));

            // Get theme info from UnrePress index
            $remote = wp_remote_get(UNREPRESS_INDEX . 'main/themes/' . $first_letter . '/' . $slug . '.json', [
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

            if (empty(wp_remote_retrieve_body($remote))) {
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($remote));

            if (is_wp_error($body)) {
                return false;
            }

            $tagUrl = $body->tags ?? '';

            if (empty($tagUrl)) {
                return false;
            }

            // Normalize tag URL
            $tagUrl = $this->helpers->normalizeTagUrl($tagUrl);

            // Get tag information
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

            if (empty(wp_remote_retrieve_body($remote))) {
                return false;
            }

            $tagBody = json_decode(wp_remote_retrieve_body($remote));

            if (!is_array($tagBody) || empty($tagBody)) {
                return false;
            }

            // Get the newest version from tags
            $latestTag = $this->helpers->getNewestVersionFromTags($tagBody);

            if (!$latestTag) {
                return false;
            }

            $remoteVersion = $latestTag->name;
            $remoteZip = $latestTag->zipball_url;

            // Store version and download information
            if ($remoteVersion) {
                // Clean version number (remove 'v' prefix if present)
                $remoteVersion = ltrim($remoteVersion, 'v');

                set_transient($this->cache_key . 'download-url-' . $slug, $remoteZip, DAY_IN_SECONDS);
                set_transient($this->cache_key . 'remote-version-' . $slug, $remoteVersion, DAY_IN_SECONDS);
            } else {
                return false;
            }
        }

        return $remoteVersion;
    }

    private $current_theme_slug = null;

    /**
     * Capture the theme slug from the AJAX request or URL parameters
     * This runs before the download starts
     */
    public function captureThemeSlug($response, $package, $upgrader)
    {
        if (!empty($_REQUEST['theme']) && $_REQUEST['action'] === 'install-theme') {
            $this->current_theme_slug = sanitize_text_field($_REQUEST['theme']);
        } elseif (!empty($_REQUEST['slug'])) {
            $this->current_theme_slug = sanitize_text_field($_REQUEST['slug']);
        } elseif (!empty($_POST['slug'])) {
            $this->current_theme_slug = sanitize_text_field($_POST['slug']);
        }

        return $response;
    }

    /**
     * Fix source directory for GitHub theme updates.
     */
    public function maybeFixSourceDir($source, $remote_source, $upgrader, $args)
    {
        // First try to get the slug from the captured AJAX/URL parameter
        if (!empty($this->current_theme_slug)) {
            return $this->helpers->fixSourceDir($source, $remote_source, $this->current_theme_slug, 'theme');
        }

        // Fallback to args if available
        if (isset($args['theme'])) {
            return $this->helpers->fixSourceDir($source, $remote_source, $args['theme'], 'theme');
        }

        if (isset($args['type']) && $args['type'] == 'theme') {
            return $this->helpers->fixSourceDir($source, $remote_source, $args, 'theme');
        }

        return $source;
    }
}
