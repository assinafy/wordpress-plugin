<?php
/**
 * Shared scaffolding for the WordPress integration tests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Settings;
use WP_UnitTestCase;

/**
 * Base case for every test that runs against a real WordPress.
 *
 * The HTTP fake is the part worth reading. `pre_http_request` is global: a filter left
 * installed, or one that answers every URL, silently replaces the transport for every test
 * that runs afterwards — including core's own. So this one is scoped to the Assinafy base
 * URL and returns the incoming `$preempt` untouched for anything else, and it is removed in
 * `tear_down()` rather than left to the hook backup to clean up.
 *
 * A request to the Assinafy host that no test queued a response for answers HTTP 500 with a
 * body naming the URL. An unqueued call is a test that is exercising a path it did not mean
 * to, and it should fail loudly rather than fall through to the network.
 */
abstract class AssinafyTestCase extends WP_UnitTestCase {

	/**
	 * Every URL the plugin may address in production. The fake refuses to answer anything
	 * else, so a test cannot accidentally stub an unrelated host.
	 */
	protected const API_HOST = 'https://api.assinafy.com.br/';

	/**
	 * Account id used throughout. Placeholder; the real one is never written to a file.
	 */
	protected const ACCOUNT_ID = '104618a0000000000000000001';

	/**
	 * Requests the fake intercepted, oldest first.
	 *
	 * @var array<int, array{url: string, args: array<string, mixed>}>
	 */
	protected array $requests = array();

	/**
	 * Queued responses, keyed by the URL fragment that selects them.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $responses = array();

	/**
	 * Install the capabilities the activation hook would have installed, and arm the fake.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->requests  = array();
		$this->responses = array();

		Capabilities::install();

		add_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10, 3 );
	}

	/**
	 * Disarm the fake and put the role store back.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10 );

		parent::tear_down();

		// Roles live in an option that the transaction rolled back, but `wp_roles()` caches
		// the object it built from the pre-rollback value. Dropping it forces a re-read.
		$GLOBALS['wp_roles'] = null;
		wp_roles();
	}

	/**
	 * Answer, record or ignore one outbound request.
	 *
	 * @param mixed                $preempt Whatever an earlier filter decided.
	 * @param array<string, mixed> $args    Parsed request arguments.
	 * @param string               $url     Request URL.
	 *
	 * @return mixed A response array for the Assinafy host, `$preempt` for anything else.
	 */
	public function intercept_http( mixed $preempt, array $args, string $url ): mixed {
		if ( ! str_starts_with( $url, self::API_HOST ) ) {
			return $preempt;
		}

		$this->requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

		foreach ( $this->responses as $needle => $response ) {
			if ( str_contains( $url, $needle ) ) {
				return $response;
			}
		}

		return self::json_response(
			500,
			array(
				'status'  => 500,
				'message' => 'No response was queued for ' . $url,
				'data'    => null,
			)
		);
	}

	/**
	 * Queue an envelope for every request whose URL contains `$needle`.
	 *
	 * @param string               $needle   URL fragment that selects this response.
	 * @param int                  $code     HTTP status line.
	 * @param array<string, mixed> $envelope Response body, already in envelope shape.
	 */
	protected function fake_response( string $needle, int $code, array $envelope ): void {
		$this->responses[ $needle ] = self::json_response( $code, $envelope );
	}

	/**
	 * Queue a response whose body and headers are given verbatim.
	 *
	 * Needed for the two things an envelope cannot express: a download, whose body is bytes
	 * under a binary content type, and the `X-Pagination-*` / `Retry-After` headers, which
	 * the API reports outside the body.
	 *
	 * @param string                $needle  URL fragment that selects this response.
	 * @param int                   $code    HTTP status line.
	 * @param string                $body    Response body, exactly as the API sends it.
	 * @param array<string, string> $headers Response headers, lowercased names.
	 */
	protected function fake_raw_response( string $needle, int $code, string $body, array $headers = array() ): void {
		$this->responses[ $needle ] = array(
			'headers'  => array_merge( array( 'content-type' => 'application/json; charset=UTF-8' ), $headers ),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Build the array shape `WP_Http` hands back to `wp_remote_request()`.
	 *
	 * @param int                  $code     HTTP status line.
	 * @param array<string, mixed> $envelope Response body.
	 *
	 * @return array<string, mixed>
	 */
	protected static function json_response( int $code, array $envelope ): array {
		return array(
			'headers'  => array( 'content-type' => 'application/json; charset=UTF-8' ),
			'body'     => (string) wp_json_encode( $envelope ),
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Give the plugin usable credentials.
	 *
	 * The key goes through `Credentials::set_api_key()` rather than straight into the option
	 * so the encryption round trip is part of every test that talks to the API.
	 */
	protected function configure_plugin(): void {
		update_option( Settings::OPTION_ACCOUNT_ID, self::ACCOUNT_ID );
		( new Credentials() )->set_api_key( 'test-api-key' );
	}

	/**
	 * Create a mirror record for a remote document.
	 *
	 * @param string            $document_id Remote document id.
	 * @param string            $status      One of the eleven status codes.
	 * @param array<int, string> $artifacts  Artifact names the remote document carries.
	 *
	 * @return int The mirror post id.
	 */
	protected function create_document( string $document_id, string $status = 'pending_signature', array $artifacts = array( 'original' ) ): int {
		$post_id = (int) self::factory()->post->create(
			array(
				'post_type'  => DocumentPostType::POST_TYPE,
				'post_title' => 'contract.pdf',
			)
		);

		( new DocumentRecord() )->hydrate_from_api(
			$post_id,
			array(
				'id'        => $document_id,
				'name'      => 'contract.pdf',
				'status'    => $status,
				'is_closed' => false,
				'artifacts' => array_fill_keys( $artifacts, self::API_HOST . 'v1/documents/' . $document_id . '/download/original' ),
			)
		);

		return $post_id;
	}
}
