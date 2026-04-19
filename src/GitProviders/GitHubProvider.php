<?php

declare(strict_types=1);

namespace UnrePress\GitProviders;

use Github\Client;
use Github\Exception\ErrorException;
use Github\Exception\RuntimeException;

/**
 * GitHub API provider implementation.
 */
class GitHubProvider implements GitProviderInterface
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function getLatestRelease(string $owner, string $repo): ?string
    {
        try {
            $release = $this->client->api('repo')->releases()->latest($owner, $repo);
            return $release['tag_name'] ?? null;
        } catch (ErrorException | RuntimeException $e) {
            return null;
        }
    }

    public function getRepository(string $owner, string $repo): array
    {
        try {
            $data = $this->client->api('repo')->show($owner, $repo);
            return [
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'homepage' => $data['homepage'] ?? '',
                'default_branch' => $data['default_branch'] ?? 'main',
                'private' => $data['private'] ?? false,
                'fork' => $data['fork'] ?? false,
                'created_at' => $data['created_at'] ?? '',
                'updated_at' => $data['updated_at'] ?? '',
                'pushed_at' => $data['pushed_at'] ?? '',
                'stargazers_count' => $data['stargazers_count'] ?? 0,
                'watchers_count' => $data['watchers_count'] ?? 0,
                'forks_count' => $data['forks_count'] ?? 0,
                'open_issues_count' => $data['open_issues_count'] ?? 0,
            ];
        } catch (ErrorException | RuntimeException $e) {
            return [];
        }
    }

    public function getDownloadUrl(string $owner, string $repo, string $tag): string
    {
        return sprintf(
            'https://api.github.com/repos/%s/%s/zipball/%s',
            $owner,
            $repo,
            $tag
        );
    }

    public function getTags(string $owner, string $repo): array
    {
        try {
            $tags = $this->client->api('repo')->tags($owner, $repo);
            return array_map(fn($tag) => $tag['name'], $tags);
        } catch (ErrorException | RuntimeException $e) {
            return [];
        }
    }

    public function authenticate(string $token): void
    {
        $this->client->authenticate($token, null, Client::AUTH_ACCESS_TOKEN);
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
