<?php

declare(strict_types=1);

namespace UnrePress\Tests\Helpers;

use PHPUnit\Framework\TestCase;

/**
 * WordPress Test Helper.
 *
 * Provides helper methods for testing WordPress-related functionality
 * using BrainMonkey for mocking WordPress functions.
 */
abstract class WordPressTestHelper extends TestCase
{
    /**
     * Set up BrainMonkey before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    /**
     * Tear down BrainMonkey after each test.
     */
    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Mock WordPress function with expected parameters and return value.
     */
    protected function mockWpFunction(string $function, $returnValue = null, array $expectedArgs = []): void
    {
        \Brain\Monkey\Functions\when($function)->alias($function, $expectedArgs)->justReturn($returnValue);
    }

    /**
     * Mock WordPress transient functions.
     */
    protected function mockTransients(): void
    {
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('set_transient')->justReturn(true);
        \Brain\Monkey\Functions\when('delete_transient')->justReturn(true);
    }

    /**
     * Mock WordPress HTTP functions.
     */
    protected function mockHttpFunctions(array $responseData = []): void
    {
        $defaultResponse = [
            'body' => '{"test": "data"}',
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => [],
        ];

        $response = array_merge($defaultResponse, $responseData);

        \Brain\Monkey\Functions\when('wp_remote_get')->alias('wp_remote_get', [$response['url'] ?? 'https://api.github.com/'])
            ->justReturn($response);

        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->justReturn($response['body']);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->justReturn($response['response']['code']);
        \Brain\Monkey\Functions\expect('is_wp_error')->never(); // No errors by default
    }

    /**
     * Mock WordPress filesystem functions.
     */
    protected function mockFilesystem(): void
    {
        \Brain\Monkey\Functions\when('WP_Filesystem')->justReturn(true);
        \Brain\Monkey\Functions\expect('copy_dir')->never(); // Don't actually copy files
        \Brain\Monkey\Functions\expect('unzip_file')->never(); // Don't actually unzip files
    }

    /**
     * Mock WordPress plugin functions.
     */
    protected function mockPluginFunctions(array $plugins = []): void
    {
        $defaultPlugins = [
            'hello-dolly/hello.php' => [
                'Name' => 'Hello Dolly',
                'Version' => '1.0.0',
                'PluginURI' => 'https://wordpress.org/plugins/hello-dolly/',
            ],
        ];

        \Brain\Monkey\Functions\when('get_plugins')->justReturn(array_merge($defaultPlugins, $plugins));
        \Brain\Monkey\Functions\when('plugin_basename')->returnArg(0);
    }

    /**
     * Mock WordPress update functions.
     */
    protected function mockUpdateFunctions(): void
    {
        \Brain\Monkey\Functions\when('get_core_updates')->justReturn([]);
        \Brain\Monkey\Functions\when('get_site_transient')->justReturn((object) [
            'updates' => [],
            'version_checked' => '6.5.0',
        ]);
    }

    /**
     * Create a mock WordPress post object.
     */
    protected function createMockPost(array $data = []): object
    {
        $defaults = [
            'ID' => 1,
            'post_title' => 'Test Post',
            'post_content' => 'Test content',
            'post_status' => 'publish',
            'post_type' => 'post',
        ];

        return (object) array_merge($defaults, $data);
    }

    /**
     * Create a mock WordPress term object.
     */
    protected function createMockTerm(array $data = []): object
    {
        $defaults = [
            'term_id' => 1,
            'name' => 'Test Term',
            'slug' => 'test-term',
            'taxonomy' => 'category',
        ];

        return (object) array_merge($defaults, $data);
    }

    /**
     * Assert that a WordPress function was called with expected arguments.
     */
    protected function assertWpFunctionCalled(string $function, array $expectedArgs = []): void
    {
        \Brain\Monkey\Functions\expect($function)->toHaveBeenCalledWith(...$expectedArgs);
    }

    /**
     * Assert that a WordPress function was never called.
     */
    protected function assertWpFunctionNotCalled(string $function): void
    {
        \Brain\Monkey\Functions\expect($function)->never();
    }

    /**
     * Get the current WordPress version for testing.
     */
    protected function getWpVersion(): string
    {
        return '6.5.0'; // Default test version
    }

    /**
     * Create a mock plugin update object.
     */
    protected function createMockPluginUpdate(string $pluginSlug, string $newVersion): object
    {
        return (object) [
            'slug' => $pluginSlug,
            'new_version' => $newVersion,
            'old_version' => '1.0.0',
            'package' => "https://github.com/{$pluginSlug}/archive/refs/tags/{$newVersion}.zip",
        ];
    }

    /**
     * Create a mock theme update object.
     */
    protected function createMockThemeUpdate(string $themeSlug, string $newVersion): object
    {
        return (object) [
            'theme' => $themeSlug,
            'new_version' => $newVersion,
            'old_version' => '1.0.0',
            'package' => "https://github.com/{$themeSlug}/archive/refs/tags/{$newVersion}.zip",
        ];
    }
}
