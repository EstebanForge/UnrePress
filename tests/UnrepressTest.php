<?php

test('plugin can be activated without fatal errors', function () {
    // Get the plugin file path relative to WordPress plugins directory
    $plugin_dir = dirname(__DIR__);
    $plugin_basename = basename($plugin_dir);
    $plugin = $plugin_basename . '/unrepress.php';

    // Attempt to activate the plugin
    $result = activate_plugin($plugin);

    // Check for activation errors
    if (is_wp_error($result)) {
        throw new Exception("Plugin activation failed: " . $result->get_error_message());
    }

    // Verify plugin is active
    $is_active = is_plugin_active($plugin);

    // Cleanup - deactivate plugin
    deactivate_plugins($plugin);

    // Assert plugin was activated successfully
    expect($is_active)->toBeTrue();
});
