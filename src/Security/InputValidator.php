<?php

declare(strict_types=1);

namespace UnrePress\Security;

/**
 * Input Validator
 *
 * Provides comprehensive input validation and sanitization to prevent:
 * - SQL injection attacks
 * - XSS attacks
 * - Path traversal attacks
 * - Command injection
 */
class InputValidator
{
    /**
     * Validate and sanitize a plugin/theme slug.
     *
     * @param string $slug The slug to validate.
     *
     * @return string|false Sanitized slug or false if invalid.
     */
    public function validateSlug(string $slug)
    {
        // Remove any directory traversal attempts
        if (strpos($slug, '..') !== false || strpos($slug, "\0") !== false) {
            return false;
        }

        // Sanitize the slug
        $sanitized = sanitize_key($slug);

        // Check if it matches expected slug patterns (plugin-name or plugin-name/file.php)
        if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)?$/', $sanitized)) {
            return false;
        }

        return $sanitized;
    }

    /**
     * Validate GitHub repository URL.
     *
     * @param string $url The URL to validate.
     *
     * @return bool True if valid GitHub URL, false otherwise.
     */
    public function validateGitHubUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }

        // Check if it's a GitHub domain
        $validHosts = [
            'github.com',
            'api.github.com',
            'raw.githubusercontent.com'
        ];

        if (!in_array($parsed['host'], $validHosts, true)) {
            return false;
        }

        // Basic URL structure validation - must use HTTPS and be a valid URL
        // Match github.com, api.github.com, or raw.githubusercontent.com
        if (!preg_match('/^https:\/\/([a-z0-9\-]+\.)*(github|githubusercontent)\.com\//i', $url)) {
            return false;
        }

        return true;
    }

    /**
     * Validate update type to prevent injection.
     *
     * @param string $type The update type to validate.
     *
     * @return bool True if valid, false otherwise.
     */
    public function validateUpdateType(string $type): bool
    {
        $validTypes = ['core', 'plugin', 'theme', 'translation'];

        return in_array($type, $validTypes, true);
    }

    /**
     * Sanitize file path to prevent directory traversal.
     *
     * @param string $path The file path to sanitize.
     *
     * @return string Sanitized path.
     */
    public function sanitizeFilePath(string $path): string
    {
        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Normalize path separators (backslashes to forward slashes)
        $path = str_replace('\\', '/', $path);

        // Remove multiple consecutive slashes
        $path = preg_replace('/\/+/', '/', $path);

        // Remove directory traversal attempts
        $path = preg_replace('/\.\.+\/?/', '', $path);

        // Ensure path starts with valid character
        $path = ltrim($path, '/');

        return $path;
    }

    /**
     * Validate JSON input structure.
     *
     * @param string $json The JSON string to validate.
     *
     * @return bool True if valid JSON, false otherwise.
     */
    public function validateJson(string $json): bool
    {
        json_decode($json);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Sanitize update response data.
     *
     * @param array $data The data to sanitize.
     *
     * @return array Sanitized data.
     */
    public function sanitizeUpdateData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Sanitize keys
            $sanitizedKey = sanitize_key($key);

            // Sanitize values based on type
            if (is_string($value)) {
                $sanitized[$sanitizedKey] = sanitize_text_field($value);
            } elseif (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeUpdateData($value);
            } elseif (is_object($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeObject($value);
            } else {
                $sanitized[$sanitizedKey] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize object properties.
     *
     * @param object $object The object to sanitize.
     *
     * @return object Sanitized object.
     */
    private function sanitizeObject($object)
    {
        $sanitized = new \stdClass();

        foreach (get_object_vars($object) as $key => $value) {
            $sanitizedKey = sanitize_key($key);

            if (is_string($value)) {
                $sanitized->$sanitizedKey = sanitize_text_field($value);
            } elseif (is_array($value)) {
                $sanitized->$sanitizedKey = $this->sanitizeUpdateData($value);
            } else {
                $sanitized->$sanitizedKey = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Validate version string format.
     *
     * @param string $version The version string to validate.
     *
     * @return bool True if valid version format, false otherwise.
     */
    public function validateVersion(string $version): bool
    {
        // Allow formats like: 1.0.0, v1.0.0, 1.0.0-beta, 1.0.0-alpha
        // Only allow common pre-release tags (alpha, beta, rc, dev, patch)
        return (bool) preg_match('/^[v]?\d+\.\d+\.\d+(-?(alpha|beta|rc|dev|patch)\d*)?$/i', $version);
    }

    /**
     * Sanitize and validate user agent string.
     *
     * @param string $userAgent The user agent string.
     *
     * @return string Sanitized user agent.
     */
    public function sanitizeUserAgent(string $userAgent): string
    {
        // Remove null bytes and control characters
        $userAgent = preg_replace('/[\x00-\x08\x0B-\x1F]/', '', $userAgent);

        // Limit length
        if (strlen($userAgent) > 500) {
            $userAgent = substr($userAgent, 0, 500);
        }

        return sanitize_text_field($userAgent);
    }

    /**
     * Validate file extension.
     *
     * @param string $filename  The filename to check.
     * @param array  $allowed  Allowed file extensions.
     *
     * @return bool True if extension is allowed, false otherwise.
     */
    public function validateFileExtension(string $filename, array $allowed = ['zip', 'tar.gz', 'json']): bool
    {
        // Check for tar.gz specifically first
        if (preg_match('/\.tar\.gz$/i', $filename)) {
            return in_array('tar.gz', $allowed, true);
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return in_array(strtolower($ext), $allowed, true);
    }

    /**
     * Detect SQL injection patterns.
     *
     * @param string $input The input to check.
     *
     * @return bool True if SQL injection detected, false otherwise.
     */
    public function detectSqlInjection(string $input): bool
    {
        $patterns = [
            '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|EXEC|SCRIPT)\b/i',
            '/(\'|\;\s*--|\/\*|\*\/)/i',
            '/(\bOR\b|\bAND\b).*=.*\b(SELECT\b)/i',
            '/\b(WHERE|HAVING)\b.*\b(OR|AND)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect XSS attack patterns.
     *
     * @param string $input The input to check.
     *
     * @return bool True if XSS detected, false otherwise.
     */
    public function detectXss(string $input): bool
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i', // onclick=, onload=, etc.
            '/<iframe[^>]*>/i',
            '/<embed[^>]*>/i',
            '/<object[^>]*>/i',
            '/<meta[^>]*>/i',
            '/<style[^>]*>.*?<\/style>/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate request nonce for AJAX requests.
     *
     * @param string $nonce The nonce to validate.
     * @param string $action The nonce action.
     *
     * @return bool True if nonce is valid, false otherwise.
     */
    public function validateNonce(string $nonce, string $action = 'unrepress_action'): bool
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Validate and sanitize repository string.
     *
     * @param string $repository The repository string to validate.
     *
     * @return string|false Sanitized repository or false if invalid.
     */
    public function validateRepository(string $repository)
    {
        // Strip HTML tags first
        $sanitized = strip_tags($repository);

        // Remove any potentially dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9_\-\/\.@]+/', '', $sanitized);

        // Basic structure validation (username/repo)
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\/[a-zA-Z0-9_\-\.]+$/', $sanitized)) {
            return false;
        }

        return $sanitized;
    }
}
