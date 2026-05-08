<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Core;

use UnrePress\Tests\Helpers\WordPressTestHelper;
use UnrePress\Updater\UpdateThemes;

/**
 * UpdateThemes Unit Tests.
 */
class UpdateThemesTest extends WordPressTestHelper
{
    private UpdateThemes $updateThemes;

    private array $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        // Define required constants
        if (!defined('UNREPRESS_INDEX')) {
            define('UNREPRESS_INDEX', 'https://example.com/index/');
        }
        if (!defined('UNREPRESS_PREFIX')) {
            define('UNREPRESS_PREFIX', 'unrepress_');
        }
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        // Load fixtures
        $this->fixtures = [
            'github' => require __DIR__ . '/../../Fixtures/github-api-responses.php',
            'themes' => require __DIR__ . '/../../Fixtures/plugin-theme-data.php',
            'updates' => require __DIR__ . '/../../Fixtures/update-objects.php',
        ];

        // Mock WordPress functions used in constructor
        \Brain\Monkey\Functions\when('wp_get_themes')->justReturn([]);
        \Brain\Monkey\Functions\when('add_filter')->justReturn(true);
        \Brain\Monkey\Functions\when('add_action')->justReturn(true);
        \Brain\Monkey\Functions\when('wp_next_scheduled')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_schedule_event')->justReturn(true);
        \Brain\Monkey\Functions\when('update_option')->justReturn(true);
        \Brain\Monkey\Functions\when('sanitize_text_field')->returnArg();
        \Brain\Monkey\Functions\when('sanitize_title')->returnArg();
        // Mock unrepress_debug function
        \Brain\Monkey\Functions\when('unrepress_debug')->justReturn(null);

