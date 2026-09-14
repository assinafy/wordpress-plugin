<?php
/**
 * The four operations offered on a document that has already been sent.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\WP\Capabilities;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\StatusSync;

/**
 * Serves the `admin_post_` endpoints behind the buttons {@see DocumentMetaBox} renders.
 *
 * The four are everything an API key may do to a request that is already out: resend one
 * signer's invitation, move the deadline, rename the document while it is still unassigned,
 * and cancel by deleting the remote document. There is no "edit this signature request" —
 * an assignment is created once per document, permanently, and the API exposes no update
 * route.
 *
 * Every handler follows the same order, and the order is load-bearing: read the post id,
 * verify the nonce bound to it, check the capability, then act. Each action carries its own
 * nonce, so one leaked or replayed from a different control cannot drive this one.
 *
 * Outcomes cannot be echoed where they happen because every handler ends in a redirect, so
 * they go through {@see Notice} under this class's own prefix and the screen the redirect
 * lands on prints them.
 */
final class DocumentActions {

	/**
	 * `admin_post_` action that resends one signer's invitation.
	 */
	public const ACTION_RESEND = 'assinafy_resend';

	/**
	 * `admin_post_` action that moves the signature deadline.
	 */
	public const ACTION_EXTEND = 'assinafy_extend';

	/**
	 * `admin_post_` action that cancels the request by deleting the remote document.
	 */
	public const ACTION_CANCEL = 'assinafy_cancel';

	/**
	 * `admin_post_` action that renames the document before it is assigned.
	 */
	public const ACTION_RENAME = 'assinafy_rename';

	/**
	 * Prefix of the per-user transient carrying the post-redirect flash notice.
	 */
	public const FLASH_PREFIX = 'assinafy_doc_notice_';

	/**
	 * @param ClientFactory  $clients API client factory.
	 * @param DocumentRecord $records Typed access to the local mirror.
	 */
	public function __construct(
		private readonly ClientFactory $clients,
		private readonly DocumentRecord $records
	) {
	}

	/**
	 * Attach the four handlers, and the renderer for the one that redirects away.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_RESEND, array( $this, 'handle_resend' ) );
		add_action( 'admin_post_' . self::ACTION_EXTEND, array( $this, 'handle_extend' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_RENAME, array( $this, 'handle_rename' ) );

		// Cancel trashes the post, so it lands on the list screen, where no meta box exists to
		// print its flash. Scoped to that one screen: everywhere else the meta box is the
		// reader, and whichever renders first consumes the notice.
		add_action(
			'admin_notices',
			static function (): void {
				if ( 'edit-' . DocumentPostType::POST_TYPE === get_current_screen()?->id ) {
					Notice::render( self::FLASH_PREFIX, 'is-dismissible' );
				}
			}
		);
	}

	/**
	 * Resend one signer's invitation.
	 */
	public function handle_resend(): void {
		$post_id = isset( $_POST['post'] ) ? absint( wp_unslash( $_POST['post'] ) ) : 0;
		check_admin_referer( self::ACTION_RESEND . '_' . $post_id );
		$this->authorise( $post_id, Capabilities::SEND );

		$signer_id = isset( $_POST['assinafy_signer'] )
			? sanitize_text_field( wp_unslash( $_POST['assinafy_signer'] ) )
			: '';

		$document_id   = $this->records->document_id( $post_id );
		$assignment_id = $this->records->assignment_id( $post_id );

		if ( '' === $signer_id || '' === $document_id || '' === $assignment_id ) {
			$this->finish( $post_id, 'error', __( 'This document has no signature request to resend.', 'assinafy' ) );
		}

		$client = $this->require_client( $post_id );

		try {
			$client->assignments()->resend( $document_id, $assignment_id, $signer_id );
			$this->resync( $document_id );
		} catch ( \Throwable $e ) {
			$this->finish( $post_id, 'error', $e->getMessage() );
		}

		$this->finish( $post_id, 'success', __( 'Invitation sent again.', 'assinafy' ) );
	}


	/**
	 * Move the signature deadline.
	 */
	public function handle_extend(): void {
		$post_id = isset( $_POST['post'] ) ? absint( wp_unslash( $_POST['post'] ) ) : 0;
		check_admin_referer( self::ACTION_EXTEND . '_' . $post_id );
		$this->authorise( $post_id, Capabilities::SEND );

		$expires_on = isset( $_POST['assinafy_expires_on'] )
			? sanitize_text_field( wp_unslash( $_POST['assinafy_expires_on'] ) )
			: '';

		$expires_at = Deadline::parse( $expires_on );

		if ( '' === $expires_at ) {
			$this->finish( $post_id, 'error', __( 'Choose a deadline in the future.', 'assinafy' ) );
		}

		$document_id   = $this->records->document_id( $post_id );
		$assignment_id = $this->records->assignment_id( $post_id );

		if ( '' === $document_id || '' === $assignment_id ) {
			$this->finish( $post_id, 'error', __( 'This document has no signature request.', 'assinafy' ) );
		}

		$client = $this->require_client( $post_id );

		try {
			$client->assignments()->resetExpiration( $document_id, $assignment_id, $expires_at );
			$this->resync( $document_id );
		} catch ( \Throwable $e ) {
			$this->finish( $post_id, 'error', $e->getMessage() );
		}

		$this->finish( $post_id, 'success', __( 'Deadline moved.', 'assinafy' ) );
	}


