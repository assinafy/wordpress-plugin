# Elementor Pro Forms

*[Leia em português](elementor.md) · English*

Assinafy includes an **Assinafy** action for Elementor Pro's **Actions After Submit**.
It sends an existing local PDF to one signer using the name and email fields selected by
the form author. Elementor Pro is required; the free Elementor plugin has no Forms widget.

This adapter has contract tests against real WordPress and small doubles of Elementor's
documented APIs. Licensed Elementor Pro was unavailable during development, so the adapter
has **not** been tested with a real Elementor Pro installation. Validate the editor, save
permissions, submission validation and success/error responses on staging before use.

## Configure a form

1. Configure the Assinafy account and environment in WordPress. Use a staging account when
   verifying a workflow; a successful send creates a remote signature request.
2. Upload the agreement PDF to this site's Media Library. The configuring user must be able
   to read the attachment, and the local file must remain available to WordPress.
3. Edit the Elementor Pro Form widget as a user with the `assinafy_send` capability. Add
   **Assinafy** to **Actions After Submit**, then open its settings section.
4. Select **Agreement PDF**. Enter the field IDs for **Signer name field ID** and
   **Signer email field ID**, matching the IDs configured on the form fields.
5. Set **Agreement workflow and revision**, for example `membership-agreement-v2`. Use a
   distinct identifier for each form/agreement workflow: 1–64 letters, numbers, dots,
   underscores or dashes. Update the revision when changing the intended agreement.
6. Optionally set **Message to signer**. Save and test the form on staging.

The selected PDF is fixed in the saved form configuration. Submitted file URLs, uploads and
filesystem paths are not used. The core additionally checks the PDF and signer data before
sending. Dynamic PDF generation, multiple signers, conditional feeds and configurable
signature expiry are outside this adapter's current controls.

The site owner must configure suitable access, consent, required fields and spam protection
in Elementor. The adapter runs as an action after Elementor's validation; it does not infer
consent from a successful submission. Public visitors do not need `assinafy_send`: they
submit the workflow that an authorized form author saved. Other configured Elementor
actions may also run, and an Assinafy failure does not roll back their side effects.

## Authorization and exports

The adapter hides its editor controls from users without `assinafy_send` and independently
enforces that capability through Elementor's native `elementor/document/save/data` filter.
An unauthorized editor cannot add, remove or modify a configured Assinafy form, including
its mapped fields and dynamic settings. They can edit unrelated widgets while leaving the
configured form unchanged. An enabled action also requires an accessible local PDF when
saved by an authorized user.

This protection applies to Elementor's document-save API. Trusted PHP code that writes
post meta directly remains responsible for its own authorization, as with the core PHP
send API. The adapter does not grant document access or send capabilities to new roles.

Elementor exports omit this site's Assinafy PDF, field mappings, workflow identity and
message. Reconfigure these values explicitly after importing a form on another site.

## Results, source and recovery

The action sends synchronously through the shared `SendService`. On success, Elementor
continues with its configured success response. On failure, the action adds a generic
native field error. Public responses contain no API diagnostic, signing URL or local
recovery ID. The Assinafy log records the error code and any recovery document ID returned
by the core.

Each accepted `Form_Record` receives an opaque reference:

```php
array(
    'integration' => 'elementor',
    'record_id'   => 'membership-agreement-v2:<32 hexadecimal characters>',
)
```

The send key is `elementor:` followed by that exact reference. The random suffix identifies
the accepted submission without storing personal information in the source. Repeated
callbacks on the same record object reuse its result. A distinct submission, even with
identical field values, is a new signature request. This does not depend on Elementor's
optional Collect Submissions action or treat matching personal data as a retry identifier.

Once core reserves a local document, its source and send key remain on that receipt,
including after a partial send failure. The core handles remote status synchronization and
document privacy through its existing document records. This adapter adds no separate
submission store or automatic status updates to Elementor's stored submissions.

There is currently **no Elementor retry screen or automatic cross-request replay**. A fresh
frontend submission creates a new reference and may create a second remote document.
Before retrying a failure, inspect the retained Assinafy document and error. Developer-led
recovery must use the original source, original key (`elementor:` plus `record_id`) and
original validated send arguments. The adapter does not retain a separate copy of those
arguments, so they must be available from an authorized host record or other existing
workflow record. Do not guess them or create a new key to bypass an unknown upload outcome.

The source reference is not an Elementor database submission ID. Consumers of the core's
verified status hooks can recognize `integration === 'elementor'`, but resolving an
Elementor submission requires a deliberate, durable host-side association. Callbacks are
not durable or exactly-once deliveries. See the [core integration contract](integrations.en.md)
for source immutability, idempotency and status-hook guarantees.

## Verification and native contracts

The `elementor-contract` PHPUnit group covers mapping and local-file restrictions, guest
submission behavior, duplicate callbacks versus distinct submissions, native error
signaling, recovery IDs, exports, early registrar timing and server-side save permissions.
Recording send closures and an unconfigured core service prevent real signature requests.
These checks do not replace licensed-host compatibility tests.

Implementation follows Elementor's official [custom action registration](https://developers.elementor.com/docs/form-actions/add-new-action/),
[action structure](https://developers.elementor.com/docs/form-actions/action-structure/),
[advanced example](https://developers.elementor.com/docs/form-actions/advanced-example/),
[form hooks](https://developers.elementor.com/docs/hooks/forms/), and
[Media control](https://developers.elementor.com/docs/editor-controls/control-media/).
The native save filter is defined by Elementor's
[document implementation](https://github.com/elementor/elementor/blob/main/core/base/document.php).
The bridge registers during plugin file loading, registers the action during Elementor's
registrar event, and receives the shared send service through `assinafy_ready` before use.
