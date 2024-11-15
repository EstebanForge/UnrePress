<?php

/*
|--------------------------------------------------------------------------
| WordPress Test Setup
|--------------------------------------------------------------------------
*/

// Define WordPress root directory root/wp-content/plugins/unrepress
define('ABSPATH', dirname(dirname(dirname(dirname(__DIR__)))) . '/');

// Load WordPress core
require_once ABSPATH . 'wp-load.php';

// Load our plugin
function _manually_load_plugin()
{
    require dirname(__DIR__) . '/unrepress.php';
}

// Register function to load our plugin
add_action('plugins_loaded', '_manually_load_plugin');
