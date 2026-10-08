<?php
/**
 * Entry point for consumers that need subscription groups.
 *
 * @package Forge12\DoubleOptIn\Subscription
 * @since   5.12.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SubscriptionGroups {

	/**
	 * The active resolver. An add-on supplies its own through the
	 * `f12_doi_subscription_group_resolver` filter; anything that is not a
	 * resolver is ignored so a faulty add-on cannot break a consumer.
	 */
	public static function resolver(): SubscriptionGroupResolverInterface {
		$resolver = apply_filters( 'f12_doi_subscription_group_resolver', new NullSubscriptionGroupResolver() );

		return $resolver instanceof SubscriptionGroupResolverInterface
			? $resolver
			: new NullSubscriptionGroupResolver();
	}

	/**
	 * Whether an add-on currently provides groups. Consumers use this to
	 * hide group controls instead of showing empty ones.
	 */
	public static function isAvailable(): bool {
		return ! ( self::resolver() instanceof NullSubscriptionGroupResolver );
	}
}
