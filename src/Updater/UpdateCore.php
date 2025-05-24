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
        //$this->enableTestMode('timeout');
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
        $timeout = 30; // 30 seconds timeout

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

        $this->helpers->writeUpdateLog(__('Core package downloaded.', 'unrepress'));

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

        $this->helpers->writeUpdateLog(wp_sprintf('Update extracted to %s', $tempDir));

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
            $this->helpers->writeUpdateLog(__('Failed to find WordPress core directory in extracted package.', 'unrepress'));
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

        $this->helpers->writeUpdateLog(__('Cleaning update folder...', 'unrepress'));

        // Remove wp-content folder.
        $wpContentDir = $tempCoreDir . '/wp-content';
        if (is_dir($wpContentDir)) {
            $removeResult = $this->helpers->removeDirectory($wpContentDir);
        }

        $this->helpers->writeUpdateLog(__('Copying update files...', 'unrepress'));

        // Apply the update
        $wpRootDir = ABSPATH;
        $copyResult = $this->helpers->copyFiles($tempCoreDir, $wpRootDir);

        $this->helpers->writeUpdateLog(__('Removing old temporary files...', 'unrepress'));

        // Clean up
        $removeResult = $this->helpers->removeDirectory($tempCoreDir);
        wp_delete_file($downloadPath);
        $this->helpers->clearTempDirectories();

        $this->helpers->writeUpdateLog(__('Core update completed.', 'unrepress'));
        $this->helpers->writeUpdateLog(':)');

        // Delete transients
        $this->helpers->clearUpdateTransients();

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

            $this->helpers->writeUpdateLog(__('Requesting WordPress core update via wp.org...', 'unrepress'));

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
                    __('WordPress core update failed: %s', 'unrepress'),
                    $result->get_error_message()
                ));
                return false;
            }

            if ($result === false) {
                $this->helpers->writeUpdateLog(__('WordPress core update failed with unknown error.', 'unrepress'));
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
     * Gets the latest version of WordPress core using the UnrePress index system.
     *
     * The API is only queried if the transient does not exist or has expired.
     *
     * @return string|null The latest version of WordPress core, or null on error.
     */
    public function getLatestCoreVersion(): ?string
    {
        unrepress_debug('UpdateCore::getLatestCoreVersion() - Starting core version check');

        // Test: Simulate failure in getting latest version
        if ($this->testMode && $this->testScenario === 'version_fail') {
            unrepress_debug('UpdateCore::getLatestCoreVersion() - Test mode: simulating version failure');
            return null;
        }

        // Check if the transient exists
        $cachedVersion = get_transient(self::TRANSIENT_NAME);
        if ($cachedVersion !== false) {
            unrepress_debug('UpdateCore::getLatestCoreVersion() - Using cached version: ' . $cachedVersion);
            return $cachedVersion;
        }

        unrepress_debug('UpdateCore::getLatestCoreVersion() - No cached version, fetching from index');

        try {
            // Get WordPress core info from UnrePress index
            $coreInfo = $this->getCoreInfoFromIndex();
            unrepress_debug('UpdateCore::getLatestCoreVersion() - Core info from index:', $coreInfo);

            if (!$coreInfo || !isset($coreInfo['tags'])) {
                unrepress_debug('UpdateCore::getLatestCoreVersion() - No core info found in index or missing tags URL');
                error_log('UnrePress: No core info found in index or missing tags URL');
                return null;
            }

            // Use GitHub provider to get latest version from the index-defined tags URL
            if ($this->provider == 'github') {
                $updaterProvider = new GitHub();
                unrepress_debug('UpdateCore::getLatestCoreVersion() - Created GitHub provider');
            }

            // Get the repository slug from the index
            $repository = $coreInfo['repository'] ?? 'https://github.com/WordPress/WordPress/';
            unrepress_debug('UpdateCore::getLatestCoreVersion() - Repository URL from index: ' . $repository);

            $repositorySlug = $this->extractRepositorySlugFromUrl($repository);
            unrepress_debug('UpdateCore::getLatestCoreVersion() - Extracted repository slug: ' . $repositorySlug);

            $latestVersion = $updaterProvider->getLatestVersion($repositorySlug);
            unrepress_debug('UpdateCore::getLatestCoreVersion() - Latest version from GitHub provider: ' . ($latestVersion ?: 'NULL'));

            if ($latestVersion) {
                // Set the transient with the new version
                set_transient(self::TRANSIENT_NAME, $latestVersion, UNREPRESS_TRANSIENT_EXPIRATION);
                unrepress_debug('UpdateCore::getLatestCoreVersion() - Cached version in transient: ' . $latestVersion);

                // Save unrepress_last_checked
                update_option(UNREPRESS_PREFIX . 'last_checked', time());
                unrepress_debug('UpdateCore::getLatestCoreVersion() - Updated last_checked timestamp');

                error_log("UnrePress: Found WordPress core version {$latestVersion} from index system");
            } else {
                unrepress_debug('UpdateCore::getLatestCoreVersion() - GitHub provider returned no version');
            }

            return $latestVersion;

        } catch (\Exception $e) {
            unrepress_debug('UpdateCore::getLatestCoreVersion() - Exception caught: ' . $e->getMessage());
            error_log('UnrePress: Error getting core version from index: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get WordPress core information from the UnrePress index system
     *
     * @return array|null Core information array or null on error
     */
    private function getCoreInfoFromIndex(): ?array
    {
        unrepress_debug('UpdateCore::getCoreInfoFromIndex() - Starting to get core info from index');

        // Get main index
        $unrepress = new \UnrePress\UnrePress();
        $mainIndex = $unrepress->index();
        unrepress_debug('UpdateCore::getCoreInfoFromIndex() - Main index loaded:', $mainIndex);

        if (!$mainIndex || !isset($mainIndex['wordpress']['url'])) {
            unrepress_debug('UpdateCore::getCoreInfoFromIndex() - Main index is null or missing wordpress.url');
            return null;
        }

        // Get WordPress core index
        $coreIndexUrl = $mainIndex['wordpress']['url'];
        unrepress_debug('UpdateCore::getCoreInfoFromIndex() - Core index URL: ' . $coreIndexUrl);

        $coreIndexResponse = wp_remote_get($coreIndexUrl, ['timeout' => 10]);

        if (is_wp_error($coreIndexResponse)) {
            unrepress_debug('UpdateCore::getCoreInfoFromIndex() - wp_remote_get error: ' . $coreIndexResponse->get_error_message());
            return null;
        }

        $responseCode = wp_remote_retrieve_response_code($coreIndexResponse);
        $responseBody = wp_remote_retrieve_body($coreIndexResponse);
        unrepress_debug('UpdateCore::getCoreInfoFromIndex() - Response code: ' . $responseCode);
        unrepress_debug('UpdateCore::getCoreInfoFromIndex() - Response body (first 200 chars): ' . substr($responseBody, 0, 200));

        $coreInfo = json_decode($responseBody, true);

        if (!is_array($coreInfo)) {
            unrepress_debug('UpdateCore::getCoreInfoFromIndex() - JSON decode failed or not an array');
            unrepress_debug('UpdateCore::getCoreInfoFromIndex() - JSON last error: ' . json_last_error_msg());
            return null;
        }

        unrepress_debug('UpdateCore::getCoreInfoFromIndex() - Successfully decoded core info with keys: ' . implode(', ', array_keys($coreInfo)));
        return $coreInfo;
    }

    /**
     * Extract repository slug (owner/repo) from GitHub URL
     *
     * @param string $url GitHub repository URL
     * @return string Repository slug in format "owner/repo"
     */
    private function extractRepositorySlugFromUrl(string $url): string
    {
        unrepress_debug('UpdateCore::extractRepositorySlugFromUrl() - Input URL: ' . $url);

        // Default fallback
        $defaultSlug = 'WordPress/WordPress';

        // Remove trailing slash and .git
        $cleanUrl = rtrim($url, '/');
        $cleanUrl = str_replace('.git', '', $cleanUrl);
        unrepress_debug('UpdateCore::extractRepositorySlugFromUrl() - Cleaned URL: ' . $cleanUrl);

        // Extract owner/repo from GitHub URL
        if (preg_match('#github\.com/([^/]+/[^/]+)#', $cleanUrl, $matches)) {
            unrepress_debug('UpdateCore::extractRepositorySlugFromUrl() - Regex matched: ' . $matches[1]);
            return $matches[1];
        }

        unrepress_debug('UpdateCore::extractRepositorySlugFromUrl() - No regex match, using default: ' . $defaultSlug);
        return $defaultSlug;
    }

    public function getDownloadUrl(string $repo, string $version): string
    {
        // Test: Simulate failure in downloading
        if ($this->testMode && $this->testScenario === 'download_fail') {
            return 'https://invalid.url/that/will/fail';
        }

        try {
            // Get repository slug from index if not provided in correct format
            if ($repo === 'WordPress/WordPress' || empty($repo)) {
                $coreInfo = $this->getCoreInfoFromIndex();
                if ($coreInfo && isset($coreInfo['repository'])) {
                    $repo = $this->extractRepositorySlugFromUrl($coreInfo['repository']);
                }
            }

            if ($this->provider == 'github') {
                $updaterProvider = new GitHub();
            }

            return $updaterProvider->getDownloadUrl($repo, $version);

        } catch (\Exception $e) {
            error_log('UnrePress: Error getting download URL: ' . $e->getMessage());
            // Fallback to default
            if ($this->provider == 'github') {
                $updaterProvider = new GitHub();
            }
            return $updaterProvider->getDownloadUrl('WordPress/WordPress', $version);
        }
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
        // Delete the transient
        delete_transient(self::TRANSIENT_NAME);

        // Force an update check
        wp_version_check([], true);
    }

    /**
     * Hook into WordPress native wp_version_check to provide GitHub-based updates
     * This leverages WordPress' existing cron system and bypasses WP 6.8 limitations
     *
     * @return void
     */
    public function checkCoreUpdatesFromGitHub(): void
    {
        try {
            $latestVersion = $this->getLatestCoreVersion();

            if (!$latestVersion) {
                return;
            }

            // Get current WordPress version
            global $wp_version;

            // Only proceed if GitHub version is newer than current
            if (!version_compare($latestVersion, $wp_version, '>')) {
                return;
            }

            // Get or create the update_core transient
            $current = get_site_transient('update_core');
            if (!is_object($current)) {
                $current = new \stdClass();
                $current->updates = [];
                $current->version_checked = $wp_version;
            }

            // Create update object matching WordPress core format
            $update = new \stdClass();
            $update->response = 'upgrade';
            $update->download = $this->getDownloadUrl('WordPress/WordPress', $latestVersion);
            $update->locale = get_locale();
            $update->packages = new \stdClass();
            $update->packages->full = $update->download;
            $update->packages->no_content = '';
            $update->packages->new_bundled = '';
            $update->packages->partial = '';
            $update->packages->rollback = '';
            $update->current = $latestVersion;
            $update->version = $latestVersion;
            $update->php_version = '7.4';
            $update->mysql_version = '5.7';
            $update->new_bundled = '';
            $update->partial_version = '';

            // Set the update in the transient
            $current->updates = [$update];
            $current->version_checked = $wp_version;
            $current->last_checked = time();

            // Save the transient
            set_site_transient('update_core', $current);

            // Log success for debugging
            error_log("UnrePress: Core update detected - WordPress {$latestVersion} available from GitHub");

        } catch (\Exception $e) {
            // Log error but don't break the update process
            error_log('UnrePress Core Update Check Error: ' . $e->getMessage());
        }
    }
}
