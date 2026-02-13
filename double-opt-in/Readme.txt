=== Double Opt-In for Contact Form 7 & Avada – Secure, GDPR-Compliant Email Verification ===
Contributors: forge12
Donate link: https://www.paypal.com/donate?hosted_button_id=MGZTVZH3L5L2G
Tags: contact form 7, double opt-in, avada, gdpr, email verification
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 3.4.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

**Protect your forms with GDPR-compliant Double Opt-In.**
Ensure valid emails, prevent fake signups, and stay compliant with Contact Form 7 and Avada.

== Description ==

**Double Opt-In** adds a mandatory email verification step to your Contact Form 7 and Avada forms.
When a visitor submits your form, the original mail is **not** sent immediately. Instead, the plugin:

1. Stores the submission in a secure database table.
2. Sends a confirmation email with a unique, time-limited link.
3. Only after the visitor clicks that link is the original form mail delivered.

This ensures:

* Only **valid, verified email addresses** reach your inbox.
* **GDPR / DSGVO requirements** are met with proper consent tracking, IP logging, and data retention.
* Your database stays **clean and reliable** -- no fake or mistyped addresses.

Seamless integration with **Contact Form 7** and **Avada Forms**.
Enable Double Opt-In per form, customize confirmation emails with a visual editor, and manage data retention with ease.

= How It Works =

1. A visitor fills out your Contact Form 7 or Avada form and clicks submit.
2. The plugin intercepts the submission, stores the form data, and generates a unique hash.
3. A confirmation email is sent to the visitor's email address containing a verification link.
4. The visitor clicks the link. The plugin verifies the hash, marks the opt-in as confirmed, and sends the original form mail (as if the form was just submitted).
5. The confirmed opt-in is logged in the admin dashboard with timestamps and IP addresses for full GDPR compliance.

= Quick Start =

