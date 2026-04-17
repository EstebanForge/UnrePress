<?php

declare(strict_types=1);

/**
 * Update Object Fixtures
 *
 * Sample update objects that WordPress expects
 */

return [
    'core_update' => (object) [
        'response' => 'upgrade',
        'download' => 'https://github.com/WordPress/WordPress/archive/refs/tags/6.5.0.zip',
        'locale' => 'en_US',
        'packages' => (object) [
            'full' => 'https://github.com/WordPress/WordPress/archive/refs/tags/6.5.0.zip',
            'no_content' => false,
            'new_bundled' => false,
            'partial' => false,
            'rollback' => false,
        ],
        'current' => '6.4.0',
        'version' => '6.5.0',
        'php_version' => '8.0',
        'mysql_version' => '5.7',
        'new_version' => '6.5.0',
        'new_bundled' => '6.5.0',
        'partial_version' => false,
    ],

    'plugin_update' => (object) [
        'id' => 'standard-plugin/standard-plugin.php',
        'slug' => 'standard-plugin',
        'plugin' => 'standard-plugin/standard-plugin.php',
        'new_version' => '2.0.0',
        'old_version' => '1.0.0',
        'url' => 'https://github.com/username/standard-plugin',
        'package' => 'https://github.com/username/standard-plugin/archive/refs/tags/2.0.0.zip',
        'tested' => '6.5',
        'requires_php' => '8.0',
        'compatibility' => (object) [
            '6.5' => (object) [
                '6.5' => (object) [
                    '1.0.0' => (object) [
                        '5.5' => true,
                        '5.6' => true,
                    ],
                ],
            ],
        ],
    ],

    'theme_update' => (object) [
        'theme' => 'standard-theme',
        'new_version' => '1.5.0',
        'old_version' => '1.0.0',
        'url' => 'https://github.com/username/standard-theme',
        'package' => 'https://github.com/username/standard-theme/archive/refs/tags/1.5.0.zip',
        'requires' => '6.0',
        'requires_php' => '8.0',
        'tested' => '6.5',
    ],

    'plugin_update_offer' => (object) [
        'slug' => 'standard-plugin',
        'new_version' => '2.0.0',
        'version' => '2.0.0',
        'url' => 'https://github.com/username/standard-plugin',
        'package' => 'https://github.com/username/standard-plugin/archive/refs/tags/2.0.0.zip',
        'tested' => '6.5',
        'requires_php' => '8.0',
        'compatibility' => (object) [],
        'upgrade_notice' => 'New version available!',
        'downloads' => 1000,
        'last_updated' => '2024-02-01',
        'sections' => (object) [
            'description' => 'Plugin description',
            'installation' => 'Installation instructions',
            'changelog' => 'Changelog entries',
            'upgrade_notice' => 'Upgrade notice',
        ],
        'download_link' => 'https://github.com/username/standard-plugin/archive/refs/tags/2.0.0.zip',
    ],

    'theme_update_offer' => (object) [
        'theme' => 'standard-theme',
        'new_version' => '1.5.0',
        'url' => 'https://github.com/username/standard-theme',
        'package' => 'https://github.com/username/standard-theme/archive/refs/tags/1.5.0.zip',
        'requires' => '6.0',
        'requires_php' => '8.0',
        'tested' => '6.5',
        'sections' => (object) [
            'description' => 'Theme description',
            'installation' => 'Installation instructions',
            'changelog' => 'Changelog entries',
        ],
    ],

    'translation_update' => (object) [
        'language' => 'es_ES',
        'version' => '6.5.0',
        'updated' => '2024-02-01',
        'package' => 'https://downloads.wordpress.org/translation/core/6.5.0/es_ES.zip',
        'iso' => 'es',
        'english_name' => 'Spanish (Spain)',
        'native_name' => 'Español',
    ],
];
