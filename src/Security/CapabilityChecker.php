<?php

declare(strict_types=1);

namespace UnrePress\Security;

use Exception;

/**
 * Capability Checker.
 *
 * Provides centralized capability validation to ensure proper authorization
 * for sensitive operations. All capability checks should go through this class
 * to maintain consistent security standards.
 */
class CapabilityChecker
{
    /**
     * Check if current user can update plugins.
     *
     * @return bool True if user can update plugins, false otherwise.
     */
    public function canUserUpdatePlugins(): bool
    {
        return $this->userHasCapability('update_plugins');
    }

    /**
     * Check if current user can update themes.
     *
     * @return bool True if user can update themes, false otherwise.
     */
    public function canUserUpdateThemes(): bool
    {
        return $this->userHasCapability('update_themes');
    }

    /**
     * Check if current user can update WordPress core.
     *
     * @return bool True if user can update core, false otherwise.
     */
    public function canUserUpdateCore(): bool
    {
        return $this->userHasCapability('update_core');
    }

    /**
     * Check if current user can modify plugin settings.
     *
     * @return bool True if user can modify settings, false otherwise.
     */
    public function canUserModifySettings(): bool
    {
        return $this->userHasCapability('manage_options');
    }

    /**
     * Check if current user can access the admin area.
     *
     * @return bool True if user can access admin, false otherwise.
     */
    public function canUserAccessAdminArea(): bool
    {
        return $this->userHasCapability('manage_options');
    }

    /**
     * Check if user is logged in.
     *
     * @return bool True if user is logged in, false otherwise.
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user ID.
     *
     * @return int User ID or 0 if not logged in.
     */
    public function getUserId(): int
    {
        return get_current_user_id();
    }

    /**
     * Check if current user has a specific capability.
     *
     * @param string $capability The capability to check.
     *
     * @return bool True if user has capability, false otherwise.
     */
    public function userHasCapability(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Check if current user has any of the specified capabilities.
     *
     * @param array $capabilities Array of capabilities to check.
     *
     * @return bool True if user has at least one capability, false otherwise.
     */
    public function userHasAnyCapability(array $capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if ($this->userHasCapability($capability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current user has all of the specified capabilities.
     *
     * @param array $capabilities Array of capabilities to check.
     *
     * @return bool True if user has all capabilities, false otherwise.
     */
    public function userHasAllCapabilities(array $capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if (!$this->userHasCapability($capability)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Require user to have a specific capability or throw exception.
     *
     * @param string $capability The required capability.
     *
     * @return bool True if user has capability.
     *
     * @throws Exception If user lacks required capability.
     */
    public function requireCapability(string $capability): bool
    {
        if (!$this->userHasCapability($capability)) {
            throw new \Exception(
                sprintf('User does not have the required capability: %s', $capability)
            );
        }

        return true;
    }

    /**
     * Require user to be logged in or throw exception.
     *
     * @return bool True if user is logged in.
     *
     * @throws Exception If user is not logged in.
     */
    public function requireLogin(): bool
    {
        if (!$this->isUserLoggedIn()) {
            throw new \Exception('User must be logged in to perform this action');
        }

        return true;
    }

    /**
     * Check if current user can install plugins.
     *
     * @return bool True if user can install plugins, false otherwise.
     */
    public function canUserInstallPlugins(): bool
    {
        return $this->userHasCapability('install_plugins');
    }

    /**
     * Check if current user can delete plugins.
     *
     * @return bool True if user can delete plugins, false otherwise.
     */
    public function canUserDeletePlugins(): bool
    {
        return $this->userHasCapability('delete_plugins');
    }

    /**
     * Check if current user can edit plugins.
     *
     * @return bool True if user can edit plugins, false otherwise.
     */
    public function canUserEditPlugins(): bool
    {
        return $this->userHasCapability('edit_plugins');
    }

    /**
     * Validate that user has all required abilities for an operation.
     *
     * @param array $requiredAbilities Array of required capabilities/abilities.
     *
     * @return bool True if user has all required abilities, false otherwise.
     */
    public function validateUserAbilities(array $requiredAbilities): bool
    {
        return $this->userHasAllCapabilities($requiredAbilities);
    }

    /**
     * Check if current user can manage options (basic admin capability).
     *
     * @return bool True if user can manage options, false otherwise.
     */
    public function canUserManageOptions(): bool
    {
        return $this->userHasCapability('manage_options');
    }
}
