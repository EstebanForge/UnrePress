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
                '<a href="%s" class="button button-primary regular">%s</a>',
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
		<h2><?php esc_html_e('Plugins', 'unrepress'); ?> (<?php echo count($pluginUpdates); ?>)</h2>

		<?php if (empty($pluginUpdates)): ?>
			<p><?php esc_html_e('Your plugins are all up to date.', 'unrepress'); ?></p>
		<?php else: ?>
			<p><?php esc_html_e('The following plugins have new versions available. Check the ones you want to update and then click "Update Plugins".', 'unrepress'); ?></p>

			<form method="post" action="" class="upgrade">
				<?php wp_nonce_field('bulk-update-plugins'); ?>
				<div class="update-plugins">
					<table class="widefat updates-table" id="update-plugins-table">
						<thead>
							<tr>
								<td class="manage-column check-column">
									<input type="checkbox" id="plugins-select-all">
								</td>
								<td class="manage-column">
									<label for="plugins-select-all"><?php esc_html_e('Select All', 'unrepress'); ?></label>
								</td>
							</tr>
						</thead>

						<tbody class="plugins">
						<?php foreach ($pluginUpdates as $plugin):
							$checkbox_id = 'checkbox_' . md5($plugin['file']);
						?>
							<tr>
								<td class="check-column">
									<input type="checkbox" name="checked[]" id="<?php echo esc_attr($checkbox_id); ?>" value="<?php echo esc_attr($plugin['file']); ?>">
									<label for="<?php echo esc_attr($checkbox_id); ?>">
										<span class="screen-reader-text">
											<?php printf(esc_html__('Select %s', 'unrepress'), esc_html($plugin['name'])); ?>
										</span>
									</label>
								</td>
								<td class="plugin-title">
									<p>
										<span class="dashicons dashicons-admin-plugins"></span>
										<strong><?php echo esc_html($plugin['name']); ?></strong>
										<?php
										printf(
											esc_html__('You have version %1$s installed. Update to %2$s.', 'unrepress'),
											esc_html($plugin['version']),
											esc_html($plugin['new_version'])
										);
										?>
										<a href="<?php echo esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=' . dirname($plugin['file']) . '&section=changelog&TB_iframe=true&width=772&height=898')); ?>"
											class="thickbox open-plugin-details-modal"
											aria-label="<?php printf(esc_attr__('View %s version %s details', 'unrepress'), esc_attr($plugin['name']), esc_attr($plugin['new_version'])); ?>">
											<?php printf(esc_html__('View version %s details', 'unrepress'), esc_html($plugin['new_version'])); ?>.
										</a>
										<br>
										<?php
										printf(
											esc_html__('Compatibility with WordPress %1$s: %2$s', 'unrepress'),
											get_bloginfo('version'),
											esc_html__('Unknown', 'unrepress')
										);
										if (!empty($plugin['update_message'])): ?>
											| <?php echo wp_kses_post($plugin['update_message']); ?>
										<?php endif; ?>
									</p>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>

						<tfoot>
							<tr>
								<td class="manage-column check-column">
									<input type="checkbox" id="plugins-select-all-2">
								</td>
								<td class="manage-column">
									<label for="plugins-select-all-2"><?php esc_html_e('Select All', 'unrepress'); ?></label>
								</td>
							</tr>
						</tfoot>
					</table>
					<p><input type="submit" class="button" value="<?php esc_attr_e('Update Plugins', 'unrepress'); ?>" /></p>
				</div>
			</form>
		<?php endif; ?>
	</section>

	<section class="updates-themes">
		<h2><?php esc_html_e('Themes', 'unrepress'); ?> (<?php echo count($themeUpdates); ?>)</h2>

		<?php if (empty($themeUpdates)): ?>
			<p><?php esc_html_e('Your themes are all up to date.', 'unrepress'); ?></p>
		<?php else: ?>
			<p><?php esc_html_e('The following themes have new versions available. Check the ones you want to update and then click "Update Themes".', 'unrepress'); ?></p>

			<form method="post" action="" class="upgrade">
				<?php wp_nonce_field('bulk-update-themes'); ?>
				<div class="update-themes">
					<table class="widefat updates-table" id="update-themes-table">
						<thead>
							<tr>
								<td class="manage-column check-column"><input type="checkbox" id="themes-select-all"></td>
								<td class="manage-column"><label for="themes-select-all"><?php esc_html_e('Select All', 'unrepress'); ?></label></td>
							</tr>
						</thead>

						<tbody class="plugins">
						<?php foreach ($themeUpdates as $theme):
							$checkbox_id = 'checkbox_' . md5($theme['theme']);
						?>
							<tr>
								<td class="check-column">
									<input type="checkbox" name="checked[]" id="<?php echo esc_attr($checkbox_id); ?>" value="<?php echo esc_attr($theme['theme']); ?>">
									<label for="<?php echo esc_attr($checkbox_id); ?>">
										<span class="screen-reader-text">
											<?php printf(esc_html__('Select %s', 'unrepress'), esc_html($theme['name'])); ?>
										</span>
									</label>
								</td>
								<td class="plugin-title"><p>
									<img src="<?php echo esc_url($theme['screenshot']); ?>" width="85" height="64" class="updates-table-screenshot" alt="">
									<strong><?php echo esc_html($theme['name']); ?></strong>
									<?php printf(
										esc_html__('You have version %1$s installed. Update to %2$s.', 'unrepress'),
										esc_html($theme['version']),
										esc_html($theme['new_version'])
									); ?>
								</p></td>
							</tr>
						<?php endforeach; ?>
						</tbody>

						<tfoot>
							<tr>
								<td class="manage-column check-column"><input type="checkbox" id="themes-select-all-2"></td>
								<td class="manage-column"><label for="themes-select-all-2"><?php esc_html_e('Select All', 'unrepress'); ?></label></td>
							</tr>
						</tfoot>
					</table>
					<p><input type="submit" class="button" value="<?php esc_attr_e('Update Themes', 'unrepress'); ?>" /></p>
				</div>
			</form>
		<?php endif; ?>
	</section>
</div>
<div class="clear"></div>
