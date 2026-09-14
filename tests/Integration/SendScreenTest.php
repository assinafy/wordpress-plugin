<?php
/**
 * Compose form validation before any external send.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Admin\SendScreen;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Log;

/**
 * @covers \Assinafy\WP\Admin\SendScreen
 */
final class SendScreenTest extends AssinafyTestCase {

	/**
	 * Invalid rows must never be silently dropped or repaired into another recipient.
	 *
	 * @dataProvider invalid_rows
	 *
	 * @param mixed $row Invalid second signer.
	 */
	public function test_invalid_signer_refuses_the_entire_request( mixed $row ): void {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$attachment = self::factory()->attachment->create_upload_object( dirname( __DIR__ ) . '/fixtures/sample.pdf' );
		$this->assertIsInt( $attachment );
		$_POST                = array(
			'assinafy_attachment_id' => $attachment,
			'assinafy_signers'       => array(
				array(
					'name'  => 'Jane Example',
					'email' => 'jane@example.com',
				),
				$row,
			),
		);
		$_REQUEST['_wpnonce'] = wp_create_nonce( SendScreen::ACTION );
		$screen               = new SendScreen( new SendService( new ClientFactory( new Credentials(), new Log() ), new DocumentRecord(), new Log() ) );
		$stop_redirect        = static function (): never {
			throw new \RuntimeException( 'redirect' );
		};
		add_filter( 'wp_redirect', $stop_redirect );
		try {
			$screen->handle_send();
			$this->fail( 'The form must redirect with a validation error.' );
		} catch ( \RuntimeException $error ) {
			$this->assertSame( 'redirect', $error->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $stop_redirect );
			$_POST    = array();
			$_REQUEST = array();
		}
		$notice = get_transient( 'assinafy_send_notice_' . get_current_user_id() );
		$this->assertSame( 'Add a name and a valid, unique email address for every signer.', $notice['message'] );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * A lost upload response leads to its recovery record, never a fresh compose form.
	 */
	public function test_uncertain_upload_redirects_to_the_reserved_record(): void {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$attachment           = self::factory()->attachment->create_upload_object( dirname( __DIR__ ) . '/fixtures/sample.pdf' );
		$_POST                = array(
			'assinafy_attachment_id' => $attachment,
			'assinafy_signers'       => array(
				array(
					'name'  => 'Jane Example',
					'email' => 'jane@example.com',
				),
			),
		);
		$_REQUEST['_wpnonce'] = wp_create_nonce( SendScreen::ACTION );
		$this->fake_response(
			'/signers?',
			200,
			array(
				'status' => 200,
				'data'   => array(
					array(
						'id'    => '19e6b92e7895332ed9708535d8c',
						'email' => 'jane@example.com',
					),
				),
			)
		);
		$this->fake_response(
			'/documents',
			500,
			array(
				'status'  => 500,
				'message' => 'Upload response unavailable.',
			)
		);
		$screen   = new SendScreen( new SendService( new ClientFactory( new Credentials(), new Log() ), new DocumentRecord(), new Log() ) );
		$redirect = '';
		$stop     = static function ( string $location ) use ( &$redirect ): never {
			$redirect = $location;
			throw new \RuntimeException( 'redirect' );
		};
		add_filter( 'wp_redirect', $stop );
		try {
			$screen->handle_send();
			$this->fail( 'A failed upload must redirect.' );
		} catch ( \RuntimeException $error ) {
			$this->assertSame( 'redirect', $error->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $stop );
			$_POST    = array();
			$_REQUEST = array();
		}
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
		$this->assertSame( 'edit', $query['action'] );
		$this->assertSame( 'assinafy_document', get_post_type( (int) $query['post'] ) );
		$this->assertSame( '', ( new DocumentRecord() )->document_id( (int) $query['post'] ) );
		$this->assertSame( 'Upload response unavailable.', ( new DocumentRecord() )->last_error( (int) $query['post'] ) );
	}

	/**
	 * A valid nonce is not authority: the capability decides who may spend account credits.
	 */
	public function test_a_user_without_the_send_capability_is_refused(): void {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		// Minted as the subscriber, so check_admin_referer() passes and the capability is the
		// only thing left that can refuse the request.
		$_REQUEST['_wpnonce'] = wp_create_nonce( SendScreen::ACTION );
		$screen               = new SendScreen( new SendService( new ClientFactory( new Credentials(), new Log() ), new DocumentRecord(), new Log() ) );
		$this->expectExceptionMessage( 'You are not allowed to send documents for signature.' );
		try {
			$screen->handle_send();
		} finally {
			$this->assertSame( array(), $this->requests );
			$_REQUEST = array();
		}
	}

	/**
	 * @return array<string, array{0: mixed}> Malformed, incomplete, duplicate and repairable rows.
	 */
	public static function invalid_rows(): array {
		return array(
			'malformed row'   => array( 'not a signer' ),
			'array field'     => array(
				array(
					'name'  => array( 'Jane' ),
					'email' => 'other@example.com',
				),
			),
			'missing name'    => array( array( 'email' => 'other@example.com' ) ),
			'invalid email'   => array(
				array(
					'name'  => 'Other Example',
					'email' => 'other@@example.com',
				),
			),
			'duplicate email' => array(
				array(
					'name'  => 'Other Example',
					'email' => 'JANE@example.com',
				),
			),
		);
	}
}
