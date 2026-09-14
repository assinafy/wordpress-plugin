<?php
/**
 * Send service tests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentIndex;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;
use WP_Error;

/**
 * The two things a send must never do: charge the account twice for one trigger, and report
 * success for a document whose local record was never written.
 *
 * @covers \Assinafy\WP\Documents\SendService
 */
final class SendServiceTest extends AssinafyTestCase {

	/**
	 * Document id the fake upload hands back.
	 */
	private const DOCUMENT_ID = '104618d0d63884bc446c534e5ff5';

	/**
	 * Assignment id the fake assignment create hands back.
	 */
	private const ASSIGNMENT_ID = '1a09c15990f0144256b98ff38aa';

	/**
	 * Signer id the fake lookup hands back.
	 */
	private const SIGNER_ID = '19e6b92e7895332ed9708535d8c';

	/**
	 * The originating form entry is independent of the remote signing document.
	 */
	private const SOURCE = array(
		'integration' => 'contact-form-7',
		'record_id'   => 'entry-1187',
	);

	/**
	 * Service under test, built on its own client factory so the memoised plugin-wide one
	 * is left alone.
	 */
	private SendService $service;

	/**
	 * Local mirror, for reading back what the service wrote.
	 */
	private DocumentRecord $records;

	/**
	 * Build the service.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->configure_plugin();

		$this->records = new DocumentRecord();
		$this->service = new SendService(
			new ClientFactory( new Credentials(), new Log() ),
			$this->records,
			new Log()
		);
	}

	/**
	 * One trigger fired twice produces one document and one assignment.
	 *
	 * The lock key is derived from the file and the signer set, so a double-clicked submit
	 * or a WooCommerce order that completes twice resolves to the same lock without either
	 * caller knowing about the other.
	 */
	public function test_sending_the_same_document_twice_creates_one_assignment(): void {
		$this->fake_successful_send();

		$first = $this->service->send( $this->args() );

		$this->assertIsInt( $first );
		$this->assertSame( DocumentPostType::POST_TYPE, get_post_type( $first ) );
		$this->assertSame( self::DOCUMENT_ID, $this->records->document_id( $first ) );
		$this->assertSame( self::ASSIGNMENT_ID, $this->records->assignment_id( $first ) );

		$requests_after_first = count( $this->requests );
		$this->assertGreaterThan( 0, $requests_after_first );

		$second = $this->service->send( $this->args() );

		$this->assertSame( $first, $second, 'The repeated send must resolve to the first record.' );
		$this->assertCount( $requests_after_first, $this->requests, 'The repeated send must not reach the API at all.' );
		$this->assertCount(
			1,
			get_posts(
				array(
					'post_type' => DocumentPostType::POST_TYPE,
					'fields'    => 'ids',
				)
			)
		);
	}

	/**
	 * A caller-supplied idempotency key short-circuits to the record the first send made.
	 */
	public function test_a_supplied_idempotency_key_returns_the_existing_record(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID );

		set_transient( $this->supplied_lock( 'order-1042' ), $post_id, 300 );

		$result = $this->service->send( $this->args( array( 'idempotency_key' => 'order-1042' ) ) );

