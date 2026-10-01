<?php

/**
 * GitHub topic catalog access.
 *
 * Fetches the crawler-generated discovery/github-*.json catalogs routed
 * through the main index "github_index" keys and resolves entries by slug.
 * All fields are precomputed crawler-side: resolution never calls
 * api.github.com at runtime.
 */

declare(strict_types=1);

namespace UnrePress\Index;

use UnrePress\Debugger;

// No direct access
defined('ABSPATH') or die();

class GitHubCatalog
{
    public const OPTION_TRACKED_REPOS = 'unrepress_tracked_repos';

    public const KIND_PLUGINS = 'plugins';

    public const KIND_THEMES = 'themes';

    private const VALID_KINDS = [self::KIND_PLUGINS, self::KIND_THEMES];

    /**
     * Build the namespaced slug for a GitHub repo.
     * The double hyphen is unambiguous because GitHub usernames cannot
     * contain consecutive hyphens.
     */
    public static function buildSlug(string $owner, string $repo): string
    {
        return $owner . '--' . $repo;
    }

    /**
     * Split a namespaced slug into [owner, repo], or null when malformed.
     */
    public static function parseSlug(string $slug): ?array
    {
        $separator = strpos($slug, '--');

        if (false === $separator || 0 === $separator || $separator + 2 >= strlen($slug)) {
            return null;
        }

        $owner = substr($slug, 0, $separator);
        $repo = substr($slug, $separator + 2);

        if ('' === $owner || '' === $repo || str_contains($owner, '--')) {
            return null;
        }

        return [$owner, $repo];
    }

    /**
     * GitHub discovery is on unless the constant or the Settings toggle
     * turns it off. The constant always wins.
     */
    public static function isEnabled(): bool
    {
        if (defined('UNREPRESS_GITHUB_DISCOVERY')) {
            return (bool) constant('UNREPRESS_GITHUB_DISCOVERY');
        }

        $settings = get_option('unrepress_settings', []);

        if (is_array($settings) && array_key_exists('github_discovery', $settings)) {
            return (bool) $settings['github_discovery'];
        }

        return true;
    }

    /**
     * Full catalog for a kind, transient-cached for 3 hours.
     *
     * @return array|null The catalog array with a "plugins"/"themes" root key
     */
    public function getCatalog(string $kind): ?array
    {
        if (!in_array($kind, self::VALID_KINDS, true)) {
            return null;
        }

        $transient_key = UNREPRESS_PREFIX . 'github_catalog_' . $kind;
        $catalog = get_transient($transient_key);

        if (is_array($catalog)) {
            return $catalog;
        }

        $main_index = (new \UnrePress\UnrePress())->index();
        $url = is_array($main_index) ? ($main_index[$kind]['github_index'] ?? null) : null;

        if (!is_string($url) || '' === $url) {
            return null;
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            Debugger::log("GitHubCatalog: failed fetching {$kind} catalog");

            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data) || !isset($data[$kind]) || !is_array($data[$kind])) {
            Debugger::log("GitHubCatalog: invalid {$kind} catalog payload");

            return null;
        }

        set_transient($transient_key, $data, 3 * HOUR_IN_SECONDS);

        return $data;
    }

    /**
     * Find one entry by slug inside a catalog array. Pure, for testability.
     */
    public function findEntry(array $catalog, string $kind, string $slug): ?array
    {
        if (!isset($catalog[$kind]) || !is_array($catalog[$kind])) {
            return null;
        }

        foreach ($catalog[$kind] as $entry) {
            if (is_array($entry) && ($entry['slug'] ?? null) === $slug) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * One entry by slug, from the cached catalog.
     */
    public function getEntry(string $kind, string $slug): ?array
    {
        $catalog = $this->getCatalog($kind);

        if (null === $catalog) {
            return null;
        }

        return $this->findEntry($catalog, $kind, $slug);
    }

    /**
     * Entry by repo URL, for tracked installs whose entry left the catalog
     * slice but whose coordinates we recorded at install time.
     */
    public function getEntryByRepoUrl(string $kind, string $repoUrl): ?array
    {
        $catalog = $this->getCatalog($kind);

        if (null === $catalog || !isset($catalog[$kind]) || !is_array($catalog[$kind])) {
            return null;
        }

        $needle = $this->normalizeRepoUrl($repoUrl);

        if ('' === $needle) {
            return null;
        }

        foreach ($catalog[$kind] as $entry) {
            if (is_array($entry) && $this->normalizeRepoUrl((string) ($entry['homepage'] ?? '')) === $needle) {
                return $entry;
            }
        }

        return null;
    }

    private function normalizeRepoUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = (string) preg_replace('#^https?://(www\.)?#', '', $url);
        $url = (string) preg_replace('#\.git$#', '', $url);

        return rtrim($url, '/');
    }

    /**
     * Record an installed GitHub extension so catalog drift never orphans it.
     */
    public function track(string $slug, string $repoUrl, string $version): void
    {
        if ('' === $slug || '' === $repoUrl) {
            return;
        }

        $tracked = $this->getTracked();
        $tracked[$slug] = [
            'provider' => 'github',
            'repo_url' => $repoUrl,
            'installed_version' => $version,
            'tracked_at' => gmdate('Y-m-d H:i:s'),
        ];

        update_option(self::OPTION_TRACKED_REPOS, $tracked, false);
    }

    /**
     * All tracked installs: slug => {provider, repo_url, installed_version}.
     */
    public function getTracked(): array
    {
        $tracked = get_option(self::OPTION_TRACKED_REPOS, []);

        return is_array($tracked) ? $tracked : [];
    }

    /**
     * One tracked record by slug, or null.
     */
    public function getTrackedEntry(string $slug): ?array
    {
        $tracked = $this->getTracked();

        return isset($tracked[$slug]) && is_array($tracked[$slug]) ? $tracked[$slug] : null;
    }
}
