<?php
/**
 * Resolver used when no add-on provides subscription groups.
 *
 * @package Forge12\DoubleOptIn\Subscription
 * @since   5.12.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NullSubscriptionGroupResolver implements SubscriptionGroupResolverInterface {

	public function groupForForm( int $formId, string $formRef = '' ): ?SubscriptionGroup {
		return null;
	}

	public function findGroup( string $key ): ?SubscriptionGroup {
		return null;
	}

	public function groups(): array {
		return array();
	}
}
