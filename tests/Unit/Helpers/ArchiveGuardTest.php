<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Helpers;

use UnrePress\Helpers;
use UnrePress\Tests\Helpers\WordPressTestHelper;

/**
 * Archive contents guard tests: symlinks, dangerous files, size ceiling.
 */
class ArchiveGuardTest extends WordPressTestHelper
{
    public function testAcceptsACleanArchive(): void
    {
        $listing = [
            'plugin.php' => ['type' => 'f', 'size' => 2048],
            'readme.txt' => ['type' => 'f', 'size' => 512],
            'includes' => [
                'type' => 'd',
                'size' => 0,
                'files' => [
                    'helpers.php' => ['type' => 'f', 'size' => 4096],
                ],
            ],
        ];

        $this->assertNull(Helpers::checkArchiveContents('/tmp/src/', $listing, 1024 * 1024));
    }

    public function testRejectsSymlinks(): void
    {
        $listing = [
            'evil' => ['type' => 's', 'size' => 11],
            'plugin.php' => ['type' => 'f', 'size' => 2048],
        ];

        $error = Helpers::checkArchiveContents('/tmp/src/', $listing, 1024 * 1024);

        $this->assertInstanceOf(\WP_Error::class, $error);
        $this->assertSame('unrepress_archive_symlink', $error->get_error_code());
    }

    public function testRejectsNestedSymlinks(): void
    {
        $listing = [
            'vendor' => [
                'type' => 'd',
                'size' => 0,
                'files' => [
                    'bin' => ['type' => 's', 'size' => 7],
                ],
            ],
            'plugin.php' => ['type' => 'f', 'size' => 2048],
        ];

        $error = Helpers::checkArchiveContents('/tmp/src/', $listing, 1024 * 1024);

        $this->assertInstanceOf(\WP_Error::class, $error);
        $this->assertSame('unrepress_archive_symlink', $error->get_error_code());
    }

    public function testRejectsDangerousRootFiles(): void
    {
        foreach (['wp-config.php', '.htaccess', 'php.ini', 'WP-CONFIG.PHP'] as $name) {
            $listing = [
                $name => ['type' => 'f', 'size' => 100],
                'plugin.php' => ['type' => 'f', 'size' => 2048],
            ];

            $error = Helpers::checkArchiveContents('/tmp/src/', $listing, 1024 * 1024);

            $this->assertInstanceOf(\WP_Error::class, $error, "Failed for {$name}");
            $this->assertSame('unrepress_archive_dangerous_file', $error->get_error_code());
        }
    }

    public function testRejectsDangerousFilesInSubdirectories(): void
    {
        $listing = [
            'inc' => [
                'type' => 'd',
                'size' => 0,
                'files' => [
                    'php.ini' => ['type' => 'f', 'size' => 100],
                ],
            ],
            'plugin.php' => ['type' => 'f', 'size' => 2048],
        ];

        $error = Helpers::checkArchiveContents('/tmp/src/', $listing, 1024 * 1024);

        $this->assertInstanceOf(\WP_Error::class, $error);
        $this->assertSame('unrepress_archive_dangerous_file', $error->get_error_code());
    }

    public function testRejectsTreesAboveTheSizeCeiling(): void
    {
        $listing = [
            'plugin.php' => ['type' => 'f', 'size' => 600],
            'assets' => [
                'type' => 'd',
                'size' => 0,
                'files' => [
                    'logo.png' => ['type' => 'f', 'size' => 700],
                ],
            ],
        ];

        $error = Helpers::checkArchiveContents('/tmp/src/', $listing, 1000);

        $this->assertInstanceOf(\WP_Error::class, $error);
        $this->assertSame('unrepress_archive_too_large', $error->get_error_code());
    }

    public function testAcceptsTreesUnderTheCeiling(): void
    {
        $listing = [
            'plugin.php' => ['type' => 'f', 'size' => 600],
        ];

        $this->assertNull(Helpers::checkArchiveContents('/tmp/src/', $listing, 1000));
    }

    public function testToleratesNonArrayListing(): void
    {
        $this->assertNull(Helpers::checkArchiveContents('/tmp/src/', null, 1000));
    }
}
