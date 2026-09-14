<?php
/**
 * Read-only status panel on the document edit screen.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\DownloadProxy;
use Assinafy\WP\Documents\StatusSync;

/**
 * Shows what Assinafy currently knows about one document, and offers the four operations an
 * API key is actually allowed to perform on a request that has already been sent.
 *
 * Everything here is read-only mirroring plus those four actions. There is no "edit this
 * signature request": an assignment is created once per document, permanently, and the API
 * exposes no update route — changing anything means uploading the document again.
 */
final class DocumentMetaBox {

	/**
	 * Statuses in which a rename is still possible.
	 *
	 * Reaching one of these is necessary but not sufficient: the name is locked the moment an
	 * assignment exists, which in the plugin's primary flow happens while the document is
	 * still `uploaded`.
	 *
	 * @var array<int, string>
	 */
	private const RENAMEABLE_STATUSES = array(
		'uploading',
		'uploaded',
		'metadata_processing',
		'metadata_ready',
	);

	/**
	 * How stale an open record may be before opening the panel refreshes it.
	 *
	 * A virtual assignment is accepted while the document is still `uploaded`, so a send
	 * returns `metadata_processing` and the API reaches `pending_signature` a few seconds
	 * later. Waiting for the hourly reconcile would show a status that stopped being true
	 * seconds after the send — which is precisely when someone opens this panel.
	 */
	private const REFRESH_AFTER = 60;

	/**
	 * @param ClientFactory  $clients API client factory.
	 * @param DocumentRecord $records Typed access to the local mirror.
	 * @param StatusSync     $sync    Remote-to-local refresh.
	 */
	public function __construct(
		private readonly ClientFactory $clients,
		private readonly DocumentRecord $records,
		private readonly StatusSync $sync
	) {
	}

	/**
	 * Attach the panel and the handlers behind its buttons.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// Constructed here rather than in `Plugin`: the handlers serve the forms this panel
		// renders, and they need nothing the panel does not already hold.
		( new DocumentActions( $this->clients, $this->records ) )->register();
	}

	/**
	 * Register the panel on the document edit screen.
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'assinafy-document-status',
			__( 'Signature status', 'assinafy' ),
			array( $this, 'render' ),
			DocumentPostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the panel.
	 *
	 * @param \WP_Post $post Document post being edited.
	 */
	public function render( \WP_Post $post ): void {
		$post_id     = (int) $post->ID;
		$document_id = $this->records->document_id( $post_id );

		Notice::render( DocumentActions::FLASH_PREFIX, 'inline' );

		if ( '' === $document_id ) {
			$error = $this->records->last_error( $post_id );
			echo '<p>' . esc_html( '' !== $error ? $error : __( 'This record has not been sent to Assinafy yet.', 'assinafy' ) ) . '</p>';

			return;
		}

		// One request, and only for a record that is still open and already stale. A closed
		// document never changes again, and a record refreshed moments ago is left alone so
		// reloading the screen cannot be turned into a way to spend the rate-limit budget.
		if ( ! $this->records->is_closed( $post_id )
			&& ( time() - $this->records->synced_at( $post_id ) ) > self::REFRESH_AFTER ) {
			$this->sync->sync_one( $document_id );
		}

		$status     = $this->records->status( $post_id );
		$last_error = $this->records->last_error( $post_id );
		$synced_at  = $this->records->synced_at( $post_id );

		echo '<p class="assinafy-status assinafy-status--' . esc_attr( sanitize_html_class( $status ) ) . '">';
		echo '<strong>' . esc_html( DocumentPostType::status_label( $status ) ) . '</strong>';

		if ( $synced_at > 0 ) {
			echo ' <span class="description">';
			printf(
				/* translators: %s: human-readable time difference, for example "5 mins". */
				esc_html__( 'checked %s ago', 'assinafy' ),
				esc_html( human_time_diff( $synced_at ) )
			);
			echo '</span>';
		}

		echo '</p>';

		echo '<p class="description"><code>' . esc_html( $document_id ) . '</code></p>';

		if ( '' !== $last_error ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $last_error ) . '</p></div>';
		}

