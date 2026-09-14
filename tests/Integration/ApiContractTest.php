<?php
/**
 * Request/response contract for every Assinafy endpoint the plugin calls.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\CostEstimate;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Log;

/**
 * One test per SDK call the plugin makes, pinning the wire contract in both directions.
 *
 * Each test asserts the method, the exact URI the transport builds onto the configured base
 * URL — account segment and query string included — the request body field by field, and the
 * local state the real success response produces. The response bodies are the captures from
 * the live sandbox in `probe-documents.md`, `probe-signatures.md` and `probe-templates.md`,
 * with addresses replaced by `example.com`.
 *
 * The fake answers an unqueued request with a 500 naming the URL, so a call that addresses a
 * path this file did not queue fails rather than passing on a response it was never given.
 *
 * @covers \Assinafy\WP\Http\WpHttpClient
 */
final class ApiContractTest extends AssinafyTestCase {

	/**
	 * Document id from the live capture in `probe-documents.md` §5.
	 */
	private const DOCUMENT_ID = '104618b275d321f5de22240ebfda';

	/**
	 * Assignment id from the end-to-end capture in `probe-signatures.md` §3.
	 */
	private const ASSIGNMENT_ID = '1a09c15990f0144256b98ff38aa';

	/**
	 * Signer id from the same capture.
	 */
	private const SIGNER_ID = '19e6b92e7895332ed9708535d8c';

	/**
	 * The eleven status codes, verbatim and in the order `GET /documents/statuses` returns.
	 *
	 * @var array<int, array{code: string, deletable: bool}>
	 */
	private const STATUS_ROWS = array(
		array(
			'code'      => 'uploading',
			'deletable' => false,
		),
		array(
			'code'      => 'uploaded',
			'deletable' => false,
		),
		array(
			'code'      => 'metadata_processing',
			'deletable' => false,
		),
		array(
			'code'      => 'metadata_ready',
			'deletable' => true,
		),
		array(
			'code'      => 'expired',
			'deletable' => true,
		),
		array(
			'code'      => 'certificating',
			'deletable' => false,
		),
		array(
			'code'      => 'certificated',
			'deletable' => false,
		),
		array(
			'code'      => 'rejected_by_signer',
			'deletable' => true,
		),
		array(
			'code'      => 'pending_signature',
			'deletable' => true,
		),
		array(
			'code'      => 'rejected_by_user',
			'deletable' => true,
		),
		array(
			'code'      => 'failed',
			'deletable' => true,
		),
	);

