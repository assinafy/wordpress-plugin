<?php
/**
 * Contract tracking through WP-CLI.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Resources\ContractResource;
use WP_CLI;

/** Exposes the workspace's contract terms without copying them into WordPress. */
final class ContractCli {

	/** @param ClientFactory $clients Configured API client factory. */
	public function __construct( private readonly ClientFactory $clients ) {
	}

	/**
	 * Read or change contract terms, amendments, renewals and terminations.
	 *
	 * Prints the SDK result as JSON. Reads require documents:read; writes require
	 * documents:write and a plan with contract tracking. IDs are remote document IDs.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : Contract operation.
	 * ---
	 * options:
	 *   - list
	 *   - get
	 *   - update
	 *   - related
	 *   - renew
	 *   - terminate
	 * ---
	 *
	 * [<document-id>]
	 * : Remote document ID. Required except for list.
	 *
	 * [--data=<json>]
	 * : JSON object for update, renew or terminate. Include updated_at to detect conflicts.
	 *
	 * [--filters=<json>]
	 * : JSON object of scalar list filters, such as status, counterparty or external_id.
	 *
	 * [--page=<number>]
	 * : List page, starting at 1. Default: 1.
	 *
	 * [--per-page=<number>]
	 * : Contracts per page, from 1 to 100. Default: 25.
	 *
	 * [--yes]
	 * : Confirm termination without prompting.
	 *
	 * ## EXAMPLES
	 *
	 *     wp assinafy contract list --filters='{"status":"active","ending_within_days":45}'
	 *     wp assinafy contract get DOCUMENT_ID
	 *     wp assinafy contract update DOCUMENT_ID --data='{"counterparty_name":"Example Company","end_date":"2027-12-31"}'
	 *     wp assinafy contract related DOCUMENT_ID
	 *     wp assinafy contract renew DOCUMENT_ID --data='{"term":1}'
	 *     wp assinafy contract terminate DOCUMENT_ID --data='{"reason":"Completed"}' --yes
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Command options.
	 */
	public function contract( array $args, array $assoc_args ): void {
		try {
			$action  = self::action( $args );
			$data    = self::json_object( $assoc_args['data'] ?? '{}', 'data' );
			$filters = self::filters( $assoc_args['filters'] ?? '{}' );
			$page    = self::page( $assoc_args['page'] ?? '1', PHP_INT_MAX, 'page' );
			$perpage = self::page( $assoc_args['per-page'] ?? '25', 100, 'per-page' );
			self::validate_data( $action, $data );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
			return;
		}

		if ( 'terminate' === $action ) {
			WP_CLI::confirm( 'Terminate this contract and stop its lifecycle alerts?', $assoc_args );
		}
		$client = $this->clients->client();
		if ( null === $client ) {
			WP_CLI::error( $this->clients->error()?->get_error_message() ?? 'Connect the plugin to Assinafy first.' );
			return;
		}

		try {
			$result = 'list' === $action
				? $client->contracts()->list( $page, $perpage, $filters )
				: self::document_action( $client->contracts(), $action, $args[1], $data );
			$json   = wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false === $json ) {
				throw new \RuntimeException( 'The contract response could not be encoded as JSON.' );
			}
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
			return;
		}

		WP_CLI::line( $json );
	}

	/** @param array<int, string> $args Positional arguments. */
	private static function action( array $args ): string {
		$action = $args[0] ?? '';
		if ( ! in_array( $action, array( 'list', 'get', 'update', 'related', 'renew', 'terminate' ), true ) ) {
			throw new \InvalidArgumentException( 'Choose list, get, update, related, renew or terminate.' );
		}
		$expected = 'list' === $action ? 1 : 2;
		if ( count( $args ) !== $expected || ( 2 === $expected && '' === trim( $args[1] ) ) ) {
			throw new \InvalidArgumentException( 'Pass one remote document ID except when listing contracts.' );
		}

		return $action;
	}

	/**
	 * Preserve nested JSON objects, including empty metadata objects.
	 *
	 * @param mixed $value JSON command option.
	 * @param string $name Option name for errors.
	 * @return array<string, mixed>
	 */
	private static function json_object( mixed $value, string $name ): array {
		$decoded = is_string( $value ) ? json_decode( $value, false, 512, JSON_THROW_ON_ERROR ) : null;
		if ( ! $decoded instanceof \stdClass ) {
			throw new \InvalidArgumentException( '--' . $name . ' must be a JSON object.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI plain-text error.
		}
		$fields = get_object_vars( $decoded );
		foreach ( array_keys( $fields ) as $key ) {
			if ( ! is_string( $key ) ) {
				throw new \InvalidArgumentException( 'JSON field names must be strings.' );
			}
		}

		return $fields;
	}

	/**
	 * @param mixed $value JSON list filters.
	 * @return array<string, scalar>
	 */
	private static function filters( mixed $value ): array {
		$filters = self::json_object( $value, 'filters' );
		foreach ( $filters as $filter ) {
			if ( ! is_scalar( $filter ) ) {
				throw new \InvalidArgumentException( 'Contract list filters must have scalar values.' );
			}
		}

		/** @var array<string, scalar> $filters */
		return $filters;
	}

	/**
	 * @param mixed $value Page option.
	 * @param int $maximum Largest accepted value.
	 * @param string $name Option name.
	 */
	private static function page( mixed $value, int $maximum, string $name ): int {
		$number = filter_var(
			$value,
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => 1,
					'max_range' => $maximum,
				),
			)
		);
		if ( false === $number ) {
			throw new \InvalidArgumentException( '--' . $name . ' must be an integer between 1 and ' . $maximum . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI plain-text error.
		}

		return $number;
	}

	/**
	 * @param string $action Requested operation.
	 * @param array<string, mixed> $data Contract or amendment fields.
	 */
	private static function validate_data( string $action, array $data ): void {
		if ( 'update' === $action && array() === $data ) {
			throw new \InvalidArgumentException( 'Updating a contract requires at least one field in --data.' );
		}
		if ( 'renew' === $action && ! isset( $data['term'] ) && ! isset( $data['end_date'] ) ) {
			throw new \InvalidArgumentException( 'Renewing a contract requires its current term or a new end_date in --data.' );
		}
	}

	/**
	 * @param ContractResource $contracts SDK contract resource.
	 * @param string $action Validated operation.
	 * @param string $document_id Remote document ID.
	 * @param array<string, mixed> $data Request fields.
	 * @return array<string, mixed>
	 */
	private static function document_action( ContractResource $contracts, string $action, string $document_id, array $data ): array {
		return match ( $action ) {
			'get' => $contracts->get( $document_id ),
			'update' => $contracts->update( $document_id, $data ),
			'related' => $contracts->related( $document_id ),
			'renew' => $contracts->renew( $document_id, $data ),
			'terminate' => $contracts->terminate( $document_id, $data ),
			default => throw new \InvalidArgumentException( 'Unknown contract operation.' ),
		};
	}
}
