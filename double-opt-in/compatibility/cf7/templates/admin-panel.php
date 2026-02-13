<?php
/**
 * CF7 Double Opt-In Admin Panel Template
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   4.0.0
 *
 * @var \WPCF7_ContactForm $post     The contact form.
 * @var array              $metadata The form metadata.
 * @var string             $id       The field ID prefix.
 * @var array              $tags     The form field tags.
 */

use forge12\contactform7\CF7DoubleOptIn\Category;
use forge12\contactform7\CF7DoubleOptIn\HTMLSelect;
use Forge12\DoubleOptIn\EmailTemplates\PlaceholderMapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get available templates
$templateOptions = [
	'blank'           => 'blank',
	'newsletter_en'   => 'newsletter_en',
	'newsletter_en_2' => 'newsletter_en_2',
	'newsletter_en_3' => 'newsletter_en_3',
];

try {
	$container   = \Forge12\DoubleOptIn\Container\Container::getInstance();
	$integration = $container->get( \Forge12\DoubleOptIn\EmailTemplates\EmailTemplateIntegration::class );
	$custom      = $integration->getCustomTemplates();

	foreach ( $custom as $template ) {
		$templateOptions[ 'custom_' . $template['id'] ] = $template['title'] . ' (' . __( 'Custom', 'double-opt-in' ) . ')';
	}
} catch ( \Exception $e ) {
	// Ignore if custom templates not available
}