[Read the Quick Guide](https://www.forge12.com/blog/so-verwendest-du-das-double-opt-in-fuer-contact-form-7/)

= Free Features =

* **Visual Email Editor** -- drag & drop block-based email template editor with live preview and mobile preview
* **Double Opt-In for Contact Form 7** -- per-form activation with full CF7 integration
* **Double Opt-In for Avada Forms** -- works with Avada's built-in form builder
* **Centralized Form Settings** -- manage all form integrations from a single admin panel
* **Email Template Presets** -- choose from pre-built templates or create your own
* **Send Test Email** -- preview your confirmation emails before going live
* **Custom Confirmation Pages** -- redirect users to a specific page after confirmation
* **Dynamic Conditions** -- enable opt-in based on user input (e.g. only when a checkbox is checked)
* **Resend Confirmation** -- resend the confirmation email from the admin dashboard
* **Delete Confirmation Modal** -- safety dialog before deleting an opt-in record to prevent accidental deletion
* **GDPR Consent Export** -- export individual consent records as JSON or CSV directly from the opt-in detail view
* **CAPTCHA Compatibility** -- automatically bypasses Forge12 Captcha, Google reCAPTCHA, and hCaptcha during opt-in confirmation to ensure mail delivery
* **Rate Limiting** -- configurable IP and email rate limits to prevent abuse
* **Error Redirect Page** -- redirect users to a custom page when an opt-in error occurs (rate limit, invalid email)
* **Token Expiry** -- confirmation links expire after a configurable time period
* **GDPR Data Storage** -- tracks Form ID, Email, Registration/Confirmation Date & IP, Consent Text
* **GDPR Anonymization** -- anonymize personal data instead of deleting it
* **WordPress Privacy Tools** -- integrates with WordPress personal data export and erasure requests
* **Automatic Cleanup** -- configurable auto-deletion of confirmed and unconfirmed entries
* **Category System** -- organize opt-ins into categories for better management
* **Pagination & Search** -- search and filter opt-in records in the admin dashboard
* **Admin Tooltips** -- contextual help tooltips throughout the admin interface
* **WordPress Multisite** -- network-wide activation creates tables on all sites automatically
* **Developer Hooks** -- 18 action hooks, 23 filters, and 11 typed events for full extensibility

= Pro Features =

Unlock the full potential of Double Opt-In with the [Pro version](https://www.forge12.com):

**Additional Form Integrations:**

* **Double Opt-In for Elementor Forms** -- seamless integration with Elementor's form widget
* **Double Opt-In for WPForms** -- full support for WPForms submissions
* **Double Opt-In for Gravity Forms** -- complete Gravity Forms integration

**Email Validation & Spam Protection:**

* **Unique Email Validation** -- prevent duplicate submissions per email address (block, silent, or redirect mode)
* **MX Validation** -- verify that the email domain has a valid mail server before sending
* **Domain Blocklist** -- block disposable and temporary email domains

**Email & Communication:**

* **Double Opt-Out System** -- unique opt-out links per submission with confirmation emails
* **Opt-In Reminder System** -- automatic reminders for unconfirmed opt-ins via cron
* **Conditional Email Templates** -- dynamic content blocks based on form data
* **Multi-Column Layouts** -- 2-column, 3-column, and sidebar layouts in the email editor
* **Image & Social Blocks** -- add images and social media icons to your emails

**Analytics & Export:**

* **Analytics Dashboard** -- charts and statistics for opt-in/opt-out rates
* **CSV Export** -- export all opt-in records for external processing

**User Management:**

* **Auto User Creation** -- automatically create WordPress users after opt-in confirmation with configurable role assignment

**Support:**

* **Premium Support** -- priority email support

== Installation ==

= Automatic Installation =

1. Go to **Plugins > Add New** in your WordPress admin.
2. Search for **"Double Opt-In"**.
3. Click **Install Now** and then **Activate**.

= Manual Installation =

1. Download the plugin ZIP file.
2. Upload it to `/wp-content/plugins/double-opt-in/` or use **Plugins > Add New > Upload Plugin**.
3. Activate via the WordPress **Plugins** menu.

= First-Time Setup =

1. After activation, go to **Double Opt-In** in the WordPress admin menu.
2. Navigate to **Forms** to see all detected Contact Form 7 and Avada forms.
3. Click on a form to enable Double Opt-In and configure the confirmation email.
4. Set the **Recipient Field** to the form field that contains the visitor's email address (e.g. `your-email`).
5. Customize the **Subject** and **Body** of the confirmation email, or choose a template preset.
6. Save the settings and test the form.

= Requirements =

* WordPress 5.0 or higher
* PHP 8.0 or higher
* Contact Form 7 5.0+ and/or Avada 7.0+

== Frequently Asked Questions ==

= How does Double Opt-In work? =

When a visitor submits your form, the plugin stores the submission and sends a confirmation email with a unique link. The original form mail is only delivered after the visitor clicks that link. This verifies that the email address is valid and belongs to the person who filled out the form.

= Is this plugin GDPR / DSGVO compliant? =

Yes. The plugin tracks all data required for GDPR compliance: consent text, registration and confirmation timestamps, IP addresses, and form data. It integrates with WordPress Privacy Tools for personal data export and erasure requests. You can configure automatic data retention and anonymization policies.

= Which form plugins are supported? =

The free version supports **Contact Form 7** and **Avada Forms**. The Pro version adds support for **Elementor Forms**, **WPForms**, and **Gravity Forms**.

= Can I customize the confirmation email? =

Yes. The plugin includes a visual drag & drop email editor with block-based design. You can choose from pre-built template presets or create your own. Placeholders like `[doubleoptinlink]`, `[doubleoptin_form_date]`, and form field values are replaced automatically.

= What happens if the user does not confirm? =

Unconfirmed opt-ins are stored in the database and can be cleaned up automatically. You can configure the retention period for unconfirmed entries in the settings (e.g. delete after 30 days). In the Pro version, you can also send automatic reminder emails.

= Can I redirect the user to a specific page after confirmation? =

Yes. In the per-form settings, you can select a **Confirmation Page**. The user will be redirected there after clicking the confirmation link.

= Does the plugin work with CAPTCHA plugins? =

Yes. The plugin automatically disables CAPTCHA validation (Google reCAPTCHA, hCaptcha, CF7 Captcha by Forge12) when re-sending the original form mail after confirmation. This prevents false spam detections during the confirmation step. CAPTCHA is re-enabled immediately after the mail has been sent.

= Can I enable Double Opt-In only when a checkbox is checked? =

Yes. Use the **Conditions** setting in the per-form configuration. Enter the name of a form field (e.g. a checkbox). Double Opt-In will only be triggered when that field has a value.

= How do I access form data after confirmation? =

**Legacy approach (WordPress hook):**

`add_action( 'f12_cf7_doubleoptin_after_confirm', function( $hash, $optIn ) {`
`    $data = maybe_unserialize( $optIn->get_content() );`
`}, 10, 2 );`

**Modern approach (typed event, since 4.0):**

Use `OptInConfirmedEvent` via the EventDispatcher. The event provides `getFormData()`, `getEmail()`, `getFormId()`, and more. See `docs/hooks-and-events.md` for the complete reference.

= Does it work with WordPress Multisite? =

Yes. When activated network-wide, the plugin creates database tables on all existing sites. New sites added to the network automatically get their own tables via the `wp_initialize_site` hook.

= Can I use this without Contact Form 7 or Avada? =

The free version requires at least one supported form plugin. However, developers can register custom form integrations using the `f12_cf7_doubleoptin_register_integrations` action hook. See the developer documentation for details.

= Where can I find the developer documentation? =

The complete hook, filter, and event reference is available at `docs/hooks-and-events.md` inside the plugin directory. It covers all 18 action hooks, 23 filters, and 11 typed events with code examples.

= How do I report a bug or request a feature? =

Please visit [forge12.com](https://www.forge12.com) or contact us via the WordPress support forum.

== Screenshots ==

1. **Opt-In Dashboard** -- Overview of all opt-in records with status, email, form, date, and actions.
2. **Form Settings** -- Per-form configuration with sender, subject, recipient field, confirmation page, and conditions.
3. **Email Template Editor** -- Visual drag & drop editor with blocks, live preview, and mobile preview.
4. **Template Presets** -- Choose from pre-built email template designs.
5. **Single Opt-In View** -- Detailed view of an opt-in record with form data, timestamps, and IP addresses.
6. **Global Settings** -- Configure data retention, token expiry, telemetry, and opt-out settings.
7. **Category Management** -- Organize opt-in records into categories.

== Privacy & Telemetry ==

Starting with version **3.1.0**, the Double Opt-In plugin includes **optional anonymous telemetry** (opt-out).
This helps us understand which features are used most, so we can improve usability and remove unused complexity.

**We never sell or share data.**
Telemetry is used **only for product improvement and maintenance**.

= Telemetry data collected =

* `plugin_slug`, `plugin_version`
* `snapshot_date`
* `settings_json` (anonymized plugin settings)
* `features_json` (enabled features)
* `created_at`, `first_seen`, `last_seen`
* `counters_json` (opt-in/opt-out event counts)
* `wp_version`, `php_version`, `locale`

= GDPR / DSGVO Compliance =

* No personal data, no cookies, no user tracking.
* Legal basis: *Art. 6 Abs. 1 lit. f DSGVO* (legitimate interest -- plugin optimization).
* Telemetry is fully optional and can be disabled anytime in **Double Opt-In > Settings**.

== Upgrade Notice ==

= 3.4.0 =
Fixed translation loading issues on WordPress 6.7+, review notice not displaying, and database table missing errors.
Added 133+ missing German translations for the Email Editor and related features. Safe to update.

= 3.3.0 =
New: Delete confirmation modal, GDPR consent export (JSON/CSV), admin tooltips, error redirect page, hCaptcha compatibility.
New: Unique Email redirect behavior (Pro).
Fixed reCAPTCHA re-activation typo. Safe to update -- no database changes.

= 3.2.3 =
Bugfix release: Fixes broken toggle switches on the settings page. Safe to update -- no database changes.

= 3.2.2 =
Bugfix release: Fixes double-firing of the after_confirm hook. Safe to update -- no database changes.

= 3.2.1 =
Bugfix release: Fixes a fatal error on new, unsaved CF7 forms. Safe to update.

= 3.2.0 =
**Important: Major Update -- Please backup before updating!**
This version includes significant changes to the form management system, email templates, and database structure.
We strongly recommend creating a full site backup before updating.
New features: Visual email editor, centralized form settings, GDPR anonymization, and more.

= 3.1.0 =
Adds optional anonymous telemetry (opt-out). No breaking changes.

== Changelog ==

= 3.4.0 =

**Bug Fixes:**

* Fix: Fixed translation loading too early warning on WordPress 6.7+ (`_load_textdomain_just_in_time` notice).
* Fix: Fixed review notice never displaying due to namespace resolution issue.
* Fix: Fixed Free and Pro plugin constant/function redeclaration conflicts when both plugins are active simultaneously.
* Fix: Fixed TestEmailBlocker fatal error in distribution builds where test dependencies are not included.
* Fix: Fixed Pro upgrade prompt ("Pro Feature", "The '{block}' block requires the Pro version.") displaying in English instead of the active language.
* Fix: Fixed database "table doesn't exist" error for `f12_cf7_doubleoptin_categories` when plugin files are uploaded manually or the database is restored without custom tables.

**Improvements:**

* Improved: Added 133+ missing German translations covering the Email Editor, Placeholder Mapping, Email Template Post Type, Email Presets, and Pro upgrade prompts.
* Improved: Added formal German (Sie) translations for all new strings.
* Improved: Database table existence safety net -- both custom tables are now verified and recreated on every update cycle, independent of the activation hook.
* Improved: Updated "Upgrade to Pro" links to point to the correct product page.

= 3.3.0 =

**New Features:**

* New: Delete confirmation modal -- clicking "Delete DOI" now opens a confirmation dialog to prevent accidental deletion. Dismissible via Cancel, overlay click, or Escape key.
* New: GDPR consent record export -- export individual opt-in records as JSON or CSV directly from the opt-in detail view.
* New: Admin tooltips -- contextual help tooltips with descriptions throughout the admin interface.
* New: Error redirect page -- configure a per-form redirect page for opt-in errors (rate limit, invalid email, etc.).
* New: hCaptcha compatibility -- hCaptcha validation is now automatically bypassed during opt-in confirmation mail delivery, alongside Forge12 Captcha and Google reCAPTCHA.

**New Features (Pro):**

* New: Unique Email – Redirect behavior. When a duplicate email is detected, users can now be redirected to a configurable WordPress page instead of just seeing an error or silent rejection.
* New: Dedicated Redirect Page selector in the Unique Email settings (per-form). Only visible when behavior is set to "Redirect to page".
* New: `UNIQUE_EMAIL_DUPLICATE` error code for distinguishing duplicate email rejections from other validation errors (e.g. MX check).

**Bug Fixes:**

* Fix: Success and error messages (e.g. "Opt-In deleted") are now rendered as styled alerts instead of plain text.
* Fix: Fixed a typo in `OptInFrontend::afterSendDefaultMail()` that prevented Google reCAPTCHA from being re-enabled after opt-in confirmation mail delivery (`wpcf7_recaptcha_verifiy_response` → `wpcf7_recaptcha_verify_response`).
* Fix: Silent mode for Unique Email no longer shows the raw string `unique_email_rejected` in the toast notification. It now displays a properly translated message.
* Fix: CF7 no longer sends its default success mail when a duplicate email is detected. The original mail is now correctly blocked via `wpcf7_skip_mail`.
* Fix: CF7 now shows an inline error message (instead of the success message) when Unique Email rejects a submission in block or redirect mode.
* Fix: WPForms and Gravity Forms no longer display a contradictory success confirmation when a validation error occurs. The confirmation message is automatically hidden and replaced by the error toast or redirect.
* Fix: Elementor Forms now correctly validate unique emails. The `f12_cf7_doubleoptin_validate_recipient` filter was not called in the legacy `OptInFrontend::maybeCreateOptIn()` path used by Elementor, so duplicate emails were never detected.
* Fix: Elementor success messages are now hidden when a validation error (block/redirect) occurs, preventing contradictory success and error messages.
* Fix: Error notification AJAX polling no longer loops infinitely. The internal `doi_check_submission_error` request was intercepted by its own XHR hook, causing a continuous polling cycle every ~800ms.

**Improvements:**

* Improved: Updated translations (German, German formal, French, English).
* Improved: CAPTCHA bypass now covers Forge12 Captcha, Google reCAPTCHA, and hCaptcha across all three bypass layers (SpamMechanics, AbstractFormIntegration, OptInFrontend).
* Improved: ErrorNotification system now stores a `hide_confirmation` flag based on the validation error behavior (block/redirect vs. silent). The frontend uses this to hide form-plugin success messages when an error should be visible.
* Improved: Error handling in CF7 integration prevents mail sending for all rejection modes (block, silent, redirect).
* Improved: `OptInFrontend::maybeCreateOptIn()` now calls the `f12_cf7_doubleoptin_validate_recipient` filter, enabling MX validation, domain blocklist, and unique email checks for all legacy form integrations (Elementor).

**Testing:**

* New: Unit tests for SpamMechanics (10 tests) -- verifies CAPTCHA bypass for Forge12 Captcha, Google reCAPTCHA, and hCaptcha, including guard conditions (no hash, invalid hash, already confirmed).
* New: E2E tests for delete confirmation modal (7 tests) -- verifies modal open/close behavior (Cancel, overlay click, Escape), re-open, correct delete URL, and red button styling.

= 3.2.4 =

**New Features:**

* New: Universal Error Notification System – displays a toast notification to the user when an OptIn error occurs (rate limit, invalid email, etc.), independent of the form plugin used.
* New: Error Redirect Page – configure a per-form redirect page for OptIn errors. When set, users are redirected to the selected page instead of seeing a toast notification. The error code is appended as a query parameter (`?doi_error=rate_limit_ip`) for context-specific content.
* New: OptInError value object for typed, translatable error codes across all integrations.

**Improvements:**

* Improved: Error handling in all form integrations now uses the centralized OptInError and ErrorNotification system.
* Improved: Frontend error detection covers Contact Form 7, WPForms, Gravity Forms, Avada, Elementor, and generic AJAX/form submissions.

= 3.2.3 =

**Bug Fixes:**

* Fix: Fixed broken toggle switches on the settings page. Clicking the toggle button or its label text now correctly toggles the value.
* Fix: Removed stale `<label class="toggle-label">` elements that were rendered as duplicate toggle buttons due to WordPress admin CSS.
* Fix: Removed non-functional `<label class="overlay">` elements (leftover from an older CSS-only toggle pattern).
* Fix: Replaced incorrect `esc_attr_e()` with `echo esc_attr()` for HTML `for` attribute values in the telemetry toggle.

**Improvements:**

* Improved: The entire toggle row (button + description text) is now clickable, not just the small toggle button.
* Improved: Added CSS for `.f12-checkbox-toggle` for proper flex layout of toggle components.

= 3.2.2 =

**Bug Fixes:**

* Fix: Fixed double-firing of the `f12_cf7_doubleoptin_after_confirm` hook. The hook was triggered twice per confirmation (once by the EventDispatcher bridge and once manually). It now fires exactly once with the original `($hash, $optIn)` parameters.

**Developer Features:**

* New: Added `getFormData()` method to `OptInConfirmedEvent`, providing direct access to submitted form field data via the typed event system.
* New: Added `shouldBridgeToWordPress()` to the Event base class, allowing individual events to opt out of automatic WordPress hook bridging to prevent duplicate hook calls.
* New: Added comprehensive developer documentation (`docs/hooks-and-events.md`) with complete reference for all 18 action hooks, 23 filters, and 11 typed events.

**Improvements:**

* Improved: Updated hook usage hints in the admin panel with both legacy and event-based code examples.

= 3.2.1 =

**Bug Fixes:**

* Fix: Fixed a fatal error (TypeError) when opening the Double Opt-In panel on a new, unsaved Contact Form 7 form.

**Improvements:**

* Improved: Added a notice in the CF7 Double Opt-In tab prompting users to save the form before configuring Double Opt-In.

= 3.2.0 =

**Email Template Editor:**

* New: Visual drag & drop email template editor with block-based design.
* New: Pre-built email template presets (Blank, Dark Professional, Yellow Bold, Minimal Clean, Opt-Out Confirmation).
* New: Placeholder library with all available form fields and system variables.
* New: Opt-out email template support in the editor.
* New: Send test email functionality to preview emails before going live.
* New: Mobile preview mode to check responsive email design.
* New: Rich text editing with formatting options (bold, italic, links, lists).
* New: Block registry for extensible template components (Pro: multi-column, images, social icons).

**Form Management:**

* New: Centralized form settings management panel for all form integrations (CF7, Avada, Elementor).
* New: Resend confirmation email directly from the admin dashboard.
* New: Unified settings interface across all supported form plugins.
* New: Field mapping system for connecting form fields to email placeholders.

**WordPress & Multisite:**

* New: Full WordPress Multisite support -- network-wide activation creates database tables on all existing sites.
* New: Automatic table creation for new sites added to the network (via `wp_initialize_site` hook).

**GDPR & Security:**

* New: GDPR-compliant anonymization of personal data instead of deletion.
* New: Rate limiting for form submissions to prevent abuse (configurable per IP and per email).
* New: Consent text snapshot stored per opt-in record for audit trail.
* New: Consent export (CSV) for GDPR compliance.
* New: WordPress Privacy Tools integration (personal data export & erasure requests).
* New: Configurable token expiry settings (default: 48 hours).
* New: Configurable data retention settings for confirmed and unconfirmed entries.
* Security: Fixed potential XSS vulnerabilities in admin screens.
* Security: Improved input sanitization throughout the plugin.

**Architecture & Performance:**

* New: Event-driven architecture with 11 typed events for form submissions and opt-in lifecycle.
* New: Service container with dependency injection for improved extensibility.
* New: `WordPressHookBridge` for backward compatibility between legacy hooks and typed events.
* New: Form integration registry for pluggable form builder support.
* New: REST API for email template management (`/wp-json/f12-doi/v1/email-templates`).
* Improved: CSS extracted to external files for better caching.
* Improved: Code refactored to PSR-4 autoloading with modern PHP architecture.

**Bug Fixes & Improvements:**

* Fix: Fixed double mail sending issue on CF7 and Avada forms.
* Fix: Fixed email button URLs being incorrectly escaped when using placeholders.
* Improved: Refactored CF7 and Avada form integration architecture.
* Improved: Redesigned admin dashboard with dedicated opt-in management views.
* Improved: Updated translations (German).

= 3.1.1 =

* Improved: Enhanced compatibility with major CAPTCHA plugins to ensure smoother user verification.

= 3.1.0 =

* New: Added optional anonymous telemetry (opt-out) to improve plugin performance and usability.
* Privacy: Documented all telemetry fields collected.
* Improved: Minor optimizations for compatibility and maintainability.
* Change: Removed frontend support link injection for improved transparency and compliance with WordPress guidelines.
* Improved: Branding is now shown only in the plugin settings (admin area).

= 3.0.72 =

* Improved: Increased compatibility between Free and Pro version.
* Improved: Added support for Avada 7.12.2.

= 3.0.70 =

* Fixed: Fixed a bug stopping the CF7 forms to attach uploaded files after opt-in confirmation.

= 3.0.62 =

* New: Added hook `f12_cf7_doubleoptin_skip_option` to allow skipping opt-ins if required.

= 3.0.60 =

* Fix: Fixed a bug causing Elementor to stop sending opt-in mails.

= 3.0.51 =

* New: Avada Opt-In now leverages the Notification System for handling emails. The "Send to Email" action remains supported.

= 3.0.50 =

* New: Added Avada Forms integration.
* Improved: Reworked admin UI for better usability.

= 3.0.0 =

* New: Complete rewrite of the plugin core.
* New: Category system for organizing opt-in records.
* New: Improved admin dashboard with pagination and search.
* New: Custom confirmation page redirects.
* New: Dynamic conditions for enabling opt-in per form.
* Improved: Database schema with additional tracking fields.

= 2.0.0 =

* New: Support for custom email templates.
* New: IP address logging for registration and confirmation.
* Improved: Opt-in record management in the admin dashboard.

= 1.0.0 =

* Initial release.
* Double Opt-In for Contact Form 7.
* Basic confirmation email customization.
