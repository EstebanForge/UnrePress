<?php

declare(strict_types=1);

namespace UnrePress\UpdaterProvider;

use UnrePress\GitProviders\GitProviderFactory;

/**
 * GitHub provider using modern Git provider clients.
 *
 * This class now acts as a facade that uses the GitProviderWrapper
 * to leverage modern API clients instead of manual wp_remote_* calls.
 */
class GitHub implements ProviderInterface
{
    private GitProviderWrapper $wrapper;

    public function __construct(?GitProviderWrapper $wrapper = null)
    {
        $this->wrapper = $wrapper ?? new GitProviderWrapper();
        $this->wrapper->setProviderType('github');
    }

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
        unrepress_debug('GitHub::getDownloadUrl() - Called with repo: ' . $repo . ', version: ' . $version);

        $downloadUrl = $this->wrapper->getDownloadUrl($repo, $version);
        unrepress_debug('GitHub::getDownloadUrl() - Result: ' . $downloadUrl);

        return $downloadUrl;
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

        $latestVersion = $this->wrapper->getLatestVersion($repo);
        unrepress_debug('GitHub::getLatestVersion() - Result: ' . ($latestVersion ?? 'null'));

        return $latestVersion;
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

        $response = $this->wrapper->makeRequest($url);
        unrepress_debug('GitHub::makeRequest() - Response received: ' . ($response ? 'YES' : 'NO'));

        return $response;
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
