<?php
/**
 * PSR-11 Compatible Container Interface
 *
 * @package Forge12\DoubleOptIn\Container
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ContainerInterface
 *
 * Describes the interface of a container that exposes methods to read its entries.
 */
interface ContainerInterface {

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return mixed Entry.
	 * @throws NotFoundException No entry was found for this identifier.
	 */
	public function get( string $id );

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool;
}
