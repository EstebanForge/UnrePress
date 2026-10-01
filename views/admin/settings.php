<?php
/**
 * UnrePress Settings page.
 *
 * @package UnrePress
 */

use UnrePress\Admin\Settings;

defined('ABSPATH') or die();

$settings = Settings::getSettings();
$providers = [
	'github' => __('GitHub personal access token', 'unrepress'),
	'gitlab' => __('GitLab personal access token', 'unrepress'),
	'bitbucket' => __('Bitbucket app password', 'unrepress'),
];
$discovery_on = \UnrePress\Index\GitHubCatalog::isEnabled();
?>
<div class="wrap">
	<h1><?php esc_html_e('UnrePress Settings', 'unrepress'); ?></h1>

	<?php if (isset($_GET['cache-cleared'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Update caches flushed.', 'unrepress'); ?></p></div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields('unrepress_settings'); ?>

		<h2 class="title"><?php esc_html_e('Git provider tokens', 'unrepress'); ?></h2>
		<p class="description">
			<?php esc_html_e('Tokens authenticate git provider requests and lift rate limits. They are stored in the database in plain text; for high-security hosts define the constants in wp-config.php instead.', 'unrepress'); ?>
		</p>

		<table class="form-table" role="presentation">
			<?php foreach ($providers as $provider => $label) : ?>
				<?php $constant = 'UNREPRESS_TOKEN_' . strtoupper($provider); ?>
				<?php $current = $settings[$provider . '_token'] ?? ''; ?>
				<tr>
					<th scope="row"><label for="unrepress-<?php echo esc_attr($provider); ?>-token"><?php echo esc_html($label); ?></label></th>
					<td>
						<?php if (defined($constant) && is_string(constant($constant)) && '' !== constant($constant)) : ?>
							<code><?php echo esc_html(sprintf(
								/* translators: %s: masked token */
								__('overridden by constant (%s)', 'unrepress'),
								Settings::maskToken(constant($constant))
							)); ?></code>
						<?php else : ?>
							<input
								type="password"
								id="unrepress-<?php echo esc_attr($provider); ?>-token"
								name="<?php echo esc_attr(Settings::OPTION_KEY . '[' . $provider . '_token]'); ?>"
								value=""
								class="regular-text"
								autocomplete="new-password"
								placeholder="<?php echo esc_attr(is_string($current) && '' !== $current ? Settings::maskToken($current) : ''); ?>"
							/>
							<p class="description">
								<?php echo esc_html(is_string($current) && '' !== $current
									? __('A token is stored. Leave the field empty to keep it; tick the box to delete it.', 'unrepress')
									: __('No token stored.', 'unrepress')); ?>
							</p>
							<?php if (is_string($current) && '' !== $current) : ?>
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr(Settings::OPTION_KEY . '[' . $provider . '_token_clear]'); ?>"
										value="1"
									/>
									<?php esc_html_e('Delete stored token', 'unrepress'); ?>
								</label>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h2 class="title"><?php esc_html_e('Discovery', 'unrepress'); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e('GitHub discovery', 'unrepress'); ?></th>
				<td>
					<label for="unrepress-github-discovery">
						<input
							type="checkbox"
							id="unrepress-github-discovery"
							name="<?php echo esc_attr(Settings::OPTION_KEY . '[github_discovery]'); ?>"
							value="1"
							<?php checked($discovery_on); ?>
						/>
						<?php esc_html_e('Include GitHub topic-tagged plugins and themes in search', 'unrepress'); ?>
					</label>
					<?php if (defined('UNREPRESS_GITHUB_DISCOVERY')) : ?>
						<p class="description"><?php esc_html_e('Overridden by the UNREPRESS_GITHUB_DISCOVERY constant.', 'unrepress'); ?></p>
					<?php endif; ?>
					<p class="description"><?php esc_html_e('GitHub results are not verified by the UnrePress index and are always labeled.', 'unrepress'); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<hr />

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<h2 class="title"><?php esc_html_e('Maintenance', 'unrepress'); ?></h2>
		<p class="description"><?php esc_html_e('Flush all UnrePress update and index caches. Useful after the index publishes new data.', 'unrepress'); ?></p>
		<input type="hidden" name="action" value="unrepress_clear_cache" />
		<?php wp_nonce_field('unrepress_clear_cache'); ?>
		<?php submit_button(__('Flush update caches', 'unrepress'), 'secondary', 'submit', true); ?>
	</form>
</div>
