# Double Opt-In Addon API

**Stability:** `4.3.0` (introduced). Covered by semver from this version forward — see §7 Deprecation Policy.

**Audience:** developers building an addon plugin that extends the Double Opt-In core.

---

## 1. What the API is for

The Double Opt-In Core plugin (`double-opt-in`) provides the foundation and the Contact Form 7 integration. Every other feature — other form systems (Elementor, Gravity Forms, WPForms, Avada), reminder emails, analytics, validators, GDPR exports — lives in its own addon plugin. An addon registers itself with the Core at runtime and extends it through a small, stable set of extension surfaces.

An addon MUST NOT touch Core internals directly. It interacts only through:

1. **The public interfaces** documented here (`AddonInterface`, `AddonLicenseRegistryInterface`, `FormIntegrationInterface`, `MigrationInterface`).
2. **The EventDispatcher** for lifecycle and domain events.
3. **Documented WordPress hooks** (actions and filters listed below).

Anything else — direct class references to classes marked `@internal`, private properties reached via reflection, undocumented hooks — is unsupported and may break between Core minor releases.

---

## 2. Minimum viable addon

A complete working addon is ~30 lines of PHP plus a plugin header.

### 2.1 Plugin file

```php
<?php
/**
 * Plugin Name: Double Opt-In — Example Addon
 * Description: Does something useful after opt-in confirm.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: double-opt-in
 * Author:      Your Name
 * Text Domain: double-opt-in-example
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/vendor/autoload.php'; // or your own autoload

add_action( 'f12_cf7_doubleoptin_register_addons', function ( $registry, $container ) {
    $licenseRegistry = $container->get(
        \Forge12\DoubleOptIn\Licensing\AddonLicenseRegistryInterface::class
    );
    $registry->register( new \Example\Addon\ExampleAddon( $licenseRegistry ) );
}, 10, 2 );
```

### 2.2 Addon class

```php
namespace Example\Addon;

use Forge12\DoubleOptIn\Addon\AddonInterface;
use Forge12\DoubleOptIn\Container\ContainerInterface;
use Forge12\DoubleOptIn\Events\Lifecycle\OptInConfirmedEvent;
use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;
use Forge12\DoubleOptIn\Licensing\AddonLicenseRegistryInterface;

final class ExampleAddon implements AddonInterface {

    public function __construct(
        private AddonLicenseRegistryInterface $licenseRegistry
    ) {}

    public function getId(): string                   { return 'example'; }
    public function getName(): string                 { return 'Example'; }
    public function getVersion(): string              { return '1.0.0'; }
    public function getCoreVersionRequirement(): string { return '^4.3'; }
    public function getCapabilities(): array          { return [ 'example.feature' ]; }

    public function isAvailable(): bool {
        return $this->licenseRegistry->isLicensed( 'example' );
    }

    public function boot( ContainerInterface $container ): void {
        $dispatcher = $container->get( EventDispatcherInterface::class );
        $dispatcher->listen( OptInConfirmedEvent::class, function ( $event ) {
            error_log( 'Opt-in confirmed: ' . $event->getOptIn()->get_email() );
        } );
    }
}
```

That's the whole contract. Every other addon in the ecosystem (Reminder, Analytics, Validators, Form integrations…) is structurally identical.

---

## 3. Lifecycle

Request timeline, from `plugins_loaded` onward:

```
plugins_loaded priority 10
├── Core plugin instantiates
├── DI Container boots all service providers
│     ├── LicensingServiceProvider registers AddonLicenseRegistry
│     ├── MigrationServiceProvider registers MigrationRegistry
│     └── AddonServiceProvider registers AddonRegistry and schedules
│           the addon-registration hook on plugins_loaded:20
└── Core fires action `f12_cf7_doubleoptin_init`
      └── Paid bundle plugins (e.g. Pro) validate licenses and grant
            entitlements via AddonLicenseRegistryInterface::grant()

plugins_loaded priority 20
└── Core fires action `f12_cf7_doubleoptin_register_addons`
      ├── Each addon's registration callback runs:
      │     registry->register( new MyAddon( $licenseRegistry ) )
      └── AddonRegistry::bootAll() runs:
            for each addon where isAvailable() === true
              and getCoreVersionRequirement() matches F12_DOI_CORE_API_VERSION:
                addon->boot( $container )

admin_init priority 20
└── MigrationRegistry::runPending() applies any unapplied migrations
```

Three invariants you can rely on:

1. When `boot()` is called, every Core service provider has already registered and booted. All Core services the addon resolves from the container are usable.
2. When `boot()` is called, every other registered, available addon has been created but not necessarily booted yet. Do not assume another addon's capabilities are already wired up inside your own boot — listen for events instead.
3. `boot()` may be called only once per request per addon. Do not schedule long-running or I/O-bound work inline. Use `wp_schedule_single_event()` or event listeners.

---

## 4. Public interfaces

### 4.1 `AddonInterface`

Namespace: `Forge12\DoubleOptIn\Addon\AddonInterface`

