<?php
/**
 * Local mirror of one remote Assinafy document.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * The only file in the plugin that names a post meta key.
 *
 * Everything else — the send service, the status sync, the download proxy, the admin
 * screens, the privacy exporter — reads and writes through the typed accessors below, so
 * a change to the storage layout is a change to one file. A meta key spelled by hand in a
 * second place is how a mirror silently stops mirroring.
 *
 * Document, assignment and signer ids are opaque variable-length hex strings: 26, 27 and 28
 * characters have all been observed inside a single workspace. They are stored as plain
 * strings and validated only for shape (hex, non-empty, bounded), never for length.
 */
final class DocumentRecord {

	/**
	 * Remote document id, e.g. `104618b275d321f5de22240ebfda`.
	 */
	public const META_DOCUMENT_ID = '_assinafy_document_id';

	/**
	 * Opaque send identity used to resume a partially completed request.
	 */
	public const META_SEND_KEY = '_assinafy_send_key';

	/**
	 * Adapter identity and its opaque local record id; never a copied form submission.
	 */
	private const META_SOURCE = '_assinafy_source';

	/**
	 * One of the eleven codes from `GET /documents/statuses`.
	 */
	private const META_STATUS = '_assinafy_status';

	/**
	 * `'1'` once the remote document reports `is_closed: true`.
	 */
	public const META_IS_CLOSED = '_assinafy_is_closed';

	/**
	 * Remote assignment id. One per document, permanently.
	 */
	private const META_ASSIGNMENT_ID = '_assinafy_assignment_id';

	/**
	 * Per-signer progress, flattened from `assignment.signers[]` and `assignment.signing_urls[]`.
	 */
	private const META_SIGNERS = '_assinafy_signers';

	/**
	 * Erased signer ids mapped to their replacement tokens, retained across later syncs.
	 */
	private const META_REDACTED_SIGNERS = '_assinafy_redacted_signers';

	/**
	 * The artifact *names* present on the remote document, e.g. `['original', 'thumbnail']`.
	 */
	private const META_ARTIFACTS = '_assinafy_artifacts';

	/**
	 * Unix timestamp of the last successful hydrate.
	 */
	public const META_SYNCED_AT = '_assinafy_synced_at';

	/**
	 * Last transport or API failure, for the admin panel.
	 */
	private const META_LAST_ERROR = '_assinafy_last_error';

	/**
	 * Longest id the plugin will accept. Ids are 26-28 hex characters today; the bound
	 * exists to keep a malformed value out of a path segment, not to assert a length.
	 */
	private const MAX_ID_LENGTH = 64;

	/**
	 * The remote document id, or an empty string when the post is not a document mirror.
	 *
	 * @param int $post_id Local post id.
	 */
	public function document_id( int $post_id ): string {
		return $this->meta_string( $post_id, self::META_DOCUMENT_ID );
	}

	/**
	 * Persist a send identity before creating the remote document.
	 *
	 * @param int    $post_id Local post id.
	 * @param string $key     Opaque send identity.
	 */
	public function set_send_key( int $post_id, string $key ): void {
		update_post_meta( $post_id, self::META_SEND_KEY, $key );
	}

	/**
	 * The adapter record that requested this document, or an empty array for older sends.
	 *
	 * @param int $post_id Local document post id, not the adapter's record id.
	 * @return array{}|array{integration: string, record_id: string}
	 */
	public function source( int $post_id ): array {
		$source = get_post_meta( $post_id, self::META_SOURCE, true );

		return SourceReference::is_valid( $source )
			? array(
				'integration' => $source['integration'],
				'record_id'   => $source['record_id'],
			)
			: array();
	}

	/**
	 * Bind a source once. Retries may repeat it, but cannot retarget the document.
	 *
	 * @param int $post_id Local document post id.
	 * @param array{integration: string, record_id: string} $source Adapter record reference.
	 * @return bool Whether the requested reference is safely stored.
	 */
	public function set_source( int $post_id, array $source ): bool {
		if ( ! SourceReference::is_valid( $source ) ) {
			return false;
		}
		$source = array(
			'integration' => $source['integration'],
			'record_id'   => $source['record_id'],
		);
		$stored = $this->source( $post_id );
		if ( array() !== $stored ) {
			return $stored === $source;
		}
		update_post_meta( $post_id, self::META_SOURCE, wp_slash( $source ) );

		return $source === $this->source( $post_id );
	}

