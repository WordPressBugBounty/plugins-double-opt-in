<?php
/**
 * Contract between the core and an add-on that manages subscription groups.
 *
 * @package Forge12\DoubleOptIn\Subscription
 * @since   5.12.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @api
 *
 * Consumers (admin list, opt-out page, export, analytics) only ever talk to
 * this interface, never to the add-on that implements it. Without an
 * add-on the {@see NullSubscriptionGroupResolver} answers "no groups" and
 * every consumer behaves exactly as before.
 */
interface SubscriptionGroupResolverInterface {

	/**
	 * The group a record belongs to, or null when it is in none.
	 *
	 * @param int    $formId  The record's form id.
	 * @param string $formRef The record's form instance reference, if any.
	 */
	public function groupForForm( int $formId, string $formRef = '' ): ?SubscriptionGroup;

	/**
	 * A group by its key, or null when it does not exist.
	 */
	public function findGroup( string $key ): ?SubscriptionGroup;

	/**
	 * All groups, for selection lists.
	 *
	 * @return SubscriptionGroup[]
	 */
	public function groups(): array;
}
