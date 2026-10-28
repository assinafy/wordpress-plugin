<?php
/**
 * Native contract controls and their workspace boundary.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

use Assinafy\WP\Admin\ContractScreen;
use Assinafy\WP\Admin\ContractActions;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentContext;
use Assinafy\WP\Log;

/** @covers \Assinafy\WP\Admin\ContractScreen */
final class ContractScreenTest extends AssinafyTestCase {

	private const DOCUMENT = '104618d0d63884bc446c534e5ff5';
	private const UPDATED  = '2026-10-23T12:00:00Z';

	/** Configure an administrator with fixture-only account credentials. */
	public function set_up(): void {
		parent::set_up();
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
	}

	/** Restore request globals between cases. */
	public function tear_down(): void {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/** Each native action uses the published verb, path and concurrency guard. */
	public function test_native_actions_send_the_expected_payloads(): void {
		$this->fake_response(
			'/contract',
			200,
			array(
				'status' => 200,
				'data'   => array(
					'document_id' => self::DOCUMENT,
					'resource'    => 'document_contract',
					'status'      => 'active',
					'updated_at'  => self::UPDATED,
				),
			)
		);
		$cases = array(
			array(
				'update',
				array(
					'value'    => '125.50',
					'end_date' => '',
				),
				'PUT',
				'/contract',
				array(
					'value'    => 125.5,
					'end_date' => null,
				),
			),
			array(
				'renew',
				array(
					'term'     => '2',
					'end_date' => '',
				),
				'POST',
				'/contract/renew',
				array( 'term' => 2 ),
			),
			array( 'terminate', array( 'reason' => 'End of service' ), 'POST', '/contract/terminate', array( 'reason' => 'End of service' ) ),
			array( 'unlink', array(), 'PUT', '/contract', array( 'parent_document_id' => null ) ),
			array(
				'link',
				array(
					'parent_document_id' => 'abc123',
					'relation_type'      => 'renewal',
					'end_date'           => '2028-01-01',
					'value'              => '',
				),
				'PUT',
				'/contract',
				array(
					'parent_document_id' => 'abc123',
					'relation_type'      => 'renewal',
					'end_date'           => '2028-01-01',
				),
			),
		);
		foreach ( $cases as [ $operation, $terms, $method, $suffix, $body ] ) {
			$this->prepare( $operation, $terms + array( 'updated_at' => self::UPDATED ) );
			$this->submit();
			$request = end( $this->requests );
			$this->assertSame( $method, $request['args']['method'] );
			$this->assertStringEndsWith( '/accounts/' . self::ACCOUNT_ID . '/documents/' . self::DOCUMENT . $suffix, $request['url'] );
			$this->assertSame( $body + array( 'updated_at' => self::UPDATED ), json_decode( $request['args']['body'], true ) );
			$this->assertSame( 'success', get_transient( 'assinafy_contract_notice_' . get_current_user_id() )['type'] );
		}
		$this->assertCount( 5, $this->requests );
	}

	/** Invalid native values never reach the API. */
	public function test_invalid_payloads_never_reach_the_api(): void {
		foreach ( array(
			array( 'update', array( 'value' => '-1' ) ),
			array( 'renew', array( 'term' => '0' ) ),
			array( 'terminate', array( 'reason' => str_repeat( 'a', 501 ) ) ),
			array(
				'link',
				array(
					'parent_document_id' => '../escape',
					'relation_type'      => 'renewal',
				),
			),
			array( 'delete', array() ),
		) as [ $operation, $terms ] ) {
			$this->prepare( $operation, $terms );
			$this->submit();
			$this->assertSame( 'error', get_transient( 'assinafy_contract_notice_' . get_current_user_id() )['type'] );
		}
		$this->assertSame( array(), $this->requests );
	}

	/** Old tabs must not write to a newly connected workspace. */
	public function test_a_changed_workspace_stops_the_write(): void {
		$this->prepare( 'update', array( 'counterparty_name' => 'Example Company' ) );
		$_POST['account'] = 'another-workspace';
		$this->submit();
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'error', get_transient( 'assinafy_contract_notice_' . get_current_user_id() )['type'] );
	}

	/** A nonce is required even for an administrator. */
	public function test_invalid_nonce_stops_before_api(): void {
		$this->prepare( 'terminate', array() );
		$_REQUEST['_wpnonce'] = 'invalid';
		$this->expectException( \WPDieException::class );
		try {
			( new ContractActions( new ClientFactory( new Credentials(), new Log() ) ) )->submit();
		} finally {
			$this->assertSame( array(), $this->requests );
		}
	}

