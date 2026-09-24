# Developer Documentation: Hooks, Filters & Events

> **Plugin:** Double Opt-In for Contact Form 7 & Avada
> **Since:** 4.0.0 (Event System), 3.2.2 (getFormData on confirm)
> **Last updated:** 2026-02-10

This document is the complete reference for integrating with the Double Opt-In plugin.
There are two ways to hook into the plugin lifecycle:

1. **WordPress Hooks** (`add_action` / `add_filter`) -- backward-compatible, works like any WP hook.
2. **Typed Events** (via `EventDispatcherInterface`) -- introduced in 4.0, strongly typed, auto-completed by your IDE.

Both approaches work side by side. For new code we recommend the typed event system.

---

## Table of Contents

- [Quick Start Examples](#quick-start-examples)
- [Lifecycle Hooks (do_action)](#lifecycle-hooks)
- [Mail Hooks (do_action)](#mail-hooks)
- [Follow-up Hooks](#follow-up-hooks)
- [Integration Hooks (do_action)](#integration-hooks)
- [Filters (apply_filters)](#filters)
- [Typed Events](#typed-events)
  - [Lifecycle Events](#lifecycle-events)
  - [Form Events](#form-events)
  - [Mail Events](#mail-events)
  - [Integration Events](#integration-events)
- [Migration Guide: Legacy to Events](#migration-guide)

---

## Quick Start Examples

### After Opt-In Confirmation (Legacy Hook)

```php
add_action( 'f12_cf7_doubleoptin_after_confirm', function ( string $hash, $optIn ) {
    $formData = maybe_unserialize( $optIn->get_content() );
    $email    = $optIn->get_email();
    $formId   = $optIn->get_cf_form_id();

    // Example: subscribe to newsletter
    my_newsletter_subscribe( $email, $formData['your-name'] ?? '' );
}, 10, 2 );
```

### After Opt-In Confirmation (Typed Event)

```php
use Forge12\DoubleOptIn\Events\Lifecycle\OptInConfirmedEvent;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;

add_action( 'plugins_loaded', function () {
    $container  = Container::getInstance();
    $dispatcher = $container->get( EventDispatcherInterface::class );

    $dispatcher->addListener(
        OptInConfirmedEvent::class,
        function ( OptInConfirmedEvent $event ) {
            $formData = $event->getFormData();
            $email    = $event->getEmail();
            $formId   = $event->getFormId();

            my_newsletter_subscribe( $email, $formData['your-name'] ?? '' );
        }
    );
} );
```

---

## Lifecycle Hooks

### `f12_cf7_doubleoptin_before_confirm`

Fires **before** the opt-in record is marked as confirmed in the database.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$hash` | `string` | The opt-in hash from the confirmation link |
| `$optIn` | `OptIn` | The opt-in record (not yet confirmed) |

```php
add_action( 'f12_cf7_doubleoptin_before_confirm', function ( $hash, $optIn ) {
    // Example: log the confirmation attempt
    error_log( "Confirm attempt for: " . $optIn->get_email() );
}, 10, 2 );
```

### `f12_cf7_doubleoptin_after_confirm`

Fires **after** the opt-in record has been confirmed and saved in the database.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$hash` | `string` | The opt-in hash |
| `$optIn` | `OptIn` | The confirmed opt-in record |

```php
add_action( 'f12_cf7_doubleoptin_after_confirm', function ( $hash, $optIn ) {
    $formData = maybe_unserialize( $optIn->get_content() );

    // Access individual form fields
    $name  = $formData['your-name']  ?? '';
    $email = $formData['your-email'] ?? '';
    $phone = $formData['your-phone'] ?? '';

    // Example: create a WooCommerce customer, sync to CRM, etc.
}, 10, 2 );
```

### `f12_cf7_doubleoptin_already_confirmed`

Fires when a user clicks a confirmation link that has already been used.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$hash` | `string` | The opt-in hash |
| `$optIn` | `OptIn` | The already-confirmed opt-in record |

### `f12_cf7_doubleoptin_token_expired`

Fires when a confirmation link has expired (based on `token_expiry_hours` setting).

| Parameter | Type | Description |
|-----------|------|-------------|
| `$hash` | `string` | The opt-in hash |
| `$optIn` | `OptIn` | The expired opt-in record |

### `f12_cf7_doubleoptin_sent`

Fires when a new opt-in record is created and the confirmation email is sent.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$form` | `mixed` | The form object (CF7 or Avada) |
| `$formId` | `int` | The form ID |

### `f12_cf7_doubleoptin_creation_failed`

Fires when an opt-in record could not be saved to the database.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$formId` | `int` | The form ID |
| `$recipient` | `string` | The recipient email |

### `f12_cf7_doubleoptin_rate_limited`

Fires when a submission is blocked by rate limiting.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$type` | `string` | `'ip'` or `'email'` |
| `$identifier` | `string` | The IP address or email that was rate-limited |
| `$formId` | `int` | The form ID |

### `f12_cf7_doubleoptin_recipient_invalid`

Fires when recipient email validation fails (e.g. MX check in Pro).

| Parameter | Type | Description |
|-----------|------|-------------|
| `$recipient` | `string` | The rejected email |
| `$formId` | `int` | The form ID |
| `$errorMsg` | `string` | The validation error message |

### `f12_cf7_doubleoptin_consent_not_given`

Fires when a submission is rejected because the form's configured
acceptance field was not confirmed. Since 5.4.0 this fires for every
integration; before that only for those extending `AbstractFormIntegration`.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$formId` | `int` | The form ID |
| `$consentField` | `string` | The configured acceptance field |

### `f12_doi_consent_field_unknown`

**Since 5.4.0.** Fires when the configured acceptance field cannot be
found on the form at all — renamed or deleted in the form builder. The
submission is **accepted**; this is the deliberate safety valve, so that a
settings mistake cannot take a site's registrations offline. The same
condition is reported under Tools → Site Health.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$formId` | `int` | The form ID |
| `$consentField` | `string` | The configured field that could not be found |
| `$integration` | `string` | The integration identifier (`cf7`, `elementor`, …) |

---

## Mail Hooks

### `f12_cf7_doubleoptin_before_send_default_mail`

Fires before the original form mail is sent after opt-in confirmation. Spam protection (reCAPTCHA, CF7 Captcha) is temporarily disabled at this point.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$optIn` | `OptIn` | The confirmed opt-in record |

### `f12_cf7_doubleoptin_trigger_default_mail`

Legacy trigger for the mail sending. Since 5.6.0 the core no longer fires it for opt-ins whose integration has a follow-up adapter (CF7, Elementor, Avada, WPForms, Gravity Forms) — their follow-up actions run through the follow-up coordinator instead. Firing it yourself is still safe: every listener checks the opt-in's integration, and managed opt-ins go through the coordinator, so actions that already ran are not repeated.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$optIn` | `OptIn` | The confirmed opt-in record |

### `f12_cf7_doubleoptin_after_send_default_mail`

Fires after the original form mail has been sent. Spam protection is re-enabled at this point. It fires after the follow-up attempt whatever its outcome — do not treat it as proof that the mail went out; read the follow-up status instead (see below).

| Parameter | Type | Description |
|-----------|------|-------------|
| `$optIn` | `OptIn` | The confirmed opt-in record |

---

## Follow-up Hooks

A confirmed opt-in is not proof that the form's own actions (stored entry, notification mails, webhooks) ran. Since 5.6.0 each of them is a *follow-up action* with its own recorded status (`pending`, `running`, `succeeded`, `failed_retryable`, `failed_permanent`, `unknown`, `skipped`), shown in the opt-in detail view and written to the audit log (type `follow_up`). Only actions that demonstrably did not run are retried automatically; `unknown` is never retried without an administrator's explicit decision.

### `f12_doi_register_follow_up_adapters`

Register a follow-up adapter for a form integration that is not part of the Double Opt-In family. Fires once, on first use.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$registry` | `FollowUpAdapterRegistry` | Call `register( FollowUpAdapterInterface $adapter )` |

```php
add_action( 'f12_doi_register_follow_up_adapters', function ( $registry ) {
    $registry->register( new My_Form_FollowUp_Adapter() );
} );
```

The adapter plans one action per side effect (`planActions()`), executes the claimed ones and returns a `FollowUpResult` per action (`execute()`), and releases resources once everything is done (`onSettled()`). Return `FollowUpResult::unknown()` whenever you cannot tell whether a side effect happened.

### `f12_doi_follow_up_backoff` (filter)

Delays in seconds between automatic retries of actions that demonstrably did not run (e.g. the internal request never reached the server). The number of entries is the maximum number of automatic retries. Default `[60, 300, 1800]`; `[]` disables automatic retries. A manual retry from the admin starts a fresh budget: the schedule applies again from its first entry.

```php
add_filter( 'f12_doi_follow_up_backoff', fn() => array( 120, 600 ) );
```

Addon-specific (Elementor Forms addon): `f12_doi_elementor_replay_skip_validators` (validator classes) and `f12_doi_elementor_replay_skip_field_validators` (field types) name spam validators that are skipped for the ticket-authorised post-confirmation replay only — for a third-party CAPTCHA whose token cannot be verified twice.

---

## Integration Hooks

### `f12_cf7_doubleoptin_register_integrations`

Fires during plugin initialization. Use this to register your own form integration.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$registry` | `FormIntegrationRegistry` | The integration registry |
| `$container` | `Container` | The service container |

```php
add_action( 'f12_cf7_doubleoptin_register_integrations', function ( $registry, $container ) {
    $registry->register( new MyCustomFormIntegration( $container->get( LoggerInterface::class ) ) );
}, 10, 2 );
```

### `f12_cf7_doubleoptin_integration_registered`

Fires after a form integration has been registered.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$integration` | `FormIntegrationInterface` | The registered integration |
| `$identifier` | `string` | The integration identifier (e.g. `cf7`, `avada`) |

### `f12_cf7_doubleoptin_integrations_initialized`

Fires after all form integrations have been initialized.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$registry` | `FormIntegrationRegistry` | The registry with all integrations |

### `f12_cf7_doubleoptin_register_event_listeners`

Fires during event system setup. Register your typed event listeners here.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$dispatcher` | `EventDispatcherInterface` | The event dispatcher |
| `$hookBridge` | `WordPressHookBridge` | The WordPress hook bridge |

```php
add_action( 'f12_cf7_doubleoptin_register_event_listeners', function ( $dispatcher, $hookBridge ) {
    $dispatcher->addListener(
        \Forge12\DoubleOptIn\Events\Lifecycle\OptInConfirmedEvent::class,
        function ( $event ) {
            // your logic
        }
    );
}, 10, 2 );
```

---

## Filters

### Form & Submission Filters

| Filter | Parameters | Return | Description |
|--------|-----------|--------|-------------|
| `f12_cf7_doubleoptin_add_request_parameter` | `$fields` (array) | `array` | Modify submitted form fields before saving to database |
| `f12_cf7_doubleoptin_skip_option` | `$skip` (bool), `$formId`, `$fields`, `$type` | `bool` | Return `true` to skip opt-in creation for this submission |
| `f12_cf7_doubleoptin_show_validation_error` | `$show` (bool), `$error` (OptInError, since 5.6.2), `$formId` (int, since 5.6.2) | `bool` | Whether the form shows the reason for a refused submission instead of its own success message (default: `false`). A refused consent (`consent_not_given`) is always shown and never reaches this filter (since 5.6.2) |
| `f12_cf7_doubleoptin_enable_error_notification` | `$enable` (bool) | `bool` | Return `false` to not load the frontend error toast at all (default: `true`, since 4.2.0) |
| `f12_cf7_doubleoptin_error_message` | `$message` (string), `$error` (OptInError), `$formId` (int) | `string` | Customize the error message per error code (since 4.2.0) |
| `f12_cf7_doubleoptin_validate_recipient` | `$valid` (bool), `$recipient`, `$formData` | `bool\|string` | Validate recipient email; return error string to reject |
| `f12_cf7_doubleoptin_send_default_mail` | `$send` (bool), `$formId` | `bool` | Whether to send the original form mail after confirmation |
| `f12_doi_enforce_consent_gate` | `$enforce` (bool), `$formId` (int), `$integration` (string) | `bool` | Return `false` to accept a submission whose configured acceptance field was not confirmed. The opt-in is then stored with a consent text nobody agreed to, so this is an escape hatch for an unforeseen edge case, not a setting (since 5.4.0) |

### Mail Filters

| Filter | Parameters | Return | Description |
|--------|-----------|--------|-------------|
| `f12_cf7_doubleoptin_body` | `$body` (string) | `string` | Modify the opt-in confirmation email body |
| `f12-cf7-doubleoptin-cf7-args` | `$args` (array) | `array` | Modify mail arguments (subject, body, headers, attachments) |
| `f12_cf7_doubleoptin_files_mail_1` | `$include` (bool), `$optIn` | `bool` | Include file attachments in the first confirmation mail |
| `f12_cf7_doubleoptin_files_mail_2` | `$include` (bool), `$optIn` | `bool` | Include file attachments in the second confirmation mail |
| `f12_cf7_doubleoptin_allowed_mime_types` | `$mimeTypes` (array) | `array` | Modify allowed MIME types for file uploads |

### Settings Filters

| Filter | Parameters | Return | Description |
|--------|-----------|--------|-------------|
| `f12_cf7_doubleoptin_save_form` | `$data` (array) | `array` | Modify form settings before saving |
| `f12_cf7_doubleoptin_metadata_cf7` | `$metadata` (array) | `array` | Modify CF7 form metadata |
| `f12_cf7_doubleoptin_metadata_avada` | `$metadata` (array) | `array` | Modify Avada form metadata |
| `f12_doi_form_settings_data` | `$formData`, `$formId` | `array` | Modify form settings data before sending to frontend |
| `f12_doi_form_settings_before_save` | `$settings`, `$storageId`, `$settingsData` | `FormSettingsDTO` | Modify FormSettingsDTO before saving |
| `f12_doi_settings_dto_from_array` | `$dto`, `$data` | `FormSettingsDTO` | Modify DTO when creating from array |
| `f12_doi_settings_dto_to_array` | `$array`, `$dto` | `array` | Modify array representation of DTO |
| `f12_doi_is_pro_active` | `$isActive` (bool) | `bool` | Whether the Pro version is active |
| `f12_cf7_doubleoptin_use_new_integration_system` | `$use` (bool) | `bool` | Enable/disable the new integration system |

### Filter Examples

```php
// Skip opt-in for specific forms
add_filter( 'f12_cf7_doubleoptin_skip_option', function ( $skip, $formId, $fields, $type ) {
    if ( $formId === 42 ) {
        return true; // skip opt-in for form #42
    }
    return $skip;
}, 10, 4 );

// Add custom fields to the stored data
add_filter( 'f12_cf7_doubleoptin_add_request_parameter', function ( $fields ) {
    $fields['custom-tracking-id'] = uniqid( 'track_' );
    return $fields;
} );

// Custom recipient validation
add_filter( 'f12_cf7_doubleoptin_validate_recipient', function ( $valid, $recipient, $formData ) {
    if ( str_ends_with( $recipient, '@blocked-domain.com' ) ) {
        return 'This email domain is not accepted.';
    }
    return $valid;
}, 10, 3 );

// Enable error display for users (default: false)
add_filter( 'f12_cf7_doubleoptin_show_validation_error', '__return_true' );

// Customize error messages per error code
add_filter( 'f12_cf7_doubleoptin_error_message', function ( $message, $error, $formId ) {
    if ( $error->getCode() === 'rate_limit_ip' ) {
        return 'Please wait a few minutes before trying again.';
    }
    return $message;
}, 10, 3 );
```

---

## Universal Error Notification System

> **Since:** 4.2.0

The plugin provides a form-plugin-agnostic error notification system that works
with **all** integrations (CF7, Avada, Gravity Forms, WPForms, Elementor, and
any future integration) without requiring integration-specific error handling code.

### How it works

1. When `createOptIn()` fails, an `OptInError` is stored in a short-lived transient
   keyed by the client's IP + User-Agent (TTL: 60 seconds).
2. A small frontend JS (loaded on every frontend page unless
   `f12_cf7_doubleoptin_enable_error_notification` returns `false`) listens for
   form submission events from all supported plugins.
3. After form submission, the JS calls the AJAX endpoint
   `doi_check_submission_error` to check for a stored error.
4. If an error exists, a toast notification is displayed. The transient is
   deleted after retrieval (one-time read). When the error is shown to the
   visitor (see below), the form plugin's success message is hidden and the
   toast stays until closed.

### Showing the error in the form

By default a refused submission is reported by the toast only; the form plugin
still shows its own success message. To show the reason in the form instead —
CF7 aborts with the message and keeps the input, Elementor answers with an
error, WPForms and Gravity Forms hide their confirmation — add this to your
theme's `functions.php`:

```php
add_filter( 'f12_cf7_doubleoptin_show_validation_error', '__return_true' );

// Or per error code (arguments since 5.6.2):
add_filter( 'f12_cf7_doubleoptin_show_validation_error', function ( $show, $error, $formId ) {
    return $error->getCode() === 'unique_email_duplicate' ? true : $show;
}, 10, 3 );
```

**A refused consent is always shown** (since 5.6.2). It is the one refusal the
visitor caused and can fix — tick the box — and before 5.6.2 the form said
"sent" while no mail was ever going to come. The MX Validator, Domain Blocklist
and Unique Email add-ons switch the filter on for all errors while active.

### Error codes

| Code | Constant | Default message |
|------|----------|----------------|
| `submission_cancelled` | `OptInError::SUBMISSION_CANCELLED` | The form submission has been cancelled. |
| `no_recipient` | `OptInError::NO_RECIPIENT` | No valid email address was found. |
| `rate_limit_ip` | `OptInError::RATE_LIMIT_IP` | Too many requests. Please try again later. |
| `rate_limit_email` | `OptInError::RATE_LIMIT_EMAIL` | Too many requests for this email address. Please try again later. |
| `recipient_invalid` | `OptInError::RECIPIENT_INVALID` | The email address could not be verified. |
| `save_failed` | `OptInError::SAVE_FAILED` | An error occurred. Please try again. |

### Programmatic access

```php
use Forge12\DoubleOptIn\Integration\AbstractFormIntegration;

// After a form submission, retrieve the last error (same request only)
$error = AbstractFormIntegration::getLastError();
if ( $error ) {
    $code    = $error->getCode();    // e.g. 'rate_limit_ip'
    $message = $error->getMessage(); // translated message
    $context = $error->getContext(); // ['ip' => '...', 'form_id' => 42]
}
```

### CSS customization

The notification uses the class `.doi-error-notification`. Override styles in your
theme to match your design:

```css
.doi-error-notification__content {
    border-left-color: #cc0000; /* custom accent color */
}
```

---

## Typed Events

All events extend `Forge12\DoubleOptIn\EventSystem\Event` and are dispatched via `EventDispatcherInterface`.

### Lifecycle Events

#### `OptInCreatedEvent`

Dispatched when a new opt-in record is created.

| Method | Return | Description |
|--------|--------|-------------|
| `getOptInId()` | `int` | The database record ID |
| `getFormId()` | `int` | The form ID |
| `getFormType()` | `string` | `'cf7'`, `'avada'`, etc. |
| `getEmail()` | `string` | The subscriber email |
| `getHash()` | `string` | The opt-in hash |
| `getFormData()` | `array` | Submitted form fields |

**WordPress hook:** `f12_cf7_doubleoptin_created` (auto-bridged)

#### `OptInConfirmedEvent`

Dispatched when an opt-in is confirmed via the confirmation link.

| Method | Return | Description |
|--------|--------|-------------|
| `getOptInId()` | `int` | The database record ID |
| `getHash()` | `string` | The opt-in hash |
| `getEmail()` | `string` | The subscriber email |
| `getConfirmedIp()` | `string` | IP address that confirmed |
| `getFormId()` | `int` | The original form ID |
| `getFormData()` | `array` | Submitted form fields (since 3.2.2) |

**WordPress hook:** `f12_cf7_doubleoptin_after_confirm` (manually bridged, not auto-bridged, to preserve `($hash, $optIn)` signature)

```php
$dispatcher->addListener( OptInConfirmedEvent::class, function ( OptInConfirmedEvent $event ) {
    $data = $event->getFormData();
    // ['your-name' => 'John Doe', 'your-email' => 'john@example.com', ...]
} );
```

#### `OptInDeletedEvent`

Dispatched when an opt-in record is deleted.

| Method | Return | Description |
|--------|--------|-------------|
| `getHash()` | `string` | The opt-in hash |
| `getEmail()` | `string` | The subscriber email |
| `getDeletedBy()` | `string` | `'admin'`, `'cron'`, or `'user'` |
| `getRowsDeleted()` | `int` | Number of rows deleted |

**WordPress hook:** `f12_cf7_doubleoptin_deleted`

#### `OptInExpiredEvent`

Dispatched during cleanup when expired records are removed.

| Method | Return | Description |
|--------|--------|-------------|
| `getCleanupType()` | `string` | `'confirmed'` or `'unconfirmed'` |
| `getRowsDeleted()` | `int` | Number of records deleted |
| `getThreshold()` | `DateTimeImmutable` | The cutoff date |

**WordPress hook:** `f12_cf7_doubleoptin_expired`

---

### Form Events

#### `FormSubmittedEvent`

Dispatched when a form is submitted (before opt-in is created).

| Method | Return | Description |
|--------|--------|-------------|
| `getFormId()` | `int` | The form ID |
| `getFormType()` | `string` | The form type |
| `getPostedData()` | `array` | Submitted form data |
| `getUploadedFiles()` | `array` | Uploaded files |
| `getFormUrl()` | `string` | Page URL where form was submitted |
| `shouldCreateOptIn()` | `bool` | Whether opt-in will be created |
| `skipOptInCreation($reason)` | `void` | Cancel opt-in creation |

**WordPress hook:** `f12_cf7_doubleoptin_form_submitted`

```php
$dispatcher->addListener( FormSubmittedEvent::class, function ( FormSubmittedEvent $event ) {
    // Skip opt-in for logged-in admins
    if ( current_user_can( 'manage_options' ) ) {
        $event->skipOptInCreation( 'Admin user, no opt-in needed' );
    }
} );
```

#### `FormValidatedEvent`

Dispatched after form validation is complete.

| Method | Return | Description |
|--------|--------|-------------|
| `getFormId()` | `int` | The form ID |
| `getFormType()` | `string` | The form type |
| `isValid()` | `bool` | Whether validation passed |
| `getRecipientEmail()` | `string` | The extracted email |
| `getErrors()` | `array` | Validation errors |

**WordPress hook:** `f12_cf7_doubleoptin_form_validated`

---

### Mail Events

#### `MailPreparingEvent`

Dispatched before the opt-in confirmation email is sent. All properties are **mutable**.

| Method | Return | Description |
|--------|--------|-------------|
| `getOptInId()` | `int` | The opt-in record ID |
| `getRecipient()` / `setRecipient()` | `string` | Recipient email |
| `getSubject()` / `setSubject()` | `string` | Email subject |
| `getBody()` / `setBody()` | `string` | Email body (HTML) |
| `getSender()` / `setSender()` | `string` | Sender email |
| `getSenderName()` / `setSenderName()` | `string` | Sender display name |
| `getHeaders()` / `addHeader()` | `array` | Email headers |
| `getAttachments()` / `addAttachment()` | `array` | File attachments |
| `shouldSend()` / `cancelSending()` | `bool` | Cancel sending |

**WordPress hook:** `f12_cf7_doubleoptin_mail_preparing`

```php
$dispatcher->addListener( MailPreparingEvent::class, function ( MailPreparingEvent $event ) {
    $event->setSubject( 'Custom: ' . $event->getSubject() )
          ->addHeader( 'X-Custom-Header: my-value' );
} );
```

#### `MailSentEvent`

Dispatched after a mail has been sent (or failed).

| Method | Return | Description |
|--------|--------|-------------|
| `getOptInId()` | `int` | The opt-in record ID |
| `getRecipient()` | `string` | Recipient email |
| `getSubject()` | `string` | Email subject |
| `wasSuccessful()` | `bool` | Whether sending succeeded |
| `getMailType()` | `string` | `'optin'` or `'confirmation'` |

**WordPress hook:** `f12_cf7_doubleoptin_mail_sent`

#### `ReminderSentEvent`

Dispatched after a reminder email is sent (Pro feature).

| Method | Return | Description |
|--------|--------|-------------|
| `getOptInId()` | `int` | The opt-in record ID |
| `getRecipient()` | `string` | Recipient email |
| `getSubject()` | `string` | Email subject |
| `wasSuccessful()` | `bool` | Whether sending succeeded |
| `getTrigger()` | `string` | `'cron'` or `'manual'` |

**WordPress hook:** `f12_cf7_doubleoptin_reminder_sent`

---

### Integration Events

#### `FormSubmissionEvent`

Dispatched when a form integration processes a submission. Allows modifying form data or cancelling the opt-in.

| Method | Return | Description |
|--------|--------|-------------|
| `getFormData()` / `setFormData()` | `FormDataInterface` | The normalized form data |
| `getIntegrationId()` | `string` | e.g. `'cf7'`, `'avada'` |
| `getFormId()` | `int` | The form ID |
| `shouldSkipOptIn()` | `bool` | Whether to skip opt-in |
| `skipOptIn($reason)` | `void` | Cancel opt-in creation |
| `getField($key, $default)` | `mixed` | Get a single form field |
| `hasField($key)` | `bool` | Check if field exists |

**WordPress hook:** `f12_cf7_doubleoptin_form_submission`

#### `IntegrationRegisteredEvent`

Dispatched when a form integration is registered with the system.

| Method | Return | Description |
|--------|--------|-------------|
| `getIntegrationId()` | `string` | The integration identifier |
| `getName()` | `string` | The display name |
| `isAvailable()` | `bool` | Whether the integration is available |

**WordPress hook:** `f12_cf7_doubleoptin_integration_registered`

---

## Migration Guide

### Legacy Hook to Typed Event

**Before (Legacy):**
```php
add_action( 'f12_cf7_doubleoptin_after_confirm', function ( $hash, $optIn ) {
    $email    = $optIn->get_email();
    $formData = maybe_unserialize( $optIn->get_content() );
    my_sync( $email, $formData );
}, 10, 2 );
```

**After (Typed Event):**
```php
add_action( 'f12_cf7_doubleoptin_register_event_listeners', function ( $dispatcher ) {
    $dispatcher->addListener(
        \Forge12\DoubleOptIn\Events\Lifecycle\OptInConfirmedEvent::class,
        function ( \Forge12\DoubleOptIn\Events\Lifecycle\OptInConfirmedEvent $event ) {
            my_sync( $event->getEmail(), $event->getFormData() );
        }
    );
}, 10, 1 );
```

**Benefits of Typed Events:**
- Full IDE autocompletion and type safety
- `getFormData()` returns a clean array (no `maybe_unserialize` needed)
- Events can be stopped with `$event->stopPropagation()`
- Priority control via `addListener( ..., $priority )`
- No dependency on the internal `OptIn` class

### Both approaches work simultaneously

The legacy `add_action('f12_cf7_doubleoptin_after_confirm', ...)` hook and the typed `OptInConfirmedEvent` listener are **not** mutually exclusive. Both fire during the same confirmation process. You can migrate incrementally.
