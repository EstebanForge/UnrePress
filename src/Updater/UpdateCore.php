<?php

namespace UnrePress\Updater;

use UnrePress\Helpers;
use UnrePress\UpdaterProvider\GitHub;

class UpdateCore
{
    private $updateLock;

    private $helpers;

    protected $updateType = 'core';

    protected $provider = 'github';
    private const TRANSIENT_NAME = UNREPRESS_PREFIX . 'updates_core_latest_version';

    private $testMode = false;
    private $testScenario = '';

    /**
     * Class constructor. If an update is already in progress, this prevents any other
     * method from being called.
     *
     * @return void
     */
    public function __construct()
    {
        $this->updateLock = new UpdateLock();
        $this->helpers = new Helpers();

        // Enable timeout test mode
        $this->enableTestMode('timeout');
    }

    /**
     * Perform an update based on the given type.
     *
     * @param string $type The type of update to perform.
     *
     * @return bool
     */
    public function update(string $type): bool
    {
        $this->helpers->clearUpdateLog();

        $this->helpers->writeUpdateLog(wp_sprintf('Starting %s update...', $type));

        // Test: Simulate timeout immediately for cleaner testing
        if ($this->testMode && $this->testScenario === 'timeout') {
            $this->helpers->writeUpdateLog(__('Index update operation timed out.', 'unrepress'));

            if (!defined('UNREPRESS_BLOCK_WPORG') || !UNREPRESS_BLOCK_WPORG) {
                $this->helpers->writeUpdateLog(__('Falling back to wp.org repository for the update...', 'unrepress'));
                return $this->fallbackToWordPressUpdate($type);
            }

            return false;
        }

        // Check if update lock is set. Don't continue if it is set.
        if ($this->updateLock->isLocked()) {
            $this->helpers->writeUpdateLog(__('An update is already in progress. Please try again later.', 'unrepress'));
            $this->helpers->writeUpdateLog(':(');

            return false;
        }

        // Acquire the update lock
        $this->updateLock->lock();

        $this->updateType = $type;

        // Set longer timeout for core update process
        @set_time_limit(300);

        // Set timeout for update operation
        $startTime = time();
        $timeout = 10; // 10 seconds timeout

        // Determine WP core latest version
        $latestVersion = $this->getLatestCoreVersion();

        if (empty($latestVersion)) {
            $this->helpers->writeUpdateLog(__('Error getting latest version of Core.', 'unrepress'));

            if (!defined('UNREPRESS_BLOCK_WPORG') || !UNREPRESS_BLOCK_WPORG) {
                $this->helpers->writeUpdateLog(__('Falling back to wp.org repository for the update...', 'unrepress'));
                $this->updateLock->unlock();
                return $this->fallbackToWordPressUpdate($type);
            }

            $this->updateLock->unlock();
            return false;
        }

        // Get the download URL
        $downloadUrl = $this->getDownloadUrl('WordPress/WordPress', $latestVersion);

        $this->helpers->writeUpdateLog(
            wp_sprintf('Downloading Core version %s from %s', $latestVersion, $downloadUrl)
        );

        // Check timeout
        if (time() - $startTime > $timeout) {
            $this->helpers->writeUpdateLog(__('Index update operation timed out.', 'unrepress'));

            if (!defined('UNREPRESS_BLOCK_WPORG') || !UNREPRESS_BLOCK_WPORG) {
                $this->helpers->writeUpdateLog(__('Falling back to wp.org repository for the update...', 'unrepress'));
                $this->updateLock->unlock();
                return $this->fallbackToWordPressUpdate($type);
            }

            $this->updateLock->unlock();
            return false;
        }

        // Download the update
        $downloadPath = $this->downloadUpdate($downloadUrl);

        if (is_wp_error($downloadPath) || !file_exists($downloadPath)) {
            $this->helpers->writeUpdateLog(__('Error downloading update. No URL or Path found.', 'unrepress'));

            if (!defined('UNREPRESS_BLOCK_WPORG') || !UNREPRESS_BLOCK_WPORG) {
                $this->helpers->writeUpdateLog(__('Falling back to wp.org repository for the update...', 'unrepress'));
                $this->updateLock->unlock();
                return $this->fallbackToWordPressUpdate($type);
            }

            $this->updateLock->unlock();
            return false;
        }

        // Check timeout
        if (time() - $startTime > $timeout) {
            $this->helpers->writeUpdateLog(__('Index update operation timed out.', 'unrepress'));
            wp_delete_file($downloadPath);

            if (!defined('UNREPRESS_BLOCK_WPORG') || !UNREPRESS_BLOCK_WPORG) {
                $this->helpers->writeUpdateLog(__('Falling back to wp.org repository for the update...', 'unrepress'));
                $this->updateLock->unlock();
                return $this->fallbackToWordPressUpdate($type);
            }

            $this->updateLock->unlock();
            return false;
        }

        // Extract the update zip file
        WP_Filesystem();

        $tempCoreDir = '';
        $tempDir = UNREPRESS_TEMP_PATH;
        $unzipfile = unzip_file($downloadPath, $tempDir);

        $this->helpers->writeUpdateLog(__('Expanding update...', 'unrepress'));

        if (is_wp_error($unzipfile)) {
            $this->helpers->writeUpdateLog(__('Error expanding update file.', 'unrepress'));
            $this->helpers->writeUpdateLog(':(');
            wp_delete_file($downloadPath);

            if (!defined('UNREPRESS_BLOCK_WPORG') || !UNREPRESS_BLOCK_WPORG) {
                $this->helpers->writeUpdateLog(__('Falling back to wp.org repository for the update...', 'unrepress'));
                $this->updateLock->unlock();
                return $this->fallbackToWordPressUpdate($type);
            }

            $this->updateLock->unlock();
            return false;
        }

        // If inside our temporary directory we should have an extra folder with all the files.
        $contents = scandir($tempDir);
        foreach ($contents as $item) {
            if ($item != '.' && $item != '..' && is_dir($tempDir . '/' . $item)) {
                $tempCoreDir = $tempDir . $item;
                break;
            }
        }

        // Check if we found the directory
        if (empty($tempCoreDir)) {
            $this->helpers->writeUpdateLog(__('Failed to find WordPress core directory in extracted update.', 'unrepress'));
            $this->helpers->writeUpdateLog(':(');
            wp_delete_file($downloadPath);

            if (!defined('UNREPRESS_BLOCK_WPORG') || !UNREPRESS_BLOCK_WPORG) {
                $this->helpers->writeUpdateLog(__('Falling back to wp.org repository for the update...', 'unrepress'));
                $this->updateLock->unlock();
                return $this->fallbackToWordPressUpdate($type);
            }

            $this->updateLock->unlock();
            return false;
        }

        $helpers = new Helpers();

        $this->helpers->writeUpdateLog(__('Cleaning update folder...', 'unrepress'));

        // Remove wp-content folder.
        $wpContentDir = $tempCoreDir . '/wp-content';
        if (is_dir($wpContentDir)) {
            $removeResult = $helpers->removeDirectory($wpContentDir);
        }

        $this->helpers->writeUpdateLog(__('Copying update files...', 'unrepress'));

        // Apply the update
        $wpRootDir = ABSPATH;
        $copyResult = $helpers->copyFiles($tempCoreDir, $wpRootDir);

        $this->helpers->writeUpdateLog(__('Removing old temporary files...', 'unrepress'));

        // Clean up
        $removeResult = $helpers->removeDirectory($tempCoreDir);
        wp_delete_file($downloadPath);
        $this->helpers->clearTempDirectories();

        $this->helpers->writeUpdateLog(__('Core update completed.', 'unrepress'));
        $this->helpers->writeUpdateLog(':)');

        $this->updateLock->unlock();

        return true;
    }

