<?php
/**
 * The WP-CLI subcommands.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Cli;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentIndex;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Documents\StatusSync;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;
use Assinafy\WP\Webhook\Route;
use WP_CLI;
use WP_CLI\ExitException;

/**
 * Every subcommand runs in-process, through the callable `register()` handed WP-CLI, rather
 * than by shelling out: a `wp` subprocess would talk to the real API and would not see the
 * HTTP fake at all.
 *
 * `tests/stubs/wp-cli.php` supplies the runner. The one behaviour that has to be reproduced
 * faithfully is that `WP_CLI::error()` and a refused `WP_CLI::confirm()` end the command —
 * both raise `ExitException` here, which is what WP-CLI raises when it captures its own exit.
 * A command that is expected to stop is therefore asserted by the exception it raises, and
 * one that is expected to finish by what it printed.
 *
 * @covers \Assinafy\WP\Cli
 */
final class CliTest extends AssinafyTestCase {

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
	 * A webhook endpoint belonging to somebody else's integration.
	 */
	private const FOREIGN_ENDPOINT = 'https://another-integration.example.test/hooks/assinafy';

	/**
	 * The commands under test, on their own client factory so the plugin-wide memoised one is
	 * left alone.
	 */
	private Cli $cli;

	/**
	 * Build the command object and arm the runner.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( \WP_CLI::class, false ) ) {
			require_once dirname( __DIR__ ) . '/stubs/wp-cli.php';
		}

		WP_CLI::reset();

		$this->configure_plugin();

		update_option( Settings::OPTION_WEBHOOK_TOKEN, 'cli-test-webhook-token' );

		$this->cli = $this->command_object();
	}

	/**
	 * WP-CLI turns every public method of a registered object into a subcommand, so each one
	 * is registered against its own method and the list is exactly four.
	 */
	public function test_every_subcommand_is_registered(): void {
		$this->cli->register();

		$this->assertSame(
			array( 'assinafy status', 'assinafy send', 'assinafy sync', 'assinafy webhook' ),
			array_keys( WP_CLI::$commands )
		);

		foreach ( WP_CLI::$commands as $callable ) {
			$this->assertIsCallable( $callable );
		}
	}

	/**
	 * The report reads the account and the subscription, and names the endpoint this site
	 * answers on.
	 */
	public function test_status_reports_how_the_site_is_connected(): void {
		$this->fake_subscription( Route::url() );
		$this->fake_account();

		$rows = $this->run_status();

		$this->assertSame( 'production', $rows['Environment'] );
		$this->assertSame( 'configured', $rows['Credentials'] );
		$this->assertSame( 'Acme Inc. (' . self::ACCOUNT_ID . ')', $rows['Account'] );
		$this->assertSame( Route::url(), $rows['This site endpoint'] );
		$this->assertSame( Route::url(), $rows['Webhook subscription'] );
		$this->assertSame( 'yes', $rows['Webhook delivering'] );
		$this->assertStringContainsString( 'document_ready', $rows['Webhook events'] );
		$this->assertArrayHasKey( 'Rate budget', $rows );
	}

	/**
	 * A site with no credentials still gets a report. Every row below `Credentials` reads the
	 * API, so the report stops there rather than failing.
	 */
	public function test_status_stops_at_the_credentials_when_there_are_none(): void {
		delete_option( Settings::OPTION_ACCOUNT_ID );
		delete_option( Settings::OPTION_API_KEY );

		$rows = $this->run_status( $this->command_object() );

		$this->assertSame( 'not configured', $rows['Credentials'] );
		$this->assertArrayNotHasKey( 'Account', $rows );
		$this->assertSame( array(), $this->requests, 'An unconfigured site must not reach the API.' );
	}

	/**
	 * An account that cannot be reached is a row, not a failure: the rest of the report is
	 * still worth printing.
	 */
	public function test_status_reports_an_unreachable_account(): void {
		$this->fake_subscription( Route::url() );

		$rows = $this->run_status();

		$this->assertStringStartsWith( 'unreachable: ', $rows['Account'] );
	}

