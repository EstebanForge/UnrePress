<?php

use UnrePress\Helpers;

// No direct access
defined('ABSPATH') or die();

// Ensure we're in admin context
if (!is_admin()) {
    wp_die(__('Access Denied', 'unrepress'));
}

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/update.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';

// Verify nonce if force-check is requested
if (isset($_GET['force-check']) && $_GET['force-check'] == 1) {
    unrepress_debug('Force-check requested - clearing transients and forcing updates');

    // Force an update check when requested.
    $force_check = !empty($_GET['force-check']);

    // Clear core transients first to ensure fresh check
    delete_site_transient('update_core');
    delete_transient('unrepress_updates_core_latest_version');
    delete_transient('unrepress_updates_count');
    unrepress_debug('Force-check - cleared core transients');

    // Force GitHub core update check
    try {
        $updateCore = new \UnrePress\Updater\UpdateCore();
        unrepress_debug('Force-check - calling checkCoreUpdatesFromGitHub()');
        $updateCore->checkCoreUpdatesFromGitHub();
        unrepress_debug('Force-check - checkCoreUpdatesFromGitHub() completed');
    } catch (Exception $e) {
        unrepress_debug('Force-check - checkCoreUpdatesFromGitHub() error: ' . $e->getMessage());
        error_log('UnrePress force-check error: ' . $e->getMessage());
    }

    // Force WordPress core update check
    unrepress_debug('Force-check - calling wp_version_check()');
    wp_version_check([], $force_check);

    // Force refresh of plugin and theme updates
    unrepress_debug('Force-check - calling wp_update_plugins() and wp_update_themes()');
    wp_update_plugins();
    wp_update_themes();

    // Clear transients
    unrepress_debug('Force-check - calling clearUpdateTransients()');
    Helpers::clearUpdateTransients();

    unrepress_debug('Force-check completed');
}
?>
<div class="wrap unrepress">
    <h1><?php esc_html_e('UnrePress Updater', 'unrepress'); ?>
    </h1>
    <p><?php printf(esc_html__('Last checked on %s.', 'unrepress'), $wpLastChecked); ?>
    </p>

    <section class="updates-core">
        <h2><?php esc_html_e('Core', 'unrepress'); ?>
            <?php if ($updateNeeded && !empty($coreLatestVersion)): ?>
                <span class="count">(1)</span>
            <?php endif; ?>
        </h2>
        <?php if ($updateNeeded && !empty($coreLatestVersion)): ?>
            <ul>
                <li>
                    <h3>
                        <?php esc_html_e('WordPress update available', 'unrepress'); ?>
                    </h3>
                    <p>
                        <?php
                        printf(
                            /* translators: 1: WordPress version, 2: WordPress version. */
                            __('You are currently running WordPress version %1$s. The latest version is %2$s', 'unrepress'),
                            $wpLocalVersion,
                            $coreLatestVersion
                        );
                        ?>
                    </p>
                    <p>
                        <?php
                        printf(
                            '<a href="%s" class="button button-primary">%s</a>',
                            $updateCoreUrl,
                            __('Update now', 'unrepress')
                        );
                        ?>
                    </p>
                </li>
            </ul>
        <?php else: ?>
            <p><?php esc_html_e('Your WordPress installation is up to date.', 'unrepress'); ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="updates-plugins-themes">
        <?php require_once ABSPATH . 'wp-admin/update-core.php'; ?>
    </section>
</div>
<style>
    .unrepress {
        .updates-plugins-themes {
            h1 {
                display: none;
            }

            h2.response {
                display: none;
            }

            .core-updates {
                display: none;
            }
        }
    }

    .notice, .update-nag {
        display: none !important;
    }
</style>
