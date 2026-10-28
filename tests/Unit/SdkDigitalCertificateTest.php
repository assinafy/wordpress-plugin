<?php
/**
 * Guards the certificate-assignment rule in the SDK bundled with the plugin.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\SDK\Exceptions\ValidationException;
use Assinafy\SDK\Resources\AssignmentResource;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use Assinafy\WP\Tests\Unit\Support\TransportTestCase;

/**
 * Proves unsafe certificate requests stop before the WordPress transport.
 *
 * @coversNothing
 */
final class SdkDigitalCertificateTest extends TransportTestCase {

	public function test_non_virtual_certificate_requests_are_rejected_with_a_stable_code(): void {
		$client  = new AssinafyClient( $this->config(), $this->client() );
		$signers = array(
			array(
				'id'                  => 'signer-id',
				'role_id'             => 'role-id',
				'verification_method' => AssignmentResource::VERIFICATION_DIGITAL_CERTIFICATE,
			),
		);
		$calls   = array(
			'create collect assignment'   => static fn(): array => $client->assignments()->create(
				'document-id',
				$signers,
				AssignmentResource::METHOD_COLLECT,
				array(
					'entries' => array(
						array(
							'page_id' => 'page-id',
							'fields'  => array(),
						),
					),
				)
			),
			'estimate collect assignment' => static fn(): array => $client->assignments()->estimateCost(
				'document-id',
				$signers,
				AssignmentResource::METHOD_COLLECT,
				array(
					'entries' => array(
						array(
							'page_id' => 'page-id',
							'fields'  => array(),
						),
					),
				)
			),
		);

		foreach ( $calls as $name => $call ) {
			try {
				$call();
				$this->fail( 'Expected rejection for ' . $name . '.' );
			} catch ( ValidationException $exception ) {
				$this->assertSame(
					AssignmentResource::ERROR_DIGITAL_CERTIFICATE_REQUIRES_VIRTUAL,
					$exception->getErrors()['code'] ?? null
				);
				$this->assertSame( array(), FakeHttp::$requests );
			}
		}
	}

	/** The API accepts virtual templates and determines whether a template has placed fields. */
	public function test_certificate_templates_reach_the_api_and_preserve_collect_rejections(): void {
		$client  = new AssinafyClient( $this->config(), $this->client() );
		$signers = array(
			array(
				'id'                   => 'signer-id',
				'role_id'              => 'role-id',
				'verification_method'  => AssignmentResource::VERIFICATION_DIGITAL_CERTIFICATE,
				'notification_methods' => array( 'Email' ),
			),
		);
		foreach ( array( 'createFromTemplate', 'estimateCostFromTemplate' ) as $method ) {
			FakeHttp::queue( 200, '{"status":200,"data":{"id":"document-id"}}' );
			$this->assertSame( array( 'id' => 'document-id' ), $client->documents()->$method( 'template-id', $signers ) );
			$this->assertSame( $signers, json_decode( FakeHttp::last()['args']['body'], true )['signers'] );
			FakeHttp::queue( 400, '{"status":400,"message":"DigitalCertificate requires virtual assignment.","data":null}' );
			try {
				$client->documents()->$method( 'template-with-fields', $signers );
				$this->fail( 'A template with placed fields must remain rejected by the API.' );
			} catch ( ApiException $error ) {
				$this->assertSame( 400, $error->getStatusCode() );
			}
		}
	}
}
