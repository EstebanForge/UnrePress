<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') or die();

if (!function_exists('unrepress_debug')) {
    /**
     * Helper function to log debug messages using the UnrePress Debugger.
     * Only logs when WP_DEBUG is true.
     *
     * @param string|array|object $message The message to log
     * @return void
     */
    function unrepress_debug($message): void
    {
        UnrePress\Debugger::log($message);
    }
}
