<?php

namespace UnrePress\Index;

use UnrePress\Helpers;
use UnrePress\Updater\UpdateLock;

class PluginsIndex extends Index
{
    private $helpers;

    private $updateLock;

    private $provider = 'github';

    public function __construct()
    {
        $this->helpers = new Helpers();
        $this->updateLock = new UpdateLock();
    }

    /**
     * Get a plugin's JSON file from UnrePress index
     *
     * @var string
     *
     * @return array|false
     */
    public function getPluginJson($pluginSlug)
    {
        $transientName = UNREPRESS_PREFIX . 'index_plugin_' . $pluginSlug;
        $pluginJson = get_transient($transientName);

        if (false === $pluginJson) {
            $pluginJsonUrl = $this->getUrlForSlug($pluginSlug);
            $pluginJson = wp_remote_get($pluginJsonUrl);

            if (is_wp_error($pluginJson)) {
                return false;
            }

            $pluginJson = json_decode($pluginJson['body'], true);

            set_transient($transientName, $pluginJson, DAY_IN_SECONDS);
        }

        return $pluginJson;
    }
}
