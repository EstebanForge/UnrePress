<?php

namespace UnrePress;

class UnrePress
{
    public function __construct()
    {
    }

    public function run(): void
    {
        $egoBlocker = new EgoBlocker();

        // Stop on frontend or rest api
        if (! is_admin() || wp_is_serving_rest_request()) {
            return;
        }

        $adminHider = new Admin\Hider();
        $adminUpdaterPages = new Admin\UpdaterPages();
        $updateLock = new Updater\UpdateLock();
        $index = new Index\Index();
        $indexPlugins = new Index\PluginsIndex();
        $indexThemes = new Index\ThemesIndex();

        $updatePlugin = new Updater\UpdatePlugin();
    }
}
