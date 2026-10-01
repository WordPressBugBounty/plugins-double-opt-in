<?php
/**
 * A follow-up adapter that applies to every form, not to one integration.
 *
 * @package Forge12\DoubleOptIn\FollowUp
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\FollowUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Additive contract (Core API 4.6). A form adapter owns the follow-up of
 * one integration (cf7, elementor …); a global adapter adds its own
 * actions to every opt-in that has a form adapter — webhooks, newsletter
 * sync. It gets the same guarantees: planned once, claimed atomically,
 * retried with backoff, shown in the follow-up panel.
 *
 * Rules on top of FollowUpAdapterInterface:
 *  - `getIntegration()` names the adapter (e.g. `webhooks`); it is stored
 *    on every row it plans and never matches a form type.
 *  - Every action id starts with `<integration>:`. Actions that do not
 *    are dropped when planning, so no adapter can take over another's id.
 *  - `execute()` and `onSettled()` see only the adapter's own rows.
 *
 * An opt-in from an integration without a form adapter is not followed up
 * by global adapters either: its confirmation still runs the old path.
 */
interface GlobalFollowUpAdapterInterface extends FollowUpAdapterInterface {
}
