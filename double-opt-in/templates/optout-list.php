<?php
/**
 * Opt-out subscription list — rendered after the visitor clicks the
 * hashed link in the opt-out invitation email.
 *
 * Variables provided by OptOutListShortcode::getContent():
 *
 * @var OptIn[]  $list             Subscriptions tied to the hash's email.
 * @var string   $hash             OptOut hash (used in cancel-action links).
 * @var int|null $done_form_id     cf_form_id of just-cancelled record (single).
 * @var bool     $done_bulk        True after the bulk-cancel action.
 * @var int      $done_count       Number of rows affected by bulk-cancel.
 * @var int|null $reoptin_form_id  cf_form_id of just-reactivated record.
 *
 * 2026-05-15 redesign — labels, bulk cancel, re-opt-in, and JS confirm
 * dialogs are all driven by OptOutConfigResolver so the admin can
 * customize every string in /opt-out → "List page labels" / "List
 * page notices" cards. Strings flow through WPML String Translation
 * for per-language overrides.
 */

use forge12\contactform7\CF7DoubleOptIn\CF7DoubleOptIn;
use forge12\contactform7\CF7DoubleOptIn\OptIn;
use Forge12\DoubleOptIn\OptOut\OptOutConfigResolver;

$settings             = CF7DoubleOptIn::getInstance()->getSettings();
$manual_delete_value  = isset( $settings['delete_unconfirmed'] ) ? $settings['delete_unconfirmed'] : 1;
$manual_delete_period = isset( $settings['delete_unconfirmed_period'] ) ? $settings['delete_unconfirmed_period'] : 'months';
$admin_email          = get_option( 'admin_email' );

$done_form_id    = isset( $done_form_id ) ? (int) $done_form_id : 0;
$done_bulk       = ! empty( $done_bulk );
$done_count      = isset( $done_count ) ? (int) $done_count : 0;
$reoptin_form_id = isset( $reoptin_form_id ) ? (int) $reoptin_form_id : 0;
$affected_hash   = isset( $affected_hash ) ? (string) $affected_hash : '';

$resolver = new OptOutConfigResolver();

// Helper closure for label lookups so the template stays compact.
$L = static function ( string $key ) use ( $resolver ): string {
	return $resolver->labelText( $key );
};

