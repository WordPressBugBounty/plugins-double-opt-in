<?php
/**
 * @var \forge12\contactform7\CF7DoubleOptIn\OptIn $optin
 * @var string                                     $domain
 */
$formfields = maybe_unserialize( $optin->get_content() );;
$Category      = \forge12\contactform7\CF7DoubleOptIn\Category::get_by_id( $optin->get_category() );
$category_html = '---';

if ( null != $Category ) {
	$category_html = '<a href="' . esc_url( $Category->get_link_ui() ) . '">' . esc_attr( $Category->get_name() ) . '</a>';
}

function flatten_array( $array, $prefix = '' ) {
	$result = array();
	foreach ( $array as $key => $value ) {
		if ( is_array( $value ) ) {
			$result = $result + flatten_array( $value, $prefix . $key . '.' );
		} else {
			$result[ $prefix . $key ] = $value;
		}
	}

	return $result;
}

?>
<script>hljs.highlightAll();</script>
<p>
	<?php _e( 'Category', 'double-opt-in' ) ?>
    : <?php echo wp_kses( $category_html, array( 'a' => array( 'href' => array() ) ) ); ?>
</p>
<?php if ( current_user_can( 'manage_options' ) ): ?>
    <div class="options" style="display:flex;align-items:center;gap:8px;float:right;margin-top:-40px;">
		<?php
		/**
		 * Opt-in View Options
		 *
		 * This action is used to extend the options for the pro version.
		 *
		 * @param \forge12\contactform7\CF7DoubleOptIn\OptIn $optin
		 *
		 * @since 3.0.0
		 */
		do_action( 'f12_cf7_doubleoptin_ui_view_optin_options', $optin );
		?>
        <a href="#" class="button" id="doi-delete-trigger"><?php _e( 'Delete DOI', 'double-opt-in' ); ?></a>
    </div>

    <?php
    $delete_url = esc_url( wp_nonce_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_optins&option=delete&hash=' . $optin->get_hash() ), 'doi-delete-optin-' . $optin->get_hash() ) );
    ?>
    <div id="doi-delete-modal" class="doi-modal-overlay" style="display:none;">
        <div class="doi-modal">
            <h3><?php _e( 'Delete Opt-In', 'double-opt-in' ); ?></h3>
            <p><?php _e( 'Are you sure you want to permanently delete this Opt-In? This action cannot be undone.', 'double-opt-in' ); ?></p>
            <div class="doi-modal-actions">
                <button type="button" class="button" id="doi-delete-cancel"><?php _e( 'Cancel', 'double-opt-in' ); ?></button>
                <a href="<?php echo $delete_url; ?>" class="button doi-button-delete"><?php _e( 'Delete', 'double-opt-in' ); ?></a>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var trigger = document.getElementById('doi-delete-trigger');
        var modal   = document.getElementById('doi-delete-modal');
        var cancel  = document.getElementById('doi-delete-cancel');
        trigger.addEventListener('click', function(e){ e.preventDefault(); modal.style.display=''; });
        cancel.addEventListener('click', function(){ modal.style.display='none'; });
        modal.addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });
        document.addEventListener('keydown', function(e){ if(e.key==='Escape' && modal.style.display!=='none') modal.style.display='none'; });
    })();
    </script>
<?php endif; ?>
<table class="view-optin">
    <tr>
        <td><?php _e( 'Key', 'double-opt-in' ); ?></td>
        <td><?php _e( 'Value', 'double-opt-in' ); ?></td>
    </tr>
    <tr>
        <td>
			<?php _e( 'ID', 'double-opt-in' ); ?>
        </td>
        <td>
			<?php echo esc_html( $optin->get_id() ); ?>
        </td>
    </tr>
    <tr>
        <td>
			<?php _e( 'CF7 Form ID', 'double-opt-in' ); ?>
        </td>
        <td>
			<?php echo esc_html( $optin->get_cf_form_id() ); ?>
        </td>
    </tr>
    <tr>
        <td>
			<?php _e( 'E-Mail', 'double-opt-in' ); ?>
        </td>
        <td>
			<?php echo esc_html( $optin->get_email() ); ?>
        </td>
    </tr>
    <tr>
        <td>
			<?php _e( 'Registration Date', 'double-opt-in' ); ?>
        </td>
        <td>
			<?php
			echo esc_html( $optin->get_createtime( 'formatted' ) );
			?>
        </td>
    </tr>
    <tr>
        <td>
			<?php _e( 'Registration IP', 'double-opt-in' ); ?>
        </td>
        <td>
			<?php echo esc_html( $optin->get_ipaddr_register() ); ?>
        </td>
    </tr>
    <tr>
        <td>
			<?php _e( 'Confirmation Date', 'double-opt-in' ); ?>
        </td>
        <td>
			<?php
			if ( $optin->is_confirmed() ) {
				echo esc_html( $optin->get_updatetime( 'formatted' ) );
			}
			?>
        </td>
    </tr>
    <tr>
        <td>
			<?php _e( 'Confirmation IP', 'double-opt-in' ); ?>
        </td>
        <td>
			<?php
			echo esc_html( $optin->get_ipaddr_confirmation() );
			?>
        </td>
    </tr>
	<?php
	/**
	 * Opt-in View Table
	 *
	 * This action is used to extend the table for the pro version.
	 *
	 * @param \forge12\contactform7\CF7DoubleOptIn\OptIn $optin
	 *
	 * @since 3.0.0
	 */
	do_action( 'f12_cf7_doubleoptin_ui_view_optin_table', $optin );
	?>
    <tr>
        <td><?php _e( 'Consent Text', 'double-opt-in' ); ?></td>
        <td>
			<?php
			$consent_text = $optin->get_consent_text();
			if ( ! empty( $consent_text ) ) {
				echo esc_html( $consent_text );
			} else {
				echo '<em>' . esc_html__( 'Not recorded (record created before consent text tracking)', 'double-opt-in' ) . '</em>';
			}
			?>
        </td>
    </tr>
