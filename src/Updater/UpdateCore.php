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

        // Check if update lock is set. Don't continue if it is set.
        if ($this->updateLock->isLocked()) {
            $this->helpers->writeUpdateLog(__('An update is already in progress. Please try again later.', 'unrepress'));
            $this->helpers->writeUpdateLog(':(');

            return false;
        }

        // Acquire the update lock
        $this->updateLock->lock();

        $this->updateType = $type;

        // Determine WP core latest version
        $latestVersion = $this->getLatestCoreVersion();

        if (empty($latestVersion)) {
            $this->helpers->writeUpdateLog(__('Error getting latest version of Core.', 'unrepress'));
            $this->helpers->writeUpdateLog(':/');
            $this->updateLock->unlock();

            return false;
        }

        // Get the download URL
        $downloadUrl = $this->getDownloadUrl('WordPress/WordPress', $latestVersion);

        $this->helpers->writeUpdateLog(
            wp_sprintf('Downloading Core version %s from %s', $latestVersion, $downloadUrl)
        );

        // Download the update
        $downloadPath = $this->downloadUpdate($downloadUrl);

        if (is_wp_error($downloadPath) || ! file_exists($downloadPath)) {
            $this->helpers->writeUpdateLog(__('Error downloading update. No URL or Path found.', 'unrepress'));
            $this->helpers->writeUpdateLog(':(');
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
            $this->updateLock->unlock();

            return false;
        }

        // If inside our temporary directory we should have an extra folder with all the files. Set a new temporary directory for Core. The catch: we don't know the name of this folder.
        // Search for the single unknown directory inside $tempDir
        $contents = scandir($tempDir);
        foreach ($contents as $item) {
            if ($item != '.' && $item != '..' && is_dir($tempDir . '/' . $item)) {
                $tempCoreDir = $tempDir . $item;

                break;
            }
        }

        // Check if we found the directory
        if (empty($tempCoreDir)) {
            // If we didn't find a directory, log an error and return false
            $this->helpers->writeUpdateLog(__('Failed to find WordPress core directory in extracted update.', 'unrepress'));
            $this->helpers->writeUpdateLog(':(');
            $this->updateLock->unlock();

            return false;
        }

        $helpers = new Helpers();

        $this->helpers->writeUpdateLog(__('Cleaning update folder...', 'unrepress'));

        // Remove wp-content folder.
        // Can we get a no-content zip somewhere? other than the Ego's site?
        $wpContentDir = $tempCoreDir . '/wp-content';
        if (is_dir($wpContentDir)) {
            $removeResult = $helpers->removeDirectoryWPFS($wpContentDir);
        }

        $this->helpers->writeUpdateLog(__('Copying update files...', 'unrepress'));

        // Apply the update. Copying the files from the temporary directory to the WP root directory
        // Get the WordPress root directory
        $wpRootDir = ABSPATH;

        // Copy files from temporary directory to WordPress root
        $copyResult = $helpers->copyFilesWPFS($tempCoreDir, $wpRootDir);

        $this->helpers->writeUpdateLog(__('Removing old temporary files...', 'unrepress'));

        // Clean up, use helpers removeDirectory method
        $removeResult = $helpers->removeDirectoryWPFS($tempCoreDir);

        // Remove $downloadPath
        wp_delete_file($downloadPath);

        // Delete the update lock
        $this->updateLock->unlock();

        $this->helpers->writeUpdateLog(__('Update process completed.', 'unrepress'));
        $this->helpers->writeUpdateLog(':)');

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
