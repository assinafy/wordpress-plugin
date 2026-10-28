<?php
/**
 * Explicit contract mutations in the native admin.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Notice::render() and ContractScreen::render() escape these plain-text errors at display time.

use Assinafy\WP\Capabilities;
use Assinafy\WP\ClientFactory;

/** Validates each submitted contract operation and preserves its revision precondition. */
final class ContractActions {

	public const ACTION = 'assinafy_contract';
	public const FLASH  = 'assinafy_contract_notice_';

	/** @param ClientFactory $clients Connected workspace client. */
	public function __construct( private readonly ClientFactory $clients ) {
	}

	/** Register the authenticated native form handler. */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'submit' ) );
	}

	/** Validate identity, nonce, workspace and form data before changing contract terms. */
	public function submit(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to manage contracts.', 'assinafy' ), '', array( 'response' => 403 ) );
		}
		$document_id = isset( $_POST['document'] ) && is_string( $_POST['document'] ) ? sanitize_text_field( wp_unslash( $_POST['document'] ) ) : '';
		check_admin_referer( self::ACTION . '_' . $document_id );
		try {
			ContractForm::validate_id( $document_id );
			$operation = ContractForm::text( wp_unslash( $_POST['operation'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ContractForm::text() rejects non-strings and sanitizes the value.
			$data      = $this->payload( $operation );
			$client    = $this->clients->client() ?? throw new \RuntimeException( $this->clients->error()?->get_error_message() ?? __( 'Connect the plugin to Assinafy first.', 'assinafy' ) );
			if ( $client->getConfig()->getAccountId() !== ContractForm::text( wp_unslash( $_POST['account'] ?? '' ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ContractForm::text() validates and sanitizes.
				|| $client->getConfig()->getBaseUrl() !== ContractForm::text( wp_unslash( $_POST['environment'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ContractForm::text() validates and sanitizes.
				throw new \RuntimeException( __( 'The connected workspace changed. Reload the contract before saving.', 'assinafy' ) );
			}
			$resource = $client->contracts();
			$saved    = match ( $operation ) {
				'update', 'link', 'unlink' => $resource->update( $document_id, $data ),
				'renew' => $resource->renew( $document_id, $data ),
				'terminate' => $resource->terminate( $document_id, $data ),
			};
			ContractForm::assert_contract( $saved, $document_id );
			Notice::set( self::FLASH, 'success', __( 'Contract saved.', 'assinafy' ) );
		} catch ( \Throwable $error ) {
			Notice::set( self::FLASH, 'error', $error->getMessage() );
		}
		wp_safe_redirect( ContractScreen::url( $document_id ) );
		exit;
	}

	/** @return array<string, mixed> Validated operation body. */
	private function payload( string $operation ): array {
		$input = wp_unslash( $_POST['terms'] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- submit() verifies the nonce; every field is validated below with ContractForm.
		if ( ! is_array( $input ) ) {
			throw new \InvalidArgumentException( __( 'Invalid contract fields.', 'assinafy' ) );
		}
		$data = match ( $operation ) {
			'update' => ContractForm::parse( $input ),
			'link' => $this->link( $input ),
			'unlink' => array( 'parent_document_id' => null ),
			'renew' => $this->renewal( $input ),
			'terminate' => array( 'reason' => ContractForm::text( $input['reason'] ?? '' ) ),
			default => throw new \InvalidArgumentException( __( 'Choose a valid contract action.', 'assinafy' ) ),
		};
		if ( 'terminate' === $operation && mb_strlen( $data['reason'] ) > 500 ) {
			throw new \InvalidArgumentException( __( 'The termination reason must contain at most 500 characters.', 'assinafy' ) );
		}
		$updated_at = ContractForm::text( $input['updated_at'] ?? '' );
		if ( '' !== $updated_at ) {
			$data['updated_at'] = $updated_at;
		}
		return $data;
	}

	/**
	 * @param array<string, mixed> $input Form fields.
	 * @return array<string, mixed> Link body.
	 */
	private function link( array $input ): array {
		$parent = ContractForm::text( $input['parent_document_id'] ?? '' );
		ContractForm::validate_id( $parent );
		$relation = ContractForm::text( $input['relation_type'] ?? '' );
		if ( ! in_array( $relation, array( 'amendment', 'renewal', 'termination' ), true ) ) {
			throw new \InvalidArgumentException( __( 'Choose a valid contract relationship.', 'assinafy' ) );
		}
		$terms = ContractForm::parse( array_intersect_key( $input, array_flip( array( 'value', 'end_date' ) ) ) );
		return array_merge(
			array(
				'parent_document_id' => $parent,
				'relation_type'      => $relation,
			),
			array_filter( $terms, static fn( mixed $value ): bool => null !== $value )
		);
	}

	/**
	 * @param array<string, mixed> $input Form fields.
	 * @return array<string, mixed> Renewal body.
	 */
	private function renewal( array $input ): array {
		$term = ContractForm::text( $input['term'] ?? '' );
		if ( ! ctype_digit( $term ) || (float) $term < 1 || (float) $term > PHP_INT_MAX ) {
			throw new \InvalidArgumentException( __( 'Reload the contract to read its current term before renewing.', 'assinafy' ) );
		}
		$data = ContractForm::parse( array_intersect_key( $input, array( 'end_date' => true ) ) );
		return array( 'term' => (int) $term ) + array_filter( $data, static fn( mixed $value ): bool => null !== $value );
	}
}
