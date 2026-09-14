# Integration architecture and rollout

*[Leia em português](integrations.md) · English*

Assinafy owns the signature lifecycle. Adapters connect a host application's events and
records to that lifecycle through a small PHP API and native WordPress actions.

WooCommerce and Elementor are bundled adapters. Separate development add-ons for Gravity
Forms, Contact Form 7 and WPForms live in the source checkout under `addons/`. Real-host
verification covers CF7 and WPForms Lite; Gravity Forms, Elementor Pro and WPForms Pro
still require licensed installation validation. See [Elementor setup](elementor.en.md).
The WordPress.org plugin slug remains `assinafy`; this architecture change does not include
an upload or publication to WordPress.org.

## Ownership

| Core | Adapter |
| --- | --- |
| Credentials, API transport and account configuration | Host availability and supported host versions |
| PDF and signer validation, upload and signature requests | Host events, field mapping and workflow conditions |
| Durable send recovery and duplicate-send protection | A stable source reference and namespaced send key |
| Local document records, downloads and privacy tools | Host record links, host permissions and retention |
| Webhook authentication, authoritative re-fetch and status synchronization | Updating the source record after a verified transition |

Core sending, storage and synchronization must not import host classes, call host functions,
or branch on provider names. The plugin's composition code may recognize an installed host
and register its bundled adapter. Existing WooCommerce behavior stays in place.

An adapter boundary and a separate plugin package are different decisions. An adapter can
live in this repository without placing host-specific behavior in the core domain. Moving
it into another package is justified by its workflow, release cycle or dependency needs.

## Register before `init`

Core emits this action once, after its services and hooks are registered:

```php
do_action( 'assinafy_ready', $send, $records );
```

The arguments are `Assinafy\WP\Documents\SendService` and
`Assinafy\WP\Documents\DocumentRecord`. An addon attaches its listener when its plugin file
loads, before `init`. Registering the listener from a later `init` callback can miss the
action. The addon should use the supplied services instead of rebuilding the SDK client or
reaching into `Plugin` as a service locator.

For example, an addon bootstrap can register its own adapter:

```php
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use ExampleVendor\ContractAddon\Adapter;

add_action(
	'assinafy_ready',
	static function ( SendService $send, DocumentRecord $records ): void {
		( new Adapter( $send, $records ) )->register();
	},
	10,
	2
);
```

`ExampleVendor\ContractAddon\Adapter` is the addon's own implementation, not a class supplied
by Assinafy. This illustrates registration without inventing a Gravity Forms API or a
working form feed. The adapter's `register()` attaches its actual host callbacks and the
Assinafy status callbacks it needs. If core is inactive, the action does not fire; a separate
addon should also declare and explain its plugin dependencies.

## Send contract

`SendService::send( array $args ): int|WP_Error` is the common sending operation. Success
returns the local `assinafy_document` post ID. Failure returns a `WP_Error`; when a send has
already reserved a local record, its error data can include that recovery `post_id`.

