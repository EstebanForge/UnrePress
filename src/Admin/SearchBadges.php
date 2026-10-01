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
     * Print the badge injector. Always printed: the theme grid needs the
     * ajaxSuccess watcher even when this request produced no stars.
     */
    public function printBadgeScript(): void
    {
        $starMap = GitHubCatalog::starMap();

        $payload = wp_json_encode(
            [] === $starMap ? new \stdClass() : $starMap,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        if (false === $payload) {
            return;
        }
        ?>
<script>
(function () {
	'use strict';

	var inlineStars = <?php echo $payload; // phpcs:ignore WordPress.WP.EnqueuedResources -- static, HEX-encoded server data ?>;
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

	// Plugin grid: core marks each card with the plugin-card-<slug> class.
	function injectPluginBadge(slug, starCount) {
		var card = document.querySelector('.plugin-card-' + window.CSS.escape(slug));

		if (!card || card.querySelector('.unrepress-github-badge')) {
			return;
		}

		var name = card.querySelector('.name');

		if (name) {
			name.appendChild(makeBadge(starCount));
		}
	}

	// Theme grid: cards carry data-slug on the .theme element.
	function injectThemeBadge(slug, starCount) {
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

	function inject(map) {
		Object.keys(map).forEach(function (slug) {
			var stars = map[slug];

			if (typeof stars !== 'number' || stars < 0) {
				return;
			}

			injectPluginBadge(slug, stars);

			if (document.querySelector('.theme[data-slug="' + window.CSS.escape(slug) + '"]')) {
				injectThemeBadge(slug, stars);
			}
		});
	}

	inject(inlineStars);

	// Theme results arrive via admin-ajax after the footer ran; Backbone
	// syncs through jQuery, so ajaxSuccess sees every rendered batch.
	if (window.jQuery) {
		window.jQuery(document).on('ajaxSuccess', function (event, xhr, settings, data) {
			if (!data || typeof data !== 'object') {
				return;
			}

			(['themes', 'plugins']).forEach(function (key) {
				var items = data[key];

				if (!Array.isArray(items)) {
					return;
				}

				var map = {};

				items.forEach(function (item) {
					if (item && item.slug && typeof item.stars === 'number' && item.stars > 0) {
						map[item.slug] = item.stars;
					}
				});

				inject(map);
			});
		});
	}
})();
</script>
		<?php
    }
}
