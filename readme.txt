=== Assinafy ===
Contributors: assinafy
Tags: electronic-signature, signature, pdf, contracts, woocommerce
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.1.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send PDFs for electronic signature with Assinafy, follow every signer from wp-admin, and download the certified file when it is signed.

== Description ==

Assinafy connects your WordPress site to the Assinafy electronic signature service. Choose a PDF you have already uploaded, name the people who must sign it, and send it for signature without leaving the admin. Each request stays on record inside WordPress, and its status is kept current until the document is signed and certified.

An Assinafy account is required. Connect your production workspace with OAuth from the settings screen. Existing API keys continue to work, and the sandbox still uses an API key.

= What it does =

* Send any PDF in the media library for signature, with a message and a deadline.
* Invite as many signers as you need, each with a name, an email address and an optional WhatsApp number.
* Control the order: signers sharing a number are invited together, a higher number waits for every lower number to sign.
* Track each request as its own record under **Assinafy &rarr; Documents**, with a status badge and a per-signer progress line.
* Copy the signing link for any signer who has already been invited.
* Resend an invitation, move the deadline, rename the document before it is sent, or cancel the request.
* Download the original PDF, the signed PDF, the signature certificate page, the PAdES file and the complete bundle. Downloads are proxied through WordPress behind a capability check, so no Assinafy URL is ever exposed to a browser.
* Keep status current automatically: Assinafy can notify this site over a webhook, and an hourly background job reconciles anything a webhook missed.
* Three roles' worth of access control: separate capabilities to send, to manage and to view signature requests.
* Optional WooCommerce integration: attach a PDF to a product and the customer is asked to sign it when their order is completed. The order gets a note either way.
* Optional Elementor Pro Forms action: send an existing local PDF to a mapped name/email signer. Development adapter: documented API contract tests pass; licensed Pro runtime validation is pending.
* WP-CLI: `wp assinafy status`, `send`, `sync` and `webhook`.
* Privacy tools: the WordPress personal data exporter reports a person's signature requests, and the eraser redacts their name, address and signing link while retaining the signature evidence.

= Form integrations =

The source repository also contains separate development add-ons for Gravity Forms, Contact Form 7 and WPForms. They are not bundled in this core ZIP. Each provides an existing-PDF workflow; none generates contracts or requires payment. CF7 and WPForms Lite have real-host tests. Gravity Forms, Elementor Pro and WPForms Pro still require licensed-host validation. See README.en.md and docs/integrations.en.md in that repository for exact scope and setup.

= Sending from your own code =

Any form plugin, theme or custom integration can send a document by firing one action:

`do_action( 'assinafy_send_document', array(
    'attachment_id' => 123,
    'signers'       => array( array( 'name' => 'Jane Doe', 'email' => 'jane@example.com' ) ),
) );`

Use `assinafy_send_document_async` for the same thing on a scheduled event. Status changes fire `assinafy_document_status_changed`, plus `assinafy_document_certificated`, `_rejected`, `_expired` and `_failed`.

= Verification methods =

Assinafy offers three, and a signer uses one of them:

* **Email** (the default) &mdash; the signer receives a one-time code by email. No credits.
* **WhatsApp** &mdash; the code arrives over WhatsApp. Requires a phone number and a paid Assinafy plan.
* **Digital certificate** &mdash; the signer signs with their own ICP-Brasil certificate. Requires the Digital Certificate feature on the Assinafy account, and the signer must be alone in their signing step.

The send screen uses email verification, and falls back to WhatsApp for a signer entered with a phone number and no address. The other methods are available to code that calls the `assinafy_send_document` action or WP-CLI.

Signing happens on Assinafy's own hosted pages, not inside WordPress. This plugin composes the request, stores the document and assignment identifiers, and fetches status and files back.

== Installation ==

