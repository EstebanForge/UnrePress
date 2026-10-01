<?php
/**
 * Uninstall cleanup for UnrePress.
 *
 * Removes settings (which hold plaintext provider tokens), tracked repos,
 * and every unrepress_ transient. Token-bearing options must not outlive
 * the plugin.
 *
 * @package UnrePress
 */

// Exit if not actually uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('unrepress_settings');
delete_option('unrepress_tracked_repos');

global $wpdb;

$like = $wpdb->esc_like('_transient_unrepress_') . '%';
$timeout_like = $wpdb->esc_like('_transient_timeout_unrepress_') . '%';

$names = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$like,
		$timeout_like
	)
);

foreach ((array) $names as $option_name) {
	if (str_starts_with($option_name, '_transient_timeout_')) {
		$transient = substr($option_name, strlen('_transient_timeout_'));
	} else {
		$transient = substr($option_name, strlen('_transient_'));
	}

	delete_option($option_name);

	if ('' !== $transient && function_exists('delete_transient')) {
		delete_transient($transient);
	}
}