	/**
	 * An account that has never configured a subscription answers `null`, not an error.
	 */
	public function test_status_reports_an_account_with_no_subscription(): void {
		$this->fake_response(
			'webhooks/subscriptions',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(),
			)
		);
		$this->fake_account();

		$rows = $this->run_status();

		$this->assertSame( 'none registered on this account', $rows['Webhook subscription'] );
	}

	/**
	 * `--format` reaches the renderer, which is what makes the report scriptable.
	 */
	public function test_status_renders_in_the_requested_format(): void {
		$this->fake_subscription( Route::url() );
		$this->fake_account();

		$this->run_command( 'status', array(), array( 'format' => 'json' ) );

		$this->assertSame( array( 'json:field,value' ), WP_CLI::messages( 'format_items' ) );
	}

	/**
	 * Neither source named.
	 */
	public function test_send_refuses_a_command_with_no_document(): void {
		$this->assertCommandStops(
			'Pass exactly one of --file or --attachment.',
			'send',
			array(),
			array( 'signers' => 'jane@example.com' )
		);
	}

	/**
	 * Both sources named. The two are mutually exclusive rather than one winning silently.
	 */
	public function test_send_refuses_a_command_naming_both_sources(): void {
		$this->assertCommandStops(
			'Pass exactly one of --file or --attachment.',
			'send',
			array(),
			array(
				'file'       => $this->fixture(),
				'attachment' => '412',
				'signers'    => 'jane@example.com',
			)
		);
	}

	/**
	 * A path that is not there is reported before anything is uploaded.
	 */
	public function test_send_refuses_a_file_it_cannot_read(): void {
		$this->assertCommandStops(
			'Cannot read /srv/contracts/absent.pdf.',
			'send',
			array(),
			array(
				'file'    => '/srv/contracts/absent.pdf',
				'signers' => 'jane@example.com',
			)
		);
	}

	/**
	 * Assinafy accepts PDFs only, and a rejected upload still leaves a `failed` document in
	 * the workspace, so the mime type is checked here.
	 */
	public function test_send_refuses_an_attachment_that_is_not_a_pdf(): void {
		$attachment_id = (int) self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/png',
			)
		);

		$this->assertCommandStops(
			sprintf( 'Attachment %d is not a PDF.', $attachment_id ),
			'send',
			array(),
			array(
				'attachment' => (string) $attachment_id,
				'signers'    => 'jane@example.com',
			)
		);
	}

	/**
	 * There is no default signer, and no sensible one to invent.
	 */
	public function test_send_requires_at_least_one_signer(): void {
		$this->assertCommandStops(
			'Pass --signers with at least one "Name <address@example.com>" or bare email address.',
			'send',
			array(),
			array( 'file' => $this->fixture() )
		);
	}

	/**
	 * A mistyped address stops the command rather than sending an invitation nobody receives.
	 */
	public function test_send_refuses_an_invalid_signer_address(): void {
		$this->assertCommandStops(
			'not-an-email is not a valid email address.',
			'send',
			array(),
			array(
				'file'    => $this->fixture(),
				'signers' => 'not-an-email',
			)
		);

		// `sanitize_email()` repairs this one to the valid-but-different `jane@example.com`,
		// so the check has to read what was typed rather than the sanitised value.
		$this->assertCommandStops(
			'jane@@example.com is not a valid email address.',
			'send',
			array(),
			array(
				'file'    => $this->fixture(),
				'signers' => 'Jane Doe <jane@@example.com>',
			)
		);
	}

	/**
	 * The whole round trip: upload, signer resolution, assignment, local record.
	 */
	public function test_send_uploads_and_reports_the_local_record(): void {
		$this->fake_successful_send();

		$this->run_command(
			'send',
			array(),
			array(
				'file'    => $this->fixture(),
				'signers' => 'Jane Doe <jane@example.com>',
			)
		);

		$post_id = ( new DocumentIndex() )->find_by_document_id( self::DOCUMENT_ID );

		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( DocumentPostType::POST_TYPE, get_post_type( $post_id ) );
		$this->assertSame(
			array( sprintf( 'Sent. Local record: post %d.', $post_id ) ),
			WP_CLI::messages( 'success' )
		);
	}

	/**
	 * `--porcelain` prints the post id and nothing else, so a shell script can capture it.
	 */
	public function test_send_prints_only_the_post_id_with_porcelain(): void {
		$this->fake_successful_send();

		$this->run_command(
			'send',
			array(),
			array(
				'file'      => $this->fixture(),
				'signers'   => 'jane@example.com',
				'porcelain' => true,
			)
		);

		$post_id = ( new DocumentIndex() )->find_by_document_id( self::DOCUMENT_ID );

		$this->assertSame( array( (string) $post_id ), WP_CLI::messages( 'line' ) );
		$this->assertSame( array(), WP_CLI::messages( 'success' ) );
	}

	/**
	 * Each entry of `--signers` is either `Name <address>` or a bare address, and a bare one
	 * doubles as the name. Both are resolved against the account before the assignment.
	 */
	public function test_send_parses_named_and_bare_signers(): void {
		$this->fake_successful_send();

		$this->run_command(
			'send',
			array(),
			array(
				'file'    => $this->fixture(),
				'signers' => 'Jane Doe <jane@example.com>, sam@example.com',
			)
		);

		$looked_up = array();

		foreach ( $this->requests as $request ) {
			if ( str_contains( $request['url'], '/signers?' ) ) {
				$looked_up[] = $request['url'];
			}
		}

		$this->assertCount( 2, $looked_up );
		$this->assertStringContainsString( 'search=jane%40example.com', $looked_up[0] );
		$this->assertStringContainsString( 'search=sam%40example.com', $looked_up[1] );
	}

	/**
	 * A refusal from Assinafy reaches the operator as the message Assinafy gave.
	 */
	public function test_send_reports_a_refusal_from_the_api(): void {
		$this->fake_successful_send();
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/documents',
			422,
			array(
				'status'  => 422,
				'message' => 'O arquivo enviado não é um PDF válido.',
				'data'    => null,
			)
		);

		$this->assertCommandStops(
			'O arquivo enviado não é um PDF válido.',
			'send',
			array(),
			array(
				'file'    => $this->fixture(),
				'signers' => 'jane@example.com',
			)
		);
	}

	/**
	 * With no document id, `sync` runs the reconcile pass the hourly cron runs. A site with
	 * nothing open has nothing to re-read.
	 */
	public function test_sync_runs_the_reconcile_pass(): void {
		$this->run_command( 'sync' );

		$this->assertSame( array( 'Reconcile pass finished.' ), WP_CLI::messages( 'success' ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * With a document id, `sync` re-reads that one document and writes what came back onto
	 * its local record.
	 */
	public function test_sync_refreshes_one_document(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'id'         => self::DOCUMENT_ID,
					'name'       => 'contract.pdf',
					'status'     => 'certificated',
					'is_closed'  => true,
					'artifacts'  => array(
						'original'     => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/download/original',
						'certificated' => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/download/certificated',
					),
					'assignment' => null,
				),
			)
		);

		$this->run_command( 'sync', array( self::DOCUMENT_ID ) );

		$this->assertSame( array( 'Refreshed ' . self::DOCUMENT_ID . '.' ), WP_CLI::messages( 'success' ) );
		$this->assertSame( 'certificated', ( new DocumentRecord() )->status( $post_id ) );
	}

	/**
	 * A document this site never sent is not something it can refresh, and saying "Refreshed"
	 * anyway would hide the typo that caused it.
	 */
	public function test_sync_refuses_a_document_the_site_has_no_record_of(): void {
		$this->assertCommandStops(
			'This site has no record of that Assinafy document.',
			'sync',
			array( '104618ffffffffffffffffffffff' )
		);
	}

	/**
	 * `webhook status` on an account that has never configured one.
	 */
	public function test_webhook_status_reports_an_account_with_no_subscription(): void {
		$this->fake_response(
			'webhooks/subscriptions',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(),
			)
		);

		$this->run_command( 'webhook', array( 'status' ) );

		$this->assertContains(
			'No webhook subscription has ever been configured on this account.',
			WP_CLI::messages( 'line' )
		);
		$this->assertSame( array(), WP_CLI::messages( 'warning' ) );
	}

	/**
	 * An account delivering somewhere else is the case worth warning about: this site will
	 * receive nothing until it takes the subscription over.
	 */
	public function test_webhook_status_warns_when_the_account_delivers_elsewhere(): void {
		$this->fake_subscription( self::FOREIGN_ENDPOINT );

		$this->run_command( 'webhook', array( 'status' ) );

		$this->assertContains( 'Registered URL:    ' . self::FOREIGN_ENDPOINT, WP_CLI::messages( 'line' ) );
		$this->assertSame(
			array( 'The account delivers somewhere else. This site will not receive webhooks until it is registered.' ),
			WP_CLI::messages( 'warning' )
		);
	}

	/**
	 * `webhook off` stops delivery and records that this site is no longer expecting any.
	 */
	public function test_webhook_off_stops_delivery_and_records_it(): void {
		update_option( Settings::OPTION_WEBHOOK_ENABLED, true );

		$this->fake_subscription( Route::url() );
		$this->fake_response(
			'webhooks/inactivate',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'url'       => Route::url(),
					'is_active' => false,
				),
			)
		);

		$this->run_command( 'webhook', array( 'off' ) );

		$this->assertSame(
			array( 'Deliveries stopped. The subscription stays on file and can be registered again.' ),
			WP_CLI::messages( 'success' )
		);
		$this->assertFalse( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
	}

	/**
	 * Registering on an account with no subscription needs no confirmation: nothing is being
	 * taken over.
	 */
	public function test_webhook_register_points_the_account_at_this_site(): void {
		$this->fake_response(
			'webhooks/subscriptions',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(),
			)
		);

		$this->run_command( 'webhook', array( 'register' ), array( 'email' => 'ops@example.com' ) );

		$this->assertSame( array( 'Assinafy now delivers to ' . Route::url() ), WP_CLI::messages( 'success' ) );
		$this->assertTrue( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
		$this->assertSame( array(), WP_CLI::messages( 'confirm' ) );

		$body = json_decode( (string) $this->last_request_body(), true );

		$this->assertSame( Route::url(), $body['url'] );
		$this->assertSame( 'ops@example.com', $body['email'] );
		$this->assertSame( Route::DEFAULT_EVENTS, $body['events'] );
		$this->assertTrue( $body['is_active'] );
	}

	/**
	 * An account has exactly one subscription and no DELETE route, so registering over
	 * somebody else's endpoint takes it away from them. That is asked about first.
	 */
	public function test_webhook_register_asks_before_taking_over_another_endpoint(): void {
		$this->fake_subscription( self::FOREIGN_ENDPOINT );

		$this->assertCommandStops(
			'This account delivers to ' . self::FOREIGN_ENDPOINT . '. Replace it with this site?',
			'webhook',
			array( 'register' )
		);

		$this->assertFalse( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
	}

	/**
	 * `--yes` answers the take-over question, which is what makes a migration scriptable.
	 */
	public function test_webhook_register_takes_over_when_told_to(): void {
		$this->fake_subscription( self::FOREIGN_ENDPOINT );

		$this->run_command( 'webhook', array( 'register' ), array( 'yes' => true ) );

		$this->assertCount( 1, WP_CLI::messages( 'confirm' ) );
		$this->assertSame( array( 'Assinafy now delivers to ' . Route::url() ), WP_CLI::messages( 'success' ) );
		$this->assertTrue( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
	}

	/**
	 * Without credentials there is nothing to ask the account with.
	 */
	public function test_webhook_refuses_to_run_without_credentials(): void {
		delete_option( Settings::OPTION_ACCOUNT_ID );
		delete_option( Settings::OPTION_API_KEY );

		$this->cli = $this->command_object();

		$this->assertCommandStops(
			'Connect the plugin to Assinafy before touching the webhook subscription.',
			'webhook',
			array( 'status' )
		);
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * A failure from the API stops the command with the message the API gave.
	 */
	public function test_webhook_reports_a_failure_from_the_api(): void {
		$this->fake_response(
			'webhooks/subscriptions',
			500,
			array(
				'status'  => 500,
				'message' => 'Erro interno do servidor.',
				'data'    => null,
			)
		);

		$this->assertCommandStops( 'Erro interno do servidor.', 'webhook', array( 'status' ) );
	}

	/**
	 * Build the command object on collaborators of its own.
	 */
	private function command_object(): Cli {
		$clients = new ClientFactory( new Credentials(), new Log() );
		$records = new DocumentRecord();
		$log     = new Log();

		return new Cli(
			$clients,
			new SendService( $clients, $records, $log ),
			new StatusSync( $clients, $records )
		);
	}

	/**
	 * Run one subcommand through the callable `register()` handed WP-CLI.
	 *
	 * @param string                $name       Subcommand, without the `assinafy ` prefix.
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, mixed>  $assoc_args Associative arguments.
	 */
	private function run_command( string $name, array $args = array(), array $assoc_args = array() ): void {
		$this->cli->register();

		$command = WP_CLI::$commands[ 'assinafy ' . $name ];

		$this->assertIsCallable( $command );

		$command( $args, $assoc_args );
	}

	/**
	 * Run a subcommand that is expected to stop, and check what it said as it did.
	 *
	 * @param string                $message    The message the operator sees.
	 * @param string                $name       Subcommand, without the `assinafy ` prefix.
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, mixed>  $assoc_args Associative arguments.
	 */
	private function assertCommandStops( string $message, string $name, array $args = array(), array $assoc_args = array() ): void {
		try {
			$this->run_command( $name, $args, $assoc_args );

			$this->fail( 'The command was expected to stop: ' . $message );
		} catch ( ExitException $e ) {
			$this->assertSame( $message, $e->getMessage() );
		}
	}

	/**
	 * The status report, keyed by its field labels.
	 *
	 * @param Cli|null $cli Command object to run, defaulting to the one under test.
	 *
	 * @return array<string, string>
	 */
	private function run_status( ?Cli $cli = null ): array {
		$this->cli = $cli ?? $this->cli;

		$this->run_command( 'status' );

		$report = array();

		foreach ( WP_CLI::rows() as $row ) {
			$report[ (string) $row['field'] ] = (string) $row['value'];
		}

		return $report;
	}

	/**
	 * The body of the most recent intercepted request.
	 */
	private function last_request_body(): string {
		$request = end( $this->requests );

		$this->assertIsArray( $request );

		return is_string( $request['args']['body'] ?? null ) ? $request['args']['body'] : '';
	}

	/**
	 * The checked-in PDF.
	 */
	private function fixture(): string {
		return dirname( __DIR__ ) . '/fixtures/sample.pdf';
	}

	/**
	 * Queue `GET /accounts/{accountId}`.
	 *
	 * Queue this last. The fake answers with the first queued fragment the URL contains, and
	 * every account-scoped route contains this one.
	 */
	private function fake_account(): void {
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource' => 'account',
					'id'       => self::ACCOUNT_ID,
					'name'     => 'Acme Inc.',
				),
			)
		);
	}

	/**
	 * Queue `GET /accounts/{accountId}/webhooks/subscriptions`.
	 *
	 * @param string $url Endpoint the account currently delivers to.
	 */
	private function fake_subscription( string $url ): void {
		$this->fake_response(
			'webhooks/subscriptions',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'url'        => $url,
					'email'      => 'ops@example.com',
					'events'     => array( 'document_ready', 'signer_signed_document' ),
					'is_active'  => true,
					'updated_at' => '2026-08-27T17:55:12Z',
				),
			)
		);
	}

	/**
	 * Queue the three calls one successful send makes.
	 */
	private function fake_successful_send(): void {
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
					),
				),
			)
		);

		// The lookup answers with Jane whatever it is asked, so any other address falls
		// through to `POST /accounts/{accountId}/signers`.
		$this->fake_response(
			'/signers',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'              => 'signer',
					'id'                    => self::SIGNER_ID . '2',
					'full_name'             => 'sam@example.com',
					'email'                 => 'sam@example.com',
					'whatsapp_phone_number' => null,
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
