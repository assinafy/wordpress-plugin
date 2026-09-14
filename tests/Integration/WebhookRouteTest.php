<?php
/**
 * Webhook endpoint tests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentIndex;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Settings;
use Assinafy\WP\Webhook\Handler;
use Assinafy\WP\Webhook\Route;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Every case here goes through `rest_do_request()`.
 *
 * Calling `Handler::handle()` directly would pass, and would prove nothing: the REST server
 * is the only thing that runs `permission_callback`, so a handler invoked by hand is a
 * handler whose authentication was never asked about. The 403 cases below exist purely to
 * hold that gate in place.
 *
 * @covers \Assinafy\WP\Webhook\Route
 * @covers \Assinafy\WP\Webhook\Handler
 */
final class WebhookRouteTest extends AssinafyTestCase {

	/**
	 * The token the site has issued. Thirty-two alphanumerics, as the route pattern demands.
	 */
	private const TOKEN = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

	/**
	 * A token of the right shape that the site never issued.
	 */
	private const WRONG_TOKEN = 'z9y8x7w6v5u4t3s2r1q0p9o8n7m6l5k4';

	/**
	 * Remote document id used by the fixtures.
	 */
	private const DOCUMENT_ID = '104618b275d321f5de22240ebfda';

	/**
	 * Arm the endpoint and hand the REST server a fresh routing table.
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( Settings::OPTION_WEBHOOK_TOKEN, self::TOKEN, false );
		update_option( Settings::OPTION_WEBHOOK_ENABLED, true );

		$this->configure_plugin();

		// The REST server is a global built once per process. Rebuilding it here re-runs
		// `rest_api_init`, which is where the plugin declares its route.
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
	}

	/**
	 * Drop the rebuilt server so the next class starts from a clean routing table.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;

		parent::tear_down();
	}

	/**
	 * A token that is not the stored one is refused, and nothing downstream runs.
	 */
	public function test_rejects_a_token_the_site_never_issued(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$response = $this->deliver( self::WRONG_TOKEN, $this->envelope() );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'assinafy_webhook_forbidden', $this->error_code( $response ) );
		$this->assertSame( array(), $this->requests, 'A refused delivery must not reach the API.' );
		$this->assertSame( 'pending_signature', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * Switching deliveries off stops the work, not the delivery.
	 *
	 * The option does not deactivate the account's subscription, so an authenticated
	 * delivery still arrives. Refusing it would spend the account's failure budget — shared
	 * with every other site on that account — on a local preference.
	 */
	public function test_acknowledges_a_delivery_while_the_endpoint_is_switched_off(): void {
		update_option( Settings::OPTION_WEBHOOK_ENABLED, false );

		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$response = $this->deliver( self::TOKEN, $this->envelope() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'disabled', $response->get_data()['reason'] );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'pending_signature', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * The stored token authenticates, and the delivery drives a re-fetch.
	 */
	public function test_accepts_a_delivery_carrying_the_stored_token(): void {
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'certificated' );

		$response = $this->deliver( self::TOKEN, $this->envelope() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'handled' => true,
				'reason'  => 'synced',
				'event'   => 'signer_signed_document',
			),
			$response->get_data()
		);
		$this->assertCount( 1, $this->requests );
		$this->assertStringEndsWith( '/v1/documents/' . self::DOCUMENT_ID, $this->requests[0]['url'] );
	}

	/**
	 * The delivery body is a hint, never a source of truth.
	 *
	 * The envelope claims the document is `certificated`; the API says `metadata_ready`.
	 * Deliveries are unsigned, so the API has to win — otherwise anyone who learns the
	 * endpoint URL can dictate what this site believes about a document.
	 */
	public function test_the_delivery_body_never_writes_state_by_itself(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'metadata_ready' );