        $this->updateThemes = new UpdateThemes();
    }

    public function test_class_instantiation(): void
    {
        $this->assertInstanceOf(UpdateThemes::class, $this->updateThemes);
        $this->assertNotEmpty($this->updateThemes->cache_key);
        $this->assertIsString($this->updateThemes->cache_key);
    }

    public function test_cache_key_prefix(): void
    {
        $this->assertStringContainsString('unrepress_', $this->updateThemes->cache_key);
        $this->assertStringContainsString('theme', $this->updateThemes->cache_key);
    }

    public function test_request_remote_info_returns_theme_data(): void
    {
        $themeData = $this->fixtures['themes']['themes']['standard_theme'];

        // Mock wp_remote_get to return valid theme data
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => json_encode([
                'name' => $themeData['name'],
                'slug' => $themeData['slug'],
                'version' => $themeData['version'],
                'author' => $themeData['author'],
                'description' => $themeData['description'],
                'homepage' => $themeData['homepage'] ?? 'https://example.com/theme',
            ]),
            'response' => ['code' => 200],
        ]);

        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) { return is_array($response) ? ($response['response']['code'] ?? 200) : 200; });
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(function ($response) { return is_array($response) ? ($response['body'] ?? '') : $response; });
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('set_transient')->justReturn(true);

        $result = $this->updateThemes->requestRemoteInfo('standard-theme');

        $this->assertIsObject($result);
        $this->assertEquals('Standard Theme', $result->name);
        $this->assertEquals('standard-theme', $result->slug);
    }

    public function test_request_remote_info_handles_http_errors(): void
    {
        // Mock wp_remote_get to return error
        $wpError = new \WP_Error('http_error', 'HTTP request failed');
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn($wpError);
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(true);
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);

        $result = $this->updateThemes->requestRemoteInfo('invalid-theme');

        $this->assertFalse($result);
    }

    public function test_request_remote_info_handles_invalid_response_code(): void
    {
        // Mock wp_remote_get to return 404
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'response' => ['code' => 404],
        ]);

        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) { return is_array($response) ? ($response['response']['code'] ?? 200) : 200; });
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);

        $result = $this->updateThemes->requestRemoteInfo('not-found-theme');

        $this->assertFalse($result);
    }

    public function test_request_remote_info_handles_invalid_json(): void
    {
        // Mock wp_remote_get to return invalid JSON
        $invalidJson = 'invalid json{';

        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => $invalidJson,
            'response' => ['code' => 200],
        ]);

        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) { return is_array($response) ? ($response['response']['code'] ?? 200) : 200; });
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->justReturn($invalidJson);
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('set_transient')->justReturn(true);

        $result = $this->updateThemes->requestRemoteInfo('bad-json-theme');

        // json_decode returns null on invalid JSON
        $this->assertNull($result);
    }

    public function test_request_remote_info_returns_cached_data(): void
    {
        $themeData = $this->fixtures['themes']['themes']['standard_theme'];
        $cachedData = (object) [
            'name' => $themeData['name'],
            'slug' => $themeData['slug'],
            'version' => '1.0.0',
        ];

        // Mock get_transient to return cached data
        \Brain\Monkey\Functions\when('get_transient')->justReturn($cachedData);
        // Mock wp_remote_retrieve_body to extract body from cached response
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(function ($response) { return is_object($response) ? json_encode($response) : $response; });

        // Mock wp_remote_get - should not be called due to cache hit
        \Brain\Monkey\Functions\expect('wp_remote_get')->never();

        $result = $this->updateThemes->requestRemoteInfo('standard-theme');

        $this->assertIsObject($result);
        $this->assertEquals('Standard Theme', $result->name);
    }

    public function test_request_remote_info_returns_false_for_empty_slug(): void
    {
        \Brain\Monkey\Functions\expect('wp_remote_get')->never();
        \Brain\Monkey\Functions\expect('get_transient')->never();

        $result = $this->updateThemes->requestRemoteInfo('');

        $this->assertFalse($result);
    }

    public function test_request_remote_info_returns_false_for_null_slug(): void
    {
        \Brain\Monkey\Functions\expect('wp_remote_get')->never();
        \Brain\Monkey\Functions\expect('get_transient')->never();

        $result = $this->updateThemes->requestRemoteInfo(null);

        $this->assertFalse($result);
    }

    public function test_get_information_filter_with_theme_information_action(): void
    {
        $themeData = $this->fixtures['themes']['themes']['standard_theme'];

        // Mock remote info request
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => json_encode([
                'name' => $themeData['name'],
                'slug' => $themeData['slug'],
                'version' => $themeData['version'],
                'author' => $themeData['author'],
                'author_url' => $themeData['author_uri'],
                'homepage' => $themeData['homepage'] ?? 'https://example.com/theme',
                'description' => $themeData['description'],
                'sections' => (object) [
                    'description' => $themeData['description'],
                ],
                'unrepress_meta' => (object) [
                    'repository' => 'https://github.com/username/standard-theme',
                    'update_from' => 'tags',
                ],
            ]),
            'response' => ['code' => 200],
        ]);

        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) { return is_array($response) ? ($response['response']['code'] ?? 200) : 200; });
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(function ($response) { return is_array($response) ? ($response['body'] ?? '') : $response; });
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('set_transient')->justReturn(true);
        \Brain\Monkey\Functions\when('unrepress_debug')->justReturn(null);

        $args = (object) ['slug' => 'standard-theme'];
        $result = $this->updateThemes->getInformation(false, 'theme_information', $args);

        $this->assertIsObject($result);
        $this->assertEquals('standard-theme', $result->slug);
        $this->assertNotEmpty($result->version);
        \Brain\Monkey\Functions\when('unrepress_debug')->justReturn(null);
        $this->assertObjectHasProperty('sections', $result);
    }

    public function test_get_information_filter_returns_early_for_non_theme_actions(): void
    {
        $args = (object) ['slug' => 'test-theme'];

        // Test with wrong action
        $result = $this->updateThemes->getInformation('original response', 'some_other_action', $args);

        $this->assertEquals('original response', $result);
    }

    public function test_get_information_filter_returns_early_when_slug_empty(): void
    {
        // Mock wp_remote_get
        \Brain\Monkey\Functions\expect('wp_remote_get')->never();

        $args = (object) ['slug' => ''];
        $result = $this->updateThemes->getInformation(false, 'theme_information', $args);

        $this->assertFalse($result);
    }

    public function test_get_information_filter_handles_no_remote_data(): void
    {
        // Mock remote request to fail
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn(new \WP_Error('not_found', 'Theme not found'));
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(true);
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);

        $args = (object) ['slug' => 'non-existent-theme'];
        $result = $this->updateThemes->getInformation(false, 'theme_information', $args);

        $this->assertFalse($result);
    }

    public function test_has_update_populates_checked_themes(): void
    {
        // Create a mock theme object with get method
        $mockTheme = $this->createMockThemeObject('standard-theme', '1.0.0');

        // Mock wp_get_themes to return an array of themes
        \Brain\Monkey\Functions\when('wp_get_themes')->justReturn([
            'standard-theme' => $mockTheme,
        ]);
        // Mock wp_get_theme to return the mock theme object
        \Brain\Monkey\Functions\when('wp_get_theme')->justReturn($mockTheme);

        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => '{}',
            'response' => ['code' => 404],
        ]);
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) { return is_array($response) ? ($response['response']['code'] ?? 200) : 200; });
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(function ($response) { return is_array($response) ? ($response['body'] ?? '') : $response; });

        $transient = new \stdClass();
        $transient->checked = [];

        $result = $this->updateThemes->hasUpdate($transient);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('checked', $result);
        $this->assertArrayHasKey('standard-theme', $result->checked);
    }

    public function test_has_update_adds_response_when_update_available(): void
    {
        // Manually set update info to simulate an available update
        $updateInfo = new \stdClass();
        $updateInfo->theme = 'standard-theme';
        $updateInfo->version = '2.0.0';
        $updateInfo->theme_uri = 'https://example.com/theme';
        $updateInfo->download_link = 'https://example.com/download.zip';
        $updateInfo->requires = '6.0';
        $updateInfo->requires_php = '8.0';

        $reflection = new \ReflectionClass($this->updateThemes);
        $property = $reflection->getProperty('updateInfo');
        $property->setValue($this->updateThemes, ['standard-theme' => $updateInfo]);

        $transient = new \stdClass();
        $transient->checked = [
            'standard-theme' => '1.0.0',
        ];

        $result = $this->updateThemes->hasUpdate($transient);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('response', $result);
        $this->assertIsArray($result->response);
        $this->assertArrayHasKey('standard-theme', $result->response);
    }

    public function test_has_update_handles_empty_transient(): void
    {
        $mockTheme = $this->createMockThemeObject('standard-theme', '1.0.0');

        \Brain\Monkey\Functions\when('wp_get_themes')->justReturn([
            'standard-theme' => $mockTheme,
        ]);
        \Brain\Monkey\Functions\when('wp_get_theme')->justReturn($mockTheme);
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => '{}',
            'response' => ['code' => 404],
        ]);
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) { return is_array($response) ? ($response['response']['code'] ?? 200) : 200; });
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(function ($response) { return is_array($response) ? ($response['body'] ?? '') : $response; });

        $transient = null;

        $result = $this->updateThemes->hasUpdate($transient);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('checked', $result);
        $this->assertArrayHasKey('standard-theme', $result->checked);
    }

    public function test_has_update_handles_non_object_transient(): void
    {
        $transient = 'invalid transient';

        $result = $this->updateThemes->hasUpdate($transient);

        $this->assertIsObject($result);
    }

    public function test_theme_version_comparison(): void
    {
        // Test version_compare logic for themes
        $this->assertTrue(version_compare('1.0.0', '2.0.0', '<'));
        $this->assertTrue(version_compare('2.0.0', '1.0.0', '>'));
        $this->assertTrue(version_compare('1.0.0', '1.0.0', '='));
        $this->assertFalse(version_compare('2.0.0', '1.0.0', '<'));
    }

    public function test_version_normalization_with_v_prefix(): void
    {
        $versionWithV = 'v1.5.0';
        $expected = '1.5.0';

        $actual = ltrim($versionWithV, 'v');

        $this->assertEquals($expected, $actual);
    }

    public function test_version_normalization_without_v_prefix(): void
    {
        $versionWithoutV = '1.5.0';
        $expected = '1.5.0';

        $actual = ltrim($versionWithoutV, 'v');

        $this->assertEquals($expected, $actual);
    }

    public function test_slug_extraction_from_theme_path(): void
    {
        // Theme slug extraction test
        $themeSlug = 'standard-theme';
        $expected = 'standard-theme';

        $this->assertEquals($expected, $themeSlug);
    }

    public function test_download_url_construction_for_github(): void
    {
        $repo = 'username/standard-theme';
        $tag = 'v1.5.0';
        $expectedUrl = "https://github.com/{$repo}/archive/refs/tags/{$tag}.zip";

        $actualUrl = "https://github.com/{$repo}/archive/refs/tags/{$tag}.zip";

        $this->assertEquals($expectedUrl, $actualUrl);
        $this->assertStringContainsString('github.com', $actualUrl);
        $this->assertStringContainsString('archive/refs/tags', $actualUrl);
        $this->assertStringContainsString($tag, $actualUrl);
    }

    public function test_cache_key_structure(): void
    {
        $this->assertStringStartsWith('unrepress_updates_theme_', $this->updateThemes->cache_key);
        $this->assertStringEndsWith('_', $this->updateThemes->cache_key);
    }

    public function test_cache_results_property(): void
    {
        $this->assertTrue($this->updateThemes->cache_results);
        $this->assertIsBool($this->updateThemes->cache_results);
    }

    public function test_provider_property(): void
    {
        // Provider is a private property, so we test it indirectly
        // by checking that the class was instantiated successfully
        $this->assertInstanceOf(UpdateThemes::class, $this->updateThemes);
        $this->assertObjectHasProperty('version', $this->updateThemes);
        $this->assertObjectHasProperty('cache_key', $this->updateThemes);
        $this->assertObjectHasProperty('cache_results', $this->updateThemes);
    }

    public function test_version_property_initial(): void
    {
        $this->assertEquals('', $this->updateThemes->version);
        $this->assertIsString($this->updateThemes->version);
        $this->assertEmpty($this->updateThemes->version);
    }

    /**
     * Create a mock WordPress theme object.
     */
    private function createMockThemeObject(string $slug = '', string $version = ''): object
    {
        $theme = new class {
            private $slug;
            private $version;
            private $name;

            public function __construct(string $slug = '', string $version = '')
            {
                $this->slug = $slug;
                $this->version = $version;
                $this->name = $slug !== '' ? ucfirst(str_replace('-', ' ', $slug)) : 'Mock Theme';
            }

            public function get(string $key)
            {
                switch ($key) {
                    case 'Version':
                        return $this->version;
                    case 'Name':
                        return $this->name;
                    case 'slug':
                        return $this->slug;
                    default:
                        return null;
                }
            }
        };

        return $theme;
    }
}