| Method | Contract |
|---|---|
| `getId(): string` | Lowercase, kebab-case, stable across versions. Primary key for license matching and the AddonRegistry. |
| `getName(): string` | Human-readable, translatable. Used in admin UI. |
| `getVersion(): string` | Addon's own semver version. Must match the addon's plugin header. |
| `getCoreVersionRequirement(): string` | Semver constraint against `F12_DOI_CORE_API_VERSION`. See §5. |
| `isAvailable(): bool` | Called before boot. Return false to skip boot when prerequisites (license, third-party plugin, PHP extension) are not met. |
| `boot(ContainerInterface): void` | One-time bootstrap: register services, attach event listeners, register WordPress hooks. |
| `getCapabilities(): array` | Flat string list advertising features. Used by other addons / Core for capability-based feature detection. |

### 4.2 `AddonLicenseRegistryInterface`

Namespace: `Forge12\DoubleOptIn\Licensing\AddonLicenseRegistryInterface`

Addons are **consumers only** of this interface. They call `isLicensed($addonId)` to decide availability. License providers (the Pro bundle, or a future per-addon license plugin) are the only callers of `grant()` / `revoke()`.

| Method | For addons |
|---|---|
| `isLicensed(string $addonId): bool` | Primary availability check. |
| `getSource(string $addonId): ?string` | Optional: find out *who* granted the entitlement ("pro-bundle", etc.) — useful for admin UI. |
| `getLicensedAddons(): array` | Rarely useful to individual addons. |
| `grant(...)`, `revoke(...)` | For license providers only. An addon that calls these is wrong. |

### 4.3 `FormIntegrationInterface`

Namespace: `Forge12\DoubleOptIn\Integration\FormIntegrationInterface`

Implement when your addon integrates a specific form plugin (Elementor, WPForms, Gravity Forms, Avada, or a third-party form system). Extend `AbstractFormIntegration` to reduce boilerplate.

Register in your addon's `boot()`:

```php
public function boot( ContainerInterface $container ): void {
    $logger   = $container->get( LoggerInterface::class );
    $registry = $container->get( FormIntegrationRegistry::class );

    $registry->register( new MyFormIntegration( $logger ) );
}
```

Form-integration addons must also check the third-party plugin is active in `isAvailable()`:

```php
public function isAvailable(): bool {
    return $this->licenseRegistry->isLicensed( $this->getId() )
        && class_exists( 'MyFormPlugin\\Main' );
}
```

### 4.4 `MigrationInterface`

Namespace: `Forge12\DoubleOptIn\Migration\MigrationInterface`

Addons that need their own tables or (rarely) extend the shared schema register migrations in their `boot()`:

```php
public function boot( ContainerInterface $container ): void {
    $migrations = $container->get( MigrationRegistry::class );
    $migrations->register( new Create_Stats_Cache_Table_20260515() );
}
```

Rules:

- Migrations are **immutable once shipped**. Never edit a released migration. If you need to change something, ship a new forward-only migration.
- Migration IDs are globally unique. Convention: `{owner}_{yyyymmdd}_{short_slug}`. Core reserves the `core_*` prefix; addons must namespace by their addon ID.
- Addons **must not** ALTER the shared `{wp_prefix}f12_cf7_doubleoptin` table. If your addon genuinely needs a new column there, submit a PR to Core adding a Core migration and bump your `getCoreVersionRequirement()` to require the Core release that ships it.
- Addons may freely create tables prefixed `{wp_prefix}f12_doi_{addon_id}_*`, use post-meta (key namespace: `_f12_doi_{addon_id}_*`), and use options (namespace: `f12_doi_{addon_id}_*`).

---

## 5. Versioning

Two distinct versions exist:

| Constant | Meaning |
|---|---|
| `FORGE12_OPTIN_VERSION` | Plugin marketing version. Changes with every release. Don't depend on this. |
| `F12_DOI_CORE_API_VERSION` | Addon API version. Bumps **only** on breaking changes to any `@api`-tagged surface. Your addon depends on this. |

Addons declare their requirement via `getCoreVersionRequirement()`, which is checked by `AddonRegistry::bootAll()` against `F12_DOI_CORE_API_VERSION` using `SemverConstraint::matches()`. Addons whose requirement is not met are skipped with a logged warning; they are not crashed, they simply do not boot.

Supported constraint grammar:

- `^X.Y` — caret: `>=X.Y.0 <(X+1).0.0`
- `~X.Y` — tilde: `>=X.Y.0 <X.(Y+1).0`
- `>=X.Y[.Z]`, `<=X.Y[.Z]`, `>X.Y[.Z]`, `<X.Y[.Z]`, `=X.Y[.Z]`, or bare `X.Y[.Z]` (exact)

OR-clauses, wildcards, and pre-release suffixes are intentionally unsupported. If an addon needs them, the API has changed and you probably want a new major.

---

## 6. Events

The Core's `EventDispatcher` is the preferred cross-addon communication channel. Listen for these lifecycle events rather than coupling to class names of other addons.

