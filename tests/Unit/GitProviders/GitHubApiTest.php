<?php

declare(strict_types=1);

use UnrePress\Tests\WordPressTestHelper;

test('GitHub API client is available', function () {
    // Verify the class exists
    expect(class_exists('Github\Client'))->toBeTrue();
});

test('GitLab API client is available', function () {
    // Verify the class exists
    expect(class_exists('Gitlab\Client'))->toBeTrue();
});

test('Bitbucket API client is available', function () {
    // Verify the class exists
    expect(class_exists('Bitbucket\Client'))->toBeTrue();
});

test('GitHub client can be instantiated', function () {
    $client = new Github\Client();
    expect($client)->toBeInstanceOf(Github\Client::class);
});

test('GitLab client can be instantiated', function () {
    $client = new Gitlab\Client();
    expect($client)->toBeInstanceOf(Gitlab\Client::class);
});

test('Bitbucket client can be instantiated', function () {
    $client = new Bitbucket\Client();
    expect($client)->toBeInstanceOf(Bitbucket\Client::class);
});
