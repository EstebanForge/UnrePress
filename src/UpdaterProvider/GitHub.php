<?php

namespace UnrePress\UpdaterProvider;

use UnrePress\Debugger;

class GitHub implements ProviderInterface
{
    private const GITHUB_API_URL = 'https://api.github.com/repos/';
    private const GITHUB_TAGS = '/tags';

    /**
     * Return the download URL for a given GitHub repository and version.
     *
     * @param string $repo The GitHub repository slug
     * @param string $version The version to download (e.g. a tag name)
     * @return string The download URL
     */
    public function getDownloadUrl(string $repo, string $version): string
    {
        return "https://github.com/{$repo}/archive/refs/tags/{$version}.zip";
    }

    /**
     * Return the latest version of a GitHub repository.
     *
     * @param string $repo The GitHub repository slug
     * @return string|null The latest version, or null on error
     */
    public function getLatestVersion(string $repo): ?string
    {
        $url = "https://github.com/{$repo}/tags";
        Debugger::log("Getting tags from: " . $url);

        $response = $this->makeGitHubRequest($url);
        if (!$response) {
            Debugger::log("No response from GitHub");
            return null;
        }

        // Extract version from HTML response using regex
        if (preg_match('/<h4[^>]*>.*?([0-9]+\.[0-9]+(?:\.[0-9]+)?(?:-[a-zA-Z0-9.]+)?)[^<]*<\/h4>/i', $response, $matches)) {
            $latest = $matches[1];
            Debugger::log("Latest version found: " . $latest);
            return $latest;
        }

        Debugger::log("No version found in response");
        return null;
    }

    private function getGitHubHeaders(): array
    {
        $token = defined('UNREPRESS_GITHUB_TOKEN') ? UNREPRESS_GITHUB_TOKEN : '';
        return [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'UnrePress/1.0',
            'Authorization' => 'Bearer ' . $token,
            'X-GitHub-Api-Version' => '2022-11-28'
        ];
    }

    private function getTagsUrl(string $repository): string
    {
        // Convert repository URL to tags URL
        $tagsUrl = str_replace('github.com', 'github.com', $repository);
        return rtrim($tagsUrl, '/') . '/tags';
    }

    private function makeGitHubRequest(string $url): ?string
    {
        Debugger::log("=== Starting GitHub API Request ===");
        $headers = $this->getGitHubHeaders();
        
        Debugger::log("Making GitHub API request to: " . $url);
        Debugger::log("Request headers: " . print_r($headers, true));

        $args = [
            'headers' => $headers,
            'timeout' => 15,
        ];
        
        Debugger::log("WP Remote request args: " . print_r($args, true));
        
        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            Debugger::log("GitHub API request failed with error: " . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        $request_headers = wp_remote_retrieve_response_headers($response);
        
        Debugger::log("GitHub API Response Code: " . $code);
        Debugger::log("GitHub API Request Headers Actually Sent: " . print_r($request_headers, true));
        Debugger::log("GitHub API Response Headers: " . print_r($response_headers, true));
        if ($code !== 200) {
            Debugger::log("GitHub API Error Response:");
            Debugger::log("Status Code: " . $code);
            Debugger::log("Response Body: " . $body);
            Debugger::log("X-RateLimit-Limit: " . wp_remote_retrieve_header($response, 'x-ratelimit-limit'));
            Debugger::log("X-RateLimit-Remaining: " . wp_remote_retrieve_header($response, 'x-ratelimit-remaining'));
            Debugger::log("X-RateLimit-Reset: " . wp_remote_retrieve_header($response, 'x-ratelimit-reset'));
            return null;
        }

        return $body;
    }
}