	/**
	 * The mirrored document status.
	 *
	 * One of the eleven codes `GET /documents/statuses` returns:
	 *
	 * ```
	 * {"status":200,"message":"","data":[
	 *   {"code":"uploading",           "deletable":false},
	 *   {"code":"uploaded",            "deletable":false},
	 *   {"code":"metadata_processing", "deletable":false},
	 *   {"code":"metadata_ready",      "deletable":true },
	 *   {"code":"expired",             "deletable":true },
	 *   {"code":"certificating",       "deletable":false},
	 *   {"code":"certificated",        "deletable":false},
	 *   {"code":"rejected_by_signer",  "deletable":true },
	 *   {"code":"pending_signature",   "deletable":true },
	 *   {"code":"rejected_by_user",    "deletable":true },
	 *   {"code":"failed",              "deletable":true }
	 * ]}
	 * ```
	 *
	 * There is no `ready` code. The SDK defines `DocumentResource::STATUS_READY = 'ready'`
	 * and the webhook catalog describes `document_ready` as "status is now ready"; the API
	 * never emits it. A document whose last signer has signed is `certificating`, then
	 * `certificated`.
	 *
	 * @param int $post_id Local post id.
	 */
	public function status( int $post_id ): string {
		return $this->meta_string( $post_id, self::META_STATUS );
	}

	/**
	 * Whether the remote document has reached a terminal state.
	 *
	 * Mirrors the document's own `is_closed` flag rather than a hand-maintained list of
	 * terminal codes, so a status added to the platform cannot strand a record in the
	 * reconcile queue forever.
	 *
	 * @param int $post_id Local post id.
	 */
	public function is_closed( int $post_id ): bool {
		return '1' === $this->meta_string( $post_id, self::META_IS_CLOSED );
	}

	/**
	 * The remote assignment id, or an empty string before an assignment exists.
	 *
	 * @param int $post_id Local post id.
	 */
	public function assignment_id( int $post_id ): string {
		return $this->meta_string( $post_id, self::META_ASSIGNMENT_ID );
	}

	/**
	 * Per-signer progress, flattened from the embedded assignment.
	 *
	 * Built from this slice of `GET /documents/{documentId}`:
	 *
	 * ```
	 * "assignment": {
	 *   "id": "1a09c15990f0144256b98ff38aa",
	 *   "signers": [
	 *     {"id":"19e6b92e7895332ed9708535d8c","full_name":"Jane Doe","email":"jane@example.com",
	 *      "completed":false,"step":1,"notified":true,
	 *      "verification_method":"Email","notification_methods":["Email"]}
	 *   ],
	 *   "signing_urls": [
	 *     {"signer_id":"19e6b92e7895332ed9708535d8c",
	 *      "url":"https://app-sandbox.assinafy.com.br/sign/104618d0d63884bc446c534e5ff5?email=jane%40example.com"}
	 *   ]
	 * }
	 * ```
	 *
	 * `signing_urls` lists only signers who have already been notified, so a signer on a
	 * later step has `notified: false` and `signing_url => ''` until the step before it
	 * completes. Callers must treat an empty URL as "not yet invited", never as an error.
	 *
	 * @param int $post_id Local post id.
	 *
	 * @return array<int, array{id: string, name: string, email: string, step: int, notified: bool, completed: bool, signing_url: string}>
	 */
	public function signers( int $post_id ): array {
		$signers = get_post_meta( $post_id, self::META_SIGNERS, true );

		return is_array( $signers ) ? array_values( $signers ) : array();
	}