</table>

<h3><?php _e( 'Export Consent Record (GDPR)', 'double-opt-in' ); ?></h3>
<p>
	<?php
	$export_nonce = wp_create_nonce( 'doi_consent_export' );
	$export_base  = admin_url( 'admin-ajax.php?action=doi_export_consent&scope=single&id=' . $optin->get_id() . '&_wpnonce=' . $export_nonce );
	?>
    <a href="<?php echo esc_url( $export_base . '&format=json' ); ?>" class="button" target="_blank">
		<?php _e( 'Export JSON', 'double-opt-in' ); ?>
    </a>
    <a href="<?php echo esc_url( $export_base . '&format=csv' ); ?>" class="button" target="_blank">
		<?php _e( 'Export CSV', 'double-opt-in' ); ?>
    </a>
</p>

<h3><?php _e( 'Form Fields', 'double-opt-in' ); ?></h3>
<table class="view-optin">
    <tr>
        <td><?php _e( 'Key', 'double-opt-in' ); ?></td>
        <td><?php _e( 'Value', 'double-opt-in' ); ?></td>
    </tr>
	<?php if ( isset( $formfields['fields'] ) ): ?>
		<?php foreach ( $formfields['fields'] as $key => $value ): ?>
            <tr>
                <td>
					<?php echo esc_attr( $key ); ?>
                </td>
                <td>
					<?php if ( is_array( $value ) ) {
						echo esc_html( implode( ',', $value ) );
					} else {
						echo esc_html( $value );
					}
					?>
                </td>
            </tr>
		<?php endforeach; ?>
	<?php else: ?>
		<?php foreach ( $formfields as $key => $value ): ?>
            <tr>
                <td>
					<?php echo esc_html( $key ); ?>
                </td>
                <td>
					<?php if ( is_array( $value ) ):
						$flattened_array = flatten_array( $value );

						?>
                        <table>
                            <tr>
                                <td><?php _e( 'Key', 'double-opt-in' ); ?></td>
                                <td><?php _e( 'Value', 'double-opt-in' ); ?></td>
                            </tr>
							<?php if ( empty( $flattened_array ) ) : ?>
                                <tr>
                                    <td colspan="2"><?php _e( 'No entries', 'double-opt-in' ); ?></td>
                                </tr>
							<?php else: ?>
								<?php foreach ( $flattened_array as $k => $v ): ?>
                                    <tr>
                                        <td><?php esc_html_e( $k ); ?></td>
                                        <td><?php esc_html_e( $v ); ?></td>
                                    </tr>
								<?php endforeach; ?>
							<?php endif; ?>
                        </table>
					<?php else: ?>
						<?php esc_html_e( $value ); ?>
					<?php endif; ?>
                </td>
            </tr>
		<?php endforeach; ?>
	<?php endif; ?>
</table>

</div>
<div class="box">
    <h2><?php _e( 'Formular', 'double-opt-in' ); ?></h2>
    <p>
		<?php _e( 'The data above was collected by the following form used from the visitor. The data has been saved as plain text. You can copy the plain content to a html file to see the original formular.', 'double-opt-in' ); ?>
    </p>
    <div class="option">
        <div class="label">
            <label for="category"><?php _e( 'HTML:', 'double-opt-in' ); ?></label>
        </div>
        <div class="input">
            <pre>
                <code class="language-html">
                    <?php echo esc_html( $optin->get_form() ); ?>
                </code>
            </pre>
        </div>
    </div>
</div>
<div class="box">
    <h2><?php _e( 'Opt-In Mail', 'double-opt-in' ); ?></h2>
    <p>
		<?php _e( 'The following Opt-In mail has been submitted to the visitor to get the confirmation. The data has been saved as plain text. Depending on the form it may contain html content.', 'double-opt-in' ); ?>
    </p>
    <div class="option">
        <div class="label">
            <label for="category"><?php _e( 'HTML:', 'double-opt-in' ); ?></label>
        </div>
        <div class="input">
            <pre>
                <code class="language-html">
                    <?php echo esc_html( str_replace( '<br />', '\n', $optin->get_mail_optin() ) ); ?>
                </code>
            </pre>
        </div>
    </div>