<?php

declare(strict_types=1);

use UnrePress\GitProviders\GitProviderFactory;
use UnrePress\GitProviders\GitHubProvider;
use UnrePress\GitProviders\GitLabProvider;
use UnrePress\GitProviders\BitbucketProvider;

test('factory creates GitHub provider', function () {
    $provider = GitProviderFactory::create('github');
    expect($provider)->toBeInstanceOf(GitHubProvider::class);
});

test('factory creates GitLab provider', function () {
    $provider = GitProviderFactory::create('gitlab');
    expect($provider)->toBeInstanceOf(GitLabProvider::class);
});

test('factory creates Bitbucket provider', function () {
    $provider = GitProviderFactory::create('bitbucket');
    expect($provider)->toBeInstanceOf(BitbucketProvider::class);
});

test('factory throws for unknown provider', function () {
    expect(fn() => GitProviderFactory::create('unknown'))
        ->toThrow(InvalidArgumentException::class);
});

test('factory detects GitHub from URL', function () {
    $provider = GitProviderFactory::createFromUrl('https://github.com/owner/repo');
    expect($provider)->toBeInstanceOf(GitHubProvider::class);
});

test('factory detects GitLab from URL', function () {
    $provider = GitProviderFactory::createFromUrl('https://gitlab.com/owner/repo');
    expect($provider)->toBeInstanceOf(GitLabProvider::class);
});

test('factory detects Bitbucket from URL', function () {
    $provider = GitProviderFactory::createFromUrl('https://bitbucket.org/owner/repo');
    expect($provider)->toBeInstanceOf(BitbucketProvider::class);
});

test('factory throws for invalid URL', function () {
    expect(fn() => GitProviderFactory::createFromUrl('not-a-url'))
        ->toThrow(InvalidArgumentException::class);
});

test('factory throws for unknown provider in URL', function () {
    expect(fn() => GitProviderFactory::createFromUrl('https://unknown.com/owner/repo'))
        ->toThrow(InvalidArgumentException::class);
});

test('parse repository from GitHub URL', function () {
    [$owner, $repo] = GitProviderFactory::parseRepositoryFromUrl('https://github.com/owner/repo');
    expect($owner)->toBe('owner');
    expect($repo)->toBe('repo');
});

test('parse repository from GitLab URL', function () {
    [$owner, $repo] = GitProviderFactory::parseRepositoryFromUrl('https://gitlab.com/owner/repo.git');
    expect($owner)->toBe('owner');
    expect($repo)->toBe('repo');
});

test('parse repository from Bitbucket URL', function () {
    [$owner, $repo] = GitProviderFactory::parseRepositoryFromUrl('https://bitbucket.org/owner/repo');
    expect($owner)->toBe('owner');
    expect($repo)->toBe('repo');
});

test('parse repository with nested path', function () {
    [$owner, $repo] = GitProviderFactory::parseRepositoryFromUrl('https://github.com/owner/group/repo');
    expect($owner)->toBe('owner');
    expect($repo)->toBe('group/repo');
});

test('parse repository throws for invalid URL', function () {
    expect(fn() => GitProviderFactory::parseRepositoryFromUrl('not-a-url'))
        ->toThrow(InvalidArgumentException::class);
});

test('factory authenticates GitHub with token', function () {
    $provider = GitProviderFactory::create('github', 'test-token');
    expect($provider)->toBeInstanceOf(GitHubProvider::class);
});

test('factory authenticates GitLab with token', function () {
    $provider = GitProviderFactory::create('gitlab', 'test-token');
    expect($provider)->toBeInstanceOf(GitLabProvider::class);
});

test('factory authenticates Bitbucket with token', function () {
    $provider = GitProviderFactory::create('bitbucket', 'test-token');
    expect($provider)->toBeInstanceOf(BitbucketProvider::class);
});
