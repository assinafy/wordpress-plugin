<?php
/**
 * The date-field deadline conversion.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\WP\Admin\Deadline;
use PHPUnit\Framework\TestCase;

/** @covers \Assinafy\WP\Admin\Deadline */
final class DeadlineTest extends TestCase {

	/** A real future date becomes the last second of that day in UTC. */
	public function test_a_future_date_becomes_the_end_of_that_day(): void {
		$day = gmdate( 'Y-m-d', strtotime( '+2 days' ) );

		$this->assertSame( gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $day . ' 23:59:59 UTC' ) ), Deadline::parse( $day ) );
	}

	/** The API rejects an expiry less than an hour ahead, so a day inside that window is refused. */
	public function test_a_day_ending_within_the_hour_is_refused(): void {
		$end_of_today = strtotime( gmdate( 'Y-m-d' ) . ' 23:59:59 UTC' );

		if ( $end_of_today <= time() + HOUR_IN_SECONDS ) {
			$this->assertSame( '', Deadline::parse( gmdate( 'Y-m-d' ) ) );
		} else {
			$this->assertSame( gmdate( 'Y-m-d\TH:i:s\Z', $end_of_today ), Deadline::parse( gmdate( 'Y-m-d' ) ) );
		}

		$this->assertSame( '', Deadline::parse( gmdate( 'Y-m-d', strtotime( '-1 day' ) ) ) );
	}

	/** A malformed or impossible day is refused. */
	public function test_an_unparseable_date_is_refused(): void {
		$this->assertSame( '', Deadline::parse( 'next Friday' ) );
		$this->assertSame( '', Deadline::parse( '2026-02-30' ) );
	}
}
