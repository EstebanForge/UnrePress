<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Index;

use UnrePress\Index\GitHubCatalog;
use UnrePress\Tests\Helpers\WordPressTestHelper;

/**
 * GitHubCatalog unit tests: slug building/parsing, entry lookup, tracking.
 */
class GitHubCatalogTest extends WordPressTestHelper
{
    public function testBuildSlugJoinsOwnerAndRepo(): void
    {
        $this->assertSame('acme--cool-plugin', GitHubCatalog::buildSlug('acme', 'cool-plugin'));
        $this->assertSame('WordPress--gutenberg', GitHubCatalog::buildSlug('WordPress', 'gutenberg'));
    }

    public function testParseSlugRoundTrips(): void
    {
        $parsed = GitHubCatalog::parseSlug('acme--cool-plugin');

        $this->assertNotNull($parsed);
        $this->assertSame(['acme', 'cool-plugin'], $parsed);
    }

    public function testParseSlugRejectsMalformedSlugs(): void
    {
        $this->assertNull(GitHubCatalog::parseSlug(''));
        $this->assertNull(GitHubCatalog::parseSlug('plain-slug'));
        $this->assertNull(GitHubCatalog::parseSlug('acme--'));
        $this->assertNull(GitHubCatalog::parseSlug('--repo'));
    }

    public function testOwnerWithHyphensDoesNotCollideWithRepoHyphens(): void
    {
        // my/org-custom-plugin and my-org/custom-plugin must map to distinct slugs
        $first = GitHubCatalog::buildSlug('my', 'org-custom-plugin');
        $second = GitHubCatalog::buildSlug('my-org', 'custom-plugin');

        $this->assertSame('my--org-custom-plugin', $first);
        $this->assertSame('my-org--custom-plugin', $second);
        $this->assertNotSame($first, $second);

        // Round-trip each back to the original coordinates
        $this->assertSame(['my', 'org-custom-plugin'], GitHubCatalog::parseSlug($first));
        $this->assertSame(['my-org', 'custom-plugin'], GitHubCatalog::parseSlug($second));
    }

    public function testRepoNamesWithDoubleHyphensStillParse(): void
    {
        // Owner can never contain --, so the first -- is the separator
        $parsed = GitHubCatalog::parseSlug('acme--weird--repo');

        $this->assertNotNull($parsed);
        $this->assertSame(['acme', 'weird--repo'], $parsed);
    }

    public function testFindEntryFindsBySlug(): void
    {
        $catalog = new GitHubCatalog();
        $data = [
            'plugins' => [
                ['slug' => 'acme--one', 'name' => 'One'],
                ['slug' => 'acme--two', 'name' => 'Two'],
            ],
        ];

        $entry = $catalog->findEntry($data, 'plugins', 'acme--two');

        $this->assertNotNull($entry);
        $this->assertSame('Two', $entry['name']);
        $this->assertNull($catalog->findEntry($data, 'plugins', 'acme--missing'));
        $this->assertNull($catalog->findEntry($data, 'themes', 'acme--one'));
        $this->assertNull($catalog->findEntry([], 'plugins', 'acme--one'));
    }

    public function testIsEnabledDefaultsToTrue(): void
    {
        if (defined('UNREPRESS_GITHUB_DISCOVERY')) {
            $this->markTestSkipped('Constant already defined in this process');
        }

        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with('unrepress_settings', [])
            ->andReturn([]);

        $this->assertTrue(GitHubCatalog::isEnabled());
    }

    public function testIsEnabledRespectsSavedToggle(): void
    {
        if (defined('UNREPRESS_GITHUB_DISCOVERY')) {
            $this->markTestSkipped('Constant already defined in this process');
        }

        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with('unrepress_settings', [])
            ->andReturn(['github_discovery' => false]);

        $this->assertFalse(GitHubCatalog::isEnabled());
    }

    public function testTrackStoresRecordUnderSlug(): void
    {
        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with('unrepress_tracked_repos', [])
            ->andReturn([]);

        \Brain\Monkey\Functions\expect('update_option')
            ->once()
            ->with(
                'unrepress_tracked_repos',
                \Mockery::on(function ($value) {
                    return 'acme--plugin' === array_key_first($value)
                        && 'github' === $value['acme--plugin']['provider']
                        && 'https://github.com/acme/plugin' === $value['acme--plugin']['repo_url'];
                }),
                false
            );

        \Brain\Monkey\Functions\when('gmdate')->justReturn('2026-10-01 00:00:00');

        $catalog = new GitHubCatalog();
        $catalog->track('acme--plugin', 'https://github.com/acme/plugin', '1.2.0');
    }

