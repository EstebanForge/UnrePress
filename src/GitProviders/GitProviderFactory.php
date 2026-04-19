<?php

declare(strict_types=1);

namespace UnrePress\GitProviders;

use InvalidArgumentException;

/**
 * Factory for creating Git provider instances.
 */
class GitProviderFactory
{
    private const GITHUB_DOMAINS = ['github.com', 'api.github.com'];
    private const GITLAB_DOMAINS = ['gitlab.com', 'gitlab.com/api/v4'];
    private const BITBUCKET_DOMAINS = ['bitbucket.org', 'api.bitbucket.org'];

    /**
     * Create a Git provider instance based on URL.
     *
     * @param string $url Repository URL
     * @param string|null $token Optional API token
     * @return GitProviderInterface
     * @throws InvalidArgumentException If URL is not recognized
     */
    public static function createFromUrl(string $url, ?string $token = null): GitProviderInterface
    {
        $provider = self::detectProviderFromUrl($url);

        return self::create($provider, $token);
    }

    /**
     * Create a Git provider instance by provider name.
     *
     * @param string $provider Provider name (github, gitlab, bitbucket)
     * @param string|null $token Optional API token
     * @return GitProviderInterface
     * @throws InvalidArgumentException If provider is not recognized
     */
    public static function create(string $provider, ?string $token = null): GitProviderInterface
    {
        return match (strtolower($provider)) {
            'github' => self::createGitHub($token),
            'gitlab' => self::createGitLab($token),
            'bitbucket' => self::createBitbucket($token),
            default => throw new InvalidArgumentException(sprintf('Unknown provider: %s', $provider)),
        };
    }

    private static function createGitHub(?string $token): GitHubProvider
    {
        $provider = new GitHubProvider();
        if ($token !== null) {
            $provider->authenticate($token);
        }

        return $provider;
    }

    private static function createGitLab(?string $token): GitLabProvider
    {
        $provider = new GitLabProvider();
        if ($token !== null) {
            $provider->authenticate($token);
        }

        return $provider;
    }

    private static function createBitbucket(?string $token): BitbucketProvider
    {
        $provider = new BitbucketProvider();
        if ($token !== null) {
            $provider->authenticate($token);
        }

        return $provider;
    }

    /**
     * Detect Git provider from URL.
     *
     * @param string $url Repository URL
     * @return string Provider name
     * @throws InvalidArgumentException If URL is not recognized
     */
    private static function detectProviderFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || $host === null) {
            throw new InvalidArgumentException(sprintf('Invalid URL: %s', $url));
        }

        $host = strtolower($host);

        if (in_array($host, self::GITHUB_DOMAINS, true)) {
            return 'github';
        }

        if (in_array($host, self::GITLAB_DOMAINS, true)) {
            return 'gitlab';
        }

        if (in_array($host, self::BITBUCKET_DOMAINS, true)) {
            return 'bitbucket';
        }

        throw new InvalidArgumentException(sprintf('Unknown Git provider in URL: %s', $url));
    }

    /**
     * Parse repository owner and name from URL.
     *
     * @param string $url Repository URL
     * @return array{0: string, 1: string} [owner, repo]
     * @throws InvalidArgumentException If URL cannot be parsed
     */
    public static function parseRepositoryFromUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === false || $path === null) {
            throw new InvalidArgumentException(sprintf('Invalid URL: %s', $url));
        }

        // Remove leading slash and .git extension
        $path = trim($path, '/');
        $path = preg_replace('/\.git$/', '', $path);

        if ($path === null || $path === '') {
            throw new InvalidArgumentException(sprintf('Invalid URL: %s', $url));
        }

        $parts = explode('/', $path);
        if (count($parts) < 2) {
            throw new InvalidArgumentException(sprintf('Invalid repository URL: %s', $url));
        }

        $owner = $parts[0];
        $repo = implode('/', array_slice($parts, 1));

        return [$owner, $repo];
    }
}
