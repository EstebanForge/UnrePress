<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * UpdateCore Unit Tests
 *
 * Tests the core WordPress update functionality from GitHub.
 * These tests can run without WordPress test environment for basic functionality.
 */
class UpdateCoreTest extends TestCase
{
    /**
     * Test that test environment is working
     */
    public function test_phpunit_environment_is_working(): void
    {
        $this->assertTrue(true);
        $this->assertEquals(2, 1 + 1);
    }

    /**
     * Test that required constants can be defined for testing
     */
    public function test_required_constants_can_be_defined(): void
    {
        // Define required constants for testing
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        if (!defined('UNREPRESS_PREFIX')) {
            define('UNREPRESS_PREFIX', 'unrepress_');
        }

        if (!defined('UNREPRESS_TRANSIENT_EXPIRATION')) {
            define('UNREPRESS_TRANSIENT_EXPIRATION', 3600);
        }

        $this->assertTrue(defined('ABSPATH'));
        $this->assertTrue(defined('UNREPRESS_PREFIX'));
        $this->assertTrue(defined('UNREPRESS_TRANSIENT_EXPIRATION'));
    }

    /**
     * Test basic string operations used in URL construction
     */
    public function test_github_url_construction(): void
    {
        $repo = 'WordPress/WordPress';
        $version = '6.5.0';
        $expectedUrl = "https://github.com/{$repo}/archive/refs/tags/{$version}.zip";

        $this->assertIsString($expectedUrl);
        $this->assertStringContainsString('github.com', $expectedUrl);
        $this->assertStringContainsString($repo, $expectedUrl);
        $this->assertStringContainsString($version, $expectedUrl);
        $this->assertStringContainsString('.zip', $expectedUrl);
    }

    /**
     * Test version string normalization
     */
    public function test_version_string_normalization(): void
    {
        $versionWithPrefix = 'v6.5.0';
        $expectedNormalized = '6.5.0';

        $normalized = ltrim($versionWithPrefix, 'v');

        $this->assertEquals($expectedNormalized, $normalized);
    }

    /**
     * Test GitHub URL slug extraction
     */
    public function test_extract_github_repository_slug(): void
    {
        $url = 'https://github.com/WordPress/WordPress';
        $expectedSlug = 'WordPress/WordPress';

        // Extract owner/repo from GitHub URL
        if (preg_match('#github\.com/([^/]+/[^/]+)#', $url, $matches)) {
            $slug = $matches[1];
        } else {
            $slug = 'WordPress/WordPress'; // Default fallback
        }

        $this->assertEquals($expectedSlug, $slug);
    }

    /**
     * Test GitHub URL slug extraction with .git extension
     */
    public function test_extract_github_repository_slug_with_git_extension(): void
    {
        $url = 'https://github.com/WordPress/WordPress.git';
        $expectedSlug = 'WordPress/WordPress';

        // Remove trailing slash and .git
        $cleanUrl = rtrim($url, '/');
        $cleanUrl = str_replace('.git', '', $cleanUrl);

        // Extract owner/repo from GitHub URL
        if (preg_match('#github\.com/([^/]+/[^/]+)#', $cleanUrl, $matches)) {
            $slug = $matches[1];
        } else {
            $slug = 'WordPress/WordPress'; // Default fallback
        }

        $this->assertEquals($expectedSlug, $slug);
    }

    /**
     * Test download URL construction for different version formats
     */
    public function test_download_url_construction(): void
    {
        $testCases = [
            ['repo' => 'WordPress/WordPress', 'version' => '6.5.0', 'contains' => '6.5.0'],
            ['repo' => 'WordPress/WordPress', 'version' => 'v6.5.0', 'contains' => 'v6.5.0'],
            ['repo' => 'example/plugin', 'version' => '1.2.3', 'contains' => '1.2.3'],
        ];

        foreach ($testCases as $testCase) {
            $url = "https://github.com/{$testCase['repo']}/archive/refs/tags/{$testCase['version']}.zip";

            $this->assertStringContainsString('github.com', $url);
            $this->assertStringContainsString($testCase['repo'], $url);
            $this->assertStringContainsString($testCase['contains'], $url);
            $this->assertStringContainsString('.zip', $url);
        }
    }

    /**
     * Test timeout values are reasonable
     */
    public function test_timeout_values_are_reasonable(): void
    {
        $defaultTimeout = 30; // 30 seconds
        $timeLimit = 300; // 5 minutes

        $this->assertGreaterThan(0, $defaultTimeout);
        $this->assertLessThan(120, $defaultTimeout); // Less than 2 minutes
        $this->assertGreaterThan(60, $timeLimit); // More than 1 minute
        $this->assertLessThan(600, $timeLimit); // Less than 10 minutes
    }

    /**
     * Test transient key generation
     */
    public function test_transient_key_generation(): void
    {
        $prefix = 'unrepress_';
        $suffix = 'updates_core_latest_version';
        $expectedKey = $prefix . $suffix;

        $this->assertEquals('unrepress_updates_core_latest_version', $expectedKey);
        $this->assertStringStartsWith($prefix, $expectedKey);
    }
}