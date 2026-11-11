<?php
/**
 * Bundled SDK resources over WordPress HTTP; all requests stay in FakeHttp.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\WP\Http\WpHttpClient;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use Assinafy\WP\Tests\Unit\Support\TransportTestCase;

/** @covers \Assinafy\WP\Http\WpHttpClient */
final class SdkTransportTest extends TransportTestCase {

	/** @return array<string, array> Resource, method, arguments, verb, path, body. */
	public static function workspace_operations(): array {
		$document = array(
			'id'         => 'document-id',
			'signer_ids' => array( 'signer-id' ),
		);
		$envelope = array(
			'name'      => 'Agreement',
			'signers'   => array( array( 'id' => 'signer-id' ) ),
			'documents' => array( $document ),
		);
		return array(
			'envelope list'              => array( 'envelopes', 'list', array(), 'GET', 'envelopes?page=1&per-page=20', null ),
			'envelope get'               => array( 'envelopes', 'get', array( 'envelope-id' ), 'GET', 'envelopes/envelope-id', null ),
			'envelope create'            => array( 'envelopes', 'create', array( $envelope, 'request-key' ), 'POST', 'envelopes', $envelope ),
			'envelope estimate'          => array( 'envelopes', 'estimateCost', array( $envelope ), 'POST', 'envelopes/estimate-cost', $envelope ),
			'envelope update'            => array( 'envelopes', 'update', array( 'envelope-id', array( 'message' => null ) ), 'PATCH', 'envelopes/envelope-id', array( 'message' => null ) ),
			'envelope delete'            => array( 'envelopes', 'delete', array( 'envelope-id' ), 'DELETE', 'envelopes/envelope-id', null ),
			'envelope activities'        => array( 'envelopes', 'activities', array( 'envelope-id' ), 'GET', 'envelopes/envelope-id/activities?page=1&per-page=20&sort=-id', null ),
			'envelope cancel'            => array( 'envelopes', 'cancel', array( 'envelope-id' ), 'PUT', 'envelopes/envelope-id/cancel', null ),
			'envelope resend'            => array( 'envelopes', 'resend', array( 'envelope-id', 'signer-id' ), 'PUT', 'envelopes/envelope-id/signers/signer-id/resend', null ),
			'envelope add document'      => array( 'envelopes', 'addDocument', array( 'envelope-id', $document ), 'POST', 'envelopes/envelope-id/documents', $document ),
			'envelope remove document'   => array( 'envelopes', 'removeDocument', array( 'envelope-id', 'document-id' ), 'DELETE', 'envelopes/envelope-id/documents/document-id', null ),
			'usage'                      => array( 'accounts', 'usage', array(), 'GET', 'usage', null ),
			'notification policy'        => array( 'accounts', 'notificationPolicy', array(), 'GET', 'notification-policy', null ),
			'update notification policy' => array( 'accounts', 'updateNotificationPolicy', array( array( 'owners_receive_all' => false ) ), 'PUT', 'notification-policy', array( 'owners_receive_all' => false ) ),
			'origins'                    => array( 'accounts', 'embedOrigins', array(), 'GET', 'embed-origins', null ),
			'add origin'                 => array( 'accounts', 'addEmbedOrigin', array( 'https://example.com' ), 'POST', 'embed-origins', array( 'origin' => 'https://example.com' ) ),
			'update origin'              => array( 'accounts', 'updateEmbedOrigin', array( 'origin-id', 'https://example.com' ), 'PUT', 'embed-origins/origin-id', array( 'origin' => 'https://example.com' ) ),
			'delete origin'              => array( 'accounts', 'deleteEmbedOrigin', array( 'origin-id' ), 'DELETE', 'embed-origins/origin-id', null ),
			'test webhook'               => array( 'webhooks', 'testEndpoint', array( 'endpoint-id' ), 'POST', 'webhooks/endpoints/endpoint-id/test', null ),
		);
	}

	/**
	 * @dataProvider workspace_operations
	 * @param array<mixed> $arguments SDK arguments.
	 * @param array<mixed>|null $body Expected JSON body.
	 */
	public function test_workspace_operation( string $accessor, string $method, array $arguments, string $verb, string $path, ?array $body ): void {
		foreach ( array( null, 'test-workspace-token' ) as $token ) {
			$config = $this->config( $token );
			$client = new AssinafyClient( $config, new WpHttpClient( $config ) );
			FakeHttp::queue( 200, '{"status":200,"message":"","data":[]}' );
			$client->$accessor()->$method( ...$arguments );
			$request = FakeHttp::last();
			$this->assertSame( $verb, $request['args']['method'] );
			$this->assertSame( $config->getBaseUrl() . '/accounts/' . self::ACCOUNT_ID . '/' . $path, $request['url'] );
			$this->assertSame( $body, json_decode( (string) ( $request['args']['body'] ?? '' ), true ) );
			$headers = FakeHttp::last_headers();
			$this->assertSame( null === $token ? self::API_KEY : 'Bearer ' . $token, $headers[ null === $token ? 'x-api-key' : 'authorization' ] );
			if ( 'create' === $method ) {
				$this->assertSame( 'request-key', $headers['idempotency-key'] );
			}
		}
	}

	/** ZIP artifacts must remain bytes, including NULs, through the native transport. */
	public function test_envelope_download_preserves_binary(): void {
		$config = $this->config();
		$client = new AssinafyClient( $config, new WpHttpClient( $config ) );
		$bytes  = "PK\x03\x04\x00fixture";
		FakeHttp::queue( 200, $bytes, array( 'Content-Type' => 'application/zip' ) );
		$this->assertSame( $bytes, $client->envelopes()->download( 'envelope-id', 'original' ) );
		$this->assertSame( self::API_KEY, FakeHttp::last_headers()['x-api-key'] );
	}

	/** Embedded session creation is private; exchange forwards only the one-use token. */
	public function test_embedded_session_authentication_boundary(): void {
		$config = $this->config();
		$client = new AssinafyClient( $config, new WpHttpClient( $config ) );
		FakeHttp::queue( 200, '{"status":200,"data":{"url":"https://example.com/embed/token"}}' );
		$this->assertArrayHasKey( 'url', $client->embeddedSigning()->createSession( 'document-id', 'assignment-id', 'signer-id' ) );
		$this->assertSame( self::API_KEY, FakeHttp::last_headers()['x-api-key'] );
		FakeHttp::queue( 200, '{"status":200,"data":{"signer_access_code":"test-code"}}' );
		$result = $client->embeddedSigning()->exchangeSession( 'one-use-token', 'https://example.com' );
		$this->assertSame( 'test-code', $result['signer_access_code'] );
		$this->assertArrayNotHasKey( 'x-api-key', FakeHttp::last_headers() );
		$this->assertArrayNotHasKey( 'authorization', FakeHttp::last_headers() );
		$this->assertSame( array( 'parent_origin' => 'https://example.com' ), json_decode( FakeHttp::last()['args']['body'], true ) );
	}
}
