<?php
/**
 * Support and feedback where people go looking for them.
 *
 * The review notice only appears after ten days and twenty-five confirmed
 * opt-ins, and it can be dismissed for good — so it cannot be the only route to
 * us. These entries are always there.
 */

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Support and Feedback next to Activate/Deactivate in the plugin list.
 *
 * Typed loosely because this is a public filter: another plugin can hand over
 * something that is not the array WordPress documents, and losing the whole
 * plugin row over our two links would be a poor trade.
 *
 * @param mixed $links The action links WordPress collected so far.
 *
 * @return mixed
 */
function add_plugin_action_links( $links ) {
	if ( ! is_array( $links ) ) {
		return $links;
	}

	$own = array(
		'f12_doi_support' => sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( get_support_url( 'plugin-list' ) ),
			esc_html__( 'Support', 'double-opt-in' )
		),
		'f12_doi_feedback' => sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( get_feedback_url( 'plugin-list' ) ),
			esc_html__( 'Feedback', 'double-opt-in' )
		),
	);

	// Appended rather than prepended: Deactivate and Settings are what someone
	// opened this row for.
	return array_merge( $links, $own );
}

if ( defined( 'FORGE12_OPTIN_BASENAME' ) ) {
	add_filter( 'plugin_action_links_' . FORGE12_OPTIN_BASENAME, __NAMESPACE__ . '\add_plugin_action_links' );
}

/**
 * Add a Feedback & Support entry to the plugin's admin menu.
 *
 * add_submenu_page() cannot point at an external address, so the entry is
 * registered normally and its href rewritten afterwards.
 */
function add_feedback_menu_item(): void {
	$slug = 'f12-doi-feedback';

	add_submenu_page(
		'f12-doi-admin',
		__( 'Feedback', 'double-opt-in' ),
		__( 'Feedback & Support', 'double-opt-in' ),
		'manage_options',
		$slug,
		'__return_null'
	);

	point_menu_item_at( $slug, get_feedback_url( 'admin-menu' ) );
}

/**
 * Send a registered submenu entry to an external address, and open it in a new tab.
 *
 * The URL travels as a JSON string literal and is compared against the
 * attribute value rather than being interpolated into a selector: escaping it
 * for a selector HTML-encodes the ampersands in the query string, so the
 * selector would ask for `…&amp;from=…` while the anchor carries `…&from=…`,
 * match nothing, and quietly open in the same tab. Throwing an admin out of
 * their dashboard on the way to a bug report is a good way to lose the report.
 *
 * @param string $slug The slug the entry was registered under.
 * @param string $url  Where it should actually go.
 */
function point_menu_item_at( string $slug, string $url ): void {
	global $submenu;

	if ( ! isset( $submenu['f12-doi-admin'] ) || ! is_array( $submenu['f12-doi-admin'] ) ) {
		return;
	}

	foreach ( $submenu['f12-doi-admin'] as $index => $item ) {
		if ( isset( $item[2] ) && $item[2] === $slug ) {
			$submenu['f12-doi-admin'][ $index ][2] = $url;
			break;
		}
	}

	add_action(
		'admin_footer',
		function () use ( $url ) {
			?>
			<script>
			(function () {
				var target = <?php echo wp_json_encode( $url ); ?>;
				document.querySelectorAll('#adminmenu a').forEach(function (a) {
					if (a.getAttribute('href') === target) {
						a.setAttribute('target', '_blank');
						a.setAttribute('rel', 'noopener');
					}
				});
			})();
			</script>
			<?php
		}
	);
}

// Priority 100: after the plugin's own pages are registered, so the parent menu
// exists and the entry lands at the bottom.
add_action( 'admin_menu', __NAMESPACE__ . '\add_feedback_menu_item', 100 );
