<?php

namespace UnrePress\UpdaterProvider;

interface ProviderInterface
{
    /**
     * Return the download URL for a given repository and version.
     *
     * @param string $repo The repository slug
     * @param string $version The version to download (e.g. a tag name)
     *
     * @return string The download URL
     */
    public function getDownloadUrl(string $repo, string $version): string;

    /**
     * Return the latest version of a repository.
     *
     * @param string $repo The repository slug
     *
     * @return string|null The latest version, or null on error
     */
    public function getLatestVersion(string $repo): ?string;

    /**
     * Make a request to a given URL.
     *
     * @param string $url The URL to make the request to
     * @return string|false The response body, or false on error
     */
    public function makeRequest(string $url): string|false;

    /**
     * Complete WordPress plugin/theme popup
     *
     * @param array|false|object $result The result object or array. Default false.
     * @param string             $action The type of information being requested from the Plugin Installation API.
     * @param object             $args   Plugin API arguments.
     *
     * @return bool|array
     */
    public function packagePopup(bool|array|object $result, string $action, object $args): bool|array;
}
