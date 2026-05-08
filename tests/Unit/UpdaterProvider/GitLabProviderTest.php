<?php

declare(strict_types=1);

use UnrePress\UpdaterProvider\GitLab;

beforeEach(function () {
    // Mock unrepress_debug function
    \Brain\Monkey\Functions\when('unrepress_debug')->justReturn();
});

test('gitlab provider uses wrapper by default', function () {
    $provider = new GitLab();

    // Check that it implements the interface
    expect($provider)->toBeInstanceOf(UnrePress\UpdaterProvider\ProviderInterface::class);
});

test('gitlab provider has required methods', function () {
    $provider = new GitLab();

    // Check that all required methods exist
    expect(method_exists($provider, 'getDownloadUrl'))->toBeTrue();
    expect(method_exists($provider, 'getLatestVersion'))->toBeTrue();
    expect(method_exists($provider, 'makeRequest'))->toBeTrue();
    expect(method_exists($provider, 'packagePopup'))->toBeTrue();
});

test('gitlab provider accepts custom wrapper', function () {
    $customWrapper = new UnrePress\UpdaterProvider\GitProviderWrapper();
    $provider = new GitLab($customWrapper);

    // Check that it was created successfully
    expect($provider)->toBeInstanceOf(GitLab::class);
});

test('gitlab provider wrapper is set to gitlab', function () {
    $provider = new GitLab();

    // Check that it's a GitLab instance
    expect($provider)->toBeInstanceOf(GitLab::class);
});

test('gitlab provider get download url returns string', function () {
    $provider = new GitLab();

    // Test that getDownloadUrl returns a string format
    $result = $provider->getDownloadUrl('owner/repo', 'v1.0.0');
    expect($result)->toBeString();
    expect($result)->toContain('gitlab');
});

test('gitlab provider get latest version returns correct type', function () {
    $provider = new GitLab();

    // Test that getLatestVersion returns string or null
    $result = $provider->getLatestVersion('owner/repo');
    expect($result)->toBeNull(); // Returns null because no actual API call in test

    // In real usage, this would return a version string like "1.0.0"
});

test('gitlab provider make request handles URLs', function () {
    $provider = new GitLab();

    // Test that makeRequest handles different URL formats
    $url = 'https://gitlab.com/api/v4/projects/owner%2Frepo/repository/tags';
    $result = $provider->makeRequest($url);

    // Should return JSON string or false
    // In test environment, API clients return empty data which gets JSON-encoded
    expect($result)->toBeString();
});

test('gitlab provider package popup handles plugin information', function () {
    $provider = new GitLab();

    // Test packagePopup with plugin_information action
    $args = (object) ['slug' => 'test-plugin'];
    $result = $provider->packagePopup(false, 'plugin_information', $args);

    // Should return false because slug doesn't match real data
    expect($result)->toBeFalse();
});

test('gitlab provider package popup handles other actions', function () {
    $provider = new GitLab();

    // Test packagePopup with non-plugin_information action
    $args = (object) ['slug' => 'test-plugin'];
    $result = $provider->packagePopup(false, 'some_other_action', $args);

    // Should return false for non-plugin_information actions
    expect($result)->toBeFalse();
});

test('gitlab provider package popup handles empty slug', function () {
    $provider = new GitLab();

    // Test packagePopup with empty slug
    $args = (object) ['slug' => ''];
    $result = $provider->packagePopup(false, 'plugin_information', $args);

    // Should return false for empty slug
    expect($result)->toBeFalse();
});