	/** A subscriber cannot read or mutate workspace contracts. */
	public function test_unauthorized_user_is_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->expectException( \WPDieException::class );
		try {
			$this->screen()->render();
		} finally {
			$this->assertSame( array(), $this->requests );
		}
	}

	/** Conflicts stay errors, without automatic replay or replacing the precondition. */
	public function test_conflict_is_reported_without_retry(): void {
		$this->fake_response(
			'/contract',
			409,
			array(
				'status'  => 409,
				'message' => 'Contract changed.',
				'data'    => null,
			)
		);
		$this->prepare(
			'renew',
			array(
				'term'       => '1',
				'updated_at' => self::UPDATED,
			)
		);
		$this->submit();
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'error', get_transient( 'assinafy_contract_notice_' . get_current_user_id() )['type'] );
	}

	/** A locally selected document keeps its original workspace binding. */
	public function test_local_document_from_another_workspace_is_not_read(): void {
		$post_id = $this->create_document( self::DOCUMENT );
		$this->assertTrue( DocumentContext::bind( $post_id, 'another-workspace', self::API_HOST . 'v1' ) );
		$_GET['post'] = $post_id;
		ob_start();
		$this->screen()->render();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'different Assinafy workspace', $html );
		$this->assertSame( array(), $this->requests );
	}

	/** Existing terms render separate forms with escaped values and a revision. */
	public function test_contract_editor_escapes_values_and_binds_each_form(): void {
		$_GET['document'] = self::DOCUMENT;
		$this->fake_response(
			'/contract',
			200,
			array(
				'status' => 200,
				'data'   => array(
					'resource'          => 'document_contract',
					'document_id'       => self::DOCUMENT,
					'counterparty_name' => '"><script>attack()</script>',
					'term'              => 1,
					'updated_at'        => self::UPDATED,
					'status'            => 'active',
					'amendments'        => array(),
				),
			)
		);
		ob_start();
		$this->screen()->render();
		$html = ob_get_clean();
		$this->assertStringNotContainsString( '<script>attack()', $html );
		$this->assertSame( 3, substr_count( $html, '<form ' ) );
		$this->assertSame( 3, substr_count( $html, 'name="terms[updated_at]" value="' . self::UPDATED . '"' ) );
		$this->assertStringContainsString( 'Save contract terms', $html );
		$this->assertStringContainsString( 'Renew contract', $html );
		$this->assertStringNotContainsString( 'name="terms[value]"', $html );
		$this->assertStringNotContainsString( 'name="terms[auto_renew]"', $html );
	}

	/** @param array<string, mixed> $terms Submitted terms. */
	private function prepare( string $operation, array $terms ): void {
		$_POST                = array(
			'document'    => self::DOCUMENT,
			'operation'   => $operation,
			'account'     => self::ACCOUNT_ID,
			'environment' => self::API_HOST . 'v1',
			'terms'       => $terms,
		);
		$_REQUEST['_wpnonce'] = wp_create_nonce( ContractActions::ACTION . '_' . self::DOCUMENT );
	}

	private function screen(): ContractScreen {
		return new ContractScreen( new ClientFactory( new Credentials(), new Log() ) );
	}

	/** A successful envelope must identify a real revision before an edit is offered. */
	public function test_malformed_contract_responses_do_not_offer_blank_edits(): void {
		$_GET['document'] = self::DOCUMENT;
		foreach ( array(
			array(),
			array(
				'resource'    => 'document_contract',
				'document_id' => 'another-document',
				'status'      => 'active',
				'updated_at'  => self::UPDATED,
			),
		) as $data ) {
			$this->fake_response(
				'/contract',
				200,
				array(
					'status' => 200,
					'data'   => $data,
				)
			);
			ob_start();
			$this->screen()->render();
			$html = ob_get_clean();
			$this->assertStringContainsString( 'invalid or mismatched contract', $html );
			$this->assertStringNotContainsString( '<form ', $html );
		}
	}

	/** An unconfirmed mutation response must not be reported as a saved contract. */
	public function test_malformed_save_response_is_reported_as_uncertain(): void {
		$this->fake_response(
			'/contract',
			200,
			array(
				'status' => 200,
				'data'   => array(),
			)
		);
		$this->prepare( 'update', array( 'value' => '1' ) );
		$this->submit();
		$this->assertSame( 'error', get_transient( 'assinafy_contract_notice_' . get_current_user_id() )['type'] );
		$this->assertCount( 1, $this->requests );
	}

	/** A document linked through the editor shows its proposed value and date afterwards. */
	public function test_amendment_displays_its_terms_and_parent(): void {
		$_GET['document'] = self::DOCUMENT;
		$this->fake_response(
			'/contract/related',
			200,
			array(
				'status' => 200,
				'data'   => array(
					'document_id' => 'abc123',
					'status'      => 'active',
					'amendments'  => array(),
				),
			)
		);
		$this->fake_response(
			'/contract',
			200,
			array(
				'status' => 200,
				'data'   => array(
					'resource'      => 'document_contract_amendment',
					'document_id'   => self::DOCUMENT,
					'relation_type' => 'renewal',
					'status'        => 'pending',
					'updated_at'    => self::UPDATED,
					'currency'      => 'BRL',
					'terms'         => array(
						'value'    => 125.5,
						'end_date' => '2028-10-28',
					),
				),
			)
		);
		ob_start();
		$this->screen()->render();
		$html = ob_get_clean();
		$this->assertStringContainsString( '125.5', $html );
		$this->assertStringContainsString( '2028-10-28', $html );
		$this->assertStringContainsString( 'abc123', $html );
		$this->assertStringContainsString( 'Unlink pending amendment', $html );
	}

	private function submit(): void {
		$stop = static function (): never {
			throw new \RuntimeException( 'redirect' );
		};
		add_filter( 'wp_redirect', $stop );
		try {
			( new ContractActions( new ClientFactory( new Credentials(), new Log() ) ) )->submit();
			$this->fail( 'A contract action must redirect.' );
		} catch ( \RuntimeException $error ) {
			$this->assertSame( 'redirect', $error->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}
	}
}
