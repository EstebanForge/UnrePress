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
        $url = "https://github.com/{$repo}/archive/refs/tags/{$version}.zip";
        Debugger::log("Generated GitHub download URL: " . $url);
        Debugger::log("Repository: " . $repo);
        Debugger::log("Version: " . $version);
        return $url;
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

        // Convert to API URL
        $url = (new \UnrePress\Helpers())->normalizeTagUrl($url);
        Debugger::log("Normalized tags URL: " . $url);

        $response = $this->makeGitHubRequest($url);
        if (!$response) {
            Debugger::log("No response from GitHub");
            return null;
        }

        $tags = json_decode($response);
        if (!is_array($tags) || empty($tags)) {
            Debugger::log("No tags found in response");
            return null;
        }

        // Get first tag (GitHub returns them in descending order)
        $latest = $tags[0]->name;
        Debugger::log("Latest version found: " . $latest);
        return ltrim($latest, 'v'); // Remove 'v' prefix if present
    }

    private function makeGitHubRequest(string $url)
    {
        Debugger::log("Making GitHub request to: " . $url);

        $args = [
            'timeout' => 5,
            'redirection' => 5,
            'httpversion' => '1.0',
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            'blocking' => true,
            'headers' => [],
            'cookies' => [],
            'body' => null,
            'compress' => false,
            'decompress' => true,
            'sslverify' => true,
            'stream' => false,
            'filename' => null
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            Debugger::log("GitHub request error: " . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        Debugger::log("GitHub response code: " . $response_code);

        if ($response_code !== 200) {
            Debugger::log("GitHub request failed with response code: " . $response_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            Debugger::log("GitHub response body is empty");
            return false;
        }

        return $body;
    }
}
