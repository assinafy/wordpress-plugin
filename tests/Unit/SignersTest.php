<?php
/**
 * Verification and notification pairing at the shared send boundary.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\WP\Documents\Signers;
use PHPUnit\Framework\TestCase;
use WP_Error;

/** @covers \Assinafy\WP\Documents\Signers */
final class SignersTest extends TestCase {

	/** @dataProvider identity_channels */
	public function test_identity_methods_keep_the_requested_notification( string $method, string $channel ): void {
		$result = Signers::normalize(
			array(
				array(
					'id'                   => 'abcdef123456',
					'verification_method'  => $method,
					'notification_methods' => array( $channel ),
				),
			)
		);
		$this->assertIsArray( $result );
		$this->assertSame( $method, $result[0]['verification_method'] );
		$this->assertSame( array( $channel ), $result[0]['notification_methods'] );
	}

	/** @return array<string, array{string, string}> */
	public static function identity_channels(): array {
		return array(
			'certificate email'    => array( 'DigitalCertificate', 'Email' ),
			'certificate whatsapp' => array( 'DigitalCertificate', 'Whatsapp' ),
			'biometric email'      => array( 'IdDocumentFaceMatchBasic', 'Email' ),
			'biometric whatsapp'   => array( 'IdDocumentFaceMatchBasic', 'Whatsapp' ),
		);
	}

	/** Supplying only a notification method must infer the matching verification method. */
	public function test_notification_only_rows_infer_the_matching_verification(): void {
		foreach ( array( 'Email', 'Whatsapp' ) as $channel ) {
			$result = Signers::normalize(
				array(
					array(
						'id'                   => 'abcdef123456',
						'notification_methods' => array( $channel ),
					),
				)
			);
			$this->assertIsArray( $result );
			$this->assertSame( $channel, $result[0]['verification_method'] );
		}
	}

	/** Biometric signers need an existing contact with a valid CPF. */
	public function test_biometric_requires_an_existing_signer(): void {
		$result = Signers::normalize(
			array(
				array(
					'full_name'           => 'Example Signer',
					'email'               => 'signer@example.test',
					'verification_method' => 'IdDocumentFaceMatchBasic',
				),
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_signer_incomplete', $result->get_error_code() );
	}
}
