<?php
/**
 * Download proxy tests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DownloadProxy;
use WPDieException;

/**
 * The proxy is the only thing standing between a browser and an artifact URL that carries
 * the account API key, so each of its refusals is worth pinning down: the caller who may not
 * view the record, the request that carries no valid nonce, and the artifact name the record
 * does not have.
 *
 * @covers \Assinafy\WP\Documents\DownloadProxy
 */
final class DownloadProxyTest extends AssinafyTestCase {

	/**
	 * Remote document id used by the fixtures.
	 */
	private const DOCUMENT_ID = '104618b275d321f5de22240ebfda';

	/**
	 * Proxy under test.
	 */
	private DownloadProxy $proxy;

	/**
	 * Make `wp_die()` throw instead of ending the process.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->configure_plugin();

		$this->proxy = new DownloadProxy(
			new \Assinafy\WP\ClientFactory( new \Assinafy\WP\Credentials(), new \Assinafy\WP\Log() ),
			new \Assinafy\WP\Documents\DocumentRecord()
		);

		add_filter( 'wp_die_handler', array( $this, 'throwing_die_handler' ) );
	}

	/**
	 * Put the request superglobals back.
	 */
	public function tear_down(): void {
		remove_filter( 'wp_die_handler', array( $this, 'throwing_die_handler' ) );

		unset( $_GET['post'], $_GET['artifact'], $_REQUEST['_wpnonce'] );

		parent::tear_down();
	}

	/**
	 * A `wp_die()` handler that raises the status code as an exception code.
	 *
	 * @return callable
	 */
	public function throwing_die_handler(): callable {
		return static function ( mixed $message, mixed $title = '', mixed $args = array() ): void {
			$status = is_array( $args ) && isset( $args['response'] ) ? (int) $args['response'] : 0;

			throw new WPDieException( is_string( $message ) ? $message : '', $status );
		};
	}

	/**
	 * A user without the view capability is turned away before anything reveals whether the
	 * record exists.
	 */
	public function test_a_caller_without_the_view_capability_is_refused(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'certificated', array( 'original', 'certificated' ) );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->request( $post_id, 'certificated' );

		try {
			$this->proxy->handle();
			$this->fail( 'The proxy served an artifact to a user without the view capability.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 403, $e->getCode() );
		}

		$this->assertSame( array(), $this->requests, 'A refused download must not reach the API.' );
	}

	/**
	 * A caller who may view the record still needs the link's own nonce, so a forged image
	 * tag cannot spend an API request on someone else's session.
	 */
	public function test_a_request_without_a_valid_nonce_is_refused(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'certificated', array( 'original', 'certificated' ) );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->request( $post_id, 'certificated', 'invalid' );

		try {
			$this->proxy->handle();
			$this->fail( 'The proxy served an artifact to a request with no valid nonce.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 403, $e->getCode() );
		}

		$this->assertSame( array(), $this->requests, 'A refused download must not reach the API.' );
	}

	/**
	 * An artifact the record does not list is a 404, and never a request.
	 *
	 * Every unavailable artifact and every invented name answer the same 404 at the API, so
	 * probing could not tell "not generated yet" from "no such thing" even if it were
	 * attempted. The record's own artifact list is the only authority.
	 */
	public function test_an_artifact_absent_from_the_record_is_not_fetched(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature', array( 'original' ) );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->request( $post_id, 'certificated' );

		try {
			$this->proxy->handle();
			$this->fail( 'The proxy fetched an artifact the record does not have.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 404, $e->getCode() );
		}

		$this->assertSame( array(), $this->requests );
	}

	/**
	 * A name outside the downloadable set answers the same 404 as a missing one, so the
	 * response tells a caller nothing about the document's state.
	 */
	public function test_an_invented_artifact_name_answers_the_same_404(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'certificated', array( 'original', 'thumbnail' ) );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->request( $post_id, 'thumbnail' );

		try {
			$this->proxy->handle();
			$this->fail( 'The proxy accepted an artifact name that is not downloadable.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 404, $e->getCode() );
		}

		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Populate the superglobals the handler reads, with a nonce for the current user.
	 *
	 * @param int    $post_id  Mirror post id.
	 * @param string $artifact Artifact name being asked for.
	 * @param string $nonce    Nonce to send; empty mints a valid one.
	 */
	private function request( int $post_id, string $artifact, string $nonce = '' ): void {
		$_GET['post']         = (string) $post_id;
		$_GET['artifact']     = $artifact;
		$_REQUEST['_wpnonce'] = '' !== $nonce ? $nonce : wp_create_nonce( DownloadProxy::ACTION . '_' . $post_id );
	}
}
