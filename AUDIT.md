# WordPress and WooCommerce integration audit

Updated: 2026-09-14. Scope: the plugin source, admin JavaScript, SDK integration, tests,
distribution build and GitHub workflows. Changes are local and uncommitted; no release was
published and no live signature invitation was sent.

The audit found and fixed substantial integration defects that the original PHP suites did
not exercise. This is verified coverage of the supported workflows, not a claim of universal
compatibility or 100% line/branch coverage.

## Findings addressed

| Area | Defect and resulting behavior | Regression evidence |
|---|---|---|
| Compose screen | JavaScript selectors/localization did not match PHP markup; the PDF chooser had no handler. The screen now uses WordPress's media modal, working signer rows and native validity checks. | Chromium selects a PDF, adds/removes rows, rejects duplicate emails and captures the final submitted fields. |
| Signer validation | The form repaired invalid emails and silently dropped incomplete participants. The entire request now fails before contacting Assinafy when any participant is invalid. Shared validation also checks contacts, notification/verification pairing, duplicate identity, signing steps and deadline. | PHP negative cases plus browser duplicate validation. |
| Document actions | Nested forms inside WordPress's post editor corrupted form ownership and nonces. Controls now belong to separate footer forms through native HTML `form` attributes. | DOM integration cases and Chromium verifies actual submitted action/post/nonce. All four handlers have nonce, role and post-type denial tests. |
| Settings | `options.php` cleared the webhook token because its registered field was absent from the form. Internal settings use a separate group. AJAX results now announce status updates. | Native allowed-options save loop preserves the token and blank API key while updating normal fields and unchecked toggles. |
| Multisite credentials | A cached API client could survive `switch_to_blog()`. The factory now resets when the site changes or is restored. | Separate account, credential and environment assertions across site switches. |
| Credential encryption | Raw authentication constants could be absent or retain predictable sample values. Encryption now uses WordPress's resolved `wp_salt('logged_in')`. | Native WordPress salt/filter regression and encryption unit checks. |
| Send concurrency | A transient check-then-set was not atomic. A native WordPress database lock now protects the send. Keys include account/environment and either the supplied event key or PDF contents and the complete normalized request. | Held-lock refusal, content/contact/account changes, concurrent callback and duplicate-submit cases. |
| Partial sends | A signer/assignment failure after upload could leave an untracked PDF and a later retry could upload again. Signers resolve first; a local reservation precedes upload; the returned remote ID is saved before assignment. Retries re-fetch and resume the same document. | Failed assignment recovery, lost assignment response, missing upload response, durable deduplication after transient expiry, local persistence failure. |
| Failed admin sends | A partially completed send was described as unsent and redirected to a fresh form. It now redirects to the reserved document and displays the stored failure. A fresh compose form has its own request ID for an intentional new send. | Admin upload-failure redirect regression. |
| Status synchronization | Malformed successful responses or mismatched document/account identities could damage the mirror. They are now rejected; admin refreshes share the validated sync path. | Existing state remains intact under malformed/mismatched responses. |
| WooCommerce orders | Completion processing ran after transactional email rendering. It now runs first using the same `WC_Order` instance. Plain-text email output is plain text, and equal document titles no longer overwrite one another's links. | Real completed-order email rendering, legacy storage and HPOS with synchronization disabled, guest order, repeated completion, variation inheritance and duplicate PDF cases. |
| Product settings | An older selected PDF disappeared outside the latest 200 attachments and could be cleared on save. The current PDF remains selectable, absent panels preserve values, and malformed submitted fields are sanitized. | Native product CRUD/save and picker regressions. |
| Lifecycle | Network activation did not provision other/new sites; network cleanup missed data. Existing and future sites now receive capabilities, independent webhook tokens and reconciliation. Deactivation cancels pending send events; individually active sites retain cron on network deactivation. | Single-site and multisite activation/deactivation/context-restoration cases. |
| Uninstall | Trash was excluded, only the current site was visited, and vetoed deletions could stall cleanup. Each site's retention preference is respected; cleanup includes trash, preserves vetoed records without looping, and invalidates option caches through native APIs. | Single/multisite cleanup, retained/vetoed records, locks and immediate cache-state checks; isolated packaging checks. |
| Privacy | Trash was omitted; unsalted email hashes were guessable; synchronization restored erased identity; removal flags were misleading. Erasure now uses keyed tokens, persists redacted signer IDs, reports removal/retention correctly and supplies privacy-policy guidance. | Export/erasure, trash, follow-up synchronization, log-only removal and policy registration tests. |
| Distribution | Clean `--no-dev` installs lacked Strauss, package versions were not checked three ways, and local tooling could enter the zip. Builds install their tooling, ship only the scoped runtime tree, validate all version markers and exclude local artifacts. | Reproduced original clean-build failure, corrected isolated production smoke, shell packaging regression, official Plugin Check. |
| CI/release | WooCommerce could silently skip; WP 6.8 was paired with incompatible latest WooCommerce; sandbox workflow could pass with no tests; releases did not wait for gates. Required WooCommerce loading, supported version pairs, separate multisite/AJAX/browser checks, empty-sandbox failure and release dependencies now make these gaps visible. | Workflow YAML validation, local equivalent checks and package inspection. Hosted GitHub execution remains pending. |

