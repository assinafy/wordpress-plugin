<?php
/**
 * Webhook delivery handler.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Webhook;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Support\WebhookEventParser;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentIndex;
use Assinafy\WP\Documents\StatusSync;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Turns an authenticated delivery into a re-fetch.
 *
 * Deliveries are unsigned: the token in the URL proves the sender knows this site's secret
 * endpoint, and nothing proves the body was written by Assinafy. So the body is read only
 * far enough to answer three questions — is this a replay, is it about the configured
 * account, and which document does it concern — and every byte of local state that changes
 * afterwards comes from {@see StatusSync::sync_one()}, which re-reads the document through
 * the authenticated API. A forged body can at most cause a wasted GET of a document this
 * site already owns.
 *
 * Two consequences of the delivery contract shape the responses:
 *
 * - An authenticated delivery is always answered 2xx. Assinafy attempts a delivery twice,
 *   three seconds apart, and pauses the account's deliveries after ten consecutive failures
 *   until a probe succeeds. Answering 500 because one document could not be re-read would
 *   spend that budget on a condition the hourly reconcile run fixes by itself. The outcome
 *   goes in the response body instead, where the first 2000 characters are kept in the
 *   account's delivery history.
 * - `assignment_created` and `document_metadata_ready` have no guaranteed ordering relative
 *   to each other, and a virtual assignment created before metadata finishes can deliver
 *   them backwards. Re-fetching makes the handler order-independent at no extra cost.
 *
 * The envelope has no `data` key. The entity is under `object`, the event-specific detail
 * under `payload` (which is `null`, an object, or an empty array), and `created_at` is a
 * Unix timestamp integer:
 *
 * ```
 * {"id": 30022, "event": "signer_signed_document", "message": "…",
 *  "payload": {"signer_full_name": "Jane Doe"},
 *  "origin": {"ip": "203.0.113.10", "user-agent": "…"},
 *  "created_at": 1789668256,
 *  "subject": {"id": "…", "type": "Signer"},
 *  "object":  {"id": "…", "type": "Document", "status": "certificated", "assignment": {…}},
 *  "account_id": "{ACCOUNT_ID}"}
 * ```
 */
final class Handler {

	/**
	 * Transient prefix for the per-delivery replay marker.
	 *
	 * Read-then-write across two transient calls, so it suppresses the sequential replay it
	 * is there for — a retried delivery, the delivery-history retry button — and not a true
	 * race between two simultaneous copies. What makes that safe is that the work it guards
	 * is idempotent: {@see StatusSync::sync_one()} re-reads the document and writes what the
	 * API says, so the worst a slipped duplicate costs is one extra GET. The marker is an
	 * economy, never the correctness argument. A site whose transient store silently drops
	 * writes therefore degrades to repeated re-fetches rather than to wrong state.
	 */
	public const DEDUPE_PREFIX = 'assinafy_wh_';

	/**
	 * How long a delivery id is remembered, in seconds. Seven days comfortably outlives the
	 * retry window and the delivery-history replay button alike.
	 */
	public const DEDUPE_TTL = 604800;

	/**
	 * @param StatusSync    $sync  Re-fetches a document and writes the local record.
	 * @param Log           $log   Plugin event log.
	 * @param DocumentIndex $index Finds the mirror a delivery names.
	 */
	public function __construct(
		private readonly StatusSync $sync,
		private readonly Log $log,
		private readonly DocumentIndex $index = new DocumentIndex()
	) {
	}

