<?php

namespace UnrePress\Index;

use UnrePress\Helpers;
use UnrePress\Updater\UpdateLock;
use UnrePress\UnrePress;

class ThemesIndex extends Index
{
    private $helpers;

    private $updateLock;

    private $provider = 'github';

    private UnrePress $unrepress;

    public function __construct()
    {
        $this->helpers = new Helpers();
        $this->updateLock = new UpdateLock();
        $this->unrepress = new UnrePress();
    }

    /**
     * Get a theme's JSON file from UnrePress index
     *
     * @var string
     *
     * @return array|false
     */
    public function getThemeJson($themeSlug)
    {
        unrepress_debug('ThemesIndex::getThemeJson called for slug: ' . $themeSlug);

        $transientName = UNREPRESS_PREFIX . 'index_theme_' . $themeSlug;
        $themeJson = get_transient($transientName);

        if (false === $themeJson) {
            unrepress_debug('ThemesIndex::getThemeJson - No cached data, fetching from index');

            $themeJsonUrl = $this->getUrlForSlug($themeSlug, 'theme');
            unrepress_debug('ThemesIndex::getThemeJson - Theme URL: ' . $themeJsonUrl);

            $themeJson = wp_remote_get($themeJsonUrl);

            if (is_wp_error($themeJson)) {
                unrepress_debug('ThemesIndex::getThemeJson - WP Error: ' . $themeJson->get_error_message());
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($themeJson);
            unrepress_debug('ThemesIndex::getThemeJson - Response code: ' . $response_code);

            if ($response_code !== 200) {
                unrepress_debug('ThemesIndex::getThemeJson - Invalid response code: ' . $response_code);
                return false;
            }

            $body = wp_remote_retrieve_body($themeJson);
            unrepress_debug('ThemesIndex::getThemeJson - Response body: ' . $body);

            $themeJson = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                unrepress_debug('ThemesIndex::getThemeJson - JSON decode error: ' . json_last_error_msg());
                return false;
            }

            unrepress_debug('ThemesIndex::getThemeJson - Decoded theme JSON: ' . print_r($themeJson, true));

            set_transient($transientName, $themeJson, DAY_IN_SECONDS);
        } else {
            unrepress_debug('ThemesIndex::getThemeJson - Returning cached data for: ' . $themeSlug);
        }

        return $themeJson;
    }

    /**
     * Search themes in the UnrePress index
     * Since search is not available in the index, this method will return false
     *
     * @param string $search_term The search term
     * @param int $page Page number for pagination
     * @param int $per_page Number of themes per page
     * @return object|false Always returns false since search is not supported
     */
    public function searchThemes($search_term, $page = 1, $per_page = 24)
    {
        unrepress_debug('ThemesIndex::searchThemes called with term: ' . $search_term . ', page: ' . $page . ', per_page: ' . $per_page);

        $index_data = $this->getThemesIndex(); // Returns the full JSON object, e.g., {"schema_version": ..., "themes": [...]}

        // Prepare a default empty response object
        $empty_response = new \stdClass();
        $empty_response->page = (int) $page;
        $empty_response->pages = 0;
        $empty_response->total = 0;
        $empty_response->themes = [];

        // Check if index_data is valid and contains the 'themes' array
        if (empty($index_data) || !is_array($index_data) || !isset($index_data['themes']) || !is_array($index_data['themes'])) {
            unrepress_debug('ThemesIndex::searchThemes - Invalid or empty themes index data, or "themes" array missing.');
            return $empty_response;
        }

        $all_theme_items = $index_data['themes']; // Access the actual array of theme items
        $search_term_lower = strtolower(sanitize_text_field($search_term));
        $found_themes = [];

        foreach ($all_theme_items as $theme_item) {
            // json_decode(..., true) in getThemesIndex (assumed) makes $theme_item an associative array
            if (!is_array($theme_item)) {
                unrepress_debug('ThemesIndex::searchThemes - Skipping invalid theme entry: ' . print_r($theme_item, true));
                continue;
            }

            $name = strtolower($theme_item['name'] ?? '');
            $slug = strtolower($theme_item['slug'] ?? '');

            // Description is a string as per your example
            $description = strtolower($theme_item['description'] ?? '');

            // Tags is an array of strings as per your example
            $tags_raw = $theme_item['tags'] ?? [];
            $tags_string = '';
            if (is_array($tags_raw)) {
                $tags_string = strtolower(implode(' ', $tags_raw));
            }

            // Search in name, slug, description, and tags
            if (str_contains($name, $search_term_lower) ||
                str_contains($slug, $search_term_lower) ||
                str_contains($description, $search_term_lower) ||
                str_contains($tags_string, $search_term_lower)) {
                // Ensure the theme item itself is added, which should be an array
                $found_themes[] = $theme_item;
            }
        }

        unrepress_debug('ThemesIndex::searchThemes - Found ' . count($found_themes) . ' themes matching "' . $search_term . '" before pagination.');

        // Apply pagination
        $total_found = count($found_themes);

        // Ensure $per_page is a positive integer for pagination calculation
        $per_page_safe = ($per_page > 0) ? (int) $per_page : 24; // Default to 24 if invalid

        $num_pages = ceil($total_found / $per_page_safe);
        if ($total_found === 0) {
            $num_pages = 0; // No pages if no themes found
        }


        $start_index = ((int) $page - 1) * $per_page_safe;
        $paged_themes = array_slice($found_themes, $start_index, $per_page_safe);

        // Build response object
        $response_obj = new \stdClass();
        $response_obj->page = (int) $page;
        $response_obj->pages = (int) $num_pages;
        $response_obj->total = $total_found;
        $response_obj->themes = $paged_themes; // This is an array of theme arrays

        unrepress_debug('ThemesIndex::searchThemes - Paginated response: ' . print_r($response_obj, true));

        return $response_obj;
    }

