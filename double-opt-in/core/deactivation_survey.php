<?php
/**
 * Asks why, on the way out.
 *
 * The moment someone deactivates is the one moment they definitely have an
 * opinion, and it is the last one we get. So we ask — but the rules are strict,
 * because this pattern is easy to abuse:
 *
 *  - Deactivation is never blocked or delayed. "Just deactivate" is always one
 *    click away and is the default if anything goes wrong with the dialog.
 *  - Nothing is transmitted from here. Choosing a reason opens the feedback form
 *    in a new tab with that reason pre-selected; the user still decides whether
 *    to send it.
 *  - It appears once. Dismissing it, or deactivating without answering, sets a flag.
 */

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_footer-plugins.php', __NAMESPACE__ . '\render_deactivation_survey' );
add_action( 'wp_ajax_f12_doi_dismiss_survey', __NAMESPACE__ . '\dismiss_deactivation_survey' );

/**
 * Remember that the dialog has run its course.
 */
function dismiss_deactivation_survey(): void {
	check_ajax_referer( 'f12_doi_survey' );

	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_send_json_error( null, 403 );
	}

	update_option( 'f12_doi_survey_done', 1, false );
	wp_send_json_success();
}

/**
 * The reasons offered, in the order they are shown.
 *
 * @return array<string, string> slug => label
 */
function get_deactivation_reasons(): array {
	return array(
		'temporary'    => __( 'Only switching it off for a moment', 'double-opt-in' ),
		'not_sending'  => __( 'Confirmation emails did not arrive', 'double-opt-in' ),
		'complicated'  => __( 'I could not get it configured', 'double-opt-in' ),
		'conflict'     => __( 'It clashed with another plugin or my theme', 'double-opt-in' ),
		'form_plugin'  => __( 'It did not work with my form plugin', 'double-opt-in' ),
		'other_plugin' => __( 'I switched to something else', 'double-opt-in' ),
	);
}

/**
 * Print the dialog on the plugins screen.
 */
function render_deactivation_survey(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	if ( (int) get_option( 'f12_doi_survey_done', 0 ) === 1 ) {
		return;
	}

	$reasons  = get_deactivation_reasons();
	$feedback = get_feedback_url( 'deactivation' );
	$nonce    = wp_create_nonce( 'f12_doi_survey' );
	?>
	<div id="f12-doi-survey" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.6);">
		<div style="max-width:460px;margin:10vh auto;background:#fff;border-radius:4px;padding:24px;box-shadow:0 4px 24px rgba(0,0,0,.3);">
			<h2 style="margin-top:0;">
				<?php esc_html_e( 'Before you go — what went wrong?', 'double-opt-in' ); ?>
			</h2>
			<p style="color:#666;">
				<?php esc_html_e( 'Optional. Picking a reason opens our feedback form; nothing is sent from this page.', 'double-opt-in' ); ?>
			</p>
			<ul style="list-style:none;margin:16px 0;padding:0;">
				<?php foreach ( $reasons as $slug => $label ) : ?>
					<li style="margin-bottom:8px;">
						<a href="<?php echo esc_url( add_query_arg( 'reason', $slug, $feedback ) ); ?>"
						   target="_blank" rel="noopener"
						   class="f12-doi-survey-reason"
						   style="display:block;padding:8px 12px;border:1px solid #ddd;border-radius:3px;text-decoration:none;color:#1d2327;">
							<?php echo esc_html( $label ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p style="margin-bottom:0;display:flex;gap:8px;align-items:center;">
				<a href="#" class="button button-primary f12-doi-survey-skip">
					<?php esc_html_e( 'Just deactivate', 'double-opt-in' ); ?>
				</a>
				<a href="#" class="f12-doi-survey-cancel" style="color:#666;">
					<?php esc_html_e( 'Cancel', 'double-opt-in' ); ?>
				</a>
			</p>
		</div>
	</div>
	<script>
	(function () {
		var overlay = document.getElementById('f12-doi-survey');
		var link = document.querySelector('tr[data-slug="double-opt-in"] .deactivate a');
		if (!overlay || !link) { return; }

		var target = link.href;

		function done() {
			// Record that we asked, then let the deactivation proceed. A failed
			// request must never strand the user on this screen, so navigation
			// happens either way.
			var body = new FormData();
			body.append('action', 'f12_doi_dismiss_survey');
			body.append('_wpnonce', <?php echo wp_json_encode( $nonce ); ?>);
			fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
				.catch(function () {})
				.finally(function () { window.location.href = target; });
		}

		link.addEventListener('click', function (e) {
			e.preventDefault();
			overlay.style.display = 'block';
		});

		overlay.querySelectorAll('.f12-doi-survey-reason').forEach(function (a) {
			// The reason opens in a new tab; this one carries on with the deactivation.
			a.addEventListener('click', function () { setTimeout(done, 150); });
		});

		overlay.querySelector('.f12-doi-survey-skip').addEventListener('click', function (e) {
			e.preventDefault();
			done();
		});

		overlay.querySelector('.f12-doi-survey-cancel').addEventListener('click', function (e) {
			e.preventDefault();
			overlay.style.display = 'none';
		});
	})();
	</script>
	<?php
}