    public function testTrackRejectsEmptyCoordinates(): void
    {
        \Brain\Monkey\Functions\expect('update_option')->never();

        $catalog = new GitHubCatalog();
        $catalog->track('', 'https://github.com/acme/plugin', '1.0.0');
        $catalog->track('acme--plugin', '', '1.0.0');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('UNREPRESS_PLUGIN_URL')) {
            define('UNREPRESS_PLUGIN_URL', 'https://example.test/wp-content/plugins/unrepress/');
        }
        if (!defined('UNREPRESS_PREFIX')) {
            define('UNREPRESS_PREFIX', 'unrepress_');
        }

        GitHubCatalog::resetStarMap();
    }

    public function testSearchEntriesMatchesNameDescriptionTagsAndTopics(): void
    {
        $catalog = new GitHubCatalog();
        $data = [
            'plugins' => [
                [
                    'slug' => 'acme--one',
                    'name' => 'Super Cacher',
                    'description' => 'Speeds up your site',
                    'tags' => ['cache'],
                    'topics' => ['performance'],
                ],
                [
                    'slug' => 'acme--two',
                    'name' => ' unrelated',
                    'description' => 'Does something else',
                    'tags' => [],
                    'topics' => ['misc'],
                ],
            ],
        ];

        $hits = $catalog->searchEntries($data, 'plugins', 'cacher');

        $this->assertCount(1, $hits);
        $this->assertSame('acme--one', $hits[0]['slug']);

        // Topic matches count too
        $this->assertCount(1, $catalog->searchEntries($data, 'plugins', 'performance'));

        // Empty term matches nothing; other kinds stay isolated
        $this->assertSame([], $catalog->searchEntries($data, 'plugins', ''));
        $this->assertSame([], $catalog->searchEntries($data, 'themes', 'cacher'));
    }

    public function testPluginCardBuildsFullWpShape(): void
    {
        \Brain\Monkey\Functions\when('get_bloginfo')->justReturn('6.7');

        $catalog = new GitHubCatalog();
        $card = $catalog->pluginCard([
            'slug' => 'acme--plugin',
            'name' => 'Acme <b>Plugin</b>',
            'description' => 'A plugin <script>alert(1)</script>',
            'version' => '1.2.3',
            'requires' => '6.0',
            'requires_php' => '8.0',
            'download_url' => 'https://github.com/acme/plugin/releases/download/v1.2.3/plugin.zip',
            'homepage' => 'https://github.com/acme/plugin',
            'author' => '<a href="https://github.com/acme">acme</a>',
            'stars' => 1234,
            'pushed_at' => '2026-09-30T12:00:00Z',
            'build' => 'release-asset',
            'tags' => ['cache', 'speed'],
        ]);

        $this->assertSame('acme--plugin', $card['slug']);
        $this->assertSame('Acme Plugin', $card['name']);
        $this->assertStringNotContainsString('<script>', $card['sections']['description']);
        $this->assertSame('1.2.3', $card['version']);
        $this->assertSame('github', $card['source']);
        $this->assertSame(1234, $card['stars']);
        $this->assertSame(0, $card['active_installs']);
        $this->assertSame(0, $card['rating']);
        $this->assertSame('release-asset', $card['build']);
        $this->assertNotFalse(filter_var($card['download_url'], FILTER_VALIDATE_URL));
        $this->assertArrayHasKey('icons', $card);
        $this->assertArrayHasKey('banners', $card);

        // Card builders feed the badge map
        $this->assertSame(['acme--plugin' => 1234], GitHubCatalog::starMap());
    }

    public function testThemeCardMatchesCuratedShape(): void
    {
        $catalog = new GitHubCatalog();
        $card = $catalog->themeCard([
            'slug' => 'acme--theme',
            'name' => 'Acme Theme',
            'description' => 'Nice theme',
            'tags' => ['blog'],
            'stars' => 42,
            'homepage' => 'https://github.com/acme/theme',
            'author' => 'acme',
            'version' => '2.0.0',
        ]);

        // Curated contract: slug, name, description, tags
        $this->assertSame('acme--theme', $card['slug']);
        $this->assertSame('Acme Theme', $card['name']);
        $this->assertSame('Nice theme', $card['description']);
        $this->assertSame(['blog'], $card['tags']);
        $this->assertSame(42, $card['stars']);
        $this->assertSame('github', $card['source']);
        $this->assertSame(['acme--theme' => 42], GitHubCatalog::starMap());
    }

    public function testStarMapCollectsAcrossCardsAndResets(): void
    {
        $catalog = new GitHubCatalog();
        $catalog->themeCard(['slug' => 'a--one', 'name' => 'One', 'stars' => 5]);
        $catalog->themeCard(['slug' => 'a--two', 'name' => 'Two', 'stars' => 7]);

        $this->assertSame(['a--one' => 5, 'a--two' => 7], GitHubCatalog::starMap());

        GitHubCatalog::resetStarMap();

        $this->assertSame([], GitHubCatalog::starMap());
    }
}
