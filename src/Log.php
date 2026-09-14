<?php
/**
 * Plugin event log.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Http\LogRedactor;

/**
 * A capped ring buffer of plugin events, stored in a single non-autoloaded option.
 *
 * There is no custom table. Per-document history lives in the API (`documents()->activities()`)
 * and webhook delivery history in `webhooks()->dispatches()`; this buffer exists only to
 * explain what the plugin itself did — sends, sync runs, failures — when someone has to
 * work out why a signature request never arrived.
 *
 * Context is passed through the SDK's `LogRedactor` so an API key, token or signer access
 * code pulled into a context array never reaches the database.
 */
final class Log {

	public const OPTION = 'assinafy_log';

	/**
	 * Entries kept. Older ones are dropped from the front.
	 */
	private const LIMIT = 200;

	/**
	 * Record one event.
	 *
	 * @param string               $event   Short machine-readable event name, e.g. `send_failed`.
	 * @param array<string, mixed> $context Structured detail; redacted before storage.
	 */
	public function add( string $event, array $context = array() ): void {
		$entries = $this->entries();

		$entries[] = array(
			'time'    => time(),
			'event'   => $event,
			'context' => LogRedactor::redact( $context ),
		);

		if ( count( $entries ) > self::LIMIT ) {
			$entries = array_slice( $entries, -self::LIMIT );
		}

		update_option( self::OPTION, $entries, false );
	}

	/**
	 * Every stored entry, oldest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function entries(): array {
		$entries = get_option( self::OPTION, array() );

		return is_array( $entries ) ? array_values( $entries ) : array();
	}
}
