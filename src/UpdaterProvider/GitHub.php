<?php

namespace UnrePress\UpdaterProvider;

use UnrePress\UpdaterProvider\ProviderInterface;

class GitHub implements ProviderInterface
{
    /**
     * Return the download URL for a given GitHub repository and version.
     *
     * @param string $repo The GitHub repository slug
     * @param string $version The version to download (e.g. a tag name)
     *
     * @return string The download URL
     */
    public function getDownloadUrl(string $repo, string $version): string
    {
        $url = "https://github.com/{$repo}/archive/refs/tags/{$version}.zip";

        return $url;
    }

    /**
     * Return the latest version of a GitHub repository.
     *
     * @param string $repo The GitHub repository slug
     *
     * @return string|null The latest version, or null on error
     */
    public function getLatestVersion(string $repo): ?string
    {
        $url = "https://github.com/{$repo}/tags";

        // Convert to API URL
        $url = (new \UnrePress\Helpers())->normalizeTagUrl($url);

        $response = $this->makeRequest($url);
        if (!$response) {
            return null;
        }

        $tags = json_decode($response);
        if (!is_array($tags) || empty($tags)) {
            return null;
        }

        // Get first tag (GitHub returns them in descending order)
        $latest = $tags[0]->name;

        return ltrim($latest, 'v'); // Remove 'v' prefix if present
    }

    /**
     * Make a request to a given URL.
     *
     * @param string $url The URL to make the request to
     *
     * @return string|false The response body, or false on error
     */
    public function makeRequest(string $url): string|false
    {
        // Generate a unique transient key based on the URL
        $cache_key = UNREPRESS_PREFIX . 'request_github_' . md5($url);

        // Try to get cached response
        $cached_response = get_transient($cache_key);
        if (false !== $cached_response) {
            return $cached_response;
        }

        $args = [
            'method'      => 'GET',
            'timeout'     => 5,
            'redirection' => 5,
            'httpversion' => '1.0',
            'user-agent'  => 'Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0',
            'headers'     => [],
            'sslverify'   => true,
        ];

        // Add GitHub token authentication if available
        if (defined('UNREPRESS_TOKEN_GITHUB') && !empty(UNREPRESS_TOKEN_GITHUB)) {
            $args['headers']['Authorization'] = 'token ' . UNREPRESS_TOKEN_GITHUB;
        }

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        if (empty($response_body)) {
            return false;
        }

        $decoded_body = json_decode($response_body, true);
        if (empty($decoded_body)) {
            return false;
        }

        // If response is for a download URL and token is available, append it
        if (defined('UNREPRESS_TOKEN_GITHUB') && !empty(UNREPRESS_TOKEN_GITHUB)) {
            $decoded_body['zipball_url'] = add_query_arg('access_token', UNREPRESS_TOKEN_GITHUB, $decoded_body['zipball_url']);
            $response_body = json_encode($decoded_body);
        }

        // Cache the response for 5 minutes (300 seconds)
        set_transient($cache_key, $response_body, 5 * MINUTE_IN_SECONDS);

        return $response_body;
    }

    /**
     * Complete WordPress plugin/theme popup.
     *
     * @param array|false|object $result The result object or array. Default false.
     * @param string             $action The type of information being requested from the Plugin Installation API.
     * @param object             $args   Plugin API arguments.
     *
     * @return bool|array
     */
    public function packagePopup(bool|array|object $result, string $action, object $args): bool|array
    {
        if ('plugin_information' !== $action || empty($args->slug)) {
            return false;
        }

        return $result;
    }
}
