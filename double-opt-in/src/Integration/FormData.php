<?php
/**
 * Form Data Value Object
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormData
 *
 * Immutable value object representing normalized form submission data.
 * Provides factory methods for creating instances from various form systems.
 */
final class FormData implements FormDataInterface {

	/**
	 * The form ID.
	 *
	 * @var int
	 */
	private int $formId;

	/**
	 * The form type identifier.
	 *
	 * @var string
	 */
	private string $formType;

	/**
	 * Submitted form fields.
	 *
	 * @var array<string, mixed>
	 */
	private array $fields;

	/**
	 * Uploaded files.
	 *
	 * @var array<string, array>
	 */
	private array $files;

	/**
	 * Submission metadata.
	 *
	 * @var array<string, mixed>
	 */
	private array $meta;

	/**
	 * Recipient email address.
	 *
	 * @var string
	 */
	private string $recipientEmail;

	/**
	 * Form HTML content.
	 *
	 * @var string
	 */
	private string $formHtml;

	/**
	 * Private constructor - use factory methods.
	 *
	 * @param int    $formId         The form ID.
	 * @param string $formType       The form type.
	 * @param array  $fields         The form fields.
	 * @param array  $files          The uploaded files.
	 * @param array  $meta           The metadata.
	 * @param string $recipientEmail The recipient email.
	 * @param string $formHtml       The form HTML.
	 */
	private function __construct(
		int $formId,
		string $formType,
		array $fields,
		array $files,
		array $meta,
		string $recipientEmail,
		string $formHtml
	) {
		$this->formId         = $formId;
		$this->formType       = $formType;
		$this->fields         = $fields;
		$this->files          = $files;
		$this->meta           = $meta;
		$this->recipientEmail = $recipientEmail;
		$this->formHtml       = $formHtml;
	}

	/**
	 * Create a new FormData instance.
	 *
	 * @param int    $formId         The form ID.
	 * @param string $formType       The form type (e.g., 'cf7', 'avada').
	 * @param array  $fields         The submitted form fields.
	 * @param array  $files          The uploaded files (optional).
	 * @param array  $meta           The metadata (optional).
	 * @param string $recipientEmail The recipient email (optional).
	 * @param string $formHtml       The form HTML (optional).
	 *
	 * @return self
	 */
	public static function create(
		int $formId,
		string $formType,
		array $fields,
		array $files = [],
		array $meta = [],
		string $recipientEmail = '',
		string $formHtml = ''
	): self {
		// Set default metadata
		$meta = array_merge( [
			'source_url' => '',
			'timestamp'  => time(),
			'ip_address' => '',
			'user_agent' => '',
		], $meta );

		return new self( $formId, $formType, $fields, $files, $meta, $recipientEmail, $formHtml );
	}

	/**
	 * Create FormData from a serialized array.
	 *
	 * @param array $data The serialized data array.
	 *
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		return new self(
			(int) ( $data['formId'] ?? $data['form_id'] ?? 0 ),
			(string) ( $data['formType'] ?? $data['form_type'] ?? '' ),
			(array) ( $data['fields'] ?? [] ),
			(array) ( $data['files'] ?? [] ),
			(array) ( $data['meta'] ?? [] ),
			(string) ( $data['recipientEmail'] ?? $data['recipient_email'] ?? '' ),
			(string) ( $data['formHtml'] ?? $data['form_html'] ?? '' )
		);
	}

	/**
	 * Create FormData from CF7 submission.
	 *
	 * @param \WPCF7_ContactForm $form       The CF7 form.
	 * @param \WPCF7_Submission  $submission The CF7 submission.
	 *
	 * @return self
	 */
	public static function fromCF7( $form, $submission ): self {
		$postedData   = $submission->get_posted_data();
		$uploadedFiles = $submission->uploaded_files();

		// Normalize files to array format
		$files = [];
		foreach ( $uploadedFiles as $key => $fileList ) {
			$files[ $key ] = is_array( $fileList ) ? $fileList : [ $fileList ];
		}

		$meta = [
			'source_url' => $submission->get_meta( 'url' ),
			'timestamp'  => $submission->get_meta( 'timestamp' ),
			'ip_address' => $submission->get_meta( 'remote_ip' ),
			'user_agent' => $submission->get_meta( 'user_agent' ),
		];

		return self::create(
			$form->id(),
			'cf7',
			$postedData,
			$files,
			$meta,
			'',
			$form->form_html()
		);
	}

	/**
	 * Create FormData from Avada form submission.
	 *
	 * @param int   $formId   The form post ID.
	 * @param array $formData The Avada form data array.
	 *
	 * @return self
	 */
	public static function fromAvada( int $formId, array $formData ): self {
		$fields = $formData['data'] ?? [];
		$files  = [];

		// Handle attachments
		if ( isset( $formData['form_parameter']['attachments'] ) ) {
			$files['attachments'] = $formData['form_parameter']['attachments'];
		}

		$meta = [
			'source_url'         => $formData['submission']['source_url'] ?? '',
			'timestamp'          => time(),
			'field_labels'       => $formData['field_labels'] ?? [],
			'field_types'        => $formData['field_types'] ?? [],
			'hidden_field_names' => $formData['hidden_field_names'] ?? [],
		];

		$formPost = get_post( $formId );
		$formHtml = $formPost ? do_shortcode( $formPost->post_content ) : '';

		return self::create(
			$formId,
			'avada',
			$fields,
			$files,
			$meta,
			'',
			$formHtml
		);
	}

