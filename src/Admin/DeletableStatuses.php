<?php
/**
 * Which document statuses Assinafy will accept a delete for.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\ClientFactory;

/**
 * The API's own `deletable` flag per status code, cached for a day.
 *
 * Asked before the Cancel button is offered. The platform can add statuses, so the list is
 * read from the API rather than hard-coded; the hard-coded map is only what answers while
 * the API is unreachable.
 */
final class DeletableStatuses {

	/**
	 * Transient caching the `deletable` flag per status code.
	 */
	private const STATUSES_TRANSIENT = 'assinafy_document_statuses';

	/**
	 * The `deletable` flag per status, used when the API's own list is unreachable.
	 *
	 * Falling back to a shorter list than the API's is deliberate: an unknown status resolves
	 * to "not deletable", which hides the Cancel button rather than offering one that 400s.
	 *
	 * @var array<string, bool>
	 */
	private const FALLBACK_DELETABLE = array(
		'uploading'           => false,
		'uploaded'            => false,
		'metadata_processing' => false,
		'metadata_ready'      => true,
		'pending_signature'   => true,
		'certificating'       => false,
		'certificated'        => false,
		'rejected_by_signer'  => true,
		'rejected_by_user'    => true,
		'expired'             => true,
		'failed'              => true,
	);

	/**
	 * @param ClientFactory $clients API client factory.
	 */
	public function __construct( private readonly ClientFactory $clients ) {
	}

	/**
	 * Whether a document in this status can be deleted.
	 *
	 * The flag comes from the API's own status list, cached for a day, because the platform
	 * can add statuses. An unknown status is not deletable.
	 *
	 * @param string $status Current status code.
	 */
	public function allows( string $status ): bool {
		$map = get_transient( self::STATUSES_TRANSIENT );

		if ( ! is_array( $map ) ) {
			$map    = self::FALLBACK_DELETABLE;
			$client = $this->clients->client();

			if ( null !== $client ) {
				try {
					$fresh = array();

					foreach ( $client->documents()->statuses() as $row ) {
						if ( is_array( $row ) && is_string( $row['code'] ?? null ) ) {
							$fresh[ $row['code'] ] = (bool) ( $row['deletable'] ?? false );
						}
					}

					if ( array() !== $fresh ) {
						$map = $fresh;
					}
				} catch ( \Throwable $e ) {
					unset( $e );
				}
			}

			set_transient( self::STATUSES_TRANSIENT, $map, DAY_IN_SECONDS );
		}

		return (bool) ( $map[ $status ] ?? false );
	}
}
