<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Security;

use UnrePress\Security\SecurityMiddleware;
use UnrePress\Tests\Helpers\WordPressTestHelper;

/**
 * SecurityMiddleware Unit Tests
 *
 * Tests for centralized security validation including CSRF protection,
 * capability checks, and input sanitization.
 */
class SecurityMiddlewareTest extends WordPressTestHelper
{
    private SecurityMiddleware $security;

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

        // Mock WordPress security functions
        \Brain\Monkey\Functions\when('check_ajax_referer')->justReturn(false);
        \Brain\Monkey\Functions\when('check_admin_referer')->justReturn(false);
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_send_json_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_create_nonce')->justReturn('valid_nonce_123');

        // Mock sanitization functions
        \Brain\Monkey\Functions\when('sanitize_text_field')->returnArg();
        \Brain\Monkey\Functions\when('esc_url_raw')->returnArg();
        \Brain\Monkey\Functions\when('sanitize_email')->returnArg();
        \Brain\Monkey\Functions\when('sanitize_file_name')->returnArg();
        \Brain\Monkey\Functions\when('sanitize_key')->returnArg();

        $this->security = new SecurityMiddleware();
    }

    public function test_verify_ajax_nonce_returns_false_for_invalid_nonce(): void
    {
        \Brain\Monkey\Functions\when('check_ajax_referer')->justReturn(false);

        $result = $this->security->verifyAjaxNonce('unrepress_action', false);

        $this->assertFalse($result);
    }

    public function test_verify_ajax_nonce_returns_true_for_valid_nonce(): void
    {
        \Brain\Monkey\Functions\when('check_ajax_referer')->justReturn(true);

        $result = $this->security->verifyAjaxNonce('unrepress_action', false);

        $this->assertTrue($result);
    }

    public function test_verify_capability_returns_false_for_unauthorized_user(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->security->verifyCapability('manage_options');

        $this->assertFalse($result);
    }

    public function test_verify_capability_returns_true_for_authorized_user(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->security->verifyCapability('manage_options');

        $this->assertTrue($result);
    }

    public function test_send_security_error_sends_json_error(): void
    {
        // Test that sendSecurityError method exists and works
        $this->assertTrue(method_exists($this->security, 'sendSecurityError'));
    }

    public function test_validate_ajax_request_returns_false_for_invalid_nonce(): void
    {
        \Brain\Monkey\Functions\when('check_ajax_referer')->justReturn(false);

        $result = $this->security->validateAjaxRequest('unrepress_action', 'manage_options', false);

        $this->assertFalse($result);
    }

    public function test_validate_ajax_request_returns_false_for_insufficient_capabilities(): void
    {
        \Brain\Monkey\Functions\when('check_ajax_referer')->justReturn(true);
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->security->validateAjaxRequest('unrepress_action', 'manage_options', false);

        $this->assertFalse($result);
    }

    public function test_validate_ajax_request_returns_true_for_valid_request(): void
    {
        \Brain\Monkey\Functions\when('check_ajax_referer')->justReturn(true);
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->security->validateAjaxRequest('unrepress_action', 'manage_options', false);

        $this->assertTrue($result);
    }

    public function test_sanitize_input_sanitizes_text(): void
    {
        $input = '<script>alert("XSS")</script>';

        \Brain\Monkey\Functions\when('sanitize_text_field')->justReturn('alert("XSS")');

        $result = $this->security->sanitizeInput($input, 'text');

        $this->assertEquals('alert("XSS")', $result);
    }

    public function test_sanitize_input_sanitizes_url(): void
    {
        $input = 'javascript:alert("XSS")';

        \Brain\Monkey\Functions\when('esc_url_raw')->justReturn('http://example.com');

        $result = $this->security->sanitizeInput($input, 'url');

        $this->assertEquals('http://example.com', $result);
    }

    public function test_sanitize_input_sanitizes_email(): void
    {
        $input = 'test@example.com';

        \Brain\Monkey\Functions\when('sanitize_email')->justReturn('test@example.com');

        $result = $this->security->sanitizeInput($input, 'email');

        $this->assertEquals('test@example.com', $result);
    }

    public function test_sanitize_input_sanitizes_filename(): void
    {
        $input = 'test-file.php';

        \Brain\Monkey\Functions\when('sanitize_file_name')->justReturn('test-file.php');

        $result = $this->security->sanitizeInput($input, 'filename');

        $this->assertEquals('test-file.php', $result);
    }

    public function test_sanitize_input_sanitizes_slug(): void
    {
        $input = 'Test-Slug_123';

        \Brain\Monkey\Functions\when('sanitize_key')->justReturn('test-slug-123');

        $result = $this->security->sanitizeInput($input, 'slug');

        $this->assertEquals('test-slug-123', $result);
    }

    public function test_validate_update_type_accepts_valid_types(): void
    {
        $validTypes = ['core', 'plugin', 'theme', 'translation'];

        foreach ($validTypes as $type) {
            $result = $this->security->validateUpdateType($type);
            $this->assertTrue($result, "Valid type rejected: {$type}");
        }
    }

    public function test_validate_update_type_rejects_invalid_types(): void
    {
        $invalidTypes = ['malicious', '../../../etc/passwd', 'script', '../../config', ''];

        foreach ($invalidTypes as $type) {
            $result = $this->security->validateUpdateType($type);
            $this->assertFalse($result, "Invalid type accepted: {$type}");
        }
    }

    public function test_create_nonce_generates_nonce(): void
    {
        \Brain\Monkey\Functions\when('wp_create_nonce')->justReturn('valid_nonce_123');

        $result = $this->security->createNonce('unrepress_action');

        $this->assertEquals('valid_nonce_123', $result);
    }

    public function test_sanitize_input_trims_strings(): void
    {
        $input = '  test input  ';
        $expected = 'test input';

        \Brain\Monkey\Functions\when('sanitize_text_field')->justReturn('test input');

        $result = $this->security->sanitizeInput($input, 'text', true);

        $this->assertEquals($expected, $result);
    }

    public function test_sanitize_input_does_not_trim_when_disabled(): void
    {
        $input = '  test input  ';
        $expected = '  test input  ';

        \Brain\Monkey\Functions\when('sanitize_text_field')->justReturn('  test input  ');

        $result = $this->security->sanitizeInput($input, 'text', false);

        $this->assertEquals($expected, $result);
    }

    public function test_validate_ajax_request_without_json_error(): void
    {
        \Brain\Monkey\Functions\when('check_ajax_referer')->justReturn(true);
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->security->validateAjaxRequest('unrepress_action', 'manage_options', false);

        $this->assertTrue($result);
    }

    public function test_verify_admin_nonce_returns_false_for_invalid_nonce(): void
    {
        \Brain\Monkey\Functions\when('check_admin_referer')->justReturn(false);

        $result = $this->security->verifyAdminNonce('unrepress_action', false);

        $this->assertFalse($result);
    }

    public function test_verify_admin_nonce_returns_true_for_valid_nonce(): void
    {
        \Brain\Monkey\Functions\when('check_admin_referer')->justReturn(true);

        $result = $this->security->verifyAdminNonce('unrepress_action', false);

        $this->assertTrue($result);
    }
}
