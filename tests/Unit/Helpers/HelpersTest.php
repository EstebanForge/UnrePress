<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Helpers;

use UnrePress\Helpers;
use UnrePress\Tests\Helpers\WordPressTestHelper;

/**
 * Helpers Unit Tests.
 */
class HelpersTest extends WordPressTestHelper
{
    private Helpers $helpers;

    protected function setUp(): void
    {
        parent::setUp();

        // Define required constants
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }
        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', '/tmp/wordpress/wp-content');
        }
        if (!defined('UNREPRESS_TEMP_PATH')) {
            define('UNREPRESS_TEMP_PATH', '/tmp/unrepress-temp/');
        }
        if (!defined('FS_CHMOD_FILE')) {
            define('FS_CHMOD_FILE', 0o644);
        }
        if (!defined('UNREPRESS_PREFIX')) {
            define('UNREPRESS_PREFIX', 'unrepress_');
        }

        // Mock WP_Filesystem function
        \Brain\Monkey\Functions\when('WP_Filesystem')->justReturn(true);

        // Mock is_wp_error
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);

        // Mock unrepress_debug
        \Brain\Monkey\Functions\when('unrepress_debug')->justReturn(null);

        $this->helpers = new Helpers();
    }

    public function test_normalize_tag_url_with_full_github_api_url(): void
    {
        $fullApiUrl = 'https://api.github.com/repos/username/repo/tags';

        $result = $this->helpers->normalizeTagUrl($fullApiUrl);

        $this->assertEquals($fullApiUrl, $result);
        $this->assertStringEndsWith('tags', $result);
    }

    public function test_normalize_tag_url_converts_browser_tags_url(): void
    {
        $browserUrl = 'https://github.com/username/repo/tags';
        $expectedApiUrl = 'https://api.github.com/repos/username/repo/tags';

        $result = $this->helpers->normalizeTagUrl($browserUrl);

        $this->assertEquals($expectedApiUrl, $result);
    }

    public function test_normalize_tag_url_converts_base_repo_url(): void
    {
        $baseUrl = 'https://github.com/username/repo';
        $expectedApiUrl = 'https://api.github.com/repos/username/repo/tags';

        $result = $this->helpers->normalizeTagUrl($baseUrl);

        $this->assertEquals($expectedApiUrl, $result);
    }

    public function test_normalize_tag_url_with_base_repo_and_tags_segment(): void
    {
        $baseUrl = 'https://github.com/username/repo';
        $segment = '/tags';

        $result = $this->helpers->normalizeTagUrl($baseUrl, $segment);

        $this->assertStringEndsWith('tags', $result);
        $this->assertStringContainsString('api.github.com', $result);
    }

    public function test_normalize_tag_url_preserves_non_github_urls(): void
    {
        $customUrl = 'https://custom-git.com/api/projects/1/tags';

        $result = $this->helpers->normalizeTagUrl($customUrl);

        $this->assertEquals($customUrl, $result);
    }

    public function test_get_newest_version_from_tags_with_v_prefixed_versions(): void
    {
        $tags = [
            (object) ['name' => 'v1.0.0'],
            (object) ['name' => 'v1.5.0'],
            (object) ['name' => 'v1.2.0'],
        ];

        $result = $this->helpers->getNewestVersionFromTags($tags);

        $this->assertIsObject($result);
        $this->assertEquals('v1.5.0', $result->name);
    }

    public function test_get_newest_version_from_tags_with_numeric_versions(): void
    {
        $tags = [
            (object) ['name' => '1.0.0'],
            (object) ['name' => '1.5.0'],
            (object) ['name' => '1.2.0'],
        ];

        $result = $this->helpers->getNewestVersionFromTags($tags);

        $this->assertIsObject($result);
        $this->assertEquals('1.5.0', $result->name);
    }

    public function test_get_newest_version_from_tags_prioritizes_v_prefixed(): void
    {
        $tags = [
            (object) ['name' => '2.0.0'],
            (object) ['name' => 'v1.5.0'],
            (object) ['name' => '1.9.0'],
        ];

        $result = $this->helpers->getNewestVersionFromTags($tags);

        $this->assertIsObject($result);
        $this->assertEquals('v1.5.0', $result->name);
    }

    public function test_get_newest_version_from_tags_returns_null_for_empty_array(): void
    {
        $result = $this->helpers->getNewestVersionFromTags([]);

        $this->assertNull($result);
    }

    public function test_get_newest_version_from_tags_returns_null_for_invalid_input(): void
    {
        $result = $this->helpers->getNewestVersionFromTags(null);

        $this->assertNull($result);
    }

    public function test_get_newest_version_from_tags_handles_mixed_tags(): void
    {
        $tags = [
            (object) ['name' => 'v1.0.0'],
            (object) ['name' => 'beta'],
            (object) ['name' => '1.2.0'],
        ];

        $result = $this->helpers->getNewestVersionFromTags($tags);

        $this->assertIsObject($result);
        $this->assertEquals('v1.0.0', $result->name);
    }

    public function test_get_download_url_for_provider_tag_github(): void
    {
        $repoUrl = 'https://github.com/username/repo';
        $tagName = 'v1.5.0';
        $slug = 'repo';
        $expectedUrl = 'https://github.com/username/repo/archive/refs/tags/v1.5.0.zip';

        $result = $this->helpers->getDownloadUrlForProviderTag($repoUrl, $tagName, $slug, 'github');

        $this->assertEquals($expectedUrl, $result);
    }

    public function test_get_download_url_for_provider_tag_without_v_prefix(): void
    {
        $repoUrl = 'https://github.com/username/repo';
        $tagName = '1.5.0';
        $slug = 'repo';
        $expectedUrl = 'https://github.com/username/repo/archive/refs/tags/1.5.0.zip';

        $result = $this->helpers->getDownloadUrlForProviderTag($repoUrl, $tagName, $slug, 'github');

        $this->assertEquals($expectedUrl, $result);
    }

    public function test_get_download_url_for_provider_tag_returns_false_for_missing_repo(): void
    {
        $result = $this->helpers->getDownloadUrlForProviderTag('', 'v1.0.0', 'slug', 'github');

        $this->assertFalse($result);
    }

    public function test_get_download_url_for_provider_tag_returns_false_for_missing_tag(): void
    {
        $result = $this->helpers->getDownloadUrlForProviderTag('https://github.com/user/repo', '', 'slug', 'github');

        $this->assertFalse($result);
    }

    public function test_get_download_url_for_provider_tag_returns_false_for_non_github(): void
    {
        $result = $this->helpers->getDownloadUrlForProviderTag('https://gitlab.com/user/repo', 'v1.0.0', 'slug', 'gitlab');

        $this->assertFalse($result);
    }
}
