<?php

namespace UnrePress\Admin;

use UnrePress\Debugger;
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
        $this->helpers = new Helpers();
    }

    /**
     * Handle AJAX update request
     *
     * @return void
     */
    public function initCoreAjaxUpdate(): void
    {
        // Check nonce
        check_ajax_referer('unrepress_update_core');

        // Check user capabilities
        if (! current_user_can('manage_options')) {
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
     * Handle getting update log contents
     *
     * @return void
     */
    public function getUpdateLog(): void
    {
        // Check nonce
        check_ajax_referer('unrepress_get_update_log');

        // Check user capabilities
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'unrepress')]);

            return;
        }

        $logFile = UNREPRESS_TEMP_PATH . 'unrepress_update_log.txt';

        if (! file_exists($logFile)) {
            wp_send_json_error(['message' => __('No update log found', 'unrepress')]);

            return;
        }

        // An array of lines $logFile
        $content = explode("\n", file_get_contents($logFile));
        wp_send_json_success($content);
    }

    /**
     * Create our new "Updates" page, children (sub menu) of Dashboard menu
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
        // Check if we have a cached count
        $cached_count = get_transient('unrepress_updates_count');
        if ($cached_count !== false) {
            return (int) $cached_count;
        }

        $count = 0;

        // Check core updates
        $wpLocalVersion = get_bloginfo('version');
        $updateCore = new \UnrePress\Updater\UpdateCore();
        $latestVersion = $updateCore->getLatestCoreVersion();
        Debugger::log($latestVersion);
        if (version_compare($wpLocalVersion, $latestVersion, '<')) {
            $count++;
        }

        // Check plugin updates
        $pluginUpdates = get_plugin_updates();
        if (!empty($pluginUpdates)) {
            $count += count($pluginUpdates);
        }

        // Check theme updates
        $themeUpdates = get_theme_updates();
        if (!empty($themeUpdates)) {
            $count += count($themeUpdates);
        }

        // Cache the count for 3 hours
        set_transient('unrepress_updates_count', $count, 3 * HOUR_IN_SECONDS);

        return $count;
    }

    /**
     * Render our new "Updates" page
     *
     * @return void
     */
    public function renderUpdaterPage(): void
    {
        // Updating
        if (isset($_GET['do_update']) && $_GET['do_update'] == 'core') {
            // Validate nonce
            if (! check_admin_referer('update-core')) {
                wp_die(__('You are not allowed to perform this action.', 'unrepress'));
            }

            $this->renderUpdatingCorePage();

            return;
        }

        // Force-check
        if (isset($_GET['force-check']) && $_GET['force-check'] == 1) {
            // Force an update check when requested.
            $force_check = ! empty($_GET['force-check']);
            wp_version_check(array(), $force_check);

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
        // Clear updater log
        $this->helpers->clearUpdateLog();

        $updateCoreUrl = admin_url('index.php?page=unrepress-updater&do_update=core');
        $updateCoreUrl = add_query_arg('_wpnonce', wp_create_nonce('update-core'), $updateCoreUrl);

        $wpLocalVersion = get_bloginfo('version');
        $updateNeeded = false;
        $coreLatestVersion = '';

        $updateCore = new \UnrePress\Updater\UpdateCore();
        $latestVersion = $updateCore->getLatestCoreVersion();

        $coreLatestVersion = $latestVersion;
        if (version_compare($wpLocalVersion, $coreLatestVersion, '<')) {
            $updateNeeded = true;
        }

        $wpLastChecked = get_option('unrepress_last_checked', time());
        // Format it to human readable time: YYYY-MM-DD HH:MM AM/PM
        $wpLastChecked = date('Y-m-d - H:i A', $wpLastChecked);

        require_once UNREPRESS_PLUGIN_PATH . 'views/updater/unrepress-updater.php';
    }

    /**
     * Render our Updating page
     * Trigger WP Core update
     *
     * @return void
     */
    public function renderUpdatingCorePage(): void
    {
        include_once UNREPRESS_PLUGIN_PATH . 'views/updater/unrepress-doing-core-update.php';
    }

    /**
     * Do updateThis
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
}
