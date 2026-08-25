<?php
/**
 * The one-time ask: would you show a credit on your confirmation page?
 *
 * Asked once, after the plugin has actually done something, and never again
 * either way.
 *
 * The shape of this notice is deliberate, because the tempting version is the
 * one that gets a plugin thrown out of the directory. Guideline 10 requires the
 * choice to be made through "clearly stated and understandable choices, not
 * buried in the terms of use or documentation" — so: both answers are ordinary
 * buttons of the same weight, the wording says plainly what will appear and
 * where, and the preview shows the exact markup rather than describing it. No
 * pre-selection, no dark pattern, no second asking.
 *
 * What it does lean on is legitimate and true: the plugin has confirmed a real,
 * countable number of opt-ins for this site before it asks for anything, and it
 * says why the link helps. That is the whole of the persuasion, and it is the
 * only part worth having — a site owner tricked into it removes it the moment
 * they notice, and leaves a one-star review on the way out.
 */

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Confirmed opt-ins required before the question is asked at all.
 *
 * High enough that the plugin has demonstrably earned the ask, low enough that
 * an active site reaches it in a sensible time. Lower than the captcha plugin's
 * equivalent (100 blocked spam submissions) because a confirmed opt-in is a
 * much rarer event than a blocked spam attempt.
 */
const CREDIT_NUDGE_THRESHOLD = 50;

/**
 * Option holding the answer. Absent = not asked yet, 'accepted' / 'declined' = done.
 */
const CREDIT_NUDGE_ANSWER_OPTION = 'f12_doi_credit_nudge_answer';

/**
 * How many opt-ins this install has confirmed.
 *
 * The same counter the review notice uses, so the two never tell the site owner
 * different numbers for the same thing.
 */
function get_confirmed_count(): int {
	$counters = get_option( 'f12_cf7_doubleoptin_telemetry_counters', array() );

	return ( is_array( $counters ) && isset( $counters['confirmed_optins'] ) )
		? (int) $counters['confirmed_optins']
		: 0;
}

/**
 * Whether to ask on this screen.
 */
function should_show_credit_nudge(): bool {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}

	// Answered once, in either direction: never ask again.
	if ( get_option( CREDIT_NUDGE_ANSWER_OPTION, '' ) !== '' ) {
		return false;
	}

	// Already on — nothing to ask for. Covers the site owner who found the
	// setting themselves.
	if ( is_credit_enabled() ) {
		return false;
	}

	if ( get_confirmed_count() < CREDIT_NUDGE_THRESHOLD ) {
		return false;
	}

	// Never alongside the review notice. Two requests on one screen is the
	// nagging that earns one-star reviews, and the review is the more valuable
	// of the two.
	if ( ! empty( $GLOBALS['f12_doi_review_notice_shown'] ) ) {
		return false;
	}

	return true;
}

/**
 * Render the notice.
 */
function render_credit_nudge(): void {
	if ( ! should_show_credit_nudge() ) {
		return;
	}

	$confirmed = get_confirmed_count();
	$accept    = wp_nonce_url( add_query_arg( 'f12_doi_credit', 'yes' ), 'f12_doi_credit' );
	$decline   = wp_nonce_url( add_query_arg( 'f12_doi_credit', 'no' ), 'f12_doi_credit' );
	?>
	<div class="notice notice-info f12-doi-credit-nudge">
		<p>
			<?php
			printf(
				wp_kses(
					/* translators: %s: number of confirmed opt-ins, already formatted. */
					__( 'Double Opt-In has confirmed <strong>%s opt-ins</strong> on this site so far.', 'double-opt-in' ),
					array( 'strong' => array() )
				),
				esc_html( number_format_i18n( $confirmed ) )
			);
			?>
			<?php esc_html_e( 'Would you show a small credit on your confirmation page? It helps other site owners find the plugin, and it is what keeps it free.', 'double-opt-in' ); ?>
		</p>

		<p style="margin:0 0 4px;"><?php esc_html_e( 'This is exactly what would appear, on the page a subscriber lands on after confirming:', 'double-opt-in' ); ?></p>
		<div style="padding:8px 12px;background:#fff;border:1px solid #dcdcde;border-radius:3px;display:inline-block;margin-bottom:8px;">
			<?php
			// Rendered, not described: the real objection is "will this clutter
			// my page", and only the actual thing answers it.
			echo wp_kses(
				get_credit_markup( true ),
				array(
					'p' => array( 'class' => true ),
					'a' => array(
						'href'   => true,
						'target' => true,
						'rel'    => true,
					),
				)
			);
			?>
		</div>

		<p>
			<a href="<?php echo esc_url( $accept ); ?>" class="button button-primary">
				<?php esc_html_e( 'Yes, show the link', 'double-opt-in' ); ?>
			</a>
			<a href="<?php echo esc_url( $decline ); ?>" class="button">
				<?php esc_html_e( 'No thanks', 'double-opt-in' ); ?>
			</a>
			<span style="margin-left:8px;color:#646970;">
				<?php esc_html_e( 'Asked once. You can change it any time under Settings.', 'double-opt-in' ); ?>
			</span>
		</p>
	</div>
	<?php
}

/**
 * Record the answer.
 *
 * Nonce-checked and capability-checked: unlike a dismiss flag, "yes" writes a
 * setting that changes what every visitor of the confirmation page sees, so a
 * stray link must not be able to trigger it.
 */
function handle_credit_nudge_answer(): void {
	if ( ! isset( $_GET['f12_doi_credit'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'f12_doi_credit' );

	$answer = sanitize_text_field( wp_unslash( $_GET['f12_doi_credit'] ) );

	if ( $answer === 'yes' ) {
		$settings = get_option( 'f12-doi-settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings[ CREDIT_SETTING_KEY ] = 1;
		update_option( 'f12-doi-settings', $settings );

		update_option( CREDIT_NUDGE_ANSWER_OPTION, 'accepted' );
	} else {
		update_option( CREDIT_NUDGE_ANSWER_OPTION, 'declined' );
	}

	// Drop the parameters so a refresh does not replay the action.
	wp_safe_redirect( remove_query_arg( array( 'f12_doi_credit', '_wpnonce' ) ) );
	exit;
}

// Priority 20: after the review notice, so the guard in
// should_show_credit_nudge() can see whether that one already claimed this screen.
add_action( 'admin_notices', __NAMESPACE__ . '\render_credit_nudge', 20 );
add_action( 'admin_init', __NAMESPACE__ . '\handle_credit_nudge_answer' );
