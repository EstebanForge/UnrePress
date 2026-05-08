<?php

declare(strict_types=1);

namespace UnrePress\GitProviders;

/**
 * Interface for Git provider API clients.
 */
interface GitProviderInterface
{
    /**
     * Get the latest release version for a repository.
     *
     * @param string $owner Repository owner/organization
     * @param string $repo Repository name
     * @return string|null Latest version tag or null if not found
     */
    public function getLatestRelease(string $owner, string $repo): ?string;

    /**
     * Get repository information.
     *
     * @param string $owner Repository owner/organization
     * @param string $repo Repository name
     * @return array<string, mixed> Repository data
     */
    public function getRepository(string $owner, string $repo): array;

    /**
     * Get download URL for a specific tag/version.
     *
     * @param string $owner Repository owner/organization
     * @param string $repo Repository name
     * @param string $tag Version tag
     * @return string Download URL
     */
    public function getDownloadUrl(string $owner, string $repo, string $tag): string;

    /**
     * Get all tags for a repository.
     *
     * @param string $owner Repository owner/organization
     * @param string $repo Repository name
     * @return array<string> List of tag names
     */
    public function getTags(string $owner, string $repo): array;

    /**
     * Authenticate with an API token.
     *
     * @param string $token API token
     * @return void
     */
    public function authenticate(string $token): void;
}