	/**
	 * Cancel the request by deleting the remote document.
	 *
	 * The delete is a hard delete and is not idempotent — a second one is a 404, exactly like
	 * an id that never existed — so a 404 here is treated as the success it describes. The
	 * local post is trashed rather than kept: it mirrors a document that no longer exists,
	 * and trash keeps it recoverable.
	 */
	public function handle_cancel(): void {
		$post_id = isset( $_POST['post'] ) ? absint( wp_unslash( $_POST['post'] ) ) : 0;
		check_admin_referer( self::ACTION_CANCEL . '_' . $post_id );
		$this->authorise( $post_id, Capabilities::MANAGE );

		$document_id = $this->records->document_id( $post_id );

		if ( '' === $document_id ) {
			$this->finish( $post_id, 'error', __( 'This record has nothing to cancel.', 'assinafy' ) );
		}

		$client = $this->require_client( $post_id );

		try {
			$client->documents()->delete( $document_id );
		} catch ( ApiException $e ) {
			if ( 404 !== $e->getStatusCode() ) {
				$this->finish( $post_id, 'error', $e->getMessage() );
			}
		} catch ( \Throwable $e ) {
			$this->finish( $post_id, 'error', $e->getMessage() );
		}

		wp_trash_post( $post_id );

		Notice::set( self::FLASH_PREFIX, 'success', __( 'The request was cancelled and the document was deleted from Assinafy.', 'assinafy' ) );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . DocumentPostType::POST_TYPE ) );
		exit;
	}


	/**
	 * Rename the document, while it is still possible.
	 */
	public function handle_rename(): void {
		$post_id = isset( $_POST['post'] ) ? absint( wp_unslash( $_POST['post'] ) ) : 0;
		check_admin_referer( self::ACTION_RENAME . '_' . $post_id );
		$this->authorise( $post_id, Capabilities::SEND );

		$name = isset( $_POST['assinafy_name'] )
			? sanitize_text_field( wp_unslash( $_POST['assinafy_name'] ) )
			: '';

		$document_id = $this->records->document_id( $post_id );

		if ( '' === $name || '' === $document_id ) {
			$this->finish( $post_id, 'error', __( 'Enter a name for the document.', 'assinafy' ) );
		}

		if ( '' !== $this->records->assignment_id( $post_id ) ) {
			$this->finish( $post_id, 'error', __( 'This document already has a signature request, so its name is locked.', 'assinafy' ) );
		}

		$client = $this->require_client( $post_id );

		try {
			$client->documents()->rename( $document_id, $name );
			$this->resync( $document_id );
		} catch ( \Throwable $e ) {
			$this->finish( $post_id, 'error', $e->getMessage() );
		}

		$this->finish( $post_id, 'success', __( 'Document renamed.', 'assinafy' ) );
	}


	/**
	 * Confirm the capability and that the post is really one of ours.
	 *
	 * The nonce is verified by the calling handler, which is also where the post id it is
	 * bound to is read.
	 *
	 * @param int    $post_id    Document post id, as read from the verified request.
	 * @param string $capability Capability the action requires.
	 */
	private function authorise( int $post_id, string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'assinafy' ), '', array( 'response' => 403 ) );
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || DocumentPostType::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'That document does not exist.', 'assinafy' ), '', array( 'response' => 404 ) );
		}
	}


	/**
	 * Fetch the API client, or stop with a flash explaining why there is none.
	 *
	 * @param int $post_id Document post id, for the redirect back.
	 */
	private function require_client( int $post_id ): \Assinafy\SDK\AssinafyClient {
		$client = $this->clients->client();

		if ( null === $client ) {
			$error = $this->clients->error();

			$this->finish(
				$post_id,
				'error',
				null !== $error ? $error->get_error_message() : __( 'Connect the plugin to Assinafy first.', 'assinafy' )
			);
		}

		return $client;
	}


	/**
	 * Refresh the local mirror from the API after an action changed something.
	 *
	 * The document is re-read rather than patched from the action's own response: `resend`
	 * answers with a delivery receipt and `reset-expiration` with the assignment alone, so
	 * only a fresh document carries every field the panel renders.
	 *
	 * @param string $document_id Remote document id.
	 */
	private function resync( string $document_id ): void {
		( new StatusSync( $this->clients, $this->records ) )->sync_one( $document_id );
	}


	/**
	 * Store a flash and go back to the document.
	 *
	 * @param int    $post_id Document post id.
	 * @param string $type    `success` or `error`.
	 * @param string $message Translated, unescaped message.
	 */
	private function finish( int $post_id, string $type, string $message ): never {
		Notice::set( self::FLASH_PREFIX, $type, $message );

		wp_safe_redirect(
			get_edit_post_link( $post_id, 'raw' ) ?? admin_url( 'edit.php?post_type=' . DocumentPostType::POST_TYPE )
		);
		exit;
	}
}
