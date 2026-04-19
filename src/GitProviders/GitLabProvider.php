<?php

declare(strict_types=1);

namespace UnrePress\GitProviders;

use Gitlab\Client;
use Gitlab\Exception\ErrorException;
use Gitlab\Exception\ExceptionInterface;

/**
 * GitLab API provider implementation.
 */
class GitLabProvider implements GitProviderInterface
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function getLatestRelease(string $owner, string $repo): ?string
    {
        try {
            // GitLab client doesn't have releases API, so we use tags
            return $this->getLatestTag($owner, $repo);
        } catch (ErrorException|ExceptionInterface $e) {
            return null;
        }
    }

    public function getRepository(string $owner, string $repo): array
    {
        try {
            $projectId = sprintf('%s/%s', $owner, $repo);
            $data = $this->client->projects()->show($projectId);

            return [
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'homepage' => $data['web_url'] ?? '',
                'default_branch' => $data['default_branch'] ?? 'main',
                'private' => $data['visibility'] !== 'public',
                'fork' => $data['forked_from_project'] !== null,
                'created_at' => $data['created_at'] ?? '',
                'updated_at' => $data['last_activity_at'] ?? '',
                'pushed_at' => $data['last_activity_at'] ?? '',
                'stargazers_count' => $data['star_count'] ?? 0,
                'watchers_count' => 0,
                'forks_count' => $data['forks_count'] ?? 0,
                'open_issues_count' => 0,
            ];
        } catch (ErrorException|ExceptionInterface $e) {
            return [];
        }
    }

    public function getDownloadUrl(string $owner, string $repo, string $tag): string
    {
        return sprintf(
            'https://gitlab.com/api/v4/projects/%s%%2F%s/repository/archive.zip?sha=%s',
            $owner,
            $repo,
            $tag
        );
    }

    public function getTags(string $owner, string $repo): array
    {
        try {
            $projectId = sprintf('%s/%s', $owner, $repo);
            $tags = $this->client->repositories()->tags($projectId);

            return array_map(fn ($tag) => $tag['name'], $tags);
        } catch (ErrorException|ExceptionInterface $e) {
            return [];
        }
    }

    public function authenticate(string $token): void
    {
        $this->client->authenticate($token, Client::AUTH_HTTP_TOKEN);
    }

    private function getLatestTag(string $owner, string $repo): ?string
    {
        try {
            $tags = $this->getTags($owner, $repo);
            if (empty($tags)) {
                return null;
            }

            // Sort tags by version (descending)
            usort($tags, function ($a, $b) {
                return version_compare(ltrim($b, 'v'), ltrim($a, 'v'));
            });

            return $tags[0] ?? null;
        } catch (ExceptionInterface $e) {
            return null;
        }
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
