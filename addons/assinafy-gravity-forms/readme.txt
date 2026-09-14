=== Assinafy for Gravity Forms ===
Contributors: assinafy
Tags: gravity forms, electronic signature, assinafy
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send an existing PDF for signature from a native Gravity Forms feed. Requires Assinafy Core and licensed Gravity Forms.

== Description ==
Map a name and email field to a signature request. Uses Gravity Forms feeds, conditions and background processing, plus Assinafy Core's shared send/recovery, status and privacy features.

Development version: documented API contract tests exist; licensed Gravity Forms runtime validation is pending. Requires Gravity Forms 2.9.4+, Assinafy Core 1.0.0+, PHP 8.2+.

= External service =
Eligible feeds send your selected PDF, mapped signer name/email, message and signing settings to your Assinafy account through Assinafy Core. An account and document allowance are required. Service: https://www.assinafy.com.br — Terms: https://www.assinafy.com.br/termos-de-uso — Privacy: https://www.assinafy.com.br/politica-de-privacidade

== Installation ==
1. Install and configure Assinafy Core and licensed Gravity Forms.
2. Install and activate this add-on.
3. In a form's Settings, add an Assinafy feed with a PDF attachment ID and signer mapping.
4. Add consent and any feed conditions appropriate to your workflow.

== Changelog ==
= 0.1.0 =
* Initial native feed adapter with stable request identity, core document links and protected retry.
