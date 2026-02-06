<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UIDashboard
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class UISettings extends UIPageForm {
		/**
		 * Class constructor.
		 *
		 * @param TemplateHandler $templateHandler The template handler object used for rendering.
		 * @param string          $domain          The domain for the settings object.
		 *
		 * @return void
		 */
		public function __construct( LoggerInterface $logger, TemplateHandler $templateHandler, string $domain ) {

			parent::__construct( $logger, $templateHandler, $domain, 'settings', __( 'Settings', 'double-opt-in' ) );

			$this->get_logger()->notice( 'UIPageSettings parent constructor called with default position.', [
				'plugin' => $domain,
			] );
		}

		/**
		 * Render the license subpage content
		 */
		protected function theContent( $slug, $page, $settings ) {
			$this->get_logger()->info( 'Rendering the main content for the "Settings" UI page.', [
				'plugin'    => $this->domain,
				'page_slug' => $page,
			] );

			// Define the options for the time period dropdowns.
			$deleteOptionItems = array( 0 => __( 'never', 'double-opt-in' ) );
			for ( $i = 1; $i <= 30; $i ++ ) {
				$deleteOptionItems[ $i ] = (string) $i;
			}

			$deleteOptionPeriod = array(
				'months' => 'months',
				'days'   => 'days',
				'years'  => 'years'
			);

			echo wp_kses_post( Messages::getInstance()->getAll() );

			// The form tag is handled by the parent renderContent method
			?>
            <h2>
				<?php _e( 'Settings', 'double-opt-in' ); ?>
            </h2>

            <div class="option">
                <div class="label">
                    <label for="delete"><?php _e( 'Delete Opt-Ins', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <select id="delete" name="delete">
						<?php
						foreach ( $deleteOptionItems as $value => $label ):
							$selected = ( $value == $settings['delete'] ) ? 'selected="selected"' : '';
							?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php echo $selected; ?>>
								<?php echo esc_html( $label ); ?>
                            </option>
						<?php endforeach; ?>
                    </select>

                    <select id="delete_period" name="delete_period">
						<?php
						foreach ( $deleteOptionPeriod as $key => $label ):
							$selected = ( $key == $settings['delete_period'] ) ? 'selected="selected"' : '';
							?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php echo $selected; ?>>
								<?php echo esc_html( $label ); ?>
                            </option>
						<?php endforeach; ?>
                    </select>

                    <p>
						<?php _e( 'Select the period of time after which all data will be deleted automatically. As for the european GDPR it is recommend to use the smallest period of time as possible.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option">
                <div class="label">
                    <label for="delete_unconfirmed"><?php _e( 'Delete unconfirmed Opt-Ins', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <select id="delete_unconfirmed" name="delete_unconfirmed">
						<?php
						foreach ( $deleteOptionItems as $value => $label ):
							$selected = ( $value == $settings['delete_unconfirmed'] ) ? 'selected="selected"' : '';
							?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php echo $selected; ?>>
								<?php echo esc_html( $label ); ?>
                            </option>
						<?php endforeach; ?>
                    </select>

                    <select id="delete_unconfirmed_period" name="delete_unconfirmed_period">
						<?php
						foreach ( $deleteOptionPeriod as $key => $label ):
							$selected = ( $key == $settings['delete_unconfirmed_period'] ) ? 'selected="selected"' : '';
							?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php echo $selected; ?>>
								<?php echo esc_html( $label ); ?>
                            </option>
						<?php endforeach; ?>
                    </select>
                    <p>
						<?php _e( 'Select the period of time after which all unconfirmed data will be deleted automatically. As for the european GDPR it is recommend to use the smallest period of time as possible.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option">
                <div class="label">
                    <label for="telemetry"><?php _e( 'Telemetry', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <div class="toggle-item-wrapper">
                        <!-- TOGGLE -->
                        <div class="f12-checkbox-toggle">
                            <div class="toggle-container">
								<?php
								$field_name = 'telemetry';
								// Default = aktiv (1), nur wenn explizit 0 → deaktiviert
								echo sprintf(
									'<input name="%s" type="hidden" value="%s" id="%s" class="toggle" data-before="%s" data-after="%s">',
									esc_attr( $field_name ),
									esc_attr( $settings[$field_name] ?? 1 ),
									esc_attr( $field_name ),
									esc_attr__( 'On', 'double-opt-in' ),
									esc_attr__( 'Off', 'double-opt-in' )
								);


								?>
                                <label for="<?php esc_attr_e( $field_name ); ?>"
                                       class="toggle-label"></label>
                            </div>
                            <label for="<?php esc_attr_e( $field_name ); ?>">
	                            <?php _e( 'Enable this option to allow anonymous telemetry data to be sent. This helps us improve and develop the plugin.', 'double-opt-in' ); ?>
                            </label>
                            <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                        </div>
                    </div>
                </div>
            </div>
            <hr>
            <h2><?php _e( 'GDPR / Privacy', 'double-opt-in' ); ?></h2>

            <div class="option">
                <div class="label">
                    <label for="privacy_policy_page"><?php _e( 'Privacy Policy Page', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
					<?php
					wp_dropdown_pages( [
						'name'             => 'privacy_policy_page',
						'id'               => 'privacy_policy_page',
						'show_option_none' => __( '&mdash; Select &mdash;', 'double-opt-in' ),
						'option_none_value' => '0',
						'selected'         => $settings['privacy_policy_page'] ?? 0,
					] );
					?>
                    <p>
						<?php _e( 'Select the privacy policy page. This is used for the [doubleoptin_privacy_url] placeholder in email templates. Falls back to the WordPress Privacy Policy page if not set.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>
            <hr>
            <h2><?php _e( 'Security', 'double-opt-in' ); ?></h2>

            <div class="option">
                <div class="label">
                    <label for="token_expiry_hours"><?php _e( 'Token Expiry (Hours)', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <input type="number" id="token_expiry_hours" name="token_expiry_hours"
                           value="<?php echo esc_attr( $settings['token_expiry_hours'] ?? 48 ); ?>"
                           min="0" max="720" step="1" />
                    <p>
                        <?php _e( 'Number of hours after which a confirmation link expires. Set to 0 to disable expiry.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option">
                <div class="label">
                    <label for="rate_limit_ip"><?php _e( 'Rate Limit (IP)', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <input type="number" id="rate_limit_ip" name="rate_limit_ip"
                           value="<?php echo esc_attr( $settings['rate_limit_ip'] ?? 5 ); ?>"
                           min="0" max="100" step="1" />
                    <p>
                        <?php _e( 'Maximum number of opt-in submissions per IP address within the time window. Set to 0 to disable.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option">
                <div class="label">
                    <label for="rate_limit_email"><?php _e( 'Rate Limit (Email)', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <input type="number" id="rate_limit_email" name="rate_limit_email"
                           value="<?php echo esc_attr( $settings['rate_limit_email'] ?? 3 ); ?>"
                           min="0" max="100" step="1" />
                    <p>
                        <?php _e( 'Maximum number of opt-in submissions per email address within the time window. Set to 0 to disable.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option">
                <div class="label">
                    <label for="rate_limit_window"><?php _e( 'Rate Limit Window (Minutes)', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <input type="number" id="rate_limit_window" name="rate_limit_window"
                           value="<?php echo esc_attr( $settings['rate_limit_window'] ?? 60 ); ?>"
                           min="1" max="1440" step="1" />
                    <p>
                        <?php _e( 'Time window in minutes for the rate limit counters.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>
            <hr>
            <h2><?php _e( 'Reminder', 'double-opt-in' ); ?></h2>
			<?php
			$is_pro          = apply_filters( 'f12_doi_is_pro_active', false );
			$pro_disabled    = $is_pro ? '' : ' disabled';
			$pro_style_wrap  = $is_pro ? '' : 'opacity:.6;';
			$pro_style_toggle = $is_pro ? '' : 'pointer-events:none;opacity:.6;';
			?>

            <div class="option">
                <div class="label">
                    <label for="reminder_enabled"><?php _e( 'Enable Reminder', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <div class="toggle-item-wrapper" style="<?php echo esc_attr( $pro_style_toggle ); ?>">
                        <div class="f12-checkbox-toggle">
                            <div class="toggle-container">
								<?php
								$field_name = 'reminder_enabled';
								echo sprintf(
									'<input name="%s" type="hidden" value="%s" id="%s" class="toggle" data-before="%s" data-after="%s">',
									esc_attr( $field_name ),
									esc_attr( $is_pro ? ( $settings[ $field_name ] ?? 0 ) : 0 ),
									esc_attr( $field_name ),
									esc_attr__( 'On', 'double-opt-in' ),
									esc_attr__( 'Off', 'double-opt-in' )
								);
								?>
                                <label for="<?php echo esc_attr( $field_name ); ?>"
                                       class="toggle-label"></label>
                            </div>
                            <label for="<?php echo esc_attr( $field_name ); ?>">
								<?php _e( 'Automatically send a reminder email to unconfirmed opt-ins after the configured delay.', 'double-opt-in' ); ?>
                            </label>
                            <label class="overlay" for="<?php echo esc_attr( $field_name ); ?>"></label>
                        </div>
                    </div>
					<?php if ( ! $is_pro ) : ?>
                        <span style="display:inline-block;background:linear-gradient(135deg,#e6a817,#d4941a);color:#fff;font-size:10px;font-weight:700;line-height:1;padding:3px 6px;border-radius:3px;letter-spacing:.5px;text-transform:uppercase;"
                              title="<?php esc_attr_e( 'This feature is available in the Pro version.', 'double-opt-in' ); ?>">PRO</span>
					<?php endif; ?>
                </div>
            </div>

            <div class="option" style="<?php echo esc_attr( $pro_style_wrap ); ?>">
                <div class="label">
                    <label for="reminder_delay"><?php _e( 'Reminder Delay (Hours)', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <input type="number" id="reminder_delay" name="reminder_delay"
                           value="<?php echo esc_attr( $settings['reminder_delay'] ?? 24 ); ?>"
                           min="1" max="720" step="1"<?php echo $pro_disabled; ?> />
                    <p>
						<?php _e( 'Number of hours to wait after opt-in submission before sending a reminder. Default: 24 hours.', 'double-opt-in' ); ?>
                    </p>
					<?php if ( $is_pro ) :
					$tokenExpiry = (int) ( $settings['token_expiry_hours'] ?? 48 );
					$reminderDelay = (int) ( $settings['reminder_delay'] ?? 24 );
					if ( $tokenExpiry > 0 && $reminderDelay >= $tokenExpiry ) :
						?>
                        <p style="color:#d63638;">
                            <strong><?php _e( 'Warning:', 'double-opt-in' ); ?></strong>
							<?php _e( 'The reminder delay is equal to or greater than the token expiry. The confirmation link in the reminder may already be expired.', 'double-opt-in' ); ?>
                        </p>
					<?php endif; ?>
					<?php
					$deleteUnconfirmed = (int) ( $settings['delete_unconfirmed'] ?? 0 );
					$deleteUnconfirmedPeriod = $settings['delete_unconfirmed_period'] ?? 'months';
					if ( $deleteUnconfirmed > 0 ) :
						$deleteThresholdHours = $deleteUnconfirmed;
						if ( $deleteUnconfirmedPeriod === 'days' ) {
							$deleteThresholdHours = $deleteUnconfirmed * 24;
						} elseif ( $deleteUnconfirmedPeriod === 'months' ) {
							$deleteThresholdHours = $deleteUnconfirmed * 30 * 24;
						} elseif ( $deleteUnconfirmedPeriod === 'years' ) {
							$deleteThresholdHours = $deleteUnconfirmed * 365 * 24;
						}
						if ( $reminderDelay >= $deleteThresholdHours ) :
							?>
                            <p style="color:#d63638;">
                                <strong><?php _e( 'Warning:', 'double-opt-in' ); ?></strong>
								<?php _e( 'The reminder delay is equal to or greater than the unconfirmed opt-in cleanup threshold. Reminders may never be sent because entries are deleted first.', 'double-opt-in' ); ?>
                            </p>
						<?php endif; ?>
					<?php endif; ?>
					<?php endif; ?>
                </div>
            </div>

            <div class="option" style="<?php echo esc_attr( $pro_style_wrap ); ?>">
                <div class="label">
                    <label for="reminder_template"><?php _e( 'Reminder Template', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <select id="reminder_template" name="reminder_template"<?php echo $pro_disabled; ?>>
                        <option value=""><?php _e( '&mdash; Use form template &mdash;', 'double-opt-in' ); ?></option>
						<?php
						try {
							$container = \Forge12\DoubleOptIn\Container\Container::getInstance();
							if ( $container->has( \Forge12\DoubleOptIn\EmailTemplates\EmailTemplateRepository::class ) ) {
								$templateRepo = $container->get( \Forge12\DoubleOptIn\EmailTemplates\EmailTemplateRepository::class );
								$templates    = $templateRepo->getForSelect();
								foreach ( $templates as $key => $label ) :
									$selected = ( $key === ( $settings['reminder_template'] ?? '' ) ) ? 'selected="selected"' : '';
									?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php echo $selected; ?>>
										<?php echo esc_html( $label ); ?>
                                    </option>
								<?php endforeach;
							}
						} catch ( \Exception $e ) {
							// Template repository not available
						}
						?>
                    </select>
                    <p>
						<?php _e( 'Select a custom email template for the reminder. Leave empty to use the same template as the original opt-in email.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option" style="<?php echo esc_attr( $pro_style_wrap ); ?>">
                <div class="label">
                    <label for="reminder_subject"><?php _e( 'Reminder Subject', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <input type="text" id="reminder_subject" name="reminder_subject"
                           value="<?php echo esc_attr( $settings['reminder_subject'] ?? '' ); ?>"
                           class="regular-text"<?php echo $pro_disabled; ?> />
                    <p>
						<?php _e( 'Custom subject line for the reminder email. Leave empty to use "Reminder: {original subject}".', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>
            <hr>
            <h2><?php _e( 'E-Mail Validation', 'double-opt-in' ); ?></h2>

            <div class="option">
                <div class="label">
                    <label for="mx_validation_enabled"><?php _e( 'MX Record Validation', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <div class="toggle-item-wrapper" style="<?php echo esc_attr( $pro_style_toggle ); ?>">
                        <div class="f12-checkbox-toggle">
                            <div class="toggle-container">
								<?php
								$field_name = 'mx_validation_enabled';
								echo sprintf(
									'<input name="%s" type="hidden" value="%s" id="%s" class="toggle" data-before="%s" data-after="%s">',
									esc_attr( $field_name ),
									esc_attr( $is_pro ? ( $settings[ $field_name ] ?? 0 ) : 0 ),
									esc_attr( $field_name ),
									esc_attr__( 'On', 'double-opt-in' ),
									esc_attr__( 'Off', 'double-opt-in' )
								);
								?>
                                <label for="<?php echo esc_attr( $field_name ); ?>"
                                       class="toggle-label"></label>
                            </div>
                            <label for="<?php echo esc_attr( $field_name ); ?>">
								<?php _e( 'Validate the email domain via MX record lookup before sending the confirmation email. This prevents opt-ins for mistyped domains (e.g. gmial.com) and saves resources.', 'double-opt-in' ); ?>
                            </label>
                            <label class="overlay" for="<?php echo esc_attr( $field_name ); ?>"></label>
                        </div>
                    </div>
					<?php if ( ! $is_pro ) : ?>
                        <span style="display:inline-block;background:linear-gradient(135deg,#e6a817,#d4941a);color:#fff;font-size:10px;font-weight:700;line-height:1;padding:3px 6px;border-radius:3px;letter-spacing:.5px;text-transform:uppercase;"
                              title="<?php esc_attr_e( 'This feature is available in the Pro version.', 'double-opt-in' ); ?>">PRO</span>
					<?php endif; ?>
                </div>
            </div>

            <div class="option" style="<?php echo esc_attr( $pro_style_wrap ); ?>">
                <div class="label">
                    <label for="mx_validation_behavior"><?php _e( 'Validation Behavior', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <select id="mx_validation_behavior" name="mx_validation_behavior"<?php echo $pro_disabled; ?>>
                        <option value="silent" <?php selected( $settings['mx_validation_behavior'] ?? 'silent', 'silent' ); ?>>
							<?php _e( 'Silent rejection (form appears normal, no opt-in created)', 'double-opt-in' ); ?>
                        </option>
                        <option value="block" <?php selected( $settings['mx_validation_behavior'] ?? 'silent', 'block' ); ?>>
							<?php _e( 'Show error message (form displays an error to the user)', 'double-opt-in' ); ?>
                        </option>
                    </select>
                    <p>
						<?php _e( 'Choose how to handle submissions with an invalid email domain. "Silent" discards the submission without notifying the user. "Block" shows an error message so the user can correct the email address.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option" style="<?php echo esc_attr( $pro_style_wrap ); ?>">
                <div class="label">
                    <label for="mx_validation_message"><?php _e( 'Custom Error Message', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <input type="text" id="mx_validation_message" name="mx_validation_message"
                           value="<?php echo esc_attr( $settings['mx_validation_message'] ?? '' ); ?>"
                           class="regular-text"<?php echo $pro_disabled; ?>
                           placeholder="<?php esc_attr_e( 'The email domain does not appear to accept emails. Please check your email address.', 'double-opt-in' ); ?>" />
                    <p>
						<?php _e( 'Optional custom error message shown when the email domain fails MX validation. Only used when behavior is set to "Block". Leave empty to use the default message.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option" style="<?php echo esc_attr( $pro_style_wrap ); ?>">
                <div class="label"></div>
                <div class="input">
                    <div style="background:#f0f6fc;border:1px solid #c3d8ef;border-radius:4px;padding:12px 16px;">
                        <strong><?php _e( 'How does MX validation work?', 'double-opt-in' ); ?></strong>
                        <p style="margin:8px 0 0;">
							<?php _e( 'Before sending a confirmation email, the plugin checks whether the email domain has valid MX (Mail Exchange) records. If no MX record is found, it falls back to an A record check. This catches common typos like "gmial.com" or "gogle.com" before wasting email resources. DNS results are cached to minimize lookup overhead. If a DNS lookup fails or times out, the domain is treated as valid (fail-open) to avoid false positives.', 'double-opt-in' ); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="option">
                <div class="label">
                    <label for="domain_blocklist_enabled"><?php _e( 'Domain Blocklist', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <div class="toggle-item-wrapper" style="<?php echo esc_attr( $pro_style_toggle ); ?>">
                        <div class="f12-checkbox-toggle">
                            <div class="toggle-container">
								<?php
								$field_name = 'domain_blocklist_enabled';
								echo sprintf(
									'<input name="%s" type="hidden" value="%s" id="%s" class="toggle" data-before="%s" data-after="%s">',
									esc_attr( $field_name ),
									esc_attr( $is_pro ? ( $settings[ $field_name ] ?? 0 ) : 0 ),
									esc_attr( $field_name ),
									esc_attr__( 'On', 'double-opt-in' ),
									esc_attr__( 'Off', 'double-opt-in' )
								);
								?>
                                <label for="<?php echo esc_attr( $field_name ); ?>"
                                       class="toggle-label"></label>
                            </div>
                            <label for="<?php echo esc_attr( $field_name ); ?>">
								<?php _e( 'Block specific email domains (e.g. disposable email services) from submitting opt-in forms.', 'double-opt-in' ); ?>
                            </label>
                            <label class="overlay" for="<?php echo esc_attr( $field_name ); ?>"></label>
                        </div>
                    </div>
					<?php if ( ! $is_pro ) : ?>
                        <span style="display:inline-block;background:linear-gradient(135deg,#e6a817,#d4941a);color:#fff;font-size:10px;font-weight:700;line-height:1;padding:3px 6px;border-radius:3px;letter-spacing:.5px;text-transform:uppercase;"
                              title="<?php esc_attr_e( 'This feature is available in the Pro version.', 'double-opt-in' ); ?>">PRO</span>
					<?php endif; ?>
                </div>
            </div>

            <div class="option" style="<?php echo esc_attr( $pro_style_wrap ); ?>">
                <div class="label">
                    <label for="domain_blocklist"><?php _e( 'Blocked Domains', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <textarea id="domain_blocklist" name="domain_blocklist" rows="6" class="large-text"<?php echo $pro_disabled; ?>
                              placeholder="tempmail.com&#10;guerrillamail.com&#10;throwaway.email"><?php echo esc_textarea( $settings['domain_blocklist'] ?? '' ); ?></textarea>
                    <p>
						<?php _e( 'Enter one domain per line. Emails from these domains will be rejected during opt-in submission.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option" style="<?php echo esc_attr( $pro_style_wrap ); ?>">
                <div class="label">
                    <label for="domain_blocklist_behavior"><?php _e( 'Blocklist Behavior', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <select id="domain_blocklist_behavior" name="domain_blocklist_behavior"<?php echo $pro_disabled; ?>>
                        <option value="silent" <?php selected( $settings['domain_blocklist_behavior'] ?? 'silent', 'silent' ); ?>>
							<?php _e( 'Silent rejection (form appears normal, no opt-in created)', 'double-opt-in' ); ?>
                        </option>
                        <option value="block" <?php selected( $settings['domain_blocklist_behavior'] ?? 'silent', 'block' ); ?>>
							<?php _e( 'Show error message (form displays an error to the user)', 'double-opt-in' ); ?>
                        </option>
                    </select>
                    <p>
						<?php _e( 'Choose how to handle submissions from blocked email domains. "Silent" discards the submission without notifying the user. "Block" shows an error message so the user can use a different email address.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option" style="<?php echo esc_attr( $pro_style_wrap ); ?>">
                <div class="label">
                    <label for="domain_blocklist_message"><?php _e( 'Custom Error Message', 'double-opt-in' ); ?></label>
                </div>
                <div class="input">
                    <input type="text" id="domain_blocklist_message" name="domain_blocklist_message"
                           value="<?php echo esc_attr( $settings['domain_blocklist_message'] ?? '' ); ?>"
                           class="regular-text"<?php echo $pro_disabled; ?>
                           placeholder="<?php esc_attr_e( 'This email domain is not allowed. Please use a different email address.', 'double-opt-in' ); ?>" />
                    <p>
						<?php _e( 'Optional custom error message shown when a blocked domain is used. Only used when behavior is set to "Block". Leave empty to use the default message.', 'double-opt-in' ); ?>
                    </p>
                </div>
            </div>

            <div class="option" style="<?php echo esc_attr( $pro_style_wrap ); ?>">
                <div class="label"></div>
                <div class="input">
                    <div style="background:#f0f6fc;border:1px solid #c3d8ef;border-radius:4px;padding:12px 16px;">
                        <strong><?php _e( 'How does the Domain Blocklist work?', 'double-opt-in' ); ?></strong>
                        <p style="margin:8px 0 0;">
							<?php _e( 'The domain blocklist prevents opt-in submissions from disposable or unwanted email domains. When enabled, the plugin extracts the domain from the submitted email address and checks it against your configured list. This is useful for blocking temporary email services (e.g. tempmail.com, guerrillamail.com) that are commonly used for spam. The check is case-insensitive and runs alongside other validations like MX record checking.', 'double-opt-in' ); ?>
                        </p>
                    </div>
                </div>
            </div>
			<?php
			$this->get_logger()->info( 'Triggering "f12_cf7_doubleoptin_ui_settings_render" action.', [
				'plugin' => $this->domain,
			] );

			do_action( 'f12_cf7_doubleoptin_ui_settings_render', $settings );

			$this->get_logger()->notice( 'Settings page content rendered successfully.', [
				'plugin' => $this->domain,
			] );
		}

		/**
		 * Display the sidebar content for the specified page.
		 *
		 * @param string $slug The slug of the current page.
		 * @param string $page The current page.
		 *
		 * @return void
		 */
		protected function theSidebar( $slug, $page ) {
			$this->get_logger()->info( 'Attempting to render the sidebar for the "settings" page.', [
				'plugin'    => $this->domain,
				'page_slug' => $page,
			] );

			// Check if the current page is the 'settings' page, and return if it's not.
			if ( $page != 'settings' ) {
				$this->get_logger()->debug( 'Skipping sidebar rendering because the page is not "settings".', [
					'plugin'    => $this->domain,
					'page_slug' => $page,
				] );

				return;
			}

			$this->get_logger()->notice( 'Rendering sidebar content for the "settings" page.', [
				'plugin' => $this->domain,
			] );
			?>
            <div class="box">
                <h2>
					<?php _e( 'Hint:', 'double-opt-in' ); ?>
                </h2>
                <p>
					<?php _e( "In the table on the left side, you'll find an list global settings. They will affect all forms using the double opt-in.", 'double-opt-in' ); ?>
                </p>
            </div>
			<?php
			$this->get_logger()->notice( 'Sidebar for the "settings" page rendered successfully.', [
				'plugin' => $this->domain,
			] );
		}

		/**
		 * Retrieves the settings array.
		 *
		 * @param array $settings The existing settings array.
		 *
		 * @return array The updated settings array.
		 */
		public function getSettings( $settings ) {
			$this->get_logger()->info( 'getSettings method called. Merging default settings with existing ones.', [
				'plugin'    => $this->domain,
				'page_slug' => $this->slug,
			] );

			// Define the default settings for this page.
			$default_settings = array(
				'telemetry'                 => 1,
				'delete'                    => 12,
				'delete_unconfirmed'        => 7,
				'delete_period'             => 'months',
				'delete_unconfirmed_period' => 'months',
				'privacy_policy_page'       => 0,
				'token_expiry_hours'        => 48,
				'rate_limit_ip'             => 5,
				'rate_limit_email'          => 3,
				'rate_limit_window'         => 60,
				'reminder_enabled'          => 0,
				'reminder_delay'            => 24,
				'reminder_template'         => '',
				'reminder_subject'          => '',
				'mx_validation_enabled'     => 0,
				'mx_validation_behavior'    => 'silent',
				'mx_validation_message'     => '',
				'domain_blocklist_enabled'  => 0,
				'domain_blocklist'          => '',
				'domain_blocklist_behavior' => 'silent',
				'domain_blocklist_message'  => '',
			);

			// Merge the default settings with the provided settings array.
			// Defaults serve as base, existing settings override them.
			$settings = array_merge( $default_settings, $settings );

			$this->get_logger()->debug( 'Merged settings: ' . json_encode( $settings ), [
				'plugin' => $this->domain,
			] );

			return $settings;
		}

		/**
		 * Update the settings based on the POST data.
		 *
		 * @param array $settings The current settings.
		 *
		 * @return array The updated settings.
		 */
		protected function onSave( $settings ) {
			$this->get_logger()->info( 'Executing onSave method for the "Settings" UI page.', [
				'plugin' => $this->domain,
			] );

			// Update the 'delete' setting if it exists in the POST data.
			if ( isset( $_POST['delete'] ) ) {
				$settings['delete'] = (int) $_POST['delete'];
				$this->get_logger()->debug( 'Updated "delete" setting to: ' . $settings['delete'], [
					'plugin' => $this->domain,
				] );
			}

			// Update the 'delete_unconfirmed' setting if it exists in the POST data.
			if ( isset( $_POST['delete_unconfirmed'] ) ) {
				$settings['delete_unconfirmed'] = (int) $_POST['delete_unconfirmed'];
				$this->get_logger()->debug( 'Updated "delete_unconfirmed" setting to: ' . $settings['delete_unconfirmed'], [
					'plugin' => $this->domain,
				] );
			}

			if ( isset( $_POST['telemetry'] ) ) {
				$settings['telemetry'] = (int) $_POST['telemetry'];
				$this->get_logger()->debug( 'Updated "telemetry" setting to: ' . $settings['telemetry'], [
					'plugin' => $this->domain,
				] );
			} else {
				// If the checkbox is not set, it means the user unchecked it, so set the value to 0.
				$settings['telemetry'] = 0;
				$this->get_logger()->debug( 'Updated "telemetry" setting to 0 (unchecked).', [
					'plugin' => $this->domain,
				] );
			}

			// Update the 'delete_period' setting, sanitizing the input as a string.
			if ( isset( $_POST['delete_period'] ) ) {
				$settings['delete_period'] = sanitize_text_field( $_POST['delete_period'] );
				$this->get_logger()->debug( 'Updated "delete_period" setting to: ' . $settings['delete_period'], [
					'plugin' => $this->domain,
				] );
			}

			// Update the 'delete_unconfirmed_period' setting, sanitizing the input as a string.
			if ( isset( $_POST['delete_unconfirmed_period'] ) ) {
				$settings['delete_unconfirmed_period'] = sanitize_text_field( $_POST['delete_unconfirmed_period'] );
				$this->get_logger()->debug( 'Updated "delete_unconfirmed_period" setting to: ' . $settings['delete_unconfirmed_period'], [
					'plugin' => $this->domain,
				] );
			}

			// Update the 'privacy_policy_page' setting.
			if ( isset( $_POST['privacy_policy_page'] ) ) {
				$settings['privacy_policy_page'] = absint( $_POST['privacy_policy_page'] );
				$this->get_logger()->debug( 'Updated "privacy_policy_page" setting to: ' . $settings['privacy_policy_page'], [
					'plugin' => $this->domain,
				] );
			}

			// Update the 'token_expiry_hours' setting.
			if ( isset( $_POST['token_expiry_hours'] ) ) {
				$settings['token_expiry_hours'] = (int) $_POST['token_expiry_hours'];
				$this->get_logger()->debug( 'Updated "token_expiry_hours" setting to: ' . $settings['token_expiry_hours'], [
					'plugin' => $this->domain,
				] );
			}

			// Update the 'rate_limit_ip' setting.
			if ( isset( $_POST['rate_limit_ip'] ) ) {
				$settings['rate_limit_ip'] = (int) $_POST['rate_limit_ip'];
				$this->get_logger()->debug( 'Updated "rate_limit_ip" setting to: ' . $settings['rate_limit_ip'], [
					'plugin' => $this->domain,
				] );
			}

			// Update the 'rate_limit_email' setting.
			if ( isset( $_POST['rate_limit_email'] ) ) {
				$settings['rate_limit_email'] = (int) $_POST['rate_limit_email'];
				$this->get_logger()->debug( 'Updated "rate_limit_email" setting to: ' . $settings['rate_limit_email'], [
					'plugin' => $this->domain,
				] );
			}

			// Update the 'rate_limit_window' setting.
			if ( isset( $_POST['rate_limit_window'] ) ) {
				$settings['rate_limit_window'] = max( 1, (int) $_POST['rate_limit_window'] );
				$this->get_logger()->debug( 'Updated "rate_limit_window" setting to: ' . $settings['rate_limit_window'], [
					'plugin' => $this->domain,
				] );
			}

			// Update reminder settings (Pro only).
			if ( apply_filters( 'f12_doi_is_pro_active', false ) ) {
				if ( isset( $_POST['reminder_enabled'] ) ) {
					$settings['reminder_enabled'] = (int) $_POST['reminder_enabled'];
					$this->get_logger()->debug( 'Updated "reminder_enabled" setting to: ' . $settings['reminder_enabled'], [
						'plugin' => $this->domain,
					] );
				} else {
					$settings['reminder_enabled'] = 0;
				}

				if ( isset( $_POST['reminder_delay'] ) ) {
					$settings['reminder_delay'] = max( 1, min( 720, (int) $_POST['reminder_delay'] ) );
					$this->get_logger()->debug( 'Updated "reminder_delay" setting to: ' . $settings['reminder_delay'], [
						'plugin' => $this->domain,
					] );
				}

				if ( isset( $_POST['reminder_template'] ) ) {
					$settings['reminder_template'] = sanitize_text_field( $_POST['reminder_template'] );
					$this->get_logger()->debug( 'Updated "reminder_template" setting to: ' . $settings['reminder_template'], [
						'plugin' => $this->domain,
					] );
				}

				if ( isset( $_POST['reminder_subject'] ) ) {
					$settings['reminder_subject'] = sanitize_text_field( $_POST['reminder_subject'] );
					$this->get_logger()->debug( 'Updated "reminder_subject" setting to: ' . $settings['reminder_subject'], [
						'plugin' => $this->domain,
					] );
				}
				// Update MX validation settings (Pro only).
				if ( isset( $_POST['mx_validation_enabled'] ) ) {
					$settings['mx_validation_enabled'] = (int) $_POST['mx_validation_enabled'];
					$this->get_logger()->debug( 'Updated "mx_validation_enabled" setting to: ' . $settings['mx_validation_enabled'], [
						'plugin' => $this->domain,
					] );
				} else {
					$settings['mx_validation_enabled'] = 0;
				}

				if ( isset( $_POST['mx_validation_behavior'] ) ) {
					$behavior = sanitize_text_field( $_POST['mx_validation_behavior'] );
					$settings['mx_validation_behavior'] = in_array( $behavior, [ 'silent', 'block' ], true ) ? $behavior : 'silent';
					$this->get_logger()->debug( 'Updated "mx_validation_behavior" setting to: ' . $settings['mx_validation_behavior'], [
						'plugin' => $this->domain,
					] );
				}

				if ( isset( $_POST['mx_validation_message'] ) ) {
					$settings['mx_validation_message'] = sanitize_text_field( $_POST['mx_validation_message'] );
					$this->get_logger()->debug( 'Updated "mx_validation_message" setting to: ' . $settings['mx_validation_message'], [
						'plugin' => $this->domain,
					] );
				}

				// Update Domain Blocklist settings (Pro only).
				if ( isset( $_POST['domain_blocklist_enabled'] ) ) {
					$settings['domain_blocklist_enabled'] = (int) $_POST['domain_blocklist_enabled'];
					$this->get_logger()->debug( 'Updated "domain_blocklist_enabled" setting to: ' . $settings['domain_blocklist_enabled'], [
						'plugin' => $this->domain,
					] );
				} else {
					$settings['domain_blocklist_enabled'] = 0;
				}

				if ( isset( $_POST['domain_blocklist'] ) ) {
					$settings['domain_blocklist'] = sanitize_textarea_field( $_POST['domain_blocklist'] );
					$this->get_logger()->debug( 'Updated "domain_blocklist" setting.', [
						'plugin' => $this->domain,
					] );
				}

				if ( isset( $_POST['domain_blocklist_behavior'] ) ) {
					$behavior = sanitize_text_field( $_POST['domain_blocklist_behavior'] );
					$settings['domain_blocklist_behavior'] = in_array( $behavior, [ 'silent', 'block' ], true ) ? $behavior : 'silent';
					$this->get_logger()->debug( 'Updated "domain_blocklist_behavior" setting to: ' . $settings['domain_blocklist_behavior'], [
						'plugin' => $this->domain,
					] );
				}

				if ( isset( $_POST['domain_blocklist_message'] ) ) {
					$settings['domain_blocklist_message'] = sanitize_text_field( $_POST['domain_blocklist_message'] );
					$this->get_logger()->debug( 'Updated "domain_blocklist_message" setting to: ' . $settings['domain_blocklist_message'], [
						'plugin' => $this->domain,
					] );
				}
			} else {
				// Ensure reminder stays disabled for non-Pro
				$settings['reminder_enabled'] = 0;
				// Ensure MX validation stays disabled for non-Pro
				$settings['mx_validation_enabled'] = 0;
				// Ensure Domain Blocklist stays disabled for non-Pro
				$settings['domain_blocklist_enabled'] = 0;
				$this->get_logger()->debug( 'Pro-only settings not saved: Pro version required.', [
					'plugin' => $this->domain,
				] );
			}

			$this->get_logger()->notice( 'onSave method finished. Returning the updated settings array.', [
				'plugin'         => $this->domain,
				'final_settings' => $settings,
			] );

			// Return the updated settings array to be saved.
			return $settings;
		}
	}
}