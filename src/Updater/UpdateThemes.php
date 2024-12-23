<?php

namespace UnrePress\Updater;

use UnrePress\Helpers;
use UnrePress\Debugger;

class UpdateThemes
{
    private $helpers;

    private $provider = 'github';

    public $version;

    public $cache_key;

    public $cache_results;

    private $updateInfo = [];

    public function __construct()
    {
        $this->helpers = new Helpers();
        $this->version = '';
        $this->cache_key = UNREPRESS_PREFIX . 'updates_theme_';
        $this->cache_results = true;

        // If force-check=1 and page=unrepress-updates, then empty all update transients
        if (isset($_GET['force-check']) && $_GET['force-check'] === '1' && isset($_GET['page']) && $_GET['page'] === 'unrepress-updater') {
            $this->deleteAllUpdateTransients();
        }

        $this->checkforUpdates();

        add_filter('themes_api', [$this, 'getInformation'], 20, 3);
        add_filter('site_transient_update_themes', [$this, 'hasUpdate']);
        add_action('upgrader_process_complete', [$this, 'cleanAfterUpdate'], 10, 2);
        add_filter('upgrader_source_selection', [$this, 'maybeFixSourceDir'], 10, 4);
    }

    /**
     * Check for theme updates
     * This method will check for updates on every installed theme.
     *
     * @return void
     */
    private function checkforUpdates()
    {
        $themes = wp_get_themes();

        foreach ($themes as $slug => $theme) {
            $this->checkForThemeUpdate($slug);
        }
    }

    private function checkForThemeUpdate($slug)
    {
        $remoteData = $this->requestRemoteInfo($slug);

        // If we can't get theme info, skip this theme
        if (!$remoteData) {
            return;
        }

        $installedVersion = $this->getInstalledVersion($slug);
        $latestVersion = $this->getRemoteVersion($slug);

        if ($remoteData && $installedVersion && $latestVersion) {
            if (version_compare($installedVersion, $latestVersion, '<')) {
                $updateInfo = new \stdClass();

                $theme = wp_get_theme($slug);

                // Get data from the remote source
                $updateInfo->requires = $remoteData->requires ?? '';
                $updateInfo->tested = $remoteData->tested ?? '';
                $updateInfo->requires_php = $remoteData->requires_php ?? '';

                // Get data from the local theme object
                $updateInfo->name = $theme->get('Name');
                $updateInfo->theme_uri = $theme->get('ThemeURI');
                $updateInfo->description = $theme->get('Description');
                $updateInfo->author = $theme->get('Author');
                $updateInfo->author_uri = $theme->get('AuthorURI');
                $updateInfo->tags = $theme->get('Tags');
                $updateInfo->textdomain = $theme->get('TextDomain');
                $updateInfo->template = $theme->get_template();

                // Remote data for updates
                $updateInfo->last_updated = $remoteData->last_updated ?? time();
                $updateInfo->changelog = isset($remoteData->sections->changelog) ? $remoteData->sections->changelog : '';
                $updateInfo->screenshot = $remoteData->screenshot_url ?? '';

                $updateInfo->version = $latestVersion;
                $updateInfo->download_link = get_transient($this->cache_key . 'download-url-' . $slug);

                // Store this information for later use
                $this->updateInfo[$slug] = $updateInfo;
            }
        }
    }

