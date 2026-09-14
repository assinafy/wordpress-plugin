<?php
/**
 * Signer rows, in the shape the API accepts.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Resources\AssignmentResource;
use Assinafy\SDK\Resources\SignerResource;
use WP_Error;

/**
 * Turns the loose signer rows every caller supplies into the strict rows the API wants.
 *
 * A row arrives from an admin form, a WooCommerce order, a WP-CLI flag or an `apply_filters`
 * caller, so the keys vary — `name` or `full_name`, `phone` or `whatsapp_phone_number` — and
 * nothing is guaranteed to be present. What comes out is uniform, and anything that cannot be
 * made uniform is a `WP_Error` rather than a request the API will reject.
 *
 * Stateless on purpose: this is a value transformation, and it runs both on the way into a
 * send and on the way into a cost estimate.
 */
final class Signers {

	/**
	 * Normalise signer rows into the shape the SDK and the API expect.
	 *
	 * Verification and notification methods are coupled server-side and only three pairings
	 * exist: `Email` with `["Email"]`, `Whatsapp` with `["Whatsapp"]`, and
	 * `DigitalCertificate` with either. Anything else is a 400, as is sending two
	 * notification methods, so the pairing is derived here rather than left to the caller.
	 *
	 * @param array<int|string, mixed> $signers Raw signer rows.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function normalize( array $signers ): array|WP_Error {
		$normalized = array();

		foreach ( $signers as $signer ) {
			if ( ! is_array( $signer ) ) {
				return new WP_Error(
					'assinafy_signer_incomplete',
					__( 'Every signer must be a complete signer row.', 'assinafy' )
				);
			}

			try {
				$row = self::row( $signer );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'assinafy_signer_incomplete', $e->getMessage() );
			}

			if ( $row instanceof WP_Error ) {
				return $row;
			}

			$normalized[] = $row;
		}

		if ( array() === $normalized ) {
			return new WP_Error(
				'assinafy_no_signers',
				__( 'Add at least one signer.', 'assinafy' )
			);
		}

		return $normalized;
	}

	/**
	 * Turn one raw signer row into the shape the SDK and the API expect.
	 *
	 * A row identifies its signer by an existing `id`, by an email address, or by a WhatsApp
	 * number, and carries a name unless the id already names one. Only the keys that carry a
	 * value are emitted: the API rejects an empty `email` or `whatsapp_phone_number` rather
	 * than ignoring it.
	 *
	 * @param array<string, mixed> $signer Raw signer row.
	 *
	 * @return array<string, mixed>|WP_Error The normalised row, or what is missing from it.
	 */
	private static function row( array $signer ): array|WP_Error {
		$fields = self::field_problem( $signer );
		if ( null !== $fields ) {
			return $fields;
		}

		$name = sanitize_text_field( (string) ( $signer['full_name'] ?? $signer['name'] ?? '' ) );

		// Validation reads what was typed; only the stored value is sanitised. `sanitize_email()`
		// repairs as well as strips — `jane@@example.com` and `jane@exam ple.com` both come back
		// as the valid-but-different `jane@example.com` — so checking the sanitised value would
		// accept a typo and send the signature request to whoever owns the repaired address.
		$raw_email = trim( (string) ( $signer['email'] ?? '' ) );
		$email     = sanitize_email( $raw_email );
		$phone     = self::normalize_phone( (string) ( $signer['whatsapp_phone_number'] ?? $signer['phone'] ?? '' ) );
		$id        = (string) ( $signer['id'] ?? '' );

		$problem = self::problem( $name, $raw_email, $email, $phone, $id );

		if ( $problem instanceof WP_Error ) {
			return $problem;
		}

		$verification = self::verification_method( $signer, '' !== $email || '' === $phone );

		$row = array(
			'verification_method'  => $verification,
			'notification_methods' => self::notification_methods( $signer, $verification ),
		);

		$row = array_merge(
			$row,
			array_filter(
				array(
					'id'                    => $id,
					'full_name'             => $name,
					'email'                 => $email,
					'whatsapp_phone_number' => $phone,
				),
				static fn( string $value ): bool => '' !== $value
			)
		);

		if ( isset( $signer['step'] ) ) {
			$step = filter_var( $signer['step'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
			if ( false === $step ) {
				return new WP_Error( 'assinafy_signer_incomplete', __( 'Signer steps must be positive whole numbers.', 'assinafy' ) );
			}
			$row['step'] = $step;
		}

		return self::contact_problem( $id, $verification, $email, $phone ) ?? $row;
	}


	/**
	 * Reject structured input before casting signer fields to text.
	 *
	 * @param array<string, mixed> $signer Raw signer.
	 * @return WP_Error|null Field error, or null.
	 */
	private static function field_problem( array $signer ): ?WP_Error {
		foreach ( array( 'full_name', 'name', 'email', 'whatsapp_phone_number', 'phone', 'id', 'verification_method' ) as $field ) {
			if ( isset( $signer[ $field ] ) && ! is_string( $signer[ $field ] ) ) {
				return new WP_Error(
					'assinafy_signer_incomplete',
					__( 'Every signer field must contain text.', 'assinafy' )
				);
			}
		}

		return null;
	}

	/**
	 * Require a valid id or the contact needed by the selected verification channel.
	 *
	 * @param string $id Signer id.
	 * @param string $verification Verification channel.
	 * @param string $email Email address.
	 * @param string $phone Phone number.
	 * @return WP_Error|null Contact error, or null.
	 */
	private static function contact_problem( string $id, string $verification, string $email, string $phone ): ?WP_Error {
		if ( '' !== $id && ! DocumentRecord::is_valid_id( $id ) ) {
			return new WP_Error( 'assinafy_signer_incomplete', __( 'The signer id is not valid.', 'assinafy' ) );
		}
		if ( '' === $id && ( AssignmentResource::VERIFICATION_DIGITAL_CERTIFICATE === $verification
			|| ( AssignmentResource::VERIFICATION_EMAIL === $verification && '' === $email )
			|| ( AssignmentResource::VERIFICATION_WHATSAPP === $verification && '' === $phone ) ) ) {
			return new WP_Error( 'assinafy_signer_incomplete', __( 'The selected verification method requires matching contact details, or an existing signer id for digital certificates.', 'assinafy' ) );
		}

		return null;
	}

	/**
	 * Check that one signer can be reached and named.
	 *
	 * An existing `id` already carries both on the account, so a row that has one needs
	 * nothing else. A row without one has to name the person and give the API somewhere to
	 * send the invitation.
	 *
	 * @param string $name      Sanitised full name.
	 * @param string $raw_email Email address exactly as it was entered.
	 * @param string $email     The same address after sanitising.
	 * @param string $phone     Phone number in E.164 form.
	 * @param string $id        Existing signer id.
	 *
	 * @return WP_Error|null What is missing, or null when the row is usable.
	 */
	private static function problem( string $name, string $raw_email, string $email, string $phone, string $id ): ?WP_Error {
		if ( '' === $name && '' === $id ) {
			return new WP_Error(
				'assinafy_signer_incomplete',
				__( 'Every signer needs a name.', 'assinafy' )
			);
		}

		// Judged on the entered value, not the sanitised one: an address that had to be repaired
		// to become valid is a typo, and sending to the repaired address reaches the wrong person.
		if ( '' !== $raw_email && ! is_email( $raw_email ) ) {
			return new WP_Error(
				'assinafy_signer_email',
				__( 'One of the signer email addresses is not valid.', 'assinafy' )
			);
		}

		if ( '' === $email && '' === $phone && '' === $id ) {
			return new WP_Error(
				'assinafy_signer_contact',
				__( 'Every signer needs an email address or a WhatsApp number.', 'assinafy' )
			);
		}

		return null;
	}


	/**
	 * Preserve the requested notification channel when the API supports the pairing.
	 *
	 * @param array<string, mixed> $signer Raw signer.
	 * @param string $verification Verification channel.
	 * @return array<int, string> The single supported notification channel.
	 * @throws \InvalidArgumentException When the requested pairing is unsupported.
	 */
	private static function notification_methods( array $signer, string $verification ): array {
		$methods = $signer['notification_methods'] ?? array(
			AssignmentResource::VERIFICATION_DIGITAL_CERTIFICATE === $verification ? AssignmentResource::NOTIFICATION_EMAIL : $verification,
		);
		if ( ! in_array( $methods, array( array( 'Email' ), array( 'Whatsapp' ) ), true )
			|| ( AssignmentResource::VERIFICATION_DIGITAL_CERTIFICATE !== $verification && $methods !== array( $verification ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
			throw new \InvalidArgumentException( __( 'The signer notification channel must match its verification method; digital certificates support Email or Whatsapp.', 'assinafy' ) );
		}
		return $methods;
	}

	/**
	 * Pick the verification method for one signer row.
	 *
	 * @param array<string, mixed> $signer    Raw signer row.
	 * @param bool                 $has_email Whether the row carries an email address.
	 */
	private static function verification_method( array $signer, bool $has_email ): string {
		$requested = (string) ( $signer['verification_method'] ?? '' );

		if ( in_array( $requested, AssignmentResource::VERIFICATION_METHODS, true ) ) {
			return $requested;
		}

		return $has_email
			? AssignmentResource::VERIFICATION_EMAIL
			: AssignmentResource::VERIFICATION_WHATSAPP;
	}


	/**
	 * Put a phone number into the E.164 form the SDK insists on.
	 *
	 * The API normalises a bare Brazilian number itself — `48999990000` comes back as
	 * `+5548999990000` — but `SignerResource::normalizePhoneNumber()` rejects anything
	 * without a country code before the request is made, so the same assumption is applied
	 * here for the 10 and 11 digit national formats.
	 *
	 * @param string $phone Raw input.
	 */
	private static function normalize_phone( string $phone ): string {
		$phone  = trim( $phone );
		$digits = preg_replace( '/\D/', '', $phone ) ?? '';

		if ( '' === $digits ) {
			return '';
		}

		if ( str_starts_with( $phone, '+' ) ) {
			return SignerResource::normalizePhoneNumber( '+' . $digits );
		}

		return SignerResource::normalizePhoneNumber(
			in_array( strlen( $digits ), array( 10, 11 ), true )
			? '+55' . $digits
			: '+' . $digits
		);
	}
}
