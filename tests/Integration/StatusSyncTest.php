<?php
/**
 * Status reconciliation tests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Resources\DocumentResource;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\StatusSync;
use Assinafy\WP\Http\RateLimit;
use Assinafy\WP\Log;
use WP_Error;

/**
 * The sync is what makes webhooks optional, so the contract it owes an integrator is the
 * whole of this file: which records the sweep picks up, in what order, when it stops, and
 * exactly which hooks fire with which arguments when a status moves.
 *
 * @covers \Assinafy\WP\Documents\StatusSync
 */
final class StatusSyncTest extends AssinafyTestCase {

	/**
	 * Remote document id used wherever one record is enough.
	 */
	private const DOCUMENT_ID = '104618b275d321f5de22240ebfda';

	/**
	 * Remote assignment id carried by the fixture payloads.
	 */
	private const ASSIGNMENT_ID = '1a09c15990f0144256b98ff38aa';

	/**
	 * Remote signer id carried by the fixture payloads.
	 */
	private const SIGNER_ID = '19e6b92e7895332ed9708535d8c';

	/**
	 * The complete vocabulary of `GET /documents/statuses`, in the order the API lists it.
	 *
	 * ```json
	 * {"status":200,"message":"","data":[
	 *   {"code":"uploading",           "deletable":false},
	 *   {"code":"uploaded",            "deletable":false},
	 *   {"code":"metadata_processing", "deletable":false},
	 *   {"code":"metadata_ready",      "deletable":true },
	 *   {"code":"expired",             "deletable":true },
	 *   {"code":"certificating",       "deletable":false},
	 *   {"code":"certificated",        "deletable":false},
	 *   {"code":"rejected_by_signer",  "deletable":true },
	 *   {"code":"pending_signature",   "deletable":true },
	 *   {"code":"rejected_by_user",    "deletable":true },
	 *   {"code":"failed",              "deletable":true }
	 * ]}
	 * ```
	 *
	 * @var array<int, string>
	 */
	private const STATUS_CODES = array(
		'uploading',
		'uploaded',
		'metadata_processing',
		'metadata_ready',
		'expired',
		'certificating',
		'certificated',
		'rejected_by_signer',
		'pending_signature',
		'rejected_by_user',
		'failed',
	);

	/**
	 * Sync under test.
	 */
	private StatusSync $sync;

	/**
	 * Local mirror reader.
	 */
	private DocumentRecord $records;

	/**
	 * Hook name => list of the argument lists it was called with.
	 *
	 * @var array<string, array<int, array<int, mixed>>>
	 */
	private array $fired = array();

	/**
	 * Build the sync against configured credentials and start recording hooks.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->configure_plugin();

		$this->records = new DocumentRecord();
		$this->sync    = new StatusSync(
			new ClientFactory( new Credentials(), new Log() ),
			$this->records
		);

		$this->fired = array();

		$this->record_hook( 'assinafy_document_status_changed', 3 );
		$this->record_hook( 'assinafy_document_certificated', 2 );
		$this->record_hook( 'assinafy_document_rejected', 2 );
		$this->record_hook( 'assinafy_document_expired', 2 );
		$this->record_hook( 'assinafy_document_failed', 2 );
	}

	/**
	 * Drop the rate-limit snapshot, which lives in a transient the object cache may keep.
	 */
	public function tear_down(): void {
		delete_transient( RateLimit::TRANSIENT );

		parent::tear_down();
	}

	/**
	 * A known document is fetched once, the record takes the remote state, and the post id
	 * comes back.
	 */
	public function test_sync_one_writes_the_remote_state_onto_a_known_record(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$this->fake_document( self::DOCUMENT_ID, 'certificated', true );

		$this->assertSame( $post_id, $this->sync->sync_one( self::DOCUMENT_ID ) );

		$this->assertCount( 1, $this->requests );
		$this->assertStringContainsString( 'v1/documents/' . self::DOCUMENT_ID, $this->requests[0]['url'] );

		$this->assertSame( 'certificated', $this->records->status( $post_id ) );
		$this->assertTrue( $this->records->is_closed( $post_id ) );
		$this->assertSame( self::ASSIGNMENT_ID, $this->records->assignment_id( $post_id ) );
		$this->assertSame( '', $this->records->last_error( $post_id ) );
	}

