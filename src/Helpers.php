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
}
