== Double Opt-In for Contact Form 7 & Avada – Secure, GDPR-Compliant Email Verification ==
Contributors: forge12
Donate link: https://www.paypal.com/donate?hosted_button_id=MGZTVZH3L5L2G
Tags: contact form 7, double opt-in, avada, gdpr, email verification
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 3.2.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

**Protect your forms with GDPR-compliant Double Opt-In.**
Ensure valid emails, prevent fake signups, and stay compliant with Contact Form 7 and Avada.

== Description ==
**Double Opt-In** verifies every submitted email before it's stored or used.
This ensures:
- Only valid email addresses are accepted.
- GDPR requirements are met with proper tracking.
- Your database stays clean and reliable.

Seamless integration with **Contact Form 7** and **Avada Forms**.
Enable Double Opt-In per form, customize confirmation emails, and manage data retention with ease.

👉 Did you find this plugin helpful? Please consider [leaving a 5-star review](https://wordpress.org/support/plugin/double-opt-in/reviews/#new-post).

= Quick Start =
[Read the Quick Guide](https://www.forge12.com/blog/so-verwendest-du-das-double-opt-in-fuer-contact-form-7/)

---

## Free Features
- Visual drag & drop email template editor with block-based design
- Double Opt-In for Contact Form 7
- Double Opt-In for Avada Forms
- Centralized form settings management for all integrations
- GDPR-ready data storage (Form ID, Email, Registration/Confirmation Date & IP)
- GDPR-compliant anonymization of personal data
- Automatic cleanup of unconfirmed entries
- Predefined templates or fully custom opt-in mails
- Custom confirmation page redirects
- Dynamic conditions (enable opt-in based on user input)
- Full mail customization with send test & mobile preview
- Resend confirmation email from admin dashboard
- Rate limiting for form submissions
- Configurable token expiry and data retention
- WordPress Multisite support

---

## Pro Features
- Double Opt-In for Elementor Forms
- Double Opt-In for WPForms
- Double Opt-In for Gravity Forms
- Double Opt-Out System (unique opt-out links per submission)
- Opt-In Reminder System (automatic reminders for unconfirmed opt-ins)
- MX Validation (verify email domain has valid mail server)
- Domain Blocklist (block disposable/temporary email domains)
- Analytics Dashboard with charts and statistics
- CSV export of all opt-ins
- Automatic WordPress user creation after opt-in (with role assignment)
- Conditional email templates (dynamic content based on form data)
- Extended cleanup and export options
- Premium support

---

== Privacy & Telemetry ==
Starting with version **3.1.0**, the Double Opt-In plugin includes **optional anonymous telemetry** (opt-out).
This helps us understand which features are used most, so we can improve usability and remove unused complexity.

**We never sell or share data.**
Telemetry is used **only for product improvement and maintenance**.

### Telemetry data collected:
- `plugin_slug`, `plugin_version`
- `snapshot_date`
- `settings_json` (anonymized plugin settings)
- `features_json` (enabled features)
- `created_at`, `first_seen`, `last_seen`
- `counters_json` (opt-in/opt-out events)
- `wp_version`, `php_version`, `locale`

### GDPR / DSGVO Compliance
- No personal data, no cookies, no user tracking.
- Basis: *Art. 6 Abs. 1 lit. f DSGVO* (legitimate interest – plugin optimization).
- Telemetry is fully optional and can be disabled anytime in plugin settings.

---

== Installation ==
1. Upload to `/wp-content/plugins/`.
2. Activate via WordPress **Plugins** menu.
3. Edit your form and enable Double Opt-In.

---

== Upgrade Notice ==

= 3.2.0 =
**Important: Major Update – Please backup before updating!**
This version includes significant changes to the form management system, email templates, and database structure.
We strongly recommend creating a full site backup before updating.
New features: Visual email editor, centralized form settings, GDPR anonymization, and more.

== Changelog ==

= 3.2.1 =
* Fix: Fixed a fatal error (TypeError) when opening the Double Opt-In panel on a new, unsaved Contact Form 7 form.
* Improved: Added a notice in the CF7 Double Opt-In tab prompting users to save the form before configuring Double Opt-In.

= 3.2.0 =
**Email Template Editor:**
* New: Visual drag & drop email template editor with block-based design.
* New: Pre-built email template presets for quick setup.
* New: Placeholder library with all available form fields.
* New: Opt-out email template support in the editor.
* New: Send test email functionality to preview emails before going live.
* New: Mobile preview mode to check responsive email design.
* New: Rich text editing with formatting options.
* New: Block registry for extensible template components.

**Form Management:**
* New: Centralized form settings management panel for all form integrations (CF7, Avada, Elementor).
* New: Resend confirmation email directly from the admin dashboard.
* New: Unified settings interface across all supported form plugins.

**WordPress & Multisite:**
* New: Full WordPress Multisite support – network-wide activation creates database tables on all existing sites.
* New: Automatic table creation for new sites added to the network (via `wp_initialize_site` hook).

**GDPR & Security:**
* New: GDPR-compliant anonymization of personal data instead of deletion.
* New: Rate limiting for form submissions to prevent abuse.
* New: Consent export (CSV) for GDPR compliance.
* New: WordPress Privacy Tools integration (personal data export & erasure requests).
* New: Configurable token expiry settings.
* New: Configurable data retention settings.
* Security: Fixed potential XSS vulnerabilities in admin screens.
* Security: Improved input sanitization throughout the plugin.

**Architecture & Performance:**
* New: Event-driven architecture for form submissions and opt-in lifecycle.
* New: Service container with dependency injection for improved extensibility.
* Improved: CSS extracted to external files for better caching.
* Improved: Code refactored for enterprise-level reliability.

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
* Improved: Increased compatibility between Free and Pro version
* Improved: Added support for Avada 7.12.2

= 3.0.70 =
* Fixed: Fixed a bug stopping the CF7 forms to attach uploaded files after opt-in confirmation

= 3.0.62 =
* New: Added hook `f12_cf7_doubleoptin_skip_option` to allow skipping opt-ins if required

= 3.0.60 =
* Fix: Fixed a bug causing Elementor to stop sending opt-in mails

= 3.0.51 =
* New: Avada Opt-In now leverages the Notification System for handling emails. The "Send to Email" action remains supported.

(Older versions trimmed – full changelog available on plugin site.)