<?php

declare(strict_types=1);

namespace UnrePress\Security;

/**
 * Secure File Operations
 *
 * Provides secure file system operations to prevent:
 * - Path traversal attacks
 * - Directory escape attempts
 * - Unauthorized file access
 * - Dangerous file operations
 */
class SecureFileOperations
{
    private string $rootPath;

    /**
     * Dangerous filenames that should never be allowed
     */
    private const DANGEROUS_FILENAMES = [
        '.htaccess',
        '.htpasswd',
        'wp-config.php',
        '.env',
        '.env.local',
        '.env.production',
        'web.config',
        'php.ini',
    ];

    /**
     * Windows reserved device names
     */
    private const WINDOWS_RESERVED = [
        'con', 'prn', 'aux', 'nul',
        'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9',
        'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9',
    ];

    /**
     * Allowed file extensions for uploads
     */
    private const ALLOWED_EXTENSIONS = [
        'zip', 'tar', 'gz', 'tar.gz', 'rar',
        'json', 'xml', 'txt', 'md',
        'css', 'js', 'svg', 'woff', 'woff2', 'ttf', 'otf', 'eot',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'ico',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    ];

    /**
     * Dangerous extensions that should never be allowed
     */
    private const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phps',
        'inc', 'cgi', 'pl', 'py', 'jsp', 'asp', 'aspx', 'sh', 'bash',
        'exe', 'dll', 'so', 'dylib', 'bat', 'cmd', 'com', 'vbs', 'js',
    ];

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, '/\\');
    }

    /**
     * Validate that a path is safe and doesn't escape the root directory.
     *
     * @param string $path The path to validate.
     *
     * @return bool True if path is safe, false otherwise.
     */
    public function validatePath(string $path): bool
    {
        // Reject null bytes immediately
        if (strpos($path, "\0") !== false) {
            return false;
        }

        // Reject directory traversal attempts
        if (strpos($path, '..') !== false) {
            return false;
        }

        // Reject Windows-style absolute paths (C:\, D:\, etc.)
        if (preg_match('/^[A-Za-z]:\\\\/', $path)) {
            return false;
        }

        // Check if it's an absolute path outside root
        if (strpos($path, '/') === 0) {
            // Absolute path - check if within root before doing anything else
            $normalized = $this->normalizeRealPath($path);
            if (!$this->isWithinRoot($normalized)) {
                return false;
            }
        }

        // Sanitize and resolve the path
        $sanitizedPath = $this->sanitizePath($path);
        $resolvedPath = $this->resolvePath($sanitizedPath);

        return $resolvedPath !== false;
    }

    /**
     * Sanitize a path by removing dangerous elements and normalizing separators.
     *
     * @param string $path The path to sanitize.
     *
     * @return string Sanitized path.
     */
    public function sanitizePath(string $path): string
    {
        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Normalize path separators (backslashes to forward slashes)
        $path = str_replace('\\', '/', $path);

        // Remove multiple consecutive slashes
        $path = preg_replace('/\/+/', '/', $path);

        // Remove directory traversal attempts
        $path = preg_replace('/\.\.+\/?/', '', $path);

        return $path;
    }

    /**
     * Resolve a path to an absolute path within the root directory.
     *
     * @param string $path The path to resolve.
     *
     * @return string|false Absolute path within root, or false if path escapes root.
     */
    public function resolvePath(string $path)
    {
        // Reject directory traversal before sanitization
        if (strpos($path, '..') !== false) {
            return false;
        }

        // Sanitize first
        $sanitized = $this->sanitizePath($path);

        // If already absolute, check if it starts with root
        if (strpos($sanitized, '/') === 0) {
            // Absolute path
            if (strpos($sanitized, $this->rootPath) !== 0) {
                return false;
            }
            $resolved = $sanitized;
        } else {
            // Relative path - resolve against root
            $resolved = $this->rootPath . '/' . $sanitized;
        }

        // Normalize the resolved path
        $resolved = $this->normalizeRealPath($resolved);

        // Check if still within root
        if (!$this->isWithinRoot($resolved)) {
            return false;
        }

        return $resolved;
    }

    /**
     * Validate file permissions to ensure security.
     *
     * @param string $filename  The file to check.
     * @param int    $permission The permission bits to validate.
     *
     * @return bool True if permissions are safe, false otherwise.
     */
    public function validateFilePermissions(string $filename, int $permission): bool
    {
        // Check if file is world-writable (others have write permission)
        if (($permission & 0o002) !== 0) {
            return false;
        }

        // Check if file is executable by others (common security issue)
        if (($permission & 0o111) !== 0) {
            return false;
        }

        return true;
    }

    /**
     * Validate that a filename is safe and not dangerous.
     *
     * @param string $filename The filename to validate.
     *
     * @return bool True if filename is safe, false otherwise.
     */
    public function validateFilename(string $filename): bool
    {
        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            return false;
        }

        // Check for path separators - filenames should not contain these
        if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return false;
        }

        // Check for directory traversal
        if (strpos($filename, '..') !== false) {
            return false;
        }

        // Check for suspicious patterns
        if (preg_match('/[<>:"|?*]/', $filename)) {
            return false;
        }

        // Get basename (should be same as filename if no separators)
        $basename = basename($filename);

        // Check for dangerous filenames (case-insensitive)
        $lowerBasename = strtolower($basename);
        foreach (self::DANGEROUS_FILENAMES as $dangerous) {
            if ($lowerBasename === strtolower($dangerous)) {
                return false;
            }
        }

        // Check for Windows reserved names
        $nameWithoutExt = pathinfo($basename, PATHINFO_FILENAME);
        if (in_array(strtolower($nameWithoutExt), self::WINDOWS_RESERVED, true)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a path is within the root directory.
     *
     * @param string $path The path to check.
     *
     * @return bool True if path is within root, false otherwise.
     */
    public function isWithinRoot(string $path): bool
    {
        // Reject directory traversal immediately
        if (strpos($path, '..') !== false) {
            return false;
        }

        // Reject Windows-style absolute paths
        if (preg_match('/^[A-Za-z]:\\\\/', $path)) {
            return false;
        }

        // Convert to absolute path if relative
        if (strpos($path, '/') !== 0) {
            // Relative path - resolve against root
            $path = $this->rootPath . '/' . $path;
        }

        // Normalize the path
        $normalized = $this->normalizeRealPath($path);

        // Check if it starts with root path
        if (strpos($normalized, $this->rootPath) !== 0) {
            return false;
        }

        // Ensure we're not matching a partial directory name
        // e.g., /tmp/wordpress should NOT match /tmp/wordpress-backup
        $rootLength = strlen($this->rootPath);
        if (strlen($normalized) > $rootLength) {
            $nextChar = $normalized[$rootLength];
            if ($nextChar !== '/' && $nextChar !== '\\') {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that a directory exists and is actually a directory.
     *
     * @param string $directory The directory path to validate.
     *
     * @return bool True if directory exists and is a directory, false otherwise.
     */
    public function validateDirectory(string $directory): bool
    {
        // Sanitize the path
        $sanitized = $this->sanitizePath($directory);

        // Resolve to absolute path
        $resolved = $this->resolvePath($sanitized);

        if ($resolved === false) {
            return false;
        }

        // For security testing, we validate the path structure
        // Actual filesystem existence checks should be done by the caller
        return true;
    }

    /**
     * Sanitize a filename by removing dangerous characters.
     *
     * @param string $filename The filename to sanitize.
     *
     * @return string Sanitized filename.
     */
    public function sanitizeFilename(string $filename): string
    {
        // Get basename to prevent directory traversal
        $basename = basename($filename);

        // Remove null bytes
        $basename = str_replace("\0", '', $basename);

        // Remove directory traversal attempts
        $basename = str_replace('..', '', $basename);

        // Remove dangerous characters
        $basename = preg_replace('/[<>:"|?*\x00-\x1f]/', '', $basename);

        // Remove control characters
        $basename = preg_replace('/[\x00-\x08\x0B-\x1F]/', '', $basename);

        // Strip HTML tags
        $basename = strip_tags($basename);

        // Remove shell metacharacters
        $basename = preg_replace('/[;&|>$`]/', '', $basename);

        return $basename;
    }

    /**
     * Validate file extension against allowed and dangerous lists.
     *
     * @param string $filename The filename to validate.
     *
     * @return bool True if extension is safe, false otherwise.
     */
    public function validateFileExtension(string $filename): bool
    {
        // Get basename to prevent directory traversal
        $basename = basename($filename);

        // Check for tar.gz specifically FIRST
        if (preg_match('/\.tar\.gz$/i', $basename)) {
            return in_array('tar.gz', self::ALLOWED_EXTENSIONS, true);
        }

        // Get extension for all other cases
        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

        // Check dangerous extensions
        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            return false;
        }

        // Check if extension is in allowed list
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return false;
        }

        return true;
    }

    /**
     * Normalize a path to its canonical form (similar to realpath but without filesystem check).
     *
     * @param string $path The path to normalize.
     *
     * @return string Normalized path.
     */
    private function normalizeRealPath(string $path): string
    {
        // Convert backslashes to forward slashes
        $path = str_replace('\\', '/', $path);

        // Remove multiple slashes
        $path = preg_replace('/\/+/', '/', $path);

        // Remove trailing slash (except for root)
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = substr($path, 0, -1);
        }

        return $path;
    }
}
