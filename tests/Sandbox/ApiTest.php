<?php
/**
 * Explicit live sandbox checks using WordPress's real HTTP transport.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Sandbox;

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Configuration;
use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\SDK\Resources\DocumentResource;
use Assinafy\WP\Http\WpHttpClient;

/** Creates one disposable, unassigned PDF; no invitations or workspace settings changes. */
final class ApiTest extends \WP_UnitTestCase {

	/** Test real authentication, multipart upload, retrieval, pricing and binary download. */
	public function test_document_transport_and_verification_estimates(): void {
		$key     = (string) getenv( 'ASSINAFY_API_KEY' );
		$account = (string) getenv( 'ASSINAFY_ACCOUNT_ID' );
		$this->assertNotSame( '', $key, 'ASSINAFY_API_KEY is required.' );
		$this->assertNotSame( '', $account, 'ASSINAFY_ACCOUNT_ID is required.' );
		$config = new Configuration( $key, $account, Configuration::SANDBOX_BASE_URL );
		$client = new AssinafyClient( $config, new WpHttpClient( $config ) );
		$this->assertSame( $account, $client->accounts()->get()['id'] );
		$this->assertNotEmpty( $client->documents()->statuses() );
		$document = $client->documents()->upload( dirname( __DIR__ ) . '/fixtures/sample.pdf' );
		$id       = (string) $document['id'];
		$this->assertNotSame( '', $id );
		$refused = array();
		try {
			$this->assertSame( $id, $client->documents()->get( $id )['id'] );
			$this->assertStringStartsWith( '%PDF-', $client->documents()->download( $id, DocumentResource::ARTIFACT_ORIGINAL ) );
			foreach ( array( 'Email', 'Whatsapp', 'DigitalCertificate' ) as $method ) {
				try {
					$estimate = $client->assignments()->estimateCost(
						$id,
						array(
							array(
								'verification_method'  => $method,
								'notification_methods' => array( 'Whatsapp' === $method ? 'Whatsapp' : 'Email' ),
							),
						)
					);
					$this->assertArrayHasKey( 'breakdown', $estimate, $method );
				} catch ( ApiException $error ) {
					$refused[] = $method . ': HTTP ' . $error->getStatusCode() . ' ' . $error->getMessage();
				}
			}
		} finally {
			// Only the unassigned document created above is disposable.
			$client->documents()->delete( $id );
		}
		$this->assertSame( array(), $refused, 'All verification features must be enabled on the test account.' );
	}
}
