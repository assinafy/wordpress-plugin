# Assinafy for Contact Form 7

*[Leia em português](README.md) · English*

Version 0.1.0. A separate WordPress add-on for Assinafy and Contact Form 7. It sends one existing Media Library PDF to the mapped signer after Contact Form 7 reports a successful submission.

Requires WordPress 6.8+, PHP 8.2+, the Assinafy core plugin and Contact Form 7. The real-host tests use Contact Form 7 6.1.7. There are no bundled dependencies or JavaScript build steps.

## Setup

1. Install and configure Assinafy, install Contact Form 7, then activate this add-on.
2. Upload the agreement as a PDF to the Media Library.
3. Open **Contact → Contact Forms**, edit a form, and select the **Assinafy** panel.
4. Select the PDF, a text/textarea name field and an email field. Field names are selected from the form's existing tags.
5. Optionally select an acceptance field. If selected, it must be checked; inverted acceptance fields are excluded. Explain the signature request in the form.
6. Set an optional invitation message, enable signature requests, and save the contact form.

Configuration requires the host's form-editing permission, `assinafy_manage` and `assinafy_send`. Saving reuses the Contact Form 7 editor nonce. REST or programmatic form saves that omit this panel preserve its settings. The PDF selector lists the newest 200 PDFs and retains the current selection if older.

## Submission behavior

The PHP `wpcf7_submit` callback requires both the result and current `WPCF7_Submission` to have status `mail_sent`. Invalid, spam, missing-acceptance, aborted and failed-mail outcomes do not send. Demo mode does not send. The native `do_not_store` consent flag also prevents creating a signature request.

Contact Form 7's intentional `skip_mail` mode reports success and can request signatures; this differs from demo mode. The first signature attempt runs synchronously after CF7's accepted outcome. API failures do not change that host outcome or its visitor-facing message.

Contact Form 7 has no built-in persistent submission entry. The add-on reserves a private Assinafy document receipt before calling the API. Its source is:

```php
array(
    'integration' => 'contact-form-7',
    'record_id' => '<form-id>:<local-receipt-id>',
)
```

The stable key contains the form ID, receipt ID and attachment ID. Repeated callbacks for the same submission object use one receipt. A new HTTP submission creates a new receipt, including an intentional resubmission of identical values. There is no cross-request browser replay token or time-window deduplication.

## Status and retry

Open **Assinafy → Documents** to inspect a request. Core owns the signing links, status, downloads, errors, webhook updates and reconciliation.

A failed attempt with usable retained signer details exposes **Retry original request** in the document sidebar to users with `assinafy_manage`, `assinafy_send` and permission to edit both the document receipt and originating contact form. This nonce-protected POST uses the original PDF selection, invitation, source and key; it does not resubmit the contact form or accept replacement signer data from the browser. Keep the selected PDF and contact form available until the request completes. Core recovery reuses a known upload and refuses to bypass an unknown upload outcome.

Successful sends remove the add-on's retry configuration. Trashed receipts, deleted originating contact forms, and erased or missing signer details cannot retry. There is no automatic retry queue, PDF generation, payment gating, additional email template, or dependency on Flamingo.

## Data and external service

On an eligible submission, the PDF, mapped signer name/email and invitation are sent to the Assinafy service configured in the core plugin. See [Assinafy](https://assinafy.com.br/), its [Terms of Use](https://assinafy.com.br/termos-de-uso) and [Privacy Policy](https://assinafy.com.br/politica-de-privacidade).

Only the mapped signer identity is stored in the core document's signer projection. The add-on separately retains the PDF selection, invitation, source and idempotency key while retry is needed. It does not copy complete submissions, IP addresses, browser metadata or unrelated fields. Core WordPress privacy tools export and redact the signer data, including pending signers without a remote ID. Retrying erased identity is refused. Remote documents and signature evidence are managed separately by Assinafy.

Deactivation retains receipts and settings. Removing this add-on does not delete signature evidence; core's documented retention/uninstall policy applies to its document records. Form configuration remains attached to its Contact Form 7 form until that form is deleted.

## Verification

The integration tests use the actual freely available host plugin and intercept WordPress HTTP and mail; no live invitations are sent. From the core repository's wp-env checkout, after placing the official host under `build/hosts/contact-form-7`:

```bash
docker exec \
  -e ASSINAFY_CF7_FILE=/var/www/html/wp-content/plugins/assinafy/build/hosts/contact-form-7/wp-contact-form-7.php \
  -w /var/www/html/wp-content/plugins/assinafy \
  <tests-cli-container> vendor/bin/phpunit \
  -c phpunit-integration.xml.dist --filter ContactForm7Test
```

Run database-backed suites serially against the shared test database. Tests and development files are excluded from the add-on ZIP.

Primary references: [official Contact Form 7 6.1.7 source archive](https://downloads.wordpress.org/plugin/contact-form-7.6.1.7.zip), [Contact Form 7 FAQ](https://contactform7.com/faq/), [acceptance checkboxes](https://contactform7.com/acceptance-checkbox/).

Licensed GPL-2.0-or-later; see [LICENSE](LICENSE).
