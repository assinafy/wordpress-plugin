=== Assinafy for Contact Form 7 ===
Contributors: assinafy
Tags: signature, pdf, contact-form-7
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: assinafy, contact-form-7
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Request signatures for an existing PDF after a successful Contact Form 7 submission.

== Description ==

Adds an Assinafy panel to the native Contact Form 7 form editor. Choose a Media Library PDF, map existing name/email fields, and optionally require an acceptance field. Core Assinafy manages signing links, status, document downloads and privacy tools.

Only successful, non-demo, non-spam submissions are eligible. The first attempt is synchronous. Failures remain in private Assinafy document receipts and can be retried from the document editor with the original source and request key. A new HTTP submission is a new request; repeated callbacks within one submission do not duplicate it.

Configuration requires the native form-editing permission plus assinafy_manage and assinafy_send. Manual retries require those permissions, permission to edit the core receipt and a valid action nonce. Contact Form 7's native do_not_store setting or unmet storage consent prevents creating a request.

No full Contact Form 7 submission is retained. Only the mapped signer identity is saved in the core privacy-managed record. Retry settings are removed after success, and erased signer identity cannot retry. No documents or signature evidence are deleted merely by deactivating this add-on.

= External service =

This add-on uses the Assinafy account and environment configured in the required core plugin. On an eligible submission it sends the selected PDF, mapped signer name/email and invitation to Assinafy to create an electronic signature request. Service: https://www.assinafy.com.br/ — Terms of Use: https://www.assinafy.com.br/termos-de-uso — Privacy Policy: https://www.assinafy.com.br/politica-de-privacidade. Review these before enabling this workflow. Remote records are not deleted by WordPress personal-data redaction.

== Installation ==

1. Install and configure Assinafy and Contact Form 7.
2. Install and activate this add-on.
3. Upload a PDF to the Media Library.
4. Open Contact, edit a form, and configure the Assinafy panel.
5. Enable signature requests and save the form.

== Frequently Asked Questions ==

= Does this require Flamingo? =

No. Requests use private core Assinafy document receipts. This add-on does not claim that Contact Form 7 stores submission entries.

= Does a failed contact email trigger a signature? =

No. CF7 must report mail_sent. Its explicit skip_mail setting can still report success; demo mode is excluded.

= Can it generate a PDF or control payments? =

No. Version 0.1.0 sends an existing Media Library PDF to one mapped signer.

== Changelog ==

= 0.1.0 =
* Separate Contact Form 7 editor and accepted-submission adapter.
* Durable core receipt, manual retry and native privacy integration.