	/**
	 * Replace the stored signer list.
	 *
	 * Exists for the privacy eraser, which redacts signer identity in place while keeping
	 * the document and assignment ids as signature evidence.
	 *
	 * @param int                                                                                                        $post_id Local post id.
	 * @param array<int, array{id: string, name: string, email: string, step: int, notified: bool, completed: bool, signing_url: string}> $signers Replacement list.
	 * @param array{}|array{email:string,token:string} $erasure Optional pending identity to redact durably.
	 */
	public function set_signers( int $post_id, array $signers, array $erasure = array() ): void {
		$registry = get_post_meta( $post_id, self::META_REDACTED_SIGNERS, true );
		$redacted = SignerRedaction::apply( $signers, $this->signers( $post_id ), is_array( $registry ) ? $registry : array(), $erasure );
		if ( array() !== $redacted['registry'] ) {
			update_post_meta( $post_id, self::META_REDACTED_SIGNERS, $redacted['registry'] );
		}
		update_post_meta( $post_id, self::META_SIGNERS, wp_slash( $redacted['signers'] ) );
	}

	/**
	 * The artifact names available on the remote document.
	 *
	 * The API's `artifacts` key is a map of name to URL:
	 *
	 * ```
	 * "artifacts": {
	 *   "original":  "https://sandbox.assinafy.com.br/v1/documents/104618b2…/download/original",
	 *   "thumbnail": "https://sandbox.assinafy.com.br/v1/documents/104618b2…/thumbnail"
	 * }
	 * ```
	 *
	 * Only the keys are stored. Every artifact URL needs the account API key to fetch, so a
	 * URL that never reaches the database can never be rendered into a page by mistake;
	 * downloads go through {@see DownloadProxy} instead.
	 *
	 * Observed key sets: `uploaded`, `metadata_processing` and `failed` carry `original`;
	 * `metadata_ready` and `pending_signature` add `thumbnail`; `certificated` adds
	 * `certificated`, `certificate-page` and `bundle`, plus `pades` when an ICP-Brasil
	 * signer took part.
	 *
	 * @param int $post_id Local post id.
	 *
	 * @return array<int, string>
	 */
	public function artifacts( int $post_id ): array {
		$artifacts = get_post_meta( $post_id, self::META_ARTIFACTS, true );

		if ( ! is_array( $artifacts ) ) {
			return array();
		}

		return array_values( array_filter( $artifacts, 'is_string' ) );
	}

	/**
	 * Unix timestamp of the last successful hydrate, or 0.
	 *
	 * @param int $post_id Local post id.
	 */
	public function synced_at( int $post_id ): int {
		return (int) get_post_meta( $post_id, self::META_SYNCED_AT, true );
	}

	/**
	 * Mark the record as visited without changing what it mirrors.
	 *
	 * The reconcile queue is ordered by this timestamp, so a record whose sync failed must
	 * still be touched — otherwise it stays at the head of the queue and starves every
	 * record behind it.
	 *
	 * @param int $post_id Local post id.
	 */
	public function touch( int $post_id ): void {
		update_post_meta( $post_id, self::META_SYNCED_AT, time() );
	}

	/**
	 * The last sync failure recorded against this record, or an empty string.
	 *
	 * @param int $post_id Local post id.
	 */
	public function last_error( int $post_id ): string {
		return $this->meta_string( $post_id, self::META_LAST_ERROR );
	}

	/**
	 * Record — or, with an empty message, clear — the last sync failure.
	 *
	 * @param int    $post_id Local post id.
	 * @param string $message Human-readable failure, already free of credentials.
	 */
	public function set_last_error( int $post_id, string $message ): void {
		if ( '' === $message ) {
			delete_post_meta( $post_id, self::META_LAST_ERROR );

			return;
		}

		update_post_meta( $post_id, self::META_LAST_ERROR, $message );
	}

