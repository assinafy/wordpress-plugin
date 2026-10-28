<?php
/**
 * Native forms for contract terms and lifecycle operations.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;

/** Renders separate, revision-bound forms without storing contract data locally. */
final class ContractEditor {

	/** @param array<string, mixed> $contract Current authoritative terms, or an absent contract. */
	public function render( AssinafyClient $client, string $document_id, array $contract ): void {
		$this->edit( $client, $document_id, $contract );
		if ( array() === $contract ) {
			$this->link_form( $client, $document_id );
			return;
		}
		$this->lifecycle( $client, $document_id, $contract );
	}

	/** @param array<string, mixed> $contract Current authoritative terms. */
	private function edit( AssinafyClient $client, string $document_id, array $contract ): void {
		$this->begin_form( $client, $document_id, 'update', $contract );
		echo '<h2>' . esc_html__( 'Contract terms', 'assinafy' ) . '</h2><table class="form-table"><tbody>';
		foreach ( ContractForm::fields() as $key => $field ) {
			if ( array() !== $contract && ! array_key_exists( $key, $contract ) ) {
				continue;
			}
			self::field( $key, $field[0], $field[1], $contract[ $key ] ?? '' );
		}
		echo '<tr><th scope="row"><label for="contract-status">' . esc_html__( 'Status', 'assinafy' ) . '</label></th><td><select id="contract-status" name="terms[status]">';
		$statuses = self::statuses( $contract );
		foreach ( $statuses as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, '', false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ' . esc_html( self::scalar( $contract['status'] ?? '' ) ) . '</td></tr>';
		if ( array() === $contract || isset( $contract['auto_renew'] ) ) {
			echo '<tr><th scope="row">' . esc_html__( 'Automatic renewal', 'assinafy' ) . '</th><td><input type="hidden" name="terms[auto_renew]" value="0" /><label><input type="checkbox" name="terms[auto_renew]" value="1"' . checked( ! empty( $contract['auto_renew'] ), true, false ) . ' /> ' . esc_html__( 'Renew automatically after the end date', 'assinafy' ) . '</label></td></tr>';
		}
		echo '</tbody></table><p class="description">' . esc_html__( 'Blank fields clear their current values. Other contract fields stay unchanged. Assinafy validates the counterparty CPF/CNPJ and plan permissions.', 'assinafy' ) . '</p>';
		submit_button( __( 'Save contract terms', 'assinafy' ) );
		echo '</form>';
	}

	/**
	 * @param array<string, mixed> $contract Current contract.
	 * @return array<string, string> Allowed native status choices.
	 */
	private static function statuses( array $contract ): array {
		$statuses = array(
			'' => __( 'Keep current status', 'assinafy' ),
		);
		if ( array() === $contract || 'draft' === ( $contract['status'] ?? '' ) ) {
			$statuses += array(
				'draft'  => __( 'Draft', 'assinafy' ),
				'active' => __( 'Active', 'assinafy' ),
			);
		}
		if ( array() === $contract ) {
			$statuses += array(
				'expired'    => __( 'Expired', 'assinafy' ),
				'terminated' => __( 'Terminated', 'assinafy' ),
			);
		}
		return $statuses;
	}

	/** @param array<string, mixed> $contract Current contract, including its term. */
	private function lifecycle( AssinafyClient $client, string $document_id, array $contract ): void {
		if ( 'terminated' === ( $contract['status'] ?? '' ) ) {
			return;
		}
		$this->begin_form( $client, $document_id, 'renew', $contract );
		echo '<h2>' . esc_html__( 'Renew contract', 'assinafy' ) . '</h2><input type="hidden" name="terms[term]" value="' . esc_attr( self::scalar( $contract['term'] ?? '' ) ) . '" /><table class="form-table">';
		self::field( 'end_date', __( 'New end date (optional)', 'assinafy' ), 'date', '', 'renew' );
		echo '</table><p>' . esc_html__( 'Leave the date blank to use the renewal term. A repeated request cannot renew the same term twice.', 'assinafy' ) . '</p>';
		submit_button( __( 'Renew contract', 'assinafy' ), 'secondary' );
		echo '</form>';
		$this->begin_form( $client, $document_id, 'terminate', $contract );
		echo '<h2>' . esc_html__( 'Terminate contract', 'assinafy' ) . '</h2><p>' . esc_html__( 'Termination stops lifecycle alerts and prevents renewal. It does not delete the document.', 'assinafy' ) . '</p><table class="form-table">';
		self::field( 'reason', __( 'Termination reason (optional)', 'assinafy' ), 'text', '' );
		echo '</table>';
		submit_button( __( 'Terminate contract', 'assinafy' ), 'secondary' );
		echo '</form>';
	}

	private function link_form( AssinafyClient $client, string $document_id ): void {
		$this->begin_form( $client, $document_id, 'link', array() );
		echo '<h2>' . esc_html__( 'Link to an existing contract', 'assinafy' ) . '</h2><p>' . esc_html__( 'Use this document as an amendment, renewal or termination. Its changes apply to the parent contract after certification.', 'assinafy' ) . '</p><table class="form-table">';
		self::field( 'parent_document_id', __( 'Parent document ID', 'assinafy' ), 'text', '' );
		echo '<tr><th><label for="contract-relation">' . esc_html__( 'Relationship', 'assinafy' ) . '</label></th><td><select id="contract-relation" name="terms[relation_type]">';
		foreach ( array(
			'amendment'   => __( 'Amendment', 'assinafy' ),
			'renewal'     => __( 'Renewal', 'assinafy' ),
			'termination' => __( 'Termination', 'assinafy' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		self::field( 'end_date', __( 'New or effective end date', 'assinafy' ), 'date', '', 'link' );
		self::field( 'value', __( 'New value (amendment or renewal only)', 'assinafy' ), 'number', '', 'link' );
		echo '</table>';
		submit_button( __( 'Link document', 'assinafy' ), 'secondary' );
		echo '</form>';
	}

	/** @param array<string, mixed> $contract Previously read terms, for optimistic locking. */
	public function begin_form( AssinafyClient $client, string $document_id, string $operation, array $contract ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		foreach ( array(
			'action'            => ContractActions::ACTION,
			'document'          => $document_id,
			'operation'         => $operation,
			'account'           => $client->getConfig()->getAccountId(),
			'environment'       => $client->getConfig()->getBaseUrl(),
			'terms[updated_at]' => self::scalar( $contract['updated_at'] ?? '' ),
		) as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		}
		wp_nonce_field( ContractActions::ACTION . '_' . $document_id );
	}

	private static function field( string $key, string $label, string $type, mixed $value, string $prefix = 'terms' ): void {
		$id = 'contract-' . $prefix . '-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" name="terms[' . esc_attr( $key ) . ']" value="' . esc_attr( self::scalar( $value ) ) . '"';
		if ( 'number' === $type ) {
			echo ' min="0" step="' . esc_attr( 'value' === $key ? '0.01' : '1' ) . '"';
		}
		echo ' /></td></tr>';
	}

	private static function scalar( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