		$response = $this->deliver(
			self::TOKEN,
			$this->envelope(
				array(
					'object' => array(
						'id'     => self::DOCUMENT_ID,
						'type'   => 'Document',
						'status' => 'certificated',
					),
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'metadata_ready', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * One Assinafy account can serve several sites. A delivery for another account writes
	 * nothing, costs no request, and is still acknowledged.
	 */
	public function test_an_account_mismatch_writes_nothing(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'certificated' );

		$response = $this->deliver(
			self::TOKEN,
			$this->envelope( array( 'account_id' => '104618a0000000000000000002' ) )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'account_mismatch', $response->get_data()['reason'] );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'pending_signature', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * A document this site never sent is acknowledged rather than 404'd.
	 *
	 * Assinafy retries a failed delivery and pauses the account's deliveries after ten
	 * consecutive failures, so answering 404 for someone else's document would eventually
	 * switch off webhooks for every site on the account.
	 */
	public function test_an_unknown_document_still_answers_200(): void {
		$response = $this->deliver(
			self::TOKEN,
			$this->envelope(
				array(
					'object' => array(
						'id'     => '1046180000000000000000ffff',
						'type'   => 'Document',
						'status' => 'certificated',
					),
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'unknown_document', $response->get_data()['reason'] );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 0, ( new DocumentIndex() )->find_by_document_id( '1046180000000000000000ffff' ) );
	}

	/**
	 * The URL is the only thing that authenticates.
	 *
	 * `WP_REST_Request::get_param()` resolves in the order the REST server declares — JSON
	 * body first, then the POST body, then the query string, and only then the URL match — so
	 * a gate reading it would let a delivery nominate its own credential. Both cases below
	 * put the real token in a channel the sender controls and a token this site never issued
	 * in the path.
	 */
	public function test_a_token_in_the_body_cannot_authenticate(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$response = $this->deliver(
			self::WRONG_TOKEN,
			$this->envelope( array( 'token' => self::TOKEN ) )
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'assinafy_webhook_forbidden', $this->error_code( $response ) );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'pending_signature', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * The same, through the query string.
	 */
	public function test_a_token_in_the_query_string_cannot_authenticate(): void {
		$request = new WP_REST_Request( 'POST', '/' . Route::REST_NAMESPACE . '/webhook/' . self::WRONG_TOKEN );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_query_params( array( 'token' => self::TOKEN ) );
		$request->set_body( (string) wp_json_encode( $this->envelope() ) );

		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'assinafy_webhook_forbidden', $this->error_code( $response ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * The route pattern narrows the shape; it does not authenticate.
	 *
	 * `WP_REST_Server::dispatch()` matches routes with a case-insensitive expression, so an
	 * upper-cased token still reaches the handler's gate. `hash_equals()` is case-sensitive,
	 * which is what turns it away.
	 */
	public function test_a_case_variant_of_the_token_is_refused(): void {
		$response = $this->deliver( strtoupper( self::TOKEN ), $this->envelope() );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'assinafy_webhook_forbidden', $this->error_code( $response ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * A token of any other length is not a route at all, so nothing downstream is consulted.
	 *
	 * @param string $token Token to put in the path.
	 *
	 * @dataProvider provide_tokens_of_the_wrong_shape
	 */
	public function test_a_token_of_the_wrong_shape_never_reaches_the_route( string $token ): void {
		$response = $this->deliver( $token, $this->envelope() );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'rest_no_route', $this->error_code( $response ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Paths the endpoint pattern must not match.
	 *
	 * @return array<string, array{string}>
	 */
	public function provide_tokens_of_the_wrong_shape(): array {
		return array(
			'one character short' => array( substr( self::TOKEN, 0, 31 ) ),
			'one character long'  => array( self::TOKEN . 'f' ),
			'empty'               => array( '' ),
			'hyphenated'          => array( str_repeat( 'a', 31 ) . '-' ),
			'path traversal'      => array( str_repeat( 'a', 29 ) . '/..' ),
			'null byte'           => array( substr( self::TOKEN, 0, 31 ) . '%00' ),
		);
	}

	/**
	 * Before the site has issued a token there is no secret, so nothing can match one.
	 */
	public function test_nothing_authenticates_while_the_site_has_no_token(): void {
		update_option( Settings::OPTION_WEBHOOK_TOKEN, '', false );

		$response = $this->deliver( self::TOKEN, $this->envelope() );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'assinafy_webhook_forbidden', $this->error_code( $response ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * The endpoint takes deliveries and nothing else. A read must not reach the handler.
	 */
	public function test_the_endpoint_answers_only_to_post(): void {
		$request  = new WP_REST_Request( 'GET', '/' . Route::REST_NAMESPACE . '/webhook/' . self::TOKEN );
		$response = rest_do_request( $request );

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Rotation issues a token of the shape the route matches, never the same one twice.
	 */
	public function test_a_rotated_token_matches_the_route_pattern_and_never_repeats(): void {
		$seen = array();

		for ( $i = 0; $i < 50; $i++ ) {
			$token = Route::rotate_token();

			$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{' . Route::TOKEN_LENGTH . '}$/', $token );
			$this->assertSame( $token, Route::token(), 'Rotation must store what it returns.' );

			$seen[ $token ] = true;
		}

		$this->assertCount( 50, $seen, 'Two rotations produced the same token.' );
	}

	/**
	 * Rotating shuts the old endpoint immediately.
	 */
	public function test_rotating_the_token_closes_the_old_endpoint(): void {
		$fresh = Route::rotate_token();

		$this->assertSame( 403, $this->deliver( self::TOKEN, $this->envelope() )->get_status() );

		$this->fake_document( 'certificated' );
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$this->assertSame( 200, $this->deliver( $fresh, $this->envelope() )->get_status() );
	}

	/**
	 * A repeated delivery id costs nothing the second time.
	 */
	public function test_a_repeated_delivery_id_is_a_no_op(): void {
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'certificated' );

		$first  = $this->deliver( self::TOKEN, $this->envelope() );
		$second = $this->deliver( self::TOKEN, $this->envelope() );

		$this->assertSame( 'synced', $first->get_data()['reason'] );
		$this->assertSame( 200, $second->get_status() );
		$this->assertSame( 'duplicate', $second->get_data()['reason'] );
		$this->assertCount( 1, $this->requests, 'The replay re-fetched the document.' );
	}

	/**
	 * The marker keys on the delivery, not on the document: a later event about the same
	 * document is new news and must be fetched.
	 */
	public function test_a_later_delivery_about_the_same_document_still_syncs(): void {
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'certificated' );

		$this->deliver( self::TOKEN, $this->envelope() );
		$response = $this->deliver( self::TOKEN, $this->envelope( array( 'id' => 30023 ) ) );

		$this->assertSame( 'synced', $response->get_data()['reason'] );
		$this->assertCount( 2, $this->requests );
	}

	/**
	 * A delivery turned away before it did any work is not remembered.
	 *
	 * `POST /accounts/{id}/webhooks/{historyId}/retry` replays a delivery under its original
	 * id, and that is the documented way to recover the deliveries a site missed while it was
	 * misconfigured. Marking those ids on the way past would make the retry button a no-op
	 * for exactly the deliveries that need it.
	 *
	 * @param array<string, mixed> $overrides Envelope keys that make the delivery a miss.
	 *
	 * @dataProvider provide_deliveries_that_do_no_work
	 */
	public function test_a_delivery_that_did_no_work_is_not_remembered( array $overrides ): void {
		$missed = $this->deliver( self::TOKEN, $this->envelope( $overrides ) );

		$this->assertSame( 200, $missed->get_status() );
		$this->assertNotSame( 'synced', $missed->get_data()['reason'] );
		$this->assertSame( array(), $this->requests );

		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'certificated' );

		$replayed = $this->deliver( self::TOKEN, $this->envelope() );

		$this->assertSame( 'synced', $replayed->get_data()['reason'], 'The replayed delivery was swallowed as a duplicate.' );
		$this->assertCount( 1, $this->requests );
	}

	/**
	 * Envelopes that reach the handler and change nothing, all under delivery id 30022.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public function provide_deliveries_that_do_no_work(): array {
		return array(
			'another account'  => array( array( 'account_id' => '104618a0000000000000000002' ) ),
			'unknown document' => array( array() ),
			'a signer object'  => array(
				array(
					'object' => array(
						'id'   => '104618b9dc17b2b771659e8b1360',
						'type' => 'Signer',
					),
				),
			),
		);
	}

	/**
	 * A transient store that drops the write costs an extra re-fetch and nothing else.
	 *
	 * Deleting the marker between two identical deliveries is what a store that silently
	 * failed looks like from here. The record still ends up carrying what the API reported,
	 * because the re-fetch — not the marker — is what makes a delivery idempotent.
	 */
	public function test_a_lost_replay_marker_costs_a_re_fetch_and_nothing_more(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'certificated' );

		$this->deliver( self::TOKEN, $this->envelope() );
		delete_transient( Handler::DEDUPE_PREFIX . '30022' );
		$response = $this->deliver( self::TOKEN, $this->envelope() );

		$this->assertSame( 'synced', $response->get_data()['reason'] );
		$this->assertCount( 2, $this->requests );
		$this->assertSame( 'certificated', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * Nothing a sender can put in the body produces a 500.
	 *
	 * Two layers answer these: WordPress rejects a body that is not JSON when the delivery
	 * says it is, and the handler rejects everything else that is not an envelope. Both give
	 * a 4xx, neither reaches the API, and neither writes.
	 *
	 * @param string $body         Raw request body.
	 * @param string $content_type Content type the delivery declares.
	 *
	 * @dataProvider provide_bodies_that_are_not_envelopes
	 */
	public function test_a_body_that_is_not_an_envelope_is_refused_without_a_500( string $body, string $content_type = 'application/json' ): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$response = $this->deliver_raw( self::TOKEN, $body, $content_type );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'pending_signature', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * Bodies no delivery would ever carry.
	 *
	 * @return array<string, array{0: string, 1?: string}>
	 */
	public function provide_bodies_that_are_not_envelopes(): array {
		return array(
			'empty'                  => array( '' ),
			'whitespace'             => array( "   \n\t" ),
			'truncated json'         => array( '{"id": 30022, "event":' ),
			'truncated, typed plain' => array( '{"id": 30022, "event":', 'text/plain' ),
			'a bare string'          => array( '"signer_signed_document"' ),
			'a bare number'          => array( '123' ),
			'a json null'            => array( 'null' ),
			'a json list'            => array( '[1, 2, 3]' ),
			'an empty object'        => array( '{}' ),
			'no id'                  => array( '{"event": "document_ready", "account_id": "' . self::ACCOUNT_ID . '"}' ),
			'a null id'              => array( '{"id": null, "event": "document_ready"}' ),
			'a non-numeric id'       => array( '{"id": "30022x", "event": "document_ready"}' ),
			'a float id'             => array( '{"id": 30022.5, "event": "document_ready"}' ),
			'a zero id'              => array( '{"id": 0, "event": "document_ready"}' ),
			'a negative id'          => array( '{"id": -30022, "event": "document_ready"}' ),
			'an array id'            => array( '{"id": [30022], "event": "document_ready"}' ),
			'an object id'           => array( '{"id": {"id": 30022}, "event": "document_ready"}' ),
			'nested past the limit'  => array( str_repeat( '[', 600 ) . str_repeat( ']', 600 ) ),
		);
	}

	/**
	 * A proxy that stringifies numbers must not be able to defeat replay detection.
	 */
	public function test_a_numeric_string_id_is_a_delivery_id(): void {
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'certificated' );

		$first  = $this->deliver( self::TOKEN, $this->envelope( array( 'id' => '30022' ) ) );
		$second = $this->deliver( self::TOKEN, $this->envelope() );

		$this->assertSame( 'synced', $first->get_data()['reason'] );
		$this->assertSame( 'duplicate', $second->get_data()['reason'] );
		$this->assertCount( 1, $this->requests );
	}

	/**
	 * A body far larger than any real delivery is read, not choked on.
	 */
	public function test_an_oversized_body_is_handled(): void {
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'certificated' );

		$response = $this->deliver(
			self::TOKEN,
			$this->envelope( array( 'message' => str_repeat( 'x', 2 * MB_IN_BYTES ) ) )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'synced', $response->get_data()['reason'] );
	}

	/**
	 * An event outside the subscribable fifteen is acknowledged and dropped.
	 *
	 * The three `template_*` names are the interesting case: they exist as SDK constants and
	 * in the published catalog but `GET /webhooks/event-types` does not offer them, so a
	 * delivery carrying one is not something this account subscribed to.
	 *
	 * @param string $event Event name as delivered.
	 *
	 * @dataProvider provide_events_outside_the_vocabulary
	 */
	public function test_an_event_outside_the_vocabulary_writes_nothing( string $event ): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$response = $this->deliver( self::TOKEN, $this->envelope( array( 'event' => $event ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'unknown_event', $response->get_data()['reason'] );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'pending_signature', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * Names that are not subscribable events.
	 *
	 * @return array<string, array{string}>
	 */
	public function provide_events_outside_the_vocabulary(): array {
		return array(
			'template_created'           => array( 'template_created' ),
			'template_processed'         => array( 'template_processed' ),
			'template_processing_failed' => array( 'template_processing_failed' ),
			'invented'                   => array( 'document_deleted' ),
			'empty'                      => array( '' ),
			'a near miss'                => array( 'Document_Ready' ),
			'padded'                     => array( ' document_ready ' ),
		);
	}

	/**
	 * Every event `GET /webhooks/event-types` offers reaches the re-fetch.
	 *
	 * Fifteen deliveries, fifteen distinct ids, fifteen documents read back. A vocabulary
	 * that drifts from the platform's shows up here as a delivery the plugin silently drops.
	 */
	public function test_every_subscribable_event_drives_a_re_fetch(): void {
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'certificated' );

		$this->assertCount( 15, Route::EVENTS );

		foreach ( Route::EVENTS as $index => $event ) {
			$response = $this->deliver(
				self::TOKEN,
				$this->envelope(
					array(
						'id'    => 40000 + $index,
						'event' => $event,
					)
				)
			);

			$this->assertSame( 'synced', $response->get_data()['reason'], $event . ' was not accepted.' );
		}

		$this->assertCount( 15, $this->requests );
	}

	/**
	 * A delivery arriving before the site knows its own account writes nothing.
	 */
	public function test_a_delivery_before_the_account_is_configured_writes_nothing(): void {
		delete_option( Settings::OPTION_ACCOUNT_ID );
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$response = $this->deliver( self::TOKEN, $this->envelope() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'account_mismatch', $response->get_data()['reason'] );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'pending_signature', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * An `account_id` that is present but not a string is a mismatch, not a match.
	 *
	 * @param mixed $account_id Value delivered under `account_id`.
	 *
	 * @dataProvider provide_account_ids_that_are_not_this_site
	 */
	public function test_an_account_id_that_is_not_this_site_writes_nothing( mixed $account_id ): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$response = $this->deliver( self::TOKEN, $this->envelope( array( 'account_id' => $account_id ) ) );

		$this->assertSame( 'account_mismatch', $response->get_data()['reason'] );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'pending_signature', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * Account ids no delivery for this site would carry.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function provide_account_ids_that_are_not_this_site(): array {
		return array(
			'another account' => array( '104618a0000000000000000002' ),
			'empty'           => array( '' ),
			'null'            => array( null ),
			'a number'        => array( 104618 ),
			'a list'          => array( array( '104618a0000000000000000001' ) ),
			'a prefix'        => array( substr( self::ACCOUNT_ID, 0, 20 ) ),
			'a suffix'        => array( self::ACCOUNT_ID . '2' ),
			'case flipped'    => array( strtoupper( self::ACCOUNT_ID ) ),
		);
	}

	/**
	 * A re-fetch that fails is reported as a failure, and the record keeps what it had.
	 *
	 * Still 200: Assinafy pauses an account's deliveries after ten consecutive failures, and
	 * the hourly reconcile run recovers this record without spending that budget. The
	 * delivery is not remembered either — it did no work, and pressing Retry once the API is
	 * back has to re-fetch rather than be answered `duplicate`.
	 */
	public function test_a_failed_re_fetch_is_reported_rather_than_claimed_as_a_sync(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			500,
			array(
				'status'  => 500,
				'message' => 'Erro interno.',
				'data'    => null,
			)
		);

		$response = $this->deliver( self::TOKEN, $this->envelope() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'handled' => false,
				'reason'  => 'sync_failed',
				'event'   => 'signer_signed_document',
			),
			$response->get_data()
		);
		$this->assertSame( 'pending_signature', ( new DocumentRecord() )->status( $post_id ) );

		$this->fake_document( 'certificated' );

		$replayed = $this->deliver( self::TOKEN, $this->envelope() );

		$this->assertSame( 'synced', $replayed->get_data()['reason'], 'The replayed delivery was swallowed as a duplicate.' );
		$this->assertSame( 'certificated', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * A document id that is not a string, or not an id, finds no record and writes nothing.
	 *
	 * @param mixed $document_id Value delivered under `object.id`.
	 *
	 * @dataProvider provide_document_ids_that_match_nothing
	 */
	public function test_a_document_id_that_matches_nothing_writes_nothing( mixed $document_id ): void {
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$response = $this->deliver(
			self::TOKEN,
			$this->envelope(
				array(
					'object' => array(
						'id'     => $document_id,
						'type'   => 'Document',
						'status' => 'certificated',
					),
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'unknown_document', $response->get_data()['reason'] );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Document ids that cannot address a local record.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function provide_document_ids_that_match_nothing(): array {
		return array(
			'absent'         => array( null ),
			'empty'          => array( '' ),
			'a number'       => array( 104618 ),
			'a list'         => array( array( self::DOCUMENT_ID ) ),
			'a sql fragment' => array( "' OR 1=1 -- " ),
			'a wildcard'     => array( str_repeat( '%', 28 ) ),
			'enormous'       => array( str_repeat( 'a', 100000 ) ),
		);
	}

	/**
	 * The `object` key is polymorphic. Only a Document is worth re-fetching.
	 *
	 * @param mixed $entity Value delivered under `object`.
	 *
	 * @dataProvider provide_objects_that_are_not_documents
	 */
	public function test_an_object_that_is_not_a_document_is_acknowledged_without_a_re_fetch( mixed $entity ): void {
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$response = $this->deliver( self::TOKEN, $this->envelope( array( 'object' => $entity ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'not_a_document', $response->get_data()['reason'] );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Entities that carry nothing to re-read.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function provide_objects_that_are_not_documents(): array {
		return array(
			'a signer'  => array(
				array(
					'id'   => '104618b9dc17b2b771659e8b1360',
					'type' => 'Signer',
				),
			),
			'a user'    => array(
				array(
					'id'   => '104618b9dc17b2b771659e8b1361',
					'type' => 'User',
				),
			),
			'absent'    => array( null ),
			'empty'     => array( array() ),
			'untyped'   => array( array( 'id' => self::DOCUMENT_ID ) ),
			'a string'  => array( 'Document' ),
			'typed odd' => array(
				array(
					'id'   => self::DOCUMENT_ID,
					'type' => array( 'Document' ),
				),
			),
		);
	}

	/**
	 * Casing on `object.type` is the platform's, not a contract. `DOCUMENT` is a Document.
	 */
	public function test_the_object_type_is_matched_without_regard_to_case(): void {
		$this->create_document( self::DOCUMENT_ID, 'pending_signature' );
		$this->fake_document( 'certificated' );

		$response = $this->deliver(
			self::TOKEN,
			$this->envelope(
				array(
					'object' => array(
						'id'   => self::DOCUMENT_ID,
						'type' => 'DOCUMENT',
					),
				)
			)
		);

		$this->assertSame( 'synced', $response->get_data()['reason'] );
	}

	/**
	 * POST one delivery to the endpoint with a body given verbatim.
	 *
	 * @param string $token        Token to put in the URL.
	 * @param string $body         Raw request body.
	 * @param string $content_type Content type the delivery declares.
	 */
	private function deliver_raw( string $token, string $body, string $content_type = 'application/json' ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/' . Route::REST_NAMESPACE . '/webhook/' . $token );
		$request->set_header( 'content-type', $content_type );
		$request->set_body( $body );

		return rest_do_request( $request );
	}

	/**
	 * POST one delivery to the endpoint.
	 *
	 * @param string               $token    Token to put in the URL.
	 * @param array<string, mixed> $envelope Delivery envelope.
	 */
	private function deliver( string $token, array $envelope ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/' . Route::REST_NAMESPACE . '/webhook/' . $token );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $envelope ) );

		return rest_do_request( $request );
	}

	/**
	 * A delivery envelope, verbatim from the shape the platform sends.
	 *
	 * @param array<string, mixed> $overrides Keys to replace.
	 *
	 * @return array<string, mixed>
	 */
	private function envelope( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'         => 30022,
				'event'      => 'signer_signed_document',
				'message'    => 'Signer signed the document',
				'payload'    => array( 'signer_full_name' => 'Jane Doe' ),
				'origin'     => array(
					'ip'         => '203.0.113.10',
					'user-agent' => 'Assinafy/1.0',
				),
				'created_at' => 1789668256,
				'subject'    => array(
					'id'   => '104618b9dc17b2b771659e8b1360',
					'type' => 'Signer',
				),
				'object'     => array(
					'id'     => self::DOCUMENT_ID,
					'type'   => 'Document',
					'status' => 'certificated',
				),
				'account_id' => self::ACCOUNT_ID,
			),
			$overrides
		);
	}

	/**
	 * Queue the `GET /documents/{id}` the handler's re-fetch will make.
	 *
	 * @param string $status Status the API reports.
	 */
	private function fake_document( string $status ): void {
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'   => 'document',
					'id'         => self::DOCUMENT_ID,
					'name'       => 'contract.pdf',
					'status'     => $status,
					'is_closed'  => 'certificated' === $status,
					'artifacts'  => array(
						'original'  => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/download/original',
						'thumbnail' => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/thumbnail',
					),
					'pages'      => array(),
					'tags'       => array(),
					'assignment' => null,
				),
			)
		);
	}

	/**
	 * The error code carried by a REST error response.
	 *
	 * @param WP_REST_Response $response Dispatched response.
	 */
	private function error_code( WP_REST_Response $response ): string {
		$data = $response->get_data();

		return is_array( $data ) && is_string( $data['code'] ?? null ) ? $data['code'] : '';
	}
}
