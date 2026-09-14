# Assinafy for WPForms

*[Leia em português](README.md) · English*

A separate WordPress plugin that requests a signature on an existing Media Library PDF after WPForms accepts a valid, non-spam submission. Version 0.1.0 supports one signer mapped from a Name or Single Line Text field and an Email field. Assinafy core handles account credentials, API transport, document status, signing links, downloads and WordPress privacy tools.

Requires WordPress 6.8+, PHP 8.2+, Assinafy 1.0.0+ and WPForms Lite or Pro 1.9.8.1+. Real-host integration tests use official **WPForms Lite 2.0.1.1**. Pro entry-ID behavior is tested through the documented callback contract only; a licensed Pro installation and its paid features have not been tested. The plugin dependency header requires Assinafy; WPForms is checked at runtime so installing Pro does not also require Lite.

## Setup

1. Install and configure Assinafy, then install WPForms Lite or Pro.
2. Install this addon as `assinafy-wpforms` and activate it.
3. Upload the PDF to the Media Library.
4. Edit a WPForms form and open **Settings → Assinafy**. Enable requests, choose the PDF, map the signer name/email fields and optionally add a plain-text invitation message. Save the form. Invalid PDF or field mappings disable the workflow on save.
5. Explain the signature request and obtain any required consent in the form. Review the service disclosure below before enabling it.

Changing the workflow requires both permission to edit that WPForms form and `assinafy_send`. The message does not expand WPForms smart tags; leaving it blank uses the core default. Missing or invalid mapped contact details prevent acceptance, even when those native fields are optional.

## Acceptance, receipts and retries

The first signature request runs synchronously on [`wpforms_process_complete`](https://wpforms.com/developers/wpforms_process_complete/), after WPForms validation, spam checks and notification processing. A failed notification email does **not** cancel an otherwise accepted submission. This is not a payment-success hook: it does not wait for payment settlement. It does not generate PDFs, implement conditional workflows, or add premium WPForms features.

WPForms Lite returns entry ID `0` and does not store entries. The addon first creates a private-to-authorized-users Assinafy document receipt. Its immutable source is `wpforms` with `form-FORM_ID:submission-MIRROR_ID`; a real positive host entry ID instead uses `form-FORM_ID:entry-ENTRY_ID`. These are signature-request receipts, not a replacement WPForms entry database. Repeated callbacks within one PHP request reuse the receipt. Separate accepted Lite HTTP submissions, including a visitor resubmitting the same values, are separate requests. A positive stored entry ID is reserved atomically and reused across callbacks.

The form's Assinafy settings show the newest twenty receipts, their core record links and failures. **Retry** requires the original form's edit permission, core record edit permission, `assinafy_send`, and a valid nonce. It uses the original PDF attachment, message and core signer projection. If upload succeeded but assignment failed, core reads back the remote document and resumes without a second upload. An uncertain upload outcome remains blocked; check the Assinafy account before initiating a fresh submission. A valid original PDF attachment remains required for retries. Trashed receipts and missing originating forms cannot be retried through the administrative link.

Status and signing links are available in the protected core record. Lite receipts do not display fabricated entry links. A real Pro entry link requires Pro and its entry-view permission. There is no public signature link in the form confirmation.

## Storage and privacy

The addon stores only form ID, optional actual entry ID, attachment ID and invitation message as retry metadata on the core mirror. It retains this configuration after success. Mapped signer name/email are stored once in the core privacy-managed signer projection; the full form submission is not copied. WordPress personal-data erasure redacts the core contact and disables retry, including pending records. Later API synchronization cannot restore the erased identity. An already-started external operation cannot be cancelled by local erasure.

Deactivation does not delete receipts, documents or signature evidence. Removing this addon leaves its metadata on core mirrors; deleting a mirror removes its post metadata. Core's documented uninstall/data-retention behavior still applies. Site administrators remain responsible for the retention of messages and source identifiers, which may themselves contain personal information.

## External service

Enabling a workflow sends the selected PDF, mapped signer name/email and invitation message to the Assinafy account and environment configured in core. API responses, remote identifiers and signature status are mirrored locally. An Assinafy account is required and its service terms/pricing apply. WordPress redaction does not delete remote Assinafy records.

Service: [Assinafy](https://assinafy.com.br/). Review its [Terms of Use](https://assinafy.com.br/termos-de-uso) and [Privacy Policy](https://assinafy.com.br/politica-de-privacidade).

## Development validation

From the repository root, use the shared integration suite with the official Lite plugin installed in a normal sibling plugin directory. WPForms' own requirements check relies on this directory name; a copy nested under `assinafy/build/hosts` will not initialize as Lite.

```sh
docker exec \
  -e WP_TESTS_DIR=/wordpress-phpunit \
  -e ASSINAFY_WPFORMS_FILE=/var/www/html/wp-content/plugins/wpforms-lite/wpforms.php \
  -w /var/www/html/wp-content/plugins/assinafy \
  wp-env-wordpress-plugin-6587357c-tests-cli-1 \
  vendor/bin/phpunit -c phpunit-integration.xml.dist --filter WPFormsAdapterTest
```

Use your own wp-env container name. Tests intercept Assinafy HTTP and WordPress email delivery; they do not send live invitations. Coverage includes real Lite validation/spam acceptance, notification failure, native form save, receipt deduplication, partial-failure retry, privacy erasure and authorization. The local `tests/stubs/wpforms.php` is only for static analysis and must never substitute for the real Lite host in integration tests.

Licensed Pro workflows, payments, third-party addons and browser interactions remain outside this version's verified scope.

License: [GPL-2.0-or-later](LICENSE).
