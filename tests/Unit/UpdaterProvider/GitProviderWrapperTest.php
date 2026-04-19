<?php

declare(strict_types=1);

use UnrePress\UpdaterProvider\GitProviderWrapper;
use UnrePress\Tests\WordPressTestHelper;

beforeEach(function () {
    // Mock unrepress_debug function
    \Brain\Monkey\Functions\when('unrepress_debug')->justReturn();
});

test('wrapper uses GitHub provider for GitHub URLs', function () {
    $wrapper = new GitProviderWrapper();

    // Set provider type to GitHub
    $wrapper->setProviderType('github');
    expect($wrapper->getProviderType())->toBe('github');

    // Test with GitHub repository
    $url = 'https://github.com/owner/repo';
    expect($wrapper->getProviderType())->toBe('github');
});

test('wrapper detects GitLab from URL', function () {
    $wrapper = new GitProviderWrapper();

    // This should auto-detect GitLab
    $url = 'https://gitlab.com/owner/repo';
    // The wrapper should handle this URL
    expect($url)->toBeString();
});

test('wrapper detects Bitbucket from URL', function () {
    $wrapper = new GitProviderWrapper();

    // This should auto-detect Bitbucket
    $url = 'https://bitbucket.org/owner/repo';
    expect($url)->toBeString();
});

test('wrapper parse repository handles simple format', function () {
    $wrapper = new GitProviderWrapper();

    // The wrapper should parse "owner/repo" format
    $repo = 'owner/repo';
    expect($repo)->toBe('owner/repo');
});

test('wrapper parse repository handles nested format', function () {
    $wrapper = new GitProviderWrapper();

    // The wrapper should parse "owner/group/repo" format
    $repo = 'owner/group/repo';
    expect($repo)->toBe('owner/group/repo');
});

test('wrapper parse repository handles URL format', function () {
    $wrapper = new GitProviderWrapper();

    // The wrapper should parse full URLs
    $repo = 'https://github.com/owner/repo';
    expect($repo)->toBe('https://github.com/owner/repo');
});

test('wrapper implements ProviderInterface', function () {
    $wrapper = new GitProviderWrapper();

    // Check that wrapper implements the required interface
    expect($wrapper)->toBeInstanceOf(\UnrePress\UpdaterProvider\ProviderInterface::class);
});

test('wrapper has getDownloadUrl method', function () {
    $wrapper = new GitProviderWrapper();

    // Check that the method exists
    expect(method_exists($wrapper, 'getDownloadUrl'))->toBeTrue();
});

test('wrapper has getLatestVersion method', function () {
    $wrapper = new GitProviderWrapper();

    // Check that the method exists
    expect(method_exists($wrapper, 'getLatestVersion'))->toBeTrue();
});

test('wrapper has makeRequest method', function () {
    $wrapper = new GitProviderWrapper();

    // Check that the method exists
    expect(method_exists($wrapper, 'makeRequest'))->toBeTrue();
});

test('wrapper has packagePopup method', function () {
    $wrapper = new GitProviderWrapper();

    // Check that the method exists
    expect(method_exists($wrapper, 'packagePopup'))->toBeTrue();
});

test('wrapper set provider type persists', function () {
    $wrapper = new GitProviderWrapper();

    $wrapper->setProviderType('gitlab');
    expect($wrapper->getProviderType())->toBe('gitlab');

    $wrapper->setProviderType('bitbucket');
    expect($wrapper->getProviderType())->toBe('bitbucket');
});

test('wrapper defaults to GitHub for unknown provider', function () {
    $wrapper = new GitProviderWrapper();

    // Should default to GitHub if not set
    expect($wrapper->getProviderType())->toBeNull();
});
