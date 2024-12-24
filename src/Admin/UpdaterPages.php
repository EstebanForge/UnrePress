<?php

namespace UnrePress\Admin;

use UnrePress\Helpers;
use UnrePress\Updater\UpdateCore;

class UpdaterPages
{
    private $helpers;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'addCoreUpdateMenu']);
        add_action('wp_ajax_unrepress_update_core', [$this, 'initCoreAjaxUpdate']);
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
        $wpLatestVersion = new UpdateCore();
        $wpLatestVersion = $wpLatestVersion->getLatestCoreVersion();

        if (!empty($wpLatestVersion) && version_compare($wpLocalVersion, $wpLatestVersion, '<')) {
            $count++;
        }

        // Check plugin updates
        $pluginUpdater = new \UnrePress\Updater\UpdatePlugins();
        $pluginTransient = get_site_transient('update_plugins');
        $pluginTransient = $pluginUpdater->hasUpdate($pluginTransient);
        if (!empty($pluginTransient->response)) {
            $count += count($pluginTransient->response);
        }

        // Check theme updates
        $themeUpdater = new \UnrePress\Updater\UpdateThemes();
        $themeTransient = get_site_transient('update_themes');
        $themeTransient = $themeUpdater->hasUpdate($themeTransient);
        if (!empty($themeTransient->response)) {
            $count += count($themeTransient->response);
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
        if (isset($_GET['force-check'])) {
            // Validate nonce
            if (! check_admin_referer('update-core')) {
                wp_die(__('You are not allowed to perform this action.', 'unrepress'));
            }

            // Force an update check when requested.
            (new UpdateCore())->forceCoreUpdateCheck();
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

        $wpLocalVersion = get_bloginfo('version');
        $updateNeeded = false;

        $wpLatestVersion = new UpdateCore();
        $wpLatestVersion = $wpLatestVersion->getLatestCoreVersion();

        if (empty($wpLatestVersion)) {
            $wpLatestVersion = 'Unknown';
        }

        $wpLastChecked = get_option('unrepress_last_checked', time());
        // Format it to human readable time: YYYY-MM-DD HH:MM AM/PM
        $wpLastChecked = date('Y-m-d - H:i A', $wpLastChecked);

        // Compare versions, check if remote version is newer
        if (version_compare($wpLocalVersion, $wpLatestVersion, '<')) {
            //add_action('admin_notices', [$this, 'showCoreUpdateNotice']);
            $updateNeeded = true;
        }

        // Get plugin updates
        $pluginUpdates = [];
        if (function_exists('get_plugin_updates')) {
            $plugin_updates = get_plugin_updates();
            foreach ($plugin_updates as $plugin_file => $plugin_data) {
                $pluginUpdates[] = [
                    'file' => $plugin_file,
                    'name' => $plugin_data->Name,
                    'version' => $plugin_data->Version,
                    'new_version' => $plugin_data->update->new_version,
                    'icon' => $plugin_data->update->icons['2x'] ?? $plugin_data->update->icons['1x'] ?? '',
                    'update_message' => $plugin_data->update->upgrade_notice ?? ''
                ];
            }
        }

        // Get theme updates
        $themeUpdates = [];
        if (function_exists('get_theme_updates')) {
            $theme_updates = get_theme_updates();
            foreach ($theme_updates as $theme_file => $theme_data) {
                $themeUpdates[] = [
                    'theme' => $theme_file,
                    'name' => $theme_data->Name,
                    'version' => $theme_data->Version,
                    'new_version' => $theme_data->update['new_version'],
                    'screenshot' => get_theme_root_uri() . '/' . $theme_file . '/screenshot.png',
                    'update_message' => $theme_data->update['upgrade_notice'] ?? ''
                ];
            }
        }

        include_once UNREPRESS_PLUGIN_PATH . 'views/updater/unrepress-updater.php';
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