	/**
	 * A document this site never sent is refused before a request is built. The lookup is
	 * local, so an id belonging to another integration on the same account cannot be used to
	 * make this site fetch it.
	 */
	public function test_sync_one_refuses_a_document_this_site_has_no_record_of(): void {
		$error = $this->sync->sync_one( '104618ffffffffffffffffffffff' );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'assinafy_unknown_document', $error->get_error_code() );
		$this->assertSame( array(), $this->requests, 'An unknown document must not reach the API.' );
	}

	/**
	 * A deleted document answers 404, which is distinguished from every other failure so a
	 * caller can tell "gone" from "try again later".
	 */
	public function test_sync_one_reports_a_deleted_document_as_gone(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID );

		$this->fake_response(
			'v1/documents/' . self::DOCUMENT_ID,
			404,
			array(
				'status'  => 404,
				'data'    => null,
				'message' => 'Documento não encontrado.',
			)
		);

		$error = $this->sync->sync_one( self::DOCUMENT_ID );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'assinafy_document_gone', $error->get_error_code() );
		$this->assertSame( 'Documento não encontrado.', $this->records->last_error( $post_id ) );
		$this->assertSame( 'pending_signature', $this->records->status( $post_id ), 'A failed sync must not blank the last known status.' );
		$this->assertSame( array(), $this->fired, 'A failed sync announces nothing.' );
	}

	/**
	 * Any other API failure is a retryable sync failure, and the record is still touched so
	 * it moves to the back of the queue instead of blocking every record behind it.
	 */
	public function test_sync_one_touches_a_record_whose_fetch_failed(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID );

		update_post_meta( $post_id, '_assinafy_synced_at', 100 );

		$this->fake_response(
			'v1/documents/' . self::DOCUMENT_ID,
			503,
			array(
				'status'  => 503,
				'data'    => null,
				'message' => 'Service unavailable.',
			)
		);

		$error = $this->sync->sync_one( self::DOCUMENT_ID );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'assinafy_sync_failed', $error->get_error_code() );
		$this->assertGreaterThan( 100, $this->records->synced_at( $post_id ) );
	}

	/**
	 * A nominally successful response must identify the requested document before writing.
	 *
	 * @dataProvider invalid_documents
	 * @param array<string, mixed> $document Malformed or mismatched API response.
	 */
	public function test_bad_success_payload_preserves_the_last_known_document( array $document ): void {
		$post_id = $this->create_document( self::DOCUMENT_ID );
		$this->fake_response(
			'v1/documents/' . self::DOCUMENT_ID,
			200,
			array(
				'status' => 200,
				'data'   => $document,
			)
		);
		$result = $this->sync->sync_one( self::DOCUMENT_ID );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_sync_failed', $result->get_error_code() );
		$this->assertSame( self::DOCUMENT_ID, $this->records->document_id( $post_id ) );
		$this->assertSame( 'pending_signature', $this->records->status( $post_id ) );
		$this->assertSame( array(), $this->fired );
	}

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public function invalid_documents(): array {
		return array(
			'empty'          => array( array() ),
			'wrong document' => array(
				array(
					'id'     => 'abcd',
					'status' => 'certificated',
				),
			),
			'missing status' => array( array( 'id' => self::DOCUMENT_ID ) ),
			'wrong account'  => array(
				array(
					'id'         => self::DOCUMENT_ID,
					'status'     => 'certificated',
					'account_id' => 'abcd',
				),
			),
		);
	}

	/**
	 * With no credentials there is no client, and the sync says so rather than fataling on a
	 * null.
	 */
	public function test_sync_one_reports_an_unconfigured_plugin(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID );

		$sync = new StatusSync(
			new ClientFactory( new Credentials(), new Log() ),
			$this->records
		);

		delete_option( \Assinafy\WP\Settings::OPTION_ACCOUNT_ID );

		$error = $sync->sync_one( self::DOCUMENT_ID );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'assinafy_not_configured', $error->get_error_code() );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'pending_signature', $this->records->status( $post_id ) );
	}

	/**
	 * The sweep takes open records oldest first.
	 */
	public function test_reconcile_takes_the_least_recently_synced_record_first(): void {
		$ids = array(
			$this->stale_document( 0, 300 ),
			$this->stale_document( 1, 100 ),
			$this->stale_document( 2, 200 ),
		);

		$this->assertSame( 3, $this->sync->reconcile() );

		$this->assertSame(
			array( $ids[1], $ids[2], $ids[0] ),
			$this->requested_document_ids()
		);
	}

	/**
	 * A closed record leaves the queue for good. `is_closed` is the platform's own terminal
	 * flag, so a status added later cannot strand a record in the sweep.
	 */
	public function test_reconcile_never_picks_up_a_closed_record(): void {
		$open_id = $this->stale_document( 0, 500 );

		$closed_post = (int) self::factory()->post->create( array( 'post_type' => DocumentPostType::POST_TYPE ) );

		$this->records->hydrate_from_api(
			$closed_post,
			array(
				'id'        => $this->document_id( 9 ),
				'name'      => 'closed.pdf',
				'status'    => 'certificated',
				'is_closed' => true,
				'artifacts' => array(),
			)
		);

		update_post_meta( $closed_post, '_assinafy_synced_at', 1 );

		$this->assertSame( 1, $this->sync->reconcile() );
		$this->assertSame( array( $open_id ), $this->requested_document_ids() );
		$this->assertSame( 1, $this->records->synced_at( $closed_post ), 'A closed record is not even touched.' );
	}

	/**
	 * One run costs at most one `GET /documents/{id}` per record up to the batch size, even
	 * when more records are waiting.
	 */
	public function test_reconcile_stops_at_its_per_run_cap(): void {
		for ( $i = 0; $i < 25; $i++ ) {
			$this->stale_document( $i, 100 + $i );
		}

		$this->assertSame( 20, $this->sync->reconcile() );
		$this->assertCount( 20, $this->requests );
	}

	/**
	 * A record that lost its remote id is retired from the head of the queue without a
	 * request, rather than being retried forever.
	 */
	public function test_reconcile_touches_a_record_with_no_remote_id(): void {
		$orphan = (int) self::factory()->post->create( array( 'post_type' => DocumentPostType::POST_TYPE ) );

		update_post_meta( $orphan, '_assinafy_document_id', '' );
		update_post_meta( $orphan, '_assinafy_synced_at', 1 );

		$this->assertSame( 0, $this->sync->reconcile() );
		$this->assertSame( array(), $this->requests );
		$this->assertGreaterThan( 1, $this->records->synced_at( $orphan ) );
	}

	/**
	 * The sweep stops while there is still budget left in the rate-limit window, so an
	 * administrator pressing Send never queues behind an hour's worth of background reads.
	 */
	public function test_reconcile_stops_when_the_rate_limit_budget_is_nearly_spent(): void {
		$this->stale_document( 0, 100 );
		$this->stale_document( 1, 200 );

		$this->set_rate_limit( 29 );

		$this->assertSame( 0, $this->sync->reconcile() );
		$this->assertSame( array(), $this->requests, 'A thin budget belongs to interactive work.' );
	}

	/**
	 * A budget comfortably above the floor is not a reason to stop.
	 */
	public function test_reconcile_keeps_sweeping_while_budget_remains(): void {
		$document_id = $this->stale_document( 0, 100 );

		$this->set_rate_limit( 30 );

		$this->assertSame( 1, $this->sync->reconcile() );
		$this->assertSame( array( $document_id ), $this->requested_document_ids() );
	}

	/**
	 * Nothing recorded means nothing has been sent recently, which is not a reason to stop.
	 */
	public function test_reconcile_sweeps_when_no_budget_has_been_recorded(): void {
		$document_id = $this->stale_document( 0, 100 );

		$this->assertNull( RateLimit::snapshot() );
		$this->assertSame( 1, $this->sync->reconcile() );
		$this->assertSame( array( $document_id ), $this->requested_document_ids() );
	}

	/**
	 * The whole happy path of BUILD-BRIEF §1.3, one sync per hop, each announcing the status
	 * it moved to and the one it held.
	 */
	public function test_the_happy_path_announces_every_transition_with_both_statuses(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'uploading' );

		$path = array( 'uploaded', 'metadata_processing', 'metadata_ready', 'pending_signature', 'certificating', 'certificated' );

		foreach ( $path as $status ) {
			$this->fake_document( self::DOCUMENT_ID, $status, 'certificated' === $status );

			$this->assertSame( $post_id, $this->sync->sync_one( self::DOCUMENT_ID ) );
		}

		$this->assertSame(
			array(
				array( $post_id, 'uploaded', 'uploading' ),
				array( $post_id, 'metadata_processing', 'uploaded' ),
				array( $post_id, 'metadata_ready', 'metadata_processing' ),
				array( $post_id, 'pending_signature', 'metadata_ready' ),
				array( $post_id, 'certificating', 'pending_signature' ),
				array( $post_id, 'certificated', 'certificating' ),
			),
			$this->fired['assinafy_document_status_changed']
		);
	}

	/**
	 * A sync that finds the status unchanged announces nothing. The cron runs hourly against
	 * documents that mostly have not moved, so a hook firing on every sweep would make
	 * `assinafy_document_status_changed` useless as a trigger.
	 */
	public function test_a_sync_that_changes_nothing_announces_nothing(): void {
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$this->fake_document( self::DOCUMENT_ID, 'pending_signature', false );

		$this->sync->sync_one( self::DOCUMENT_ID );

		$this->assertSame( array(), $this->fired );
	}

	/**
	 * Adapters can route verified transitions using the source saved before the upload.
	 */
	public function test_status_callbacks_can_resolve_the_original_source(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$source  = array(
			'integration' => 'example-forms',
			'record_id'   => 'entry-123',
		);
		$this->assertTrue( $this->records->set_source( $post_id, $source ) );
		$observed = array();
		add_action(
			'assinafy_document_status_changed',
			function ( int $document_post_id ) use ( &$observed ): void {
				$observed[] = array( $document_post_id, $this->records->source( $document_post_id ), $this->records->status( $document_post_id ) );
			}
		);
		$this->fake_document( self::DOCUMENT_ID, 'certificated', true );
		$this->sync->sync_one( self::DOCUMENT_ID );
		$this->assertSame( array( array( $post_id, $source, 'certificated' ) ), $observed );
	}

	/**
	 * Each terminal state fires its own hook, carrying the document exactly as the API
	 * returned it.
	 *
	 * @dataProvider terminal_outcomes
	 *
	 * @param string $status The terminal status code.
	 * @param string $hook   The action integrators bind to for it.
	 */
	public function test_a_terminal_status_fires_its_own_hook( string $status, string $hook ): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$this->fake_document( self::DOCUMENT_ID, $status, true );

		$this->sync->sync_one( self::DOCUMENT_ID );

		$this->assertSame(
			array( array( $post_id, $status, 'pending_signature' ) ),
			$this->fired['assinafy_document_status_changed']
		);

		$this->assertArrayHasKey( $hook, $this->fired );
		$this->assertCount( 1, $this->fired[ $hook ] );

		$arguments = $this->fired[ $hook ][0];

		$this->assertSame( $post_id, $arguments[0] );
		$this->assertIsArray( $arguments[1] );
		$this->assertSame( self::DOCUMENT_ID, $arguments[1]['id'] );
		$this->assertSame( $status, $arguments[1]['status'] );
		$this->assertSame( self::ASSIGNMENT_ID, $arguments[1]['assignment']['id'] );

		$this->assertSame(
			array( 'assinafy_document_status_changed', $hook ),
			array_keys( $this->fired ),
			'A terminal status fires exactly one specific hook.'
		);
	}

	/**
	 * The four terminal outcomes and the actions they fire. Both declines share one hook:
	 * an integrator cares that the document will never be signed, not who stopped it, and
	 * `decline_reason` / `declined_by` on the payload say which when it matters.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function terminal_outcomes(): array {
		return array(
			'certificated'       => array( 'certificated', 'assinafy_document_certificated' ),
			'rejected_by_signer' => array( 'rejected_by_signer', 'assinafy_document_rejected' ),
			'rejected_by_user'   => array( 'rejected_by_user', 'assinafy_document_rejected' ),
			'expired'            => array( 'expired', 'assinafy_document_expired' ),
			'failed'             => array( 'failed', 'assinafy_document_failed' ),
		);
	}

	/**
	 * A status on the way to a terminal one announces the transition and nothing else.
	 *
	 * @dataProvider open_statuses
	 *
	 * @param string $status A status code that is not terminal.
	 */
	public function test_an_open_status_fires_only_the_generic_hook( string $status ): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'uploading' );

		$this->fake_document( self::DOCUMENT_ID, $status, false );

		$this->sync->sync_one( self::DOCUMENT_ID );

		$this->assertSame(
			array( array( $post_id, $status, 'uploading' ) ),
			$this->fired['assinafy_document_status_changed']
		);

		$this->assertSame( array( 'assinafy_document_status_changed' ), array_keys( $this->fired ) );
	}

	/**
	 * The six codes a document holds while it is still open and moving.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function open_statuses(): array {
		$cases = array();

		foreach ( array( 'uploaded', 'metadata_processing', 'metadata_ready', 'pending_signature', 'certificating' ) as $status ) {
			$cases[ $status ] = array( $status );
		}

		return $cases;
	}

	/**
	 * The eleven codes are the whole machine, and `ready` is not one of them.
	 *
	 * `DocumentResource::STATUS_READY` exists in the SDK and the webhook catalog describes
	 * `document_ready` as "status is now ready", but the API never emits a status by that
	 * name: the last signature moves a document to `certificating` and then `certificated`.
	 * An integrator who bound to a `ready` status would wait forever, so no code path here
	 * may treat it as terminal.
	 */
	public function test_the_status_machine_has_eleven_codes_and_no_ready(): void {
		$this->assertCount( 11, self::STATUS_CODES );
		$this->assertSame( self::STATUS_CODES, array_unique( self::STATUS_CODES ) );
		$this->assertNotContains( DocumentResource::STATUS_READY, self::STATUS_CODES );

		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$this->fake_document( self::DOCUMENT_ID, DocumentResource::STATUS_READY, false );

		$this->sync->sync_one( self::DOCUMENT_ID );

		$this->assertSame(
			array( array( $post_id, 'ready', 'pending_signature' ) ),
			$this->fired['assinafy_document_status_changed']
		);

		$this->assertSame(
			array( 'assinafy_document_status_changed' ),
			array_keys( $this->fired ),
			'A status the machine does not define must not be announced as an outcome.'
		);
	}

	/**
	 * Every one of the eleven codes survives the round trip onto the record.
	 */
	public function test_every_status_code_round_trips_onto_the_record(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, '' );

		foreach ( self::STATUS_CODES as $status ) {
			$this->fake_document( self::DOCUMENT_ID, $status, false );

			$this->sync->sync_one( self::DOCUMENT_ID );

			$this->assertSame( $status, $this->records->status( $post_id ) );
		}
	}

	/**
	 * Start recording every call to one hook.
	 *
	 * @param string $hook           Action name.
	 * @param int    $accepted_args  Arguments the action passes.
	 */
	private function record_hook( string $hook, int $accepted_args ): void {
		add_action(
			$hook,
			function () use ( $hook ): void {
				$this->fired[ $hook ][] = func_get_args();
			},
			10,
			$accepted_args
		);
	}

	/**
	 * Queue `GET /documents/{documentId}` for one document.
	 *
	 * The payload is the live sandbox capture, trimmed to the keys the record reads.
	 *
	 * @param string $document_id Remote document id.
	 * @param string $status      Status the document reports.
	 * @param bool   $is_closed   The document's own terminal flag.
	 */
	private function fake_document( string $document_id, string $status, bool $is_closed ): void {
		$this->fake_response(
			'v1/documents/' . $document_id,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'       => 'document',
					'id'             => $document_id,
					'account_id'     => self::ACCOUNT_ID,
					'template_id'    => null,
					'name'           => 'contract.pdf',
					'status'         => $status,
					'artifacts'      => array(
						'original'  => self::API_HOST . 'v1/documents/' . $document_id . '/download/original',
						'thumbnail' => self::API_HOST . 'v1/documents/' . $document_id . '/thumbnail',
					),
					'is_closed'      => $is_closed,
					'signing_url'    => 'https://app.example.com/sign/' . $document_id,
					'decline_reason' => null,
					'declined_by'    => null,
					'tags'           => array(),
					'created_at'     => '2026-09-13T18:40:10Z',
					'updated_at'     => '2026-09-13T18:40:12Z',
					'assignment'     => array(
						'resource'       => 'assignment',
						'id'             => self::ASSIGNMENT_ID,
						'method'         => 'virtual',
						'sender_email'   => 'sender@example.com',
						'expires_at'     => '2026-12-31T23:59:59Z',
						'message'        => 'Please review and sign.',
						'copy_receivers' => array(),
						'items'          => array(),
						'signers'        => array(
							array(
								'id'        => self::SIGNER_ID,
								'full_name' => 'Jane Doe',
								'email'     => 'jane@example.com',
								'completed' => 'certificated' === $status,
								'step'      => 1,
								'notified'  => true,
							),
						),
						'signing_urls'   => array(
							array(
								'signer_id' => self::SIGNER_ID,
								'url'       => 'https://app.example.com/sign/' . $document_id . '?email=jane%40example.com',
							),
						),
					),
					'pages'          => array(),
				),
			)
		);
	}

	/**
	 * Create one open mirror record with a fixed staleness, and queue its API response.
	 *
	 * @param int $index     Distinguishes this record's remote id from the others.
	 * @param int $synced_at Value for `_assinafy_synced_at`, which orders the queue.
	 *
	 * @return string The remote document id.
	 */
	private function stale_document( int $index, int $synced_at ): string {
		$document_id = $this->document_id( $index );
		$post_id     = $this->create_document( $document_id );

		update_post_meta( $post_id, '_assinafy_synced_at', $synced_at );

		$this->fake_document( $document_id, 'pending_signature', false );

		return $document_id;
	}

	/**
	 * A distinct, well-formed remote document id.
	 *
	 * Ids are opaque lowercase hex of variable length; 28 characters is one observed length.
	 *
	 * @param int $index Sequence number.
	 */
	private function document_id( int $index ): string {
		return '104618b275d321f5de22240eb' . sprintf( '%03x', $index );
	}

	/**
	 * The remote document ids the fake was asked for, in order.
	 *
	 * @return array<int, string>
	 */
	private function requested_document_ids(): array {
		$ids = array();

		foreach ( $this->requests as $request ) {
			$ids[] = basename( (string) wp_parse_url( $request['url'], PHP_URL_PATH ) );
		}

		return $ids;
	}

	/**
	 * Record a rate-limit budget the way a successful response would have.
	 *
	 * @param int $remaining Requests left in the 120-per-minute window.
	 */
	private function set_rate_limit( int $remaining ): void {
		set_transient(
			RateLimit::TRANSIENT,
			array(
				'remaining' => $remaining,
				'reset'     => 60,
				'recorded'  => time(),
			),
			120
		);
	}
}
