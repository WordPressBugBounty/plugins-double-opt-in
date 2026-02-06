<?php
/**
 * Container Not Found Exception
 *
 * @package Forge12\DoubleOptIn\Container
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NotFoundException
 *
 * Exception thrown when a requested entry is not found in the container.
 */
class NotFoundException extends \Exception {
}
