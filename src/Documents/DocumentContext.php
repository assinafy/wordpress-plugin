<?php
/**
 * Immutable remote ownership for local document mirrors.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;

/** Prevents an old mirror from being reinterpreted under another workspace or API. */
final class DocumentContext {

	public const META_ACCOUNT_ID  = '_assinafy_account_id';
	public const META_ENVIRONMENT = '_assinafy_environment';

	/** Bind a legacy mirror once; an existing binding is immutable. */
	public static function bind( int $post_id, string $account_id, string $environment ): bool {
		return ProjectionLock::run(
			$post_id,
			static fn(): bool => self::bind_unlocked( $post_id, $account_id, $environment )
		);
	}

	/** Bind while holding the record's projection lock. */
	private static function bind_unlocked( int $post_id, string $account_id, string $environment ): bool {
		if ( '' === $account_id || '' === $environment ) {
			return false;
		}

		$current_account     = self::value( $post_id, self::META_ACCOUNT_ID );
		$current_environment = self::value( $post_id, self::META_ENVIRONMENT );
		if ( ( '' !== $current_account && $current_account !== $account_id )
			|| ( '' !== $current_environment && $current_environment !== $environment ) ) {
			return false;
		}

		update_post_meta( $post_id, self::META_ACCOUNT_ID, $account_id );
		update_post_meta( $post_id, self::META_ENVIRONMENT, $environment );

		return self::matches( $post_id, $account_id, $environment );
	}

	/** Whether a mirror belongs to this exact workspace and API root. */
	public static function matches( int $post_id, string $account_id, string $environment ): bool {
		return $account_id === self::value( $post_id, self::META_ACCOUNT_ID )
			&& $environment === self::value( $post_id, self::META_ENVIRONMENT );
	}

	/** Whether a mirror has any recorded remote ownership. */
	public static function is_bound( int $post_id ): bool {
		return ! self::matches( $post_id, '', '' );
	}

	/** Return the configured client only when it owns the mirror. */
	public static function matching_client( int $post_id, ?AssinafyClient $client ): ?AssinafyClient {
		return null !== $client
			&& self::matches( $post_id, $client->getConfig()->getAccountId(), $client->getConfig()->getBaseUrl() )
			? $client
			: null;
	}

	/** Verify a legacy mirror before adopting it, or reject a conflicting binding. */
	public static function verify_or_bind( int $post_id, AssinafyClient $client, string $document_id ): void {
		$account_id  = $client->getConfig()->getAccountId();
		$environment = $client->getConfig()->getBaseUrl();
		if ( self::matches( $post_id, $account_id, $environment ) ) {
			return;
		}
		if ( self::is_bound( $post_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The eventual UI consumer escapes this translated failure.
			throw new \RuntimeException( __( 'This document belongs to a different Assinafy workspace or environment.', 'assinafy' ) );
		}

		$document = $client->documents()->get( $document_id );
		StatusSync::assert_document( $document, $document_id, $account_id );
		self::bind_or_fail( $post_id, $account_id, $environment );
	}

	/** Bind a verified mirror or raise a storage failure. */
	public static function bind_or_fail( int $post_id, string $account_id, string $environment ): void {
		if ( ! self::bind( $post_id, $account_id, $environment ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The eventual UI consumer escapes this translated failure.
			throw new \RuntimeException( __( 'The document workspace binding could not be saved.', 'assinafy' ) );
		}
	}

	/** Whether a panel should bind a legacy record or refresh a stale open one. */
	public static function needs_refresh( int $post_id, bool $closed, int $synced_at, int $stale_after ): bool {
		return ! self::is_bound( $post_id ) || ( ! $closed && ( time() - $synced_at ) > $stale_after );
	}

	private static function value( int $post_id, string $key ): string {
		$value = get_post_meta( $post_id, $key, true );

		return is_string( $value ) ? $value : '';
	}
}