## Core adapter boundary and documentation follow-up

- Core now emits `assinafy_ready(SendService, DocumentRecord)` after its own hooks are ready.
  Bundled WooCommerce detection, registration and early compatibility declarations belong to
  `integrations/bootstrap.php` and the adapter. Real runtime checks passed with WooCommerce
  loaded and absent.
- Optional `source = {integration, record_id}` references are validated before HTTP and stored
  before upload. They survive synchronization and cannot be reassigned through a retry.
  Supplied keys retain their existing hashes so older WooCommerce partial sends can resume;
  adapters must namespace their keys. Default digests distinguish sources. Source conflicts
  preserve existing reservations, and failed source persistence cannot create an ambiguous
  upload reservation. Regression cases cover cached/durable keys, legacy adoption, source
  routing through status hooks, deferred arguments and persistence failures.
- Webhook account checks now use the same `wp-config.php` constant precedence as the sending
  client. An isolated constant-precedence regression passes.
- `docs/integrations.md` documents core/adapter ownership and the requested rollout. Gravity Forms, CF7 and WPForms now have separate 0.1.0 development packages; Elementor
  is bundled. Existing WooCommerce behavior remains bundled until a deeper contract workflow
  is defined. Licensed-host validation remains pending where noted below.
- The entire README was checked against the current source and workflow configuration.
  Corrections include source/result contracts, signer validation, recovery, optional hosts,
  cron execution of queued sends, webhook acknowledgement and token rotation, credential
  precedence, SDK signatures, deployment gates and privacy limits. All 12 PHP examples pass
  syntax validation, and all 47 current local Markdown links resolve in the checkout.

## Form adapter implementation follow-up

- Gravity Forms: separate native Feed Add-On Framework package, one existing PDF and mapped
  email signer, feed conditions/background processing, durable per-entry/feed identity and
  protected entry-page retry. PDF-content fingerprints prevent changed files from retargeting
  a partial request. Explicit failures before reservation permit correcting invalid input.
  **14 documented-host contract tests / 51 assertions**; no licensed host available.
- Contact Form 7: separate native editor panel, successful accepted-submission callback,
  optional acceptance-field mapping and native storage-consent checks. Invalid, spam, aborted,
  failed-mail and demo submissions do not send. Core receipts preserve original retry data.
  **16 real CF7 6.1.7 tests / 63 assertions** including native permissions and nonces.
- WPForms: separate native builder settings and accepted-submission adapter, durable receipts
  for Lite's zero-entry-ID submissions, protected retries and serialized positive-entry
  receipt creation. **15 real WPForms Lite 2.0.1.1 tests / 61 assertions**. Positive-entry
  scenarios exercise the adapter contract; they do not certify licensed WPForms Pro.