    /**
     * Fallback to WordPress native update mechanism
     *
     * @param string $type The type of update to perform
     * @return bool Whether the update was successful
     */
    protected function fallbackToWordPressUpdate(string $type): bool
    {
        try {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
            require_once(ABSPATH . 'wp-admin/includes/update.php');
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');

            // Validate WordPress update availability
            wp_version_check();
            $updates = get_core_updates();

            if (empty($updates)) {
                $this->helpers->writeUpdateLog(__('No WordPress updates available.', 'unrepress'));
                return false;
            }

            // Check if filesystem access is available
            if (!$this->validateFileSystemAccess()) {
                $this->helpers->writeUpdateLog(__('WordPress filesystem access not available.', 'unrepress'));
                return false;
            }

            $this->helpers->writeUpdateLog(__('Starting WordPress core update via wp.org...', 'unrepress'));
            $this->helpers->writeUpdateLog(sprintf(__('Update type: %s', 'unrepress'), $type));

            // Set longer timeout for core update process
            @set_time_limit(300);

            // Initialize the upgrader with our custom skin
            $upgrader = new \Core_Upgrader(new \WP_Ajax_Upgrader_Skin());

            // Perform the upgrade
            $result = $upgrader->upgrade($updates[0], [
                'attempt_rollback' => true,
                'do_rollback' => true,
            ]);

            if (is_wp_error($result)) {
                $this->helpers->writeUpdateLog(sprintf(
                    __('WordPress update failed: %s', 'unrepress'),
                    $result->get_error_message()
                ));
                return false;
            }

            if ($result === false) {
                $this->helpers->writeUpdateLog(__('WordPress update failed with unknown error.', 'unrepress'));
                return false;
            }

            $this->helpers->writeUpdateLog(__('Core update completed.', 'unrepress'));
            $this->helpers->writeUpdateLog(':)');
            return true;
        } catch (\Exception $e) {
            $this->helpers->writeUpdateLog(sprintf(
                __('Update exception: %s', 'unrepress'),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Validates WordPress filesystem access
     *
     * @return bool
     */
    protected function validateFileSystemAccess(): bool
    {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $filesystem_method = get_filesystem_method();

        if ($filesystem_method !== 'direct') {
            return false;
        }

        if (!WP_Filesystem()) {
            return false;
        }

        return true;
    }

    /**
     * Gets the latest version of WordPress core from the GitHub API.
     *
     * The API is only queried if the transient does not exist or has expired.
     *
     * @return string|null The latest version of WordPress core, or null on error.
     */
    public function getLatestCoreVersion(): ?string
    {
        // Test: Simulate failure in getting latest version
        if ($this->testMode && $this->testScenario === 'version_fail') {
            return null;
        }

        if ($this->provider == 'github') {
            $updaterProvider = new GitHub();
        }

        // Check if the transient exists
        $cachedVersion = get_transient(self::TRANSIENT_NAME);
        if ($cachedVersion !== false) {
            return $cachedVersion;
        }

        $latestVersion = $updaterProvider->getLatestVersion('WordPress/WordPress');

        if ($latestVersion) {
            // Set the transient with the new version
            set_transient(self::TRANSIENT_NAME, $latestVersion, UNREPRESS_TRANSIENT_EXPIRATION);

            // Save unrepress_last_checked
            update_option(UNREPRESS_PREFIX . 'last_checked', time());
        }

        return $latestVersion;
    }

    public function getDownloadUrl(string $repo, string $version): string
    {
        // Test: Simulate failure in downloading
        if ($this->testMode && $this->testScenario === 'download_fail') {
            return 'https://invalid.url/that/will/fail';
        }

        if ($this->provider == 'github') {
            $updaterProvider = new GitHub();
        }

        return $updaterProvider->getDownloadUrl($repo, $version);
    }

    /**
     * Download a given version from a GitHub repository.
     *
     * Downloads the specified version from the given GitHub repository and saves
     * it to a temporary path.
     *
     * @param string $repository The GitHub repository slug
     *
     * @return mixed
     */
    public function downloadUpdate(string $downloadUrl): mixed
    {
        $downloadPath = UNREPRESS_TEMP_PATH . 'wordpress_core_' . uniqid() . '.zip';

        // Download the update
        $downloadResult = wp_remote_get($downloadUrl, ['filename' => $downloadPath, 'stream' => true, 'timeout' => 300]);

        if (is_wp_error($downloadResult)) {
            return false;
        }

        return $downloadPath;
    }

    /**
     * Enable test mode with a specific scenario
     *
     * @param string $scenario The test scenario to run:
     *                        'version_fail' - Simulate failure in getting latest version
     *                        'download_fail' - Simulate failure in downloading update
     *                        'timeout' - Simulate a timeout
     * @return void
     */
    public function enableTestMode(string $scenario = 'version_fail'): void
    {
        $this->testMode = true;
        $this->testScenario = $scenario;
    }

    /**
     * Disable test mode
     *
     * @return void
     */
    public function disableTestMode(): void
    {
        $this->testMode = false;
        $this->testScenario = '';
    }

    /**
     * Forces WordPress to check for a core update.
     *
     * Useful if you need to ensure that WordPress checks for a core update
     * immediately, rather than waiting for the next scheduled check.
     *
     * @since 1.0.0
     */
    public function forceCoreUpdateCheck(): void
    {
        // Remove lock
        $this->updateLock->unlock();

        // Clear all update transients
        Helpers::clearUpdateTransients();
    }
}
