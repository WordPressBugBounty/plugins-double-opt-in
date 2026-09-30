<?php
/**
 * The confirmation email the setup wizard writes into a form.
 *
 * The designs under mails/ are English only and speak of a newsletter, which
 * is wrong for a contact form and wrong for every German site. This builds the
 * mail from the free editor blocks instead, with neutral, translatable text,
 * and renders it with the same generator that renders saved templates.
 *
 * Only blocks from BlockRegistry::FREE_BLOCKS are used.
 *
 * @package Forge12\DoubleOptIn\Setup
 * @since   5.7.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Setup;

use Forge12\DoubleOptIn\EmailTemplates\EmailHtmlGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SetupMailComposer {

	public const DESIGNS = array( 'light', 'dark', 'accent', 'plain' );

	public const DEFAULT_DESIGN = 'light';

	private const LINK = '[doubleoptinlink]';

	/**
	 * Colours per design: page background, card, text, muted text, button, button text.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const PALETTES = array(
		'light'  => array(
			'page'       => '#f3f4f6',
			'card'       => '#ffffff',
			'text'       => '#1f2937',
			'muted'      => '#6b7280',
			'button'     => '#2271b1',
			'buttonText' => '#ffffff',
		),
		'dark'   => array(
			'page'       => '#0f1216',
			'card'       => '#1c2128',
			'text'       => '#e5e7eb',
			'muted'      => '#9ca3af',
			'button'     => '#3b82f6',
			'buttonText' => '#ffffff',
		),
		'accent' => array(
			'page'       => '#fdf6dd',
			'card'       => '#ffffff',
			'text'       => '#1f2937',
			'muted'      => '#6b7280',
			'button'     => '#f5c518',
			'buttonText' => '#1f2937',
		),
		'plain'  => array(
			'page'       => '#ffffff',
			'card'       => '#ffffff',
			'text'       => '#1f2937',
			'muted'      => '#6b7280',
			'button'     => '#2271b1',
			'buttonText' => '#ffffff',
		),
	);

	/** @var string */
	private $siteName;

	public function __construct( ?string $siteName = null ) {
		$this->siteName = $siteName ?? wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	public static function isDesign( string $design ): bool {
		return in_array( $design, self::DESIGNS, true );
	}

	public function subject(): string {
		return sprintf(
			/* translators: %s: site name */
			__( 'Please confirm your email address for %s', 'double-opt-in' ),
			$this->siteName
		);
	}

	/**
	 * Rendered HTML body, ready for the form's `body` setting.
	 */
	public function body( string $design ): string {
		$design = self::isDesign( $design ) ? $design : self::DEFAULT_DESIGN;
		$colors = self::PALETTES[ $design ];

		return ( new EmailHtmlGenerator() )->generate(
			$this->blocks( $design ),
			array(
				'backgroundColor' => $colors['page'],
				'primaryColor'    => $colors['button'],
				'textColor'       => $colors['text'],
				'linkColor'       => $colors['button'],
				'contentWidth'    => '600',
			)
		);
	}

	/**
	 * The block tree of a design.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function blocks( string $design ): array {
		$design = self::isDesign( $design ) ? $design : self::DEFAULT_DESIGN;
		$colors = self::PALETTES[ $design ];
		$site   = esc_html( $this->siteName );
		$link   = '<a href="' . self::LINK . '" style="color:' . $colors['button'] . ';word-break:break-all;">' . self::LINK . '</a>';

		$children = array(
			$this->block(
				'heading',
				array(
					'level' => 1,
					'text'  => __( 'Please confirm your email address', 'double-opt-in' ),
					'color' => $colors['text'],
				)
			),
			$this->block(
				'text',
				array(
					'content' => sprintf(
						/* translators: %s: site name */
						__( 'Hello,<br><br>this email address was just entered on %s. To make sure it was really you, please confirm it with one click.', 'double-opt-in' ),
						$site
					),
					'color'   => $colors['text'],
				)
			),
		);

		if ( $design === 'plain' ) {
			$children[] = $this->block(
				'text',
				array(
					'content' => $link,
					'color'   => $colors['text'],
				)
			);
		} else {
			$children[] = $this->block( 'spacer', array( 'height' => 8 ) );
			$children[] = $this->block(
				'button',
				array(
					'text'            => __( 'Confirm email address', 'double-opt-in' ),
					'url'             => self::LINK,
					'align'           => 'center',
					'backgroundColor' => $colors['button'],
					'textColor'       => $colors['buttonText'],
					'borderRadius'    => 6,
				)
			);
			$children[] = $this->block( 'spacer', array( 'height' => 8 ) );
			$children[] = $this->block(
				'text',
				array(
					'content'  => __( 'If the button does not work, copy this link into your browser:', 'double-opt-in' ) . '<br>' . $link,
					'color'    => $colors['muted'],
					'fontSize' => '13px',
				)
			);
		}

		$children[] = $this->block( 'divider', array( 'color' => $colors['page'] === $colors['card'] ? '#e5e7eb' : $colors['page'] ) );
		$children[] = $this->block(
			'text',
			array(
				'content'  => __( 'If you did not request this, simply ignore this email. Nothing happens without your confirmation.', 'double-opt-in' )
					. '<br><br>'
					. sprintf(
						/* translators: %s: site name */
						__( 'Kind regards,<br>%s', 'double-opt-in' ),
						$site
					),
				'color'    => $colors['muted'],
				'fontSize' => '14px',
			)
		);

		return array(
			$this->block(
				'email-wrapper',
				array(
					'backgroundColor' => $colors['card'],
					'padding'         => $design === 'plain' ? '8px' : '32px',
				),
				$children
			),
		);
	}

	/**
	 * @param array<string, mixed>             $attributes
	 * @param array<int, array<string, mixed>> $children
	 *
	 * @return array<string, mixed>
	 */
	private function block( string $type, array $attributes, array $children = array() ): array {
		return array(
			'type'       => $type,
			'attributes' => $attributes,
			'children'   => $children,
		);
	}
}
