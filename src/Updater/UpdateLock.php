<?php

namespace UnrePress\Updater;

use UnrePress\Helpers;

class UpdateLock
{
    private $helpers;

    /**
     * Constructor. Acquires the update lock and schedules it to be cleaned up if
     * it has expired.
     */
    public function __construct()
    {
        $this->helpers = new Helpers();

        // Register schedule to clean up the update lock if it has expired
        $this->scheduleCleanup();
    }

    /**
     * Acquire the update lock.
     *
     * This method is used to prevent multiple updates to run at the same time.
     * It will set a lock option and its expiration time.
     *
     * @return void
     */
    public function lock(): void
    {
        if ($this->isLocked()) {
            return;
        }

        update_option(UNREPRESS_PREFIX . 'update_lock', true);
        $this->setLockTime();

        return;
    }

    /**
     * Delete the update lock option and its expiration time.
     *
     * The lock is used to prevent multiple updates to run at the same time.
     *
     * @return void
     */
    public function unlock(): void
    {
        delete_option(UNREPRESS_PREFIX . 'update_lock');
        delete_option(UNREPRESS_PREFIX . 'update_lock_time');

        // Clear update log
        //$this->helpers->clearUpdateLog(); // We don't want to clear the update log here, or else the user won't see the update log when core update is done.

        return;
    }

    /**
     * Checks if the update lock is currently active.
     *
     * @return bool Whether the update lock is active.
     */
    public function isLocked(): bool
    {
        // Check if hasLockTimeExpired() returns true
        if ($this->hasLockTimeExpired()) {
            $this->unlock();

            return false;
        }

        return get_option(UNREPRESS_PREFIX . 'update_lock', false);
    }

    /**
     * Returns the timestamp of the last time the update lock was set.
     *
     * @return int The timestamp of the last time the update lock was set.
     */
    public function getLockTime(): int
    {
        return get_option(UNREPRESS_PREFIX . 'update_lock_time', 0);
    }

    /**
     * Set the timestamp of the last time the update lock was set.
     *
     * @return void
     */
    public function setLockTime(): void
    {
        update_option(UNREPRESS_PREFIX . 'update_lock_time', time());
    }

    /**
     * Checks if the update lock has expired.
     *
     * The update lock is considered expired if the time elapsed since the lock was set
     * is greater than 10 minutes.
     *
     * @return bool Whether the update lock has expired.
     */
    public function hasLockTimeExpired(): bool
    {
        $lockTime = $this->getLockTime();
        $currentTime = time();

        // Check if the lock has expired = 10 minutes
        return ($currentTime - $lockTime) > 600;
    }

    /**
     * Reset the update lock timestamp to 0.
     *
     * This will be used when the update process is finished, so that the next
     * scheduled update can run.
     *
     * @return void
     */
    public function resetLockTime(): void
    {
        delete_option(UNREPRESS_PREFIX . 'update_lock_time');
    }

    /**
     * Set a schedule to clean up the update lock if it has expired
     *
     * @return void
     */
    public function scheduleCleanup(): void
    {
        if (! wp_next_scheduled(UNREPRESS_PREFIX . 'cleanup_lock')) {
            wp_schedule_event(time(), 'hourly', UNREPRESS_PREFIX . 'cleanup_lock');
        }

        add_action(UNREPRESS_PREFIX . 'cleanup_lock', [$this, 'cleanup']);
    }

    /**
     * Clean up the update lock if it has expired
     *
     * @return void
     */
    public function cleanup(): void
    {
        if ($this->hasLockTimeExpired()) {
            $this->unlock();
            $this->resetLockTime();
        }
    }
}
