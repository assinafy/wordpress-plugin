<?php
/**
 * WPForms' accepted-submission signature workflow.
 *
 * @package Assinafy\WP\Addons\WPForms
 */

declare(strict_types=1);

namespace Assinafy\WP\Addons\WPForms;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use WP_Error;

/**
 * Accept validated host data, send through core, and expose protected recovery controls.
 */
final class Adapter {

	/** @var array<string, int> Receipts already created by this PHP request. */
	private array $accepted = [];

	private readonly RequestStore $store;

	/**
	 * @param SendService $send Shared core sender.
	 * @param DocumentRecord $records Shared core storage.
	 */
	public function __construct( private readonly SendService $send, private readonly DocumentRecord $records ) {
		$this->store = new RequestStore( $records );
	}

	/** Register only when both host and core are available. */
	public function register(): void {
		( new FormSettings() )->register();
		add_action( 'wpforms_process', [ $this, 'validate' ], 20, 3 );
		add_action( 'wpforms_process_complete', [ $this, 'complete' ], 20, 4 );
		add_action( 'assinafy_wpforms_requests', [ $this, 'render_requests' ] );
		add_action( 'admin_post_assinafy_wpforms_retry', [ $this, 'retry' ] );
	}

	/**
	 * Make mapped contact details required before WPForms accepts the submission.
	 *
	 * @param array<int, array<string, mixed>> $fields Sanitized host fields.
	 * @param array<string, mixed> $entry Raw submission, deliberately unused.
	 * @param array<string, mixed> $form Native form configuration.
	 */
	public function validate( array $fields, array $entry, array $form ): void {
		if ( FormSettings::read( $form )['enabled'] && is_wp_error( $this->signer( $fields, $form ) ) ) {
			wpforms()->process->errors[ (int) $form['id'] ]['header'] = __( 'A valid signer name and email address are required for this signature request.', 'assinafy-wpforms' );
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $fields Validated host fields.
	 * @param array<string, mixed> $entry Original submitted values, never persisted.
	 * @param array<string, mixed> $form Accepted form configuration.
	 * @param int $entry_id Actual entry ID, zero in Lite or when entry storage is disabled.
	 */
	public function complete( array $fields, array $entry, array $form, int $entry_id ): void {
		$config = FormSettings::read( $form );
		if ( ! $config['enabled'] || ! empty( $form['spam_reason'] ) ) {
			return;
		}
		$signer = $this->signer( $fields, $form );
		if ( is_wp_error( $signer ) ) {
			return;
		}
		$identity = get_current_blog_id() . ':' . (int) $form['id'] . ':' . $entry_id . ':' . hash( 'sha256', (string) wp_json_encode( $fields ) );
		if ( isset( $this->accepted[ $identity ] ) ) {
			return;
		}
		$post_id = $this->store->find_entry( (int) $form['id'], $entry_id );
		if ( $post_id === 0 ) {
			$post_id = $this->store->create( $form, $entry_id, $config, $signer );
		}
		if ( is_wp_error( $post_id ) ) {
			wpforms_log( __( 'Assinafy request could not be recorded', 'assinafy-wpforms' ), $post_id->get_error_message(), [ 'form_id' => (int) $form['id'] ] );
			return;
		}
		$this->accepted[ $identity ] = $post_id;
		$this->dispatch( $post_id );
	}

	/**
	 * Retry only the durable receipt and its original contact/configuration.
	 *
	 * @param int $post_id Core mirror ID.
	 * @return int|WP_Error Core outcome, also recorded in the mirror.
	 */
	public function dispatch( int $post_id ): int|WP_Error {
		$request = $this->store->get( $post_id );
		$source  = $this->records->source( $post_id );
		if ( $request === [] || ( $source['integration'] ?? '' ) !== 'wpforms' || get_post_status( $post_id ) !== 'publish' ) {
			return new WP_Error( 'assinafy_wpforms_request', __( 'This signature request is unavailable.', 'assinafy-wpforms' ) );
		}
		if ( ! $this->has_contact( $post_id ) ) {
			return new WP_Error( 'assinafy_wpforms_erased', __( 'Signer details were erased. This request cannot be retried.', 'assinafy-wpforms' ) );
		}
		$signers = array_map(
			static fn( array $signer ): array => [
				'full_name' => $signer['name'],
				'email'     => $signer['email'],
			],
			$this->records->signers( $post_id )
		);
		$args    = [
			'attachment_id'   => $request['attachment_id'],
			'post_id'         => $post_id,
			'signers'         => $signers,
			'source'          => $source,
			'idempotency_key' => 'wpforms:' . $source['record_id'] . ':signature-v1',
		];
		if ( $request['message'] !== '' ) {
			$args['message'] = $request['message'];
		}
		try {
			$result = $this->send->send( $args );
		} catch ( \Throwable $error ) {
			$result = new WP_Error( 'assinafy_wpforms_send', $error->getMessage(), [ 'post_id' => $post_id ] );
		}
		$this->records->set_last_error( $post_id, is_wp_error( $result ) ? $result->get_error_message() : '' );
		return $result;
	}

	/** Process the nonce-protected administrative retry link. */
	public function retry(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
		if ( ! current_user_can( 'assinafy_send' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You cannot retry this signature request.', 'assinafy-wpforms' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'assinafy_wpforms_retry_' . $post_id );
		$request = $this->store->get( $post_id );
		if ( $request === [] || ! FormSettings::can_manage( $request['form_id'] ) || get_post_type( $request['form_id'] ) !== 'wpforms' ) {
			wp_die( esc_html__( 'The originating form is unavailable or you cannot edit it.', 'assinafy-wpforms' ), '', [ 'response' => 403 ] );
		}
		$this->dispatch( $post_id );
		wp_safe_redirect( self::form_url( $request['form_id'] ) );
		exit;
	}

	/**
	 * @param int $form_id Native form ID.
	 */
	public function render_requests( int $form_id ): void {
		if ( ! FormSettings::can_manage( $form_id ) || ! current_user_can( 'assinafy_view' ) ) {
			return;
		}
		echo '<h3>' . esc_html__( 'Recent signature requests', 'assinafy-wpforms' ) . '</h3>';
		echo '<p>' . esc_html__( 'WPForms Lite does not store entries. These are signature-request receipts, not saved form submissions. Open a request to inspect its status. Retry reuses the original request; investigate an unknown upload outcome before starting another submission.', 'assinafy-wpforms' ) . '</p><ul>';
		foreach ( $this->store->for_form( $form_id ) as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$this->render_request( $post_id );
		}
		echo '</ul>';
	}

	/**
	 * @param int $post_id Core mirror ID visible to the current user.
	 */
	private function render_request( int $post_id ): void {
		$request = $this->store->get( $post_id );
		$status  = $this->records->status( $post_id );
		printf( '<li><a href="%s">%s</a> — %s', esc_url( (string) get_edit_post_link( $post_id ) ), esc_html( '#' . $post_id ), esc_html( $status !== '' ? $status : __( 'Not sent', 'assinafy-wpforms' ) ) );
		if ( $this->has_contact( $post_id ) && $this->records->assignment_id( $post_id ) === '' && get_post_status( $post_id ) === 'publish' ) {
			$url = wp_nonce_url(
				add_query_arg(
					[
						'action'  => 'assinafy_wpforms_retry',
						'post_id' => $post_id,
					],
					admin_url( 'admin-post.php' )
				),
				'assinafy_wpforms_retry_' . $post_id
			);
			printf( ' — <a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Retry', 'assinafy-wpforms' ) );
		}
		if ( ! empty( $request['entry_id'] ) && wpforms()->is_pro() && wpforms_current_user_can( 'view_entry_single', $request['entry_id'] ) ) {
			$url = add_query_arg(
				[
					'page'     => 'wpforms-entries',
					'view'     => 'details',
					'entry_id' => $request['entry_id'],
				],
				admin_url( 'admin.php' )
			);
			printf( ' — <a href="%s">%s</a>', esc_url( $url ), esc_html__( 'WPForms entry', 'assinafy-wpforms' ) );
		}
		echo '<br />' . esc_html( $this->records->last_error( $post_id ) ) . '</li>';
	}

	/**
	 * @param int $form_id Native form ID.
	 */
	private static function form_url( int $form_id ): string {
		return add_query_arg(
			[
				'page'    => 'wpforms-builder',
				'view'    => 'settings',
				'form_id' => $form_id,
				'section' => 'assinafy',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Privacy-erased contact data must never be reconstructed from the submitted form.
	 *
	 * @param int $post_id Core mirror ID.
	 */
	private function has_contact( int $post_id ): bool {
		$signers = $this->records->signers( $post_id );
		return count( $signers ) === 1 && is_email( $signers[0]['email'] ) !== false;
	}

	/**
	 * @param array<int, array<string, mixed>> $fields Formatted host fields.
	 * @param array<string, mixed> $form Native form definition.
	 * @return array{full_name: string, email: string}|WP_Error One supported signer.
	 */
	private function signer( array $fields, array $form ): array|WP_Error {
		$config = FormSettings::read( $form );
		$name   = $fields[ $config['name_field'] ]['value'] ?? null;
		$email  = $fields[ $config['email_field'] ]['value'] ?? null;
		if ( ! is_string( $name ) || trim( $name ) === '' || ! is_string( $email ) || ! is_email( trim( $email ) ) ) {
			return new WP_Error( 'assinafy_wpforms_signer', __( 'The mapped signer name or email is missing or invalid.', 'assinafy-wpforms' ) );
		}
		return [
			'full_name' => sanitize_text_field( $name ),
			'email'     => trim( $email ),
		];
	}
}
