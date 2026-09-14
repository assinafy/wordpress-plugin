<?php
/**
 * Shared setup for the transport tests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit\Support;

use Assinafy\SDK\Configuration;
use Assinafy\WP\Http\RateLimit;
use Assinafy\WP\Http\WpHttpClient;
use Assinafy\WP\Vendor\Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Builds a `WpHttpClient` over the {@see FakeHttp} double and clears the recorded state
 * between tests.
 */
abstract class TransportTestCase extends TestCase {

	/**
	 * Placeholder credential. The real key never appears in this repository.
	 */
	protected const API_KEY = 'test-api-key-not-a-real-credential';

	/**
	 * Placeholder account id, shaped like a real one: opaque lowercase hex.
	 */
	protected const ACCOUNT_ID = '0e3a1c4b9f2d47a8b6c05e19';

	protected function setUp(): void {
		parent::setUp();

		FakeHttp::reset();
		delete_transient( RateLimit::TRANSIENT );
	}

	protected function tearDown(): void {
		FakeHttp::reset();
		delete_transient( RateLimit::TRANSIENT );

		parent::tearDown();
	}

	/**
	 * @param string|null $access_token Bearer token instead of an API key.
	 */
	protected function config( ?string $access_token = null ): Configuration {
		return new Configuration(
			null === $access_token ? self::API_KEY : '',
			self::ACCOUNT_ID,
			Configuration::SANDBOX_BASE_URL,
			30,
			10,
			$access_token
		);
	}

	protected function client( ?LoggerInterface $logger = null ): WpHttpClient {
		return new WpHttpClient( $this->config(), $logger );
	}
}