1. In wp-admin go to **Plugins &rarr; Add New**, search for Assinafy, then install and activate. To install manually, upload the plugin folder to `wp-content/plugins/` and activate it from the Plugins screen.
2. Create a free account at assinafy.com.br and use an HTTPS WordPress admin page.
3. In wp-admin open **Assinafy &rarr; Settings**, choose Production, and press **Connect Assinafy**. Approve access in the new tab, then copy the displayed code into the original WordPress settings tab within 60 seconds. The plugin selects the workspace you approved.
4. For Sandbox, choose Sandbox and enter its account id and API key. Existing production API-key connections continue to work until you connect with OAuth.
5. Press **Register this site with Assinafy** to receive status updates over a webhook. This is optional: status is reconciled hourly regardless.
6. Set a default deadline and an optional default message, and choose which capability is allowed to send.

Activation grants administrators all three Assinafy capabilities, editors send and view, and authors view; registers the Documents post type; generates the webhook token; and schedules the hourly reconcile job. Nothing is deleted on deactivation.

For a legacy or sandbox API-key connection, you may define credentials in `wp-config.php`:

`define( 'ASSINAFY_ACCOUNT_ID', '...' );
define( 'ASSINAFY_API_KEY', '...' );`

Otherwise the API key is stored encrypted, and the settings field never renders it back to the browser. OAuth access and refresh tokens are also encrypted in WordPress; you can disconnect from the settings screen.
New stored credentials require unique WordPress security keys and salts in `wp-config.php`, or a random `ASSINAFY_ENCRYPTION_KEY` constant. WordPress-generated database salts alone cannot protect credentials in a database backup. Existing credentials remain readable; if you add a new encryption key, reconnect Assinafy or re-enter the API key.
If the site sends HTTP requests through a proxy (`WP_PROXY_HOST` in `wp-config.php`), Assinafy requests need the PHP cURL extension: without it, WordPress cannot carry HTTPS through the proxy securely, so the plugin refuses the request. Enable cURL, or add `api.assinafy.com.br` (and `sandbox.assinafy.com.br` for Sandbox) to `WP_PROXY_BYPASS_HOSTS`.

== Frequently Asked Questions ==

= Do I need an Assinafy account? =

Yes. Connect your production workspace with OAuth, or use an API key for Sandbox or an existing legacy connection. Assinafy has a free plan.

= Can I try it without spending a document? =

Yes. Set the environment to Sandbox. Sandbox documents have no legal effect and are billed separately from your plan.

= Where do people actually sign? =

On Assinafy's hosted signing pages. The plugin sends the invitation and shows you the link; it cannot sign on anyone's behalf and cannot read past the signer's one-time code.

= Do I have to set up webhooks? =

No. An hourly job re-checks every open document. The webhook only makes the update immediate.

= Is the webhook endpoint secure? =

The endpoint carries a 32-character secret token generated for this site, compared in constant time. A delivery never writes anything from its body: the plugin re-fetches the document from the API and writes that. Assinafy does not sign its deliveries, so the plugin does not claim to verify a signature.

= Does it need WooCommerce? =

No. The WooCommerce tab only appears when WooCommerce is active.

= How do I send more than one document per order? =

Each product carries its own PDF. The plugin records what it already sent per order and per file, so re-completing an order does not send the same document twice.

= Where is the signed PDF stored? =

In your Assinafy account. WordPress stores the identifiers and streams the file on demand through its own download handler, which refuses any file not listed on the document record.

= What does it cost to send? =

One document from your Assinafy plan allowance. Email invitations cost no credits; WhatsApp costs 0.45 credits per signer and a digital-certificate signature costs 2 credits. If the balance is short, the request is refused before the file is uploaded.

== External services ==

This plugin connects to the Assinafy electronic signature service, which is where documents are uploaded, signed, certified and stored. It is required for the plugin to do anything.

API requests use one of two hosts, chosen by the Environment setting:

* `api.assinafy.com.br` &mdash; Production. Real, legally effective signatures.
* `sandbox.assinafy.com.br` &mdash; Sandbox. Test signatures with no legal effect.

