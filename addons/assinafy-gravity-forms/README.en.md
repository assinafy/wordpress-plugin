# Assinafy for Gravity Forms

*[Leia em português](README.md) · English*

Version 0.1.0 is a development adapter. It requires Assinafy Core 1.0.0+, WordPress 6.8+, PHP 8.2+, and licensed Gravity Forms 2.9.4+. Automated contract tests use documented host doubles with real WordPress and Assinafy Core; a licensed Gravity Forms installation has **not** been tested.

Install this directory as `wp-content/plugins/assinafy-gravity-forms`, alongside Assinafy and Gravity Forms. Configure the account in Assinafy Core, then add a feed under the form's Settings → Assinafy. Enter an existing Media Library PDF attachment ID, map Full name and Email, and optionally set a message and native feed condition. Editing the feed requires `assinafy_send`; PDF validation also requires `gravityforms_edit_forms` and permission to edit that attachment.

The [native Feed Add-On Framework](https://docs.gravityforms.com/gffeedaddon/) owns conditional processing and the background queue. This adapter sends one existing PDF to one signer using core's default email verification. It does not generate PDFs, populate templates, require payment, gate confirmations on signatures, or support payment fulfillment. Add explicit consent and any appropriate feed condition to your form.

One entry/feed pair produces one signature request. A keyed fingerprint of the original mapping, values and PDF bytes, plus the Assinafy document ID, is kept in Gravity Forms entry metadata; the complete submission is not copied. Changed mappings, entry values or a replaced PDF cannot retarget an unfinished request. Restore the original inputs before retrying. A returned failure before core reserves a document releases the fingerprint, allowing invalid inputs to be corrected; an interrupted or partially uploaded attempt keeps it. A new feed is a distinct workflow and can create another document for the same entry. Core records the source as `gravity-forms` / entry ID.

The entry details page links to the core document and offers a nonce-protected retry for failed requests. Retries require `assinafy_send` and `gravityforms_view_entries`. Core status, webhook synchronization, signing links, downloads and privacy handling remain shared. An upload with an unknown outcome requires checking the Assinafy account before any new send. Entry data remains subject to Gravity Forms' own privacy and retention controls; core erasure covers the signature mirror.

This add-on uses the Assinafy service through Assinafy Core. On eligible feed processing it sends the selected PDF, mapped signer name/email, configured message, and signing settings to the configured Assinafy account. An Assinafy account and document allowance are required. See the [service](https://www.assinafy.com.br), [terms](https://www.assinafy.com.br/termos-de-uso) and [privacy policy](https://www.assinafy.com.br/politica-de-privacidade). No separate credentials or transport are bundled.

Test command from the repository root (requires the WordPress test library):

```sh
ASSINAFY_GRAVITY_FORMS_CONTRACT=1 composer test:integration -- --filter GravityFormsContractTest
```

Before a release, validate real feed settings/save/conditional processing, background jobs, entry UI/retry, name-field formatting, permissions and privacy in a licensed installation. Contract tests cannot establish host compatibility.
