<?php

/**
 * UnrePress Settings page.
 *
 * Stores user-supplied git provider tokens and the GitHub discovery toggle
 * in the unrepress_settings option. Token resolution order: constant,
 * saved option, filter, null. Constants stay the host-level override.
 */

declare(strict_types=1);

namespace UnrePress\Admin;

use UnrePress\Helpers;
use UnrePress\Security\InputValidator;
use UnrePress\Security\SecurityMiddleware;

// No direct access
defined('ABSPATH') or die();

class Settings
{
    public const OPTION_KEY = 'unrepress_settings';

    public const PAGE_SLUG = 'unrepress-settings';

    public const PROVIDERS = ['github', 'gitlab', 'bitbucket'];

    private const CAPABILITY = 'manage_options';

    private $security;

    /**
     * @param bool $register_hooks False in unit tests that call methods directly
     */
    public function __construct(bool $register_hooks = true)
    {
        $this->security = new SecurityMiddleware();

        if (!$register_hooks) {
            return;
        }

        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_post_unrepress_clear_cache', [$this, 'handleClearCache']);
    }

    public function registerMenu(): void
    {
        // Parent is index.php: unrepress-updater is itself a Dashboard
        // submenu, and submenus can only attach to top-level pages.
        add_submenu_page(
            'index.php',
            __('UnrePress Settings', 'unrepress'),
            __('Settings', 'unrepress'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting('unrepress_settings', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeSettings'],
            'default' => [],
        ]);
    }

    /**
     * Sanitize the whole settings array on save. Tokens are validated for
     * shape, never echoed; an invalid value keeps the previous one.
     */
    public function sanitizeSettings($input): array
    {
        $inputValidator = new InputValidator();
        $clean = self::getSettings();

        if (!is_array($input)) {
            return $clean;
        }

        foreach (self::PROVIDERS as $provider) {
            $key = $provider . '_token';
            $clear_key = $provider . '_token_clear';

            // Explicit clear flag removes the stored token
            if (!empty($input[$clear_key])) {
                $clean[$key] = '';

                continue;
            }

            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = trim((string) $input[$key]);

            // Empty input keeps the stored token: the form never echoes
            // tokens back into the DOM, so blank means "unchanged".
            if ('' === $value) {
                continue;
            }

            $valid = strlen($value) >= 8
                && strlen($value) <= 255
                && !preg_match('/[\s<>"\']/', $value)
                && !$inputValidator->detectXss($value);

            if ($valid) {
                $clean[$key] = $value;
            }
        }

        $clean['github_discovery'] = !empty($input['github_discovery']);

        return $clean;
    }

    public function renderPage(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        include UNREPRESS_PLUGIN_PATH . 'views/admin/settings.php';
    }

    /**
     * Clear-cache action: nonce-checked POST, then flush everything.
     */
    public function handleClearCache(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to do that.', 'unrepress'));
        }

        check_admin_referer('unrepress_clear_cache');

        // Single shared guard flushes updates, indexes and catalogs alike.
        (new Helpers())->clearUpdateTransients();

        wp_safe_redirect(add_query_arg('cache-cleared', '1', self::pageUrl()));
        exit;
    }

    public static function pageUrl(): string
    {
        return admin_url('index.php?page=' . self::PAGE_SLUG);
    }

    /**
     * Raw stored settings array.
     */
    public static function getSettings(): array
    {
        $stored = get_option(self::OPTION_KEY, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * Resolve a provider token. Order: constant, saved option, filter, null.
     * A constant defined empty falls through so hosts can shadow defaults.
     */
    public static function getProviderToken(string $provider): ?string
    {
        $provider = strtolower($provider);

        if (!in_array($provider, self::PROVIDERS, true)) {
            return null;
        }

        $constant = 'UNREPRESS_TOKEN_' . strtoupper($provider);

        if (defined($constant)) {
            $value = constant($constant);

            if (is_string($value) && '' !== $value) {
                return $value;
            }
        }

        $settings = self::getSettings();
        $option = $settings[$provider . '_token'] ?? '';

        if (is_string($option) && '' !== $option) {
            return $option;
        }

        $filtered = apply_filters('unrepress_' . $provider . '_token', '');

        return is_string($filtered) && '' !== $filtered ? $filtered : null;
    }

    /**
     * Mask a token for display: provider name plus the last 4 characters.
     */
    public static function maskToken(?string $token): string
    {
        if (!is_string($token) || '' === $token) {
            return '';
        }

        return '********' . substr($token, -4);
    }
}
