<?php

namespace UnrePress\Index;

use UnrePress\Helpers;
use UnrePress\Updater\UpdateLock;

class ThemesIndex extends Index
{
    private $helpers;
    private $updateLock;
    private $provider = 'github';

    public function __construct()
    {
        $this->helpers    = new Helpers();
        $this->updateLock = new UpdateLock();
    }

    /**
     * Get a theme's JSON file from UnrePress index
     *
     * @var string $themeSlug
     *
     * @return array|false
     */
    public function getThemeJson($themeSlug)
    {
        $transientName = UNREPRESS_PREFIX . 'index_theme_' . $themeSlug;
        $themeJson     = get_transient($transientName);

        if (false === $themeJson) {
            $themeJsonUrl = $this->getUrlForSlug($themeSlug, 'theme');
            $themeJson    = wp_remote_get($themeJsonUrl);

            if (is_wp_error($themeJson)) {
                return false;
            }

            $themeJson    = json_decode($themeJson['body'], true);

            set_transient($transientName, $themeJson, DAY_IN_SECONDS);
        }

        return $themeJson;
    }
}