Production OAuth connection opens `auth.assinafy.com.br` for consent. A new tab first visits `integrations.assinafy.com.br/wordpress/oauth-start` with the public client ID, requested permissions, random state and PKCE challenge. Assinafy sends a short-lived authorization code to `integrations.assinafy.com.br/wordpress/oauth-callback`. That page validates the browser session, state and issuer, then shows the code for the admin to copy into the original WordPress settings tab. It does not forward the code to a site or receive the PKCE verifier, access token or refresh token. The plugin exchanges the code directly with `api.assinafy.com.br` using the verifier stored on this WordPress site. WordPress stores both tokens encrypted and renews them as needed. Each renewal is valid for another 30 days, so you only reconnect after 30 days without one, when Assinafy rejects the connection, or when a renewal was interrupted after leaving the site: a refresh token Assinafy may have retired is never sent again.

Production API requests use `Authorization: Bearer` after OAuth connection. Existing API-key connections and Sandbox requests use `X-Api-Key`. The signer-side token route needs neither credential.
Disconnect, and deleting the plugin with "Delete data on uninstall" enabled, revoke the connection at `api.assinafy.com.br` and remove this site's OAuth tokens even if remote revocation fails. In that case, revoke the app in Assinafy Connected Apps. Both wait for a renewal in progress, so the latest token is the one revoked; Disconnect asks you to try again shortly.

What is sent, and when:

* **Testing the connection, or registering the webhook** (when you press either button): your account id. Registering also sends this site's webhook URL and the notification address you type into the field.
* **Pricing a request** (immediately before a send): the number of signers and the verification method chosen for each. No file and no personal data.
* **Sending a document for signature** (when you press Send, when a WooCommerce order is completed, when an enabled form adapter processes an eligible submission, or when the `assinafy_send_document` action fires): the PDF file, the document name, and for every signer their full name, email address and/or WhatsApp number, signing order, verification method and notification method, plus your optional message and the deadline.
* **Checking status** (hourly, on a webhook delivery, when you open a document, and on `wp assinafy sync`): the Assinafy document id.
* **Resending, moving a deadline, cancelling or renaming** (when you press the button): the Assinafy document or assignment id, and the new deadline or name.
* **Downloading a file** (when you press a download link): the Assinafy document id and the name of the file requested.

No site visitor data, no post content and no WordPress user account data leaves the site beyond the connection details above and what you enter or explicitly map into a signature request.

Assinafy delivers status notifications back to this site over the webhook endpoint, if you register one. Those deliveries are used only as a signal to re-check a document.

Service terms: [Terms of Use](https://www.assinafy.com.br/termos-de-uso) and [Privacy Policy](https://www.assinafy.com.br/politica-de-privacidade).

== Changelog ==

= 1.1.2 =
* OAuth connections stay active while they are used: every refresh returns a refresh token valid for another 30 days, so a connection ends only after 30 days without a refresh.
* A refresh token that may already have been used is never sent again; the plugin asks you to reconnect instead. An API 401 refreshes once and resends the request.
* Disconnect and uninstall revoke the latest token, and uninstall revokes the connection before deleting it.
* Without cURL, requests to Assinafy use TLS 1.2 and are refused through an HTTP proxy that cannot tunnel HTTPS.
* Assinafy PHP SDK 2.4.2.

= 1.1.1 =
* The plugin's own HTTPS requests to Assinafy now require TLS 1.2 or newer; TLS 1.0 and 1.1 connections are refused. Other HTTP requests on the site are unchanged.

= 1.1.0 =
* Adds OAuth connection for production WordPress sites, with encrypted rotating tokens, a dedicated callback and disconnect control.
* Existing API-key connections continue to work; Sandbox still uses an API key.

= 1.0.1 =
* Translations are delivered as wordpress.org language packs.
* Fixes across sending, webhooks, multisite, privacy and WooCommerce. CHANGELOG.md has the full list.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.1.2 =
OAuth connections now renew with use instead of expiring 30 days after approval. Sites using a WordPress HTTP proxy without the PHP cURL extension must enable cURL or bypass the proxy for Assinafy.

= 1.1.1 =
Requests to Assinafy now require TLS 1.2 or newer.

= 1.1.0 =
Connect your production workspace through Assinafy OAuth. Existing API-key connections remain usable.

= 1.0.0 =
First release. Requires PHP 8.2, WordPress 6.8, and an Assinafy account with an API key.
