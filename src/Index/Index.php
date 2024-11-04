<?php

namespace UnrePress\Index;

class Index
{
    public function __construct()
    {
    }

    /**
     * Retrieve the main index json from GitHub
     */
    public function getRootIndex()
    {
        $index = get_transient(UNREPRESS_PREFIX . 'index');

        if (false === $index) {
            $indexJson = wp_remote_get(UNREPRESS_INDEX);

            if (is_wp_error($indexJson)) {
                return false;
            }

            $index = json_decode($indexJson['body'], true);

            set_transient(UNREPRESS_PREFIX . 'index', $index, DAY_IN_SECONDS);
        }

        return $index;
    }

    /**
     * Generate the URL for the given slug and type
     *
     * @param string $slug The slug of the plugin or theme
     * @param string $type The type of index, either 'plugin' or 'theme'
     *
     * @return string The full URL to the index
     */
    protected function getUrlForSlug($slug = '', $type = 'plugin')
    {
        if (empty($slug)) {
            return false;
        }

        $slug = $this->normalizeSlug($slug);

        return UNREPRESS_INDEX . 'main/' . $type . '/' . $slug . '.json';
    }

    /**
     * Normalize a slug to a valid filename
     *
     * @param string $slug The slug to normalize
     *
     * @return string|false The normalized slug, or false if empty
     */
    protected function normalizeSlug($slug = '')
    {
        if (empty($slug)) {
            return false;
        }
        $slug = sanitize_title($slug);

        return $slug;
    }
}
