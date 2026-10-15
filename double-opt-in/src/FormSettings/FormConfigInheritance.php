<?php
/**
 * Lets a form take its double opt-in settings from its subscription group.
 *
 * @package Forge12\DoubleOptIn\FormSettings
 * @since   5.14.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\FormSettings;

use Forge12\DoubleOptIn\Subscription\SubscriptionGroup;
use Forge12\DoubleOptIn\Subscription\SubscriptionGroups;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place that decides what a form's settings are *at run time*.
 *
 * Two readings exist and must not be mixed up:
 *
 *  - **raw** is what is stored on the form (post meta). The admin reads and
 *    writes this. Writing back an inherited value would copy the group into
 *    the form and break the promise that taking a form out of its group
 *    restores what it had.
 *  - **effective** is what the sender, the confirmation page and the
 *    reminders use. This class turns the raw reading into the effective one.
 *
 * Without an add-on that provides groups the resolver answers "no group" and
 * the raw settings come back unchanged.
 *
 * Keys are the stored (legacy) names, as in `CF7DoubleOptIn::getParameter()`.
 *
 * **The consent evidence is not here, on purpose.** What a sign-up records as the wording
 * (`consent_text`) and the field that had to be ticked (`consent_field`) has to be what the
 * visitor was actually shown on *that form*. A subscription cannot know that, only the form
 * does, so a subscription never supplies them: a sign-up would otherwise prove consent to a
 * wording the form never displayed. This is also why the two are absent from every list below.
 */
final class FormConfigInheritance {

	/**
	 * Shared: a value the group has set replaces the form's.
	 * The `enable` switch is deliberately not here; it stays per form, so a
	 * group can never switch on a form that is missing its required fields.
	 */
	public const SHARED_KEYS = array(
		'sender',
		'sender_name',
		'subject',
		'body',
		'page',
		'error_page',
		'template',
		'category',
	);

	/**
	 * Defaults: field names differ from form to form, so the group's value
	 * only fills a gap and never replaces what the form has.
	 */
	public const DEFAULT_KEYS = array(
		'recipient',
	);

	/**
	 * @param array<string, mixed> $params  The form's own settings (raw).
	 * @param int                  $formId  The form's id (for Elementor: the page).
	 *
	 * @return array<string, mixed> The settings the form actually runs with.
	 */
	public static function apply( array $params, int $formId ): array {
		$group = self::sourceGroup( $formId );
		if ( null === $group ) {
			return $params;
		}

		$config = $group->getSharedConfig();

		foreach ( self::SHARED_KEYS as $key ) {
			if ( array_key_exists( $key, $config ) && ! self::isUnset( $config[ $key ] ) ) {
				$params[ $key ] = $config[ $key ];
			}
		}

		foreach ( self::DEFAULT_KEYS as $key ) {
			if ( isset( $config[ $key ] ) && ! self::isUnset( $config[ $key ] ) && self::isUnset( $params[ $key ] ?? '' ) ) {
				$params[ $key ] = $config[ $key ];
			}
		}

		if ( ! empty( $config['field_mapping'] ) && is_array( $config['field_mapping'] ) ) {
			$own                     = isset( $params['field_mapping'] ) && is_array( $params['field_mapping'] ) ? $params['field_mapping'] : array();
			$params['field_mapping'] = array_merge( $config['field_mapping'], $own );
		}

		/**
		 * Adjust the settings a group member runs with.
		 *
		 * @since 5.14.0
		 *
		 * @param array<string, mixed> $params  The effective settings.
		 * @param int                  $formId  The form's id.
		 * @param SubscriptionGroup    $group   The group they came from.
		 */
		return (array) apply_filters( 'f12_doi_form_config_inherited', $params, $formId, $group );
	}

	/**
	 * The required fields a form still lacks once its group has been applied.
	 *
	 * The completeness gate asks whether the form will work, not whether the
	 * form itself holds every value. A member whose subject, text and template
	 * come from the group is complete.
	 *
	 * @param FormSettingsDTO $dto    The settings being checked (not modified).
	 * @param int             $formId The form's id.
	 *
	 * @return array<int, string> See {@see FormSettingsDTO::getMissingRequiredFields()}.
	 */
	public static function missingRequiredFields( FormSettingsDTO $dto, int $formId ): array {
		$effective = self::apply(
			array(
				'recipient' => $dto->recipient,
				'subject'   => $dto->subject,
				'body'      => $dto->body,
				'template'  => $dto->template,
			),
			$formId
		);

		$check            = clone $dto;
		$check->recipient = (string) $effective['recipient'];
		$check->subject   = (string) $effective['subject'];
		$check->body      = (string) $effective['body'];
		$check->template  = (string) $effective['template'];

		return $check->getMissingRequiredFields();
	}

	/**
	 * The required fields (see {@see FormSettingsDTO::getMissingRequiredFields()})
	 * a group's shared settings take care of, so an admin screen does not call
	 * a member incomplete for something the group supplies.
	 *
	 * @return array<int, string>
	 */
	public static function providedRequiredFields( SubscriptionGroup $group ): array {
		$config   = $group->getSharedConfig();
		$provided = array();

		if ( isset( $config['recipient'] ) && ! self::isUnset( $config['recipient'] ) ) {
			$provided[] = 'recipient';
		}
		if ( isset( $config['subject'] ) && ! self::isUnset( $config['subject'] ) ) {
			$provided[] = 'subject';
		}
		$hasTemplate = isset( $config['template'] ) && ! self::isUnset( $config['template'] );
		$hasLink     = isset( $config['body'] ) && is_string( $config['body'] ) && false !== strpos( $config['body'], '[doubleoptinlink]' );
		if ( $hasTemplate || $hasLink ) {
			$provided[] = 'body_or_template';
		}

		return $provided;
	}

	/**
	 * The form's stored settings with its group applied, for code that reads
	 * the post meta directly instead of through `getParameter()`.
	 *
	 * Returns an empty array when the form has no settings and no group, like
	 * `get_post_meta( …, true )` does for a missing value.
	 *
	 * @param int $formId The form's id.
	 *
	 * @return array<string, mixed>
	 */
	public static function effective( int $formId ): array {
		$stored = get_post_meta( $formId, FormSettingsService::META_KEY, true );
		$stored = is_array( $stored ) ? $stored : array();

		return self::apply( $stored, $formId );
	}

	/**
	 * The group a form takes settings from, or null when it takes none.
	 * A group that shares nothing is not a source.
	 *
	 * Settings are stored per form id, and for Elementor that id is the page.
	 * So a member counts for the whole id, whichever widget (`form_ref`) it
	 * names; otherwise two forms on one page would run with different settings
	 * depending on which code path asks.
	 */
	public static function sourceGroup( int $formId ): ?SubscriptionGroup {
		if ( $formId <= 0 ) {
			return null;
		}

		foreach ( SubscriptionGroups::resolver()->groups() as $group ) {
			if ( array() === $group->getSharedConfig() ) {
				continue;
			}
			foreach ( $group->getMembers() as $member ) {
				if ( $member['form_id'] === $formId ) {
					return $group;
				}
			}
		}

		return null;
	}

	/**
	 * An empty text, `-1` (no page chosen) and `0` (no category) mean "not
	 * set". Such a value never blanks a form's own setting.
	 *
	 * @param mixed $value
	 */
	private static function isUnset( $value ): bool {
		if ( is_string( $value ) ) {
			return '' === trim( $value ) || '-1' === $value || '0' === $value;
		}

		return null === $value || false === $value || 0 === $value || -1 === $value || array() === $value;
	}
}
