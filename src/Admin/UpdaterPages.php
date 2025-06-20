<?php

namespace UnrePress\Admin;

use UnrePress\Helpers;
use UnrePress\Updater\UpdateCore;

// No direct access
defined('ABSPATH') or die();

class UpdaterPages
{
    private $helpers;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'addCoreUpdateMenu']);
        add_action('wp_ajax_unrepress_get_update_log', [$this, 'getUpdateLog']);
        add_action('wp_ajax_unrepress_update_core', [$this, 'initCoreAjaxUpdate']);
        add_filter('wp_get_update_data', [$this, 'add_updates_count']);
        $this->helpers = new Helpers();
    }

    /**
     * Handle AJAX update request.
     *
     * @return void
     */
    public function initCoreAjaxUpdate(): void
    {
        // Check nonce
        check_ajax_referer('unrepress_update_core');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'unrepress')]);

            return;
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'core';

        // Create updater instance
        $updateCore = new UpdateCore();

        $data = [
            'success' => true,
            'message' => __('Update process started', 'unrepress'),
        ];

        header('Content-Type: application/json');
        echo json_encode($data);

        $updateCore->update($type);

        die();
    }

    /**
     * Handle getting update log contents.
     *
     * @return void
     */
    public function getUpdateLog(): void
    {
        // Check nonce
        check_ajax_referer('unrepress_get_update_log');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'unrepress')]);
            return;
        }

        $logFile = UNREPRESS_TEMP_PATH . 'unrepress_update_log.txt';

        if (!file_exists($logFile)) {
            wp_send_json_error(['message' => __('No update log found', 'unrepress')]);
            return;
        }

        // Get the last position we read from
        $lastPos = get_transient('unrepress_log_last_pos');
        $lastPos = $lastPos === false ? 0 : (int) $lastPos;

        // Get file size
        $fileSize = filesize($logFile);

        // If file size is smaller than last position (file was truncated)
        // or if this is first read, start from beginning
        if ($fileSize < $lastPos || $lastPos === 0) {
            $lastPos = 0;
        }

        // Read only new content
        $handle = fopen($logFile, 'r');
        if ($lastPos > 0) {
            fseek($handle, $lastPos);
        }

        $newContent = '';
        while (!feof($handle)) {
            $newContent .= fread($handle, 8192);
        }

        // Store new position
        $newPos = ftell($handle);
        set_transient('unrepress_log_last_pos', $newPos, HOUR_IN_SECONDS);

        fclose($handle);

        // Split into lines and filter empty ones
        $lines = array_filter(
            explode("\n", $newContent),
            function ($line) {
                return !empty(trim($line));
            }
        );

        wp_send_json_success(['lines' => array_values($lines)]);
    }

    /**
     * Create our new "Updates" page, children (sub menu) of Dashboard menu.
     *
     * @return void
     */
    public function addCoreUpdateMenu(): void
    {
        $update_count = $this->getUpdateCount();
        $menu_title = __('Updates', 'unrepress');

        if ($update_count > 0) {
            $menu_title .= sprintf(
                ' <span class="update-plugins count-%d"><span class="update-count">%d</span></span>',
                $update_count,
                $update_count
            );
        }

        add_dashboard_page(
            __('Updates', 'unrepress'),
            $menu_title,
            'manage_options',
            'unrepress-updater',
            [$this, 'renderUpdaterPage']
        );
    }

    private function getUpdateCount(): int
    {
        unrepress_debug('UpdaterPages::getUpdateCount() - Starting update count calculation');

        // Check if we have a cached count
        $cached_count = get_transient('unrepress_updates_count');
        if ($cached_count !== false) {
            unrepress_debug('UpdaterPages::getUpdateCount() - Using cached count: ' . $cached_count);
            return (int) $cached_count;
        }

        $count = 0;

        // Check core updates
        $wpLocalVersion = get_bloginfo('version');
        unrepress_debug('UpdaterPages::getUpdateCount() - Current WP version: ' . $wpLocalVersion);

        $updateCore = new UpdateCore();
        $latestVersion = $updateCore->getLatestCoreVersion();
        unrepress_debug('UpdaterPages::getUpdateCount() - Latest core version: ' . ($latestVersion ?: 'NULL'));

        if ($latestVersion && version_compare($wpLocalVersion, $latestVersion, '<')) {
            $count++;
            unrepress_debug('UpdaterPages::getUpdateCount() - Core update available, count++');
        } else {
            unrepress_debug('UpdaterPages::getUpdateCount() - No core update available');
        }

        // Check plugin updates
        $pluginUpdates = get_plugin_updates();
        if (!empty($pluginUpdates)) {
            $count += count($pluginUpdates);
            unrepress_debug('UpdaterPages::getUpdateCount() - Plugin updates: ' . count($pluginUpdates));
        }

        // Check theme updates
        $themeUpdates = get_theme_updates();
        if (!empty($themeUpdates)) {
            $count += count($themeUpdates);
            unrepress_debug('UpdaterPages::getUpdateCount() - Theme updates: ' . count($themeUpdates));
        }

        unrepress_debug('UpdaterPages::getUpdateCount() - Total update count: ' . $count);

        // Cache the count for 3 hours
        set_transient('unrepress_updates_count', $count, 3 * HOUR_IN_SECONDS);

        return $count;
    }

    /**
     * Render our new "Updates" page.
     *
     * @return void
     */
    public function renderUpdaterPage(): void
    {
        // Updating
        if (isset($_GET['do_update']) && $_GET['do_update'] == 'core') {
            // Validate nonce
            if (!check_admin_referer('update-core')) {
                wp_die(__('You are not allowed to perform this action.', 'unrepress'));
            }

            $this->renderUpdatingCorePage();

            return;
        }

        // Force-check
        if (isset($_GET['force-check']) && $_GET['force-check'] == 1) {
            // Force an update check when requested.
            $force_check = !empty($_GET['force-check']);
            wp_version_check([], $force_check);

            wp_update_plugins();
            wp_update_themes();

            // Clear transients
            $this->helpers->clearUpdateTransients();
        }

        $this->updaterIndex();
    }

    /**
     * Main function to handle the rendering of the updater page.
     *
     * Checks if a WordPress core update is needed, and if so, renders the
     * page with an update button.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updaterIndex(): void
    {
        unrepress_debug('UpdaterPages::updaterIndex() - Starting updater page rendering');

        // Clear updater log
        $this->helpers->clearUpdateLog();

        $updateCoreUrl = admin_url('index.php?page=unrepress-updater&do_update=core');
        $updateCoreUrl = add_query_arg('_wpnonce', wp_create_nonce('update-core'), $updateCoreUrl);

        $wpLocalVersion = get_bloginfo('version');
        unrepress_debug('UpdaterPages::updaterIndex() - Current WP version: ' . $wpLocalVersion);

        $updateNeeded = false;
        $coreLatestVersion = '';

        $updateCore = new UpdateCore();
        unrepress_debug('UpdaterPages::updaterIndex() - Created UpdateCore instance, calling getLatestCoreVersion()');

        $latestVersion = $updateCore->getLatestCoreVersion();
        unrepress_debug('UpdaterPages::updaterIndex() - getLatestCoreVersion() returned: ' . ($latestVersion ?: 'NULL'));

        $coreLatestVersion = $latestVersion;
        if ($coreLatestVersion && version_compare($wpLocalVersion, $coreLatestVersion, '<')) {
            $updateNeeded = true;
            unrepress_debug('UpdaterPages::updaterIndex() - Update needed! ' . $wpLocalVersion . ' < ' . $coreLatestVersion);
        } else {
            unrepress_debug('UpdaterPages::updaterIndex() - No update needed. Latest: ' . ($coreLatestVersion ?: 'NULL') . ', Current: ' . $wpLocalVersion);
        }

        $wpLastChecked = get_option('unrepress_last_checked', time());
        // Format it to human readable time: YYYY-MM-DD HH:MM AM/PM
        $wpLastChecked = date('Y-m-d - H:i A', $wpLastChecked);
        unrepress_debug('UpdaterPages::updaterIndex() - Last checked: ' . $wpLastChecked);

        unrepress_debug('UpdaterPages::updaterIndex() - Loading updater view with updateNeeded=' . ($updateNeeded ? 'true' : 'false'));
        require_once UNREPRESS_PLUGIN_PATH . 'views/updater/unrepress-updater.php';
    }

    /**
     * Render our Updating page
     * Trigger WP Core update.
     *
     * @return void
     */
    public function renderUpdatingCorePage(): void
    {
        include_once UNREPRESS_PLUGIN_PATH . 'views/updater/unrepress-doing-core-update.php';
    }

    /**
     * Do updateThis.
     *
     * @param string $type core, plugins, themes
     *
     * @return bool
     */
    public function updateThis($type = 'core'): bool
    {
        $result = false;

        if ($type == 'core') {
            $updater = new UpdateCore();
            $result = $updater->update($type);
        }

        return $result;
    }

    /**
     * Add our update count to the admin bar updates counter.
     */
    public function add_updates_count($update_data)
    {
        // Get our custom updates count from transient
        $update_count = $this->getUpdateCount();

        if ($update_count > 0) {
            $update_data['counts']['total'] = $update_count;
        }

        return $update_data;
    }
}
