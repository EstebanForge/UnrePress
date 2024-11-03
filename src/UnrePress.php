<?php

namespace UnrePress;

class UnrePress
{
    public function __construct()
    {
    }

    public function run(): void
    {
        // Stop on frontend or rest api
        if (! is_admin() || wp_is_serving_rest_request()) {
            return;
        }

        $egoBlocker = new EgoBlocker();
        $adminHider = new Admin\Hider();
        $adminUpdaterPages = new Admin\UpdaterPages();
        $updateLock = new Updater\UpdateLock();
    }
}
