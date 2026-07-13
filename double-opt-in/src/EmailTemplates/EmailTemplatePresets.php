<?php
/**
 * Email Template Presets
 *
 * Provides predefined template presets based on the legacy HTML templates.
 *
 * @package Forge12\DoubleOptIn\EmailTemplates
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EmailTemplates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailTemplatePresets
 *
 * Contains predefined email template presets that users can choose as starting points.
 */
class EmailTemplatePresets {

	/**
	 * Get all available presets.
	 *
	 * @return array
	 */
	public static function getAll(): array {
		return array(
			self::getBlankPreset(),
			self::getDarkPreset(),
			self::getYellowBlackPreset(),
			self::getMinimalPreset(),
			self::getOptOutPreset(),
		);
	}

	/**
	 * Get a preset by ID.
	 *
	 * @param string $presetId Preset ID.
	 * @return array|null
	 */
	public static function getById( string $presetId ): ?array {
		$presets = self::getAll();
		foreach ( $presets as $preset ) {
			if ( $preset['id'] === $presetId ) {
				return $preset;
			}
		}
		return null;
	}

	/**
	 * Blank preset - empty template.
	 *
	 * @return array
	 */
	private static function getBlankPreset(): array {
		return array(
			'id'           => 'blank',
			'name'         => __( 'Blank Template', 'double-opt-in' ),
			'description'  => __( 'Start from scratch with an empty template.', 'double-opt-in' ),
			'thumbnail'    => 'blank',
			'category'     => 'basic',
			'blocks'       => array(),
			'globalStyles' => array(
				'fontFamily'      => 'Arial, Helvetica, sans-serif',
				'fontSize'        => '16px',
				'lineHeight'      => '1.5',
				'backgroundColor' => '#f4f4f4',
				'contentWidth'    => '600',
				'primaryColor'    => '#0073aa',
				'textColor'       => '#333333',
				'linkColor'       => '#0073aa',
			),
		);
	}

