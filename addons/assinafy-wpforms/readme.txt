=== Assinafy for WPForms ===
Contributors: assinafy
Tags: signature, pdf, wpforms
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: assinafy
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Request an Assinafy signature on an existing PDF after an accepted WPForms submission.

== Description ==

Adds an Assinafy section to the native WPForms builder settings. Select a Media Library PDF, map a Name or Single Line Text field and an Email field, and optionally enter a plain-text invitation. Core Assinafy manages status, signing links, downloads and WordPress privacy tools.

Requires Assinafy 1.0.0 and WPForms Lite or Pro 1.9.8.1 or newer. Tested with real WPForms Lite 2.0.1.1; Pro entry callbacks are contract-tested only. No licensed Pro installation or paid feature testing is claimed.

The first attempt runs synchronously after successful, non-spam processing. WPForms notification email failure does not cancel an accepted signature request. This workflow does not wait for payment and does not generate PDFs.

Lite does not store entries. Requests are kept as protected core Assinafy document receipts, not full form submissions. Repeated callbacks within one PHP request reuse a receipt; separate accepted Lite HTTP submissions create separate requests. Real positive entry IDs are reserved atomically and reused. Failures appear in the form's Assinafy settings with a protected Retry link. Retry retains the original source and avoids uploading again after a confirmed successful upload. Unknown upload outcomes require investigation in the Assinafy account.

Signer contact data is stored in the core privacy-managed projection. Redaction disables retry and persists through later synchronization. Retry settings (form ID, optional entry ID, attachment ID and message) remain on the mirror after success. Deactivation or removing this addon does not delete signature evidence or existing mirror metadata.

= External service =

This addon uses the Assinafy account and environment configured in the required core plugin. On accepted submissions, it sends the selected PDF, mapped signer name/email and invitation message to Assinafy to create an electronic signature request. Service: https://www.assinafy.com.br/ — Terms: https://www.assinafy.com.br/termos-de-uso — Privacy: https://www.assinafy.com.br/politica-de-privacidade. Review these before enabling the workflow. WordPress redaction does not delete remote records.

== Installation ==

1. Install and configure Assinafy and WPForms Lite or Pro.
2. Install and activate this addon.
3. Upload a PDF to the Media Library.
4. Edit a form and open Settings, Assinafy.
5. Enable requests, choose the PDF, map signer fields and save.
6. Explain the signature request and obtain any required consent in the form.

== Frequently Asked Questions ==

= Does Lite need an entry-storage addon? =

No. The addon keeps signature-request receipts in core Assinafy. It does not invent WPForms entries.

= Why can a signature request be sent when notification email fails? =

WPForms' accepted-submission hook runs after email processing without requiring successful email delivery. This adapter follows that acceptance contract.

= Can I retry after privacy erasure? =

No. The stored signer contact is redacted, and the addon will not reconstruct it from old submissions.

= Which advanced workflows are supported? =

Version 0.1.0 sends one existing PDF to one mapped signer. Pro-only features, payments, conditional workflows, PDF generation and third-party addons are not verified.

== Changelog ==

= 0.1.0 =
* Separate native WPForms settings and accepted-submission adapter.
* Core receipts, protected recovery and privacy-aware signer storage.
