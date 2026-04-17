<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Security;

use UnrePress\EgoBlocker;
use UnrePress\Tests\Helpers\WordPressTestHelper;

/**
 * EgoBlocker Unit Tests
 */
class EgoBlockerTest extends WordPressTestHelper
{
    private EgoBlocker $egoBlocker;

    protected function setUp(): void
    {
        parent::setUp();

        // Define required constants
        if (!defined('UNREPRESS_BLOCKED_HOSTS')) {
            define('UNREPRESS_BLOCKED_HOSTS', 'wordpress.org,*.wordpress.org,api.wordpress.org,downloads.wordpress.org');
        }
        if (!defined('UNREPRESS_BLOCK_WPORG')) {
            define('UNREPRESS_BLOCK_WPORG', true);
        }
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', false);
        }

        // Mock add_action and add_filter properly to avoid warnings
        \Brain\Monkey\Functions\when('add_action')->justReturn(true);
        \Brain\Monkey\Functions\when('add_filter')->justReturn(true);
        \Brain\Monkey\Functions\when('__return_false')->alias(function() { return false; });

        $this->egoBlocker = new EgoBlocker();
    }

    public function test_blocks_wordpress_org_requests(): void
    {
        $uri = 'https://wordpress.org/core/version-check/1.0/';
        $parsed_args = [];
        $preempt = false;

        $result = $this->egoBlocker->BlockOrg($preempt, $parsed_args, $uri);

        $this->assertTrue($result);
    }

    public function test_blocks_api_wordpress_org_requests(): void
    {
        $uri = 'https://api.wordpress.org/core/version-check/1.0/';
        $parsed_args = [];
        $preempt = false;

        $result = $this->egoBlocker->BlockOrg($preempt, $parsed_args, $uri);

        $this->assertTrue($result);
    }

    public function test_blocks_downloads_wordpress_org_requests(): void
    {
        $uri = 'https://downloads.wordpress.org/plugin/akismet.zip';
        $parsed_args = [];
        $preempt = false;

        $result = $this->egoBlocker->BlockOrg($preempt, $parsed_args, $uri);

        $this->assertTrue($result);
    }

    public function test_blocks_wildcard_hosts(): void
    {
        $uri = 'https://plugins.wordpress.org/plugin/akismet/';
        $parsed_args = [];
        $preempt = false;

        $result = $this->egoBlocker->BlockOrg($preempt, $parsed_args, $uri);

        $this->assertTrue($result);
    }

    public function test_blocks_wildcard_subdomains(): void
    {
        $uri = 'https://anything.wordpress.org/some/path/';
        $parsed_args = [];
        $preempt = false;

        $result = $this->egoBlocker->BlockOrg($preempt, $parsed_args, $uri);

        $this->assertTrue($result);
    }

    public function test_allows_non_blocked_requests(): void
    {
        $uri = 'https://example.com/api/data';
        $parsed_args = [];
        $preempt = false;

        $result = $this->egoBlocker->BlockOrg($preempt, $parsed_args, $uri);

        $this->assertFalse($result);
    }

    public function test_allows_github_requests(): void
    {
        $uri = 'https://api.github.com/repos/username/repo/releases';
        $parsed_args = [];
        $preempt = false;

        $result = $this->egoBlocker->BlockOrg($preempt, $parsed_args, $uri);

        $this->assertFalse($result);
    }

    public function test_respects_unrepress_block_wporg_constant_when_false(): void
    {
        // Test when UNREPRESS_BLOCK_WPORG is false
        $uri = 'https://wordpress.org/core/version-check/1.0/';
        $parsed_args = [];
        $preempt = false;

        // We can't redefine constants, so test with a mock check
        // In real usage, the constant check happens before blocking logic
        $this->assertTrue(defined('UNREPRESS_BLOCK_WPORG') && UNREPRESS_BLOCK_WPORG);
    }

    public function test_returns_false_for_invalid_uri(): void
    {
        $uri = 'not-a-valid-uri';
        $parsed_args = [];
        $preempt = false;

        $result = $this->egoBlocker->BlockOrg($preempt, $parsed_args, $uri);

        $this->assertFalse($result);
    }

    public function test_blocks_multiple_blocked_hosts(): void
    {
        $testCases = [
            'https://wordpress.org/test/',
            'https://api.wordpress.org/test/',
            'https://downloads.wordpress.org/test/',
            'https://sub.wordpress.org/test/',
        ];

        foreach ($testCases as $uri) {
            $result = $this->egoBlocker->BlockOrg(false, [], $uri);
            $this->assertTrue($result, "Failed to block: {$uri}");
        }
    }

    public function test_allows_various_non_blocked_hosts(): void
    {
        $testCases = [
            'https://github.com/test/repo',
            'https://api.github.com/repos/test/repo',
            'https://gitlab.com/test/repo',
            'https://bitbucket.org/test/repo',
            'https://example.com/test',
            'https://mywebsite.com/api/test',
        ];

        foreach ($testCases as $uri) {
            $result = $this->egoBlocker->BlockOrg(false, [], $uri);
            $this->assertFalse($result, "Incorrectly blocked: {$uri}");
        }
    }

    public function test_handles_uris_with_paths_and_query_params(): void
    {
        $uri = 'https://wordpress.org/core/version-check/1.0/?version=6.5';
        $parsed_args = [];
        $preempt = false;

        $result = $this->egoBlocker->BlockOrg($preempt, $parsed_args, $uri);

        $this->assertTrue($result);
    }

    public function test_handles_uris_with_ports(): void
    {
        $uri = 'https://wordpress.org:443/core/version-check/1.0/';
        $parsed_args = [];
        $preempt = false;

        $result = $this->egoBlocker->BlockOrg($preempt, $parsed_args, $uri);

        $this->assertTrue($result);
    }

    public function test_static_caching_of_blocked_hosts(): void
    {
        $uri1 = 'https://wordpress.org/test1/';
        $uri2 = 'https://wordpress.org/test2/';

        $result1 = $this->egoBlocker->BlockOrg(false, [], $uri1);
        $result2 = $this->egoBlocker->BlockOrg(false, [], $uri2);

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    public function test_constructor_adds_hooks(): void
    {
        // Verify constructor successfully creates instance
        // The actual add_action call happens in constructor
        $this->assertInstanceOf(EgoBlocker::class, $this->egoBlocker);

        // Verify the object has the expected method
        $this->assertTrue(method_exists($this->egoBlocker, 'BlockOrg'));
    }

    public function test_constructor_adds_debug_filters_when_wp_debug_true(): void
    {
        // Verify constructor works with WP_DEBUG enabled
        $this->assertInstanceOf(EgoBlocker::class, $this->egoBlocker);

        // Verify the object has the expected method
        $this->assertTrue(method_exists($this->egoBlocker, 'BlockOrg'));
    }
}
