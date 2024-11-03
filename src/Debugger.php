<?php

namespace UnrePress;

class Debugger
{
    private static $log = [];

    /**
     * Log a message to the PHP error log and the internal log array only if WP_DEBUG is true.
     *
     * @param string $message The message to log.
     *
     * @return void
     */
    public static function log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::$log[] = date('Y-m-d H:i:s') . ' - ' . $message;
            error_log('UnrePress Debug: ' . $message);
        }
    }

    /**
     * Returns the internal log array, which contains all messages logged with the
     * `log` method.
     *
     * @return array The internal log array.
     */
    public static function get_log()
    {
        return self::$log;
    }
}
