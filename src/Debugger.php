<?php

namespace UnrePress;

class Debugger
{
    private static $log = [];

    /**
     * Log messages to the PHP error log and the internal log array only if WP_DEBUG is true.
     *
     * @param mixed ...$args Multiple arguments to log.
     *
     * @return void
     */
    public static function log(...$args)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (count($args) === 1) {
                $message = $args[0];
                $formatted_message = is_array($message) ? print_r($message, true) :
                                   (is_object($message) ? print_r($message, true) : $message);
            } else {
                // Format multiple arguments as a readable string
                $formatted_parts = [];
                foreach ($args as $arg) {
                    $formatted_parts[] = is_array($arg) ? print_r($arg, true) :
                                       (is_object($arg) ? print_r($arg, true) : $arg);
                }
                $formatted_message = implode(' ', $formatted_parts);
            }

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
