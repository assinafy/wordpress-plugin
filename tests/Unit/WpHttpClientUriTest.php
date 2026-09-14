<?php
/**
 * The relative-URI guard.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use Assinafy\WP\Tests\Unit\Support\TransportTestCase;

/**
 * Every request carries `X-Api-Key`. A URI that can name its own origin therefore hands that
 * credential to whoever the caller points at, so the guard has to run before the request is
 * built — not merely produce a wrong URL.
 *
 * @covers \Assinafy\WP\Http\WpHttpClient
 */
final class WpHttpClientUriTest extends TransportTestCase {

	/**
	 * URIs that must never reach the network.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function rejected_uris(): array {
		return array(
			'empty string'                => array( '' ),
			'leading slash'               => array( '/documents' ),
			'protocol relative'           => array( '//api.example.com/documents' ),
			'absolute https url'          => array( 'https://api.example.com/documents' ),
			'absolute http url'           => array( 'http://api.example.com/documents' ),
			'scheme without host'         => array( 'http:documents' ),
			'unparseable url'             => array( 'http://' ),
			'single dot'                  => array( '.' ),
			'double dot'                  => array( '..' ),
			'leading traversal'           => array( '../accounts' ),
			'interior traversal'          => array( 'documents/../../accounts' ),
			'trailing traversal'          => array( 'documents/..' ),
			'trailing single dot'         => array( 'documents/.' ),
			'encoded leading traversal'   => array( '%2e%2e/accounts' ),
			'encoded interior traversal'  => array( 'documents/%2E%2E/accounts' ),
			'fully encoded traversal'     => array( 'documents/%2e%2e%2faccounts' ),
			'encoded dot with real slash' => array( 'documents/%2e./accounts' ),
		);
	}

	/**
	 * @dataProvider rejected_uris
	 *
	 * @param string $uri Candidate request URI.
	 */
	public function test_rejects_a_uri_that_could_move_the_request_off_origin( string $uri ): void {
		$client = $this->client();

		try {
			$client->get( $uri );
			$this->fail( "Expected '{$uri}' to be rejected." );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame(
				'Request URI must be relative to the configured API base URL',
				$e->getMessage()
			);
		}

		$this->assertSame(
			array(),
			FakeHttp::$requests,
			'The guard must run before the request is dispatched, not after.'
		);
	}

	/**
	 * The guard is on the shared request path, so it covers every verb — including the ones
	 * that carry a body.
	 */
	public function test_every_verb_is_guarded(): void {
		$client = $this->client();

		foreach (
			array(
				'get'     => static fn( $c ) => $c->get( '/documents' ),
				'post'    => static fn( $c ) => $c->post( '/documents', array() ),
				'put'     => static fn( $c ) => $c->put( '/documents', array() ),
				'patch'   => static fn( $c ) => $c->patch( '/documents', array() ),
				'delete'  => static fn( $c ) => $c->delete( '/documents' ),
				'postRaw' => static fn( $c ) => $c->postRaw( '/documents', 'x', 'text/plain' ),
			) as $verb => $call
		) {
			try {
				$call( $client );
				$this->fail( "Expected {$verb} to reject an absolute path." );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'must be relative', $e->getMessage() );
			}
		}

		$this->assertSame( array(), FakeHttp::$requests );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function accepted_uris(): array {
		return array(
			'collection'        => array( 'documents', 'https://sandbox.assinafy.com.br/v1/documents' ),
			'nested resource'   => array(
				'accounts/0e3a1c4b9f2d47a8b6c05e19/documents',
				'https://sandbox.assinafy.com.br/v1/accounts/0e3a1c4b9f2d47a8b6c05e19/documents',
			),
			'artifact download' => array(
				'documents/104618b275d321f5de22240ebfda/download/original',
				'https://sandbox.assinafy.com.br/v1/documents/104618b275d321f5de22240ebfda/download/original',
			),
			'dot inside a word' => array( 'documents/v1.2/statuses', 'https://sandbox.assinafy.com.br/v1/documents/v1.2/statuses' ),
		);
	}

	/**
	 * The join is plain concatenation onto a base that keeps its trailing slash. RFC 3986
	 * resolution would drop the `/v1` segment instead.
	 *
	 * @dataProvider accepted_uris
	 *
	 * @param string $uri      Request URI.
	 * @param string $expected Resulting absolute URL.
	 */
	public function test_accepts_a_relative_uri_and_keeps_the_base_path( string $uri, string $expected ): void {
		FakeHttp::queue( 200, '{"status":200,"message":"","data":[]}' );

		$this->client()->get( $uri );

		$this->assertSame( $expected, FakeHttp::last()['url'] );
	}
}
