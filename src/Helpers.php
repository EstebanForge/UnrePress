<?php

namespace UnrePress;

class Helpers
{
    private $updateLogFile = 'unrepress_update_log.txt';

    // Helper function to get directory structure as an array
    public function dirToArray($dir)
    {
        $result = [];
        $cdir = scandir($dir);

        foreach ($cdir as $key => $value) {
            if (! in_array($value, [".", ".."])) {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
                    $result[$value] = $this->dirToArray($dir . DIRECTORY_SEPARATOR . $value);
                } else {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Recursively removes a directory and all its contents.
     *
     * @param string $dir Full path to the directory to be removed.
     *
     * @return bool True on success, false on failure.
     */
    public function removeDirectory($dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }

    /**
     * Removes a directory and all its contents recursively using WP_Filesystem.
     *
     * @param string $dir Full path to the directory to be removed.
     *
     * @return bool True on success, false on failure.
     */
    public function removeDirectoryWPFS($dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        if (! class_exists('WP_Filesystem')) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
        }

        // Initialize the WP_Filesystem
        global $wp_filesystem;
        WP_Filesystem();

        Debugger::log('Removing directory: ' . $dir);

        // Use rmdir() to remove the directory and all its contents recursively
        $result = $wp_filesystem->rmdir($dir, true); // `true` for recursive deletion

        Debugger::log('Result: ' . $result);

        if (! $result) {
            if (is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->has_errors()) {
                return $wp_filesystem->errors->get_error_message();
            }

            return false;
        }

        Debugger::log('Removed directory: ' . $dir);

        return true;
    }

    /**
     * Remove a directory and its contents using WP_Filesystem.
     *
     * @param string $directory The directory to remove.
     *
     * @return bool True on success, false on failure.
     */
    public function removeDirectoryWPFSNew($directory): bool
    {
        if (! class_exists('WP_Filesystem')) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
        }

        // Initialize the WP_Filesystem
        global $wp_filesystem;
        WP_Filesystem();

        // Use delete() to remove the directory and its contents
        return $wp_filesystem->delete($directory, true);
    }

    /**
     * Recursively copies all files and directories from one directory to another.
     *
     * @param string $source Full path to the source directory.
     * @param string $destination Full path to the destination directory.
     *
     * @return bool True on success, false on failure.
     */
    public function copyFiles($source, $destination): bool
    {
        $dir = opendir($source);
        if ($dir === false) {
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $srcFile = $source . '/' . $file;
                $destFile = $destination . '/' . $file;

                if (is_dir($srcFile)) {
                    if (! is_dir($destFile)) {
                        mkdir($destFile, 0755, true);
                    }
                    if (! $this->copyFiles($srcFile, $destFile)) {
                        return false;
                    }
                } else {
                    if (! copy($srcFile, $destFile)) {
                        return false;
                    }
                }
            }
        }
        closedir($dir);

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
    public function copyFilesWPFS($source, $destination): bool
    {
        if (! class_exists('WP_Filesystem')) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
        }

        // Initialize the WP_Filesystem
        WP_Filesystem();

        Debugger::log('Copying directory: ' . $source . ' to ' . $destination);

        // Use copy_dir to copy source to destination
        $result = copy_dir($source, $destination);

        Debugger::log('Result: ' . $result);

        if (is_wp_error($result)) {
            // Handle the error if copying fails
            return $result->get_error_message();
        }

        Debugger::log('Copied directory: ' . $source . ' to ' . $destination);

        return true;
    }

    /**
     * Write an update message (for public viewing) to the unrepress update log
     *
     * @param string $message The message to write
     *
     * @return void
     */
    public function writeUpdateLog($message)
    {
        $updateLogFile = UNREPRESS_TEMP_PATH . $this->updateLogFile;

        if (! file_exists($updateLogFile)) {
            touch($updateLogFile);
        }

        file_put_contents($updateLogFile, $message . "\n", FILE_APPEND);
    }

    /**
     * Clear the unrepress update log
     *
     * @return void
     */
    public function clearUpdateLog()
    {
        $updateLogFile = UNREPRESS_TEMP_PATH . $this->updateLogFile;

        if (! file_exists($updateLogFile)) {
            touch($updateLogFile);
        }

        file_put_contents($updateLogFile, '');
    }

    /**
     * Move/rename a directory, using WP_Filesystem.
     *
     * @param string $source The source directory to rename.
     * @param string $destination The new directory name.
     *
     * @return bool True on success, false on failure.
     */
    public function moveDirectoryWPFS($source, $destination): bool
    {
        if (! class_exists('WP_Filesystem')) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
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
        if (! str_contains($url, 'github.com')) {
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
        if (! empty($numeric_tags)) {
            return $numeric_tags[0]['tag'];
        } elseif (! empty($v_tags)) {
            return $v_tags[0]['tag'];
        } elseif (! empty($other_tags)) {
            return $other_tags[0]['tag'];
        }

        return null;
    }
}
