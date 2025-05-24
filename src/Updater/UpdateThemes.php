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

        add_filter('themes_api', [$this, 'getInformation'], 20, 3);
        add_filter('site_transient_update_themes', [$this, 'hasUpdate']);
        add_action('upgrader_process_complete', [$this, 'cleanAfterUpdate'], 10, 2);
        add_filter('upgrader_source_selection', [$this, 'maybeFixSourceDir'], 10, 4);
        add_filter('upgrader_pre_download', [$this, 'captureThemeSlug'], 10, 3);
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
        if (!$installedVersion) {
            return; // Cannot compare if installed version is unknown
        }

        // Get the latest tag object using the same logic as getInformation
        $latest_tag_object = $this->getLatestVersionFromMeta($remoteData);

        if (!is_object($latest_tag_object) || !isset($latest_tag_object->name)) {
            return;
        }

        $actual_tag_name_for_url = $latest_tag_object->name;
        $latest_version_string = ltrim($actual_tag_name_for_url, 'v');

        // Get the proper download URL using the consistent method
        $download_url = $this->getDownloadUrlFromMeta($remoteData, $actual_tag_name_for_url);

        if (!$download_url) {
            return;
        }

        if (version_compare($installedVersion, $latest_version_string, '<')) {
            $updateInfo = new \stdClass();
            $theme = wp_get_theme($slug); // Get local theme object for some details

            // Populate with data from $remoteData, local theme, and our determined values
            $updateInfo->theme = $slug; // WordPress uses 'theme' key for slug in transient
            $updateInfo->new_version = $latest_version_string;
            $updateInfo->url = $remoteData->homepage ?? $theme->get('ThemeURI'); // A presentation URL
            $updateInfo->package = $download_url; // The actual download zip

            // Additional fields WordPress might check or display from the transient
            // (though themes_api is the primary source for full display)
            $updateInfo->requires = $remoteData->requires ?? '5.0';
            $updateInfo->requires_php = $remoteData->requires_php ?? '7.4';
            $updateInfo->tested = $remoteData->tested ?? (defined('get_bloginfo') ? get_bloginfo('version') : '0.0');

            // Store this information for later use in hasUpdate filter
            $this->updateInfo[$slug] = $updateInfo;
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
        unrepress_debug('UpdateThemes::getInformation called: action=' . $action . ', slug=' . ($args->slug ?? 'N/A') . ', browse=' . ($args->browse ?? 'N/A') . ', search=' . ($args->search ?? 'N/A'));

        if ($action !== 'theme_information' && $action !== 'query_themes') {
            unrepress_debug('UpdateThemes::getInformation - Action not supported: ' . $action);
            return $response;
        }

        if ($action === 'query_themes') {
            $themesIndex = new \UnrePress\Index\ThemesIndex();

            $page = isset($args->page) ? intval($args->page) : 1;
            $per_page = isset($args->per_page) ? intval($args->per_page) : 24;

            if (!empty($args->search)) {
                $search_term = sanitize_text_field($args->search);
                unrepress_debug('UpdateThemes::getInformation - Search term: ' . $search_term);
                $themes_data = $themesIndex->searchThemes($search_term, $page, $per_page);
                unrepress_debug('UpdateThemes::getInformation - Search themes_data count: ' . count($themes_data->themes ?? []));

                if ($themes_data && isset($themes_data->themes) && !empty($themes_data->themes)) {
                    $formatted_response = $this->formatThemesResponse($themes_data, $action);
                    unrepress_debug('UpdateThemes::getInformation - Returning search response with ' . count($formatted_response->themes) . ' themes');
                    return $formatted_response;
                } else {
                    unrepress_debug('UpdateThemes::getInformation - Search returned no data or empty themes array from ThemesIndex');
                    return $this->getEmptyThemesResponse();
                }
            }
            elseif (!empty($args->browse)) {
                $browse_type = sanitize_text_field($args->browse);
                unrepress_debug('UpdateThemes::getInformation - Browse type: ' . $browse_type);

                switch ($browse_type) {
                    case 'featured':
                    case 'popular': // Popular tab now shows featured
                        $themes_data = $themesIndex->getFeaturedThemes($page, $per_page);
                        break;
                    case 'new':
                    case 'latest':
                        unrepress_debug('UpdateThemes::getInformation - Latest/new themes not available, returning empty');
                        return $this->getEmptyThemesResponse();
                    default:
                        $themes_data = $themesIndex->getFeaturedThemes($page, $per_page);
                        break;
                }

                unrepress_debug('UpdateThemes::getInformation - Browse result count: ' . count($themes_data->themes ?? []));

                if ($themes_data) {
                    $formatted_response = $this->formatThemesResponse($themes_data, $action);
                    unrepress_debug('UpdateThemes::getInformation - Returning browse response with ' . count($formatted_response->themes) . ' themes');
                    return $formatted_response;
                } else {
                    unrepress_debug('UpdateThemes::getInformation - Browse returned no data');
                }
            }
            else {
                unrepress_debug('UpdateThemes::getInformation - No search or browse specified, defaulting to featured');
                $themes_data = $themesIndex->getFeaturedThemes($page, $per_page);
                unrepress_debug('UpdateThemes::getInformation - Default featured result count: ' . count($themes_data->themes ?? []));

                if ($themes_data) {
                    $formatted_response = $this->formatThemesResponse($themes_data, $action);
                    unrepress_debug('UpdateThemes::getInformation - Returning default response with ' . count($formatted_response->themes) . ' themes');
                    return $formatted_response;
                } else {
                    unrepress_debug('UpdateThemes::getInformation - Default featured returned no data');
                }
            }

            unrepress_debug('UpdateThemes::getInformation - Returning empty response for query_themes');
            return $this->getEmptyThemesResponse();
        }

        if (empty($args->slug)) {
            unrepress_debug('UpdateThemes::getInformation - No slug provided for theme_information');
            return $response;
        }

        unrepress_debug('UpdateThemes::getInformation - Getting theme info for slug: ' . $args->slug);

        // Fetch the theme's core data from its JSON file in the UnrePress index
        // This is analogous to UpdatePlugins::requestRemoteInfo()
        $theme_data_from_index = $this->requestRemoteInfo($args->slug); // Assuming requestRemoteInfo fetches and decodes the theme's JSON

        if (!$theme_data_from_index) {
            unrepress_debug('UpdateThemes::getInformation - Could not fetch remote theme data for slug: ' . $args->slug);
            return $response; // Return original $response if our index doesn't have it
        }

        // Now, dynamically determine the latest version and download URL
        // This mirrors the logic in UpdatePlugins::getInformation
        $latest_tag_object = $this->getLatestVersionFromMeta($theme_data_from_index);

        $actual_tag_name_for_url = (is_object($latest_tag_object) && isset($latest_tag_object->name)) ? $latest_tag_object->name : null;
        $display_version = (is_object($latest_tag_object) && isset($latest_tag_object->name)) ? ltrim($latest_tag_object->name, 'v') : ($theme_data_from_index->version ?? '0.0.0');

        $download_url = $this->getDownloadUrlFromMeta($theme_data_from_index, $actual_tag_name_for_url);

        unrepress_debug('UpdateThemes::getInformation - Slug: ' . $args->slug . ' | Latest Tag Name: ' . ($actual_tag_name_for_url ?? 'N/A') . ' | Display Version: ' . $display_version . ' | Download URL: ' . ($download_url ?? 'N/A'));

        // If we couldn't determine a download URL, we can't proceed with installation info
        if (empty($download_url)) {
            unrepress_debug('UpdateThemes::getInformation - Could not determine download_url for: ' . $args->slug);
            // Optionally, try to fall back to a hardcoded download_url from theme_data_from_index if it exists and is preferred for some themes
            // For now, we strictly follow the dynamic approach.
            // return $response; // Or, if ThemesIndex::getThemeInformation is more suitable for display data:
            $theme_display_info = (new \UnrePress\Index\ThemesIndex())->getThemeInformation($args->slug);
            if ($theme_display_info) return $theme_display_info; // Return rich display data if download link is missing
            return $response;
        }

        // Construct the $response object for WordPress themes_api
        $api_response = new \stdClass();

        // Populate with data from $theme_data_from_index and dynamic values
        $api_response->name = $theme_data_from_index->name ?? $args->slug;
        $api_response->slug = $args->slug;
        $api_response->version = $display_version; // Use display version (normalized)
        $api_response->author = $theme_data_from_index->author ?? '';
        $api_response->author_profile = $theme_data_from_index->author_url ?? ''; // Match your JSON field: author_url

        $api_response->requires = $theme_data_from_index->requires ?? '6.0'; // Default from your getThemeInformation
        $api_response->tested = $theme_data_from_index->tested ?? '6.7';   // Default from your getThemeInformation
        $api_response->requires_php = $theme_data_from_index->requires_php ?? '8.1'; // Default from your getThemeInformation

        $api_response->homepage = $theme_data_from_index->homepage ?? '';
        $api_response->preview_url = $theme_data_from_index->preview_url ?? ''; // Assuming this exists in theme's JSON
        $api_response->screenshot_url = $theme_data_from_index->screenshot_url ?? ''; // Assuming this exists

        $api_response->rating = $theme_data_from_index->rating ?? 0;
        $api_response->num_ratings = $theme_data_from_index->num_ratings ?? 0;
        $api_response->downloaded = $theme_data_from_index->downloaded ?? 0;
        $api_response->last_updated = $theme_data_from_index->last_updated ?? date('Y-m-d'); // Or a field from unrepress_meta if available

        // Sections: description, installation, changelog
        $api_response->sections = [];
        if (isset($theme_data_from_index->sections) && is_object($theme_data_from_index->sections)) {
            $api_response->sections['description'] = $theme_data_from_index->sections->description ?? '';
            $api_response->sections['installation'] = $theme_data_from_index->sections->installation ?? '';
            $api_response->sections['changelog'] = $theme_data_from_index->sections->changelog ?? '';
        } elseif (isset($theme_data_from_index->description)) { // Fallback for flat description
            $api_response->sections['description'] = $theme_data_from_index->description;
        }

        // Tags
        $api_response->tags = $theme_data_from_index->tags ?? [];
        if (is_object($api_response->tags)) { // WordPress expects an array for tags in some contexts
            $api_response->tags = (array) $api_response->tags;
        }

        // Crucial: Download link and package
        $api_response->download_link = $download_url;
        $api_response->package = $download_url;

        return $api_response;
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
        $this->helpers->cleanAfterUpdate($upgrader, $options, $this->cache_key, 'theme');
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

    /**
     * Get the latest version from unrepress_meta->tags URL.
     *
     * @param object $theme_data Theme data object from the UnrePress index JSON file.
     * @return string|false Version string or false on failure.
     */
    protected function getLatestVersionFromMeta($theme_data)
    {
        // Ensure $theme_data itself is an object and has unrepress_meta property
        if (!is_object($theme_data) || !isset($theme_data->unrepress_meta) || !is_object($theme_data->unrepress_meta)) {
            unrepress_debug('getLatestVersionFromMeta: Basic theme_data structure invalid or unrepress_meta missing/not an object for theme: ' . (isset($theme_data->slug) ? $theme_data->slug : 'unknown'));
            // Fallback logic as before
            if (is_object($theme_data) && !empty($theme_data->version)) {
                 $mock_tag = new \stdClass();
                 $mock_tag->name = $theme_data->version;
                 unrepress_debug('getLatestVersionFromMeta: Falling back to version from theme JSON (structure issue): ' . $theme_data->version);
                 return $mock_tag;
            }
            return false;
        }

        // Now we know $theme_data->unrepress_meta is an object.
        // Check required properties within unrepress_meta
        $meta = $theme_data->unrepress_meta; // Use a shorter variable for clarity
        if (
            empty($meta->tags) || // Check for non-empty string for tags URL
            !isset($meta->update_from) ||
            $meta->update_from !== 'tags' ||
            !is_string($meta->tags) // Ensure tags is a string before passing to normalizeTagUrl
        ) {
            unrepress_debug('getLatestVersionFromMeta: unrepress_meta missing critical string properties (tags, update_from), or update_from not set to "tags" for theme: ' . ($theme_data->slug ?? 'unknown'));
            // Fallback logic as before
            if (!empty($theme_data->version)) {
                 $mock_tag = new \stdClass();
                 $mock_tag->name = $theme_data->version;
                 unrepress_debug('getLatestVersionFromMeta: Falling back to version from theme JSON (meta content issue): ' . $theme_data->version);
                 return $mock_tag;
            }
            return false;
        }

        // Proceed with valid meta
        $repository_url = (isset($meta->repository) && is_string($meta->repository)) ? $meta->repository : '';
        $tags_url = $this->helpers->normalizeTagUrl($meta->tags, $repository_url);
        unrepress_debug('getLatestVersionFromMeta: Fetching tags from: ' . $tags_url . ' for theme: ' . ($theme_data->slug ?? 'unknown'));

        $response = wp_remote_get($tags_url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json', // GitHub and GitLab usually provide JSON for /tags API
            ],
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            unrepress_debug('getLatestVersionFromMeta: Error fetching tags or bad response code for theme: ' . ($theme_data->slug ?? 'unknown') . ' Error: ' . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response)));
            return false;
        }

        $tags_body = json_decode(wp_remote_retrieve_body($response));
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($tags_body) || empty($tags_body)) {
            unrepress_debug('getLatestVersionFromMeta: JSON decode error or empty/invalid tags array for theme: ' . ($theme_data->slug ?? 'unknown') . ' Error: ' . json_last_error_msg());
            return false;
        }

        $latest_tag = $this->helpers->getNewestVersionFromTags($tags_body);
        if (!$latest_tag || empty($latest_tag->name)) {
            unrepress_debug('getLatestVersionFromMeta: Could not determine newest tag from body for theme: ' . ($theme_data->slug ?? 'unknown'));
            return false;
        }

        unrepress_debug('getLatestVersionFromMeta: Determined latest tag: ' . ($latest_tag->name ?? 'N/A') . ' for theme: ' . ($theme_data->slug ?? 'unknown'));
        return $latest_tag; // Return the whole tag object
    }

    /**
     * Construct the download URL for the theme based on the version and unrepress_meta.
     *
     * @param object $theme_data Theme data object from the UnrePress index JSON file.
     * @param string|null $tag_name The specific tag name (e.g., "v1.2.3" or "1.2.3") to download. Null if not found.
     * @return string|false The direct download URL or false on failure.
     */
    private function getDownloadUrlFromMeta($theme_data, $tag_name)
    {
        if (empty($tag_name) || empty($theme_data->unrepress_meta->repository)) {
            unrepress_debug('getDownloadUrlFromMeta: Missing tag_name or repository URL for theme: ' . ($theme_data->slug ?? 'unknown'));
            return false;
        }

        $repo_url = $theme_data->unrepress_meta->repository;
        // Basic GitHub URL construction: REPO_URL/archive/refs/tags/vTAG.zip or REPO_URL/archive/refs/tags/TAG.zip
        // Some use REPO_URL/releases/download/TAG/archive_name.zip
        // This needs to be robust or configurable based on provider or `update_from` strategy if it varies.
        // For now, assume GitHub tag archive structure.

        $download_url = '';
        // Check if the repository is GitHub
        if (strpos($repo_url, 'github.com') !== false) {
            // Attempt with common GitHub tag archive formats
            $tag_prefixed_v = 'v' . $tag_name;
            $tag_bare = $tag_name;

            // Format 1: /archive/refs/tags/vX.Y.Z.zip
            $url1 = rtrim($repo_url, '/') . '/archive/refs/tags/' . $tag_prefixed_v . '.zip';
            // Format 2: /archive/refs/tags/X.Y.Z.zip
            $url2 = rtrim($repo_url, '/') . '/archive/refs/tags/' . $tag_bare . '.zip';
            // Format 3: /releases/download/vX.Y.Z/theme-slug.zip (less common for general tags, more for releases)
            // Format 4: /releases/download/X.Y.Z/theme-slug.zip
            // We would typically need to know the asset name for release downloads.
            // For simplicity with tags, we prioritize the /archive/refs/tags/ structure.

            // To be robust, UnrePress could HEAD check these URLs or have a more deterministic way from unrepress_meta.
            // For now, let's prefer a common one. Helpers.php might have a more advanced getDownloadUrlForTag.
            // Let's assume a helper function `getGithubDownloadUrlForTag` exists or is added to `Helpers.php`

            $download_url = $this->helpers->getDownloadUrlForProviderTag(
                $repo_url,
                $tag_name,
                $theme_data->slug ?? 'theme', // asset name hint
                'github' // provider hint
            );

        } else {
            // Add logic for other providers (GitLab, Bitbucket) if needed
            unrepress_debug('getDownloadUrlFromMeta: Non-GitHub repositories not yet fully supported for dynamic URL generation. Repo: ' . $repo_url);
            // Fallback to a direct download_url if present in unrepress_meta or theme_data itself, as a last resort.
            if (!empty($theme_data->download_url)) {
                 return $theme_data->download_url;
            }
            return false;
        }

        unrepress_debug('getDownloadUrlFromMeta: Constructed download URL: ' . $download_url . ' for theme: ' . ($theme_data->slug ?? 'unknown'));
        return $download_url;
    }
}
