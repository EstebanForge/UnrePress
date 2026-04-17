<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Security;

use UnrePress\Security\InputValidator;
use UnrePress\Tests\Helpers\WordPressTestHelper;

/**
 * InputValidator Unit Tests
 *
 * Tests for input validation to prevent SQL injection, XSS attacks,
 * path traversal, and other security vulnerabilities.
 */
class InputValidatorTest extends WordPressTestHelper
{
    private InputValidator $validator;

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

        // Mock WordPress validation functions
        \Brain\Monkey\Functions\when('sanitize_key')->alias(function($input) {
            return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $input));
        });
        \Brain\Monkey\Functions\when('sanitize_text_field')->returnArg();
        \Brain\Monkey\Functions\when('wp_verify_nonce')->justReturn(false);

        $this->validator = new InputValidator();
    }

    public function test_validate_slug_accepts_valid_plugin_slugs(): void
    {
        $validSlugs = [
            'my-plugin',
            'my-plugin/my-plugin.php',
            'my-theme',
            'advanced-plugin-name',
        ];

        foreach ($validSlugs as $slug) {
            $result = $this->validator->validateSlug($slug);
            $this->assertIsString($result, "Valid slug rejected: {$slug}");
        }
    }

    public function test_validate_slug_rejects_directory_traversal(): void
    {
        $maliciousSlugs = [
            '../../../etc/passwd',
            '../../wp-config',
            '..\\..\\windows\\system32',
            'plugin/../../../etc/passwd',
            "test\x00injection",
        ];

        foreach ($maliciousSlugs as $slug) {
            $result = $this->validator->validateSlug($slug);
            $this->assertFalse($result, "Malicious slug accepted: {$slug}");
        }
    }

    public function test_validate_github_url_accepts_valid_urls(): void
    {
        $validUrls = [
            'https://github.com/username/repo',
            'https://api.github.com/repos/username/repo',
            'https://raw.githubusercontent.com/username/repo/main/file.json',
        ];

        foreach ($validUrls as $url) {
            $result = $this->validator->validateGitHubUrl($url);
            $this->assertTrue($result, "Valid GitHub URL rejected: {$url}");
        }
    }

    public function test_validate_github_url_rejects_non_github_urls(): void
    {
        $invalidUrls = [
            'https://example.com/repo',
            'https://github.com.evil.com/repo',
            'https://gitlab.com/repo',
            'not-a-url',
            'javascript:alert(1)',
        ];

        foreach ($invalidUrls as $url) {
            $result = $this->validator->validateGitHubUrl($url);
            $this->assertFalse($result, "Invalid GitHub URL accepted: {$url}");
        }
    }

    public function test_validate_update_type_accepts_valid_types(): void
    {
        $validTypes = ['core', 'plugin', 'theme', 'translation'];

        foreach ($validTypes as $type) {
            $result = $this->validator->validateUpdateType($type);
            $this->assertTrue($result, "Valid update type rejected: {$type}");
        }
    }

    public function test_validate_update_type_rejects_invalid_types(): void
    {
        $invalidTypes = [
            'malicious',
            '../../../etc/passwd',
            'core; DROP TABLE users;',
            'script',
            '../../config',
        ];

        foreach ($invalidTypes as $type) {
            $result = $this->validator->validateUpdateType($type);
            $this->assertFalse($result, "Invalid update type accepted: {$type}");
        }
    }

    public function test_sanitize_file_path_removes_directory_traversal(): void
    {
        $maliciousPaths = [
            '../../../etc/passwd',
            '..\\..\\windows\\system32',
            'plugin/../../secret.txt',
            "path\x00injection",
            'normal/path/../../../etc/passwd',
        ];

        foreach ($maliciousPaths as $path) {
            $result = $this->validator->sanitizeFilePath($path);
            $this->assertStringNotContainsString('..', $result, "Path traversal not removed: {$path}");
            $this->assertStringNotContainsString("\x00", $result, "Null byte not removed: {$path}");
        }
    }

    public function test_sanitize_file_path_normalizes_separators(): void
    {
        $paths = [
            'path\\to\\file',
            'path//to///file',
            'path\\\\to\\\\file',
        ];

        $expected = 'path/to/file';

        foreach ($paths as $path) {
            $result = $this->validator->sanitizeFilePath($path);
            $this->assertEquals($expected, $result, "Path not normalized: {$path}");
        }
    }

    public function test_validate_json_accepts_valid_json(): void
    {
        $validJson = [
            '{"test": "data"}',
            '[]',
            '{}',
            '["item1", "item2"]',
        ];

        foreach ($validJson as $json) {
            $result = $this->validator->validateJson($json);
            $this->assertTrue($result, "Valid JSON rejected: {$json}");
        }
    }

    public function test_validate_json_rejects_invalid_json(): void
    {
        $invalidJson = [
            'invalid json{',
            '{broken json',
            'test',
            '',
        ];

        foreach ($invalidJson as $json) {
            $result = $this->validator->validateJson($json);
            $this->assertFalse($result, "Invalid JSON accepted: {$json}");
        }
    }

    public function test_sanitize_update_data_sanitizes_strings(): void
    {
        $data = [
            'name' => '<script>alert("XSS")</script>',
            'version' => '1.0.0',
            'description' => 'Test plugin',
        ];

        \Brain\Monkey\Functions\when('sanitize_text_field')->alias(function($input) {
            return strip_tags($input);
        });

        $result = $this->validator->sanitizeUpdateData($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertStringNotContainsString('script', $result['name']);
        $this->assertStringNotContainsString('<', $result['name']);
        $this->assertStringNotContainsString('>', $result['name']);
    }

    public function test_sanitize_update_data_sanitizes_nested_arrays(): void
    {
        $data = [
            'plugin' => [
                'name' => '<script>alert("XSS")</script>',
                'version' => '1.0.0',
            ],
        ];

        \Brain\Monkey\Functions\when('sanitize_text_field')->alias(function($input) {
            return strip_tags($input);
        });

        $result = $this->validator->sanitizeUpdateData($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('plugin', $result);
        $this->assertArrayHasKey('name', $result['plugin']);
    }

    public function test_validate_version_accepts_valid_versions(): void
    {
        $validVersions = [
            '1.0.0',
            'v1.0.0',
            '2.5.3-beta',
            '1.0.0-alpha',
            '10.20.30',
        ];

        foreach ($validVersions as $version) {
            $result = $this->validator->validateVersion($version);
            $this->assertTrue($result, "Valid version rejected: {$version}");
        }
    }

    public function test_validate_version_rejects_invalid_versions(): void
    {
        $invalidVersions = [
            'not.a.version',
            '1.0',
            'v1.0',
            'malicious; DROP TABLE users;',
            '../../etc/passwd',
            '1.0.0-evil',
        ];

        foreach ($invalidVersions as $version) {
            $result = $this->validator->validateVersion($version);
            $this->assertFalse($result, "Invalid version accepted: {$version}");
        }
    }

    public function test_sanitize_user_agent_removes_control_characters(): void
    {
        $maliciousAgent = "Mozilla/5.0 \x00\x01\x02 Browser";

        \Brain\Monkey\Functions\when('sanitize_text_field')->returnArg();

        $result = $this->validator->sanitizeUserAgent($maliciousAgent);

        $this->assertStringNotContainsString("\x00", $result);
        $this->assertStringNotContainsString("\x01", $result);
    }

    public function test_sanitize_user_agent_limits_length(): void
    {
        $longAgent = str_repeat('A', 600);

        \Brain\Monkey\Functions\when('sanitize_text_field')->returnArg();

        $result = $this->validator->sanitizeUserAgent($longAgent);

        $this->assertLessThanOrEqual(500, strlen($result));
    }

    public function test_validate_file_extension_accepts_allowed_extensions(): void
    {
        $allowedFiles = [
            'plugin.zip',
            'theme.tar.gz',
            'data.json',
            'update.ZIP', // Case insensitive
        ];

        foreach ($allowedFiles as $file) {
            $result = $this->validator->validateFileExtension($file);
            $this->assertTrue($result, "Allowed extension rejected: {$file}");
        }
    }

    public function test_validate_file_extension_rejects_disallowed_extensions(): void
    {
        $disallowedFiles = [
            'plugin.exe',
            'theme.php',
            'data.xml',
            'malicious.sh',
            'script.js',
        ];

        foreach ($disallowedFiles as $file) {
            $result = $this->validator->validateFileExtension($file);
            $this->assertFalse($result, "Disallowed extension accepted: {$file}");
        }
    }

    public function test_detect_sql_injection_detects_common_patterns(): void
    {
        $sqlInjection = [
            "1' OR '1'='1",
            "1; DROP TABLE users;",
            "admin' --",
            "admin' OR '1'='1' #",
            " UNION SELECT * FROM",
            "' OR 1=1--",
        ];

        foreach ($sqlInjection as $input) {
            $result = $this->validator->detectSqlInjection($input);
            $this->assertTrue($result, "SQL injection not detected: {$input}");
        }
    }

    public function test_detect_sql_injection_allows_safe_input(): void
    {
        $safeInput = [
            'my-plugin-1.0.0',
            'username/repository',
            'valid_plugin_name',
            'Version 2.0',
        ];

        foreach ($safeInput as $input) {
            $result = $this->validator->detectSqlInjection($input);
            $this->assertFalse($result, "Safe input flagged as SQL injection: {$input}");
        }
    }

    public function test_detect_xss_detects_common_patterns(): void
    {
        $xssAttacks = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="evil.com"></iframe>',
            '<embed src="evil.swf">',
            '<object data="evil"></object>',
            '<style>alert("XSS")</style>',
        ];

        foreach ($xssAttacks as $input) {
            $result = $this->validator->detectXss($input);
            $this->assertTrue($result, "XSS attack not detected: {$input}");
        }
    }

    public function test_detect_xss_allows_safe_html(): void
    {
        $safeHtml = [
            '<strong>Bold text</strong>',
            '<p>Paragraph</p>',
            '<ul><li>List item</li></ul>',
            'Regular text without tags',
            '&lt;script&gt;escaped&lt;/script&gt;',
        ];

        foreach ($safeHtml as $input) {
            $result = $this->validator->detectXss($input);
            $this->assertFalse($result, "Safe HTML flagged as XSS: {$input}");
        }
    }

    public function test_validate_nonce_returns_false_for_invalid_nonce(): void
    {
        \Brain\Monkey\Functions\when('wp_verify_nonce')->justReturn(false);

        $result = $this->validator->validateNonce('invalid_nonce');

        $this->assertFalse($result);
    }

    public function test_validate_nonce_returns_true_for_valid_nonce(): void
    {
        \Brain\Monkey\Functions\when('wp_verify_nonce')->justReturn(true);

        $result = $this->validator->validateNonce('valid_nonce');

        $this->assertTrue($result);
    }

    public function test_validate_repository_accepts_valid_repositories(): void
    {
        $validRepos = [
            'username/repo',
            'username/plugin-name',
            'org-name/repository-name',
            'user123/plugin_v2',
        ];

        foreach ($validRepos as $repo) {
            $result = $this->validator->validateRepository($repo);
            $this->assertIsString($result, "Valid repository rejected: {$repo}");
        }
    }

    public function test_validate_repository_rejects_invalid_repositories(): void
    {
        $invalidRepos = [
            '../../../etc/passwd',
            'repo; DROP TABLE users;',
            'user|malicious',
            'repo <script>alert(1)</script>',
            '',
        ];

        foreach ($invalidRepos as $repo) {
            $result = $this->validator->validateRepository($repo);
            $this->assertFalse($result, "Invalid repository accepted: {$repo}");
        }
    }

    public function test_validate_repository_sanitizes_input(): void
    {
        $maliciousRepos = [
            'user/../../repo',
            'user;rm -rf /',
            'user|cat /etc/passwd',
        ];

        foreach ($maliciousRepos as $repo) {
            $result = $this->validator->validateRepository($repo);

            // All these malicious inputs should be rejected
            $this->assertFalse($result, "Malicious repository was not rejected: {$repo}");
        }
    }
}
