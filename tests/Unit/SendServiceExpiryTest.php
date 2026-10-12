<?php
/**
 * Local expiry validation before any upload.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Log;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use PHPUnit\Framework\TestCase;
use WP_Error;

/** @covers \Assinafy\WP\Documents\SendService */
final class SendServiceExpiryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['assinafy_test_options']    = array();
		$GLOBALS['assinafy_test_transients'] = array();
		FakeHttp::reset();
	}

	/** The API refuses an expiry less than an hour ahead, so the service refuses it before upload. */
	public function test_an_expiry_under_an_hour_ahead_is_rejected_before_upload(): void {
		$result = $this->service()->send( $this->args( gmdate( 'Y-m-d\TH:i:s\Z', time() + 1800 ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_invalid_expiry', $result->get_error_code() );
		$this->assertSame( array(), FakeHttp::$requests );
	}

	/** A past expiry was already refused; the boundary only moved further out. */
	public function test_a_past_expiry_is_rejected_before_upload(): void {
		$result = $this->service()->send( $this->args( '2000-01-01T00:00:00Z' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_invalid_expiry', $result->get_error_code() );
		$this->assertSame( array(), FakeHttp::$requests );
	}

	/** A malformed instant never reaches the one-hour comparison. */
	public function test_an_unparseable_expiry_is_rejected_before_upload(): void {
		$result = $this->service()->send( $this->args( 'next Friday' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_invalid_expiry', $result->get_error_code() );
		$this->assertSame( array(), FakeHttp::$requests );
	}

	private function service(): SendService {
		$credentials = new Credentials();
		$credentials->set_api_key( 'test-api-key-not-a-real-credential' );
		update_option( Credentials::OPTION_ACCOUNT_ID, '0e3a1c4b9f2d47a8b6c05e19' );

		$log = new Log();
		return new SendService( new ClientFactory( $credentials, $log ), new DocumentRecord(), $log );
	}

	/** @return array<string, mixed> */
	private function args( string $expires_at ): array {
		return array(
			'file_path'  => dirname( __DIR__ ) . '/fixtures/sample.pdf',
			'signers'    => array(
				array(
					'full_name' => 'Jane Doe',
					'email'     => 'jane@example.com',
				),
			),
			'expires_at' => $expires_at,
		);
	}
}
