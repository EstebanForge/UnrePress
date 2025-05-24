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
        unrepress_debug('GitHub::getLatestVersion() - Called with repo: ' . $repo);

        $url = "https://github.com/{$repo}/tags";
        unrepress_debug('GitHub::getLatestVersion() - Original URL: ' . $url);

        // Convert to API URL
        $url = (new \UnrePress\Helpers())->normalizeTagUrl($url);
        unrepress_debug('GitHub::getLatestVersion() - Normalized URL: ' . $url);

        $response = $this->makeRequest($url);
        unrepress_debug('GitHub::getLatestVersion() - Response received: ' . ($response ? 'YES' : 'NO'));

        if (!$response) {
            unrepress_debug('GitHub::getLatestVersion() - No response received, returning null');
            return null;
        }

        unrepress_debug('GitHub::getLatestVersion() - Response length: ' . strlen($response));
        unrepress_debug('GitHub::getLatestVersion() - Response start (first 100 chars): ' . substr($response, 0, 100));

        $tags = json_decode($response);
        unrepress_debug('GitHub::getLatestVersion() - JSON decode result type: ' . gettype($tags));
        unrepress_debug('GitHub::getLatestVersion() - Is array: ' . (is_array($tags) ? 'YES' : 'NO'));

        if (is_array($tags)) {
            unrepress_debug('GitHub::getLatestVersion() - Tag count: ' . count($tags));
        } else {
            unrepress_debug('GitHub::getLatestVersion() - JSON decode error: ' . json_last_error_msg());
        }

        if (!is_array($tags) || empty($tags)) {
            unrepress_debug('GitHub::getLatestVersion() - Invalid or empty tags response, returning null');
            return null;
        }

        // Get first tag (GitHub returns them in descending order)
        $latest = $tags[0]->name;
        unrepress_debug('GitHub::getLatestVersion() - Latest tag found: ' . $latest);

        $cleanVersion = ltrim($latest, 'v'); // Remove 'v' prefix if present
        unrepress_debug('GitHub::getLatestVersion() - Cleaned version: ' . $cleanVersion);

        return $cleanVersion;
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
        unrepress_debug('GitHub::makeRequest() - Called for URL: ' . $url);

        // Generate a unique transient key based on the URL
        $cache_key = UNREPRESS_PREFIX . 'request_github_' . md5($url);
        unrepress_debug('GitHub::makeRequest() - Cache key: ' . $cache_key);

        // Try to get cached response
        $cached_response = get_transient($cache_key);
        if (false !== $cached_response) {
            unrepress_debug('GitHub::makeRequest() - Using cached response (length: ' . strlen($cached_response) . ')');
            return $cached_response;
        }

        unrepress_debug('GitHub::makeRequest() - No cached response, making fresh request');

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
        $github_token = unrepress_get_github_token();
        if (!empty($github_token)) {
            $args['headers']['Authorization'] = 'token ' . $github_token;
            unrepress_debug('GitHub::makeRequest() - Added GitHub token to Authorization header');
        } else {
            unrepress_debug('GitHub::makeRequest() - No GitHub token available, making unauthenticated request');
        }

        unrepress_debug('GitHub::makeRequest() - Making wp_remote_get request');
        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            unrepress_debug('GitHub::makeRequest() - wp_remote_get failed with WP_Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        unrepress_debug('GitHub::makeRequest() - Response code: ' . $response_code);

        if ($response_code !== 200) {
            $error_body = wp_remote_retrieve_body($response);
            unrepress_debug('GitHub::makeRequest() - Non-200 response code, body: ' . substr($error_body, 0, 200));
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        if (empty($response_body)) {
            unrepress_debug('GitHub::makeRequest() - Empty response body received');
            return false;
        }

        unrepress_debug('GitHub::makeRequest() - Response body length: ' . strlen($response_body));

        // Validate JSON response
        $decoded_body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            unrepress_debug('GitHub::makeRequest() - Invalid JSON response: ' . json_last_error_msg());
            unrepress_debug('GitHub::makeRequest() - Response start: ' . substr($response_body, 0, 200));
            return false;
        }

        unrepress_debug('GitHub::makeRequest() - Valid JSON response, caching for 5 minutes');

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
