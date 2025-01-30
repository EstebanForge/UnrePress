<?php

namespace UnrePress;

class Debugger
{
    private static $log = [];

    /**
     * Log a message to the PHP error log and the internal log array only if WP_DEBUG is true.
     *
     * @param string|array|object $message The message to log.
     *
     * @return void
     */
    public static function log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $formatted_message = is_array($message) ? print_r($message, true) :
                               (is_object($message) ? print_r($message, true) : $message);
            self::$log[] = date('Y-m-d H:i:s') . ' - ' . $formatted_message;
            error_log('UnrePress: ' . $formatted_message);
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
