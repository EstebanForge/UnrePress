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
        $apiUrl = self::GITHUB_API_URL . $repo . self::GITHUB_TAGS;

        $response = wp_remote_get($apiUrl);

        if (is_wp_error($response)) {
            Debugger::log('Error fetching latest version: ' . $response->get_error_message());

            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (! is_array($data) || empty($data)) {
            Debugger::log('Invalid or empty response from GitHub API');

            return false;
        }

        // Grab the latest release
        $data = $data[0];

        Debugger::log("Latest version data fetched: " . print_r($data, true));

        $latestVersion = $data['name'] ?? null;

        return $latestVersion;
    }
}
