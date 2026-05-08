<?php

declare(strict_types=1);

namespace UnrePress\UpdaterProvider;

use InvalidArgumentException;
use UnrePress\GitProviders\GitProviderFactory;
use UnrePress\GitProviders\GitProviderInterface;

/**
 * Unified wrapper for Git provider API clients.
 *
 * This class wraps the modern Git provider implementations (GitHub, GitLab, Bitbucket)
 * behind the legacy ProviderInterface, maintaining backward compatibility while
 * leveraging robust API clients.
 */
class GitProviderWrapper implements ProviderInterface
{
    private ?GitProviderInterface $provider = null;
    private ?string $detectedProvider = null;

    /**
     * Get the download URL for a given repository and version.
     *
     * @param string $repo The repository slug (e.g., "owner/repo")
     * @param string $version The version to download (e.g., a tag name)
     * @return string The download URL
     */
    public function getDownloadUrl(string $repo, string $version): string
    {
        [$owner, $repository] = $this->parseRepository($repo);
        $provider = $this->getProviderForRepository($repo);

        return $provider->getDownloadUrl($owner, $repository, $version);
    }

    /**
     * Return the latest version of a repository.
     *
     * @param string $repo The repository slug (e.g., "owner/repo" or full URL)
     * @return string|null The latest version, or null on error
     */
    public function getLatestVersion(string $repo): ?string
    {
        [$owner, $repository] = $this->parseRepository($repo);
        $provider = $this->getProviderForRepository($repo);

        return $provider->getLatestRelease($owner, $repository);
    }

    /**
     * Make a request to a given URL (legacy method for backward compatibility).
     *
     * @param string $url The URL to make the request to
     * @return string|false The response body, or false on error
     * @deprecated Use specific provider methods instead
     */
    public function makeRequest(string $url): string|false
    {
        unrepress_debug('GitProviderWrapper::makeRequest() - Called for URL: ' . $url);

        // For backward compatibility, we'll try to detect the provider and make appropriate calls
        try {
            $provider = GitProviderFactory::createFromUrl($url);
            [$owner, $repo] = GitProviderFactory::parseRepositoryFromUrl($url);

            // Check if this is a tags/releases URL
            if (str_contains($url, '/tags') || str_contains($url, '/releases')) {
                $tags = $provider->getTags($owner, $repo);

                return json_encode($tags);
            }

            // Default to repository info
            $repoData = $provider->getRepository($owner, $repo);

            return json_encode($repoData);
        } catch (InvalidArgumentException $e) {
            unrepress_debug('GitProviderWrapper::makeRequest() - Error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Complete WordPress plugin/theme popup.
     *
     * @param array|false|object $result The result object or array. Default false.
     * @param string $action The type of information being requested from the Plugin Installation API.
     * @param object $args Plugin API arguments.
     * @return bool|array
     */
    public function packagePopup(bool|array|object $result, string $action, object $args): bool|array
    {
        if ('plugin_information' !== $action || empty($args->slug)) {
            return false;
        }

        return $result;
    }

    /**
     * Parse repository string into owner and repo components.
     *
     * @param string $repo Repository string (e.g., "owner/repo" or full URL)
     * @return array{0: string, 1: string} [owner, repo]
     */
    private function parseRepository(string $repo): array
    {
        // If it's a full URL, use the factory to parse it
        if (filter_var($repo, FILTER_VALIDATE_URL)) {
            return GitProviderFactory::parseRepositoryFromUrl($repo);
        }

        // Otherwise assume it's in "owner/repo" format
        $parts = explode('/', $repo);
        if (count($parts) < 2) {
            throw new \InvalidArgumentException(sprintf('Invalid repository format: %s', $repo));
        }

        $owner = $parts[0];
        $repository = implode('/', array_slice($parts, 1));

        return [$owner, $repository];
    }

    /**
     * Get the appropriate Git provider for a repository.
     *
     * @param string $repo Repository string or URL
     * @return GitProviderInterface
     */
    private function getProviderForRepository(string $repo): GitProviderInterface
    {
        // If it's a URL, detect provider from URL
        if (filter_var($repo, FILTER_VALIDATE_URL)) {
            $this->detectedProvider = $this->detectProviderFromUrl($repo);

            return $this->createProvider($this->detectedProvider);
        }

        // For "owner/repo" format, we need to determine the provider
        // Check if we can detect from context or use a default
        if ($this->detectedProvider === null) {
            // Default to GitHub for backward compatibility
            $this->detectedProvider = 'github';
        }

        return $this->createProvider($this->detectedProvider);
    }

    /**
     * Detect Git provider from URL.
     *
     * @param string $url Repository URL
     * @return string Provider name
     */
    private function detectProviderFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || $host === null) {
            return 'github'; // Default
        }

        $host = strtolower($host);

        if (str_contains($host, 'github.com')) {
            return 'github';
        }

        if (str_contains($host, 'gitlab.com')) {
            return 'gitlab';
        }

        if (str_contains($host, 'bitbucket.org')) {
            return 'bitbucket';
        }

        return 'github'; // Default to GitHub
    }

    /**
     * Create a Git provider instance.
     *
     * @param string $providerType Provider type (github, gitlab, bitbucket)
     * @return GitProviderInterface
     */
    private function createProvider(string $providerType): GitProviderInterface
    {
        return GitProviderFactory::create($providerType);
    }

    /**
     * Set the Git provider type to use for subsequent operations.
     *
     * @param string $providerType Provider type (github, gitlab, bitbucket)
     * @return void
     */
    public function setProviderType(string $providerType): void
    {
        $this->detectedProvider = $providerType;
    }

    /**
     * Get the currently detected provider type.
     *
     * @return string|null
     */
    public function getProviderType(): ?string
    {
        return $this->detectedProvider;
    }
}
