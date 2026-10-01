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
}