    /**
     * Get featured themes from UnrePress index
     *
     * @param int $page Page number for pagination
     * @param int $per_page Number of themes per page
     * @return object|false Featured themes object or false on error
     */
    public function getFeaturedThemes($page = 1, $per_page = 24)
    {
        unrepress_debug('ThemesIndex::getFeaturedThemes called with page: ' . $page . ', per_page: ' . $per_page);

        // Get main index
        $main_index = $this->unrepress->index();

        unrepress_debug('ThemesIndex::getFeaturedThemes - Main index: ' . print_r($main_index, true));

        // Check if featured themes URL exists and is not null
        if (!$main_index || !isset($main_index['themes']['featured']) || $main_index['themes']['featured'] === null) {
            unrepress_debug('ThemesIndex::getFeaturedThemes - No themes.featured URL in main index or it is null');
            return false;
        }

        $featured_url = $main_index['themes']['featured'];
        unrepress_debug('ThemesIndex::getFeaturedThemes - Featured URL: ' . $featured_url);

        // Use a transient that depends on the featured_url to ensure fresh data if the URL changes
        // Page and per_page are for pagination of the already fetched list of slugs + full data
        $slugs_transient_key = UNREPRESS_PREFIX . 'featured_theme_slugs_' . md5($featured_url);
        $featured_slugs_data = get_transient($slugs_transient_key);

        if (false === $featured_slugs_data) {
            unrepress_debug('ThemesIndex::getFeaturedThemes - No cached slugs, fetching from: ' . $featured_url);
            $response = wp_remote_get($featured_url, [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);

            if (is_wp_error($response)) {
                unrepress_debug('ThemesIndex::getFeaturedThemes - WP Error fetching slugs: ' . $response->get_error_message());
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                unrepress_debug('ThemesIndex::getFeaturedThemes - Invalid response code for slugs: ' . $response_code);
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $decoded_body = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                unrepress_debug('ThemesIndex::getFeaturedThemes - JSON decode error for slugs: ' . json_last_error_msg());
                return false;
            }

            if (!isset($decoded_body['featured']) || !is_array($decoded_body['featured'])) {
                unrepress_debug('ThemesIndex::getFeaturedThemes - No featured array in slugs response');
                return false;
            }
            $featured_slugs_data = $decoded_body['featured'];
            set_transient($slugs_transient_key, $featured_slugs_data, 6 * HOUR_IN_SECONDS); // Cache slugs for 6 hours
        } else {
            unrepress_debug('ThemesIndex::getFeaturedThemes - Using cached slugs.');
        }

        unrepress_debug('ThemesIndex::getFeaturedThemes - Featured slugs: ' . print_r($featured_slugs_data, true));

        $all_featured_themes_details = [];
        foreach ($featured_slugs_data as $slug) {
            // getThemeJson fetches the individual theme's JSON file (e.g., themes/g/generatepress.json)
            // It already handles its own transient caching.
            $theme_detail = $this->getThemeJson($slug);
            if ($theme_detail) {
                // Ensure slug is part of the array, as getThemeJson returns the decoded JSON body directly
                if (!isset($theme_detail['slug'])) {
                    $theme_detail['slug'] = $slug;
                }
                $all_featured_themes_details[] = $theme_detail;
            } else {
                unrepress_debug('ThemesIndex::getFeaturedThemes - Could not retrieve details for slug: ' . $slug);
            }
        }

        unrepress_debug('ThemesIndex::getFeaturedThemes - All featured themes details count: ' . count($all_featured_themes_details));

        // Apply pagination to the array of fully detailed themes
        $total_themes = count($all_featured_themes_details);
        $start_index = ($page - 1) * $per_page;
        $paged_themes = array_slice($all_featured_themes_details, $start_index, $per_page);

        // Build response object
        $response_obj = new \stdClass();
        $response_obj->page = $page;
        $response_obj->pages = ceil($total_themes / $per_page);
        $response_obj->total = $total_themes;
        $response_obj->themes = $paged_themes; // This is now an array of arrays (from JSON decodes)

        unrepress_debug('ThemesIndex::getFeaturedThemes - Final paginated response: ' . print_r($response_obj, true));

        // No need to set a transient here for $response_obj as individual theme details are already cached by getThemeJson,
        // and the slugs list is cached above. Pagination is dynamic.

        return $response_obj;
    }

    /**
     * Get the themes index containing all theme data
     *
     * @return array|false Themes index data or false on error
     */
    private function getThemesIndex()
    {
        unrepress_debug('ThemesIndex::getThemesIndex called');

        // Get main index
        $main_index = $this->unrepress->index();

        if (!$main_index || !isset($main_index['themes']['index'])) {
            unrepress_debug('ThemesIndex::getThemesIndex - No themes.index URL in main index');
            return false;
        }

        $index_url = $main_index['themes']['index'];
        unrepress_debug('ThemesIndex::getThemesIndex - Index URL: ' . $index_url);

        $transient_key = UNREPRESS_PREFIX . 'themes_index_data';
        $cached_result = get_transient($transient_key);

        if (false !== $cached_result) {
            unrepress_debug('ThemesIndex::getThemesIndex - Returning cached themes index');
            return $cached_result;
        }

        $response = wp_remote_get($index_url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            unrepress_debug('ThemesIndex::getThemesIndex - WP Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        unrepress_debug('ThemesIndex::getThemesIndex - Response code: ' . $response_code);

        if ($response_code !== 200) {
            unrepress_debug('ThemesIndex::getThemesIndex - Invalid response code: ' . $response_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        unrepress_debug('ThemesIndex::getThemesIndex - Response body: ' . $body);

        $index_data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            unrepress_debug('ThemesIndex::getThemesIndex - JSON decode error: ' . json_last_error_msg());
            return false;
        }

        unrepress_debug('ThemesIndex::getThemesIndex - Index data loaded successfully');

        // Cache for 12 hours
        set_transient($transient_key, $index_data, 12 * HOUR_IN_SECONDS);

        return $index_data;
    }

    /**
     * Find a specific theme in the themes index
     *
     * @param array $themes_index The full themes index
     * @param string $slug The theme slug to find
     * @return array|false Theme data or false if not found
     */
    private function findThemeInIndex($themes_index, $slug)
    {
        unrepress_debug('ThemesIndex::findThemeInIndex - Looking for slug: ' . $slug);

        if (!isset($themes_index['themes']) || !is_array($themes_index['themes'])) {
            unrepress_debug('ThemesIndex::findThemeInIndex - No themes array in index');
            return false;
        }

        foreach ($themes_index['themes'] as $theme) {
            if (isset($theme['slug']) && $theme['slug'] === $slug) {
                unrepress_debug('ThemesIndex::findThemeInIndex - Found theme: ' . $slug);
                return $theme;
            }
        }

        unrepress_debug('ThemesIndex::findThemeInIndex - Theme not found: ' . $slug);
        return false;
    }

    /**
     * Get popular themes from UnrePress index
     * Since popular themes are null in the index, this will return false
     *
     * @param int $page Page number for pagination
     * @param int $per_page Number of themes per page
     * @return object|false Always returns false since popular themes are not available
     */
    public function getPopularThemes($page = 1, $per_page = 24)
    {
        unrepress_debug('ThemesIndex::getPopularThemes called - Popular themes not available (null in index), returning false');
        return false;
    }

    /**
     * Get latest/new themes from UnrePress index
     * Since recent themes are null in the index, this will return false
     *
     * @param int $page Page number for pagination
     * @param int $per_page Number of themes per page
     * @return object|false Always returns false since recent themes are not available
     */
    public function getLatestThemes($page = 1, $per_page = 24)
    {
        unrepress_debug('ThemesIndex::getLatestThemes called - Recent themes not available (null in index), returning false');
        return false;
    }

    /**
     * Get theme information by slug
     *
     * @param string $theme_slug The theme slug
     * @return object|false Theme information object or false on error
     */
    public function getThemeInformation($theme_slug)
    {
        unrepress_debug('ThemesIndex::getThemeInformation called for slug: ' . $theme_slug);

        $theme_slug = sanitize_text_field($theme_slug);
        $transient_key = UNREPRESS_PREFIX . 'theme_info_' . $theme_slug;
        $cached_result = get_transient($transient_key);

        if (false !== $cached_result) {
            unrepress_debug('ThemesIndex::getThemeInformation - Returning cached result for: ' . $theme_slug);
            return $cached_result;
        }

        // Get theme JSON data
        $theme_data = $this->getThemeJson($theme_slug);

        unrepress_debug('ThemesIndex::getThemeInformation - Raw theme data: ' . print_r($theme_data, true));

        if (!$theme_data) {
            unrepress_debug('ThemesIndex::getThemeInformation - No theme data found for: ' . $theme_slug);
            return false;
        }

        // Transform the data to match WordPress themes_api format
        $theme_info = new \stdClass();
        $theme_info->name = $theme_data['name'] ?? $theme_slug;
        $theme_info->slug = $theme_slug;
        $theme_info->version = $theme_data['version'] ?? '1.0.0';
        $theme_info->author = $theme_data['author'] ?? '';
        $theme_info->author_profile = $theme_data['author_url'] ?? '';
        $theme_info->preview_url = $theme_data['preview_url'] ?? '';
        $theme_info->screenshot_url = $theme_data['screenshot_url'] ?? '';
        $theme_info->rating = $theme_data['rating'] ?? 0;
        $theme_info->num_ratings = $theme_data['num_ratings'] ?? 0;
        $theme_info->downloaded = $theme_data['downloaded'] ?? 0;
        $theme_info->last_updated = $theme_data['last_updated'] ?? '';
        $theme_info->requires = $theme_data['requires'] ?? '6.0';
        $theme_info->tested = $theme_data['tested'] ?? '6.7';
        $theme_info->requires_php = $theme_data['requires_php'] ?? '8.1';
        $theme_info->tags = $theme_data['tags'] ?? [];
        $theme_info->homepage = $theme_data['homepage'] ?? '';
        $theme_info->download_link = $theme_data['download_url'] ?? '';
        $theme_info->package = $theme_data['download_url'] ?? '';

        // Add sections
        $theme_info->sections = [];
        if (isset($theme_data['sections']['description'])) {
            $theme_info->sections['description'] = $theme_data['sections']['description'];
        }
        if (isset($theme_data['sections']['installation'])) {
            $theme_info->sections['installation'] = $theme_data['sections']['installation'];
        }
        if (isset($theme_data['sections']['changelog'])) {
            $theme_info->sections['changelog'] = $theme_data['sections']['changelog'];
        }

        unrepress_debug('ThemesIndex::getThemeInformation - Formatted theme info: ' . print_r($theme_info, true));

        // Cache the result for 24 hours
        set_transient($transient_key, $theme_info, DAY_IN_SECONDS);

        return $theme_info;
    }
}
