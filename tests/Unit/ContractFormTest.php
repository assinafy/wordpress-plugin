<?php
/**
 * Validation for native contract forms.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\WP\Admin\ContractForm;
use PHPUnit\Framework\TestCase;

/** @covers \Assinafy\WP\Admin\ContractForm */
final class ContractFormTest extends TestCase {

	/** Blanks clear, missing fields stay missing, and zero remains a value. */
	public function test_normalizes_only_submitted_fields(): void {
		$this->assertSame(
			array(
				'value'              => 0.0,
				'currency'           => 'USD',
				'end_date'           => null,
				'notice_period_days' => 0,
				'auto_renew'         => false,
			),
			ContractForm::parse(
				array(
					'value'              => '0.00',
					'currency'           => 'usd',
					'end_date'           => '',
					'notice_period_days' => '0',
					'auto_renew'         => '0',
					'metadata'           => 'ignored',
				)
			)
		);
		$this->assertSame( array( 'end_date' => '2028-02-29' ), ContractForm::parse( array( 'end_date' => '2028-02-29' ) ) );
	}

	/** @dataProvider invalid_values
	 * @param array<string, mixed> $input Invalid form data.
	 */
	public function test_rejects_invalid_values( array $input ): void {
		$this->expectException( \InvalidArgumentException::class );
		ContractForm::parse( $input );
	}

	/** @return array<string, array{array<string, mixed>}> Input boundaries. */
	public static function invalid_values(): array {
		return array(
			'array'           => array( array( 'value' => array( '1' ) ) ),
			'invalid date'    => array( array( 'end_date' => '2027-02-29' ) ),
			'early date'      => array( array( 'start_date' => '0999-12-31' ) ),
			'reversed dates'  => array(
				array(
					'start_date' => '2027-02-02',
					'end_date'   => '2027-02-01',
				),
			),
			'negative money'  => array( array( 'value' => '-0.01' ) ),
			'rounding money'  => array( array( 'value' => '10.001' ) ),
			'exponent'        => array( array( 'value' => '1e5' ) ),
			'money overflow'  => array( array( 'value' => '10000000000000' ) ),
			'currency'        => array( array( 'currency' => 'US' ) ),
			'zero term'       => array( array( 'renewal_term_months' => '0' ) ),
			'term overflow'   => array( array( 'renewal_term_months' => '601' ) ),
			'fractional term' => array( array( 'renewal_term_months' => '1.5' ) ),
			'notice overflow' => array( array( 'notice_period_days' => '3651' ) ),
			'boolean'         => array( array( 'auto_renew' => 'false' ) ),
			'status'          => array( array( 'status' => 'certificated' ) ),
			'taxpayer shape'  => array( array( 'counterparty_taxpayer_id' => 'example' ) ),
			'name length'     => array( array( 'counterparty_name' => str_repeat( 'a', 256 ) ) ),
		);
	}
}
