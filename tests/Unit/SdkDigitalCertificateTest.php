<?php
/**
 * Guards the certificate-assignment rule in the SDK bundled with the plugin.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\SDK\AssinafyClient;
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
			'create template document'    => static fn(): array => $client->documents()->createFromTemplate( 'template-id', $signers ),
			'estimate template document'  => static fn(): array => $client->documents()->estimateCostFromTemplate( 'template-id', $signers ),
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
}
