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
     *
     * @param string $url The URL to normalize.
     *
     * @return string The normalized URL.
     */
    public function normalizeTagUrl($url)
    {
        // Is github.com url?
        if (!str_contains($url, 'github.com')) {
            return $url;
        }

        // Does it has "api." on it?
        if (str_contains($url, 'api.')) {
            return $url;
        }

        return str_replace('github.com', 'api.github.com/repos', $url);
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
        if (empty($tags)) {
            return null;
        }

        // First, separate tags into groups (v-prefixed, numeric-only, and others)
        $v_tags = [];
        $numeric_tags = [];
        $other_tags = [];

        foreach ($tags as $tag) {
            $name = $tag->name;

            // Remove 'v' prefix if it exists for comparison
            $version = ltrim($name, 'v');

            // Check if the remaining string contains only numbers and dots
            if (preg_match('/^[\d.]+$/', $version)) {
                if (strpos($name, 'v') === 0) {
                    $v_tags[] = [
                        'tag' => $tag,
                        'normalized' => $version,
                    ];
                } else {
                    $numeric_tags[] = [
                        'tag' => $tag,
                        'normalized' => $version,
                    ];
                }
            } else {
                $other_tags[] = [
                    'tag' => $tag,
                    'normalized' => $version,
                ];
            }
        }

        // Custom version comparison function
        $version_compare = function ($a, $b) {
            $a_parts = explode('.', $a['normalized']);
            $b_parts = explode('.', $b['normalized']);

            // Pad arrays to equal length
            $max_length = max(count($a_parts), count($b_parts));
            $a_parts = array_pad($a_parts, $max_length, '0');
            $b_parts = array_pad($b_parts, $max_length, '0');

            // Compare each part numerically
            for ($i = 0; $i < $max_length; $i++) {
                $a_num = intval($a_parts[$i]);
                $b_num = intval($b_parts[$i]);

                if ($a_num !== $b_num) {
                    return $b_num - $a_num; // Descending order
                }
            }

            return 0;
        };

        // Sort each group
        usort($v_tags, $version_compare);
        usort($numeric_tags, $version_compare);
        usort($other_tags, $version_compare);

        // Return the highest version in order of preference:
        // 1. Numeric tags (e.g., "9.4.5")
        // 2. v-prefixed tags (e.g., "v2.7.7")
        // 3. Other tags
        if (!empty($numeric_tags)) {
            return $numeric_tags[0]['tag'];
        } elseif (!empty($v_tags)) {
            return $v_tags[0]['tag'];
        } elseif (!empty($other_tags)) {
            return $other_tags[0]['tag'];
        }

        return null;
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