	/**
	 * Create FormData from WPForms submission.
	 *
	 * @param int   $formId   The form ID.
	 * @param array $fields   The submitted fields (sanitized).
	 * @param array $entry    The original $_POST data.
	 * @param array $formData The form settings/data.
	 *
	 * @return self
	 */
	public static function fromWPForms( int $formId, array $fields, array $entry, array $formData ): self {
		// Normalize fields to simple key => value format
		$normalizedFields = [];
		foreach ( $fields as $fieldId => $field ) {
			if ( is_array( $field ) ) {
				$normalizedFields[ $fieldId ] = $field['value'] ?? '';
				// Also store the full field data with name
				$normalizedFields[ 'field_' . $fieldId ] = $field;
			} else {
				$normalizedFields[ $fieldId ] = $field;
			}
		}

		// Extract files from fields
		$files = [];
		foreach ( $fields as $fieldId => $field ) {
			if ( is_array( $field ) && isset( $field['type'] ) && $field['type'] === 'file-upload' ) {
				if ( ! empty( $field['value_raw'] ) ) {
					$files[ $fieldId ] = (array) $field['value_raw'];
				}
			}
		}

		$meta = [
			'source_url'  => wp_get_referer() ?: '',
			'timestamp'   => time(),
			'entry'       => $entry,
			'form_title'  => $formData['settings']['form_title'] ?? '',
		];

		return self::create(
			$formId,
			'wpforms',
			$normalizedFields,
			$files,
			$meta,
			'',
			''
		);
	}

	/**
	 * Create FormData from Gravity Forms submission.
	 *
	 * @param int   $formId The form ID.
	 * @param array $entry  The entry data.
	 * @param array $form   The form object.
	 *
	 * @return self
	 */
	public static function fromGravityForms( int $formId, array $entry, array $form ): self {
		// Extract field values from entry
		$fields = [];
		$files  = [];

		if ( ! empty( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$fieldId = $field->id;

				// Handle file upload fields
				if ( $field->type === 'fileupload' ) {
					$value = $entry[ $fieldId ] ?? '';
					if ( ! empty( $value ) ) {
						$files[ $fieldId ] = is_array( $value ) ? $value : [ $value ];
					}
					continue;
				}

				// Handle complex fields with inputs
				if ( ! empty( $field->inputs ) && is_array( $field->inputs ) ) {
					foreach ( $field->inputs as $input ) {
						$inputId = $input['id'];
						if ( isset( $entry[ $inputId ] ) ) {
							$fields[ $inputId ] = $entry[ $inputId ];
						}
					}
				} else {
					if ( isset( $entry[ $fieldId ] ) ) {
						$fields[ $fieldId ] = $entry[ $fieldId ];
					}
				}
			}
		}

		$meta = [
			'source_url' => $entry['source_url'] ?? '',
			'timestamp'  => strtotime( $entry['date_created'] ?? 'now' ),
			'ip_address' => $entry['ip'] ?? '',
			'user_agent' => $entry['user_agent'] ?? '',
			'entry_id'   => $entry['id'] ?? 0,
			'form_title' => $form['title'] ?? '',
		];

		return self::create(
			$formId,
			'gravityforms',
			$fields,
			$files,
			$meta,
			'',
			''
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormId(): int {
		return $this->formId;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormType(): string {
		return $this->formType;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFields(): array {
		return $this->fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getField( string $key, $default = null ) {
		return $this->fields[ $key ] ?? $default;
	}

	/**
	 * {@inheritdoc}
	 */
	public function hasField( string $key ): bool {
		return array_key_exists( $key, $this->fields );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFiles(): array {
		return $this->files;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMeta(): array {
		return $this->meta;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMetaValue( string $key, $default = null ) {
		return $this->meta[ $key ] ?? $default;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecipientEmail(): string {
		return $this->recipientEmail;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormHtml(): string {
		return $this->formHtml;
	}

	/**
	 * {@inheritdoc}
	 */
	public function toArray(): array {
		return [
			'formId'         => $this->formId,
			'formType'       => $this->formType,
			'fields'         => $this->fields,
			'files'          => $this->files,
			'meta'           => $this->meta,
			'recipientEmail' => $this->recipientEmail,
			'formHtml'       => $this->formHtml,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function withFields( array $fields ): FormDataInterface {
		return new self(
			$this->formId,
			$this->formType,
			array_merge( $this->fields, $fields ),
			$this->files,
			$this->meta,
			$this->recipientEmail,
			$this->formHtml
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function withMeta( array $meta ): FormDataInterface {
		return new self(
			$this->formId,
			$this->formType,
			$this->fields,
			$this->files,
			array_merge( $this->meta, $meta ),
			$this->recipientEmail,
			$this->formHtml
		);
	}

	/**
	 * Create a new instance with a specific recipient email.
	 *
	 * @param string $email The recipient email.
	 *
	 * @return self
	 */
	public function withRecipientEmail( string $email ): self {
		return new self(
			$this->formId,
			$this->formType,
			$this->fields,
			$this->files,
			$this->meta,
			$email,
			$this->formHtml
		);
	}

	/**
	 * Serialize for storage in database.
	 *
	 * @return string Serialized representation.
	 */
	public function serialize(): string {
		return maybe_serialize( $this->toArray() );
	}

	/**
	 * Unserialize from database storage.
	 *
	 * @param string $data The serialized data.
	 *
	 * @return self|null The FormData instance or null if invalid.
	 */
	public static function unserialize( string $data ): ?self {
		$unserialized = maybe_unserialize( $data );

		if ( ! is_array( $unserialized ) ) {
			return null;
		}

		return self::fromArray( $unserialized );
	}
}
