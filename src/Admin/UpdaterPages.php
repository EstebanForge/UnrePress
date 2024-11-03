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
        add_dashboard_page(
            __('Updates', 'unrepress'),
            __('Updates', 'unrepress'),
            'manage_options',
            'unrepress-updater',
            [$this, 'renderUpdaterPage']
        );
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

        $wpLastChecked = get_option('unrepress_last_checked', time());
        // Format it to human readable time: YYYY-MM-DD HH:MM AM/PM
        $wpLastChecked = date('Y-m-d - H:i A', $wpLastChecked);

        // Compare versions, check if remote version is newer
        if (version_compare($wpLocalVersion, $wpLatestVersion, '<')) {
            //add_action('admin_notices', [$this, 'showCoreUpdateNotice']);
            $updateNeeded = true;
        }

        include_once UNREPRESS_PLUGIN_PATH . 'views/unrepress-updater.php';
    }

    /**
     * Render our Updating page
     * Trigger WP Core update
     *
     * @return void
     */
    public function renderUpdatingCorePage(): void
    {
        include_once UNREPRESS_PLUGIN_PATH . 'views/unrepress-doing-core-update.php';
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