		$this->render_signers( $post_id );
		$this->render_downloads( $post_id );
		$this->render_actions( $post_id, $status );
		$this->render_activities( $document_id );
	}

	/**
	 * Render the signer table, with a signing link for everyone already invited.
	 *
	 * The link is read from the assignment's `signing_urls` and never built here. Its path
	 * segment is the document id rather than anything per-signer, and the only thing telling
	 * two signers apart is an `email` query parameter on the web app's host — a URL assembled
	 * from the parts the plugin happens to hold would point at the wrong host and identify
	 * nobody.
	 *
	 * @param int $post_id Document post id.
	 */
	private function render_signers( int $post_id ): void {
		$signers = $this->records->signers( $post_id );

		if ( array() === $signers ) {
			echo '<p>' . esc_html__( 'No signers recorded yet.', 'assinafy' ) . '</p>';

			return;
		}

		echo '<h4>' . esc_html__( 'Signers', 'assinafy' ) . '</h4>';
		echo '<table class="widefat striped assinafy-signer-status"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Signer', 'assinafy' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Order', 'assinafy' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Progress', 'assinafy' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Signing link', 'assinafy' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $signers as $signer ) {
			$name        = (string) ( $signer['name'] ?? '' );
			$email       = (string) ( $signer['email'] ?? '' );
			$step        = (int) ( $signer['step'] ?? 1 );
			$notified    = (bool) ( $signer['notified'] ?? false );
			$completed   = (bool) ( $signer['completed'] ?? false );
			$signing_url = (string) ( $signer['signing_url'] ?? '' );

			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '<br /><span class="description">' . esc_html( $email ) . '</span></td>';
			echo '<td>' . esc_html( (string) $step ) . '</td>';

			echo '<td>';
			echo esc_html(
				match ( true ) {
					$completed => __( 'Signed', 'assinafy' ),
					$notified  => __( 'Invited, waiting', 'assinafy' ),
					default    => __( 'Not yet invited', 'assinafy' ),
				}
			);
			echo '</td>';

			echo '<td>';
			$this->render_signing_link( $signing_url, $completed );
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'The link does not sign anyone in on its own: Assinafy emails the signer a one-time code when they open it.', 'assinafy' ) . '</p>';
	}

	/**
	 * Render one signer's link cell.
	 *
	 * The field is read-only text beside the link rather than the link alone, because the
	 * common support request is "send me that URL again" and copying it out of an anchor is
	 * awkward.
	 *
	 * @param string $signing_url The signer's URL, or '' when they have none yet.
	 * @param bool   $completed   Whether this signer has already signed.
	 */
	private function render_signing_link( string $signing_url, bool $completed ): void {
		if ( $completed ) {
			printf( '<span class="description">%s</span>', esc_html__( 'No longer needed', 'assinafy' ) );

			return;
		}

		if ( '' === $signing_url ) {
			printf( '<span class="description">%s</span>', esc_html__( 'Available once this signer is invited', 'assinafy' ) );

			return;
		}

		printf(
			'<input type="text" class="large-text code" readonly value="%1$s" aria-label="%2$s" /> <a href="%3$s" target="_blank" rel="noopener noreferrer">%4$s</a>',
			esc_attr( $signing_url ),
			esc_attr__( 'Signing link', 'assinafy' ),
			esc_url( $signing_url ),
			esc_html__( 'Open', 'assinafy' )
		);
	}

	/**
	 * Render one download link per artifact the document actually has.
	 *
	 * The list comes from the stored `artifacts` map. Probing the download route tells you
	 * nothing — an artifact that does not exist yet and an artifact name that never existed
	 * return the identical 404 — and every artifact URL carries the account's API key, so
	 * each one is proxied through WordPress behind a capability check instead of being linked
	 * to directly.
	 *
	 * @param int $post_id Document post id.
	 */
	private function render_downloads( int $post_id ): void {
		$labels = array(
			'original'         => __( 'Original PDF', 'assinafy' ),
			'certificated'     => __( 'Signed PDF', 'assinafy' ),
			'certificate-page' => __( 'Signature certificate', 'assinafy' ),
			'pades'            => __( 'PAdES signed PDF', 'assinafy' ),
			'bundle'           => __( 'Everything (zip)', 'assinafy' ),
		);

		// The artifacts map carries keys the download route rejects — `thumbnail` today, and
		// whatever the platform adds next — so a link is offered only for a name it accepts.
		$artifacts = array_intersect( $this->records->artifacts( $post_id ), array_keys( $labels ) );

		if ( array() === $artifacts ) {
			return;
		}

		echo '<h4>' . esc_html__( 'Downloads', 'assinafy' ) . '</h4><ul class="assinafy-downloads">';

		foreach ( $artifacts as $artifact ) {
			printf(
				'<li><a href="%1$s">%2$s</a></li>',
				esc_url( DownloadProxy::url( $post_id, $artifact ) ),
				esc_html( $labels[ $artifact ] )
			);
		}

		echo '</ul>';
	}

	/**
	 * Render the action forms that apply to this document's current state.
	 *
	 * @param int    $post_id Document post id.
	 * @param string $status  Current status code.
	 */
	private function render_actions( int $post_id, string $status ): void {
		echo '<h4>' . esc_html__( 'Actions', 'assinafy' ) . '</h4>';

		if ( current_user_can( Capabilities::SEND ) ) {
			$this->render_send_actions( $post_id, $status );
		}

		if ( current_user_can( Capabilities::MANAGE ) && ( new DeletableStatuses( $this->clients ) )->allows( $status ) ) {
			$this->render_cancel_form( $post_id, $status );
		}
	}

	/**
	 * Render the forms the send capability covers: rename before the assignment exists, and
	 * the deadline and resend controls once it does.
	 *
	 * The deadline survives expiry. `is_closed` covers every terminal state at once, but
	 * `expired` is the one a new deadline reopens — hiding the control there would hide it in
	 * the single state it exists to repair. Resending is not offered: an invitation sent
	 * against a dead deadline arrives at a document nobody can sign.
	 *
	 * @param int    $post_id Document post id.
	 * @param string $status  Current status code.
	 */
	private function render_send_actions( int $post_id, string $status ): void {
		if ( '' === $this->records->assignment_id( $post_id ) ) {
			if ( in_array( $status, self::RENAMEABLE_STATUSES, true ) ) {
				$this->render_rename_form( $post_id );
			}

			return;
		}

		$is_closed = $this->records->is_closed( $post_id );

		if ( ! $is_closed || 'expired' === $status ) {
			$this->render_extend_form( $post_id );
		}

		if ( ! $is_closed ) {
			$this->render_resend_forms( $post_id );
		}
	}

	/**
	 * Render the rename form.
	 *
	 * Rename disappears rather than greying out once an assignment exists: the name is then
	 * locked for the life of the document, and a control that can never come back is noise.
	 *
	 * @param int $post_id Document post id.
	 */
	private function render_rename_form( int $post_id ): void {
		$form_id = $this->open_form( DocumentActions::ACTION_RENAME, $post_id );

		printf(
			'<label for="assinafy-rename-%1$d">%2$s</label> ',
			(int) $post_id,
			esc_html__( 'Document name', 'assinafy' )
		);

		printf(
			'<input type="text" class="regular-text" id="assinafy-rename-%1$d" name="assinafy_name" value="%2$s" form="%3$s" required /> ',
			(int) $post_id,
			esc_attr( get_the_title( $post_id ) ),
			esc_attr( $form_id )
		);

		echo '<button type="submit" class="button" form="' . esc_attr( $form_id ) . '">' . esc_html__( 'Rename', 'assinafy' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Renaming is only possible until the signature request is created. Assinafy folds accents and replaces unsupported characters.', 'assinafy' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render the deadline form.
	 *
	 * @param int $post_id Document post id.
	 */
	private function render_extend_form( int $post_id ): void {
		$form_id = $this->open_form( DocumentActions::ACTION_EXTEND, $post_id );

		printf(
			'<label for="assinafy-extend-%1$d">%2$s</label> ',
			(int) $post_id,
			esc_html__( 'New deadline', 'assinafy' )
		);

		printf(
			'<input type="date" id="assinafy-extend-%1$d" name="assinafy_expires_on" min="%2$s" form="%3$s" required /> ',
			(int) $post_id,
			esc_attr( gmdate( 'Y-m-d', time() + DAY_IN_SECONDS ) ),
			esc_attr( $form_id )
		);

		echo '<button type="submit" class="button" form="' . esc_attr( $form_id ) . '">' . esc_html__( 'Move deadline', 'assinafy' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Render one resend button per signer who has been invited but has not signed.
	 *
	 * @param int $post_id Document post id.
	 */
	private function render_resend_forms( int $post_id ): void {
		foreach ( $this->records->signers( $post_id ) as $signer ) {
			$signer_id = (string) ( $signer['id'] ?? '' );

			if ( '' === $signer_id || ! ( $signer['notified'] ?? false ) || ( $signer['completed'] ?? false ) ) {
				continue;
			}

			$form_id = $this->open_form( DocumentActions::ACTION_RESEND, $post_id );

			printf( '<input type="hidden" name="assinafy_signer" value="%1$s" form="%2$s" />', esc_attr( $signer_id ), esc_attr( $form_id ) );

			printf(
				'<button type="submit" class="button" form="%2$s">%1$s</button>',
				esc_html(
					sprintf(
						/* translators: %s: signer name or email address. */
						__( 'Resend invitation to %s', 'assinafy' ),
						(string) ( $signer['name'] ?? $signer['email'] ?? '' )
					)
				),
				esc_attr( $form_id )
			);

			echo '</div>';
		}
	}

	/**
	 * Render the cancel form.
	 *
	 * @param int    $post_id Document post id.
	 * @param string $status  Current status code.
	 */
	private function render_cancel_form( int $post_id, string $status ): void {
		$form_id = $this->open_form( DocumentActions::ACTION_CANCEL, $post_id );

		echo '<button type="submit" class="button button-link-delete" form="' . esc_attr( $form_id ) . '">' . esc_html__( 'Cancel and delete from Assinafy', 'assinafy' ) . '</button>';

		echo '<p class="description">';
		echo esc_html(
			'pending_signature' === $status
				? __( 'Signers can no longer open the document. This cannot be undone, and a cancelled request cannot be restarted — send the file again instead.', 'assinafy' )
				: __( 'Removes the document from your Assinafy account. This cannot be undone.', 'assinafy' )
		);
		echo '</p></div>';
	}

	/**
	 * Open the action controls and emit their associated form outside WordPress's edit form.
	 *
	 * Metaboxes are inside form#post, so nesting a form here corrupts its owner and nonce.
	 * Native HTML form attributes associate each control with its own footer form and nonce.
	 *
	 * @param string $action  `admin_post_` action name.
	 * @param int    $post_id Document post id.
	 *
	 * @return string Form id for the controls' form attribute.
	 */
	private function open_form( string $action, int $post_id ): string {
		$form_id = wp_unique_id( 'assinafy-action-' );
		add_action(
			'admin_footer',
			static function () use ( $action, $post_id, $form_id ): void {
				printf(
					'<form id="%1$s" action="%2$s" method="post"><input type="hidden" name="action" value="%3$s" /><input type="hidden" name="post" value="%4$d" /><input type="hidden" name="_wpnonce" value="%5$s" /></form>',
					esc_attr( $form_id ),
					esc_url( admin_url( 'admin-post.php' ) ),
					esc_attr( $action ),
					(int) $post_id,
					esc_attr( wp_create_nonce( $action . '_' . $post_id ) )
				);
			}
		);

		echo '<div class="assinafy-action-form">';

		return $form_id;
	}

	/**
	 * Render the document's history, newest first.
	 *
	 * The API's activity log is the plugin's history: there is no local audit table to drift
	 * out of step with it.
	 *
	 * Cached for the same window as the status refresh, and for the same reason: reloading
	 * the screen must not be a way to spend the rate-limit budget. Failures are not cached.
	 *
	 * @param string $document_id Remote document id.
	 */
	private function render_activities( string $document_id ): void {
		$key        = 'assinafy_activities_' . $document_id;
		$activities = get_transient( $key );

		if ( ! is_array( $activities ) ) {
			try {
				$activities = $this->clients->client()?->documents()->activities( $document_id ) ?? array();
			} catch ( \Throwable $e ) {
				echo '<h4>' . esc_html__( 'History', 'assinafy' ) . '</h4>';
				echo '<p class="description">' . esc_html( $e->getMessage() ) . '</p>';

				return;
			}

			set_transient( $key, $activities, self::REFRESH_AFTER );
		}

		if ( array() === $activities ) {
			return;
		}

		echo '<h4>' . esc_html__( 'History', 'assinafy' ) . '</h4>';
		echo '<table class="widefat striped assinafy-activities"><tbody>';

		foreach ( array_slice( $activities, 0, 20 ) as $activity ) {
			if ( ! is_array( $activity ) ) {
				continue;
			}

			/*
			 * `payload` arrives as a JSON array when the event carries no keys and as a JSON
			 * object when it does, so it is read as an array with defaults rather than as a
			 * shaped object.
			 */
			$payload = is_array( $activity['payload'] ?? null ) ? $activity['payload'] : array();
			$who     = (string) ( $payload['signer_name'] ?? $payload['signer_email'] ?? $payload['user_name'] ?? '' );

			echo '<tr>';
			echo '<td>' . esc_html( $this->format_time( (string) ( $activity['created_at'] ?? '' ) ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $activity['message'] ?? $activity['event'] ?? '' ) );

			if ( '' !== $who ) {
				echo ' <span class="description">' . esc_html( $who ) . '</span>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Format an API timestamp in the site's timezone.
	 *
	 * @param string $timestamp ISO 8601 instant from the API.
	 */
	private function format_time( string $timestamp ): string {
		$parsed = strtotime( $timestamp );

		if ( false === $parsed ) {
			return $timestamp;
		}

		return (string) wp_date( 'Y-m-d H:i', $parsed );
	}
}