		$this->assertSame( $post_id, $result );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * While the first send is still in flight the lock holds `-1`, which is not a post id,
	 * so the second caller is told to wait rather than handed a bogus record.
	 */
	public function test_a_send_still_in_flight_is_refused(): void {
		set_transient( $this->supplied_lock( 'order-1042' ), -1, 300 );

		$result = $this->service->send( $this->args( array( 'idempotency_key' => 'order-1042' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_send_in_progress', $result->get_error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * A request arriving before the transient write cannot start a second upload.
	 */
	public function test_simultaneous_sends_share_an_atomic_database_lock(): void {
		$this->fake_successful_send();
		$lock   = $this->supplied_lock( 'racing-order' );
		$nested = null;
		$race   = function ( $value ) use ( &$nested, $lock ) {
			if ( -1 === $value ) {
				$nested = $this->service->send( $this->args( array( 'idempotency_key' => 'racing-order' ) ) );
			}
			return $value;
		};
		add_filter( 'pre_set_transient_' . $lock, $race );
		try {
			$result = $this->service->send( $this->args( array( 'idempotency_key' => 'racing-order' ) ) );
		} finally {
			remove_filter( 'pre_set_transient_' . $lock, $race );
		}
		$this->assertIsInt( $result );
		$this->assertInstanceOf( WP_Error::class, $nested );
		$this->assertSame( 'assinafy_send_in_progress', $nested->get_error_code() );
		$this->assertFalse( get_option( $lock . '.lock' ), 'The atomic lock is released after sending.' );
	}

	/**
	 * The selected file's contents, every signer field and the message identify a send.
	 */
	public function test_different_send_payloads_do_not_share_a_deduplication_key(): void {
		$this->fake_successful_send();
		$first = $this->service->send( $this->args() );
		$this->assertIsInt( $first );
		$second = $this->service->send( $this->args( array( 'message' => 'A separate agreement.' ) ) );
		$this->assertIsInt( $second );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * Malformed source identity cannot reach either the estimate or upload endpoints.
	 *
	 * @dataProvider invalid_sources
	 * @param mixed $source Invalid caller-provided source.
	 */
	public function test_invalid_source_is_rejected_before_http( mixed $source ): void {
		$result = $this->service->send( $this->args( array( 'source' => $source ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_invalid_source', $result->get_error_code() );
		$this->assertSame( array(), $this->requests );
		$this->assertSame(
			array(),
			get_posts(
				array(
					'post_type' => DocumentPostType::POST_TYPE,
					'fields'    => 'ids',
				)
			)
		);
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function invalid_sources(): array {
		return array(
			'explicit null'         => array( null ),
			'not a record'          => array( 'contact-form-7:entry-1187' ),
			'missing id'            => array( array( 'integration' => 'contact-form-7' ) ),
			'extra field'           => array( self::SOURCE + array( 'label' => 'Entry' ) ),
			'uppercase integration' => array(
				array(
					'integration' => 'Contact-Form-7',
					'record_id'   => 'entry-1187',
				),
			),
			'id containing spaces'  => array(
				array(
					'integration' => 'contact-form-7',
					'record_id'   => 'entry 1187',
				),
			),
			'numeric id'            => array(
				array(
					'integration' => 'contact-form-7',
					'record_id'   => 1187,
				),
			),
		);
	}

	/**
	 * The default digest distinguishes both the originating integration and its record.
	 *
	 * @dataProvider different_sources
	 * @param array{integration: string, record_id: string} $source Another origin.
	 */
	public function test_different_sources_have_distinct_default_send_keys( array $source ): void {
		$this->fake_successful_send();
		$first  = $this->service->send( $this->args( array( 'source' => self::SOURCE ) ) );
		$second = $this->service->send( $this->args( array( 'source' => $source ) ) );
		$this->assertIsInt( $first );
		$this->assertIsInt( $second );
		$this->assertNotSame( $first, $second );
		$this->assertSame( self::SOURCE, $this->records->source( $first ) );
		$this->assertSame( $source, $this->records->source( $second ) );
	}

	/**
	 * Associative field ordering does not create a different originating record.
	 */
	public function test_source_field_order_does_not_change_default_identity(): void {
		$this->fake_successful_send();
		$post_id = $this->service->send( $this->args( array( 'source' => self::SOURCE ) ) );
		$this->assertIsInt( $post_id );
		$requests = count( $this->requests );
		$this->assertSame( $post_id, $this->service->send( $this->args( array( 'source' => array_reverse( self::SOURCE, true ) ) ) ) );
		$this->assertCount( $requests, $this->requests );
	}

	/**
	 * @return array<string, array{0: array{integration: string, record_id: string}}>
	 */
	public static function different_sources(): array {
		return array(
			'another integration' => array(
				array(
					'integration' => 'gravity-forms',
					'record_id'   => 'entry-1187',
				),
			),
			'another record'      => array(
				array(
					'integration' => 'contact-form-7',
					'record_id'   => 'entry-1188',
				),
			),
		);
	}

	/**
	 * Cache and durable-key lookups both retain the source without repeating remote work.
	 *
	 * @dataProvider deduplication_paths
	 * @param bool $expire_cache Whether to exercise the durable record lookup.
	 */
	public function test_matching_source_deduplicates_and_omission_preserves_it( bool $expire_cache ): void {
		$this->fake_successful_send();
		$args    = $this->args(
			array(
				'idempotency_key' => 'source-matches',
				'source'          => self::SOURCE,
			)
		);
		$post_id = $this->service->send( $args );
		$this->assertIsInt( $post_id );
		if ( $expire_cache ) {
			delete_transient( $this->supplied_lock( 'source-matches' ) );
		}
		$requests       = count( $this->requests );
		$args['source'] = array_reverse( self::SOURCE, true );
		$this->assertSame( $post_id, $this->service->send( $args ) );
		unset( $args['source'] );
		if ( $expire_cache ) {
			delete_transient( $this->supplied_lock( 'source-matches' ) );
		}
		$this->assertSame( $post_id, $this->service->send( $args ) );
		$this->assertCount( $requests, $this->requests );
		$this->assertSame( self::SOURCE, $this->records->source( $post_id ) );
	}

	/**
	 * A caller cannot relabel a previous send by reusing its explicit idempotency key.
	 *
	 * @dataProvider deduplication_paths
	 * @param bool $expire_cache Whether to exercise the durable record lookup.
	 */
	public function test_conflicting_source_is_rejected_without_http_or_metadata_changes( bool $expire_cache ): void {
		$this->fake_successful_send();
		$args    = $this->args(
			array(
				'idempotency_key' => 'source-conflicts',
				'source'          => self::SOURCE,
			)
		);
		$post_id = $this->service->send( $args );
		$this->assertIsInt( $post_id );
		if ( $expire_cache ) {
			delete_transient( $this->supplied_lock( 'source-conflicts' ) );
		}
		$requests                    = count( $this->requests );
		$metadata                    = get_post_meta( $post_id );
		$args['source']['record_id'] = 'entry-1188';
		$result                      = $this->service->send( $args );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_source_conflict', $result->get_error_code() );
		$this->assertCount( $requests, $this->requests );
		$this->assertSame( $metadata, get_post_meta( $post_id ) );
	}

	/**
	 * A different source cannot replace an empty mirror's original recovery key.
	 */
	public function test_conflicting_source_on_an_empty_mirror_preserves_its_reservation(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => DocumentPostType::POST_TYPE ) );
		$this->assertTrue( $this->records->set_source( $post_id, self::SOURCE ) );
		$this->records->set_send_key( $post_id, $this->supplied_lock( 'original-source' ) );
		$metadata = get_post_meta( $post_id );
		$result   = $this->service->send(
			$this->args(
				array(
					'post_id'         => $post_id,
					'idempotency_key' => 'conflicting-source',
					'source'          => array(
						'integration' => self::SOURCE['integration'],
						'record_id'   => 'entry-1188',
					),
				)
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_source_conflict', $result->get_error_code() );
		$this->assertSame( $post_id, $result->get_error_data()['post_id'] );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( $metadata, get_post_meta( $post_id ) );
	}

	/**
	 * A failed source write must not look like an upload with an unknown outcome on retry.
	 */
	public function test_failed_source_persistence_does_not_reserve_an_upload(): void {
		$this->fake_successful_send();
		$args   = $this->args(
			array(
				'idempotency_key' => 'source-write-refused',
				'source'          => self::SOURCE,
			)
		);
		$refuse = static fn( mixed $check, int $post_id, string $key ): mixed => '_assinafy_source' === $key ? false : $check;
		add_filter( 'update_post_metadata', $refuse, 10, 3 );
		try {
			$result = $this->service->send( $args );
		} finally {
			remove_filter( 'update_post_metadata', $refuse, 10 );
		}
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_send_failed', $result->get_error_code() );
		$this->assertSame( 0, ( new DocumentIndex() )->find_by_send_key( $this->supplied_lock( 'source-write-refused' ) ) );
		$this->assertCount( 0, array_filter( $this->requests, static fn( array $request ): bool => str_contains( $request['url'], '/accounts/' . self::ACCOUNT_ID . '/documents' ) ) );

		$post_id = $this->service->send( $args );
		$this->assertIsInt( $post_id );
		$this->assertSame( self::SOURCE, $this->records->source( $post_id ) );
		$this->assertSame( self::DOCUMENT_ID, $this->records->document_id( $post_id ) );
		$this->assertCount( 1, array_filter( $this->requests, static fn( array $request ): bool => str_contains( $request['url'], '/accounts/' . self::ACCOUNT_ID . '/documents' ) ) );
	}

	/**
	 * Older records can acquire an origin without changing their established send key.
	 *
	 * @dataProvider deduplication_paths
	 * @param bool $expire_cache Whether to exercise the durable record lookup.
	 */
	public function test_legacy_success_can_adopt_source_without_another_upload( bool $expire_cache ): void {
		$this->fake_successful_send();
		$args    = $this->args( array( 'idempotency_key' => 'legacy-source' ) );
		$post_id = $this->service->send( $args );
		$this->assertIsInt( $post_id );
		$this->assertSame( array(), $this->records->source( $post_id ) );
		$this->assertSame( $post_id, get_transient( $this->supplied_lock( 'legacy-source' ) ), 'The existing supplied-key hash remains compatible.' );
		if ( $expire_cache ) {
			delete_transient( $this->supplied_lock( 'legacy-source' ) );
		}
		$requests       = count( $this->requests );
		$args['source'] = self::SOURCE;
		$this->assertSame( $post_id, $this->service->send( $args ) );
		$this->assertSame( self::SOURCE, $this->records->source( $post_id ) );
		$this->assertCount( $requests, $this->requests );
	}

	/**
	 * @return array<string, array{0: bool}>
	 */
	public static function deduplication_paths(): array {
		return array(
			'cached result'  => array( false ),
			'durable record' => array( true ),
		);
	}

	/**
	 * Replacing a PDF with different bytes of the same size creates a new signature request.
	 */
	public function test_equal_filename_and_size_do_not_hide_changed_pdf_contents(): void {
		$this->fake_successful_send();
		$file = get_temp_dir() . wp_unique_id( 'assinafy-content-' ) . '.pdf';
		$pdf  = (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/sample.pdf' );
		try {
			file_put_contents( $file, $pdf );
			$first = $this->service->send( $this->args( array( 'file_path' => $file ) ) );
			file_put_contents( $file, str_replace( '612', '600', $pdf ) );
			$this->assertSame( strlen( $pdf ), filesize( $file ) );
			$second = $this->service->send( $this->args( array( 'file_path' => $file ) ) );
		} finally {
			unlink( $file );
		}
		$this->assertIsInt( $first );
		$this->assertIsInt( $second );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * A cached success for one account must not masquerade as a send in another.
	 */
	public function test_supplied_keys_are_scoped_to_the_configured_account(): void {
		$this->fake_successful_send();
		$args  = $this->args( array( 'idempotency_key' => 'same-key' ) );
		$first = $this->service->send( $args );
		$this->assertIsInt( $first );
		$account = '104618a0000000000000000002';
		update_option( Settings::OPTION_ACCOUNT_ID, $account );
		$this->fake_successful_send( $account );
		$other  = new SendService( new ClientFactory( new Credentials(), new Log() ), $this->records, new Log() );
		$second = $other->send( $args );
		$this->assertIsInt( $second );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * A malformed row must never silently remove a required participant.
	 */
	public function test_malformed_signer_rows_refuse_the_entire_send(): void {
		$args              = $this->args();
		$args['signers'][] = 'not a signer row';
		$result            = $this->service->send( $args );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_signer_incomplete', $result->get_error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Mixed input cannot stringify an array into a signer's identity.
	 */
	public function test_structured_signer_fields_are_refused_before_upload(): void {
		$args                       = $this->args();
		$args['signers'][0]['name'] = array( 'Jane Doe' );
		$result                     = $this->service->send( $args );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_signer_incomplete', $result->get_error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * The API rejects a past expiry after upload, so the service refuses it locally.
	 */
	public function test_past_expiry_is_rejected_before_upload(): void {
		$this->fake_successful_send();
		$result = $this->service->send( $this->args( array( 'expires_at' => '2000-01-01T00:00:00Z' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_invalid_expiry', $result->get_error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * An existing signer id without contact overrides inherits the SDK's email default.
	 */
	public function test_existing_signer_id_defaults_to_email(): void {
		$this->fake_successful_send();
		$result = $this->service->send( $this->args( array( 'signers' => array( array( 'id' => self::SIGNER_ID ) ) ) ) );
		$this->assertIsInt( $result );
		$request = end( $this->requests );
		$body    = json_decode( (string) $request['args']['body'], true );
		$this->assertSame( 'Email', $body['signers'][0]['verification_method'] );
		$this->assertSame( array( 'Email' ), $body['signers'][0]['notification_methods'] );
	}

	/**
	 * Digital certificates permit either notification channel; the requested one is retained.
	 */
	public function test_digital_certificate_signers_keep_whatsapp_notifications(): void {
		$this->fake_successful_send();
		$result = $this->service->send(
			$this->args(
				array(
					'signers' => array(
						array(
							'id'                   => self::SIGNER_ID,
							'verification_method'  => 'DigitalCertificate',
							'notification_methods' => array( 'Whatsapp' ),
						),
					),
				)
			)
		);
		$this->assertIsInt( $result );
		$request = end( $this->requests );
		$body    = json_decode( (string) $request['args']['body'], true );
		$this->assertSame( 'DigitalCertificate', $body['signers'][0]['verification_method'] );
		$this->assertSame( array( 'Whatsapp' ), $body['signers'][0]['notification_methods'] );
	}

	/**
	 * An assignment failure leaves a recoverable mirror and never requires a second upload.
	 */
	public function test_assignment_failure_retries_the_saved_upload(): void {
		$this->fake_successful_send();
		$this->fake_response(
			'/assignments',
			503,
			array(
				'status'  => 503,
				'message' => 'Try again.',
			)
		);
		$args             = $this->args(
			array(
				'idempotency_key' => 'recover-assignment',
				'source'          => self::SOURCE,
			)
		);
		$source_at_upload = null;
		$inspect          = function ( mixed $preempt, array $http_args, string $url ) use ( &$source_at_upload ): mixed {
			if ( str_ends_with( $url, '/accounts/' . self::ACCOUNT_ID . '/documents' ) ) {
				$posts            = get_posts(
					array(
						'post_type' => DocumentPostType::POST_TYPE,
						'fields'    => 'ids',
					)
				);
				$source_at_upload = $this->records->source( (int) ( $posts[0] ?? 0 ) );
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $inspect, 9, 3 );
		try {
			$failed = $this->service->send( $args );
		} finally {
			remove_filter( 'pre_http_request', $inspect, 9 );
		}
		$this->assertInstanceOf( WP_Error::class, $failed );
		$this->assertSame( self::SOURCE, $source_at_upload, 'The origin must be durable before the upload is sent.' );
		$post_id = $failed->get_error_data()['post_id'];
		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( self::DOCUMENT_ID, $this->records->document_id( $post_id ) );
		$this->assertSame( self::SOURCE, $this->records->source( $post_id ) );
		$this->assertNotSame( '', $this->records->last_error( $post_id ) );
		delete_transient( $this->supplied_lock( 'recover-assignment' ) );
		$this->fake_successful_send();
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			200,
			array(
				'status' => 200,
				'data'   => array(
					'id'         => self::DOCUMENT_ID,
					'status'     => 'uploaded',
					'assignment' => null,
				),
			)
		);
		$this->assertSame( $post_id, $this->service->send( $args ) );
		$this->assertSame( self::ASSIGNMENT_ID, $this->records->assignment_id( $post_id ) );
		$this->assertSame( self::SOURCE, $this->records->source( $post_id ) );
		$this->assertSame( '', $this->records->last_error( $post_id ) );
		$this->assertCount( 1, array_filter( $this->requests, static fn( array $request ): bool => str_contains( $request['url'], '/accounts/' . self::ACCOUNT_ID . '/documents' ) ) );
	}

	/**
	 * Lost assignment responses are resolved by GET, never by uploading the PDF again.
	 */
	public function test_assignment_created_remotely_is_recognized_on_retry(): void {
		$this->fake_successful_send();
		$this->fake_response(
			'/assignments',
			503,
			array(
				'status'  => 503,
				'message' => 'Response lost.',
			)
		);
		$args   = $this->args( array( 'idempotency_key' => 'lost-assignment-response' ) );
		$failed = $this->service->send( $args );
		$this->assertInstanceOf( WP_Error::class, $failed );
		$post_id = $failed->get_error_data()['post_id'];
		$this->assertSame( array(), $this->records->source( $post_id ) );
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			200,
			array(
				'status' => 200,
				'data'   => array(
					'id'         => self::DOCUMENT_ID,
					'status'     => 'pending_signature',
					'assignment' => array( 'id' => self::ASSIGNMENT_ID ),
				),
			)
		);
		$requests       = count( $this->requests );
		$args['source'] = self::SOURCE;
		$this->assertSame( $post_id, $this->service->send( $args ) );
		$this->assertSame( self::SOURCE, $this->records->source( $post_id ), 'A legacy pending send adopts its source while recovering the original upload.' );
		$this->assertCount( $requests + 1, $this->requests, 'Only the authoritative document GET is needed.' );
	}

	/**
	 * A missing upload response is uncertain; a blind retry could consume a second document.
	 */
	public function test_unknown_upload_outcome_stays_blocked_after_transient_expiry(): void {
		$this->fake_successful_send();
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/documents',
			503,
			array(
				'status'  => 503,
				'message' => 'Response lost.',
			)
		);
		$args   = $this->args( array( 'idempotency_key' => 'lost-upload-response' ) );
		$failed = $this->service->send( $args );
		$this->assertInstanceOf( WP_Error::class, $failed );
		$this->assertGreaterThan( 0, $failed->get_error_data()['post_id'] );
		delete_transient( $this->supplied_lock( 'lost-upload-response' ) );
		$requests = count( $this->requests );
		$retry    = $this->service->send( $args );
		$this->assertInstanceOf( WP_Error::class, $retry );
		$this->assertStringContainsString( 'outcome is unknown', $retry->get_error_message() );
		$this->assertCount( $requests, $this->requests, 'An uncertain upload is never retried automatically.' );
	}

	/**
	 * Durable send keys still prevent duplicate billing after a transient is evicted.
	 */
	public function test_successful_send_remains_idempotent_after_transient_expiry(): void {
		$this->fake_successful_send();
		$args  = $this->args( array( 'idempotency_key' => 'durable-send' ) );
		$first = $this->service->send( $args );
		$this->assertIsInt( $first );
		delete_transient( $this->supplied_lock( 'durable-send' ) );
		$requests = count( $this->requests );
		$this->assertSame( $first, $this->service->send( $args ) );
		$this->assertCount( $requests, $this->requests );
	}

	/**
	 * An account that cannot pay is refused after the upload and before the assignment.
	 *
	 * The estimate has to be addressed to a document that carries no assignment yet, so the
	 * only one it can price is the document this send has just uploaded. The allowance is
	 * spent by the assignment, so a refusal that lands between the two costs the account
	 * nothing, and the send lock is released so the saved upload can resume once it can pay.
	 */
	public function test_an_unaffordable_account_is_refused_between_the_upload_and_the_assignment(): void {
		$this->fake_response(
			'/assignments/estimate-cost',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'documents'                => 1,
					'credits'                  => 0,
					'total_credits'            => 0,
					'breakdown'                => array(),
					'document_balance'         => 0,
					'credit_balance'           => 0,
					'has_sufficient_resources' => false,
					'blocking_reason'          => 'InsufficientDocuments',
					'message'                  => 'A conta não possui documentos suficientes.',
				),
			)
		);
		$this->fake_successful_send();

		$result = $this->service->send( $this->args( array( 'idempotency_key' => 'no-allowance' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_insufficient_resources', $result->get_error_code() );
		$this->assertSame(
			array(
				self::API_HOST . 'v1/accounts/' . self::ACCOUNT_ID . '/signers?search=jane%40example.com&page=1&per-page=100',
				self::API_HOST . 'v1/accounts/' . self::ACCOUNT_ID . '/documents',
				self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/assignments/estimate-cost',
			),
			array_column( $this->requests, 'url' ),
			'The price is asked of the document just uploaded, and no assignment follows the refusal.'
		);

		$post_id = ( new DocumentIndex() )->find_by_document_id( self::DOCUMENT_ID );

		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( '', $this->records->assignment_id( $post_id ), 'Nothing was assigned, so nothing was charged.' );
		$this->assertSame(
			'The Assinafy account has no document allowance left on its plan.',
			$result->get_error_message(),
			'The blocking reason reaches the operator as something they can act on.'
		);
		$this->assertFalse( get_transient( $this->supplied_lock( 'no-allowance' ) ), 'A refused send may be retried once the account can pay.' );
	}

	/**
	 * Explicit caller keys are scoped to the active account and environment.
	 *
	 * @param string $key Caller key.
	 */
	private function supplied_lock( string $key ): string {
		return SendService::LOCK_PREFIX . md5(
			(string) wp_json_encode(
				array(
					'account'     => self::ACCOUNT_ID,
					'environment' => self::API_HOST . 'v1',
					'request'     => $key,
				)
			)
		);
	}

	/**
	 * A document whose mirror cannot be reserved is refused before upload.
	 *
	 * `wp_insert_post()` is called with `$wp_error = true` and its return value is checked
	 * for a falsy id as well. The two checks catch different things: current core answers
	 * every failure it can name with a `WP_Error`, and returns a bare `0` only when the
	 * insert reports success while `$wpdb->insert_id` comes back zero — which
	 * `is_wp_error()` does not catch, and which would otherwise send every following meta
	 * write to post 0 and silently discard it.
	 */
	public function test_a_mirror_that_cannot_be_written_is_reported_as_a_failure(): void {
		$this->assertFalse( is_wp_error( 0 ), 'The falsy-id check exists because 0 is not a WP_Error.' );

		$this->fake_successful_send();

		add_filter( 'wp_insert_post_empty_content', '__return_true' );

		$result = $this->service->send( $this->args() );

		remove_filter( 'wp_insert_post_empty_content', '__return_true' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame(
			array(),
			get_posts(
				array(
					'post_type' => DocumentPostType::POST_TYPE,
					'fields'    => 'ids',
				)
			)
		);
	}

	/**
	 * Send arguments pointing at the checked-in PDF.
	 *
	 * @param array<string, mixed> $overrides Keys to replace.
	 *
	 * @return array<string, mixed>
	 */
	private function args( array $overrides = array() ): array {
		return array_merge(
			array(
				'file_path' => dirname( __DIR__ ) . '/fixtures/sample.pdf',
				'signers'   => array(
					array(
						'name'  => 'Jane Doe',
						'email' => 'jane@example.com',
						'step'  => 1,
					),
				),
				'message'   => 'Please review and sign.',
			),
			$overrides
		);
	}

	/**
	 * Queue the three calls one successful send makes: the signer lookup that finds the
	 * address already on the account, the upload, and the assignment create.
	 *
	 * The estimate that runs between the upload and the assignment is answered by the
	 * `/assignments` fragment queued here, which its URL also contains. A test that needs a
	 * verdict of its own queues `/assignments/estimate-cost` before calling this: the fake
	 * answers with the first queued fragment the URL contains.
	 *
	 * @param string $account Account id receiving the upload.
	 */
	private function fake_successful_send( string $account = self::ACCOUNT_ID ): void {
		$this->fake_response(
			'/accounts/' . $account . '/documents',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'  => 'document',
					'id'        => self::DOCUMENT_ID,
					'name'      => 'sample.pdf',
					'status'    => 'uploaded',
					'artifacts' => array( 'original' => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/download/original' ),
					'is_closed' => false,
					'tags'      => array(),
					'pages'     => array(),
				),
			)
		);

		$this->fake_response(
			'/signers?',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					array(
						'resource'              => 'signer',
						'id'                    => self::SIGNER_ID,
						'full_name'             => 'Jane Doe',
						'email'                 => 'jane@example.com',
						'whatsapp_phone_number' => null,
					),
				),
			)
		);

		$this->fake_response(
			'/assignments',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'     => 'assignment',
					'id'           => self::ASSIGNMENT_ID,
					'method'       => 'virtual',
					'message'      => 'Please review and sign.',
					'signers'      => array(
						array(
							'id'        => self::SIGNER_ID,
							'full_name' => 'Jane Doe',
							'email'     => 'jane@example.com',
							'completed' => false,
							'step'      => 1,
							'notified'  => true,
						),
					),
					'signing_urls' => array(
						array(
							'signer_id' => self::SIGNER_ID,
							'url'       => 'https://app.assinafy.com.br/sign/' . self::DOCUMENT_ID . '?email=jane%40example.com',
						),
					),
				),
			)
		);
	}
}