- Elementor: bundled native action, early registration bridge and server-side document-save
  authorization. One existing PDF and mapped email signer; duplicate callbacks on one record
  reuse results, distinct identical submissions remain distinct. **18 contract tests / 54
  assertions** against real WordPress and documented host doubles. No licensed Pro available.
  The adapter has no durable form retry UI; retained core recovery details require original
  arguments from an authorized existing workflow record. See `docs/elementor.md`.
- Shared recovery/privacy: unassigned upload hydration preserves pending signer projections;
  metadata slashing preserves literal signer text. Core erasure remembers a site-keyed email
  digest before a remote ID exists and carries its token forward when that ID arrives, so
  later assignment/sync responses cannot restore an erased pending contact. The full WordPress
  regression covers pending erasure through upload and assignment, as well as metadata escaping.
- Core ZIP excludes all dedicated add-ons. `bin/build-addons.sh` produces three independent
  dependency-free development ZIPs, with tests/host doubles excluded. Each includes its own
  setup, external-service disclosure and license. No package was published.
- The combined integration harness installs native WPForms tables and loads the native admin
  helper definitions because PHPUnit starts with a frontend bootstrap before running admin
  AJAX cases. Actual WPForms must use its normal plugin directory basename. CI mirrors this
  layout and downloads the tested free host versions; hosted CI has not run in this session.

The initial workflows do not generate PDFs, merge fields into contracts, enforce payment,
create signature-gated fulfillment, or support every host extension. Core keeps its existing
WooCommerce contract behavior. Form-side consent, access and retention must be configured for
the intended workflow. No live Assinafy upload/sign/webhook cycle was performed.

WordPress.org submission remains a separate release gate: Plugin Check warns that required
plugin `assinafy` is not in the directory yet. It also flags the `wp` text in the WPForms
add-on's name/slug. These warnings are recorded, not suppressed or treated as directory
approval. All three add-on checks returned no errors. The checker still emits its own early
translation notice described below.

## Verification

| Check | Final result |
|---|---|
| PHPCS + PHPStan level 8 + PHPMD | Passed |
| PHPUnit unit, host PHP 8.4.6 | 139 tests, 354 assertions |
| WP 7.1 / WC 11.1.0 / PHP 8.2.33 | 442 cases, 1,861 assertions; 7 multisite-only skips |
| WP 6.8 / WC 10.2.2 / PHP 8.2.33 | 442 cases, 1,861 assertions; 7 multisite-only skips |
| Multisite, both version pairs | 62 tests, 260 assertions per pair; no skips |
| AJAX, both version pairs | 14 tests, 51 assertions per pair |
| Chromium admin workflows | 2 tests passed |
| Packaging shell regression | Passed |
| pt_BR catalog | 238 translated messages; no untranslated/fuzzy entries; compiled MO and placeholders validated |
| Isolated clean install → prune dev tools → build ZIP → load packaged transport | Passed |
| Official Plugin Check 2.1.0, core static + runtime | No errors found |
| Official Plugin Check 2.1.0, three separate add-ons | No errors; unpublished core dependency warnings, plus WPForms naming/slug warnings |
| Composer advisory scan | No advisories or abandoned packages |
| npm advisory scan | 0 vulnerabilities, including development dependencies |

The seven single-site skips are intentional multisite cases exercised by the separate
multisite runs. Existing coverage also passed for authenticated downloads, token-gated REST
webhooks, authoritative API refetches, public send/cron hooks, WP-CLI, transport errors,
response envelopes and rate-limit handling.

API responses in PHP tests are
intercepted through WordPress's HTTP filter; they exercise the native transport and SDK
contracts without contacting an Assinafy account. Browser tests use the local wp-env admin,
upload a fixture PDF, intercept submission before a send, and delete their created fixtures.

- `composer check`: WordPress PHPCS, PHPStan level 8, PHPMD and PHPUnit unit suite.
- Full integration suite: WordPress 7.1 / WooCommerce 11.1.0 / PHP 8.2.33.
- Full integration suite: WordPress 6.8 / WooCommerce 10.2.2 / PHP 8.2.33.
- Separate AJAX and multisite runs, because core excludes AJAX by default and single-site runs
  deliberately skip multisite-only cases.
