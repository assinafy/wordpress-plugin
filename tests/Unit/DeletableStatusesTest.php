<?php
/**
 * Caching of the API's deletable-status map.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\WP\Admin\DeletableStatuses;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Log;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use PHPUnit\Framework\TestCase;

/** @covers \Assinafy\WP\Admin\DeletableStatuses */
final class DeletableStatusesTest extends TestCase {

	private const TRANSIENT = 'assinafy_document_statuses';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['assinafy_test_options']    = array();
		$GLOBALS['assinafy_test_transients'] = array();
		FakeHttp::reset();
	}

	/** The API's own list is cached for a day once it answers. */
	public function test_the_api_map_is_cached(): void {
		FakeHttp::queue( 200, '{"status":200,"message":"","data":[{"code":"uploaded","deletable":true}]}' );

		$this->assertTrue( $this->statuses()->allows( 'uploaded' ) );
		$this->assertSame( array( 'uploaded' => true ), get_transient( self::TRANSIENT ) );
	}

	/** The fallback answers while the API is unreachable, without being cached over a recovery. */
	public function test_the_fallback_is_not_cached_when_the_api_fails(): void {
		FakeHttp::queue_error( 'http_request_failed', 'timeout' );

		$this->assertTrue( $this->statuses()->allows( 'metadata_ready' ) );
		$this->assertFalse( get_transient( self::TRANSIENT ) );

		FakeHttp::queue( 200, '{"status":200,"message":"","data":[{"code":"uploaded","deletable":true}]}' );
		$this->assertTrue( $this->statuses()->allows( 'uploaded' ), 'The recovered API must be read again right away.' );
		$this->assertCount( 2, FakeHttp::$requests );
	}

	/** Without credentials the fallback answers and nothing is cached. */
	public function test_the_fallback_is_not_cached_without_a_client(): void {
		$statuses = new DeletableStatuses( new ClientFactory( new Credentials(), new Log() ) );

		$this->assertTrue( $statuses->allows( 'metadata_ready' ) );
		$this->assertFalse( get_transient( self::TRANSIENT ) );
	}

	private function statuses(): DeletableStatuses {
		$credentials = new Credentials();
		$credentials->set_api_key( 'test-api-key-not-a-real-credential' );
		update_option( Credentials::OPTION_ACCOUNT_ID, '0e3a1c4b9f2d47a8b6c05e19' );

		return new DeletableStatuses( new ClientFactory( $credentials, new Log() ) );
	}
}
