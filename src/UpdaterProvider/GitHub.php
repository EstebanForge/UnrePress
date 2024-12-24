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
     * @return string|null The latest version, or null on error
     */
    public function getLatestVersion(string $repo): ?string
    {
        $url = "https://github.com/{$repo}/tags";

        // Convert to API URL
        $url = (new \UnrePress\Helpers())->normalizeTagUrl($url);

        $response = $this->makeGitHubRequest($url);
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

    private function makeGitHubRequest(string $url)
    {
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
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return false;
        }

        return $body;
    }
}
