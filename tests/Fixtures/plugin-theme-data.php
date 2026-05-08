<?php

declare(strict_types=1);

/**
 * Plugin and Theme Fixtures.
 *
 * Sample plugin and theme metadata for testing
 */

return [
    'plugins' => [
        'standard_plugin' => [
            'name' => 'Standard Plugin',
            'slug' => 'standard-plugin',
            'version' => '1.0.0',
            'author' => 'Test Author',
            'description' => 'A standard WordPress plugin',
            'plugin_uri' => 'https://wordpress.org/plugins/standard-plugin/',
            'author_uri' => 'https://example.com',
            'text_domain' => 'standard-plugin',
            'requires_wp' => '6.0',
            'requires_php' => '8.0',
            'update_uri' => 'https://github.com/username/standard-plugin',
            'github_repo' => 'username/standard-plugin',
            'branch' => 'main',
        ],

        'plugin_with_branch' => [
            'name' => 'Plugin with Branch',
            'slug' => 'plugin-with-branch',
            'version' => '2.5.0',
            'author' => 'Another Author',
            'description' => 'Plugin using develop branch',
            'plugin_uri' => 'https://example.com/plugin-with-branch',
            'author_uri' => 'https://example.com',
            'text_domain' => 'plugin-with-branch',
            'requires_wp' => '6.2',
            'requires_php' => '8.1',
            'update_uri' => 'https://github.com/username/plugin-with-branch',
            'github_repo' => 'username/plugin-with-branch',
            'branch' => 'develop',
        ],

        'plugin_with_git_extension' => [
            'name' => 'Plugin with .git extension',
            'slug' => 'plugin-with-git',
            'version' => '1.2.3',
            'author' => 'Git Author',
            'description' => 'Plugin repository URL has .git extension',
            'plugin_uri' => 'https://example.com/plugin-with-git',
            'author_uri' => 'https://example.com',
            'text_domain' => 'plugin-with-git',
            'update_uri' => 'https://github.com/username/plugin-with-git.git',
            'github_repo' => 'username/plugin-with-git.git',
            'branch' => 'main',
        ],
    ],

    'themes' => [
        'standard_theme' => [
            'name' => 'Standard Theme',
            'slug' => 'standard-theme',
            'version' => '1.0.0',
            'author' => 'Theme Author',
            'description' => 'A standard WordPress theme',
            'theme_uri' => 'https://example.com/standard-theme',
            'author_uri' => 'https://example.com',
            'text_domain' => 'standard-theme',
            'requires_wp' => '6.0',
            'requires_php' => '8.0',
            'update_uri' => 'https://github.com/username/standard-theme',
            'github_repo' => 'username/standard-theme',
            'branch' => 'main',
        ],

        'theme_with_custom_branch' => [
            'name' => 'Custom Branch Theme',
            'slug' => 'custom-branch-theme',
            'version' => '2.0.0',
            'author' => 'Theme Developer',
            'description' => 'Theme using custom branch',
            'theme_uri' => 'https://example.com/custom-branch-theme',
            'author_uri' => 'https://example.com',
            'text_domain' => 'custom-branch-theme',
            'requires_wp' => '6.3',
            'requires_php' => '8.1',
            'update_uri' => 'https://github.com/username/custom-branch-theme',
            'github_repo' => 'username/custom-branch-theme',
            'branch' => 'staging',
        ],
    ],

    'plugin_headers' => [
        'standard' => [
            'Name' => 'Standard Plugin',
            'PluginURI' => 'https://wordpress.org/plugins/standard-plugin/',
            'Version' => '1.0.0',
            'Description' => 'A standard WordPress plugin',
            'Author' => 'Test Author',
            'AuthorURI' => 'https://example.com',
            'TextDomain' => 'standard-plugin',
            'RequiresWP' => '6.0',
            'RequiresPHP' => '8.0',
            'UpdateURI' => 'https://github.com/username/standard-plugin',
        ],

        'minimal' => [
            'Name' => 'Minimal Plugin',
            'Version' => '0.1.0',
        ],

        'full' => [
            'Name' => 'Full Featured Plugin',
            'PluginURI' => 'https://example.com/full-featured',
            'Version' => '3.2.1',
            'Description' => 'Plugin with all possible headers',
            'Author' => 'Full Author',
            'AuthorURI' => 'https://example.com/full-author',
            'TextDomain' => 'full-featured',
            'DomainPath' => '/languages',
            'Network' => 'true',
            'RequiresWP' => '6.4',
            'RequiresPHP' => '8.2',
            'UpdateURI' => 'https://github.com/username/full-featured',
            'RequiresPlugins' => 'plugin1,plugin2',
        ],
    ],

    'theme_styles' => [
        'standard' => [
            'Name' => 'Standard Theme',
            'ThemeURI' => 'https://example.com/standard-theme',
            'Description' => 'A standard WordPress theme',
            'Author' => 'Theme Author',
            'AuthorURI' => 'https://example.com',
            'Version' => '1.0.0',
            'Template' => '',
            'Status' => 'publish',
            'Tags' => 'blog, custom-background, threaded-comments',
            'TextDomain' => 'standard-theme',
            'RequiresWP' => '6.0',
            'RequiresPHP' => '8.0',
        ],

        'child_theme' => [
            'Name' => 'Child Theme',
            'ThemeURI' => 'https://example.com/child-theme',
            'Description' => 'A child theme',
            'Author' => 'Child Author',
            'AuthorURI' => 'https://example.com',
            'Version' => '1.5.0',
            'Template' => 'parent-theme',
            'Status' => 'publish',
            'Tags' => 'child-theme',
            'TextDomain' => 'child-theme',
        ],
    ],
];
