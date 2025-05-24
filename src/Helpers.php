<?php

namespace UnrePress;

/** @package UnrePress */
class Helpers
{
    private $updateLogFile = 'unrepress_update_log.txt';

    /**
     * Removes a directory and all its contents recursively using WP_Filesystem.
     *
     * @param string $dir Full path to the directory to be removed.
     *
     * @return bool True on success, false on failure.
     */
    public function removeDirectory($dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        if (!class_exists('WP_Filesystem')) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
        }

        // Initialize the WP_Filesystem
        global $wp_filesystem;
        WP_Filesystem();

        // Use rmdir() to remove the directory and all its contents recursively
        $result = $wp_filesystem->rmdir($dir, true); // `true` for recursive deletion

        if (!$result) {
            if (is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->has_errors()) {
                return $wp_filesystem->errors->get_error_message();
            }

            return false;
        }

        return true;
    }

    /**
     * Recursively copies all files and directories from one directory to another using WP_Filesystem.
     *
     * @param string $source Full path to the source directory.
     * @param string $destination Full path to the destination directory.
     *
     * @return bool True on success, false on failure.
     */
    public function copyFiles($source, $destination): bool
    {
        if (!class_exists('WP_Filesystem')) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
        }

        // Initialize the WP_Filesystem
        global $wp_filesystem;
        WP_Filesystem();

        // Use copy_dir to copy source to destination
        $result = copy_dir($source, $destination);

        if (is_wp_error($result)) {
            // Handle the error if copying fails
            return $result->get_error_message();
        }

        return true;
    }

    /**
     * Write an update message (for public viewing) to the unrepress update log.
     *
     * @param string $message The message to write
     * @return void
     */
    public function writeUpdateLog($message)
    {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        $updateLogFile = UNREPRESS_TEMP_PATH . $this->updateLogFile;
        $updateLogDir = dirname($updateLogFile);

        // Create directory if it doesn't exist
        if (!$wp_filesystem->exists($updateLogDir)) {
            if (!$wp_filesystem->mkdir($updateLogDir, 0755)) {
                Debugger::log('Unable to create directory at ' . $updateLogDir);
                return;
            }
        }

        // Ensure we can write to the file
        if (!$wp_filesystem->exists($updateLogFile)) {
            if (!$wp_filesystem->touch($updateLogFile)) {
                Debugger::log('Unable to create log file at ' . $updateLogFile);
                return;
            }
        }

        $current_content = $wp_filesystem->exists($updateLogFile) ? $wp_filesystem->get_contents($updateLogFile) : '';
        //$timestamp = current_time('mysql');
        $new_content = $current_content . "{$message}\n";

        if (!$wp_filesystem->put_contents($updateLogFile, $new_content, FS_CHMOD_FILE)) {
            Debugger::log('Unable to write to log file at ' . $updateLogFile);
        }
    }

    /**
     * Clear the unrepress update log.
     *
     * @return void
     */
    public function clearUpdateLog()
    {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        $updateLogFile = UNREPRESS_TEMP_PATH . $this->updateLogFile;
        $updateLogDir = dirname($updateLogFile);

        // Create directory if it doesn't exist
        if (!$wp_filesystem->exists($updateLogDir)) {
            if (!$wp_filesystem->mkdir($updateLogDir, 0755)) {
                Debugger::log('Unable to create directory at ' . $updateLogDir);
                return;
            }
        }

        // Create or clear the file
        if (!$wp_filesystem->put_contents($updateLogFile, '', FS_CHMOD_FILE)) {
            Debugger::log('Unable to clear log file at ' . $updateLogFile);
        }
    }

    /**
     * Move/rename a directory, using WP_Filesystem.
     *
     * @param string $source The source directory to rename.
     * @param string $destination The new directory name.
     *
     * @return bool True on success, false on failure.
     */
    public function moveDirectory($source, $destination): bool
    {
        if (!class_exists('WP_Filesystem')) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
        }

        // Initialize the WP_Filesystem
        global $wp_filesystem;
        WP_Filesystem();

        // Use move() to rename the directory
        return $wp_filesystem->move($source, $destination);
    }

    /**
     * Normalize a Github Tags URL, to be used with the Github API.
     * It can also construct the full tags API URL if a base repository URL is provided
     * and the initial tags URL is just a path segment (e.g., "/tags").
     *
     * @param string $tags_or_repo_url The URL to the tags endpoint OR the base repository URL.
     *                                If it's a full tags API URL, it will be returned as is (or with minor adjustments).
     *                                If it's a base repo URL, $tags_path_segment must be provided.
     * @param string|null $tags_path_segment Optional. If $tags_or_repo_url is a base repo URL,
     *                                      this should be the path segment for tags (e.g., "/tags", or an empty string if $tags_or_repo_url already includes it implicitly and just needs API conversion).
     *
     * @return string The normalized and potentially completed tags API URL.
     */
    public function normalizeTagUrl($tags_or_repo_url, $tags_path_segment = null)
    {
        $url_to_process = trim($tags_or_repo_url);
        $segment = $tags_path_segment ? trim($tags_path_segment) : '';

        // Case 1: $url_to_process is already a full GitHub API tags URL
        if (strpos($url_to_process, 'api.github.com/repos/') !== false && strpos($url_to_process, '/tags') !== false) {
            Debugger::log('normalizeTagUrl: Already a full API tags URL: ' . $url_to_process);
            return rtrim($url_to_process, '/'); // Ensure no trailing slash for consistency
        }

        // Case 2: $url_to_process is a browser URL to a GitHub repository's tags page (e.g., github.com/user/repo/tags)
        if (strpos($url_to_process, 'github.com/') !== false && strpos($url_to_process, '/tags') !== false) {
            $api_url = preg_replace('~github\.com/([^/]+)/([^/]+)(/tags)?~i', 'api.github.com/repos/$1/$2/tags', $url_to_process);
            Debugger::log('normalizeTagUrl: Converted browser tags URL to API URL: ' . $api_url);
            return rtrim($api_url, '/');
        }

        // Case 3: $url_to_process is a base repository URL (e.g., github.com/user/repo) and $segment might be "/tags" or empty
        if (strpos($url_to_process, 'github.com/') !== false) {
            // Ensure it's a base repo URL structure, not something else
            if (preg_match('~github\.com/([^/]+)/([^/]+)/?$~i', rtrim($url_to_process, '/'), $matches)) {
                $user = $matches[1];
                $repo = $matches[2];
                $base_api_url = "https://api.github.com/repos/{$user}/{$repo}";

                // Determine the final tags path
                $final_tags_path = '/tags'; // Default
                if (!empty($segment) && $segment !== '/tags') {
                    // If segment is provided and isn't just "/tags", append it.
                    // This handles cases where $segment might be a specific ref like "refs/tags" but shouldn't usually be needed if meta->tags is the tags API endpoint itself.
                    $final_tags_path = strpos($segment, '/') === 0 ? $segment : '/' . $segment;
                } elseif (empty($segment)) {
                     // If segment is empty, we assume the base repo URL needs /tags appended for the API.
                }
                // if $segment is exactly '/tags', it's already handled by $final_tags_path default

                $full_api_url = $base_api_url . rtrim($final_tags_path, '/');
                Debugger::log('normalizeTagUrl: Constructed API URL from base repo: ' . $full_api_url);
                return $full_api_url;
            }
        }

        // Case 4: $url_to_process is not a GitHub URL, or it's a non-standard GitHub URL we don't automatically convert.
        // It might be a direct API endpoint for GitLab, Bitbucket, etc.
        // Or, $url_to_process is a partial path like "/tags" and $segment contains the base repo URL (less common)
        if (!empty($segment) && filter_var($segment, FILTER_VALIDATE_URL) && strpos($url_to_process, '/') === 0) {
             // This is a less common scenario: $url_to_process is a path, $segment is the base URL.
             // Example: $url_to_process = "/tags", $segment = "https://api.somegit.com/repos/foo/bar"
             $combined_url = rtrim($segment, '/') . $url_to_process;
             Debugger::log('normalizeTagUrl: Combined base URL from segment with path: ' . $combined_url);
             return $combined_url;
        }

        // Default: Return the original URL if no specific GitHub transformation applies
        // It might be a pre-formed API URL for another service or already correct.
        Debugger::log('normalizeTagUrl: No specific GitHub normalization applied, returning: ' . $url_to_process);
        return rtrim($url_to_process, '/');
    }

    /**
     * Gets the newest version from a list of tags.
     *
     * @param array $tags a json body response
     *
     * @return object|null The newest version, or null if none was found.
     */
    public function getNewestVersionFromTags($tags)
    {
        if (empty($tags) || !is_array($tags)) {
            Debugger::log('getNewestVersionFromTags: No tags provided or not an array.');
            return null;
        }

        Debugger::log('getNewestVersionFromTags: Processing ' . count($tags) . ' tags.');

        $v_tags = [];
        $numeric_tags = [];
        $other_tags = [];

        foreach ($tags as $tag) {
            if (!is_object($tag) || !isset($tag->name) || !is_string($tag->name)) {
                Debugger::log('getNewestVersionFromTags: Skipping invalid tag object or missing name.');
                continue;
            }
            $tag_name = $tag->name;
            $tag_type = '';

            // Attempt to normalize the version string by removing 'v' prefix
            $normalized_version = ltrim($tag_name, 'v');

            // Further clean up to keep only numbers and dots for robust comparison
            // but preserve the original $tag_name for download URL construction.
            $comparable_version = preg_replace('/[^0-9.]/', '', $normalized_version);


            if (strpos($tag_name, 'v') === 0 && preg_match('/^[0-9.]+$/', $comparable_version)) {
                $tag_type = 'v_prefixed';
            } elseif (preg_match('/^[0-9.]+$/', $comparable_version)) {
                // This will catch tags like "1.0.0", "1.2", "1"
                $tag_type = 'numeric';
            } else {
                $tag_type = 'other';
            }

            Debugger::log('Tag: ' . $tag_name . ' | Normalized for compare: ' . $comparable_version . ' | Type: ' . $tag_type);

            if ($tag_type === 'v_prefixed') {
                $v_tags[] = [
                    'tag' => $tag, // Store the original tag object
                    'normalized' => $comparable_version
                ];
            } elseif ($tag_type === 'numeric') {
                $numeric_tags[] = [
                    'tag' => $tag,
                    'normalized' => $comparable_version
                ];
            } else {
                $other_tags[] = [
                    'tag' => $tag,
                    'normalized' => $comparable_version // May not be truly comparable, but store it
                ];
            }
        }

        Debugger::log('V_TAGS count: ' . count($v_tags));
        Debugger::log('NUMERIC_TAGS count: ' . count($numeric_tags));
        Debugger::log('OTHER_TAGS count: ' . count($other_tags));

        // Custom version comparison function
        $version_compare = function ($a, $b) {
            // Use version_compare for robust comparison of version strings
            return version_compare($b['normalized'], $a['normalized']); // Descending order
        };

        // Prioritize v-prefixed tags, then numeric-only tags
        if (!empty($v_tags)) {
            usort($v_tags, $version_compare);
            Debugger::log('Latest v_tag selected: ' . ($v_tags[0]['tag']->name ?? 'Error'));
            return $v_tags[0]['tag']; // Return the full tag object
        } elseif (!empty($numeric_tags)) {
            usort($numeric_tags, $version_compare);
            Debugger::log('Latest numeric_tag selected: ' . ($numeric_tags[0]['tag']->name ?? 'Error'));
            return $numeric_tags[0]['tag'];
        }

        // If only other tags exist, return the first one (no reliable sorting here, but it's a fallback)
        if (!empty($other_tags)) {
            Debugger::log('Latest other_tag selected (fallback): ' . ($other_tags[0]['tag']->name ?? 'Error'));
            return $other_tags[0]['tag'];
        }

        Debugger::log('No suitable latest tag found after processing all tags.');
        return null;
    }

    /**
     * Constructs a download URL for a given tag from a specific provider.
     *
     * @param string $repo_url The base repository URL.
     * @param string $tag_name The version/tag string (e.g., "1.2.3" or "v1.2.3").
     * @param string $slug Slug of the theme/plugin, used as a hint for some URL structures.
     * @param string $provider The provider hint (e.g., "github"). Currently only supports "github".
     * @return string|false The download URL or false on failure.
     */
    public function getDownloadUrlForProviderTag($repo_url, $tag_name, $slug, $provider = 'github')
    {
        if (empty($repo_url) || empty($tag_name)) {
            Debugger::log('getDownloadUrlForProviderTag: Missing repo_url or tag_name.');
            return false;
        }

        $download_url = false;
        // $version_bare = ltrim($tag_name, 'v'); // No longer needed here, using exact tag_name
        // $version_prefixed_v = 'v' . $version_bare; // No longer needed here

        if (strtolower($provider) === 'github') {
            if (strpos($repo_url, 'github.com') !== false) {
                // GitHub provides ZIPs of tags at /archive/refs/tags/TAG_NAME.zip
                // Construct URL directly using the provided $tag_name
                $download_url = rtrim($repo_url, '/') . '/archive/refs/tags/' . $tag_name . '.zip';

                Debugger::log('getDownloadUrlForProviderTag (GitHub): Constructed URL: ' . $download_url . ' for repo: ' . $repo_url . ' tag_name: ' . $tag_name);

            } else {
                Debugger::log('getDownloadUrlForProviderTag: Repo URL does not seem to be a GitHub URL: ' . $repo_url);
                return false;
            }
        } else {
            Debugger::log('getDownloadUrlForProviderTag: Provider ' . $provider . ' not yet supported.');
            return false;
        }

        return $download_url;
    }

    /**
     * Fix the source directory name during plugin/theme updates
     * This prevents GitHub's repository naming format from being used.
     *
     * @param string $source        File source location
     * @param string $remote_source Remote file source location
     * @param string $slug          Slug of the plugin or theme
     * @param string $type          Type of update (plugin or theme)
     *
     * @return string|WP_Error
     */
    public static function fixSourceDir($source, $remote_source, $slug, $type = 'theme')
    {
        Debugger::log('Received values for fixSourceDir:');
        Debugger::log('source: ' . $source);
        Debugger::log('remote_source: ' . $remote_source);
        Debugger::log('slug: ' . $slug);
        Debugger::log('type: ' . $type);

        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (!WP_Filesystem()) {
            return $source;
        }

        if(is_array($slug) && isset($slug['slug'])) {
            $slug = $slug['slug'];
        }

        // Remove unwanted directories like .git, .github, etc.
        $directories_to_remove = ['.git', '.github', '.wordpress-org', '.ci', '.gitignore'];
        foreach ($directories_to_remove as $dir) {
            $dir_path = $source . $dir;
            if ($wp_filesystem->exists($dir_path)) {
                $result = $wp_filesystem->delete($dir_path, true);
            }
        }

        // For themes, we want to move it directly to wp-content/upgrade/slug
        if ($type === 'theme') {
            $source_dir = untrailingslashit($source);
            $new_source = WP_CONTENT_DIR . '/upgrade/' . $slug;

            // If the target directory already exists, remove it
            if ($wp_filesystem->exists($new_source)) {
                $wp_filesystem->delete($new_source, true);
            }

            // Create the target directory if it doesn't exist
            wp_mkdir_p(dirname($new_source));

            // Move the theme to the new location
            if (!$wp_filesystem->move($source_dir, $new_source)) {
                return $source;
            }

            return trailingslashit($new_source);
        }

        // For plugins and other types
        if ($type === 'plugin') {
            // Extract just the plugin directory name from the full slug path
            $clean_slug = '';

            if (is_string($slug)) {
                $clean_slug = explode('/', $slug)[0];
            }

            Debugger::log('Cleaned slug: ' . $clean_slug);

            if (empty($clean_slug)) {
                return $source;
            }

            // Get the parent directory of source
            $parent_dir = dirname($source);
            $current_dir = basename($source);

            // If the current directory doesn't match the slug
            if ($current_dir !== $clean_slug) {
                $new_source = trailingslashit($parent_dir) . $clean_slug;

                // First remove target directory if it exists
                if ($wp_filesystem->exists($new_source)) {
                    $wp_filesystem->delete($new_source, true);
                }

                // Move to the correct directory
                $result = $wp_filesystem->move($source, $new_source);
                if ($result) {
                    return trailingslashit($new_source);
                }
            }
        }

        return $source;
    }

    /**
     * Clean up a directory by deleting all files and subdirectories.
     *
     * @param string $dir Directory to clean up
     *
     * @return void
     */
    public function cleanDirectory($dir)
    {
        if (!function_exists('WP_Filesystem')) {
            return;
        }

        global $wp_filesystem;

        // Our $dir is inside wp-content
        $dir = WP_CONTENT_DIR . '/' . $dir;

        // Scan everything in the directory and delete it. Files and directories.
        $files = $wp_filesystem->dirlist($dir);
        if (!$files) {
            return;
        }

        foreach ($files as $filename => $file_info) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }
            $path = $dir . '/' . $filename;
            if ($wp_filesystem->is_dir($path)) {
                $wp_filesystem->delete($path, true);
            } else {
                $wp_filesystem->delete($path);
            }
        }
    }

    /**
     * Unified cleanAfterUpdate function.
     *
     * @param object $upgrader
     * @param array  $options
     * @param string $cache_key
     *
     * @return void
     */
    public function cleanAfterUpdate($upgrader, $options, string $cache_key)
    {
        $slug = '';
        $type = '';

        // Determine if it's a plugin or theme update
        if ($options['type'] === 'plugin' && $options['action'] === 'update') {
            $slug = $options['plugins'][0];
            $type = 'plugin';
        } elseif ($options['type'] === 'theme' && $options['action'] === 'update') {
            $slug = $options['themes'][0] ?? '';
            $type = 'theme';
        }

        if (!empty($slug)) {
            // Clean the cache for the updated item
            delete_transient($cache_key . $slug);
            if ($type === 'theme') {
                delete_transient($cache_key . 'remote-version-' . $slug);
                delete_transient($cache_key . 'download-url-' . $slug);
            }

            // Clean up the upgrade and backup directories
            $this->clearTempDirectories();

            // Clean transients
            $this->clearUpdateTransients();
        }
    }

    /**
     * Clear the temp directories.
     *
     * @return void
     */
    public function clearTempDirectories()
    {
        // Clean up the upgrade and backup directories
        $this->cleanDirectory(WP_CONTENT_DIR . '/upgrade');
        $this->cleanDirectory(WP_CONTENT_DIR . '/upgrade-temp-backup');
    }

    /**
     * Clear all WordPress update related transients
     * This forces WordPress to check for updates on the next request.
     *
     * @return void
     */
    public static function clearUpdateTransients(): void
    {
        delete_transient(UNREPRESS_PREFIX . 'updates_count');
        delete_transient(UNREPRESS_PREFIX . 'updates_core_latest_version');
        delete_transient(UNREPRESS_PREFIX . 'log_last_pos');

        delete_transient('update_plugins');
        delete_transient('update_themes');
        delete_transient('update_core');

        wp_version_check(); // Force immediate core update check
    }
}