The PDF, signers, message, expiry and optional mirror `post_id` retain their existing
[documented contract](../README.en.md#assinafy_send_document--send-now). A mirror `post_id` is not
the host's entry or order ID. Host identity belongs in `source`:

```php
$result = $send->send(
	array(
		'attachment_id'   => 412,
		'signers'         => array(
			array( 'full_name' => 'Jane Doe', 'email' => 'jane@example.com' ),
		),
		'source'          => array(
			'integration' => 'example-forms',
			'record_id'   => 'entry-123',
		),
		'idempotency_key' => 'example-forms:entry-123:workflow-signature:feed-7:agreement-v2',
	)
);
```

The sample IDs illustrate the contract; the adapter must resolve a real, available PDF and
validated signer data from its host.

### Source reference

`source` is optional. When supplied, it has exactly these two keys:

| Key | Contract |
| --- | --- |
| `integration` | A string of 1–64 lowercase ASCII letters, digits, underscores or hyphens. Use a stable provider slug. |
| `record_id` | A string of 1–191 printable ASCII characters without spaces (`0x21`–`0x7e`). Treat it as an opaque host identifier. |

Do not place an entire entry, customer profile, email address or field map in this reference.
Use a host record identifier that the adapter can resolve. The reference supplies routing
information, not authority to access that record.

Core binds a nonempty source to the local reservation before uploading. A retry cannot
retarget that reservation to another source. Omitting source remains valid for older
callers and records. Adapters should consistently resend the same source on retries.

`DocumentRecord::source( int $post_id )` returns the stored pair or `array()` when no source
is recorded. Adapters use this accessor instead of naming a private meta key. The source
belongs to the current WordPress site; an ID on another site is a different record.

### Idempotency and recovery

An explicit `idempotency_key` remains scoped to the configured Assinafy account and
environment. Core does **not** silently add the source to an explicit key. This preserves
existing keys, including WooCommerce recovery records created before source references
were available.

The adapter must namespace its key with the provider, record, workflow or feed, and contract
revision that identify one intended request. Different adapters using `entry-123` alone can
collide within the same account. A random value, timestamp or freshly generated UUID
prevents retries from finding the original request.

The default digest, used when no explicit key is supplied, includes the source when present.
Existing calls without source retain their previous behavior. Adapters should still prefer
an explicit key representing the business operation rather than incidental request state.

A repeated key can return the existing mirror or resume a partially completed send. An
unknown upload outcome requires investigation; creating a new key just to bypass that error
can create another remote document. Source immutability does not replace correct key
namespacing or recovery handling.

### Existing native actions

These extension points remain available to code that does not need injected services:

| Action | Behavior |
| --- | --- |
| `assinafy_send_document( $args )` | Send during the current request. |
| `assinafy_send_document_async( $args )` | Schedule the same arguments through WordPress Cron. |
| `assinafy_send_document_result( $result, $args )` | Observe the result of either hook-based path. |

The result action is specific to the hook-based paths. A direct call to `$send->send()`
returns its result to the caller; it does not also promise that result action. Adapters must
handle the returned result when calling the service directly.

For deferred sends, arguments must survive serialization, and the PDF must still be readable
when cron runs. An upload temporary file or a host object that exists only during submission
is insufficient. WordPress Cron also depends on the site's cron execution being configured.

## Receive verified status changes

The existing status actions are the callback contract:

| Action | Arguments |
| --- | --- |
| `assinafy_document_status_changed` | Local mirror ID, current status, previous status |
| `assinafy_document_certificated` | Local mirror ID, fetched document |
| `assinafy_document_rejected` | Local mirror ID, fetched document |
| `assinafy_document_expired` | Local mirror ID, fetched document |
| `assinafy_document_failed` | Local mirror ID, fetched document |

In a callback, read `$records->source( $post_id )`, check that `integration` is your adapter's
slug, and then resolve `record_id` through the host's own API. An empty source is a legacy or
unattributed record, not a reason to guess an owner. A deleted host record must be handled
without breaking synchronization for other documents.

These actions come from `StatusSync` after an authenticated API fetch has been validated and
the mirror updated. Webhook-triggered and cron-triggered synchronization use the same path.
They describe document-status transitions; a signer can make progress while the document
stays `pending_signature`, without firing a transition action. A future progress consumer
can justify one core post-sync notification; a general event catalog is not required now.

Callbacks execute in the current request. They are not durable deliveries and carry no
exactly-once guarantee. Handlers must be idempotent, especially when they send notifications,
change business records or grant access. An adapter needing reliable retries must persist
that work through its host's supported mechanism and report failures there.

### Keep raw Assinafy webhooks in core

Adapters do not implement `handle_webhook()` for Assinafy deliveries. Those deliveries are
unsigned, so core checks the endpoint token and account, identifies a known local document,
and re-fetches authoritative state before changing it. Forwarding raw payloads to adapters
would duplicate this boundary and permit different trust decisions for the same delivery.

A host's own inbound webhook, if one is needed, is a separate adapter concern with that
host's authentication and validation. It still enters the signature workflow through the
common send operation.

## Keep the adapter contract small

There is no mandatory five-method adapter interface:

| Suggested method | Minimum useful replacement |
| --- | --- |
| `get_source()` | A stable slug plus the concrete source pair on each send. |
| `get_fields()` | Host-specific field mapping when implementing the actual feed UI. No universal field-schema engine. |
| `get_events()` | Register the native host actions the adapter supports. Add discovery metadata only for a real trigger selector. |
| `get_record()` | Resolve the host record inside the adapter when its workflow requires it. Some form submissions have no persisted entry. |
| `handle_webhook()` | Subscribe to verified core status actions. |

An addon-owned `register()` method is a convenient convention, not a required base class or
registry framework. A provider's fields, selectable triggers and settings can evolve in its
adapter without making every other adapter implement unused methods.

## Adapter responsibilities

- Authenticate host requests, check permissions and nonces where applicable, enforce consent,
  and verify that the selected trigger is appropriate. The PHP service is trusted server-side
  code; it does not authorize users or expose a public endpoint on an adapter's behalf.
- Map and validate signer fields, signing order, verification methods, workflow conditions
  and PDF selection using the host's APIs. Core validation remains a final boundary.
- Use durable PDF storage for queued work and decide ownership and cleanup of generated files.
  The send API does not itself provide a document-template or PDF-generation engine.
- Persist the host-to-mirror relationship when the host UI needs it, report send failures,
  and preserve recovery information instead of marking a failed operation complete.
- Handle record deletion, personal-data requests and retention in the host. Core's signer
  redaction does not delete the host's entry, order or remote document. Avoid storing complete
  submissions in document meta or logging sensitive payloads.
- Use source references and idempotent callback handling to keep different providers,
  workflows and WordPress sites isolated.

## Rollout

| Order | Deliverable | Packaging decision |
| --- | --- | --- |
| 1 | Stabilize core readiness, source references and documented extension behavior. | Keep the existing `assinafy` plugin and bundled WooCommerce adapter. |
| 2 | Native Gravity Forms feed implementation; licensed host validation pending. | Separate `assinafy-gravity-forms` addon, 0.1.0. |
| 3 | Contact Form 7 accepted-submission/consent mapping and protected retries. | Separate `assinafy-contact-form-7` addon, 0.1.0. |
| 4 | WPForms Lite mapping, durable receipt and retries; licensed Pro verification pending. | Separate `assinafy-wpforms` addon, 0.1.0. |
| 5 | Elementor Forms action and server-side configuration permissions; Pro validation pending. | Bundled adapter, registered through the native Pro Forms hook. |
| 6 | Consider a separate WooCommerce product for a deeper contract workflow. | Split only when that workflow justifies it; preserve the current bundled behavior meanwhile. |

### Gravity Forms release acceptance criteria

The development adapter implements one existing PDF, one mapped email signer, native feed
conditions/background processing and entry-page recovery. It does not implement payment
gates, PDF generation, variable verification methods or signing-order controls. The criteria
below remain release review items; contract tests do not replace licensed-host validation.

- Define the supported Gravity Forms versions and the real feed/submission lifecycle before
  choosing its hooks. Add no placeholder host API or unsupported compatibility claim.
- Provide a working feed configuration for PDF selection or an explicitly supported PDF
  producer, signer mapping, conditions, verification and signing order.
- Choose the precise send trigger, including whether payment is required. Generate a stable
  key from the entry, feed/workflow and contract revision; retries must reuse the same send.
- Save the source before upload, link the mirror back to the entry, and show actionable
  success, failure and recovery state through the host's native administrative UI.
- Route verified terminal outcomes back to the correct entry. Define what happens when that
  entry is deleted, the addon is disabled, or a callback must be retried.
- Test the supported host version against a real installation, including consent/permissions,
  duplicate triggers, partial-send recovery, deferred PDF availability and privacy handling.

### Decisions before a deeper WooCommerce split

Decide whether the product needs signature-before-payment, signature-before-fulfillment,
custom order states, downloadable signed contracts, contract generation, renewals or
subscriptions, multiple signers, or revision tracking. Also define cancellation/refund
behavior, guest access, customer-account screens and the migration of existing product and
order metadata.

Those requirements would justify a separate workflow and release cycle. Moving today's
order-completion trigger into another ZIP alone does not. Any split must preserve existing
order-to-document mappings, supplied idempotency keys, partial-send recovery, HPOS behavior
and customer email links.

## Regression checks for the core boundary

Check that readiness exposes the shared services once; malformed source is rejected before
remote side effects; a source survives partial failure and cannot be changed on retry; and
source reads remain empty for legacy records. Explicit key behavior must remain compatible,
while default digests distinguish different sources.

Exercise a small addon through registration, send result and verified status routing, with
duplicate notifications, a missing host record and a site switch. Keep existing hook-based
callers, the bundled WooCommerce flow and their recovery behavior passing. A future adapter
then adds tests for its own host contract instead of duplicating the core transport suite.
