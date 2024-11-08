<?php

namespace UnrePress\Views;

// No direct access
defined('ABSPATH') or die();

?>
<div class="wrap unrepress">
    <h1><?php esc_html_e('Updates', 'unrepress'); ?>
    </h1>

    <hr class="wp-header-end">

    <section class="updates-core">
        <h2 class="wp-current-version">
            <?php esc_html_e('Current version', 'unrepress'); ?>:
            <?php echo $wpLocalVersion; ?>
        </h2>

        <p>
            <?php
            printf(
                /* translators: %s: WordPress version. */
                __('You are currently using WordPress version %s'),
                $wpLocalVersion
            );
            ?>
        </p>

        <p class="response">
            <?php esc_html_e('Latest WordPress version', 'unrepress'); ?>:
            <?php echo $wpLatestVersion; ?>
        </p>

        <p class="update-last-checked">
            <?php
                $forceCheckUrl = admin_url('index.php?page=unrepress-updater&force-check=1');
                $forceCheckUrl = add_query_arg('_wpnonce', wp_create_nonce('update-core'), $forceCheckUrl);
            ?>
            <?php esc_html_e('Last checked', 'unrepress'); ?>:
            <?php echo $wpLastChecked; ?>. <a
                href="<?php echo $forceCheckUrl; ?>"><?php esc_html_e('Check again', 'unrepress'); ?>.</a>
        </p>

        <?php
        if ($updateNeeded):
        ?>
            <ul class="core-updates">
                <li>
                    <h3>
                        <?php esc_html_e('WordPress update available', 'unrepress'); ?>
                    </h3>
                    <p>
                        <?php
                        $updateCoreUrl = admin_url('index.php?page=unrepress-updater&do_update=core');
                        $updateCoreUrl = add_query_arg('_wpnonce', wp_create_nonce('update-core'), $updateCoreUrl);

                        printf(
                            /* translators: 1: WordPress version, 2: WordPress version. */
                            __('You are currently running WordPress version %1$s. The latest version is %2$s', 'unrepress'),
                            $wpLocalVersion,
                            $wpLatestVersion
                        );

                        echo '<br/>';

                        printf(
                            '<a href="%s" class="button">%s</a>',
                            $updateCoreUrl,
                            __('Update now', 'unrepress')
                        );
                        ?>
                    </p>
                </li>
            </ul>
        <?php
        endif;
        ?>
    </section>

    <section class="updates-plugins">
        <h2><?php esc_html_e('Plugins', 'unrepress'); ?>
        </h2>
        <p>
            <?php esc_html_e('Your plugins are all up to date.', 'unrepress'); ?>
        </p>
    </section>

    <section class="updates-themes">
        <h2><?php esc_html_e('Themes', 'unrepress'); ?>
        </h2>
        <p>
            <?php esc_html_e('Your themes are all up to date.', 'unrepress'); ?>
        </p>
    </section>
</div>
<div class="clear"></div>