	/**
	 * Dark theme preset (based on newsletter_en.html).
	 *
	 * @return array
	 */
	private static function getDarkPreset(): array {
		return array(
			'id'           => 'dark-professional',
			'name'         => __( 'Dark Professional', 'double-opt-in' ),
			'description'  => __( 'A professional dark-themed template with a red accent color.', 'double-opt-in' ),
			'thumbnail'    => 'dark-professional',
			'category'     => 'professional',
			'blocks'       => array(
				// Email Wrapper
				array(
					'type'       => 'email-wrapper',
					'attributes' => array(
						'backgroundColor' => '#2d2d31',
						'padding'         => '30px',
					),
					'children'   => array(
						// Header Row (wrapping columns-2 inside a row)
						array(
							'type'       => 'row',
							'attributes' => array(
								'backgroundColor' => '#2d2d31',
								'padding'         => '20px 0',
							),
							'children'   => array(
								array(
									'type'       => 'columns-2',
									'attributes' => array(
										'backgroundColor' => 'transparent',
									),
									'children'   => array(
										// Logo Column
										array(
											'type'       => 'image',
											'attributes' => array(
												'src'   => '',
												'alt'   => 'Logo',
												'width' => '150',
												'align' => 'left',
												'_columnIndex' => 0,
											),
										),
										// Navigation Link Column
										array(
											'type'       => 'text',
											'attributes' => array(
												'content' => '<a href="#" style="color:#ffffff;text-decoration:underline;text-transform:uppercase;font-size:11px;">GO TO THE WEBSITE</a>',
												'align'   => 'right',
												'color'   => '#ffffff',
												'_columnIndex' => 1,
											),
										),
									),
								),
							),
						),
						// Spacer
						array(
							'type'       => 'spacer',
							'attributes' => array(
								'height' => '20',
							),
						),
						// Main Content Row
						array(
							'type'       => 'row',
							'attributes' => array(
								'backgroundColor' => '#2d2d31',
								'padding'         => '30px',
							),
							'children'   => array(
								// Heading
								array(
									'type'       => 'heading',
									'attributes' => array(
										'content' => __( 'Please confirm your Registration', 'double-opt-in' ),
										'level'   => 'h2',
										'align'   => 'center',
										'color'   => '#f6f6f6',
									),
								),
								// Spacer
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '20',
									),
								),
								// Text Content
								array(
									'type'       => 'text',
									'attributes' => array(
										'content' => __( 'Dear Mr./Mrs.,<br><br>This email address was just used ([doubleoptin_form_date], [doubleoptin_form_time]) to request our newsletter on [doubleoptin_form_url]. If you did not submit this request, please ignore this email and we apologize for disturbing you.<br><br>If you would like to receive our newsletter by email, please confirm your request by clicking on the link below. We will then add you to our newsletter mailing list. You can unsubscribe at any time in the future from our newsletter.<br><br>By clicking on this link, you confirm your registration for our newsletter.', 'double-opt-in' ),
										'align'   => 'left',
										'color'   => '#b6b6b6',
									),
								),
								// Spacer
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '25',
									),
								),
								// Confirm Button
								array(
									'type'       => 'button',
									'attributes' => array(
										'text'            => __( 'CONFIRM', 'double-opt-in' ),
										'url'             => '[doubleoptinlink]',
										'backgroundColor' => '#ed5258',
										'textColor'       => '#ffffff',
										'align'           => 'center',
										'borderRadius'    => '0',
										'padding'         => '12px 24px',
									),
								),
								// Spacer
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '25',
									),
								),
								// Alternative Link Text
								array(
									'type'       => 'text',
									'attributes' => array(
										'content'  => __( 'If you have trouble clicking on the button above please copy the following link into your internet browser:<br>[doubleoptinlink]', 'double-opt-in' ),
										'align'    => 'left',
										'color'    => '#b6b6b6',
										'fontSize' => '14px',
									),
								),
								// Spacer
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '20',
									),
								),
								// Closing Text
								array(
									'type'       => 'text',
									'attributes' => array(
										'content' => __( 'Thank you for registering.<br>Kind regards,<br>Your Newsletter Team', 'double-opt-in' ),
										'align'   => 'left',
										'color'   => '#b6b6b6',
									),
								),
							),
						),
						// Footer Divider
						array(
							'type'       => 'spacer',
							'attributes' => array(
								'height'          => '60',
								'backgroundColor' => '#ed5258',
							),
						),
						// Footer
						array(
							'type'       => 'footer',
							'attributes' => array(
								'content'         => __( 'Copyright &copy; 2024 Your Company Name', 'double-opt-in' ),
								'backgroundColor' => '#ed5258',
								'textColor'       => '#ffffff',
								'padding'         => '20px',
								'align'           => 'center',
							),
						),
					),
				),
			),
			'globalStyles' => array(
				'fontFamily'      => 'Arial, Helvetica, sans-serif',
				'fontSize'        => '14px',
				'lineHeight'      => '1.5',
				'backgroundColor' => '#ffffff',
				'contentWidth'    => '650',
				'primaryColor'    => '#ed5258',
				'textColor'       => '#b6b6b6',
				'linkColor'       => '#ffffff',
			),
		);
	}

	/**
	 * Yellow/Black theme preset (based on newsletter_en_2.html).
	 *
	 * @return array
	 */
	private static function getYellowBlackPreset(): array {
		return array(
			'id'           => 'yellow-bold',
			'name'         => __( 'Yellow Bold', 'double-opt-in' ),
			'description'  => __( 'A bold design with yellow accents on a dark background.', 'double-opt-in' ),
			'thumbnail'    => 'yellow-bold',
			'category'     => 'creative',
			'blocks'       => array(
				// Email Wrapper
				array(
					'type'       => 'email-wrapper',
					'attributes' => array(
						'backgroundColor' => '#1f1e23',
						'padding'         => '40px 0',
					),
					'children'   => array(
						// Top Bar
						array(
							'type'       => 'row',
							'attributes' => array(
								'backgroundColor' => '#373737',
								'padding'         => '15px 30px',
							),
							'children'   => array(
								array(
									'type'       => 'columns-2',
									'attributes' => array(),
									'children'   => array(
										array(
											'type'       => 'text',
											'attributes' => array(
												'content'  => __( 'Welcome to Company Name', 'double-opt-in' ),
												'color'    => '#ffffff',
												'fontSize' => '11px',
												'align'    => 'left',
												'_columnIndex' => 0,
											),
										),
										array(
											'type'       => 'text',
											'attributes' => array(
												'content' => '<a href="#" style="color:#fff;text-decoration:underline;text-transform:uppercase;font-size:11px;">GO TO WEBSITE</a>',
												'align'   => 'right',
												'_columnIndex' => 1,
											),
										),
									),
								),
							),
						),
						// Logo Section
						array(
							'type'       => 'row',
							'attributes' => array(
								'backgroundColor' => '#f6de17',
								'padding'         => '30px',
							),
							'children'   => array(
								array(
									'type'       => 'image',
									'attributes' => array(
										'src'   => '',
										'alt'   => 'Logo',
										'width' => '200',
										'align' => 'left',
									),
								),
							),
						),
						// Main Content
						array(
							'type'       => 'row',
							'attributes' => array(
								'backgroundColor' => '#f6de17',
								'padding'         => '30px',
							),
							'children'   => array(
								array(
									'type'       => 'heading',
									'attributes' => array(
										'content' => __( 'Please confirm your Registration', 'double-opt-in' ),
										'level'   => 'h2',
										'align'   => 'center',
										'color'   => '#26252a',
									),
								),
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '25',
									),
								),
								array(
									'type'       => 'text',
									'attributes' => array(
										'content' => __( 'Dear Mr./Mrs.,<br><br>This email address was just used ([doubleoptin_form_date], [doubleoptin_form_time]) to request our newsletter on [doubleoptin_form_url]. If you did not submit this request, please ignore this email and we apologize for disturbing you.<br><br>If you would like to receive our newsletter by email, please confirm your request by clicking on the link below. We will then add you to our newsletter mailing list. You can unsubscribe at any time in the future from our newsletter.<br><br>By clicking on this link, you confirm your registration for our newsletter.', 'double-opt-in' ),
										'align'   => 'center',
										'color'   => '#26252a',
									),
								),
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '22',
									),
								),
								array(
									'type'       => 'button',
									'attributes' => array(
										'text'            => __( 'CONFIRM', 'double-opt-in' ),
										'url'             => '[doubleoptinlink]',
										'backgroundColor' => '#2e2d33',
										'textColor'       => '#f6de17',
										'align'           => 'center',
										'borderRadius'    => '0',
										'padding'         => '12px 24px',
									),
								),
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '20',
									),
								),
								array(
									'type'       => 'text',
									'attributes' => array(
										'content'  => __( 'If you have trouble clicking on the button above please copy the following link into your internet browser:<br>[doubleoptinlink]', 'double-opt-in' ),
										'align'    => 'center',
										'color'    => '#26252a',
										'fontSize' => '14px',
									),
								),
							),
						),
						// Spacer
						array(
							'type'       => 'spacer',
							'attributes' => array(
								'height'          => '22',
								'backgroundColor' => '#f6de17',
							),
						),
						// Footer Logo
						array(
							'type'       => 'row',
							'attributes' => array(
								'backgroundColor' => '#373737',
								'padding'         => '40px 30px',
							),
							'children'   => array(
								array(
									'type'       => 'image',
									'attributes' => array(
										'src'   => '',
										'alt'   => 'Logo',
										'width' => '150',
										'align' => 'center',
									),
								),
							),
						),
						// Footer Text
						array(
							'type'       => 'footer',
							'attributes' => array(
								'content'         => __( 'Copyright &copy; 2024 Company Name', 'double-opt-in' ),
								'backgroundColor' => '#373737',
								'textColor'       => '#ffffff',
								'padding'         => '15px 30px 40px',
								'align'           => 'center',
							),
						),
					),
				),
			),
			'globalStyles' => array(
				'fontFamily'      => 'Arial, Helvetica, sans-serif',
				'fontSize'        => '14px',
				'lineHeight'      => '1.75',
				'backgroundColor' => '#1f1e23',
				'contentWidth'    => '650',
				'primaryColor'    => '#f6de17',
				'textColor'       => '#26252a',
				'linkColor'       => '#ffffff',
			),
		);
	}

	/**
	 * Minimal/Clean theme preset (based on newsletter_en_3.html).
	 *
	 * @return array
	 */
	private static function getMinimalPreset(): array {
		return array(
			'id'           => 'minimal-clean',
			'name'         => __( 'Minimal Clean', 'double-opt-in' ),
			'description'  => __( 'A clean, minimal design with a blue accent - perfect for professional communications.', 'double-opt-in' ),
			'thumbnail'    => 'minimal-clean',
			'category'     => 'minimal',
			'blocks'       => array(
				// Email Wrapper
				array(
					'type'       => 'email-wrapper',
					'attributes' => array(
						'backgroundColor' => '#f6f6f6',
						'padding'         => '10px',
					),
					'children'   => array(
						// Main Content Box
						array(
							'type'       => 'row',
							'attributes' => array(
								'backgroundColor' => '#ffffff',
								'padding'         => '20px',
								'borderRadius'    => '3',
							),
							'children'   => array(
								// Text Content
								array(
									'type'       => 'text',
									'attributes' => array(
										'content' => __( 'Dear Mr./Mrs.,<br><br>This email address was just used ([doubleoptin_form_date], [doubleoptin_form_time]) to request our newsletter on [doubleoptin_form_url]. If you did not submit this request, please ignore this email and we apologize for disturbing you.<br><br>If you would like to receive our newsletter by email, please confirm your request by clicking on the link below. We will then add you to our newsletter mailing list. You can unsubscribe at any time in the future from our newsletter.<br><br>By clicking on this link, you confirm your registration for our newsletter.', 'double-opt-in' ),
										'align'   => 'left',
										'color'   => '#333333',
									),
								),
								// Spacer
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '15',
									),
								),
								// Confirm Button
								array(
									'type'       => 'button',
									'attributes' => array(
										'text'            => __( 'CONFIRM', 'double-opt-in' ),
										'url'             => '[doubleoptinlink]',
										'backgroundColor' => '#3498db',
										'textColor'       => '#ffffff',
										'align'           => 'left',
										'borderRadius'    => '5',
										'padding'         => '12px 25px',
									),
								),
								// Spacer
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '15',
									),
								),
								// Alternative Link
								array(
									'type'       => 'text',
									'attributes' => array(
										'content'  => __( 'If you have trouble clicking on the button above please copy the following link into your internet browser:<br>[doubleoptinlink]', 'double-opt-in' ),
										'align'    => 'left',
										'color'    => '#333333',
										'fontSize' => '14px',
									),
								),
								// Spacer
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '15',
									),
								),
								// Closing
								array(
									'type'       => 'text',
									'attributes' => array(
										'content' => __( 'Thank you for registering.<br>Kind regards,<br>Your Newsletter Team.', 'double-opt-in' ),
										'align'   => 'left',
										'color'   => '#333333',
									),
								),
							),
						),
						// Spacer before footer
						array(
							'type'       => 'spacer',
							'attributes' => array(
								'height' => '10',
							),
						),
						// Footer
						array(
							'type'       => 'footer',
							'attributes' => array(
								'content'         => __( 'Maxmuster Company, Musterstreet 123, Mustercity 71337', 'double-opt-in' ),
								'backgroundColor' => 'transparent',
								'textColor'       => '#999999',
								'padding'         => '10px',
								'align'           => 'center',
								'fontSize'        => '12px',
							),
						),
					),
				),
			),
			'globalStyles' => array(
				'fontFamily'      => 'Arial, Helvetica, sans-serif',
				'fontSize'        => '14px',
				'lineHeight'      => '1.5',
				'backgroundColor' => '#f6f6f6',
				'contentWidth'    => '580',
				'primaryColor'    => '#3498db',
				'textColor'       => '#333333',
				'linkColor'       => '#3498db',
			),
		);
	}

	/**
	 * Opt-Out confirmation email preset.
	 *
	 * @return array
	 */
	private static function getOptOutPreset(): array {
		return array(
			'id'           => 'opt-out-confirmation',
			'name'         => __( 'Opt-Out Confirmation', 'double-opt-in' ),
			'description'  => __( 'A clean template for opt-out confirmation emails with a link to the opt-out settings page.', 'double-opt-in' ),
			'thumbnail'    => 'minimal-clean',
			'category'     => 'opt-out',
			'blocks'       => array(
				// Email Wrapper
				array(
					'type'       => 'email-wrapper',
					'attributes' => array(
						'backgroundColor' => '#f6f6f6',
						'padding'         => '10px',
					),
					'children'   => array(
						// Main Content Box
						array(
							'type'       => 'row',
							'attributes' => array(
								'backgroundColor' => '#ffffff',
								'padding'         => '20px',
								'borderRadius'    => '3',
							),
							'children'   => array(
								// Text Content
								array(
									'type'       => 'text',
									'attributes' => array(
										'content' => __( 'Dear Mr./Mrs.,<br><br>This email address was just used to request access to the opt-out page on <a href="[doubleoptin_optout_url]">[doubleoptin_optout_url]</a>. If you did not submit this request, please ignore this email and we apologize for disturbing you.', 'double-opt-in' ),
										'align'   => 'left',
										'color'   => '#333333',
									),
								),
								// Spacer
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '15',
									),
								),
								// Opt-Out Button
								array(
									'type'       => 'button',
									'attributes' => array(
										'text'            => __( 'OPT-OUT SETTINGS', 'double-opt-in' ),
										'url'             => '[doubleoptin_optout_url]',
										'backgroundColor' => '#3498db',
										'textColor'       => '#ffffff',
										'align'           => 'left',
										'borderRadius'    => '5',
										'padding'         => '12px 25px',
									),
								),
								// Spacer
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '15',
									),
								),
								// Alternative Link
								array(
									'type'       => 'text',
									'attributes' => array(
										'content'  => __( 'If you have trouble clicking on the button above please copy the following link into your internet browser:<br><a href="[doubleoptin_optout_url]">[doubleoptin_optout_url]</a>', 'double-opt-in' ),
										'align'    => 'left',
										'color'    => '#333333',
										'fontSize' => '14px',
									),
								),
								// Spacer
								array(
									'type'       => 'spacer',
									'attributes' => array(
										'height' => '15',
									),
								),
								// Closing
								array(
									'type'       => 'text',
									'attributes' => array(
										'content' => __( 'Thank you.<br>Kind regards,<br>Your Newsletter Team.', 'double-opt-in' ),
										'align'   => 'left',
										'color'   => '#333333',
									),
								),
							),
						),
						// Spacer before footer
						array(
							'type'       => 'spacer',
							'attributes' => array(
								'height' => '10',
							),
						),
						// Footer
						array(
							'type'       => 'footer',
							'attributes' => array(
								'content'         => __( 'Maxmuster Company, Musterstreet 123, Mustercity 71337', 'double-opt-in' ),
								'backgroundColor' => 'transparent',
								'textColor'       => '#999999',
								'padding'         => '10px',
								'align'           => 'center',
								'fontSize'        => '12px',
							),
						),
					),
				),
			),
			'globalStyles' => array(
				'fontFamily'      => 'Arial, Helvetica, sans-serif',
				'fontSize'        => '14px',
				'lineHeight'      => '1.5',
				'backgroundColor' => '#f6f6f6',
				'contentWidth'    => '580',
				'primaryColor'    => '#3498db',
				'textColor'       => '#333333',
				'linkColor'       => '#3498db',
			),
		);
	}
}