	/**
	 * The route callback.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Incoming delivery.
	 * @return WP_REST_Response|WP_Error 200 with an outcome body, or 400 when the body is
	 *     not a delivery envelope at all.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		nocache_headers();

		if ( ! Settings::get( Settings::OPTION_WEBHOOK_ENABLED ) ) {
			// Unticking the box stops this site from acting on deliveries; it does not
			// deactivate the account's subscription, which only `wp assinafy webhook off`
			// does. Refusing the delivery would pause it for every site on the account.
			return $this->outcome( false, 'disabled', '' );
		}

		$parser   = new WebhookEventParser();
		$envelope = $parser->extractEvent( $request->get_body() );

		if ( null === $envelope ) {
			return new WP_Error(
				'assinafy_webhook_bad_body',
				__( 'Delivery body is not valid JSON.', 'assinafy' ),
				array( 'status' => 400 )
			);
		}

		$delivery_id = $this->delivery_id( $envelope );

		if ( 0 === $delivery_id ) {
			return new WP_Error(
				'assinafy_webhook_bad_id',
				__( 'Delivery is missing a numeric id.', 'assinafy' ),
				array( 'status' => 400 )
			);
		}

		$event  = (string) $parser->getEventType( $envelope );
		$marker = self::DEDUPE_PREFIX . $delivery_id;

		if ( false !== get_transient( $marker ) ) {
			return $this->outcome( false, 'duplicate', $event );
		}

		$document_id = $this->document_to_refetch( $parser, $envelope, $event );

		if ( $document_id instanceof WP_REST_Response ) {
			return $document_id;
		}

		return $this->refetch( $document_id, $event, $marker );
	}

	/**
	 * Decide whether this delivery names a document this site should re-read.
	 *
	 * Every refusal is a 200 outcome rather than an error status: the delivery authenticated,
	 * and answering 4xx or 5xx would make Assinafy retry it and charge the failures to the
	 * account's budget.
	 *
	 * @param WebhookEventParser    $parser   Parser for the delivery envelope.
	 * @param array<string, mixed>  $envelope Decoded delivery envelope.
	 * @param string                $event    The event name as delivered.
	 * @return string|WP_REST_Response The document id to re-read, or the outcome refusing the
	 *     delivery.
	 */
	private function document_to_refetch(
		WebhookEventParser $parser,
		array $envelope,
		string $event
	): string|WP_REST_Response {
		$account = ( new Credentials() )->account_id();

		if ( '' === $account || $parser->getAccountId( $envelope ) !== $account ) {
			// One Assinafy account can serve several sites. A delivery for a different
			// account is not this site's business, and minting records from it would let
			// two installs invent each other's documents.
			$this->log->add( 'webhook_account_mismatch', array( 'event' => $event ) );

			return $this->outcome( false, 'account_mismatch', $event );
		}

		if ( ! in_array( $event, Route::EVENTS, true ) ) {
			$this->log->add( 'webhook_unknown_event', array( 'event' => $event ) );

			return $this->outcome( false, 'unknown_event', $event );
		}

		$object = $parser->getEventData( $envelope );
		$type   = is_string( $object['type'] ?? null ) ? strtolower( $object['type'] ) : '';

		if ( 'document' !== $type ) {
			// `object` is polymorphic: signer lifecycle events carry the Signer, not the
			// document it belongs to, and there is nothing to re-fetch from those.
			return $this->outcome( false, 'not_a_document', $event );
		}

		$document_id = is_string( $object['id'] ?? null ) ? $object['id'] : '';

		if ( '' === $document_id || ! $this->index->find_by_document_id( $document_id ) ) {
			// A delivery for a document this site never sent. Answering 404 would make
			// Assinafy retry it forever and count the failures against the account.
			$this->log->add(
				'webhook_unknown_document',
				array(
					'event'    => $event,
					'document' => $document_id,
				)
			);

			return $this->outcome( false, 'unknown_document', $event );
		}

		return $document_id;
	}

	/**
	 * Re-read one document from the API and write what it says.
	 *
	 * @param string $document_id Remote document id.
	 * @param string $event       The event name as delivered.
	 * @param string $marker      Replay marker to claim once the re-fetch has landed.
	 */
	private function refetch( string $document_id, string $event, string $marker ): WP_REST_Response {
		$result = $this->sync->sync_one( $document_id );

		if ( $result instanceof WP_Error ) {
			$this->log->add(
				'webhook_sync_failed',
				array(
					'event'    => $event,
					'document' => $document_id,
					'error'    => $result->get_error_message(),
					'code'     => $result->get_error_code(),
				)
			);

			// The hourly reconcile run picks this record up again, so the delivery is still
			// acknowledged rather than charged to the account's failure budget.
			return $this->outcome( false, 'sync_failed', $event );
		}

		// The replay marker is claimed here rather than on arrival: only a delivery that did
		// the work is worth remembering. Marking the ones turned away earlier, or the ones
		// whose re-fetch failed, would make the delivery-history retry button a no-op for
		// exactly the deliveries a misconfigured or briefly stranded site needs to replay.
		set_transient( $marker, 1, self::DEDUPE_TTL );

		return $this->outcome( true, 'synced', $event );
	}

	/**
	 * The envelope's delivery id, or 0 when it is absent or not an integer.
	 *
	 * The id is an activity id and arrives as a JSON number, but a numeric string is
	 * accepted so that a proxy which stringifies numbers cannot defeat replay detection.
	 *
	 * @param array<string, mixed> $envelope Decoded delivery envelope.
	 */
	private function delivery_id( array $envelope ): int {
		$id = $envelope['id'] ?? null;

		if ( is_int( $id ) ) {
			return $id > 0 ? $id : 0;
		}

		if ( is_string( $id ) && '' !== $id && ctype_digit( $id ) ) {
			return (int) $id;
		}

		return 0;
	}

	/**
	 * Build the response body.
	 *
	 * Always 200: the delivery authenticated, and the body says what became of it.
	 *
	 * @param bool   $handled Whether local state was refreshed.
	 * @param string $reason  Machine-readable outcome.
	 * @param string $event   The event name as delivered.
	 */
	private function outcome( bool $handled, string $reason, string $event ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'handled' => $handled,
				'reason'  => $reason,
				'event'   => $event,
			),
			200
		);
	}
}