// Count active rows so the bulk button can be hidden when there's
// nothing to cancel.
$activeCount = 0;
foreach ( $list as $OptIn ) {
	if ( $OptIn->is_confirmed() ) {
		$activeCount++;
	}
}
?>
<div class="f12-cf7-doubleoptin-optout f12-cf7-doubleoptin-optout-list">

	<?php if ( $done_bulk ) : ?>
		<div class="f12-doi-optout-notice f12-doi-optout-notice--success" role="status">
			<strong><?php echo esc_html( $resolver->listNoticeText( 'cancelled_all', 'title' ) ); ?></strong>
			<p><?php echo esc_html( sprintf( $resolver->listNoticeText( 'cancelled_all', 'body' ), $done_count ) ); ?></p>
		</div>
	<?php elseif ( $done_form_id > 0 ) :
		$doneTitle = get_the_title( $done_form_id );
		if ( $doneTitle === '' ) {
			$doneTitle = sprintf( __( 'Form #%d', 'double-opt-in-opt-out' ), $done_form_id );
		}
		?>
		<div class="f12-doi-optout-notice f12-doi-optout-notice--success" role="status">
			<strong><?php echo esc_html( $resolver->listNoticeText( 'cancelled', 'title' ) ); ?></strong>
			<p><?php echo esc_html( sprintf( $resolver->listNoticeText( 'cancelled', 'body' ), $doneTitle ) ); ?></p>
		</div>
	<?php elseif ( $reoptin_form_id > 0 ) :
		$reoptinTitle = get_the_title( $reoptin_form_id );
		if ( $reoptinTitle === '' ) {
			$reoptinTitle = sprintf( __( 'Form #%d', 'double-opt-in-opt-out' ), $reoptin_form_id );
		}
		?>
		<div class="f12-doi-optout-notice f12-doi-optout-notice--success" role="status">
			<strong><?php echo esc_html( $resolver->listNoticeText( 'reactivated', 'title' ) ); ?></strong>
			<p><?php echo esc_html( sprintf( $resolver->listNoticeText( 'reactivated', 'body' ), $reoptinTitle ) ); ?></p>
		</div>
	<?php endif; ?>

	<div class="f12-cf7-doubleoptin-optout-list--inner">
		<?php if ( empty( $list ) ) : ?>
			<div class="f12-doi-optout-empty">
				<?php echo esc_html( $L( 'empty_state' ) ); ?>
			</div>
		<?php else : ?>
			<?php if ( $activeCount > 1 ) :
				// Pre-encode the hash so add_query_arg's =-eating
				// regex doesn't strip the base64 padding (see
				// OptOutHandler::doRedirect for the full story).
				$encodedHash = rawurlencode( $hash );
				$bulkUrl     = add_query_arg(
					array( 'optout_all' => $encodedHash, 'hash' => $encodedHash ),
					get_permalink()
				);
				?>
				<div class="f12-doi-optout-bulk-bar">
					<a href="<?php echo esc_url( $bulkUrl ); ?>"
					   class="f12-doi-optout-bulk"
					   data-f12-confirm="<?php echo esc_attr( $L( 'bulk_confirm' ) ); ?>">
						<?php echo esc_html( $L( 'bulk_button' ) ); ?>
					</a>
				</div>
			<?php endif; ?>

			<table class="doi-table">
				<thead>
					<tr>
						<th><?php echo esc_html( $L( 'column_subscription' ) ); ?></th>
						<th><?php echo esc_html( $L( 'column_signed_up' ) ); ?></th>
						<th><?php echo esc_html( $L( 'column_status' ) ); ?></th>
						<th><?php echo esc_html( $L( 'column_valid_until' ) ); ?>*</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $list as $OptIn ) :
					$formId    = $OptIn->get_cf_form_id();
					$formTitle = $formId > 0 ? get_the_title( $formId ) : '';
					if ( $formTitle === '' ) {
						$formTitle = sprintf( __( 'Form #%d', 'double-opt-in-opt-out' ), $formId );
					}
					$isConfirmed = $OptIn->is_confirmed();
					// Highlight only the SPECIFIC row that was just
					// acted on. Matching on $affected_hash means a
					// click on one of N identical-form duplicates
					// highlights just that one (was form_id before;
					// caught every duplicate, user-reported 2026-05-15).
					$justDone = ( $affected_hash !== '' && $OptIn->get_hash() === $affected_hash );
					$rowClass = $justDone ? 'f12-doi-optout-row--justdone' : '';
					?>
					<tr class="<?php echo esc_attr( $rowClass ); ?>">
						<td class="f12-doi-optout-name">
							<?php echo esc_html( $formTitle ); ?>
						</td>
						<td>
							<?php echo esc_html( $OptIn->get_createtime( 'view' ) ); ?>
						</td>
						<td>
							<?php if ( $isConfirmed ) : ?>
								<span class="f12-doi-optout-badge f12-doi-optout-badge--active">
									<?php echo esc_html( $L( 'badge_active' ) ); ?>
								</span>
							<?php else : ?>
								<span class="f12-doi-optout-badge f12-doi-optout-badge--cancelled">
									<?php echo esc_html( $L( 'badge_cancelled' ) ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $settings['delete'] == 0 || $settings['delete_unconfirmed'] == 0 ) : ?>
								<?php echo esc_html( '∞' ); ?>
							<?php else : ?>
								<?php echo esc_html( $OptIn->get_valid_until() ); ?>
							<?php endif; ?>

							<?php if ( $isConfirmed && $settings['delete'] != 0 ) : ?>
								<small>(<?php echo esc_html( $settings['delete'] ); ?> <?php echo esc_html( $settings['delete_period'] ); ?>)</small>
							<?php endif; ?>
							<?php if ( ! $isConfirmed && $settings['delete_unconfirmed'] != 0 ) : ?>
								<small>(<?php echo esc_html( $settings['delete_unconfirmed'] ); ?> <?php echo esc_html( $settings['delete_unconfirmed_period'] ); ?>)</small>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $isConfirmed ) :
								// Use get_permalink() instead of
								// OptIn::get_link_optout() — the
								// latter still reads from the legacy
								// `f12-doi-settings` blob and falls
								// back to home_url() when the page
								// isn't mirrored there from the new
								// option (user-reported 2026-05-15:
								// "Cancel takes me to homepage").
								// We're rendering inside the list
								// shortcode, so the current permalink
								// IS the opt-out page — no lookup
								// needed.
								$cancelUrl = add_query_arg(
									array(
										'optout' => rawurlencode( $OptIn->get_hash() ),
										'hash'   => rawurlencode( $hash ),
									),
									get_permalink()
								);
								?>
								<a href="<?php echo esc_url( $cancelUrl ); ?>"
								   class="button f12-doi-optout-cancel"
								   data-f12-confirm="<?php echo esc_attr( $L( 'cancel_confirm' ) ); ?>">
									<?php echo esc_html( $L( 'cancel_button' ) ); ?>
								</a>
							<?php else : ?>
								<?php
								// Re-opt-in lives on the same page —
								// `?reoptin=<optin-hash>&hash=<owner>`
								// keeps the list-rendering hash on the
								// redirect target so the table comes
								// back with the row highlighted.
								// Pre-encoded to dodge add_query_arg's
								// =-stripping regex on base64 padding.
								$reoptinUrl = add_query_arg(
									array(
										'reoptin' => rawurlencode( $OptIn->get_hash() ),
										'hash'    => rawurlencode( $hash ),
									),
									get_permalink()
								);
								?>
								<a href="<?php echo esc_url( $reoptinUrl ); ?>"
								   class="button f12-doi-optout-reactivate"
								   data-f12-confirm="<?php echo esc_attr( $L( 'reactivate_confirm' ) ); ?>">
									<?php echo esc_html( $L( 'reactivate_button' ) ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<span class="hint">* <?php esc_html_e( 'The date indicates when your personal data will be deleted.', 'double-opt-in' ); ?></span>
	<span class="hint">*
		<?php if ( $manual_delete_value == 0 ) : ?>
			<?php printf(
				/* translators: %s = admin email */
				esc_html__( 'Please note that even with manual opt-out, your data will be stored. Please contact the administrator to delete your personal data: %s.', 'double-opt-in' ),
				esc_html( $admin_email )
			); ?>
		<?php else : ?>
			<?php printf(
				/* translators: 1: number, 2: period (e.g. months) */
				esc_html__( 'Please note that even with manual opt-out, your data will only be deleted in %1$d %2$s.', 'double-opt-in' ),
				(int) $manual_delete_value,
				esc_html( $manual_delete_period )
			); ?>
		<?php endif; ?>
	</span>
</div>

<!--
    Styled confirm modal for cancel / bulk / reactivate actions.
    Uses the native <dialog> element — accessible (focus trap +
    Escape-to-close are built in), no JS framework needed. Hidden
    until JS upgrades a click; if <dialog> support is missing on
    very old browsers, the script falls back to window.confirm().
-->
<dialog class="f12-doi-optout-dialog" aria-labelledby="f12-doi-optout-dialog__prompt">
    <p class="f12-doi-optout-dialog__prompt" id="f12-doi-optout-dialog__prompt"></p>
    <div class="f12-doi-optout-dialog__actions">
        <button type="button" class="f12-doi-optout-dialog__cancel"><?php echo esc_html( $L( 'confirm_no' ) ); ?></button>
        <button type="button" class="f12-doi-optout-dialog__confirm"><?php echo esc_html( $L( 'confirm_yes' ) ); ?></button>
    </div>
</dialog>

<script>
/*
 * Click-time confirm modal for the cancel / bulk / reactivate links.
 * Reads the prompt from data-f12-confirm on each anchor so the admin
 * can configure the text per action under /opt-out → Labels. Button
 * labels live in the modal itself (rendered server-side).
 *
 * Falls back to window.confirm() if the browser doesn't support the
 * <dialog> element (pre-2022 Safari etc.) so the action never
 * silently fires without a confirmation.
 *
 * Lives inline because the script is tiny + only needed when the
 * list shortcode renders. An enqueued asset would cost an extra HTTP
 * request for ~30 lines of JS.
 */
(function () {
    var dialog = document.querySelector('.f12-doi-optout-dialog');
    var supportsDialog = dialog && typeof dialog.showModal === 'function';

    if (!supportsDialog && dialog) {
        // Old-browser fallback: rip the <dialog> out of the DOM so
        // its content doesn't render as a visible block of text.
        dialog.parentNode.removeChild(dialog);
        dialog = null;
    }

    var pendingHref = null;
    if (dialog) {
        var promptEl = dialog.querySelector('.f12-doi-optout-dialog__prompt');
        var yesBtn = dialog.querySelector('.f12-doi-optout-dialog__confirm');
        var noBtn = dialog.querySelector('.f12-doi-optout-dialog__cancel');

        yesBtn.addEventListener('click', function () {
            dialog.close();
            if (pendingHref) {
                window.location.href = pendingHref;
                pendingHref = null;
            }
        });
        noBtn.addEventListener('click', function () {
            pendingHref = null;
            dialog.close();
        });
        // Cancel = Escape key + clicking outside → also reset.
        dialog.addEventListener('cancel', function () { pendingHref = null; });
        dialog.addEventListener('close', function () { pendingHref = null; });
    }

    document.querySelectorAll('[data-f12-confirm]').forEach(function (el) {
        el.addEventListener('click', function (ev) {
            var msg = el.getAttribute('data-f12-confirm') || '';
            if (!msg) return;

            ev.preventDefault();

            if (!supportsDialog) {
                if (window.confirm(msg)) {
                    window.location.href = el.href;
                }
                return;
            }

            promptEl.textContent = msg;
            pendingHref = el.href;
            dialog.showModal();
        });
    });
})();
</script>
