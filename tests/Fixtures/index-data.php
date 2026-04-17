<?php

declare(strict_types=1);

/**
 * Index Fixtures
 *
 * Sample index data for plugins and themes
 */

return [
    'plugins_index' => [
        'version' => '1.0',
        'generated' => '2024-03-01T00:00:00Z',
        'plugins' => [
            [
                'slug' => 'standard-plugin',
                'name' => 'Standard Plugin',
                'version' => '1.0.0',
                'author' => 'Test Author',
                'description' => 'A standard WordPress plugin',
                'download_url' => 'https://github.com/username/standard-plugin/archive/refs/tags/1.0.0.zip',
                'homepage' => 'https://github.com/username/standard-plugin',
                'requires' => '6.0',
                'requires_php' => '8.0',
                'tested_up_to' => '6.5',
                'last_updated' => '2024-02-01',
            ],
            [
                'slug' => 'another-plugin',
                'name' => 'Another Plugin',
                'version' => '2.3.0',
                'author' => 'Another Author',
                'description' => 'Another plugin in the index',
                'download_url' => 'https://github.com/username/another-plugin/archive/refs/tags/2.3.0.zip',
                'homepage' => 'https://github.com/username/another-plugin',
                'requires' => '6.2',
                'requires_php' => '8.1',
                'tested_up_to' => '6.5',
                'last_updated' => '2024-02-15',
            ],
        ],
    ],

    'themes_index' => [
        'version' => '1.0',
        'generated' => '2024-03-01T00:00:00Z',
        'themes' => [
            [
                'slug' => 'standard-theme',
                'name' => 'Standard Theme',
                'version' => '1.0.0',
                'author' => 'Theme Author',
                'description' => 'A standard WordPress theme',
                'download_url' => 'https://github.com/username/standard-theme/archive/refs/tags/1.0.0.zip',
                'homepage' => 'https://github.com/username/standard-theme',
                'requires' => '6.0',
                'requires_php' => '8.0',
                'tested_up_to' => '6.5',
                'last_updated' => '2024-01-20',
            ],
            [
                'slug' => 'another-theme',
                'name' => 'Another Theme',
                'version' => '1.2.0',
                'author' => 'Theme Dev',
                'description' => 'Another theme in the index',
                'download_url' => 'https://github.com/username/another-theme/archive/refs/tags/1.2.0.zip',
                'homepage' => 'https://github.com/username/another-theme',
                'requires' => '6.3',
                'requires_php' => '8.1',
                'tested_up_to' => '6.5',
                'last_updated' => '2024-02-10',
            ],
        ],
    ],

    'main_index' => [
        'version' => '1.0',
        'generated' => '2024-03-01T00:00:00Z',
        'wordpress' => [
            'latest_version' => '6.5.0',
            'download_url' => 'https://github.com/WordPress/WordPress/archive/refs/tags/6.5.0.zip',
        ],
        'plugins_url' => 'https://example.com/index/plugins.json',
        'themes_url' => 'https://example.com/index/themes.json',
    ],
];
