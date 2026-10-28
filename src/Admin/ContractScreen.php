<?php
/**
 * Workspace contract tracking in the native WordPress admin.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Notice::render() and ContractScreen::render() escape these plain-text errors at display time.

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\WP\Capabilities;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Documents\DocumentContext;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Settings;

/** Reads authoritative terms on demand; contract data is not copied into WordPress. */
final class ContractScreen {

	public const PAGE = 'assinafy-contracts';

	/** @param ClientFactory $clients Connected workspace client. */
	public function __construct( private readonly ClientFactory $clients ) {
	}

	/** Register native navigation and the explicit mutation endpoint. */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		( new ContractActions( $this->clients ) )->register();
	}

	/** Add the contract list beside the plugin's document list. */
	public function menu(): void {
		add_submenu_page( Settings::PAGE, __( 'Contracts', 'assinafy' ), __( 'Contracts', 'assinafy' ), Capabilities::MANAGE, self::PAGE, array( $this, 'render' ) );
	}

	/** Show the current workspace's contracts or one document's terms. */
	public function render(): void {
		$this->authorise();
		echo '<div class="wrap"><h1>' . esc_html__( 'Contracts', 'assinafy' ) . '</h1>';
		Notice::render( ContractActions::FLASH, 'is-dismissible' );
		try {
			$client      = $this->client();
			$document_id = $this->selected_document( $client );
			match ( $document_id ) {
				'' => $this->listing( $client ),
				default => $this->detail( $client, $document_id ),
			};
		} catch ( \Throwable $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error->getMessage() ) . '</p></div>';
		}
		echo '</div>';
	}

	private function authorise(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to manage contracts.', 'assinafy' ), '', array( 'response' => 403 ) );
		}
	}

	private function client(): AssinafyClient {
		return $this->clients->client() ?? throw new \RuntimeException( $this->clients->error()?->get_error_message() ?? __( 'Connect the plugin to Assinafy first.', 'assinafy' ) );
	}

	private function selected_document( AssinafyClient $client ): string {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		if ( $post_id > 0 ) {
			if ( DocumentPostType::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'read_post', $post_id ) ) {
				throw new \RuntimeException( __( 'That document does not exist.', 'assinafy' ) );
			}
			$document_id = ( new DocumentRecord() )->document_id( $post_id );
			ContractForm::validate_id( $document_id );
			DocumentContext::verify_or_bind( $post_id, $client, $document_id );
			return $document_id;
		}
		$document_id = isset( $_GET['document'] ) ? ContractForm::text( wp_unslash( $_GET['document'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation; ContractForm::text() validates and sanitizes.
		if ( '' !== $document_id ) {
			ContractForm::validate_id( $document_id );
		}
		return $document_id;
	}

	/** @param AssinafyClient $client Connected workspace. */
	private function listing( AssinafyClient $client ): void {
		$page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$list = $client->contracts()->list( $page, 25 );
		echo '<p>' . esc_html__( 'Track contract dates, renewal terms and related documents in your connected Assinafy workspace. Changes require a plan with contract tracking.', 'assinafy' ) . '</p>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '" />';
		echo '<label for="contract-document">' . esc_html__( 'Document ID', 'assinafy' ) . '</label> <input id="contract-document" name="document" required pattern="[a-fA-F0-9]+" /> ';
		submit_button( __( 'Open contract', 'assinafy' ), 'secondary', '', false );
		echo '</form><br />';
		$this->table( is_array( $list['data'] ?? null ) ? $list['data'] : array() );
		$pages = (int) ( $list['pagination']['page_count'] ?? 1 );
		foreach ( array(
			$page - 1 => __( 'Previous page', 'assinafy' ),
			$page + 1 => __( 'Next page', 'assinafy' ),
		) as $target => $label ) {
			if ( $target > 0 && $target <= $pages ) {
				echo '<p><a href="' . esc_url( add_query_arg( 'paged', $target, self::url() ) ) . '">' . esc_html( $label ) . '</a></p>';
			}
		}
	}

	private function detail( AssinafyClient $client, string $document_id ): void {
		try {
			$contract = $client->contracts()->get( $document_id );
			ContractForm::assert_contract( $contract, $document_id );
		} catch ( ApiException $error ) {
			if ( 404 !== $error->getStatusCode() ) {
				throw $error;
			}
			$contract = array();
		}
		echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'All contracts', 'assinafy' ) . '</a></p>';
		echo '<h2>' . esc_html( $document_id ) . '</h2>';
		echo '<p>' . esc_html__( 'Contract tracking does not change the signed PDF or its signature status.', 'assinafy' ) . '</p>';
		if ( 'document_contract_amendment' === ( $contract['resource'] ?? '' ) ) {
			$this->amendment( $client, $document_id, $contract );
			return;
		}
		( new ContractEditor() )->render( $client, $document_id, $contract );
		if ( array() !== $contract ) {
			$this->table( is_array( $contract['amendments'] ?? null ) ? $contract['amendments'] : array() );
			return;
		}
	}

	/** @param array<string, mixed> $contract Amendment projection. */
	private function amendment( AssinafyClient $client, string $document_id, array $contract ): void {
		echo '<p>' . esc_html( self::scalar( $contract['relation_type'] ?? '' ) . ': ' . self::scalar( $contract['status'] ?? '' ) ) . '</p>';
		echo '<p>' . esc_html( self::scalar( $contract['apply_error'] ?? '' ) ) . '</p>';
		$terms = is_array( $contract['terms'] ?? null ) ? $contract['terms'] : array();
		echo '<h3>' . esc_html__( 'Amendment terms', 'assinafy' ) . '</h3><dl>';
		foreach ( array_intersect_key( ContractForm::fields(), $terms ) as $key => $field ) {
			echo '<dt>' . esc_html( $field[0] ) . '</dt><dd>' . esc_html( self::scalar( $terms[ $key ] ) ) . '</dd>';
		}
		echo '</dl><p>' . esc_html( self::scalar( $contract['currency'] ?? '' ) ) . '</p>';
		$related = $client->contracts()->related( $document_id );
		$this->table( array( $related ) );
		$this->table( is_array( $related['amendments'] ?? null ) ? $related['amendments'] : array() );
		if ( 'pending' === ( $contract['status'] ?? '' ) ) {
			( new ContractEditor() )->begin_form( $client, $document_id, 'unlink', $contract );
			submit_button( __( 'Unlink pending amendment', 'assinafy' ), 'secondary' );
			echo '</form>';
		}
	}

	/** @param array<array-key, mixed> $contracts Contract or amendment rows. */
	private function table( array $contracts ): void {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Document', 'assinafy' ) . '</th><th>' . esc_html__( 'Counterparty / relationship', 'assinafy' ) . '</th><th>' . esc_html__( 'Status', 'assinafy' ) . '</th><th>' . esc_html__( 'End date', 'assinafy' ) . '</th></tr></thead><tbody>';
		foreach ( $contracts as $contract ) {
			if ( ! is_array( $contract ) || ! is_string( $contract['document_id'] ?? null ) ) {
				continue;
			}
			echo '<tr><td><a href="' . esc_url( self::url( $contract['document_id'] ) ) . '">' . esc_html( self::scalar( $contract['document_name'] ?? $contract['document_id'] ) ) . '</a></td>';
			foreach ( array( $contract['counterparty_name'] ?? $contract['relation_type'] ?? '', $contract['status'] ?? '', $contract['end_date'] ?? '' ) as $value ) {
				echo '<td>' . esc_html( self::scalar( $value ) ) . '</td>';
			}
			echo '</tr>';
		}
		if ( array() === $contracts ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No contracts found.', 'assinafy' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function scalar( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	public static function url( string $document_id = '' ): string {
		return add_query_arg(
			array(
				'page'     => self::PAGE,
				'document' => $document_id,
			),
			admin_url( 'admin.php' )
		);
	}
}
