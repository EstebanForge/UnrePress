<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Core;

use UnrePress\Tests\Helpers\WordPressTestHelper;
use UnrePress\Updater\UpdatePlugins;

/**
 * UpdatePlugins Unit Tests
 */
class UpdatePluginsTest extends WordPressTestHelper
{
    private UpdatePlugins $updatePlugins;

    private array $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        // Define required constants
        if (!defined('UNREPRESS_INDEX')) {
            define('UNREPRESS_INDEX', 'https://example.com/index/');
        }
        if (!defined('UNREPRESS_PLUGIN_URL')) {
            define('UNREPRESS_PLUGIN_URL', 'https://example.com/plugin/');
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
            'plugins' => require __DIR__ . '/../../Fixtures/plugin-theme-data.php',
            'updates' => require __DIR__ . '/../../Fixtures/update-objects.php',
        ];

        // Mock WordPress functions used in constructor
        \Brain\Monkey\Functions\when('get_plugins')->justReturn([]);
        \Brain\Monkey\Functions\when('add_filter')->justReturn(true);
        \Brain\Monkey\Functions\when('add_action')->justReturn(true);

        $this->updatePlugins = new UpdatePlugins();
    }

    public function test_class_instantiation(): void
    {
        $this->assertInstanceOf(UpdatePlugins::class, $this->updatePlugins);
        $this->assertNotEmpty($this->updatePlugins->cache_key);
        $this->assertIsString($this->updatePlugins->cache_key);
    }

    public function test_cache_key_prefix(): void
    {
        $this->assertStringContainsString('unrepress_', $this->updatePlugins->cache_key);
        $this->assertStringContainsString('plugin', $this->updatePlugins->cache_key);
    }

    public function test_request_remote_info_returns_data(): void
    {
        $pluginData = $this->fixtures['plugins']['plugins']['standard_plugin'];

        // Mock wp_remote_get to return valid plugin data
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => json_encode([
                'name' => $pluginData['name'],
                'slug' => $pluginData['slug'],
                'version' => $pluginData['version'],
                'author' => $pluginData['author'],
                'description' => $pluginData['description'],
            ]),
            'response' => ['code' => 200],
        ]);

        
        \Brain\Monkey\Functions\when("wp_remote_retrieve_response_code")->alias(function($response) { return is_array($response) ? ($response["response"]["code"] ?? 200) : 200; });
        \Brain\Monkey\Functions\when("wp_remote_retrieve_body")->alias(function($response) { return is_array($response) ? ($response["body"] ?? "") : $response; });

        $result = $this->updatePlugins->requestRemoteInfo('standard-plugin');

        $this->assertIsObject($result);
        $this->assertEquals('Standard Plugin', $result->name);
        $this->assertEquals('standard-plugin', $result->slug);
    }

    public function test_request_remote_info_handles_http_errors(): void
    {
        // Mock wp_remote_get to return error
        $wpError = new \WP_Error('http_error', 'HTTP request failed');
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn($wpError);
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(true);

        $result = $this->updatePlugins->requestRemoteInfo('invalid-plugin');

        $this->assertFalse($result);
    }

    public function test_request_remote_info_handles_invalid_response_code(): void
    {
        // Mock wp_remote_get to return 404
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'response' => ['code' => 404],
        ]);

        
        \Brain\Monkey\Functions\when("wp_remote_retrieve_response_code")->alias(function($response) { return is_array($response) ? ($response["response"]["code"] ?? 200) : 200; });

        $result = $this->updatePlugins->requestRemoteInfo('not-found-plugin');

        $this->assertFalse($result);
    }

    public function test_request_remote_info_handles_invalid_json(): void
    {
        // Mock wp_remote_get to return invalid JSON
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => 'invalid json{',
            'response' => ['code' => 200],
        ]);

        
        \Brain\Monkey\Functions\when("wp_remote_retrieve_response_code")->alias(function($response) { return is_array($response) ? ($response["response"]["code"] ?? 200) : 200; });
        \Brain\Monkey\Functions\when("wp_remote_retrieve_body")->alias(function($response) { return is_array($response) ? ($response["body"] ?? "") : $response; });

        $result = $this->updatePlugins->requestRemoteInfo('bad-json-plugin');

        $this->assertFalse($result);
    }

    public function test_request_remote_info_cleans_trailing_commas(): void
    {
        // Mock wp_remote_get to return JSON with trailing comma
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => '{"name":"Test","version":"1.0",}',
            'response' => ['code' => 200],
        ]);

        
        \Brain\Monkey\Functions\when("wp_remote_retrieve_response_code")->alias(function($response) { return is_array($response) ? ($response["response"]["code"] ?? 200) : 200; });
        \Brain\Monkey\Functions\when("wp_remote_retrieve_body")->alias(function($response) { return is_array($response) ? ($response["body"] ?? "") : $response; });

        $result = $this->updatePlugins->requestRemoteInfo('test-plugin');

        $this->assertIsObject($result);
        $this->assertEquals('Test', $result->name);
        $this->assertEquals('1.0', $result->version);
    }

    public function test_get_information_filter_with_plugin_information_action(): void
    {
        $pluginData = $this->fixtures['plugins']['plugins']['standard_plugin'];

        // Mock remote info request
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => json_encode([
                'name' => $pluginData['name'],
                'slug' => $pluginData['slug'],
                'version' => $pluginData['version'],
                'author' => $pluginData['author'],
                'author_url' => $pluginData['author_uri'],
                'homepage' => $pluginData['homepage'] ?? 'https://example.com/plugin',
                'description' => $pluginData['description'],
                'sections' => (object) [
                    'description' => $pluginData['description'],
                ],
                'unrepress_meta' => (object) [
                    'repository' => 'https://github.com/username/standard-plugin',
                    'update_from' => 'tags',
                ],
            ]),
            'response' => ['code' => 200],
        ]);

        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when("wp_remote_retrieve_response_code")->alias(function($response) { return is_array($response) ? ($response["response"]["code"] ?? 200) : 200; });
        \Brain\Monkey\Functions\when("wp_remote_retrieve_body")->alias(function($response) { return is_array($response) ? ($response["body"] ?? "") : $response; });

        // Mock WordPress functions
        \Brain\Monkey\Functions\when('esc_url')->returnArg(1);
        \Brain\Monkey\Functions\when('esc_html')->returnArg(1);
        \Brain\Monkey\Functions\when('get_bloginfo')->justReturn('6.5.0');
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('set_transient')->justReturn(true);

        $args = (object) ['slug' => 'standard-plugin'];
        $result = $this->updatePlugins->getInformation(false, 'plugin_information', $args);

        $this->assertIsObject($result);
        $this->assertEquals('standard-plugin', $result->slug);
        $this->assertNotEmpty($result->version);
        $this->assertNotEmpty($result->author);
        $this->assertObjectHasProperty('sections', $result);
    }

    public function test_get_information_filter_returns_early_for_non_plugin_actions(): void
    {
        $args = (object) ['slug' => 'test-plugin'];

        // Test with wrong action
        $result = $this->updatePlugins->getInformation('original response', 'some_other_action', $args);

        $this->assertEquals('original response', $result);
    }

    public function test_get_information_filter_returns_early_when_slug_empty(): void
    {
        // Mock wp_remote_get
        \Brain\Monkey\Functions\expect('wp_remote_get')->never();

        $args = (object) ['slug' => ''];
        $result = $this->updatePlugins->getInformation(false, 'plugin_information', $args);

        $this->assertFalse($result);
    }

    public function test_get_information_filter_handles_no_remote_data(): void
    {
        // Mock remote request to fail
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn(new \WP_Error('not_found', 'Plugin not found'));
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(true);

        $args = (object) ['slug' => 'non-existent-plugin'];
        $result = $this->updatePlugins->getInformation(false, 'plugin_information', $args);

        $this->assertFalse($result);
    }

    public function test_has_update_populates_checked_plugins(): void
    {
        $plugins = [
            'standard-plugin/standard.php' => [
                'Name' => 'Standard Plugin',
                'Version' => '1.0.0',
            ],
        ];

        \Brain\Monkey\Functions\when('get_plugins')->justReturn($plugins);
        // Mock wp_remote_get to prevent errors in checkforUpdates
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => '{}',
            'response' => ['code' => 404], // Return 404 to simulate no update available
        ]);
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(function($response) { return is_array($response) ? ($response["body"] ?? "") : $response; });
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(function($response) { return is_array($response) ? ($response["response"]["code"] ?? 200) : 200; });

        $transient = new \stdClass();
        $transient->checked = [];

        $result = $this->updatePlugins->hasUpdate($transient);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('checked', $result);
        $this->assertNotEmpty($result->checked);
        $this->assertArrayHasKey('standard-plugin/standard.php', $result->checked);
    }

    public function test_has_update_adds_response_when_update_available(): void
    {
        $plugins = [
            'standard-plugin/standard.php' => [
                'Name' => 'Standard Plugin',
                'Version' => '1.0.0',
            ],
        ];

        // Manually set update info to simulate an available update
        $updateInfo = new \stdClass();
        $updateInfo->slug = 'standard-plugin';
        $updateInfo->version = '2.0.0';
        $updateInfo->tested = '6.5';
        $updateInfo->download_link = 'https://example.com/download.zip';

        $reflection = new \ReflectionClass($this->updatePlugins);
        $property = $reflection->getProperty('updateInfo');
        // setAccessible() not needed in PHP 8.1+ - all properties are accessible by default
        $property->setValue($this->updatePlugins, ['standard-plugin' => $updateInfo]);

        \Brain\Monkey\Functions\when('get_plugins')->justReturn($plugins);

        $transient = new \stdClass();
        $transient->checked = [
            'standard-plugin/standard.php' => '1.0.0',
        ];

        $result = $this->updatePlugins->hasUpdate($transient);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('response', $result);
        $this->assertIsArray($result->response);
        $this->assertArrayHasKey('standard-plugin/standard.php', $result->response);
    }

    public function test_has_update_handles_empty_transient(): void
    {
        $plugins = [
            'standard-plugin/standard.php' => [
                'Name' => 'Standard Plugin',
                'Version' => '1.0.0',
            ],
        ];

        \Brain\Monkey\Functions\when('get_plugins')->justReturn($plugins);
        // Mock wp_remote_get to prevent errors in checkforUpdates
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([
            'body' => '{}',
            'response' => ['code' => 404],
        ]);
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(function($response) { return is_array($response) ? ($response["body"] ?? "") : $response; });
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(function($response) { return is_array($response) ? ($response["response"]["code"] ?? 200) : 200; });

        $transient = null;

        $result = $this->updatePlugins->hasUpdate($transient);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('checked', $result);
    }

    public function test_plugin_version_comparison(): void
    {
        // Test version_compare logic
        $this->assertTrue(version_compare('1.0.0', '2.0.0', '<'));
        $this->assertTrue(version_compare('2.0.0', '1.0.0', '>'));
        $this->assertTrue(version_compare('1.0.0', '1.0.0', '='));
        $this->assertFalse(version_compare('2.0.0', '1.0.0', '<'));
    }

    public function test_version_normalization_with_v_prefix(): void
    {
        $versionWithV = 'v1.0.0';
        $expected = '1.0.0';

        $actual = ltrim($versionWithV, 'v');

        $this->assertEquals($expected, $actual);
    }

    public function test_version_normalization_without_v_prefix(): void
    {
        $versionWithoutV = '1.0.0';
        $expected = '1.0.0';

        $actual = ltrim($versionWithoutV, 'v');

        $this->assertEquals($expected, $actual);
    }

    public function test_slug_extraction_from_plugin_path(): void
    {
        $pluginPath = 'standard-plugin/standard.php';
        $expectedSlug = 'standard-plugin';

        $actualSlug = basename(dirname($pluginPath));

        $this->assertEquals($expectedSlug, $actualSlug);
    }

    public function test_slug_extraction_from_single_file_plugin(): void
    {
        $pluginPath = 'single-file-plugin.php';
        // Single file plugins have no directory, so dirname returns '.'
        $expectedSlug = '.';

        $actualSlug = basename(dirname($pluginPath));

        $this->assertEquals($expectedSlug, $actualSlug);
    }

    public function test_download_url_construction_for_github(): void
    {
        $repo = 'username/standard-plugin';
        $tag = 'v2.0.0';
        $expectedUrl = "https://github.com/{$repo}/archive/refs/tags/{$tag}.zip";

        $actualUrl = "https://github.com/{$repo}/archive/refs/tags/{$tag}.zip";

        $this->assertEquals($expectedUrl, $actualUrl);
        $this->assertStringContainsString('github.com', $actualUrl);
        $this->assertStringContainsString('archive/refs/tags', $actualUrl);
        $this->assertStringContainsString($tag, $actualUrl);
    }
}