	/**
	 * Map one API document onto the local record. The single place remote shape meets local.
	 *
	 * Input is the unwrapped `data` of `GET /documents/{documentId}` — the same shape the
	 * upload response carries, with the freshly created assignment merged in by the send
	 * service:
	 *
	 * ```
	 * {
	 *   "status": 200, "message": "",
	 *   "data": {
	 *     "resource": "document",
	 *     "id": "104618b275d321f5de22240ebfda",
	 *     "account_id": "{ACCOUNT_ID}",
	 *     "template_id": null,
	 *     "name": "test-3page.pdf",
	 *     "status": "pending_signature",
	 *     "artifacts": {
	 *       "original":  "https://sandbox.assinafy.com.br/v1/documents/104618b2…/download/original",
	 *       "thumbnail": "https://sandbox.assinafy.com.br/v1/documents/104618b2…/thumbnail"
	 *     },
	 *     "is_closed": false,
	 *     "signing_url": "https://app-sandbox.assinafy.com.br/sign/104618b275d321f5de22240ebfda",
	 *     "decline_reason": null,
	 *     "declined_by": null,
	 *     "tags": [],
	 *     "created_at": "2026-09-13T18:40:10Z",
	 *     "updated_at": "2026-09-13T18:40:12Z",
	 *     "assignment": {
	 *       "resource": "assignment",
	 *       "id": "1a09c15990f0144256b98ff38aa",
	 *       "sender_email": "sender@example.com",
	 *       "method": "virtual",
	 *       "expires_at": "2026-12-31T23:59:59Z",
	 *       "message": "Please review and sign.",
	 *       "signers": [
	 *         {"id":"19e6b92e7895332ed9708535d8c","full_name":"Jane Doe","email":"jane@example.com",
	 *          "whatsapp_phone_number":null,"government_id":null,"has_accepted_terms":true,
	 *          "completed":false,"notification_history":[{"event":"signature_request","status":"sent",
	 *          "error_code":null,"error_message":null,"sent_at":"2026-09-13T18:44:16Z","failed_at":null}],
	 *          "verification_method":"Email","notification_methods":["Email"],"step":1,"notified":true}
	 *       ],
	 *       "copy_receivers": [],
	 *       "items": [{"id":"104618d7fae557b29898ca4475b7","page":null,"display_settings":[],
	 *                  "value":null,"completed":false,
	 *                  "signer":{"id":"19e6b92e7895332ed9708535d8c","full_name":"Jane Doe"},
	 *                  "field":{"id":"102d25a48bc7357b93f9b8e01b24","name":"Virtual","type":"virtual"}}],
	 *       "summary": {"signer_count":2,"completed_count":0,"signers":[…]},
	 *       "signing_urls": [
	 *         {"signer_id":"19e6b92e7895332ed9708535d8c",
	 *          "url":"https://app-sandbox.assinafy.com.br/sign/104618d0d63884bc446c534e5ff5?email=jane%40example.com"}
	 *       ]
	 *     },
	 *     "pages": [
	 *       {"id":"104618b2ac6a3b2f99d0fd2c5f43","number":1,"height":1755,"width":1240,
	 *        "download_url":"https://sandbox.assinafy.com.br/v1/documents/104618b2…/pages/104618b2…/download"}
	 *     ]
	 *   }
	 * }
	 * ```
	 *
	 * `assignment` is `null` until one exists, `items[].display_settings` is an array for a
	 * virtual assignment and an object for a collect one, and `items[].page` is null for
	 * virtual — every read below therefore goes through `??` with an array default rather
	 * than assuming a shape.
	 *
	 * @param int                  $post_id  Local post id.
	 * @param array<string, mixed> $document Unwrapped API document.
	 *
	 * @return string The status the record held before this call, for change detection.
	 */
	public function hydrate_from_api( int $post_id, array $document ): string {
		$previous    = $this->status( $post_id );
		$document_id = self::text( $document, 'id' );

		if ( '' !== $document_id ) {
			update_post_meta( $post_id, self::META_DOCUMENT_ID, $document_id );
		}

		update_post_meta( $post_id, self::META_STATUS, self::text( $document, 'status' ) );
		update_post_meta( $post_id, self::META_IS_CLOSED, ! empty( $document['is_closed'] ) ? '1' : '0' );
		update_post_meta(
			$post_id,
			self::META_ARTIFACTS,
			array_values( array_filter( array_keys( self::rows( $document, 'artifacts' ) ), 'is_string' ) )
		);

		$assignment    = self::rows( $document, 'assignment' );
		$assignment_id = self::text( $assignment, 'id' );

		if ( '' !== $assignment_id ) {
			update_post_meta( $post_id, self::META_ASSIGNMENT_ID, $assignment_id );
		}

		// An unassigned upload must retain a pending receipt's retry details and redactions.
		if ( array() !== $assignment || '' !== $this->assignment_id( $post_id ) || array_filter( array_column( $this->signers( $post_id ), 'id' ) ) ) {
			$this->set_signers( $post_id, self::flatten_signers( $assignment ) );
		}

		update_post_meta( $post_id, self::META_SYNCED_AT, time() );

		$this->follow_remote_name( $post_id, self::text( $document, 'name' ) );

		return $previous;
	}

