<?php
/**
 * Block Registry
 *
 * Which block types the email renderer knows.
 *
 * Up to Core 5.7 this class also decided, by licence, which blocks and how
 * many templates were allowed. Core on wordpress.org may not lock
 * functionality behind a licence (plugin guideline 5), and editing
 * templates is the email editor add-on's feature anyway; since 5.8 nothing
 * here depends on a licence. The methods the add-on up to 1.0.5 calls are
 * kept so an older add-on keeps working, but they no longer restrict.
 *
 * @package Forge12\DoubleOptIn\EmailTemplates
 * @since   4.2.0
 */

namespace Forge12\DoubleOptIn\EmailTemplates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BlockRegistry
 */
class BlockRegistry {

	/**
	 * Basic blocks.
	 *
	 * @var string[]
	 */
	const FREE_BLOCKS = array(
		'email-wrapper',
		'row',
		'columns-1',
		'heading',
		'text',
		'button',
		'spacer',
		'divider',
		'placeholder-confirm-link',
		'placeholder-optout-link',
		'placeholder-date',
		'placeholder-time',
		'placeholder-url',
		'placeholder-custom',
	);

	/**
	 * Blocks the editor add-on labels as Pro. The renderer handles them
	 * like any other block.
	 *
	 * @var string[]
	 */
	const PRO_BLOCKS = array(
		'columns-2',
		'columns-2-sidebar',
		'columns-3',
		'image',
		'social-icons',
		'header',
		'footer',
		'conditional-content',
	);

	/**
	 * Maximum number of published templates: no limit.
	 *
	 * @deprecated 5.8.0 Kept for the email editor add-on up to 1.0.5.
	 */
	public function getTemplateLimit(): int {
		return PHP_INT_MAX;
	}

	/**
	 * Always true: Core no longer checks licences here.
	 *
	 * @deprecated 5.8.0 Kept for the email editor add-on up to 1.0.5, which
	 *             only runs with a licence anyway.
	 */
	public function isProActive(): bool {
		return true;
	}

	/**
	 * Every known block, all available.
	 *
	 * @deprecated 5.8.0 Kept for the email editor add-on up to 1.0.5.
	 *
	 * @return array<string, array{type: string, available: bool, requiresPro: bool}>
	 */
	public function getBlockAvailability(): array {
		$availability = array();

		foreach ( self::FREE_BLOCKS as $blockType ) {
			$availability[ $blockType ] = array(
				'type'        => $blockType,
				'available'   => true,
				'requiresPro' => false,
			);
		}

		foreach ( self::PRO_BLOCKS as $blockType ) {
			$availability[ $blockType ] = array(
				'type'        => $blockType,
				'available'   => true,
				'requiresPro' => true,
			);
		}

		return $availability;
	}

	/**
	 * Every block type is available.
	 *
	 * @deprecated 5.8.0
	 *
	 * @param string $blockType The block type to check.
	 */
	public function isBlockAvailable( string $blockType ): bool {
		return true;
	}

	/**
	 * No block is refused.
	 *
	 * @deprecated 5.8.0
	 *
	 * @param array $blocks The blocks array.
	 * @return array Always empty.
	 */
	public function validateBlocks( array $blocks ): array {
		return array();
	}
}
