<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Ray stub
 * https://myray.app
 */

if (!function_exists('ray')) {
    function ray($variable = null)
    {
        return new class () {
            public function __call($name, $arguments)
            {
                // Handle all dynamic method calls
                return $this;
            }

            public static function __callStatic($name, $arguments)
            {
                // Handle all static method calls (if needed)
                return new self();
            }
        };
    }
}
