<?php
/**
 * Personal data held in deferred Assinafy sends.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Integrations\Hook;

/** Finds, exports and cancels matching WordPress cron requests. */
final class PendingSendStore {

	/** @return array<int, array<string, mixed>> Matching sends in WordPress's export shape. */
	public function export( string $email ): array {
		$data = array();
		foreach ( $this->matching( $email ) as $pending ) {
			$request  = $pending['request'];
			$signer   = $pending['signer'];
			$document = isset( $request['attachment_id'] )
				? get_the_title( (int) $request['attachment_id'] )
				: basename( (string) ( $request['file_path'] ?? '' ) );

			$data[] = array(
				'group_id'    => 'assinafy-signature-requests',
				'group_label' => __( 'Assinafy signature requests', 'assinafy' ),
				'item_id'     => 'assinafy-pending-' . md5( $pending['timestamp'] . (string) wp_json_encode( $pending['args'] ) ),
				'data'        => array(
					array(
						'name'  => __( 'Document', 'assinafy' ),
						'value' => $document,
					),
					array(
						'name'  => __( 'Status', 'assinafy' ),
						'value' => __( 'Pending asynchronous send', 'assinafy' ),
					),
					array(
						'name'  => __( 'Signer name', 'assinafy' ),
						'value' => (string) ( $signer['full_name'] ?? $signer['name'] ?? '' ),
					),
					array(
						'name'  => __( 'Signer email', 'assinafy' ),
						'value' => (string) ( $signer['email'] ?? '' ),
					),
					array(
						'name'  => __( 'Scheduled', 'assinafy' ),
						'value' => gmdate( 'Y-m-d H:i:s', $pending['timestamp'] ),
					),
				),
			);
		}

		return $data;
	}

	/** Cancel matching deferred sends before they can recreate erased data. */
	public function remove( string $email ): int {
		$removed = 0;
		foreach ( $this->matching( $email ) as $pending ) {
			if ( true === wp_unschedule_event( $pending['timestamp'], Hook::ACTION, $pending['args'] ) ) {
				++$removed;
			}
		}

		return $removed;
	}

	/** @return array<int, array{timestamp: int, args: list<mixed>, request: array<string, mixed>, signer: array<string, mixed>}> */
	private function matching( string $email ): array {
		$matches = array();
		foreach ( _get_cron_array() as $timestamp => $hooks ) {
			$events = is_array( $hooks[ Hook::ACTION ] ?? null ) ? $hooks[ Hook::ACTION ] : array();
			foreach ( $events as $event ) {
				$args    = is_array( $event['args'] ?? null ) ? array_values( $event['args'] ) : array();
				$request = is_array( $args[0] ?? null ) ? $args[0] : array();
				$signers = is_array( $request['signers'] ?? null ) ? $request['signers'] : array();

				foreach ( $signers as $signer ) {
					if ( self::matches( $signer, $email ) ) {
						$matches[] = array(
							'timestamp' => (int) $timestamp,
							'args'      => $args,
							'request'   => $request,
							'signer'    => $signer,
						);
						break;
					}
				}
			}
		}

		return $matches;
	}

	private static function matches( mixed $signer, string $email ): bool {
		return is_array( $signer )
			&& is_string( $signer['email'] ?? null )
			&& 0 === strcasecmp( $signer['email'], $email );
	}
}
