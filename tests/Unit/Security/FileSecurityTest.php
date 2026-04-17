<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Security;

use UnrePress\Security\SecureFileOperations;
use UnrePress\Tests\Helpers\WordPressTestHelper;

/**
 * SecureFileOperations Unit Tests
 *
 * Tests for secure file system operations to prevent path traversal,
 * unauthorized file access, and directory escape attempts.
 */
class FileSecurityTest extends WordPressTestHelper
{
    private SecureFileOperations $fileOps;

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

        $this->fileOps = new SecureFileOperations('/tmp/wordpress/');
    }

    public function test_validate_path_rejects_directory_traversal(): void
    {
        $maliciousPaths = [
            '../../../etc/passwd',
            '..\\..\\windows\\system32',
            '/etc/passwd',
            'C:\\Windows\\System32\\config',
            '....//....//etc/passwd',
            '..../..../etc/passwd',
        ];

        foreach ($maliciousPaths as $path) {
            $result = $this->fileOps->validatePath($path);
            $this->assertFalse($result, "Malicious path accepted: {$path}");
        }
    }

    public function test_validate_path_rejects_absolute_paths_outside_root(): void
    {
        $outsidePaths = [
            '/var/www/html',
            '/home/user/file.txt',
            'C:\\Users\\test\\file.txt',
            '/tmp/secret',
        ];

        foreach ($outsidePaths as $path) {
            $result = $this->fileOps->validatePath($path);
            $this->assertFalse($result, "Outside path accepted: {$path}");
        }
    }

    public function test_validate_path_accepts_safe_relative_paths(): void
    {
        $safePaths = [
            'plugins/my-plugin/file.php',
            'themes/my-theme/style.css',
            'uploads/2024/01/image.jpg',
            'cache/data.json',
        ];

        foreach ($safePaths as $path) {
            $result = $this->fileOps->validatePath($path);
            $this->assertTrue($result, "Safe path rejected: {$path}");
        }
    }

    public function test_validate_path_accepts_absolute_paths_within_root(): void
    {
        $safeAbsolutePaths = [
            '/tmp/wordpress/plugins/my-plugin/file.php',
            '/tmp/wordpress/themes/my-theme/style.css',
            '/tmp/wordpress/uploads/image.jpg',
        ];

        foreach ($safeAbsolutePaths as $path) {
            $result = $this->fileOps->validatePath($path);
            $this->assertTrue($result, "Safe absolute path rejected: {$path}");
        }
    }

    public function test_sanitize_path_removes_dangerous_patterns(): void
    {
        $maliciousPaths = [
            '../../../etc/passwd',
            'path/../../secret',
            '..\\..\\windows\\system32',
            'normal/../../../etc/passwd',
        ];

        foreach ($maliciousPaths as $path) {
            $result = $this->fileOps->sanitizePath($path);
            $this->assertStringNotContainsString('..', $result, "Path traversal not removed: {$path}");
            $this->assertStringNotContainsString("\0", $result, "Null byte not removed: {$path}");
        }
    }

    public function test_sanitize_path_normalizes_separators(): void
    {
        $paths = [
            'path\\to\\file' => 'path/to/file',
            'path//to///file' => 'path/to/file',
            'path\\\\to\\\\file' => 'path/to/file',
            'path/to\\mixed/file' => 'path/to/mixed/file',
        ];

        foreach ($paths as $input => $expected) {
            $result = $this->fileOps->sanitizePath($input);
            $this->assertEquals($expected, $result, "Path not normalized: {$input}");
        }
    }

    public function test_validate_file_permission_rejects_world_writable(): void
    {
        // This would need file system integration - testing with mock
        $filename = '/tmp/test-world-writable.txt';

        // Test with permission check
        $result = $this->fileOps->validateFilePermissions($filename, 0666);

        // World-writable files should be rejected
        $this->assertFalse($result);
    }

    public function test_validate_file_permission_accepts_secure_permissions(): void
    {
        $filename = '/tmp/test-secure.txt';

        // Test with secure permission (644)
        $result = $this->fileOps->validateFilePermissions($filename, 0644);

        // Secure files should be accepted
        $this->assertTrue($result);
    }

    public function test_validate_filename_rejects_dangerous_names(): void
    {
        $dangerousNames = [
            '.htaccess',
            '.htpasswd',
            'wp-config.php',
            '.env',
            'web.config',
            'php.ini',
            '.git/config',
            '../../etc/passwd',
            'con', // Windows reserved
            'prn', // Windows reserved
            'aux', // Windows reserved
            'nul', // Windows reserved
        ];

        foreach ($dangerousNames as $filename) {
            $result = $this->fileOps->validateFilename($filename);
            $this->assertFalse($result, "Dangerous filename accepted: {$filename}");
        }
    }

    public function test_validate_filename_accepts_safe_names(): void
    {
        $safeNames = [
            'plugin.php',
            'style.css',
            'script.js',
            'image.png',
            'data.json',
            'readme.txt',
            'my-plugin-file.php',
        ];

        foreach ($safeNames as $filename) {
            $result = $this->fileOps->validateFilename($filename);
            $this->assertTrue($result, "Safe filename rejected: {$filename}");
        }
    }

    public function test_resolve_path_returns_absolute_path_within_root(): void
    {
        $relativePath = 'plugins/my-plugin/file.php';
        $result = $this->fileOps->resolvePath($relativePath);

        $expected = '/tmp/wordpress/plugins/my-plugin/file.php';
        $this->assertEquals($expected, $result);
    }

    public function test_resolve_path_rejects_paths_outside_root(): void
    {
        $escapePaths = [
            '../../../etc/passwd',
            '../../outside.txt',
            '/etc/passwd',
        ];

        foreach ($escapePaths as $path) {
            $result = $this->fileOps->resolvePath($path);
            $this->assertFalse($result, "Escape path not rejected: {$path}");
        }
    }

    public function test_validate_directory_accepts_valid_directory(): void
    {
        // Test with safe relative and absolute paths
        $validDirs = [
            '/tmp/wordpress/plugins',
            '/tmp/wordpress/themes',
            'plugins/my-plugin',
        ];

        foreach ($validDirs as $dir) {
            $result = $this->fileOps->validateDirectory($dir);
            $this->assertTrue($result, "Valid directory rejected: {$dir}");
        }
    }

    public function test_is_within_root_detects_escape_attempts(): void
    {
        $escapePaths = [
            '../../../etc/passwd',
            '/etc/passwd',
            '/var/www/html',
        ];

        foreach ($escapePaths as $path) {
            $result = $this->fileOps->isWithinRoot($path);
            $this->assertFalse($result, "Escape attempt not detected: {$path}");
        }
    }

    public function test_is_within_root_accepts_safe_paths(): void
    {
        $safePaths = [
            '/tmp/wordpress/plugins',
            '/tmp/wordpress/themes',
            'plugins/my-plugin',
            'uploads/file.jpg',
        ];

        foreach ($safePaths as $path) {
            $result = $this->fileOps->isWithinRoot($path);
            $this->assertTrue($result, "Safe path rejected: {$path}");
        }
    }

    public function test_sanitize_filename_removes_dangerous_characters(): void
    {
        $maliciousFilenames = [
            'file<script>.php',
            'file;rm -rf /.txt',
            'file|cat /etc/passwd.dat',
            'file\x00null.php',
            'file../../etc.dat',
        ];

        foreach ($maliciousFilenames as $filename) {
            $result = $this->fileOps->sanitizeFilename($filename);

            $this->assertStringNotContainsString('<', $result);
            $this->assertStringNotContainsString('>', $result);
            $this->assertStringNotContainsString(';', $result);
            $this->assertStringNotContainsString('|', $result);
            $this->assertStringNotContainsString("\0", $result);
            $this->assertStringNotContainsString('..', $result);
        }
    }

    public function test_sanitize_filename_preserves_safe_characters(): void
    {
        $safeFilenames = [
            'my-plugin.php' => 'my-plugin.php',
            'file_v2.js' => 'file_v2.js',
            'image.test.png' => 'image.test.png',
        ];

        foreach ($safeFilenames as $input => $expected) {
            $result = $this->fileOps->sanitizeFilename($input);
            $this->assertEquals($expected, $result);
        }
    }

    public function test_validate_file_extension_rejects_dangerous_extensions(): void
    {
        $dangerousExtensions = [
            'malicious.php',
            'script.php4',
            'script.php5',
            'hack.phtml',
            'dangerous.inc',
            'executable.exe',
            'script.sh',
            'malicious.bat',
            'script.cmd',
        ];

        foreach ($dangerousExtensions as $filename) {
            $result = $this->fileOps->validateFileExtension($filename);
            $this->assertFalse($result, "Dangerous extension accepted: {$filename}");
        }
    }

    public function test_validate_file_extension_accepts_safe_extensions(): void
    {
        $safeExtensions = [
            'plugin.zip',
            'theme.tar.gz',
            'data.json',
            'style.css',
            'readme.txt',
            'image.png',
            'image.jpg',
            'logo.svg',
        ];

        foreach ($safeExtensions as $filename) {
            $result = $this->fileOps->validateFileExtension($filename);
            $this->assertTrue($result, "Safe extension rejected: {$filename}");
        }
    }
}
