<?php

use UnrePress\Helpers;

// No direct access
defined('ABSPATH') or die();

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/update.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';

// Verify nonce if force-check is requested
if (isset($_GET['force-check']) && $_GET['force-check'] == 1) {
    // Force refresh of plugin and theme updates
    wp_update_plugins();
    wp_update_themes();

    // Clear transients
    Helpers::clearUpdateTransients();
}

// Ensure we're in admin context
if (!is_admin()) {
    wp_die(__('Access Denied', 'unrepress'));
}
?>
<div class="wrap">
    <h1><?php esc_html_e('UnrePress Updates', 'unrepress'); ?>
    </h1>
    <p><?php printf(esc_html__('Last checked on %s.', 'unrepress'), $wpLastChecked); ?>
    </p>

    <section class="updates-core">
        <h2><?php esc_html_e('WordPress Core', 'unrepress'); ?>
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
</style>
