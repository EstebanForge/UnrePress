<?php

namespace UnrePress\Updater;

use UnrePress\Helpers;
use UnrePress\UpdaterProvider\GitHub;

class UpdatePlugin
{
    private $helpers;
    private $provider = 'github';
    public $version;
    public $cache_key;
    public $cache_results;

    public function __construct()
    {
        $this->helpers       = new Helpers();
        $this->version       = '';
        $this->cache_key     = UNREPRESS_PREFIX . 'updates_plugin_';
        $this->cache_results = true;

        add_filter('plugins_api', [ $this, 'getInformation' ], 20, 3);
        add_filter('site_transient_update_plugins', [ $this, 'hasUpdate' ]);
        add_action('upgrader_process_complete', [ $this, 'cleanAfterUpdate' ], 10, 2);
    }

    public function requestRemoteInfo($slug = null)
    {
        if (! $slug) {
            return false;
        }

        $remote = get_transient($this->cache_key . $slug);

        // Get the first letter of the slug
        $first_letter = mb_strtolower(mb_substr($slug, 0, 1));

        if ($remote === false || ! $this->cache_results) {

            $remote = wp_remote_get(
                UNREPRESS_INDEX . 'plugins/' . $first_letter . '/' . $slug . '.json',
                [
                    'timeout' => 10,
                    'headers' => [
                        'Accept' => 'application/json'
                    ]
                ]
            );

            if (is_wp_error($remote) || 200 !== wp_remote_retrieve_response_code($remote) || empty(wp_remote_retrieve_body($remote))) {
                return false;
            }

            set_transient($this->cache_key . $slug, $remote, DAY_IN_SECONDS);
        }

        $remote = json_decode(wp_remote_retrieve_body($remote));

        return $remote;
    }

    public function getInformation($response, $action, $args)
    {
        // do nothing if you're not getting plugin information right now
        if ($action !== 'plugin_information') {
            return $response;
        }

        if (empty($args->slug)) {
            return $response;
        }

        // get updates
        $remote = $this->requestRemoteInfo($args->slug);

        if (! $remote) {
            return $response;
        }

        $response = new \stdClass();

        $response->name           = $remote->name;
        $response->slug           = $remote->slug;
        $response->version        = $remote->version;
        $response->tested         = $remote->tested;
        $response->requires       = $remote->requires;
        $response->author         = $remote->author;
        $response->author_profile = $remote->author_profile;
        $response->donate_link    = $remote->donate_link;
        $response->homepage       = $remote->homepage;
        $response->download_link  = $remote->download_url;
        $response->trunk          = $remote->download_url;
        $response->requires_php   = $remote->requires_php;
        $response->last_updated   = $remote->last_updated;

        $response->sections = [
            'description'  => $remote->sections->description,
            'installation' => $remote->sections->installation,
            'changelog'    => $remote->sections->changelog
        ];

        if (! empty($remote->banners)) {
            $response->banners = [
                'low'  => $remote->banners->low,
                'high' => $remote->banners->high
            ];
        }

        return $response;

    }

    public function hasUpdate($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get the plugin being updated slug
        $slug = $transient->checked[0];

        $remoteData = $this->requestRemoteInfo($slug);

        if ($remoteData && version_compare($this->version, $remoteData->version, '<') && version_compare($remoteData->requires, get_bloginfo('version'), '<=') && version_compare($remoteData->requires_php, PHP_VERSION, '<')) {
            $response              = new \stdClass();
            $response->slug        = $slug;
            $response->plugin      = "{$slug}/{$slug}.php";
            $response->tested      = $remoteData->tested;
            $response->package     = $remoteData->download_url;
            $response->new_version = $this->getRemoteVersion($slug, $remoteData->tags);

            $transient->response[ $response->plugin ] = $response;

        }

        return $transient;
    }

    public function cleanAfterUpdate($upgrader, $options)
    {
        if ($this->cache_results && $options['action'] === 'update' && $options[ 'type' ] === 'plugin') {
            // Get the updated plugin slug
            $slug = $options['plugins'][0];

            // Clean the cache for this plugin
            delete_transient($this->cache_key . $slug);
        }
    }

    /**
     * Get the latest available version from the remote tags
     *
     * @param string $slug Plugin slug
     * @param string $tagUrl URL for the remote tags
     *
     * @return string
     */
    private function getRemoteVersion($slug, $tagUrl) {
        $remote = get_transient($this->cache_key . 'remote-version-' . $slug);

        if ($remote === false) {
            $remote = wp_remote_get($tagUrl, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);

            if (is_wp_error($remote) || 200 !== wp_remote_retrieve_response_code($remote) || empty(wp_remote_retrieve_body($remote))) {
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($remote));

            // Just retrieve the first tag if there are multiple and we are using GitHub
            $remote = $body[0]->name;

            set_transient($this->cache_key . 'remote-version-' . $slug, $remote, DAY_IN_SECONDS);
        }

        return $remote;
    }
}