| Event | Payload | Fired When |
|---|---|---|
| `Events\Lifecycle\OptInCreatedEvent` | `OptIn $optIn` | After opt-in row is created, before confirmation mail is sent. |
| `Events\Lifecycle\OptInConfirmedEvent` | `OptIn $optIn` | User clicks the confirmation link and the opt-in is marked confirmed. |
| `Events\Lifecycle\OptInExpiredEvent` | `OptIn $optIn` | An expiry sweep removes an unconfirmed opt-in past its TTL. |
| `Events\Lifecycle\OptInDeletedEvent` | `int $optInId`, `array $snapshot` | Opt-in record is being deleted; snapshot is its last known state. |
| `Events\Mail\MailSentEvent` | `string $recipient`, `string $subject`, `bool $success` | Any mail sent by Core or an addon via the shared mail path. |
| `Events\Form\FormSubmittedEvent` | `FormDataInterface $data` | A form integration has received and validated a submission. |
| `Events\Integration\IntegrationRegisteredEvent` | `FormIntegrationInterface $integration` | New form integration joined the FormIntegrationRegistry. |

Events are dispatched synchronously. Addons MUST NOT perform I/O-bound work inside listeners. Offload via `wp_schedule_single_event()` or use a background-job pattern.

---

## 7. Deprecation policy

Anything tagged `@api` in a PHPDoc class or method docblock is covered by this policy. Anything tagged `@internal`, or anything not tagged at all, is implementation detail.

Breaking changes to `@api` surface follow this process:

1. **Minor release N**: the affected method/class is kept, annotated `@deprecated since N+reason`, and emits a `_doing_it_wrong()` runtime notice when called (only in `WP_DEBUG` mode, to avoid spamming production logs).
2. **Minor release N+1** (minimum — Core may wait longer): the deprecated item remains; warning continues.
3. **Major release** following: removal allowed.

That is, addons that declare `^X.Y` where X is the current major are guaranteed their API will work until the next X+1 release, with at least one full minor release of advance warning.

Items labelled `@internal` may be removed in any release without warning.

---

## 8. Hooks (actions / filters)

Minimum documented set. Every hook below is `@api`-stable from Core API 4.3.0.

### Actions

| Hook | When | Use case |
|---|---|---|
| `f12_cf7_doubleoptin_register_addons` | plugins_loaded:20 | Register your addon with the AddonRegistry. |
| `f12_cf7_doubleoptin_register_integrations` | Core boot, inside IntegrationServiceProvider | Register a form integration directly with the FormIntegrationRegistry (alternative to registering inside your addon's `boot()`). |
| `f12_cf7_doubleoptin_init` | Core constructor | Pro-bundle-style plugins instantiate themselves here. |
| `f12_cf7_doubleoptin_integrations_initialized` | `init:5` | All form integrations' `registerHooks()` have been called. |

### Filters

| Filter | Use case |
|---|---|
| `f12_doi_settings_dto_from_array` | Extend the central settings DTO with addon-specific fields. |
| `f12_doi_form_settings_before_save` | Validate / mutate form-level settings on save. |
| `f12_cf7_doubleoptin_body` | Mutate email body after placeholder replacement. |
| `f12_cf7_doubleoptin_template_body` | Render a custom email template by template key. |

Additional hooks exist in the legacy surface (`docs/hooks-and-events.md`); they are **not** `@api`-tagged and may be replaced during Phase 2 extraction.

---

## 9. Things that will trip you up

- **Do not `require_once` other addons' files.** They may be deactivated or missing. Use the AddonRegistry for detection (`$registry->has('other-addon')`) and the EventDispatcher for communication.
- **Do not call `f12_doi_pro_is_unlocked()`.** It is a deprecated Pro-bundle alias. Use `AddonLicenseRegistryInterface::isLicensed($yourAddonId)`.
- **Do not persist addon state in the shared `f12_cf7_doubleoptin` table.** Use your own tables (via `MigrationInterface`) or namespaced options/post-meta.
- **Do not assume load order of addons.** If addon A depends on a service registered by addon B, listen for events or resolve from the container at use-time rather than at boot-time.
- **Do not hold references to the Container.** Resolve what you need in `boot()`, register listeners, and let the listeners resolve at dispatch time. Holding the container leaks it into places that don't expect it.

---

## 10. Example addons

The best reference implementations live in the `double-opt-in-pro` bundle plugin alongside this documentation:

- Service-only addon: `core/ReminderAddon.php`
- Validator addon: `core/MxValidatorAddon.php`
- Form-integration addon: `core/WPFormsAddon.php`
- Form-integration with legacy bridge: `core/ElementorAddon.php`
- Cross-form-adapter addon: `core/UserRegistrationAddon.php`

Copy one as a starting point, rename, adjust `getId()` and `getCapabilities()`, and replace the body of `boot()` with your feature's wiring.

---

*Documentation version: 1.0 — shipped with Core API 4.3.0.*
