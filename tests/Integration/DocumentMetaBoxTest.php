<?php
/**
 * Action forms inside the native WordPress document editor.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Admin\DocumentMetaBox;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\StatusSync;
use Assinafy\WP\Log;

/**
 * @covers \Assinafy\WP\Admin\DocumentMetaBox
 */
final class DocumentMetaBoxTest extends AssinafyTestCase {

	/**
	 * Each action submits its own fields and nonce without nesting forms or changing the
	 * WordPress edit form, including independent resend controls for multiple signers.
	 *
	 * @dataProvider document_states
	 * @param bool   $assigned Whether the document already has its signature request.
	 * @param string $status   Current status code.
	 * @param int    $count    Number of action forms expected.
	 */
	public function test_action_controls_belong_to_separate_footer_forms( bool $assigned, string $status, int $count ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post_id = $this->create_document( '104618d0d63884bc446c534e5ff5', $status );
		$records = new DocumentRecord();
		if ( $assigned ) {
			$records->hydrate_from_api(
				$post_id,
				array(
					'status'     => $status,
					'is_closed'  => 'expired' === $status,
					'assignment' => array(
						'id'      => '1a09c15990f0144256b98ff38aa',
						'signers' => array(
							array(
								'id'       => 'first',
								'email'    => 'first@example.com',
								'notified' => true,
							),
							array(
								'id'       => 'second',
								'email'    => 'second@example.com',
								'notified' => true,
							),
						),
					),
				)
			);
		}
		$clients = new ClientFactory( new Credentials(), new Log() );
		$panel   = new DocumentMetaBox( $clients, $records, new StatusSync( $clients, $records ) );
		remove_all_actions( 'admin_footer' );
		ob_start();
		$panel->render( get_post( $post_id ) );
		$markup = (string) ob_get_clean();
		$this->assertStringNotContainsString( '<form', $markup );
		$this->assertStringNotContainsString( '</form>', $markup );
		ob_start();
		do_action( 'admin_footer' );
		$footer = (string) ob_get_clean();

		$dom = new \DOMDocument();
		$dom->loadHTML( '<!doctype html><html><body><form id="post">' . $markup . '</form>' . $footer . '</body></html>' );
		$xpath = new \DOMXPath( $dom );
		$forms = $xpath->query( '//form[@id!="post"]' );
		$this->assertCount( $count, $forms );
		$this->assertCount( 0, $xpath->query( '//form//form' ) );
		foreach ( $forms as $form ) {
			$this->assertSame( admin_url( 'admin-post.php' ), $form->getAttribute( 'action' ) );
			$this->assertSame( 'post', $form->getAttribute( 'method' ) );
			$action = $xpath->evaluate( 'string(input[@name="action"]/@value)', $form );
			$nonce  = $xpath->evaluate( 'string(input[@name="_wpnonce"]/@value)', $form );
			$this->assertNotFalse( wp_verify_nonce( $nonce, $action . '_' . $post_id ) );
			$this->assertSame( (string) $post_id, $xpath->evaluate( 'string(input[@name="post"]/@value)', $form ) );
			$this->assertCount( 1, $xpath->query( '//button[@form="' . $form->getAttribute( 'id' ) . '"]' ) );
		}
		foreach ( $xpath->query( '//form[@id="post"]//input[@name] | //form[@id="post"]//button' ) as $control ) {
			$this->assertNotSame( '', $control->getAttribute( 'form' ) );
			$this->assertCount( 1, $xpath->query( '//form[@id="' . $control->getAttribute( 'form' ) . '"]' ) );
		}
	}

	/**
	 * Before assignment: rename/cancel. After assignment: deadline, two resends, cancel. Once
	 * expired: deadline and cancel, with both resends gone.
	 *
	 * @return array<string, array{bool, string, int}>
	 */
	public static function document_states(): array {
		return array(
			'without assignment' => array( false, 'metadata_ready', 2 ),
			'with assignment'    => array( true, 'pending_signature', 4 ),
			'expired'            => array( true, 'expired', 2 ),
		);
	}

	/**
	 * An artifact name the download route does not accept gets no link at all, rather than
	 * one that dies with the route's 404.
	 */
	public function test_downloads_skip_artifact_names_the_route_rejects(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post_id = $this->create_document( '104618d0d63884bc446c534e5ff5', 'certificated', array( 'original', 'thumbnail', 'preview' ) );
		$clients = new ClientFactory( new Credentials(), new Log() );
		$records = new DocumentRecord();
		$panel   = new DocumentMetaBox( $clients, $records, new StatusSync( $clients, $records ) );
		remove_all_actions( 'admin_footer' );
		ob_start();
		$panel->render( get_post( $post_id ) );
		$markup = (string) ob_get_clean();
		$this->assertStringContainsString( 'artifact=original', $markup );
		$this->assertStringNotContainsString( 'thumbnail', $markup );
		$this->assertStringNotContainsString( 'preview', $markup );
	}
}