- `npm run test:e2e`: Chromium against the actual admin pages.
- `bash tests/build-zip.sh`: mismatched-version rejection and exclusion checks.
- Official Plugin Check 2.1.0: static and runtime checks of the staged distribution.
- Composer and npm dependency advisory scans.

## Remaining limits and next acceptance checks

1. **Live Assinafy delivery is not verified.** No sandbox test classes currently exist. The
   manual workflow now fails instead of passing an empty suite. A dedicated sandbox account,
   explicit test recipients and a real upload/sign/download/webhook round trip are needed to
   verify external delivery and signing. Current API contract fixtures are not proof of current
   service availability, account plan entitlements or successful email/WhatsApp delivery.
2. **An upload with no response ID has an uncertain outcome.** A durable local reservation
   prevents repeating that key blindly. Check the Assinafy account before starting another
   send. The plugin cannot reconstruct an ID that the service never returned. Known uploaded
   documents can resume with the original send arguments/key through the service, hook, CLI
   or WooCommerce completion path; the admin failure screen exposes its recovery record.
3. **Checkout coverage has a defined boundary.** Classic and block checkout reach the shared
   native order-status callback; that callback, real order CRUD and email output are tested.
   This audit did not automate complete browser checkout with real payment gateways, every
   theme, subscription extensions or third-party checkout fields. The plugin sends on completed
   orders, not automatically at payment/processing; other triggers use its public hooks.
4. **Privacy erasure is local redaction.** Remote PDFs, document/signer/assignment IDs,
   signature evidence, adapter source references and WooCommerce order data remain. This is not anonymization or remote
   deletion. Site owners still need retention decisions for those stores.
5. **Hosting combinations remain unbounded.** PHP 8.2 and 8.4 were exercised locally; CI also
   declares PHP 8.3/8.5 and lowest-dependency jobs, but these hosted jobs have not run in this
   audit. Redis/Memcached configurations, persistent-cache eviction under load, very large
   multisite networks, bulk sends exceeding the five-minute lock lease, and all web servers
   are not certified. Network provisioning runs synchronously in bounded-memory pages.
6. **No coverage percentage is claimed.** No coverage driver was installed in the test runtime.
   Passing test counts describe executed cases, not all possible branches. The zip has not
   been published or reviewed by wordpress.org.
7. **SDK namespace isolation is incomplete.** The shipped PSR logger is scoped and Guzzle is
   absent, but Strauss explicitly keeps `Assinafy\SDK` unprefixed. Another plugin loading an
   incompatible Assinafy SDK first could conflict. No collision was reproduced in these test
   environments; this remains a compatibility risk requiring a dedicated coexistence test
   before claiming isolation from every other Assinafy integration.
8. **Plugin Check emits its own translation-timing notice.** Its metadata scanner calls
   `get_plugin_data()` with translation enabled during `plugins_loaded`; the stack was traced
   to Plugin Check itself. Its complete check result is separate from that tool notice.

## Platform references

- Native order CRUD and compatibility declarations follow the
  [WooCommerce HPOS recipe book](https://github.com/woocommerce/woocommerce/blob/trunk/docs/features/orders/high-performance-order-storage/recipe-book.md).
  Checkout compatibility has the scope described in the
  [Cart and Checkout extensibility documentation](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks).
- The product data panel remains the appropriate integration surface: WooCommerce documents
  [retirement of its beta product editor in 11.0](https://developer.woocommerce.com/2026/06/02/product-editor-beta-retiring/).
- Internal settings isolation uses the native
  [Settings API registration groups](https://developer.wordpress.org/reference/functions/register_setting/);
  encryption uses [WordPress's salt resolver](https://developer.wordpress.org/reference/functions/wp_salt/).
- Privacy removal/retention reporting follows the
  [WordPress eraser contract](https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-eraser-to-your-plugin/),
  and new network sites use
  [the site-initialization hook](https://developer.wordpress.org/reference/hooks/wp_initialize_site/).
