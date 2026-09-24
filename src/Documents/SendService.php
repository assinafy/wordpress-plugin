<?php
/**
 * The send-for-signature domain action.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Resources\DocumentResource;
use Assinafy\SDK\Support\Iso8601;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;
use WP_Error;
use WP_Upgrader;

/**
 * One entry point for every way a document leaves this site: the admin send screen, the
 * `assinafy_send_document` action, WooCommerce order completion, WP-CLI and cron all call
 * {@see self::send()} with the same argument array.
 *
 * The order of operations is not negotiable. Local validation runs before any request
 * because **every upload the API rejects with 415 still creates a document row in the
 * customer's workspace with status `failed`, and the 415 body does not carry its id** — the
 * plugin cannot clean up after itself, so it must not create the mess. The affordability
 * check cannot join it there: `estimate-cost` is addressed to a document that has not been
 * assigned yet, so it runs on the freshly uploaded one, between the upload and the
 * assignment that spends the allowance.
 *
 * The assignment is created with `method: virtual` while the document is still `uploaded`,
 * which the API allows: it promotes the document to `pending_signature` by itself once page
 * rendering finishes. Waiting for `metadata_ready` first would block the request for four to
 * nine seconds and buy nothing.
 */
final class SendService {

	/**
	 * Prefix of the per-send lock transient. Shares the plugin's global prefix so
	 * `uninstall.php` can sweep it with the other transients.
	 */
	public const LOCK_PREFIX = 'assinafy_send_lock_';

	/**
	 * How long one lock holds, in seconds.
	 */
	private const LOCK_TTL = 300;

	/**
	 * Prices a request against the account before its assignment is created.
	 */
	private readonly CostEstimate $cost;

	/**
	 * `CostEstimate` is built from the same collaborators, so it is constructed here rather
	 * than threaded through every caller of this constructor.
	 *
	 * @param ClientFactory  $clients API client factory.
	 * @param DocumentRecord $records Local mirror.
	 * @param Log            $log     Plugin event log.
	 */
	public function __construct(
		private readonly ClientFactory $clients,
		private readonly DocumentRecord $records,
		private readonly Log $log
	) {
		$this->cost = new CostEstimate( $clients, $log );
	}

