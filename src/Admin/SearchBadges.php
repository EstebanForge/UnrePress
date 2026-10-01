<?php

/**
 * Search badge renderer.
 *
 * Plugin install results render server-side, so the slug-to-stars map is
 * printed inline. Theme results load through admin-ajax (Backbone), so the
 * script also watches jQuery ajaxSuccess and badges cards from the stars
 * field the AJAX response now carries. Data flows only through
 * wp_json_encode with HEX flags and DOM textContent, so catalog strings
 * can never inject markup.
 */

declare(strict_types=1);

namespace UnrePress\Admin;

use UnrePress\Index\GitHubCatalog;

// No direct access
defined('ABSPATH') or die();

class SearchBadges
{
    /**
     * Register the footer print hook for both install screens.
     */
    public function __construct()
    {
        add_action('admin_print_footer_scripts-plugin-install.php', [$this, 'printBadgeScript']);
        add_action('admin_print_footer_scripts-theme-install.php', [$this, 'printBadgeScript']);
    }

    /**
 * Print the badge injector with the full catalog star maps. Plugin cards
 * render server-side and badge immediately; theme cards render through
 * Backbone without jQuery, so a MutationObserver badges whatever appears.
 * Injection uses textContent and CSS escaping only, so catalog strings
 * can never inject markup.
 */
    public function printBadgeScript(): void
    {
        $catalog = new \UnrePress\Index\GitHubCatalog();

        $plugins = GitHubCatalog::starMap();

        // Full theme map: the grid renders through Backbone without jQuery,
        // so the page needs the complete slug-to-stars set up front.
        $themes = [];

        foreach ((array) ($catalog->getCatalog(GitHubCatalog::KIND_THEMES)['themes'] ?? []) as $entry) {
            if (is_array($entry) && isset($entry['slug'], $entry['stars'])) {
                $themes[(string) $entry['slug']] = (int) $entry['stars'];
            }
        }

        $payload = wp_json_encode(
            ['plugins' => [] === $plugins ? new \stdClass() : $plugins, 'themes' => [] === $themes ? new \stdClass() : $themes],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        if (false === $payload) {
            return;
        }
        ?>
<script>
(function () {
	'use strict';

	var maps = <?php echo $payload; // phpcs:ignore WordPress.WP.EnqueuedResources -- static, HEX-encoded server data ?>;
	var badgeCss = 'margin-left:8px;padding:0 8px;border-radius:10px;background:#f0f0f1;color:#50575e;display:inline-block;font-size:12px;line-height:20px;vertical-align:middle;';

	function formatCount(n) {
		return Number(n).toLocaleString();
	}

	function makeBadge(starCount) {
		var badge = document.createElement('span');
		badge.className = 'unrepress-github-badge';
		badge.setAttribute('style', badgeCss);
		badge.textContent = 'GitHub \u2605 ' + formatCount(starCount);
		return badge;
	}

	function badgePluginCard(slug, starCount) {
		var card = document.querySelector('.plugin-card-' + window.CSS.escape(slug));

		if (!card || card.querySelector('.unrepress-github-badge')) {
			return;
		}

		var name = card.querySelector('.name');

		if (name) {
			name.appendChild(makeBadge(starCount));
		}
	}

	function badgeThemeCard(slug, starCount) {
		var card = document.querySelector('.theme[data-slug="' + window.CSS.escape(slug) + '"]');

		if (!card || card.querySelector('.unrepress-github-badge')) {
			return;
		}

		var badge = makeBadge(starCount);
		var author = card.querySelector('.theme-author');

		if (author) {
			card.insertBefore(badge, author.nextSibling);
		} else {
			card.appendChild(badge);
		}
	}

	function inject(map, injector) {
		Object.keys(map).forEach(function (slug) {
			var stars = map[slug];

			if (typeof stars === 'number' && stars >= 0) {
				injector(slug, stars);
			}
		});
	}

	inject(maps.plugins, badgePluginCard);
	inject(maps.themes, badgeThemeCard);

	// Theme results render through Backbone without jQuery: watch the DOM
	// instead of the transport.
	if (typeof MutationObserver === 'function') {
		new MutationObserver(function () {
			inject(maps.themes, badgeThemeCard);
			inject(maps.plugins, badgePluginCard);
		}).observe(document.body, {childList: true, subtree: true});
	}
})();
</script>
		<?php
    }
}
