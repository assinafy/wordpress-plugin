<?php
/**
 * Native contract form fields and validation.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Notice::render() and ContractScreen::render() escape these plain-text errors at display time.

use Assinafy\WP\Documents\DocumentRecord;

/** Validates the fields exposed by the contract editor before API requests. */
final class ContractForm {

	/** @return array<string, array{string, string}> Field label and input type. */
	public static function fields(): array {
		return array(
			'counterparty_name'        => array( __( 'Counterparty', 'assinafy' ), 'text' ),
			'counterparty_taxpayer_id' => array( __( 'Counterparty CPF/CNPJ', 'assinafy' ), 'text' ),
			'value'                    => array( __( 'Contract value', 'assinafy' ), 'number' ),
			'currency'                 => array( __( 'Currency', 'assinafy' ), 'text' ),
			'start_date'               => array( __( 'Start date', 'assinafy' ), 'date' ),
			'end_date'                 => array( __( 'End date', 'assinafy' ), 'date' ),
			'renewal_term_months'      => array( __( 'Renewal term (months)', 'assinafy' ), 'number' ),
			'notice_period_days'       => array( __( 'Notice period (days)', 'assinafy' ), 'number' ),
		);
	}

	/**
	 * Parse the native editor's subset; absent fields remain unchanged and blanks clear.
	 *
	 * @param array<string, mixed> $input Unslashed form input.
	 * @return array<string, mixed> Validated wire fields.
	 */
	public static function parse( array $input ): array {
		$result = array();
		foreach ( array_keys( self::fields() ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$result[ $key ] = self::value( $key, self::text( $input[ $key ] ) );
			}
		}
		if ( isset( $input['auto_renew'] ) ) {
			$value = self::text( $input['auto_renew'] );
			if ( ! in_array( $value, array( '0', '1' ), true ) ) {
				throw new \InvalidArgumentException( __( 'Choose whether this contract renews automatically.', 'assinafy' ) );
			}
			$result['auto_renew'] = '1' === $value;
		}
		$status = self::text( $input['status'] ?? '' );
		if ( '' !== $status ) {
			if ( ! in_array( $status, array( 'draft', 'active', 'expired', 'terminated' ), true ) ) {
				throw new \InvalidArgumentException( __( 'Choose a valid contract status.', 'assinafy' ) );
			}
			$result['status'] = $status;
		}
		if ( isset( $result['start_date'], $result['end_date'] ) && $result['end_date'] < $result['start_date'] ) {
			throw new \InvalidArgumentException( __( 'The end date must be on or after the start date.', 'assinafy' ) );
		}
		return $result;
	}

	/** Reject arrays and objects instead of silently clearing a field. */
	public static function text( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw new \InvalidArgumentException( __( 'A contract field has an invalid value.', 'assinafy' ) );
		}
		return sanitize_text_field( $value );
	}

	private static function value( string $key, string $value ): string|int|float|null {
		if ( '' === $value ) {
			return null;
		}
		return match ( $key ) {
			'start_date', 'end_date' => self::date( $value ),
			'value' => self::amount( $value ),
			'renewal_term_months', 'notice_period_days' => self::interval( $key, $value ),
			default => self::label( $key, $value ),
		};
	}

	private static function date( string $value ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		if ( false === $date || $date->format( 'Y-m-d' ) !== $value || $value < '1000-01-01' ) {
			throw new \InvalidArgumentException( __( 'Use a valid contract date in YYYY-MM-DD format, from year 1000.', 'assinafy' ) );
		}
		return $value;
	}

	private static function amount( string $value ): float {
		if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/D', $value ) || (float) $value > 9999999999999.99 ) {
			throw new \InvalidArgumentException( __( 'Use a non-negative contract value with at most two decimal places.', 'assinafy' ) );
		}
		return (float) $value;
	}

	private static function label( string $key, string $value ): string {
		if ( 'currency' === $key ) {
			if ( ! preg_match( '/^[A-Za-z]{3}$/D', $value ) ) {
				throw new \InvalidArgumentException( __( 'Use a three-letter currency code.', 'assinafy' ) );
			}
			return strtoupper( $value );
		}
		if ( 'counterparty_taxpayer_id' === $key
			&& ( ! preg_match( '/^[0-9.\/-]+$/D', $value ) || ! in_array( strlen( (string) preg_replace( '/\D/', '', $value ) ), array( 11, 14 ), true ) ) ) {
			throw new \InvalidArgumentException( __( 'Enter a CPF or CNPJ for the counterparty.', 'assinafy' ) );
		}
		if ( mb_strlen( $value ) > 255 ) {
			throw new \InvalidArgumentException( __( 'The counterparty name must contain at most 255 characters.', 'assinafy' ) );
		}
		return $value;
	}

	private static function interval( string $key, string $value ): int {
		$minimum = 'renewal_term_months' === $key ? 1 : 0;
		$maximum = 'renewal_term_months' === $key ? 600 : 3650;
		if ( ! ctype_digit( $value ) || (float) $value < $minimum || (float) $value > $maximum ) {
			throw new \InvalidArgumentException( __( 'Renewal months must be 1–600 and notice days must be 0–3650.', 'assinafy' ) );
		}
		return (int) $value;
	}

	/** @param array<string, mixed> $contract Unwrapped API response. */
	public static function assert_contract( array $contract, string $document_id ): void {
		if ( ( $contract['document_id'] ?? null ) !== $document_id
			|| ! in_array( $contract['resource'] ?? null, array( 'document_contract', 'document_contract_amendment' ), true )
			|| ! is_string( $contract['updated_at'] ?? null ) || '' === $contract['updated_at']
			|| ! is_string( $contract['status'] ?? null ) || '' === $contract['status'] ) {
			throw new \UnexpectedValueException( __( 'Assinafy returned an invalid or mismatched contract. Reload its current state before trying again.', 'assinafy' ) );
		}
		if ( 'document_contract' === $contract['resource'] ) {
			self::parse( array_map( self::form_value( ... ), array_intersect_key( $contract, self::fields() ) ) );
		}
	}

	/** Convert API scalar values to the same inputs validated by the native form. */
	private static function form_value( mixed $value ): mixed {
		return null === $value ? '' : ( is_string( $value ) || is_numeric( $value ) ? (string) $value : $value );
	}

	/** Require the same opaque document IDs as other native document controls. */
	public static function validate_id( string $document_id ): void {
		if ( ! DocumentRecord::is_valid_id( $document_id ) ) {
			throw new \InvalidArgumentException( __( 'Enter a valid Assinafy document ID.', 'assinafy' ) );
		}
	}
}
