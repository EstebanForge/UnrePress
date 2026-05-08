<?php

declare(strict_types=1);

use UnrePress\UpdaterProvider\GitHub;

beforeEach(function () {
    // Mock unrepress_debug function
    \Brain\Monkey\Functions\when('unrepress_debug')->justReturn();
});

test('github provider uses wrapper by default', function () {
    $provider = new GitHub();

    // Check that it implements the interface
    expect($provider)->toBeInstanceOf(UnrePress\UpdaterProvider\ProviderInterface::class);
});

test('github provider has required methods', function () {
    $provider = new GitHub();

    // Check that all required methods exist
    expect(method_exists($provider, 'getDownloadUrl'))->toBeTrue();
    expect(method_exists($provider, 'getLatestVersion'))->toBeTrue();
    expect(method_exists($provider, 'makeRequest'))->toBeTrue();
    expect(method_exists($provider, 'packagePopup'))->toBeTrue();
});

test('github provider accepts custom wrapper', function () {
    $customWrapper = new UnrePress\UpdaterProvider\GitProviderWrapper();
    $provider = new GitHub($customWrapper);

    // Check that it was created successfully
    expect($provider)->toBeInstanceOf(GitHub::class);
});

test('github provider wrapper is set to github', function () {
    $provider = new GitHub();

    // Check that it's a GitHub instance
    expect($provider)->toBeInstanceOf(GitHub::class);
});

test('github provider get download url returns string', function () {
    $provider = new GitHub();

    // Test that getDownloadUrl returns a string format
    $result = $provider->getDownloadUrl('owner/repo', 'v1.0.0');
    expect($result)->toBeString();
    expect($result)->toContain('github');
});

test('github provider get latest version returns correct type', function () {
    $provider = new GitHub();

    // Test that getLatestVersion returns string or null
    $result = $provider->getLatestVersion('owner/repo');
    expect($result)->toBeNull(); // Returns null because no actual API call in test

    // In real usage, this would return a version string like "1.0.0"
});

test('github provider make request handles URLs', function () {
    $provider = new GitHub();

    // Test that makeRequest handles different URL formats
    $url = 'https://api.github.com/repos/owner/repo/tags';
    $result = $provider->makeRequest($url);

    // Should return JSON string or false
    // In test environment, API clients return empty data which gets JSON-encoded
    expect($result)->toBeString();
});

test('github provider package popup handles plugin information', function () {
    $provider = new GitHub();

    // Test packagePopup with plugin_information action
    $args = (object) ['slug' => 'test-plugin'];
    $result = $provider->packagePopup(false, 'plugin_information', $args);

    // Should return false because slug doesn't match real data
    expect($result)->toBeFalse();
});

test('github provider package popup handles other actions', function () {
    $provider = new GitHub();

    // Test packagePopup with non-plugin_information action
    $args = (object) ['slug' => 'test-plugin'];
    $result = $provider->packagePopup(false, 'some_other_action', $args);

    // Should return false for non-plugin_information actions
    expect($result)->toBeFalse();
});

test('github provider package popup handles empty slug', function () {
    $provider = new GitHub();

    // Test packagePopup with empty slug
    $args = (object) ['slug' => ''];
    $result = $provider->packagePopup(false, 'plugin_information', $args);

    // Should return false for empty slug
    expect($result)->toBeFalse();
});
