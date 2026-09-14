<?php
/**
 * Preserve signer erasure across incomplete uploads and later authoritative responses.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);
namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Pure signer projection; DocumentRecord remains the only owner of document meta keys.
 * @phpstan-type Signer array{id:string,name:string,email:string,step:int,notified:bool,completed:bool,signing_url:string}
 */
final class SignerRedaction {
	/**
	 * @param array<int,Signer> $incoming Incoming rows.
	 * @param array<int,Signer> $previous Previously stored rows, including legacy redactions.
	 * @param array<string,string> $registry Remote IDs and site-keyed email digests to tokens.
	 * @param array{}|array{email:string,token:string} $erasure Pending contact erasure.
	 * @return array{signers:array<int,Signer>,registry:array<string,string>}
	 */
	public static function apply( array $incoming, array $previous, array $registry, array $erasure ): array {
		if ( array() !== $erasure ) {
			$registry[ self::email_key( $erasure['email'] ) ] = $erasure['token'];
		}
		foreach ( array_merge( $previous, $incoming ) as $signer ) {
			if ( '' !== $signer['id'] && str_starts_with( $signer['email'], '[redacted-' ) ) {
				$registry[ $signer['id'] ] = $signer['email'];
			}
		}
		foreach ( $incoming as &$signer ) {
			$signer = self::redact( $signer, $registry );
		}
		unset( $signer );
		return array(
			'signers'  => array_values( $incoming ),
			'registry' => $registry,
		);
	}

	/**
	 * @param Signer $signer Incoming row.
	 * @param array<string,string> $registry Durable identities.
	 * @return Signer
	 */
	private static function redact( array $signer, array &$registry ): array {
		$token = $registry[ $signer['id'] ] ?? $registry[ self::email_key( $signer['email'] ) ] ?? null;
		if ( null === $token ) {
			return $signer;
		}
		if ( '' !== $signer['id'] ) {
			$registry[ $signer['id'] ] = $token;
		}
		$signer['name']        = $token;
		$signer['email']       = $token;
		$signer['signing_url'] = '';
		return $signer;
	}

	/** @param string $email Address whose erasure must survive a newly assigned remote ID. */
	private static function email_key( string $email ): string {
		return 'email:' . hash_hmac( 'sha256', strtolower( trim( $email ) ), wp_salt( 'auth' ) );
	}
}
