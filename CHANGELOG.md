# Changelog

All notable changes to the Assinafy WordPress plugin are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Kept pending signer details through unassigned uploads, preserved native metadata escaping,
  and retained email-based privacy redaction before a remote signer ID exists.

- Matched webhook account validation to configured credential constants, and refreshed the
  README against the current API, workflow, testing and release behavior.

- Restored the native PDF chooser and signer repeater, rejected incomplete/duplicate signer
  lists before sending, and isolated document action forms from WordPress's editor form.
- Preserved webhook tokens when saving settings and reset cached credentials when switching
  multisite blogs. Credential encryption now uses WordPress's resolved authentication salt.
- Added network activation/new-site provisioning, per-site uninstall retention, trash cleanup,
  native document read permissions, and persistent privacy redaction through later syncs.
- Serialized concurrent sends with a native database lock, scoped keys to account/environment,
  and recorded uploads before requesting signatures so failed assignments can resume safely.
- Validated signer methods, steps and deadlines before upload, and rejected malformed or
  mismatched document responses without overwriting the local mirror.
- Populated WooCommerce completion emails before rendering, supported plain-text emails and
  duplicate document names, and retained older selected PDFs when saving product data.
- Required CI and Plugin Check before release; corrected clean build dependency setup and
  enforced all three package version markers. Excluded local tooling from distribution zips.

### Added

- Separate 0.1.0 development adapters for native Gravity Forms feeds, Contact Form 7 and
  WPForms, with original-request recovery and shared core document records.
- Bundled Elementor Pro Forms action with native save authorization and contract tests;
  licensed Gravity Forms/Elementor Pro and WPForms Pro validation remains pending.
- Separate add-on packaging, free-host integration CI setup and updated adapter guides.

- A core `assinafy_ready` adapter registration hook, independent bundled WooCommerce
  bootstrap, and documented integration ownership and rollout.
- Validated document source references saved before upload and preserved across status
  synchronization and retries, with existing WooCommerce recovery keys retained.
- Browser regressions, HPOS/legacy order coverage, multisite/privacy/settings/recovery tests,
  supported WordPress/WooCommerce CI pairs, and packaging regression checks.
- An explicit failure for empty sandbox test runs and WordPress privacy-policy guidance.
- Explicit WooCommerce HPOS/Checkout Blocks compatibility and cleanup of queued sends on
  deactivation, plus updated pt_BR translations for all current messages.
- Development-only Playwright tests and a fixed `qs` override for the wp-env dependency tree.

## [1.0.0] - 2026-09-13

### Added

- Send any PDF in the media library for electronic signature from the WordPress admin, with a
  signer repeater, a custom message and an expiry date.
- `assinafy_document` post type mirroring the remote document: status, signers, per-signer
  progress, artifacts and the Assinafy document id.
- Signature status panel on the document screen, with per-signer signing links, resend, expiry
  extension, cancellation, rename before assignment, and the document's activity trail read
  from the API.
- Capability-gated download proxy. Artifact URLs carry the account API key and are never
  rendered, stored or handed to a browser; the plugin streams the bytes instead.
- `do_action( 'assinafy_send_document', $args )` as the universal integration point, with a
  matching asynchronous variant scheduled through WP-Cron.
- WooCommerce integration: send a document when an order completes, with an order note on
  success and on failure. High-Performance Order Storage is declared compatible.
- Token-authenticated webhook receiver. Deliveries are never trusted as a source of state — the
  referenced document is re-fetched through the API before anything is written.
- Hourly reconcile task, so webhooks are optional and a missed delivery cannot strand a record.
- WP-CLI commands: `wp assinafy status`, `send`, `sync` and `webhook`.
- Cost and plan gate before every send, refusing when the account has insufficient resources and
  surfacing the reason.
- Credentials encrypted at rest with libsodium, with `ASSINAFY_API_KEY` and
  `ASSINAFY_ACCOUNT_ID` wp-config constants taking precedence over the stored values.
- Custom capabilities `assinafy_send`, `assinafy_manage` and `assinafy_view`.
- Privacy exporter and eraser registered with the WordPress privacy tools. The eraser redacts
  signer identity while retaining signature evidence.
- Brazilian Portuguese translation.

### Security

- The plugin ships no HTTP client of its own: requests go through `wp_remote_request()`, so a
  site's proxy, TLS and request filters all apply, and no vendored HTTP library can collide with
  another plugin's copy.
- Request URIs are constrained to the configured API origin. A URI naming its own scheme, host
  or parent path is rejected before a request is built, so a credentialled request cannot be
  redirected off-origin.
- Webhook routes authenticate with a rotatable high-entropy token compared using
  `hash_equals()`, and deliveries are de-duplicated by delivery id.

[1.0.0]: https://github.com/assinafy/wordpress-plugin/releases/tag/v1.0.0
