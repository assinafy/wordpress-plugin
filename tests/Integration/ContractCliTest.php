<?php
/**
 * Contract tracking commands through the WordPress HTTP transport.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\ClientFactory;
use Assinafy\WP\ContractCli;
use Assinafy\WP\Credentials;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;
use WP_CLI;
use WP_CLI\ExitException;

/** @covers \Assinafy\WP\ContractCli */
final class ContractCliTest extends AssinafyTestCase {

	private const DOCUMENT_ID = '104618d0d63884bc446c534e5ff5';

	private ContractCli $command;

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( WP_CLI::class, false ) ) {
			require_once dirname( __DIR__ ) . '/stubs/wp-cli.php';
		}
		WP_CLI::reset();
		$this->configure_plugin();
		$this->command = new ContractCli( new ClientFactory( new Credentials(), new Log() ) );
	}

	/** Every document operation preserves its request and prints the returned contract. */
	public function test_document_operations_use_the_native_transport(): void {
		$operations = array(
			'get'       => array( 'GET', '', null ),
			'update'    => array( 'PUT', '', '{"counterparty_name":"Example Company","metadata":{},"external_references":[]}' ),
			'related'   => array( 'GET', '/related', null ),
			'renew'     => array( 'POST', '/renew', '{"term":1,"updated_at":"2026-10-28T12:00:00Z"}' ),
			'terminate' => array( 'POST', '/terminate', '{"reason":"Completed","updated_at":"2026-10-28T12:00:00Z"}' ),
		);
		$this->fake_response(
			'/contract',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'document_id' => self::DOCUMENT_ID,
					'status'      => 'active',
				),
			)
		);

		foreach ( $operations as $action => [ $method, $suffix, $body ] ) {
			$options = array( 'yes' => true );
			if ( null !== $body ) {
				$options['data'] = $body;
			}
			$this->command->contract( array( $action, self::DOCUMENT_ID ), $options );
			$request = end( $this->requests );
			$this->assertSame( 'https://api.assinafy.com.br/v1/accounts/' . self::ACCOUNT_ID . '/documents/' . self::DOCUMENT_ID . '/contract' . $suffix, $request['url'] );
			$this->assertSame( $method, $request['args']['method'] );
			$this->assertSame( $body, $request['args']['body'] ?? null );
			$this->assertSame( 'test-api-key', $request['args']['headers']['X-Api-Key'] );
			$output = WP_CLI::messages( 'line' );
			$this->assertSame( self::DOCUMENT_ID, json_decode( end( $output ), true )['document_id'] );
		}
		$this->assertCount( 5, $this->requests );
		$this->assertCount( 1, WP_CLI::messages( 'confirm' ) );
	}

	/** List output retains pagination headers, with encoded scalar filters. */
	public function test_list_preserves_filters_and_pagination(): void {
		$this->fake_raw_response(
			'/contracts',
			200,
			'{"status":200,"message":"","data":[]}',
			array(
				'x-pagination-current-page' => '2',
				'x-pagination-page-count'   => '3',
				'x-pagination-total-count'  => '42',
				'x-pagination-per-page'     => '20',
			)
		);

		$this->command->contract(
			array( 'list' ),
			array(
				'page'     => '2',
				'per-page' => '20',
				'filters'  => '{"counterparty":"Example & Co","ending_within_days":45}',
			)
		);

		$this->assertCount( 1, $this->requests );
		$this->assertStringEndsWith( '/contracts?page=2&per-page=20&counterparty=Example%20%26%20Co&ending_within_days=45', $this->requests[0]['url'] );
		$output = json_decode( WP_CLI::messages( 'line' )[0], true );
		$this->assertSame( array(), $output['data'] );
		$this->assertSame(
			array(
				'current_page' => 2,
				'page_count'   => 3,
				'per_page'     => 20,
				'total_count'  => 42,
			),
			$output['pagination']
		);
	}

	/**
	 * @dataProvider invalid_inputs
	 * @param array<int, string> $args Command arguments.
	 * @param array<string, mixed> $options Invalid command options.
	 */
	public function test_invalid_inputs_make_no_request( array $args, array $options ): void {
		try {
			$this->command->contract( $args, $options );
			$this->fail( 'Invalid contract input was accepted.' );
		} catch ( ExitException $e ) {
			$this->assertNotSame( '', $e->getMessage() );
		}
		$this->assertSame( array(), $this->requests );
		$this->assertSame( array(), WP_CLI::messages( 'line' ) );
	}

	/** @return array<string, array{array<int, string>, array<string, mixed>}> */
	public static function invalid_inputs(): array {
		return array(
			'unknown action'      => array( array( 'delete', self::DOCUMENT_ID ), array() ),
			'missing id'          => array( array( 'get' ), array() ),
			'extra id for list'   => array( array( 'list', self::DOCUMENT_ID ), array() ),
			'empty update'        => array( array( 'update', self::DOCUMENT_ID ), array( 'data' => '{}' ) ),
			'list update'         => array( array( 'update', self::DOCUMENT_ID ), array( 'data' => '[{"value":1}]' ) ),
			'scalar update'       => array( array( 'update', self::DOCUMENT_ID ), array( 'data' => '"value"' ) ),
			'bad JSON'            => array( array( 'update', self::DOCUMENT_ID ), array( 'data' => '{' ) ),
			'numeric field'       => array( array( 'update', self::DOCUMENT_ID ), array( 'data' => '{"0":1}' ) ),
			'empty renewal'       => array( array( 'renew', self::DOCUMENT_ID ), array() ),
			'null renewal guards' => array( array( 'renew', self::DOCUMENT_ID ), array( 'data' => '{"term":null,"end_date":null}' ) ),
			'array filter'        => array( array( 'list' ), array( 'filters' => '{"status":["active"]}' ) ),
			'null filter'         => array( array( 'list' ), array( 'filters' => '{"status":null}' ) ),
			'invalid page'        => array( array( 'list' ), array( 'page' => '2x' ) ),
			'zero page'           => array( array( 'list' ), array( 'page' => '0' ) ),
			'oversized page'      => array( array( 'list' ), array( 'per-page' => '101' ) ),
		);
	}

	/** A declined termination spends no network request and changes no contract. */
	public function test_termination_requires_confirmation(): void {
		try {
			$this->command->contract( array( 'terminate', self::DOCUMENT_ID ), array() );
			$this->fail( 'Termination continued without confirmation.' );
		} catch ( ExitException $e ) {
			$this->assertStringContainsString( 'Terminate this contract', $e->getMessage() );
		}
		$this->assertCount( 1, WP_CLI::messages( 'confirm' ) );
		$this->assertSame( array(), $this->requests );
	}

	/** API conflict details remain visible and no successful result is printed. */
	public function test_conflicts_are_reported_without_retrying(): void {
		$this->fake_response(
			'/contract',
			409,
			array(
				'status'  => 409,
				'message' => 'Contract changed; read it again.',
				'data'    => null,
			)
		);
		try {
			$this->command->contract( array( 'renew', self::DOCUMENT_ID ), array( 'data' => '{"term":1,"updated_at":"2026-10-28T12:00:00Z"}' ) );
			$this->fail( 'A stale write was accepted.' );
		} catch ( ExitException $e ) {
			$this->assertSame( 'Contract changed; read it again.', $e->getMessage() );
		}
		$this->assertCount( 1, $this->requests );
		$this->assertSame( array(), WP_CLI::messages( 'line' ) );
	}

	/** The CLI uses the configured OAuth workspace and Bearer token. */
	public function test_oauth_uses_the_authorized_workspace(): void {
		( new Credentials() )->set_oauth_connection(
			array(
				'account_id'    => 'oauth-workspace',
				'access_token'  => 'synthetic-access',
				'refresh_token' => 'synthetic-refresh',
				'expires_at'    => time() + 3600,
				'connected_at'  => time(),
				'scope'         => 'account:read documents:read documents:write webhooks:write',
			)
		);
		$this->fake_response(
			'/contract',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array( 'document_id' => self::DOCUMENT_ID ),
			)
		);

		$this->command->contract( array( 'get', self::DOCUMENT_ID ), array() );

		$this->assertStringContainsString( '/accounts/oauth-workspace/', $this->requests[0]['url'] );
		$this->assertSame( 'Bearer synthetic-access', $this->requests[0]['args']['headers']['Authorization'] );
		$this->assertArrayNotHasKey( 'X-Api-Key', $this->requests[0]['args']['headers'] );
	}

	/** No credentials produces an actionable error without a request. */
	public function test_unconfigured_sites_make_no_request(): void {
		delete_option( Settings::OPTION_ACCOUNT_ID );
		delete_option( Settings::OPTION_API_KEY );
		try {
			$this->command->contract( array( 'list' ), array() );
			$this->fail( 'An unconfigured site continued.' );
		} catch ( ExitException $e ) {
			$this->assertSame( 'Connect the plugin to Assinafy first.', $e->getMessage() );
		}
		$this->assertSame( array(), $this->requests );
	}
}
