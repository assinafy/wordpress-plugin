<?php
/**
 * A recoverable upload and assignment attempt.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Resources\AssignmentResource;
use Assinafy\WP\Log;
use WP_Error;

/**
 * Records each irreversible step before moving on to the next one.
 *
 * The SDK composite cannot return the uploaded document when assignment creation throws.
 * Using its public resource methods keeps that document recoverable on this site. A retry
 * re-reads the saved document and only creates an assignment when none exists yet.
 */
final class SendAttempt {

	/**
	 * @param DocumentRecord $records Local mirror.
	 * @param Log $log Plugin log.
	 * @param CostEstimate $cost Affordability check, addressed to the uploaded document.
	 */
	public function __construct( private readonly DocumentRecord $records, private readonly Log $log, private readonly CostEstimate $cost ) {
	}

	/**
	 * Resolve signers, reserve a record, upload, price and assign; resume a saved upload on retry.
	 *
	 * Uses GET/POST /accounts/{id}/signers, POST /accounts/{id}/documents,
	 * POST /documents/{id}/assignments/estimate-cost and POST /documents/{id}/assignments
	 * with the payloads documented on SendService::send(). A retry starts with
	 * GET /documents/{id}; its embedded assignment is authoritative.
	 *
	 * The estimate sits between the upload and the assignment because it is the only place
	 * it can: the route must be addressed to a document that has not been assigned yet, and
	 * the assignment is what spends the allowance, so a refusal here still costs nothing.
	 *
	 * @param AssinafyClient $client Configured client.
	 * @param array{file: string, signers: array<int, array<string, mixed>>, lock: string} $prepared Validated inputs.
	 * @param array<string, mixed> $args Send arguments with resolved message and expiry.
	 * @param int $existing Previously reserved record for this send key, or zero.
	 * @return int|WP_Error Completed record, or an error carrying its recovery post id.
	 */
	public function run( AssinafyClient $client, array $prepared, array $args, int $existing ): int|WP_Error {
		$post_id  = $existing;
		$document = array();
		if ( $existing > 0 && '' !== $this->records->assignment_id( $existing ) ) {
			return $existing;
		}
		try {
			if ( $existing > 0 ) {
				$document = $this->resume( $client, $existing );
			}
			if ( 0 === $existing ) {
				$signers  = $this->resolve_signers( $client, $prepared['signers'] );
				$post_id  = $this->reserve( $args, $prepared['file'], $prepared['lock'] );
				$document = $this->upload( $client, $post_id, $prepared['file'] );
			}

			if ( empty( $document['assignment']['id'] ) ) {
				$refusal = $this->cost->refusal( $prepared['signers'], (string) $document['id'] );
				if ( null !== $refusal ) {
					$this->records->set_last_error( $post_id, $refusal->get_error_message() );
					return $refusal;
				}
				$signers              ??= $this->resolve_signers( $client, $prepared['signers'] );
				$document['assignment'] = $this->assign( $client, (string) $document['id'], $signers, $args );
			}

			$this->records->hydrate_from_api( $post_id, $document );
			$this->records->set_last_error( $post_id, '' );
			$this->log->add(
				'sent',
				array(
					'post_id'     => $post_id,
					'document_id' => $document['id'],
					'signers'     => count( $prepared['signers'] ),
				)
			);
			return $post_id;
		} catch ( \Throwable $e ) {
			if ( $post_id > 0 ) {
				$this->records->set_last_error( $post_id, $e->getMessage() );
			}
			$this->log->add(
				'send_failed',
				array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
					'type'    => get_debug_type( $e ),
				)
			);
			return new WP_Error( 'assinafy_send_failed', $e->getMessage(), array( 'post_id' => $post_id ) );
		}
	}

	/**
	 * Reject duplicate participants and invalid signing order before consuming an upload.
	 *
	 * @param array<int, array<string, mixed>> $signers Normalized rows.
	 * @return WP_Error|null A refusal or null when the sequence is usable.
	 */
	public static function sequence_problem( array $signers ): ?WP_Error {
		$identities = array_map( static fn( array $row ): string => strtolower( (string) ( $row['id'] ?? $row['email'] ?? $row['whatsapp_phone_number'] ?? '' ) ), $signers );
		$steps      = array_column( $signers, 'step' );
		$unique     = array_unique( $steps );
		sort( $unique );
		if ( count( array_unique( $identities ) ) !== count( $signers )
			|| ( array() !== $steps && ( count( $steps ) !== count( $signers ) || $unique !== range( 1, count( $unique ) ) ) ) ) {
			return new WP_Error( 'assinafy_signer_incomplete', __( 'Signers must be unique, and their signing steps must start at 1 without gaps.', 'assinafy' ) );
		}
		$counts = array_count_values( array_map( static fn( array $row ): int => (int) ( $row['step'] ?? 1 ), $signers ) );
		foreach ( $signers as $row ) {
			if ( AssignmentResource::VERIFICATION_DIGITAL_CERTIFICATE === $row['verification_method'] && $counts[ (int) ( $row['step'] ?? 1 ) ] > 1 ) {
				return new WP_Error( 'assinafy_signer_incomplete', __( 'A digital certificate signer must have a signing step of their own.', 'assinafy' ) );
			}
		}
		return null;
	}


	/**
	 * Upload once and save its identity before requesting any signatures.
	 *
	 * @param AssinafyClient $client Configured client.
	 * @param int $post_id Reserved mirror.
	 * @param string $file Validated PDF path.
	 * @return array<string, mixed> Uploaded document.
	 * @throws \RuntimeException When the remote id cannot be preserved.
	 */
	private function upload( AssinafyClient $client, int $post_id, string $file ): array {
		$document = $client->documents()->upload( $file );
		$this->assert_uploaded( $document );
		$this->records->hydrate_from_api( $post_id, $document );
		if ( $this->records->document_id( $post_id ) !== $document['id'] ) {
			$this->log->add(
				'send_orphaned',
				array(
					'post_id'     => $post_id,
					'document_id' => $document['id'],
				)
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
			throw new \RuntimeException( __( 'The uploaded document id could not be saved. Check the Assinafy account before retrying.', 'assinafy' ) );
		}
		return $document;
	}

	/**
	 * Create the document's single virtual assignment and verify its receipt.
	 *
	 * @param AssinafyClient $client Configured client.
	 * @param string $document_id Saved document id.
	 * @param array<int, array<string, mixed>> $signers Resolved assignment signers.
	 * @param array<string, mixed> $args Send arguments.
	 * @return array<string, mixed> Created assignment.
	 * @throws \UnexpectedValueException When the assignment response has no id.
	 */
	private function assign( AssinafyClient $client, string $document_id, array $signers, array $args ): array {
		$assignment = $client->assignments()->create(
			$document_id,
			$signers,
			AssignmentResource::METHOD_VIRTUAL,
			array_filter( array_intersect_key( $args, array_flip( array( 'message', 'expires_at' ) ) ), static fn( $value ): bool => null !== $value )
		);
		if ( ! is_string( $assignment['id'] ?? null ) || '' === $assignment['id'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
			throw new \UnexpectedValueException( __( 'Assinafy returned no assignment id. Retry to check whether signatures were requested.', 'assinafy' ) );
		}
		return $assignment;
	}

	/**
	 * Resolve identities before uploading, using the SDK's exact paginated email lookup.
	 *
	 * GET /accounts/{id}/signers?search={email} returns a list of signer objects; if absent,
	 * POST /accounts/{id}/signers receives {full_name,email?,whatsapp_phone_number?} and
	 * returns the created signer. Assignment rows retain id and signing options only.
	 *
	 * @param AssinafyClient $client Configured client.
	 * @param array<int, array<string, mixed>> $signers Normalized rows.
	 * @return array<int, array<string, mixed>> Assignment signers.
	 * @throws \UnexpectedValueException When a signer response carries no usable id.
	 */
	private function resolve_signers( AssinafyClient $client, array $signers ): array {
		$resolved = array();
		foreach ( $signers as $signer ) {
			$id = $signer['id'] ?? null;
			if ( null === $id ) {
				$email   = isset( $signer['email'] ) ? (string) $signer['email'] : null;
				$found   = null === $email ? null : $client->signers()->findByEmail( $email );
				$found ??= $client->signers()->create(
					(string) ( $signer['full_name'] ?? '' ),
					$email,
					isset( $signer['whatsapp_phone_number'] ) ? (string) $signer['whatsapp_phone_number'] : null
				);
				$id      = $found['id'] ?? null;
			}
			if ( ! is_string( $id ) || ! DocumentRecord::is_valid_id( $id ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
				throw new \UnexpectedValueException( __( 'Assinafy returned no valid signer id.', 'assinafy' ) );
			}
			$resolved[] = array( 'id' => $id ) + array_intersect_key( $signer, array_flip( array( 'verification_method', 'notification_methods', 'step' ) ) );
		}
		return $resolved;
	}

	/**
	 * A saved upload is reused even after the transient expires or was evicted.
	 *
	 * @param AssinafyClient $client Configured client.
	 * @param int $post_id Reserved local record.
	 * @return array<string, mixed> Authoritative document, with its assignment when present.
	 * @throws \RuntimeException When the upload outcome is unknown.
	 */
	private function resume( AssinafyClient $client, int $post_id ): array {
		$document_id = $this->records->document_id( $post_id );
		if ( '' === $document_id ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
			throw new \RuntimeException( __( 'The previous upload outcome is unknown. Check the Assinafy account before starting a new send with a new idempotency key.', 'assinafy' ) );
		}
		$document = $client->documents()->get( $document_id );
		StatusSync::assert_document( $document, $document_id, $client->getConfig()->getAccountId() );
		return $document;
	}

	/**
	 * Save the reservation before the API can consume a document allowance.
	 *
	 * @param array<string, mixed> $args Send arguments.
	 * @param string $file PDF path.
	 * @param string $key Durable idempotency key.
	 * @return int The reserved record.
	 * @throws \RuntimeException When the reservation cannot be written.
	 */
	private function reserve( array $args, string $file, string $key ): int {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		if ( $post_id > 0 && '' !== $this->records->document_id( $post_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
			throw new \RuntimeException( __( 'This record already mirrors a document. Start a new send without post_id to keep its signature history.', 'assinafy' ) );
		}
		if ( DocumentPostType::POST_TYPE !== get_post_type( $post_id ) ) {
			$inserted = wp_insert_post(
				array(
					'post_type'   => DocumentPostType::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => basename( $file ),
					'post_author' => get_current_user_id(),
				),
				true
			);
			if ( $inserted instanceof WP_Error || $inserted < 1 ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
				throw new \RuntimeException( __( 'The local document record could not be created. Nothing was uploaded.', 'assinafy' ) );
			}
			$post_id = $inserted;
		}
		if ( isset( $args['source'] ) && ! $this->records->set_source( $post_id, $args['source'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
			throw new \RuntimeException( __( 'The integration source could not be saved. Nothing was uploaded.', 'assinafy' ) );
		}
		$this->records->set_send_key( $post_id, $key );
		if ( ( new DocumentIndex() )->find_by_send_key( $key ) !== $post_id ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
			throw new \RuntimeException( __( 'The send reservation could not be saved. Nothing was uploaded.', 'assinafy' ) );
		}
		return $post_id;
	}

	/**
	 * Reject a malformed upload response before recording a success.
	 *
	 * @param array<string, mixed> $document Upload response.
	 * @throws \UnexpectedValueException When the upload did not identify the document.
	 */
	private function assert_uploaded( array $document ): void {
		if ( ! is_string( $document['id'] ?? null ) || ! DocumentRecord::is_valid_id( $document['id'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
			throw new \UnexpectedValueException( __( 'Upload returned no valid document id. Check the Assinafy account before retrying.', 'assinafy' ) );
		}
	}
}
