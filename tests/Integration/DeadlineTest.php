<?php
/**
 * The deadline a posted date turns into.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Admin\Deadline;

/**
 * Both admin write paths run the posted date through this parser before anything reaches the
 * API, so what it refuses — and the exact instant it produces for what it accepts — is worth
 * pinning down. An integration test because `wp_checkdate()` is WordPress's.
 *
 * @covers \Assinafy\WP\Admin\Deadline
 */
final class DeadlineTest extends AssinafyTestCase {

	/**
	 * A day already gone and a day that never existed are both refused; a real future date
	 * becomes the last second of that day in UTC.
	 *
	 * The impossible day is in the future on purpose: `strtotime()` rolls `02-30` forward to
	 * March, so a past one would be refused by the deadline check whether `wp_checkdate()`
	 * ran or not.
	 */
	public function test_only_a_real_future_date_becomes_a_deadline(): void {
		$this->assertSame( '', Deadline::parse( '2000-01-01' ) );
		$this->assertSame( '', Deadline::parse( '2099-02-30' ) );
		$this->assertSame( '2099-12-31T23:59:59Z', Deadline::parse( '2099-12-31' ) );
	}
}
