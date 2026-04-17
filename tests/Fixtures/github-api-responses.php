<?php

declare(strict_types=1);

/**
 * GitHub API Response Fixtures
 *
 * Sample responses from GitHub API for testing
 */

return [
    'latest_release' => [
        'tag_name' => '6.5.0',
        'name' => 'WordPress 6.5.0',
        'body' => 'Release notes for WordPress 6.5.0',
        'html_url' => 'https://github.com/WordPress/WordPress/releases/tag/6.5.0',
        'zipball_url' => 'https://api.github.com/repos/WordPress/WordPress/zipball/6.5.0',
        'published_at' => '2024-03-01T00:00:00Z',
        'author' => [
            'login' => 'WordPress',
            'html_url' => 'https://github.com/WordPress',
        ],
    ],

    'tags_list' => [
        [
            'name' => '6.5.0',
            'zipball_url' => 'https://api.github.com/repos/WordPress/WordPress/zipball/6.5.0',
            'tarball_url' => 'https://api.github.com/repos/WordPress/WordPress/tarball/6.5.0',
            'commit' => [
                'sha' => 'abc123',
                'url' => 'https://api.github.com/repos/WordPress/WordPress/commits/abc123',
            ],
        ],
        [
            'name' => '6.4.0',
            'zipball_url' => 'https://api.github.com/repos/WordPress/WordPress/zipball/6.4.0',
            'tarball_url' => 'https://api.github.com/repos/WordPress/WordPress/tarball/6.4.0',
            'commit' => [
                'sha' => 'def456',
                'url' => 'https://api.github.com/repos/WordPress/WordPress/commits/def456',
            ],
        ],
        [
            'name' => '6.3.0',
            'zipball_url' => 'https://api.github.com/repos/WordPress/WordPress/zipball/6.3.0',
            'tarball_url' => 'https://api.github.com/repos/WordPress/WordPress/tarball/6.3.0',
            'commit' => [
                'sha' => 'ghi789',
                'url' => 'https://api.github.com/repos/WordPress/WordPress/commits/ghi789',
            ],
        ],
    ],

    'repository_info' => [
        'id' => 12345678,
        'name' => 'WordPress',
        'full_name' => 'WordPress/WordPress',
        'private' => false,
        'owner' => [
            'login' => 'WordPress',
            'html_url' => 'https://github.com/WordPress',
        ],
        'html_url' => 'https://github.com/WordPress/WordPress',
        'description' => 'WordPress Git repository',
        'url' => 'https://api.github.com/repos/WordPress/WordPress',
        'default_branch' => 'trunk',
    ],

    'plugin_release' => [
        'tag_name' => '2.0.0',
        'name' => 'My Plugin 2.0.0',
        'body' => 'Release notes for plugin version 2.0.0',
        'html_url' => 'https://github.com/username/my-plugin/releases/tag/2.0.0',
        'zipball_url' => 'https://api.github.com/repos/username/my-plugin/zipball/2.0.0',
        'published_at' => '2024-02-01T00:00:00Z',
    ],

    'theme_release' => [
        'tag_name' => '1.5.0',
        'name' => 'My Theme 1.5.0',
        'body' => 'Release notes for theme version 1.5.0',
        'html_url' => 'https://github.com/username/my-theme/releases/tag/1.5.0',
        'zipball_url' => 'https://api.github.com/repos/username/my-theme/zipball/1.5.0',
        'published_at' => '2024-01-15T00:00:00Z',
    ],

    'rate_limit_exceeded' => [
        'message' => 'API rate limit exceeded',
        'documentation_url' => 'https://docs.github.com/rest/overview/rate-limits-for-the-rest-api',
    ],

    'not_found' => [
        'message' => 'Not Found',
        'documentation_url' => 'https://docs.github.com/rest',
    ],

    'timeout_error' => [
        'error' => 'timeout',
        'message' => 'Request timeout',
    ],
];
