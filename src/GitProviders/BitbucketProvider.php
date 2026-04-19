<?php

declare(strict_types=1);

namespace UnrePress\GitProviders;

use Bitbucket\Client;
use Bitbucket\Exception\ClientErrorException;
use Bitbucket\Exception\ExceptionInterface;

/**
 * Bitbucket API provider implementation.
 */
class BitbucketProvider implements GitProviderInterface
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function getLatestRelease(string $owner, string $repo): ?string
    {
        try {
            $refs = $this->client->repositories()->refs($owner, $repo)->list('tags');
            $tags = $refs['values'] ?? [];

            if (empty($tags)) {
                return null;
            }

            // Sort tags by date (descending)
            usort($tags, function($a, $b) {
                $dateA = strtotime($a['date'] ?? 'now');
                $dateB = strtotime($b['date'] ?? 'now');
                return $dateB <=> $dateA;
            });

            return $tags[0]['name'] ?? null;
        } catch (ClientErrorException | ExceptionInterface $e) {
            return null;
        }
    }

    public function getRepository(string $owner, string $repo): array
    {
        try {
            $data = $this->client->repositories()->get($owner, $repo);

            return [
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'homepage' => $data['website'] ?? '',
                'default_branch' => $data['mainbranch']['name'] ?? 'main',
                'private' => $data['is_private'] ?? false,
                'fork' => false,
                'created_at' => $data['created_on'] ?? '',
                'updated_at' => $data['updated_on'] ?? '',
                'pushed_at' => '',
                'stargazers_count' => 0,
                'watchers_count' => 0,
                'forks_count' => 0,
                'open_issues_count' => 0,
            ];
        } catch (ClientErrorException | ExceptionInterface $e) {
            return [];
        }
    }

    public function getDownloadUrl(string $owner, string $repo, string $tag): string
    {
        return sprintf(
            'https://bitbucket.org/%s/%s/get/%s.zip',
            $owner,
            $repo,
            $tag
        );
    }

    public function getTags(string $owner, string $repo): array
    {
        try {
            $refs = $this->client->repositories()->refs($owner, $repo)->list('tags');
            $tags = $refs['values'] ?? [];
            return array_map(fn($tag) => $tag['name'], $tags);
        } catch (ClientErrorException | ExceptionInterface $e) {
            return [];
        }
    }

    public function authenticate(string $token): void
    {
        $this->client->authenticate(Client::AUTH_OAUTH_TOKEN, $token);
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
