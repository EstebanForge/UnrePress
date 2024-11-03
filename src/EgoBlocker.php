<?php

namespace UnrePress;

class EgoBlocker
{
    public function __construct()
    {
        add_action('pre_http_request', [$this, 'BlockOrg'], 10, 3);
    }

    /**
     * Filter for http_api_curl hook. Blocks any requests that go to the hosts
     * specified in the UNREPRESS_BLOCKED_HOSTS constant.
     *
     * @link https://dustinrue.com/2023/03/simplistic-method-for-blocking-http-requests-in-wordpress/
     *
     * @param bool $preempt  Whether to preempt the request.
     * @param array   $parsed_args Parsed arguments for the request.
     * @param string  $uri       The URI of the request.
     *
     * @return bool True if the request should be blocked, false otherwise.
     */
    public function BlockOrg($preempt, $parsed_args, $uri)
    {

        if (! defined('UNREPRESS_BLOCKED_HOSTS')) {
            return false;
        }

        $check = parse_url($uri);
        if (! $check) {
            return false;
        }

        static $blocked_hosts = null;
        static $wildcard_regex = [];
        if (null === $blocked_hosts) {
            $blocked_hosts = preg_split('|,\s*|', UNREPRESS_BLOCKED_HOSTS);
            if (false !== strpos(UNREPRESS_BLOCKED_HOSTS, '*')) {
                $wildcard_regex = [];
                foreach ($blocked_hosts as $host) {
                    $wildcard_regex[] = str_replace('\*', '.+', preg_quote($host, '/'));
                }
                $wildcard_regex = '/^(' . implode('|', $wildcard_regex) . ')$/i';
            }
        }

        if (! empty($wildcard_regex)) {
            $results = preg_match($wildcard_regex, $check['host']);
            if ($results > 0) {
                Debugger::log(sprintf("Blocking %s://%s%s", $check['scheme'], $check['host'], $check['path']));
            }

            return $results > 0;
        } else {
            $results = in_array($check['host'], $blocked_hosts, true); // Inverse logic, if it's in the array, then block it.

            if ($results) {
                Debugger::log(sprintf("Blocking %s://%s%s", $check['scheme'], $check['host'], $check['path']));
            }

            return $results;
        }
    }
}