	/**
	 * Give every test credentials and a client built the way the plugin builds one.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->configure_plugin();
	}

	/**
	 * `GET /accounts/{accountId}` — the Test Connection call.
	 *
	 * Account-scoped through the path, no query, no body. The response is a single item, so
	 * the SDK hands back the unwrapped `data`.
	 */
	public function test_accounts_get(): void {
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'id'              => self::ACCOUNT_ID,
					'name'            => 'Acme Inc.',
					'primary_color'   => null,
					'secondary_color' => null,
					'created_at'      => '2026-05-12T18:05:11Z',
				),
			)
		);

		$account = $this->client()->accounts()->get();

		$args = $this->assert_request( 'GET', 'accounts/' . self::ACCOUNT_ID );
		$this->assertNull( $args['body'], 'A read carries no request body.' );
		$this->assertSame( 'Acme Inc.', $account['name'] );
		$this->assertSame( self::ACCOUNT_ID, $account['id'] );
		$this->assertArrayNotHasKey( 'status', $account, 'A single-item method returns the unwrapped data.' );
	}

	/**
	 * `GET /documents/{documentId}` — the read every sync and every post-action resync makes.
	 *
	 * Not account-scoped: the document id alone addresses it. The capture is the one in
	 * `probe-documents.md` §5, extended with the expanded `assignment` that route returns
	 * once one exists, because that is the only shape the mirror hydrates from.
	 */
	public function test_documents_get(): void {
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => $this->document_payload(),
			)
		);

		$post_id = $this->create_document( self::DOCUMENT_ID, 'uploaded', array( 'original' ) );

		$document = $this->client()->documents()->get( self::DOCUMENT_ID );

		$this->assert_request( 'GET', 'documents/' . self::DOCUMENT_ID );

		$records = new DocumentRecord();
		$records->hydrate_from_api( $post_id, $document );

		$this->assertSame( 'pending_signature', $records->status( $post_id ) );
		$this->assertFalse( $records->is_closed( $post_id ) );
		$this->assertSame( array( 'original', 'thumbnail' ), $records->artifacts( $post_id ) );
		$this->assertSame( self::ASSIGNMENT_ID, $records->assignment_id( $post_id ) );
		$this->assertSame( 'test-3page.pdf', get_post_field( 'post_title', $post_id ) );

		$signers = $records->signers( $post_id );
		$this->assertCount( 1, $signers );
		$this->assertSame( self::SIGNER_ID, $signers[0]['id'] );
		$this->assertSame( 'jane@example.com', $signers[0]['email'] );
	}

	/**
	 * `GET /documents/statuses` — the deletable-status vocabulary.
	 *
	 * Account-independent and unpaginated: eleven rows, in the order the API returns them.
	 */
	public function test_documents_statuses(): void {
		$this->fake_response(
			'/documents/statuses',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => self::STATUS_ROWS,
			)
		);

		$statuses = $this->client()->documents()->statuses();

		$this->assert_request( 'GET', 'documents/statuses' );
		$this->assertCount( 11, $statuses );

		$deletable = array();
		foreach ( $statuses as $row ) {
			$deletable[ $row['code'] ] = $row['deletable'];
		}

		$this->assertTrue( $deletable['pending_signature'] );
		$this->assertFalse( $deletable['certificated'], 'A certificated document can never be deleted.' );
		$this->assertFalse( $deletable['uploaded'] );
	}

	/**
	 * `PATCH /documents/{documentId}` — rename.
	 *
	 * One key, `name`, as JSON. The response omits `pages` and `assignment`, and carries the
	 * server's normalised name, which the mirror follows rather than keeping the one sent.
	 */
	public function test_documents_rename(): void {
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'       => 'document',
					'id'             => self::DOCUMENT_ID,
					'account_id'     => self::ACCOUNT_ID,
					'template_id'    => null,
					'name'           => 'Contrato Acao - Servico -1 -2026-.pdf',
					'status'         => 'metadata_ready',
					'artifacts'      => array(
						'original'  => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/download/original',
						'thumbnail' => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/thumbnail',
					),
					'is_closed'      => false,
					'signing_url'    => 'https://app.assinafy.com.br/sign/' . self::DOCUMENT_ID,
					'decline_reason' => null,
					'declined_by'    => null,
					'tags'           => array(),
					'created_at'     => '2026-09-13T18:40:10Z',
					'updated_at'     => '2026-09-13T18:43:30Z',
				),
			)
		);

		$post_id = $this->create_document( self::DOCUMENT_ID, 'metadata_ready' );

		$renamed = $this->client()->documents()->rename( self::DOCUMENT_ID, 'Contrato Ação & Serviço #1 (2026).pdf' );

		$args = $this->assert_request( 'PATCH', 'documents/' . self::DOCUMENT_ID );
		$this->assertSame( 'application/json', $args['headers']['Content-Type'] );
		$this->assertSame( array( 'name' => 'Contrato Ação & Serviço #1 (2026).pdf' ), $this->body() );

		// The API folds diacritics and replaces unsupported characters, so the stored name is
		// the server's, not the one that was sent.
		$this->assertSame( 'Contrato Acao - Servico -1 -2026-.pdf', $renamed['name'] );

		( new DocumentRecord() )->hydrate_from_api( $post_id, $renamed );
		$this->assertSame( 'Contrato Acao - Servico -1 -2026-.pdf', get_post_field( 'post_title', $post_id ) );
	}

	/**
	 * `GET /documents/{documentId}/download/{artifactName}` — the artifact bytes.
	 *
	 * The only call whose response is not the JSON envelope: `application/pdf` bytes, served
	 * inline by the API rather than redirected to storage. The SDK returns the body verbatim.
	 */
	public function test_documents_download(): void {
		$pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

		$this->fake_raw_response(
			'/documents/' . self::DOCUMENT_ID . '/download/original',
			200,
			$pdf,
			array(
				'content-type'        => 'application/pdf',
				'content-disposition' => 'attachment; filename="test-3page.pdf"',
			)
		);

		$bytes = $this->client()->documents()->download( self::DOCUMENT_ID, 'original' );

		$this->assert_request( 'GET', 'documents/' . self::DOCUMENT_ID . '/download/original' );
		$this->assertSame( $pdf, $bytes, 'The artifact body is returned byte for byte.' );
	}

	/**
	 * `DELETE /documents/{documentId}` — the only cancel the document API offers.
	 *
	 * No body on the way out, and `data` comes back as an empty JSON array rather than an
	 * object or null. This is the one single-item method that returns the raw envelope
	 * instead of the unwrapped `data`, so the success check is `status`, not emptiness.
	 *
	 * The delete is hard and not idempotent: a second one answers the same 404 as an id that
	 * never existed, which the cancel handler treats as the success it describes.
	 */
	public function test_documents_delete(): void {
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(),
			)
		);

		$result = $this->client()->documents()->delete( self::DOCUMENT_ID );

		$args = $this->assert_request( 'DELETE', 'documents/' . self::DOCUMENT_ID );
		$this->assertNull( $args['body'], 'The delete is addressed entirely through the path.' );
		$this->assertSame( 200, $result['status'] );
		$this->assertSame( array(), $result['data'], '`data` is an empty array, not an object or null.' );
	}

	/**
	 * `GET /documents/{documentId}/activities` — the history panel.
	 *
	 * Newest first, no pagination at all, and `payload` arrives as a JSON array when the
	 * event carries no keys and as an object when it does. The panel reads it with defaults
	 * for exactly that reason.
	 */
	public function test_documents_activities(): void {
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID . '/activities',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					array(
						'id'         => 17172,
						'event'      => 'signature_requested',
						'message'    => 'Solicitação para assinar enviada para Jane Doe <jane@example.com>.',
						'payload'    => array(
							'signer_email'                 => 'jane@example.com',
							'signer_full_name'             => 'Jane Doe',
							'notification_method'          => 'email',
							'signer_whatsapp_phone_number' => null,
						),
						'origin'     => null,
						'created_at' => '2026-07-28T19:33:56Z',
					),
					array(
						'id'         => 17168,
						'event'      => 'document_uploaded',
						'message'    => 'Documento criado.',
						'payload'    => array(),
						'origin'     => array(
							'ip'         => '203.0.113.20',
							'user-agent' => null,
						),
						'created_at' => '2026-07-28T19:33:50Z',
					),
				),
			)
		);

		$activities = $this->client()->documents()->activities( self::DOCUMENT_ID );

		$this->assert_request( 'GET', 'documents/' . self::DOCUMENT_ID . '/activities' );
		$this->assertCount( 2, $activities );
		$this->assertSame( 17172, $activities[0]['id'], 'Activities arrive newest first.' );
		$this->assertSame( 'jane@example.com', $activities[0]['payload']['signer_email'] );
		$this->assertSame( array(), $activities[1]['payload'], 'An event with no payload keys sends a JSON array.' );
		$this->assertNull( $activities[0]['origin'], 'A system-generated event has no origin.' );
	}

	/**
	 * `POST /documents/{documentId}/assignments/estimate-cost` — the pre-flight price.
	 *
	 * Signer ids are not sent: the estimate is priced from the method mix alone, so the
	 * normalised rows lose their name and address on the way out and keep only the methods.
	 * The refusal arrives as HTTP 200 with `has_sufficient_resources: false`, so the verdict
	 * is read from the body and never from the status code.
	 */
	public function test_assignments_estimate_cost(): void {
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID . '/assignments/estimate-cost',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'documents'                => 1,
					'credits'                  => 0.45,
					'needs_extra_document'     => false,
					'extra_document_cost'      => 0,
					'total_credits'            => 0.45,
					'breakdown'                => array(
						array(
							'code'      => 'NotificationWhatsapp',
							'name'      => 'WhatsApp Notification',
							'cost'      => 0.45,
							'quantity'  => 1,
							'unit_cost' => 0.45,
						),
					),
					'document_balance'         => 480,
					'credit_balance'           => 0,
					'has_sufficient_resources' => false,
					'blocking_reason'          => 'InsufficientCredits',
					'message'                  => 'A conta não possui créditos suficientes.',
				),
			)
		);

		$estimate = new CostEstimate( new ClientFactory( new Credentials(), new Log() ), new Log() );

		$verdict = $estimate->for_signers(
			array(
				array(
					'name'  => 'Jane Doe',
					'phone' => '+5548999990000',
				),
			),
			self::DOCUMENT_ID
		);

		$args = $this->assert_request( 'POST', 'documents/' . self::DOCUMENT_ID . '/assignments/estimate-cost' );
		$this->assertSame( 'application/json', $args['headers']['Content-Type'] );
		$this->assertSame(
			array(
				'method'  => 'virtual',
				'signers' => array(
					array(
						'verification_method'  => 'Whatsapp',
						'notification_methods' => array( 'Whatsapp' ),
					),
				),
			),
			$this->body(),
			'The estimate carries the method mix and nothing that identifies a signer.'
		);

		$this->assertInstanceOf( \WP_Error::class, $verdict );
		$this->assertSame( 'assinafy_insufficient_resources', $verdict->get_error_code() );
	}

	/**
	 * `PUT /documents/{d}/assignments/{a}/signers/{s}/resend` — resend one invitation.
	 *
	 * Everything is addressed through the path; there is no request body. The response is a
	 * delivery receipt, not a signer or an assignment, which is why the panel re-reads the
	 * document afterwards instead of patching itself from this.
	 */
	public function test_assignments_resend(): void {
		$path = 'documents/' . self::DOCUMENT_ID . '/assignments/' . self::ASSIGNMENT_ID
			. '/signers/' . self::SIGNER_ID . '/resend';

		$this->fake_response(
			'/' . $path,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'is_sent'     => true,
					'document_id' => self::DOCUMENT_ID,
					'signer_id'   => self::SIGNER_ID,
				),
			)
		);

		$receipt = $this->client()->assignments()->resend( self::DOCUMENT_ID, self::ASSIGNMENT_ID, self::SIGNER_ID );

		$args = $this->assert_request( 'PUT', $path );
		$this->assertNull( $args['body'], 'The resend is addressed entirely through the path.' );
		$this->assertArrayNotHasKey( 'Content-Type', $args['headers'] );
		$this->assertSame( 'test-api-key', $args['headers']['X-Api-Key'] );
		$this->assertTrue( $receipt['is_sent'] );
		$this->assertSame( self::SIGNER_ID, $receipt['signer_id'] );
	}

	/**
	 * `PUT /documents/{d}/assignments/{a}/reset-expiration` — move the deadline.
	 *
	 * One key, `expires_at`, as an ISO 8601 instant. The response is the whole assignment
	 * with the new deadline on it.
	 */
	public function test_assignments_reset_expiration(): void {
		$path = 'documents/' . self::DOCUMENT_ID . '/assignments/' . self::ASSIGNMENT_ID . '/reset-expiration';

		$this->fake_response(
			'/' . $path,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'   => 'assignment',
					'id'         => self::ASSIGNMENT_ID,
					'method'     => 'virtual',
					'expires_at' => '2027-06-30T12:00:00Z',
					'message'    => 'Please review and sign.',
					'signers'    => array(),
				),
			)
		);

		$assignment = $this->client()->assignments()->resetExpiration(
			self::DOCUMENT_ID,
			self::ASSIGNMENT_ID,
			'2027-06-30T12:00:00Z'
		);

		$this->assert_request( 'PUT', $path );
		$this->assertSame( array( 'expires_at' => '2027-06-30T12:00:00Z' ), $this->body() );
		$this->assertSame( '2027-06-30T12:00:00Z', $assignment['expires_at'] );
	}

	/**
	 * `GET /accounts/{accountId}/webhooks/subscriptions` — read the current subscription.
	 *
	 * The subscription is account-wide and singular, and the response carries no secret of
	 * any kind: deliveries are unsigned.
	 */
	public function test_webhooks_get(): void {
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/webhooks/subscriptions',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'events'     => array( 'document_ready', 'signer_signed_document', 'signer_rejected_document' ),
					'is_active'  => false,
					'url'        => 'https://example.com/hooks/assinafy',
					'email'      => 'ops@example.com',
					'updated_at' => '2026-08-27T17:55:12Z',
				),
			)
		);

		$subscription = $this->client()->webhooks()->get();

		$this->assert_request( 'GET', 'accounts/' . self::ACCOUNT_ID . '/webhooks/subscriptions' );
		$this->assertIsArray( $subscription );
		$this->assertSame( array( 'events', 'is_active', 'url', 'email', 'updated_at' ), array_keys( $subscription ) );
		$this->assertFalse( $subscription['is_active'] );
	}

	/**
	 * The same route answering an account that never configured a subscription.
	 *
	 * `data: null` becomes an empty array in `extractData()`, and the SDK turns that into
	 * `null` so absence is one check rather than two.
	 */
	public function test_webhooks_get_without_a_subscription(): void {
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/webhooks/subscriptions',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => null,
			)
		);

		$this->assertNull( $this->client()->webhooks()->get() );
		$this->assert_request( 'GET', 'accounts/' . self::ACCOUNT_ID . '/webhooks/subscriptions' );
	}

	/**
	 * `PUT /accounts/{accountId}/webhooks/subscriptions` — point the account at this site.
	 *
	 * The upsert is wholesale: exactly four keys, every one sent every time. There is no
	 * secret field, which is why the route defends itself with an unguessable token instead.
	 */
	public function test_webhooks_register(): void {
		$events = array( 'document_ready', 'signer_signed_document' );

		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/webhooks/subscriptions',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'events'     => $events,
					'is_active'  => true,
					'url'        => 'https://example.test/wp-json/assinafy/v1/webhook/abc',
					'email'      => 'ops@example.com',
					'updated_at' => '2026-08-27T17:55:12Z',
				),
			)
		);

		$stored = $this->client()->webhooks()->register(
			'https://example.test/wp-json/assinafy/v1/webhook/abc',
			'ops@example.com',
			$events,
			true
		);

		$this->assert_request( 'PUT', 'accounts/' . self::ACCOUNT_ID . '/webhooks/subscriptions' );
		$this->assertSame(
			array(
				'url'       => 'https://example.test/wp-json/assinafy/v1/webhook/abc',
				'email'     => 'ops@example.com',
				'events'    => $events,
				'is_active' => true,
			),
			$this->body()
		);
		$this->assertTrue( $stored['is_active'] );
	}

	/**
	 * `PUT /accounts/{accountId}/webhooks/inactivate` — stop deliveries.
	 *
	 * No body, and no `DELETE` route exists: the url, email and event list stay on file so
	 * the same subscription can be switched back on.
	 */
	public function test_webhooks_deactivate(): void {
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/webhooks/inactivate',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'events'     => array( 'document_ready' ),
					'is_active'  => false,
					'url'        => 'https://example.test/wp-json/assinafy/v1/webhook/abc',
					'email'      => 'ops@example.com',
					'updated_at' => '2026-08-27T18:01:40Z',
				),
			)
		);

		$stored = $this->client()->webhooks()->deactivate();

		$args = $this->assert_request( 'PUT', 'accounts/' . self::ACCOUNT_ID . '/webhooks/inactivate' );
		$this->assertNull( $args['body'], 'Inactivating sends no body.' );
		$this->assertFalse( $stored['is_active'] );
		$this->assertSame( 'https://example.test/wp-json/assinafy/v1/webhook/abc', $stored['url'] );
	}

	/**
	 * `AssinafyClient::uploadAndRequestSignatures()` — the three calls one send makes.
	 *
	 * The upload is `multipart/form-data` with the file part named exactly `file`; any other
	 * part name answers 400. The signer is then resolved by address, and the assignment is
	 * created while the document is still `uploaded`, which the API allows — it promotes the
	 * document to `pending_signature` itself once page rendering finishes.
	 */
	public function test_upload_and_request_signatures(): void {
		$this->fake_upload_flow();

		$result = $this->client()->uploadAndRequestSignatures(
			dirname( __DIR__ ) . '/fixtures/sample.pdf',
			array(
				array(
					'verification_method'  => 'Email',
					'notification_methods' => array( 'Email' ),
					'full_name'            => 'Jane Doe',
					'email'                => 'jane@example.com',
					'step'                 => 1,
				),
			),
			'Please review and sign.',
			'2026-12-31T23:59:59Z',
			false
		);

		$this->assertCount( 3, $this->requests );

		$upload = $this->requests[0];
		$this->assertSame( 'POST', $upload['args']['method'] );
		$this->assertSame( self::API_HOST . 'v1/accounts/' . self::ACCOUNT_ID . '/documents', $upload['url'] );
		$this->assertStringStartsWith( 'multipart/form-data; boundary=', $upload['args']['headers']['Content-Type'] );
		$this->assertStringContainsString(
			'Content-Disposition: form-data; name="file"; filename="sample.pdf"',
			$upload['args']['body']
		);
		$this->assertStringContainsString( 'Content-Type: application/pdf', $upload['args']['body'] );

		$lookup = $this->requests[1];
		$this->assertSame( 'GET', $lookup['args']['method'] );
		$this->assertSame(
			self::API_HOST . 'v1/accounts/' . self::ACCOUNT_ID . '/signers?search=jane%40example.com&page=1&per-page=100',
			$lookup['url']
		);

		$create = $this->requests[2];
		$this->assertSame( 'POST', $create['args']['method'] );
		$this->assertSame( self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/assignments', $create['url'] );
		$this->assertSame(
			array(
				'method'     => 'virtual',
				'signers'    => array(
					array(
						'id'                   => self::SIGNER_ID,
						'verification_method'  => 'Email',
						'notification_methods' => array( 'Email' ),
						'step'                 => 1,
					),
				),
				'message'    => 'Please review and sign.',
				'expires_at' => '2026-12-31T23:59:59Z',
			),
			(array) json_decode( (string) $create['args']['body'], true )
		);

		$this->assertSame( self::DOCUMENT_ID, $result['document']['id'] );
		$this->assertSame( 'uploaded', $result['document']['status'] );
		$this->assertSame( self::ASSIGNMENT_ID, $result['assignment']['id'] );
		$this->assertSame( array( self::SIGNER_ID ), $result['signer_ids'] );
	}


	/**
	 * The client the plugin builds for itself, with the fake transport underneath it.
	 */
	private function client(): AssinafyClient {
		$client = ( new ClientFactory( new Credentials(), new Log() ) )->client();

		$this->assertInstanceOf( AssinafyClient::class, $client );

		return $client;
	}

	/**
	 * Assert the one request made so far, and hand back its arguments.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path relative to the `/v1` base, query string included.
	 *
	 * @return array<string, mixed> The transport arguments.
	 */
	private function assert_request( string $method, string $path ): array {
		$this->assertCount( 1, $this->requests );
		$this->assertSame( $method, $this->requests[0]['args']['method'] );
		$this->assertSame( self::API_HOST . 'v1/' . $path, $this->requests[0]['url'] );
		$this->assertSame( 'test-api-key', $this->requests[0]['args']['headers']['X-Api-Key'] );
		$this->assertSame( 0, $this->requests[0]['args']['redirection'], 'A credentialled request never follows a redirect.' );

		return $this->requests[0]['args'];
	}

	/**
	 * The decoded JSON body of the one request made so far.
	 *
	 * @return array<string, mixed>
	 */
	private function body(): array {
		return (array) json_decode( (string) $this->requests[0]['args']['body'], true );
	}

	/**
	 * `GET /documents/{id}` as the live API answers it once an assignment exists.
	 *
	 * @return array<string, mixed>
	 */
	private function document_payload(): array {
		return array(
			'resource'       => 'document',
			'id'             => self::DOCUMENT_ID,
			'account_id'     => self::ACCOUNT_ID,
			'template_id'    => null,
			'name'           => 'test-3page.pdf',
			'status'         => 'pending_signature',
			'artifacts'      => array(
				'original'  => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/download/original',
				'thumbnail' => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/thumbnail',
			),
			'is_closed'      => false,
			'signing_url'    => 'https://app.assinafy.com.br/sign/' . self::DOCUMENT_ID,
			'decline_reason' => null,
			'declined_by'    => null,
			'tags'           => array(),
			'created_at'     => '2026-09-13T18:40:10Z',
			'updated_at'     => '2026-09-13T18:44:16Z',
			'assignment'     => array(
				'id'           => self::ASSIGNMENT_ID,
				'method'       => 'virtual',
				'message'      => 'Please review and sign.',
				'sender_email' => 'owner@example.com',
				'expires_at'   => '2026-12-31T23:59:59Z',
				'signers'      => array(
					array(
						'id'                    => self::SIGNER_ID,
						'full_name'             => 'Jane Doe',
						'email'                 => 'jane@example.com',
						'whatsapp_phone_number' => null,
						'completed'             => false,
						'step'                  => 1,
						'notified'              => true,
					),
				),
				'signing_urls' => array(
					array(
						'signer_id' => self::SIGNER_ID,
						'url'       => 'https://app.assinafy.com.br/sign/' . self::DOCUMENT_ID . '?email=jane%40example.com',
					),
				),
				'summary'      => array(
					'signer_count'    => 1,
					'completed_count' => 0,
				),
			),
			'pages'          => array(
				array(
					'id'           => '104618b2ac6a3b2f99d0fd2c5f43',
					'number'       => 1,
					'height'       => 1755,
					'width'        => 1240,
					'download_url' => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/pages/104618b2ac6a3b2f99d0fd2c5f43/download',
				),
			),
		);
	}

	/**
	 * Queue the upload, the signer lookup and the assignment create.
	 */
	private function fake_upload_flow(): void {
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/documents',
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
						'has_accepted_terms'    => true,
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
					'sender_email' => 'owner@example.com',
					'method'       => 'virtual',
					'expires_at'   => '2026-12-31T23:59:59Z',
					'message'      => 'Please review and sign.',
					'signers'      => array(
						array(
							'id'                   => self::SIGNER_ID,
							'full_name'            => 'Jane Doe',
							'email'                => 'jane@example.com',
							'completed'            => false,
							'verification_method'  => 'Email',
							'notification_methods' => array( 'Email' ),
							'step'                 => 1,
							'notified'             => true,
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
