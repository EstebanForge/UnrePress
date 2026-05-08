<?php

declare(strict_types=1);

namespace UnrePress\Security;

/**
 * Security Middleware.
 *
 * Provides centralized security validation for AJAX requests and sensitive operations.
 */
class SecurityMiddleware
{
    /**
     * Verify nonce for AJAX requests.
     *
     * @param string $action The nonce action to verify.
     * @param bool   $die    Whether to die on failure. Default true.
     *
     * @return bool False if nonce is invalid, true if valid (or dies).
     */
    public function verifyAjaxNonce(string $action, bool $die = true): bool
    {
        return check_ajax_referer($action, false, $die);
    }

    /**
     * Verify nonce for admin requests.
     *
     * @param string $action The nonce action to verify.
     * @param bool   $die    Whether to die on failure. Default true.
     *
     * @return bool|void False if nonce is invalid, true if valid (or dies).
     */
    public function verifyAdminNonce(string $action, bool $die = true)
    {
        $result = check_admin_referer($action);

        if (!$result && $die) {
            wp_die(__('You are not allowed to perform this action.', 'unrepress'));
        }

        return $result;
    }

    /**
     * Verify user capabilities for sensitive operations.
     *
     * @param string $capability The capability to check.
     *
     * @return bool True if user has capability, false otherwise.
     */
    public function verifyCapability(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Send JSON error response for failed security checks.
     *
     * @param string $message Error message to send.
     *
     * @return void
     */
    public function sendSecurityError(string $message = 'Security check failed'): void
    {
        wp_send_json_error([
            'message' => $message,
            'code' => 'security_check_failed',
        ]);
    }

    /**
     * Validate AJAX request with nonce and capability checks.
     *
     * @param string $nonce_action   The nonce action to verify.
     * @param string $capability     The capability required.
     * @param bool   $send_json_error Whether to send JSON error on failure. Default true.
     *
     * @return bool True if all checks pass, false otherwise.
     */
    public function validateAjaxRequest(string $nonce_action, string $capability = 'manage_options', bool $send_json_error = true): bool
    {
        // Verify nonce
        if (!$this->verifyAjaxNonce($nonce_action, false)) {
            if ($send_json_error) {
                $this->sendSecurityError('Invalid security token. Please refresh and try again.');
            }

            return false;
        }

        // Verify capability
        if (!$this->verifyCapability($capability)) {
            if ($send_json_error) {
                $this->sendSecurityError('You do not have permission to perform this action.');
            }

            return false;
        }

        return true;
    }

    /**
     * Sanitize and validate input data.
     *
     * @param mixed  $input   The input data to sanitize.
     * @param string $type    The input type (text, url, email, etc.).
     * @param bool   $trim    Whether to trim the input. Default true.
     *
     * @return mixed Sanitized input or false if invalid.
     */
    public function sanitizeInput($input, string $type = 'text', bool $trim = true)
    {
        if ($trim && is_string($input)) {
            $input = trim($input);
        }

        switch ($type) {
            case 'text':
                return sanitize_text_field($input);
            case 'url':
                return esc_url_raw($input);
            case 'email':
                return sanitize_email($input);
            case 'filename':
                return sanitize_file_name($input);
            case 'slug':
                return sanitize_key($input);
            default:
                return sanitize_text_field($input);
        }
    }

    /**
     * Validate update type to prevent injection attacks.
     *
     * @param string $type The update type to validate.
     *
     * @return bool True if valid update type, false otherwise.
     */
    public function validateUpdateType(string $type): bool
    {
        $valid_types = ['core', 'plugin', 'theme', 'translation'];

        return in_array($type, $valid_types, true);
    }

    /**
     * Create nonce for AJAX requests.
     *
     * @param string $action The nonce action.
     *
     * @return string The nonce value.
     */
    public function createNonce(string $action): string
    {
        return wp_create_nonce($action);
    }
}
