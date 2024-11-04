<?php

namespace UnrePress\UpdaterProvider;

interface ProviderInterface
{
    /**
     * Return the download URL for a given repository and version.
     *
     * @param string $repo The repository slug
     * @param string $version The version to download (e.g. a tag name)
     * @return string The download URL
     */
    public function getDownloadUrl(string $repo, string $version): string;

    /**
     * Return the latest version of a repository.
     *
     * @param string $repo The repository slug
     * @return string|null The latest version, or null on error
     */
    public function getLatestVersion(string $repo): ?string;
}