    private function getInstalledVersion($slug)
    {
        $theme = wp_get_theme($slug);
        $version = $theme->get('Version');

        return ! empty($version) ? $version : false;
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
                UNREPRESS_INDEX . 'themes/' . $first_letter . '/' . $slug . '.json',
                [
                    'timeout' => 10,
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
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
        // do nothing if you're not getting theme information right now
        if ($action !== 'theme_information') {
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

        // Last updated, now
        $remote->last_updated = time();
        // Changelog
        $remote->sections->changelog = $remote->changelog;

        $response->name = $remote->name;
        $response->slug = $remote->slug;
        $response->version = $remote->version;
        $response->tested = $remote->tested;
        $response->requires = $remote->requires;
        $response->author = $remote->author;
        $response->author_profile = $remote->author_profile;
        $response->donate_link = $remote->donate_link;
        $response->homepage = $remote->homepage;
        $response->download_link = $remote->download_url;
        $response->trunk = $remote->download_url;
        $response->requires_php = $remote->requires_php;
        $response->last_updated = $remote->last_updated;
        $response->screenshot_url = $remote->screenshot_url;

        $response->sections = [
            'description' => $remote->sections->description,
            'installation' => $remote->sections->installation,
            'changelog' => $remote->sections->changelog,
        ];

        return $response;
    }

    public function hasUpdate($transient)
    {
        if (! is_object($transient)) {
            $transient = new \stdClass();
        }

        if (empty($transient->checked)) {
            $transient->checked = [];
            // Get all themes and their versions
            $themes = wp_get_themes();
            foreach ($themes as $slug => $theme) {
                $transient->checked[$slug] = $theme->get('Version');
            }
        }

        foreach ($this->updateInfo as $slug => $updateInfo) {
            if (isset($transient->checked[$slug])) {
                $currentVersion = $transient->checked[$slug];

                if (
                    ! empty($currentVersion) && ! empty($updateInfo->version) &&
                    version_compare($currentVersion, $updateInfo->version, '<')
                ) {
                    if (! isset($transient->response)) {
                        $transient->response = [];
                    }

                    // Format response according to WordPress theme update structure
                    $transient->response[$slug] = [
                        'theme' => $slug,
                        'new_version' => $updateInfo->version,
                        'url' => $updateInfo->theme_uri ?? '',
                        'package' => $updateInfo->download_link,
                        'requires' => $updateInfo->requires ?? '',
                        'requires_php' => $updateInfo->requires_php ?? '',
                    ];
                }
            }
        }

        return $transient;
    }

    public function cleanAfterUpdate($upgrader, $options)
    {
        if ($this->cache_results && $options['action'] === 'update' && $options['type'] === 'theme') {
            // Get the updated theme slug
            $slug = $options['themes'][0] ?? '';

            if (empty($slug)) {
                Debugger::log("No theme slug found in update options");
                return;
            }

            Debugger::log("Cleaning up after theme update for {$slug}");

            // Clean the cache for this theme
            delete_transient($this->cache_key . $slug);
            delete_transient($this->cache_key . 'remote-version-' . $slug);
            delete_transient($this->cache_key . 'download-url-' . $slug);

            // Clean up the upgrade and backup directories
            //$this->helpers->cleanDirectory(WP_CONTENT_DIR . '/upgrade');
            //$this->helpers->cleanDirectory(WP_CONTENT_DIR . '/upgrade-temp-backup');

            Debugger::log("Cleanup complete for theme {$slug}");
        }
    }

    /**
     * Get the latest available version from the remote tags
     *
     * @param string $slug Theme slug
     *
     * @return string|false Version string or false on failure
     */
    private function getRemoteVersion($slug)
    {
        $remoteVersion = get_transient($this->cache_key . 'remote-version-' . $slug);

        if ($remoteVersion === false || !$this->cache_results) {
            $first_letter = mb_strtolower(mb_substr($slug, 0, 1));

            // Get theme info from UnrePress index
            $remote = wp_remote_get(UNREPRESS_INDEX . 'themes/' . $first_letter . '/' . $slug . '.json', [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($remote)) {
                return false;
            }

            if (200 !== wp_remote_retrieve_response_code($remote)) {
                return false;
            }

            if (empty(wp_remote_retrieve_body($remote))) {
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($remote));

            if (is_wp_error($body)) {
                return false;
            }

            $tagUrl = $body->tags ?? '';

            if (empty($tagUrl)) {
                return false;
            }

            // Normalize tag URL
            $tagUrl = $this->helpers->normalizeTagUrl($tagUrl);

            // Get tag information
            $remote = wp_remote_get($tagUrl, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($remote)) {
                return false;
            }

            if (200 !== wp_remote_retrieve_response_code($remote)) {
                return false;
            }

            if (empty(wp_remote_retrieve_body($remote))) {
                return false;
            }

            $tagBody = json_decode(wp_remote_retrieve_body($remote));

            if (! is_array($tagBody) || empty($tagBody)) {
                return false;
            }

            // Get the newest version from tags
            $latestTag = $this->helpers->getNewestVersionFromTags($tagBody);

            if (! $latestTag) {
                return false;
            }

            $remoteVersion = $latestTag->name;
            $remoteZip = $latestTag->zipball_url;

            // Store version and download information
            if ($remoteVersion) {
                // Clean version number (remove 'v' prefix if present)
                $remoteVersion = ltrim($remoteVersion, 'v');

                set_transient($this->cache_key . 'download-url-' . $slug, $remoteZip, DAY_IN_SECONDS);
                set_transient($this->cache_key . 'remote-version-' . $slug, $remoteVersion, DAY_IN_SECONDS);
            } else {
                return false;
            }
        }

        return $remoteVersion;
    }

    /**
     * Deletes all transients used by the updates API.
     *
     * All transients used by the updates API have a name that begins with
     * UNREPRESS_PREFIX . 'updates_theme'. This method will delete all
     * transients with names that match this pattern.
     *
     * @since 1.0.0
     */
    private function deleteAllUpdateTransients()
    {
        global $wpdb;

        // Delete both transients and their timeout entries
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
                OR option_name LIKE %s",
                '_transient_' . $this->cache_key . '%',
                '_transient_timeout_' . $this->cache_key . '%'
            )
        );
    }

    /**
     * Fix source directory for GitHub theme updates
     */
    public function maybeFixSourceDir($source, $remote_source, $upgrader, $args)
    {
        if (! isset($args['theme'])) {
            Debugger::log("Theme not found in args");
            return $source;
        }

        Debugger::log("Fixing theme source directory");
        Debugger::log("Source: {$source}");
        Debugger::log("Remote source: {$remote_source}");
        Debugger::log("Theme: {$args['theme']}");

        $result = Helpers::fixSourceDir($source, $remote_source, $args['theme'], 'theme');

        Debugger::log("New source directory: {$result}");
        return $result;
    }
}
