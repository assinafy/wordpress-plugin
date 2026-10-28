<?php
/**
 * Webhook secret recovery against the WordPress option store.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;
use Assinafy\WP\Webhook\Route;
use Assinafy\WP\Webhook\SigningSecret;
use WP_REST_Request;

/** @covers \Assinafy\WP\Webhook\SigningSecret */
final class WebhookSecretTest extends AssinafyTestCase {

	private const TOKEN = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

	public function set_up(): void {
		parent::set_up();
		( new Credentials() )->set_oauth_connection(
			array(
				'account_id'    => self::ACCOUNT_ID,
				'access_token'  => 'synthetic-access',
				'refresh_token' => 'synthetic-refresh',
				'expires_at'    => time() + 3600,
				'connected_at'  => time(),
				'scope'         => 'account:read documents:read documents:write webhooks:write',
			)
		);
		update_option( Settings::OPTION_WEBHOOK_TOKEN, self::TOKEN, false );
		update_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID, 'ep_own', false );
		update_option( Settings::OPTION_WEBHOOK_SECRET_ENC, ( new Credentials() )->encrypt( 'whsec_cHJldmlvdXM=' ), false );
		$this->fake_response(
			'/secret/rotate',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array( 'secret' => 'whsec_cm90YXRlZA==' ),
			)
		);
	}

	/** A failed final write must never restore acceptance of the key the API just retired. */
	public function test_failed_rotation_storage_leaves_the_signature_gate_closed(): void {
		$writes = 0;
		$refuse = static function ( mixed $value, mixed $old ) use ( &$writes ): mixed {
			++$writes;
			return 1 === $writes ? $value : $old;
		};
		$client = ( new ClientFactory( new Credentials(), new Log() ) )->client();
		$this->assertNotNull( $client );
		add_filter( 'pre_update_option_' . Settings::OPTION_WEBHOOK_SECRET_ENC, $refuse, PHP_INT_MAX, 2 );
		try {
			$result = SigningSecret::store( $client, 'ep_own' );
		} finally {
			remove_filter( 'pre_update_option_' . Settings::OPTION_WEBHOOK_SECRET_ENC, $refuse, PHP_INT_MAX );
		}

		$this->assertWPError( $result );
		$this->assertSame( 'assinafy_webhook_secret_failed', $result->get_error_code() );
		$this->assertCount( 1, $this->requests );
		$this->assertStringEndsWith( '/secret/rotate', $this->requests[0]['url'] );
		$this->assertSame( '', Route::secret() );
		$GLOBALS['wp_rest_server'] = null;
		$request                   = new WP_REST_Request( 'POST', '/' . Route::REST_NAMESPACE . '/webhook/' . self::TOKEN );
		$body                      = '{"id":30022}';
		$timestamp                 = (string) time();
		$request->set_header( 'content-type', 'application/json' );
		$request->set_header( 'webhook-id', 'synthetic-delivery' );
		$request->set_header( 'webhook-timestamp', $timestamp );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard Webhooks encodes its signature.
		$request->set_header( 'webhook-signature', 'v1,' . base64_encode( hash_hmac( 'sha256', 'synthetic-delivery.' . $timestamp . '.' . $body, 'previous', true ) ) );
		$request->set_body( $body );
		try {
			$this->assertSame( 401, rest_do_request( $request )->get_status() );
		} finally {
			$GLOBALS['wp_rest_server'] = null;
		}

		$this->assertNull( SigningSecret::store( $client, 'ep_own' ) );
		$this->assertCount( 2, $this->requests );
		$this->assertSame( 'whsec_cm90YXRlZA==', Route::secret() );
	}

	/** The native option lock rejects a second registration during a remote rotation. */
	public function test_concurrent_recovery_is_blocked_before_its_rotation_request(): void {
		$client = ( new ClientFactory( new Credentials(), new Log() ) )->client();
		$this->assertNotNull( $client );
		$second   = null;
		$attempt  = true;
		$interact = static function ( mixed $preempt, array $args, string $url ) use ( $client, &$second, &$attempt ): mixed {
			if ( $attempt && str_ends_with( $url, '/secret/rotate' ) ) {
				$attempt = false;
				$second  = SigningSecret::store( $client, 'ep_own' );
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $interact, 9, 3 );
		try {
			$this->assertNull( SigningSecret::store( $client, 'ep_own' ) );
		} finally {
			remove_filter( 'pre_http_request', $interact, 9 );
		}

		$this->assertWPError( $second );
		$this->assertSame( 'assinafy_webhook_secret_busy', $second->get_error_code() );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'whsec_cm90YXRlZA==', Route::secret() );
	}

	/** A failure to close the old gate must stop before the remote key is rotated. */
	public function test_failed_invalidation_does_not_rotate_the_remote_secret(): void {
		$client = ( new ClientFactory( new Credentials(), new Log() ) )->client();
		$this->assertNotNull( $client );
		$refuse = static fn( mixed $value, mixed $old ): mixed => $old;
		add_filter( 'pre_update_option_' . Settings::OPTION_WEBHOOK_SECRET_ENC, $refuse, PHP_INT_MAX, 2 );
		try {
			$result = SigningSecret::store( $client, 'ep_own' );
		} finally {
			remove_filter( 'pre_update_option_' . Settings::OPTION_WEBHOOK_SECRET_ENC, $refuse, PHP_INT_MAX );
		}

		$this->assertWPError( $result );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'whsec_cHJldmlvdXM=', Route::secret() );
	}
}
