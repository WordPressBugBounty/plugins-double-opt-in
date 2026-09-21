<?php
/**
 * Contract between the follow-up coordinator and a form integration.
 *
 * @package Forge12\DoubleOptIn\FollowUp
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\FollowUp;

use forge12\contactform7\CF7DoubleOptIn\OptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Additive contract (Core API 4.4). Integrations that implement it hand
 * the post-confirmation work to the coordinator, which persists a
 * status per action, claims each action atomically and records an
 * honest result. Integrations that do not implement it keep the
 * previous behaviour unchanged.
 *
 * Adapters are registered on `f12_doi_register_follow_up_adapters`.
 */
interface FollowUpAdapterInterface {

	/**
	 * Integration identifier, identical to the one passed to
	 * `OptIn::isType()` (`cf7`, `elementor`, `avada`, `wpforms`,
	 * `gravityforms`).
	 */
	public function getIntegration(): string;

	/**
	 * The actions this opt-in needs after confirmation, read from the
	 * form's current configuration. Called once per opt-in; the result
	 * is bound (persisted) and later configuration changes do not add
	 * or re-map actions.
	 *
	 * An empty array means "nothing configured".
	 *
	 * @return FollowUpAction[]
	 */
	public function planActions( OptIn $optIn ): array;

	/**
	 * Execute the given actions. Every action passed in has been claimed
	 * for this attempt and MUST get a result; a missing result is
	 * recorded as `unknown`.
	 *
	 * Implementations must not throw for expected failures — return a
	 * failed result instead. Any global state (superglobals, temporary
	 * filters) must be restored in `finally`.
	 *
	 * @param FollowUpAction[] $actions
	 *
	 * @return array<string, FollowUpResult> Keyed by action id.
	 */
	public function execute( OptIn $optIn, array $actions, FollowUpAttempt $attempt ): array;

	/**
	 * Called after every attempt with the complete status of the
	 * opt-in, so the adapter can release resources — e.g. delete
	 * staged uploads once every action that needs them is done.
	 *
	 * @param array<string, string> $statusByAction Action id => status.
	 */
	public function onSettled( OptIn $optIn, array $statusByAction ): void;
}