	/**
	 * Upload a PDF, resolve its signers and request signatures.
	 *
	 * `$args` accepts:
	 *
	 * - `attachment_id` (int) or `file_path` (string) — the PDF. An attachment must carry
	 *   the `application/pdf` mime type.
	 * - `signers` (array) — one or more rows of `{name|full_name, email, phone?, step?,
	 *   verification_method?, notification_methods?, id?}`.
	 * - `message` (string, optional) — falls back to the configured default message.
	 * - `expires_at` (string, optional) — ISO 8601 with `Z` or an explicit offset; falls back
	 *   to the configured expiry in days. `0` days means no expiry.
	 * - `post_id` (int, optional) — reuse an empty mirror record instead of creating one.
	 * - `idempotency_key` (string, optional) — defaults to a digest of the file and request,
	 *   scoped to the account and environment. Completed keys remain tied to their mirror.
	 *   Adapters must namespace supplied keys with their integration, record and workflow.
	 * - `source` (array, optional) — `{integration: string, record_id: string}` identifying
	 *   the adapter's record. Persisted before upload and immutable on subsequent retries.
	 *
	 * The wire traffic this produces, in order:
	 *
	 * ```http
	 * GET /v1/accounts/{ACCOUNT_ID}/signers?search=jane%40example.com&page=1&per-page=100
	 * POST /v1/accounts/{ACCOUNT_ID}/signers
	 * {"full_name":"Jane Doe","email":"jane@example.com"}
	 *
	 * HTTP/2 200
	 * {"status":200,"message":"","data":{
	 *   "resource":"signer","id":"104618b9dc17b2b771659e8b1360","full_name":"Jane Doe",
	 *   "email":"jane@example.com","whatsapp_phone_number":null,"government_id":null,
	 *   "has_accepted_terms":false}}
	 * ```
	 * ```http
	 * POST /v1/accounts/{ACCOUNT_ID}/documents
	 * Content-Type: multipart/form-data; boundary=…      (part name exactly "file")
	 *
	 * HTTP/2 200
	 * {"status":200,"message":"","data":{
	 *   "resource":"document","id":"104618b275d321f5de22240ebfda","name":"contract.pdf",
	 *   "status":"uploaded","artifacts":{"original":"https://…/download/original"},
	 *   "is_closed":false,"tags":[],"pages":[],
	 *   "created_at":"2026-09-13T18:40:10Z","updated_at":"2026-09-13T18:40:10Z"}}
	 * ```
	 * ```http
	 * POST /v1/documents/104618b275d321f5de22240ebfda/assignments/estimate-cost
	 * {"method":"virtual","signers":[{"verification_method":"Email","notification_methods":["Email"]}]}
	 *
	 * HTTP/2 200
	 * {"status":200,"message":"","data":{
	 *   "documents":1,"credits":0,"needs_extra_document":false,"extra_document_cost":0,
	 *   "total_credits":0,"breakdown":[],"document_balance":480,"credit_balance":0,
	 *   "has_sufficient_resources":true,"blocking_reason":null,"message":null}}
	 * ```
	 * ```http
	 * POST /v1/documents/104618d0d63884bc446c534e5ff5/assignments
	 * {"method":"virtual",
	 *  "signers":[{"id":"19e6b92e7895332ed9708535d8c","verification_method":"Email",
	 *              "notification_methods":["Email"],"step":1}],
	 *  "message":"Please review and sign.","expires_at":"2026-12-31T23:59:59Z"}
	 *
	 * HTTP/2 200
	 * {"status":200,"message":"","data":{
	 *   "resource":"assignment","id":"1a09c15990f0144256b98ff38aa",
	 *   "sender_email":"sender@example.com","method":"virtual",
	 *   "expires_at":"2026-12-31T23:59:59Z","message":"Please review and sign.",
	 *   "signers":[{"id":"19e6b92e7895332ed9708535d8c","full_name":"Jane Doe",
	 *               "email":"jane@example.com","completed":false,"step":1,"notified":true,
	 *               "verification_method":"Email","notification_methods":["Email"],
	 *               "notification_history":[{"event":"signature_request","status":"sent",
	 *                 "error_code":null,"error_message":null,
	 *                 "sent_at":"2026-09-13T18:44:16Z","failed_at":null}]}],
	 *   "copy_receivers":[],"items":[…],"summary":{"signer_count":1,"completed_count":0,"signers":[…]},
	 *   "signing_urls":[{"signer_id":"19e6b92e7895332ed9708535d8c",
	 *     "url":"https://app-sandbox.assinafy.com.br/sign/104618d0d63884bc446c534e5ff5?email=jane%40example.com"}]}}
	 * ```
	 *
	 * The lookup-then-create signer step is mandatory rather than defensive: `email` is
	 * unique per account, so a blind create for a returning signer is a hard
	 * 400 `"Um signatário com este e-mail já existe."` The SDK's own
	 * `signers()->findByEmail()` runs the paged case-insensitive exact scan this needs,
	 * because `?search=` is a substring match and would otherwise hand back
	 * `jane@example.com.test` for `jane@example.com`.
	 *
	 * @param array<string, mixed> $args Send arguments, described above.
	 *
	 * @return int|WP_Error The mirror post id, or a failure carrying the recovery post id when reserved.
	 */
	public function send( array $args ): int|WP_Error {
		$source_problem = SourceReference::problem( $args );
		if ( null !== $source_problem ) {
			return $source_problem;
		}

		$client = $this->clients->client();

		if ( null === $client ) {
			return $this->clients->error() ?? new WP_Error(
				'assinafy_not_configured',
				__( 'Connect the plugin to Assinafy before sending a document.', 'assinafy' )
			);
		}

		$prepared = $this->preflight( $args );

		if ( $prepared instanceof WP_Error ) {
			return $prepared;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		// Transients are not atomic, even with an external object cache. Core's database
		// lock serializes the read and write so simultaneous requests cannot both upload.
		if ( ! WP_Upgrader::create_lock( $prepared['lock'], self::LOCK_TTL ) ) {
			return $this->in_progress();
		}

		try {
			return $this->send_locked( $client, $args, $prepared );
		} finally {
			WP_Upgrader::release_lock( $prepared['lock'] );
		}
	}

	/**
	 * Perform a send while holding the database lock.
	 *
	 * @param AssinafyClient $client Configured client.
	 * @param array<string, mixed> $args Send arguments.
	 * @param array{file: string, signers: array<int, array<string, mixed>>, lock: string} $prepared Validated inputs.
	 * @return int|WP_Error The mirror post id or failure.
	 */
	private function send_locked( AssinafyClient $client, array $args, array $prepared ): int|WP_Error {
		$held = $this->claim_lock( $prepared['lock'] );

		if ( null !== $held ) {
			return is_int( $held ) ? SourceReference::bind( $this->records, $held, $args ) : $held;
		}

		$existing = ( new DocumentIndex() )->find_by_send_key( $prepared['lock'] );
		$bound    = $existing > 0 ? SourceReference::bind( $this->records, $existing, $args ) : 0;
		if ( $bound instanceof WP_Error ) {
			delete_transient( $prepared['lock'] );
			return $bound;
		}

		$args['message']    = $this->message( $args );
		$args['expires_at'] = $this->expires_at( $args );
		$result             = ( new SendAttempt( $this->records, $this->log, $this->cost ) )->run( $client, $prepared, $args, $existing );

		if ( $result instanceof WP_Error ) {
			delete_transient( $prepared['lock'] );
			return $result;
		}

		set_transient( $prepared['lock'], $result, self::LOCK_TTL );
		return $result;
	}

	/**
	 * Run every check that must pass before the API is touched, and derive the lock key from
	 * what survives.
	 *
	 * Nothing here reaches the network, which is the point: an upload the API rejects with 415
	 * still creates a document row in the customer's workspace with status `failed`, and the
	 * 415 body does not carry its id, so a file that cannot be uploaded has to be refused
	 * locally.
	 *
	 * @param array<string, mixed> $args Send arguments.
	 *
	 * @return array{file: string, signers: array<int, array<string, mixed>>, lock: string}|WP_Error
	 *         The validated inputs and the lock that guards them, or the first refusal.
	 */
	private function preflight( array $args ): array|WP_Error {
		$file = $this->resolve_file( $args );

		if ( $file instanceof WP_Error ) {
			return $file;
		}

		$signers = Signers::normalize( is_array( $args['signers'] ?? null ) ? $args['signers'] : array() );

		if ( $signers instanceof WP_Error ) {
			return $signers;
		}

		$sequence = SendAttempt::sequence_problem( $signers );
		if ( null !== $sequence ) {
			return $sequence;
		}

		$expires_at = $this->expires_at( $args );
		if ( null !== $expires_at && ( null !== Iso8601::reasonInvalid( $expires_at ) || strtotime( $expires_at ) <= time() ) ) {
			return new WP_Error(
				'assinafy_invalid_expiry',
				__( 'The signature deadline must be a valid date in the future.', 'assinafy' )
			);
		}

		try {
			DocumentResource::assertUploadable( $file );
		} catch ( \Throwable $e ) {
			$this->log->add( 'send_rejected_locally', array( 'error' => $e->getMessage() ) );

			return new WP_Error( 'assinafy_invalid_pdf', $e->getMessage() );
		}

		return array(
			'file'    => $file,
			'signers' => $signers,
			'lock'    => self::LOCK_PREFIX . $this->idempotency_key( $args, $file, $signers ),
		);
	}

	/**
	 * Take the send lock, or report what its current holder left behind.
	 *
	 * The caller holds WordPress's database lock while reading and writing this transient,
	 * so only one request can reach the upload. The marker is -1 while
	 * the send is in flight and the post id once it lands, so a duplicate submit can hand back
	 * the record the first one created.
	 *
	 * @param string $lock Lock transient name.
	 *
	 * @return int|WP_Error|null Null when the lock was taken and the send may proceed; the
	 *                           existing record, or the reason to wait, when it was not.
	 */
	private function claim_lock( string $lock ): int|WP_Error|null {
		$locked = get_transient( $lock );

		if ( false !== $locked ) {
			$this->log->add( 'send_deduplicated', array( 'post_id' => (int) $locked ) );

			return (int) $locked > 0
				? (int) $locked
				: $this->in_progress();
		}

		set_transient( $lock, -1, self::LOCK_TTL );

		return null;
	}

	/**
	 * The shared response to either kind of active send lock.
	 */
	private function in_progress(): WP_Error {
		return new WP_Error(
			'assinafy_send_in_progress',
			__( 'This document is already being sent. Wait for the first attempt to finish.', 'assinafy' )
		);
	}

	/**
	 * Resolve the PDF to send.
	 *
	 * An attachment is checked for the `application/pdf` mime type here as well as on the
	 * send screen, because the `assinafy_send_document` action reaches this method without
	 * passing through any screen.
	 *
	 * @param array<string, mixed> $args Send arguments.
	 *
	 * @return string|WP_Error Absolute path.
	 */
	private function resolve_file( array $args ): string|WP_Error {
		$attachment_id = (int) ( $args['attachment_id'] ?? 0 );

		if ( $attachment_id > 0 ) {
			if ( 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
				return new WP_Error(
					'assinafy_not_a_pdf',
					__( 'Only PDF attachments can be sent for signature.', 'assinafy' )
				);
			}

			// get_attached_file() answers false when the attachment has no file; the cast
			// folds that into the same empty string an empty meta row produces.
			$path = (string) get_attached_file( $attachment_id );

			if ( '' === $path ) {
				return new WP_Error(
					'assinafy_file_missing',
					__( 'The selected attachment has no file on disk.', 'assinafy' )
				);
			}

			return $path;
		}

		$path = is_string( $args['file_path'] ?? null ) ? $args['file_path'] : '';

		if ( '' === $path ) {
			return new WP_Error(
				'assinafy_no_file',
				__( 'Choose a PDF to send: pass either attachment_id or file_path.', 'assinafy' )
			);
		}

		return $path;
	}

	/**
	 * The message shown to signers, falling back to the configured default.
	 *
	 * @param array<string, mixed> $args Send arguments.
	 */
	private function message( array $args ): ?string {
		$message = sanitize_textarea_field( (string) ( $args['message'] ?? '' ) );

		if ( '' === $message ) {
			$message = (string) Settings::get( Settings::OPTION_MESSAGE );
		}

		return '' === $message ? null : $message;
	}

	/**
	 * The expiry instant, or null for no expiry.
	 *
	 * The API requires a trailing `Z` or an explicit offset and rejects an instant in the
	 * past with 400 `"A expiração deve maior que a data/hora atual."`, so the default is
	 * always computed forward from now.
	 *
	 * @param array<string, mixed> $args Send arguments.
	 */
	private function expires_at( array $args ): ?string {
		$explicit = sanitize_text_field( (string) ( $args['expires_at'] ?? '' ) );

		if ( '' !== $explicit ) {
			return $explicit;
		}

		$days = (int) Settings::get( Settings::OPTION_EXPIRY_DAYS );

		return $days > 0 ? gmdate( 'Y-m-d\TH:i:s\Z', time() + ( $days * DAY_IN_SECONDS ) ) : null;
	}

	/**
	 * The lock key for this send.
	 *
	 * A caller-supplied key wins; otherwise the digest of the file and the signer set makes
	 * a repeated trigger — a double-clicked submit button, a WooCommerce order completing
	 * twice — resolve to the same lock and therefore to the same document.
	 *
	 * @param array<string, mixed>             $args    Send arguments.
	 * @param string                           $file    Resolved file path.
	 * @param array<int, array<string, mixed>> $signers Normalised signers.
	 */
	private function idempotency_key( array $args, string $file, array $signers ): string {
		$supplied = (string) ( $args['idempotency_key'] ?? '' );

		$request = array(
			'account'     => $this->clients->client()?->getConfig()->getAccountId(),
			'environment' => $this->clients->base_url(),
			'request'     => '' !== $supplied ? $supplied : array(
				// Equal filenames and sizes do not mean equal PDF contents.
				'file'    => hash_file( 'sha256', $file ),
				'post'    => (int) ( $args['post_id'] ?? 0 ),
				'author'  => get_current_user_id(),
				'signers' => $signers,
				'message' => $this->message( $args ),
				'expiry'  => $args['expires_at'] ?? Settings::get( Settings::OPTION_EXPIRY_DAYS ),
			),
		);

		// Supplied keys retain their existing hash so an older partial send still resumes.
		// Adapters namespace those keys themselves; the default digest includes the source.
		if ( '' === $supplied && isset( $args['source'] ) ) {
			$request['source'] = array( $args['source']['integration'], $args['source']['record_id'] );
		}
		$seed = wp_json_encode( $request );

		return md5( is_string( $seed ) ? $seed : $file );
	}
}
