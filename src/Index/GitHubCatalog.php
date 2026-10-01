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

    /**
     * Page-scoped star map for the search badge script. Card builders
     * register stars here; the admin footer prints the map as JSON and a
     * small script injects badges into the rendered grids.
     */
    private static array $starMap = [];

    public static function addStar(string $slug, int $stars): void
    {
        if ('' !== $slug && $stars >= 0) {
            self::$starMap[$slug] = $stars;
        }
    }

    public static function starMap(): array
    {
        return self::$starMap;
    }

    public static function resetStarMap(): void
    {
        self::$starMap = [];
    }

    /**
     * Catalog entries whose name, description, tags or topics contain the
     * search term. Pure, for testability.
     */
    public function searchEntries(array $catalog, string $kind, string $term): array
    {
        if (!isset($catalog[$kind]) || !is_array($catalog[$kind])) {
            return [];
        }

        $term = strtolower(trim($term));

        if ('' === $term) {
            return [];
        }

        $matches = [];

        foreach ($catalog[$kind] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $tags = array_merge(
                (array) ($entry['tags'] ?? []),
                (array) ($entry['topics'] ?? [])
            );

            $haystack = strtolower(
                ($entry['name'] ?? '') . ' '
                . ($entry['description'] ?? '') . ' '
                . implode(' ', array_map('strval', $tags))
            );

            if (str_contains($haystack, $term)) {
                $matches[] = $entry;
            }
        }

        return $matches;
    }

    /**
     * Search matches from the cached catalog.
     *
     * @return array[] Empty when discovery is off or the catalog is unreachable
     */
    public function search(string $kind, string $term): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        $catalog = $this->getCatalog($kind);

        if (null === $catalog) {
            return [];
        }

        return $this->searchEntries($catalog, $kind, $term);
    }

    /**
     * Full WordPress plugin card for a catalog entry, in the same shape
     * getPluginData() produces for curated plugins. Stars are registered
     * for the search badge as the social proof (no fake install counts).
     */
    public function pluginCard(array $entry): array
    {
        $slug = sanitize_text_field((string) ($entry['slug'] ?? ''));
        $name = sanitize_text_field((string) ($entry['name'] ?? ''));
        $description = sanitize_text_field((string) ($entry['description'] ?? ''));
        $version = sanitize_text_field((string) ($entry['version'] ?? '0.0.0'));
        $requires = sanitize_text_field((string) ($entry['requires'] ?? '6.0'));
        $requiresPhp = sanitize_text_field((string) ($entry['requires_php'] ?? '7.4'));
        $download = esc_url_raw((string) ($entry['download_url'] ?? ''));
        $repoUrl = esc_url_raw((string) ($entry['homepage'] ?? ''));
        $authorLink = (string) ($entry['author'] ?? '');
        $stars = (int) ($entry['stars'] ?? 0);
        $icon = UNREPRESS_PLUGIN_URL . 'assets/images/icon-256.webp';

        self::addStar($slug, $stars);

        return [
            'name' => '' !== $name ? $name : $slug,
            'slug' => $slug,
            'version' => $version,
            'author' => wp_kses_post($authorLink),
            'author_profile' => $repoUrl,
            'requires' => $requires,
            'tested' => get_bloginfo('version'),
            'requires_php' => $requiresPhp,
            'sections' => [
                'description' => wp_kses_post(nl2br($description)),
                'installation' => '',
                'changelog' => '',
            ],
            'banners' => [
                'low' => UNREPRESS_PLUGIN_URL . 'assets/images/banner-772x250.webp',
                'high' => UNREPRESS_PLUGIN_URL . 'assets/images/banner-1544x500.webp',
            ],
            'icons' => [
                'default' => $icon,
                'low' => $icon,
                'high' => $icon,
            ],
            'download_url' => $download,
            'download_link' => $download,
            'homepage' => $repoUrl,
            'short_description' => '' !== $description
                ? substr($description, 0, 150) . (strlen($description) > 150 ? '&hellip;' : '')
                : '',
            'rating' => 0,
            'num_ratings' => 0,
            'support_threads' => 0,
            'support_threads_resolved' => 0,
            'active_installs' => 0,
            'last_updated' => sanitize_text_field((string) ($entry['pushed_at'] ?? '')) ?: gmdate('Y-m-d'),
            'added' => substr((string) ($entry['created_at'] ?? ''), 0, 10) ?: gmdate('Y-m-d'),
            'tags' => array_slice(array_map('sanitize_text_field', (array) ($entry['tags'] ?? [])), 0, 5),
            'compatibility' => [],
            'contributors' => [],
            'screenshots' => [],
            'external' => true,
            'source' => 'github',
            'stars' => $stars,
            'build' => sanitize_text_field((string) ($entry['build'] ?? '')),
        ];
    }

    /**
     * Theme card in the curated themes-index entry shape (slug, name,
     * description, tags) plus GitHub extras the grid tolerates.
     */
    public function themeCard(array $entry): array
    {
        $slug = sanitize_text_field((string) ($entry['slug'] ?? ''));
        $name = sanitize_text_field((string) ($entry['name'] ?? ''));
        $description = sanitize_text_field((string) ($entry['description'] ?? ''));
        $stars = (int) ($entry['stars'] ?? 0);

        self::addStar($slug, $stars);

        return [
            'slug' => $slug,
            'name' => '' !== $name ? $name : $slug,
            'description' => $description,
            'tags' => array_slice(array_map('sanitize_text_field', (array) ($entry['tags'] ?? [])), 0, 10),
            'author' => wp_strip_all_tags((string) ($entry['author'] ?? '')),
            'version' => sanitize_text_field((string) ($entry['version'] ?? '0.0.0')),
            'homepage' => esc_url_raw((string) ($entry['homepage'] ?? '')),
            'preview_url' => esc_url_raw((string) ($entry['homepage'] ?? '')),
            'screenshot_url' => '',
            'rating' => 0,
            'num_ratings' => 0,
            'active_installs' => 0,
            'source' => 'github',
            'stars' => $stars,
        ];
    }

    /**
     * Plugin cards for many entries, empties dropped.
     */
    public function pluginCards(array $entries): array
    {
        return array_values(array_filter(array_map([$this, 'pluginCard'], $entries)));
    }

    /**
     * Theme cards for many entries.
     */
    public function themeCards(array $entries): array
    {
        return array_values(array_map([$this, 'themeCard'], $entries));
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