// Get categories
$atts = [
	'perPage' => -1,
	'orderBy' => 'name',
	'order'   => 'ASC',
];
$categories = Category::get_list( $atts, $numberOfPages );
?>
<div class="forge12-plugin" style="padding-top:0;">
	<div class="forge12-plugin-content" style="margin-top:0;">
		<div class="forge12-plugin-content-main">
			<div class="box" style="width:100%;">
				<h2><?php esc_html_e( 'Opt-In Formular Settings', 'double-opt-in' ); ?></h2>
				<div class="option">
					<div class="label">
						<label for="doubleoptin[enable]">
							<?php esc_html_e( 'Enable', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<input type="checkbox" name="doubleoptin[enable]" id="doubleoptin"
							   value="1" <?php checked( isset( $metadata['enable'] ) && $metadata['enable'] == 1 ); ?> >
						<span><?php esc_html_e( 'Yes', 'double-opt-in' ); ?></span>
						<p><?php esc_html_e( 'Enable this checkbox to activate the Double-Opt-In Mail for this formular.', 'double-opt-in' ); ?></p>
					</div>
				</div>

				<div class="option">
					<div class="label">
						<label for="doubleoptin[category]">
							<?php esc_html_e( 'Category', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<?php
						$categoryOptions = '<option value="0">' . esc_html__( 'Please select', 'double-opt-in' ) . '</option>';
						foreach ( $categories as $category ) {
							$selected = ( $category->get_id() == $metadata['category'] ) ? 'selected="selected"' : '';
							$categoryOptions .= '<option value="' . esc_attr( $category->get_id() ) . '" ' . $selected . '>' . esc_html( $category->get_name() ) . '</option>';
						}
						?>
						<select name="doubleoptin[category]"><?php echo wp_kses( $categoryOptions, [ 'option' => [ 'value' => [], 'selected' => [] ] ] ); ?></select>
						<span><?php esc_html_e( 'Assign the Opt-In to a Category for an easier administration.', 'double-opt-in' ); ?></span>
					</div>
				</div>

				<div class="option">
					<div class="label">
						<label for="doubleoptin[page]">
							<?php esc_html_e( 'Dynamic Condition', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<span><?php esc_html_e( 'Enable the Opt-In only when the field ', 'double-opt-in' ); ?></span>
						<?php
						$conditionOptions = array_merge( [ 'disable' => __( 'Disabled', 'double-opt-in' ) ], $tags );
						$conditionList = new HTMLSelect( $id . '[conditions]', $conditionOptions, [
							'id'    => [ $id . '-conditions' ],
							'class' => [ 'large-text', 'code' ],
						] );
						$conditionList->setOptionSelectedByKey( $metadata['conditions'] );
						echo wp_kses( $conditionList->get(), [ 'select' => [ 'name' => [] ], 'option' => [ 'value' => [], 'selected' => [] ] ] );
						?>
						<span><?php esc_html_e( 'has been filled/checked by the visitor.', 'double-opt-in' ); ?></span>
					</div>
				</div>

				<div class="option">
					<div class="label">
						<label for="doubleoptin[page]">
							<?php esc_html_e( 'Confirmation Page', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<?php wp_dropdown_pages( [
							'show_option_none' => __( 'default', 'double-opt-in' ),
							'name'             => 'doubleoptin[page]',
							'selected'         => $metadata['page'],
						] ); ?>
						<p><?php esc_html_e( 'Select the page that should be displayed after the user clicks on the confirmation link', 'double-opt-in' ); ?></p>
					</div>
				</div>

				<div class="option">
					<div class="label">
						<label for="doubleoptin[error_page]">
							<?php esc_html_e( 'Error Redirect Page', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<?php wp_dropdown_pages( [
							'show_option_none' => __( 'default', 'double-opt-in' ),
							'name'             => 'doubleoptin[error_page]',
							'selected'         => $metadata['error_page'] ?? -1,
						] ); ?>
						<p><?php esc_html_e( 'Select the page to redirect to when an error occurs (e.g. rate limit). Leave on default for toast notification.', 'double-opt-in' ); ?></p>
					</div>
				</div>

				<?php
				echo apply_filters( 'f12_cf7_formular_settings_after_cf7', '', $metadata );
				do_action( 'f12_cf7_formular_settings_after', 'cf7', $metadata );
				?>
			</div>

			<div class="box" style="width:100%;">
				<h2><?php esc_html_e( 'Customize your Opt-In Mail', 'double-opt-in' ); ?></h2>
				<div class="option">
					<div class="label">
						<label for="<?php echo esc_attr( $id ); ?>-recipient">
							<?php esc_html_e( 'To', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<input type="text" id="<?php echo esc_attr( $id ); ?>-recipient"
							   name="<?php echo esc_attr( $id ); ?>[recipient]"
							   class="large-text code" size="70"
							   value="<?php echo esc_attr( $metadata['recipient'] ); ?>"/>
						<p><?php esc_html_e( 'This field defines who will receive the Double-Opt-In mail. Enter one of the following tags that represents the mail for the customer:', 'double-opt-in' ); ?></p>
						<p>
							<code>
								<?php
								$tagsList = [];
								foreach ( $tags as $key => $value ) {
									$tagsList[] = '[' . esc_html( $value ) . ']';
								}
								echo esc_html( implode( ',', array_filter( $tagsList ) ) );
								?>
							</code>
						</p>
					</div>
				</div>

				<div class="option">
					<div class="label">
						<label for="<?php echo esc_attr( $id ); ?>-sender">
							<?php esc_html_e( 'From', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<input type="text" id="<?php echo esc_attr( $id ); ?>-sender"
							   name="<?php echo esc_attr( $id ); ?>[sender]"
							   class="large-text code" size="70"
							   value="<?php echo esc_attr( $metadata['sender'] ); ?>"/>
						<p><?php esc_html_e( 'This field defines who will sent the mail. You can either enter the e-Mail (e.g.: your-email@yourdomain.de) or use a placeholder (e.g.: [_site_admin_email]).', 'double-opt-in' ); ?></p>
					</div>
				</div>

				<div class="option">
					<div class="label">
						<label for="<?php echo esc_attr( $id ); ?>-sender_name">
							<?php esc_html_e( 'From Name', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<input type="text" id="<?php echo esc_attr( $id ); ?>-sender_name"
							   name="<?php echo esc_attr( $id ); ?>[sender_name]"
							   class="large-text code" size="70"
							   value="<?php echo esc_attr( $metadata['sender_name'] ); ?>"/>
						<p><?php esc_html_e( 'This field defines the name of the sender (e.g. Max Mustermann).', 'double-opt-in' ); ?></p>
					</div>
				</div>

				<div class="option">
					<div class="label">
						<label for="<?php echo esc_attr( $id ); ?>-subject">
							<?php esc_html_e( 'Subject', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<input type="text" id="<?php echo esc_attr( $id ); ?>-subject"
							   name="<?php echo esc_attr( $id ); ?>[subject]"
							   class="large-text code" size="70"
							   value="<?php echo esc_attr( $metadata['subject'] ); ?>"/>
						<p><?php esc_html_e( 'Enter a custom subject for the Double-Opt-In mail (e.g.: Please confirm your registration).', 'double-opt-in' ); ?></p>
					</div>
				</div>

				<div class="option">
					<div class="label">
						<label for="<?php echo esc_attr( $id ); ?>-body">
							<?php esc_html_e( 'Template', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<div class="preview">
							<?php
							$builtInTemplates = [ 'blank', 'newsletter_en', 'newsletter_en_2', 'newsletter_en_3' ];
							foreach ( $builtInTemplates as $template ) :
								$isActive = $metadata['template'] === $template ? 'active' : '';
								?>
								<div class="preview-item">
									<div class="preview-item-inner <?php echo esc_attr( $isActive ); ?>">
										<img src="<?php echo esc_url( plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'mails/' . $template . '.png' ); ?>"
											 class="f12-cf7-templateloader-preview" data-template="<?php echo esc_attr( $template ); ?>"
											 title="<?php echo esc_attr( ucfirst( str_replace( '_', ' ', $template ) ) ); ?>"/>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
						<?php
						$templateList = new HTMLSelect( $id . '[template]', $templateOptions, [
							'class' => [ 'f12-cf7-templateloader' ],
							'style' => [ 'display:none;' ],
						] );
						$templateList->setOptionSelectedByKey( $metadata['template'] );
						echo wp_kses( $templateList->get(), [ 'select' => [ 'style' => [], 'class' => [], 'name' => [] ], 'option' => [ 'value' => [], 'selected' => [] ] ] );

						do_action( 'f12_cf7_doubleoptin_admin_panel_templates', $metadata, $id );
						?>
					</div>
				</div>

				<div class="option">
					<div class="label">
						<label for="<?php echo esc_attr( $id ); ?>-body">
							<?php esc_html_e( 'Message body', 'double-opt-in' ); ?>
						</label>
					</div>
					<div class="input">
						<textarea id="<?php echo esc_attr( $id ); ?>-body"
								  name="<?php echo esc_attr( $id ); ?>[body]"
								  cols="100" rows="18"
								  class="large-text code"><?php echo esc_textarea( $metadata['body'] ); ?></textarea>
						<p><?php esc_html_e( 'Enter the Message you want to sent to your customer. Do not forget to add the Double-Opt-In tag ([doubleoptinlink]) e.g.:', 'double-opt-in' ); ?>
							<code>&lt;a href="[doubleoptinlink]"&gt;Confirm the registration&lt;/a&gt;</code>
						</p>
						<p><?php esc_html_e( 'These placeholders are available for the opt-in Message body: ', 'double-opt-in' ); ?></p>
						<p>
							<code>[doubleoptinlink],[doubleoptin_form_url],[doubleoptin_form_subject],[doubleoptin_form_date],[doubleoptin_form_time],[_site_admin_email],<?php echo esc_html( implode( ',', $tagsList ) ); ?></code>
						</p>
					</div>
				</div>
			</div>

			<?php
			// Render placeholder mapping UI
			if ( class_exists( PlaceholderMapper::class ) ) {
				$formFields          = array_keys( $tags );
				$standardPlaceholders = PlaceholderMapper::getStandardPlaceholders();
				$autoMapping         = PlaceholderMapper::autoDetectMapping( $formFields );
				$customMapping       = PlaceholderMapper::getCustomMapping( $post->id(), 'cf7' );
				$effectiveMapping    = array_merge( $autoMapping, $customMapping );
				?>
				<div class="box" style="width:100%;">
					<h2><?php esc_html_e( 'Standard Placeholders Mapping', 'double-opt-in' ); ?></h2>
					<p class="description" style="margin-bottom: 15px;">
						<?php esc_html_e( 'Map your form fields to standard placeholders. This allows you to use the same email template across different forms.', 'double-opt-in' ); ?>
					</p>

					<table class="widefat" style="margin-bottom: 20px;">
						<thead>
						<tr>
							<th style="width: 200px;"><?php esc_html_e( 'Standard Placeholder', 'double-opt-in' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Auto-Detected', 'double-opt-in' ); ?></th>
							<th><?php esc_html_e( 'Custom Mapping (Override)', 'double-opt-in' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ( $standardPlaceholders as $placeholder => $config ) :
							$autoDetected = $autoMapping[ $placeholder ] ?? '';
							$customValue = $customMapping[ $placeholder ] ?? '';
							?>
							<tr>
								<td>
									<code>[<?php echo esc_html( $placeholder ); ?>]</code>
									<br><small style="color: #666;"><?php echo esc_html( $config['label'] ); ?></small>
								</td>
								<td>
									<?php if ( $autoDetected ) : ?>
										<span style="color: #46b450;"><code>[<?php echo esc_html( $autoDetected ); ?>]</code></span>
									<?php else : ?>
										<span style="color: #999;"><?php esc_html_e( 'Not detected', 'double-opt-in' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<select name="doubleoptin[placeholder_mapping][<?php echo esc_attr( $placeholder ); ?>]" style="width: 100%; max-width: 300px;">
										<option value="">
											<?php
											if ( $autoDetected ) {
												printf( esc_html__( 'Use auto-detected: [%s]', 'double-opt-in' ), esc_html( $autoDetected ) );
											} else {
												esc_html_e( '-- Select field --', 'double-opt-in' );
											}
											?>
										</option>
										<?php foreach ( $formFields as $field ) : ?>
											<option value="<?php echo esc_attr( $field ); ?>" <?php selected( $customValue, $field ); ?>>
												[<?php echo esc_html( $field ); ?>]
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php } ?>

			<?php
			do_action( 'f12_cf7_doubleoptin_admin_panel', $post, $metadata, $id );
			wp_nonce_field( 'f12_cf7_doubleoptin_save_form_action', 'f12_cf7_doubleoptin_save_form_nonce' );
			?>
		</div>
	</div>
</div>
