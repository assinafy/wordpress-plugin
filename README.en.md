# Assinafy for WordPress

[Português](README.md) · [Complete guide](docs/user-guide.en.md)

Upload PDFs, request electronic signatures, track progress and retrieve signed artifacts from WordPress.
Manage contract terms, amendments, renewals and termination in the admin or WP-CLI.

## Requirements and installation

- WordPress 6.8 or newer; PHP 8.2–8.5 with libsodium. PHP 8.5 is recommended for new deployments.
- An Assinafy workspace with the required plan features and sufficient document/credit balance.
- HTTPS for the WordPress admin and unique server-side encryption material; see [configuration](docs/user-guide.en.md#3-configuration).

Install the release ZIP through **Plugins → Add New → Upload Plugin**, then activate it.
A source checkout needs `composer install` to generate `vendor-prefixed`; the release ZIP includes runtime dependencies.
The plugin uses WordPress HTTP with PHP SDK 2.9.2. PHP follows active/security support windows, without a separate LTS edition.

## Connect

1. Open **Assinafy → Settings** and choose OAuth.
2. Use the existing public client to open the Assinafy authorization page. Sign in and select one workspace.
3. Approve `account:read documents:read documents:write webhooks:write offline_access`.
4. Paste the short-lived authorization code into the same administrator's page within 60 seconds.
5. Test the connection. Tokens are encrypted locally; refresh tokens rotate automatically. Disconnect removes local access and attempts remote revocation.

API-key authentication remains available for server integrations. Select the matching production or sandbox environment and workspace.
Keep credentials server-side. Blank secret fields preserve stored credentials. [Connection details and recovery](docs/user-guide.en.md#3-configuration).

## Complete document flow

1. Choose a readable PDF in **Assinafy → Send for signature**, provide signer names/emails and an optional message/deadline. Hooks, CLI and enabled host adapters use the same send service.
2. Validate the PDF, contacts, verification/channel pairing, contiguous signing order and expiry before network traffic.
3. Reserve a local request receipt. Resolve existing signers or create missing contacts, then upload the PDF and persist its remote ID.
4. Estimate assignment cost. Insufficient balance preserves the uploaded document and returns a recoverable local record; retry after funding the workspace.
5. Create a virtual assignment with explicit verification methods, notification channels and signing steps. The API sends invitations.
6. Signers complete the hosted Assinafy flow. Email/WhatsApp use codes; certificates stay with their owners; biometric verification captures consent, document images and liveness on Assinafy.
7. Authenticated webhooks and hourly reconciliation fetch authoritative document state. Identity review can delay certification even after signing.
8. View progress and download available original, certified, certificate-page, PAdES or bundle artifacts through capability-protected links.
9. Rename only before assignment; resend, extend deadlines and delete only when the API permits. Retry known partial operations without replaying an ambiguous upload.

For hook/SDK `expires_at`, date-only deadlines end at 23:59 UTC. Native date controls send 23:59:59 UTC explicitly. Datetimes without a timezone use UTC; explicit offsets are preserved.
See [payloads and recovery states](docs/document-flow.en.md) and [advanced hook examples](docs/user-guide.en.md#5-extension-points).

| Verification | API value | Notifications | Requirements |
| --- | --- | --- | --- |
| Email | `Email` | `Email` | Valid email |
| WhatsApp | `Whatsapp` | `Whatsapp` | Valid phone and workspace entitlement |
| A1/A3 certificate | `DigitalCertificate` | `Email` or `Whatsapp` | Existing eligible signer, certificate entitlement and isolated signing step |
| Biometric | `IdDocumentFaceMatchBasic` | `Email` or `Whatsapp` | Existing signer with valid CPF, entitlement, consent and hosted identity flow |

The compose screen supports email signers. Advanced hooks and the bundled SDK expose the other methods.
A pending identity review must be resolved in Assinafy before certification. WordPress never collects certificate private keys or biometric evidence.

## Webhooks and contracts

**Register webhook** manages this site's endpoint without replacing foreign endpoints. Supported events are intersected with the environment's event catalog.
The receiver verifies its URL token and Standard Webhooks signature, rejects stale signed deliveries and refetches the document.
Creation stores the encrypted signing secret; OAuth recovers a missing secret by rotation. [Webhook setup](docs/user-guide.en.md#7-webhooks).

**Assinafy → Contracts** reads current workspace terms on demand. Manage values, dates, renewal settings, amendments and termination.
Writes require contract-tracking entitlement. Each existing-contract change submits its revision; a conflict requires reloading.
Renewal preserves the original term to prevent duplicate advancement. Termination stops lifecycle alerts without deleting the PDF or altering signatures.
[Contract flow and payloads](docs/user-guide.en.md#contract-lifecycle-management-clm).

## Integrations and SDK

WooCommerce and Elementor integrations use the core service. Contact Form 7, WPForms and Gravity Forms ship as separate add-ons.
Configure host permissions, field mappings and consent before enabling them. [Host setup](docs/integrations.en.md).

The SDK also exposes envelopes, embedded signing sessions, branding, certified-copy delivery, notification policies and usage.
These are SDK APIs, not additional native WordPress screens. Account entitlements and OAuth scopes still apply.

- [Every SDK method, input and return value](docs/sdk-reference.md)
- [All published REST requests, responses and shared schemas](docs/api-payloads.md)
- [Official API contract](https://api.assinafy.com.br/v1/docs)
- [WP-CLI commands](docs/user-guide.en.md#6-wp-cli)
- [Troubleshooting](docs/user-guide.en.md#10-troubleshooting)
- [Multisite, privacy and uninstall behavior](docs/user-guide.en.md#multisite-and-privacy-behavior)

## Development

Use PHP 8.2–8.5 and a supported Node.js LTS release, at least 22.13.0.

```sh
composer install
npm ci
composer check
npm run start
npm run test:php:integration
npm run test:php:ajax
npm run test:e2e
npm run makepot
bash tests/build-zip.sh
bash bin/build-zip.sh
bash bin/build-addons.sh
```

[Development guide](docs/user-guide.en.md#11-development) describes host fixtures and multisite execution.
Live checks use `phpunit-sandbox.xml.dist` with credentials supplied through environment variables.
Production requires explicit `ASSINAFY_LIVE_PRODUCTION=1` and permission to create/delete disposable PDFs and contract terms.
Live checks never create assignments or change workspace settings. Completed signatures require consenting recipients and the corresponding test resources.

GPL-2.0-or-later. See [LICENSE](LICENSE).