	/**
	 * Read one value of an API payload as a string.
	 *
	 * Anything that is not a string is not an id, a status or a name, so it becomes `''` and
	 * the caller's own emptiness check decides what to do about it.
	 *
	 * @param array<array-key, mixed> $data API payload.
	 * @param string                  $key  Key to read.
	 */
	private static function text( array $data, string $key ): string {
		$value = $data[ $key ] ?? null;

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Read one value of an API payload as an array.
	 *
	 * An empty JSON object decodes to an empty PHP array, so `artifacts`, `assignment`,
	 * `signers` and `signing_urls` each arrive as a map or as a list depending on how full
	 * they are, and `assignment` is `null` outright until one exists.
	 *
	 * @param array<array-key, mixed> $data API payload.
	 * @param string                  $key  Key to read.
	 *
	 * @return array<array-key, mixed>
	 */
	private static function rows( array $data, string $key ): array {
		$value = $data[ $key ] ?? null;

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Keep the post title on the name the server holds.
	 *
	 * The API folds diacritics and replaces unsupported characters at upload, so the stored
	 * name is the server's, never the one that was sent.
	 *
	 * @param int    $post_id Local post id.
	 * @param string $name    Name carried by the API document.
	 */
	private function follow_remote_name( int $post_id, string $name ): void {
		if ( '' === $name || $name === get_post_field( 'post_title', $post_id ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $name,
			)
		);
	}

	/**
	 * Whether a string is shaped like an Assinafy id.
	 *
	 * Ids are opaque variable-length lowercase hex. 26, 27 and 28 characters have all been
	 * observed in one workspace, so the only safe check is "hex, non-empty, not absurd" —
	 * a fixed-length pattern rejects real ids.
	 *
	 * @param string $id Candidate id.
	 */
	public static function is_valid_id( string $id ): bool {
		return '' !== $id
			&& strlen( $id ) <= self::MAX_ID_LENGTH
			&& 1 === preg_match( '/^[0-9a-f]+$/i', $id );
	}

	/**
	 * Flatten `assignment.signers[]` and `assignment.signing_urls[]` into one list.
	 *
	 * @param array<array-key, mixed> $assignment Embedded assignment, or an empty array.
	 *
	 * @return array<int, array{id: string, name: string, email: string, step: int, notified: bool, completed: bool, signing_url: string}>
	 */
	private static function flatten_signers( array $assignment ): array {
		$urls = array();

		foreach ( self::rows( $assignment, 'signing_urls' ) as $entry ) {
			if ( is_array( $entry ) ) {
				$urls[ self::text( $entry, 'signer_id' ) ] = self::text( $entry, 'url' );
			}
		}

		$flattened = array();

		foreach ( self::rows( $assignment, 'signers' ) as $signer ) {
			if ( ! is_array( $signer ) ) {
				continue;
			}

			$id = self::text( $signer, 'id' );

			$flattened[] = array(
				'id'          => $id,
				'name'        => self::text( $signer, 'full_name' ),
				'email'       => self::text( $signer, 'email' ),
				'step'        => isset( $signer['step'] ) ? (int) $signer['step'] : 1,
				'notified'    => ! empty( $signer['notified'] ),
				'completed'   => ! empty( $signer['completed'] ),
				'signing_url' => $urls[ $id ] ?? '',
			);
		}

		return $flattened;
	}

	/**
	 * Read one meta value as a string.
	 *
	 * @param int    $post_id Local post id.
	 * @param string $key     Meta key.
	 */
	private function meta_string( int $post_id, string $key ): string {
		$value = get_post_meta( $post_id, $key, true );

		return is_string( $value ) ? $value : '';
	}
}
