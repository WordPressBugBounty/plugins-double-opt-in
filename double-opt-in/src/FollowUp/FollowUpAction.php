<?php
/**
 * Descriptor of one planned follow-up action.
 *
 * @package Forge12\DoubleOptIn\FollowUp
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\FollowUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What an adapter will do for a confirmed opt-in.
 *
 * The id is the idempotency key: together with the opt-in id it is
 * unique in the follow-up table, so the same action is never planned —
 * and never executed — twice. It must therefore be stable across
 * requests (`elementor:email`, `wpforms:notification:2`), never derived
 * from time or randomness.
 */
final class FollowUpAction {

	public const KIND_ENTRY    = 'entry';
	public const KIND_MAIL     = 'mail';
	public const KIND_WEBHOOK  = 'webhook';
	public const KIND_DISPATCH = 'dispatch';
	public const KIND_OPAQUE   = 'opaque';
	public const KIND_MARKER   = 'marker';

	/**
	 * Reserved id for "the integration planned nothing". Lets the admin
	 * tell "no follow-ups configured" apart from "no status recorded".
	 */
	public const ID_NONE = '_none';

	/** @var string */
	private $id;

	/** @var string */
	private $kind;

	/** @var string */
	private $label;

	/** @var bool */
	private $gatedByDefaultMail;

	/** @var string */
	private $skipReason;

	/** @var string */
	private $previousEntryRef;

	/**
	 * @param string $id                 Stable action id, [a-z0-9_:.-], max 100 chars.
	 * @param string $kind               One of the KIND_* constants.
	 * @param string $label              Non-personal label for the admin (e.g. "Email 2").
	 * @param bool   $gatedByDefaultMail Whether `f12_cf7_doubleoptin_send_default_mail = false`
	 *                                   disables this action. Mirrors the existing
	 *                                   per-integration semantics of that filter.
	 * @param string $skipReason         Non-empty: plan the action as `skipped`
	 *                                   with this reason (e.g. `not_supported`).
	 * @param string $previousEntryRef   On a retry: the record a previous attempt
	 *                                   already created (e.g. a half-written
	 *                                   submission), so the adapter can avoid a
	 *                                   duplicate instead of starting over.
	 */
	public function __construct( string $id, string $kind, string $label, bool $gatedByDefaultMail = true, string $skipReason = '', string $previousEntryRef = '' ) {
		$id = strtolower( $id );
		if ( $id === '' || strlen( $id ) > 100 || preg_match( '/[^a-z0-9_:.\-]/', $id ) ) {
			throw new \InvalidArgumentException( 'Invalid follow-up action id.' );
		}

		$this->id                 = $id;
		$this->kind               = $kind;
		$this->label              = substr( $label, 0, 190 );
		$this->gatedByDefaultMail = $gatedByDefaultMail;
		$this->skipReason         = $skipReason;
		$this->previousEntryRef   = $previousEntryRef;
	}

	public function getPreviousEntryRef(): string {
		return $this->previousEntryRef;
	}

	public function getId(): string {
		return $this->id;
	}

	public function getKind(): string {
		return $this->kind;
	}

	public function getLabel(): string {
		return $this->label;
	}

	public function isGatedByDefaultMail(): bool {
		return $this->gatedByDefaultMail;
	}

	public function getSkipReason(): string {
		return $this->skipReason;
	}

	/**
	 * Stable fingerprint of a plan: the sorted action ids. Stored with
	 * every row so a later retry can tell whether the form's action set
	 * changed since the plan was bound.
	 *
	 * @param FollowUpAction[] $actions
	 */
	public static function fingerprint( array $actions ): string {
		$ids = array();
		foreach ( $actions as $action ) {
			$ids[] = $action->getId();
		}
		sort( $ids );
		return hash( 'sha256', implode( '|', $ids ) );
	}
}
