<?php
/**
 * One place that ships help articles: the core or an addon.
 *
 * @package Forge12\DoubleOptIn\Help
 * @since   5.11.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Help;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HelpSource {

	public const GROUP_CORE        = 'core';
	public const GROUP_MODULE      = 'modules';
	public const GROUP_INTEGRATION = 'integrations';

	/** @var string */
	public $id;

	/** @var string */
	public $name;

	/** @var string Absolute path of the `help` directory, no trailing slash. */
	public $dir;

	/** @var string URL of the `help` directory, with trailing slash. */
	public $url;

	/** @var string One of the GROUP_* constants. */
	public $group;

	public function __construct( string $id, string $name, string $dir, string $url, string $group ) {
		$this->id    = $id;
		$this->name  = $name;
		$this->dir   = rtrim( $dir, '/\\' );
		$this->url   = rtrim( $url, '/' ) . '/';
		$this->group = $group;
	}
}
