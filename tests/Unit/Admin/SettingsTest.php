<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Admin;

use UnrePress\Admin\Settings;
use UnrePress\Tests\Helpers\WordPressTestHelper;

/**
 * Settings unit tests: token resolution precedence and sanitization.
 */
class SettingsTest extends WordPressTestHelper
{
    public function testRejectsUnknownProviders(): void
    {
        $this->assertNull(Settings::getProviderToken('gitea'));
        $this->assertNull(Settings::getProviderToken(''));
    }

    public function testResolvesTokenFromSavedOption(): void
    {
        if (defined('UNREPRESS_TOKEN_GITHUB')) {
            $this->markTestSkipped('Constant already defined in this process');
        }

        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_KEY, [])
            ->andReturn(['github_token' => 'ghp_optionstoredtoken']);

        $this->assertSame('ghp_optionstoredtoken', Settings::getProviderToken('github'));
    }

    public function testFallsThroughToFilterWhenOptionEmpty(): void
    {
        if (defined('UNREPRESS_TOKEN_GITHUB')) {
            $this->markTestSkipped('Constant already defined in this process');
        }

        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_KEY, [])
            ->andReturn(['github_token' => '']);

        \Brain\Monkey\Functions\expect('apply_filters')
            ->once()
            ->with('unrepress_github_token', '')
            ->andReturn('ghp_filteredtoken');

        $this->assertSame('ghp_filteredtoken', Settings::getProviderToken('GITHUB'));
    }

    public function testReturnsNullWhenNothingSet(): void
    {
        if (defined('UNREPRESS_TOKEN_GITLAB')) {
            $this->markTestSkipped('Constant already defined in this process');
        }

        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_KEY, [])
            ->andReturn([]);

        \Brain\Monkey\Functions\expect('apply_filters')
            ->once()
            ->with('unrepress_gitlab_token', '')
            ->andReturn('');

        $this->assertNull(Settings::getProviderToken('gitlab'));
    }

    public function testSanitizeKeepsValidToken(): void
    {
        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_KEY, [])
            ->andReturn([]);

        $settings = new Settings(false);
        $clean = $settings->sanitizeSettings([
            'github_token' => 'ghp_validtoken123',
            'github_discovery' => '1',
        ]);

        $this->assertSame('ghp_validtoken123', $clean['github_token']);
        $this->assertTrue($clean['github_discovery']);
    }

    public function testSanitizeDropsMalformedTokens(): void
    {
        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_KEY, [])
            ->andReturn(['github_token' => 'keepme12345']);

        $settings = new Settings(false);
        $clean = $settings->sanitizeSettings([
            'github_token' => 'bad token with spaces',
            'gitlab_token' => 'x',
            'bitbucket_token' => '<script>alert(1)</script>',
        ]);

        // Invalid input keeps the previously stored value
        $this->assertSame('keepme12345', $clean['github_token']);
        $this->assertArrayNotHasKey('gitlab_token', $clean);
        $this->assertArrayNotHasKey('bitbucket_token', $clean);
    }

    public function testSanitizeEmptyKeepsStoredToken(): void
    {
        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_KEY, [])
            ->andReturn(['github_token' => 'oldstoredtoken']);

        $settings = new Settings(false);
        $clean = $settings->sanitizeSettings(['github_token' => '']);

        $this->assertSame('oldstoredtoken', $clean['github_token']);
    }

    public function testSanitizeClearCheckboxRemovesToken(): void
    {
        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_KEY, [])
            ->andReturn(['github_token' => 'oldstoredtoken']);

        $settings = new Settings(false);
        $clean = $settings->sanitizeSettings([
            'github_token' => '',
            'github_token_clear' => '1',
        ]);

        $this->assertSame('', $clean['github_token']);
    }

    public function testSanitizeTreatsNonArrayInputAsNoop(): void
    {
        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_KEY, [])
            ->andReturn(['github_discovery' => true]);

        $settings = new Settings(false);
        $clean = $settings->sanitizeSettings('nonsense');

        $this->assertTrue($clean['github_discovery']);
    }

    public function testMaskTokenShowsOnlyTail(): void
    {
        $this->assertSame('', Settings::maskToken(null));
        $this->assertSame('', Settings::maskToken(''));
        $masked = Settings::maskToken('ghp_abcdwxyz');

        $this->assertSame('********wxyz', $masked);
        $this->assertStringNotContainsString('abcd', $masked);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('UNREPRESS_PLUGIN_PATH')) {
            define('UNREPRESS_PLUGIN_PATH', '/tmp/wordpress/wp-content/plugins/unrepress/');
        }
        if (!defined('UNREPRESS_PREFIX')) {
            define('UNREPRESS_PREFIX', 'unrepress_');
        }

        // No-op the constructor hooks for direct method tests
        \Brain\Monkey\Functions\when('add_action')->justReturn(true);
    }
}
