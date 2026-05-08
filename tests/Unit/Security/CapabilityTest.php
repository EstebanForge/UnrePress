<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Security;

use UnrePress\Security\CapabilityChecker;
use UnrePress\Tests\Helpers\WordPressTestHelper;

/**
 * CapabilityChecker Unit Tests.
 *
 * Tests for user capability validation to ensure proper authorization
 * for sensitive operations like updates, settings changes, and file operations.
 */
class CapabilityTest extends WordPressTestHelper
{
    private CapabilityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        // Define required constants
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }
        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', '/tmp/wordpress/wp-content');
        }

        // Mock WordPress capability functions
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);
        \Brain\Monkey\Functions\when('get_current_user_id')->justReturn(0);

        $this->checker = new CapabilityChecker();
    }

    public function test_user_can_update_plugins_requires_manage_options(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->checker->canUserUpdatePlugins();

        $this->assertFalse($result);
    }

    public function test_user_can_update_plugins_returns_true_for_admin(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->canUserUpdatePlugins();

        $this->assertTrue($result);
    }

    public function test_user_can_update_themes_requires_manage_options(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->checker->canUserUpdateThemes();

        $this->assertFalse($result);
    }

    public function test_user_can_update_themes_returns_true_for_admin(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->canUserUpdateThemes();

        $this->assertTrue($result);
    }

    public function test_user_can_update_core_requires_manage_options(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->checker->canUserUpdateCore();

        $this->assertFalse($result);
    }

    public function test_user_can_update_core_returns_true_for_admin(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->canUserUpdateCore();

        $this->assertTrue($result);
    }

    public function test_user_can_modify_settings_requires_manage_options(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->checker->canUserModifySettings();

        $this->assertFalse($result);
    }

    public function test_user_can_modify_settings_returns_true_for_admin(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->canUserModifySettings();

        $this->assertTrue($result);
    }

    public function test_user_can_access_admin_area_requires_admin(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->checker->canUserAccessAdminArea();

        $this->assertFalse($result);
    }

    public function test_user_can_access_admin_area_returns_true_for_admin(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->canUserAccessAdminArea();

        $this->assertTrue($result);
    }

    public function test_is_user_logged_in_returns_false_when_not_logged_in(): void
    {
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $result = $this->checker->isUserLoggedIn();

        $this->assertFalse($result);
    }

    public function test_is_user_logged_in_returns_true_when_logged_in(): void
    {
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);

        $result = $this->checker->isUserLoggedIn();

        $this->assertTrue($result);
    }

    public function test_get_user_id_returns_current_user_id(): void
    {
        \Brain\Monkey\Functions\when('get_current_user_id')->justReturn(123);

        $result = $this->checker->getUserId();

        $this->assertEquals(123, $result);
    }

    public function test_get_user_id_returns_zero_when_not_logged_in(): void
    {
        \Brain\Monkey\Functions\when('get_current_user_id')->justReturn(0);

        $result = $this->checker->getUserId();

        $this->assertEquals(0, $result);
    }

    public function test_user_has_capability_returns_false_for_insufficient_permissions(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->checker->userHasCapability('install_plugins');

        $this->assertFalse($result);
    }

    public function test_user_has_capability_returns_true_for_sufficient_permissions(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->userHasCapability('install_plugins');

        $this->assertTrue($result);
    }

    public function test_user_has_any_capability_returns_false_if_none_match(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $capabilities = ['install_plugins', 'update_plugins', 'delete_plugins'];
        $result = $this->checker->userHasAnyCapability($capabilities);

        $this->assertFalse($result);
    }

    public function test_user_has_any_capability_returns_true_if_one_matches(): void
    {
        $callCount = 0;
        \Brain\Monkey\Functions\when('current_user_can')->alias(function ($cap) use (&$callCount) {
            $callCount++;

            return $cap === 'update_plugins';
        });

        $capabilities = ['install_plugins', 'update_plugins', 'delete_plugins'];
        $result = $this->checker->userHasAnyCapability($capabilities);

        $this->assertTrue($result);
        $this->assertGreaterThan(0, $callCount);
    }

    public function test_user_has_all_capabilities_returns_false_if_one_missing(): void
    {
        $callCount = 0;
        \Brain\Monkey\Functions\when('current_user_can')->alias(function ($cap) use (&$callCount) {
            $callCount++;

            return $cap !== 'delete_plugins';
        });

        $capabilities = ['install_plugins', 'update_plugins', 'delete_plugins'];
        $result = $this->checker->userHasAllCapabilities($capabilities);

        $this->assertFalse($result);
    }

    public function test_user_has_all_capabilities_returns_true_if_all_present(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $capabilities = ['install_plugins', 'update_plugins', 'delete_plugins'];
        $result = $this->checker->userHasAllCapabilities($capabilities);

        $this->assertTrue($result);
    }

    public function test_require_capability_throws_when_insufficient_permissions(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User does not have the required capability: manage_options');

        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $this->checker->requireCapability('manage_options');
    }

    public function test_require_capability_does_not_throw_when_sufficient_permissions(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->requireCapability('manage_options');

        $this->assertTrue($result);
    }

    public function test_require_login_throws_when_not_logged_in(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User must be logged in to perform this action');

        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $this->checker->requireLogin();
    }

    public function test_require_login_does_not_throw_when_logged_in(): void
    {
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);

        $result = $this->checker->requireLogin();

        $this->assertTrue($result);
    }

    public function test_can_install_plugins_requires_specific_capability(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->checker->canUserInstallPlugins();

        $this->assertFalse($result);
    }

    public function test_can_install_plugins_returns_true_for_authorized_user(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->canUserInstallPlugins();

        $this->assertTrue($result);
    }

    public function test_can_delete_plugins_requires_specific_capability(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->checker->canUserDeletePlugins();

        $this->assertFalse($result);
    }

    public function test_can_delete_plugins_returns_true_for_authorized_user(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->canUserDeletePlugins();

        $this->assertTrue($result);
    }

    public function test_can_edit_plugins_requires_specific_capability(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->checker->canUserEditPlugins();

        $this->assertFalse($result);
    }

    public function test_can_edit_plugins_returns_true_for_authorized_user(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->canUserEditPlugins();

        $this->assertTrue($result);
    }

    public function test_validate_user_abilities_returns_false_for_insufficient_abilities(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $requiredAbilities = ['update_core', 'update_plugins', 'update_themes'];
        $result = $this->checker->validateUserAbilities($requiredAbilities);

        $this->assertFalse($result);
    }

    public function test_validate_user_abilities_returns_true_for_sufficient_abilities(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $requiredAbilities = ['update_core', 'update_plugins', 'update_themes'];
        $result = $this->checker->validateUserAbilities($requiredAbilities);

        $this->assertTrue($result);
    }

    public function test_can_manage_options_is_basic_admin_capability(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $result = $this->checker->canUserManageOptions();

        $this->assertFalse($result);
    }

    public function test_can_manage_options_returns_true_for_admin(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $result = $this->checker->canUserManageOptions();

        $this->assertTrue($result);
    }
}
