<?php
/**
 * Block Registry
 *
 * Central registry for email editor blocks with Free/Pro availability.
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
 *
 * Manages block availability based on Free/Pro status.
 */
class BlockRegistry {

	/**
	 * Blocks available in the Free version.
	 *
	 * @var string[]
	 */
	const FREE_BLOCKS = [
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
	];

	/**
	 * Blocks that require the Pro version.
	 *
	 * @var string[]
	 */
	const PRO_BLOCKS = [
		'columns-2',
		'columns-2-sidebar',
		'columns-3',
		'image',
		'social-icons',
		'header',
		'footer',
		'conditional-content',
	];

	/**
	 * Get the template limit based on Pro status.
	 *
	 * @return int Maximum number of published templates allowed.
	 */
	public function getTemplateLimit(): int {
		return $this->isProActive() ? PHP_INT_MAX : 1;
	}

	/**
	 * Check if Pro is active.
	 *
	 * @return bool
	 */
	public function isProActive(): bool {
		/**
		 * Filter to check if the Pro version is active and licensed.
		 *
		 * @param bool $isActive Whether Pro is active. Default false.
		 *
		 * @since 4.2.0
		 */
		return (bool) apply_filters( 'f12_doi_is_pro_active', false );
	}

	/**
	 * Get block availability for all blocks.
	 *
	 * Returns an associative array where each key is a block type
	 * and the value indicates its availability status.
	 *
	 * @return array
	 */
	public function getBlockAvailability(): array {
		$isProActive = $this->isProActive();
		$availability = [];

		foreach ( self::FREE_BLOCKS as $blockType ) {
			$availability[ $blockType ] = [
				'type'        => $blockType,
				'available'   => true,
				'requiresPro' => false,
			];
		}

		foreach ( self::PRO_BLOCKS as $blockType ) {
			$availability[ $blockType ] = [
				'type'        => $blockType,
				'available'   => $isProActive,
				'requiresPro' => true,
			];
		}

		/**
		 * Filter block availability.
		 *
		 * Allows extensions to modify block availability.
		 *
		 * @param array $availability The block availability array.
		 * @param bool  $isProActive  Whether Pro is active.
		 *
		 * @since 4.2.0
		 */
		return apply_filters( 'f12_doi_block_availability', $availability, $isProActive );
	}

	/**
	 * Check if a specific block type is available.
	 *
	 * @param string $blockType The block type to check.
	 *
	 * @return bool True if the block is available.
	 */
	public function isBlockAvailable( string $blockType ): bool {
		if ( in_array( $blockType, self::FREE_BLOCKS, true ) ) {
			return true;
		}

		if ( in_array( $blockType, self::PRO_BLOCKS, true ) ) {
			return $this->isProActive();
		}

		// Unknown block types are allowed by default
		return true;
	}

	/**
	 * Validate blocks recursively, checking that no Pro blocks are used without a license.
	 *
	 * @param array $blocks The blocks array to validate.
	 *
	 * @return array Array of invalid block types found. Empty if all valid.
	 */
	public function validateBlocks( array $blocks ): array {
		$invalidBlocks = [];
		$this->validateBlocksRecursive( $blocks, $invalidBlocks );

		return $invalidBlocks;
	}

	/**
	 * Recursively validate blocks.
	 *
	 * @param array $blocks        The blocks to validate.
	 * @param array $invalidBlocks Reference to collect invalid block types.
	 *
	 * @return void
	 */
	private function validateBlocksRecursive( array $blocks, array &$invalidBlocks ): void {
		foreach ( $blocks as $block ) {
			$type = $block['type'] ?? '';

			if ( ! empty( $type ) && ! $this->isBlockAvailable( $type ) ) {
				$invalidBlocks[] = $type;
			}

			if ( ! empty( $block['children'] ) && is_array( $block['children'] ) ) {
				$this->validateBlocksRecursive( $block['children'], $invalidBlocks );
			}
		}
	}
}
