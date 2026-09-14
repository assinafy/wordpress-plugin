<?php
/**
 * The universal send hook.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Integrations;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\SourceReference;
use Assinafy\WP\Documents\SendService;
use WP_Error;

/**
 * The plugin's public extension contract: one action any theme, form plugin or bespoke
 * integration can fire to request a signature.
 *
 * Adapters translate their host's validated events into this shared contract. They own
 * field mapping and workflows; the core owns sending, recovery and status reconciliation:
 *
 *     do_action( 'assinafy_send_document', $args );
 *
 * Two actions are listened to:
 *
 * - `assinafy_send_document` sends inline. The calling request waits for three Assinafy API
 *   calls (upload, signer resolution, assignment) — typically one to two seconds. Use it from
 *   an admin screen or anywhere a person is waiting and wants to be told what happened.
 * - `assinafy_send_document_async` schedules the same send with `wp_schedule_single_event()`
 *   and returns immediately. Use it from a front-end request, a payment callback or anything
 *   else that must not block on a third-party API. WordPress refuses a second identical
 *   schedule inside ten minutes, which is a free extra layer of double-send protection on top
 *   of the `idempotency_key`.
 *
 * ## The `$args` contract
 *
 * Both actions take exactly one argument: an associative array, passed through to
 * `SendService::send()` unchanged. This is the whole vocabulary.
 *
 * Exactly one source for the PDF is required:
 *
 * - `file_path`     (string) Absolute path to a readable PDF on this server.
 * - `attachment_id` (int)    Media-library attachment id whose mime type is `application/pdf`.
 *
 * Also required:
 *
 * - `signers` (array<int, array<string, mixed>>) One entry per signer. Each entry takes:
 *     - `full_name`             (string) Required. The only field Assinafy insists on.
 *     - `email`                 (string) Required for the default `Email` verification.
 *     - `whatsapp_phone_number` (string) Optional, E.164. Bills credits and needs a paid plan.
 *     - `step`                  (int)    Optional, default 1. Signers sharing a step sign in
 *                                        parallel; a higher step is notified only once every
 *                                        lower step has completed.
 *     - `id`                    (string) Optional. An existing Assinafy signer id, used as-is
 *                                        instead of the look-up-then-create dance.
 *
	 * Optional:
	 *
 * - `source`          (array) `{integration: string, record_id: string}`. A provider slug
 *                              and opaque local record id, saved before upload. No form data.
 * - `message`         (string) Body of the invitation. Falls back to the configured default.
 * - `expires_at`      (string) ISO 8601 carrying `Z` or a `±HH:MM` offset. Falls back to the
 *                              configured expiry window.
 * - `post_id`         (int)    An existing `assinafy_document` post to update rather than
 *                              creating a new record.
 * - `idempotency_key` (string) Stable identifier for this send. Two calls carrying the same
 *                              key reuse or resume the recorded signature request.
 *                              Derive it from whatever makes the send unique — an order id, a
 *                              form entry id, a post id — and never from `time()`, `uniqid()`
 *                              or `wp_generate_uuid4()`, which defeat the guard entirely.
 *
 * ## Examples
 *
 *     // Inline. Blocks for the round trip; the caller can read the result action.
 *     do_action(
 *         'assinafy_send_document',
 *         array(
 *             'attachment_id'   => 412,
 *             'signers'         => array(
 *                 array( 'full_name' => 'Jane Doe', 'email' => 'jane@example.com' ),
 *                 array( 'full_name' => 'Sam Roe', 'email' => 'sam@example.com', 'step' => 2 ),
 *             ),
 *             'message'         => 'Please review and sign the attached agreement.',
 *             'idempotency_key' => 'contact-form-7-entry-1187',
 *         )
 *     );
 *
 *     // Deferred. Returns at once; the send runs on the next cron tick.
 *     do_action( 'assinafy_send_document_async', $args );
 *
 *     // Observe the outcome of either.
 *     add_action(
 *         'assinafy_send_document_result',
 *         function ( $result, array $args ) {
 *             if ( is_wp_error( $result ) ) {
 *                 error_log( $result->get_error_message() );
 *                 return;
 *             }
 *             // $result is the id of the assinafy_document post holding the request.
 *         },
 *         10,
 *         2
 *     );
 */
final class Hook {

	/**
	 * Send now, in the current request.
	 */
	public const ACTION = 'assinafy_send_document';

	/**
	 * Send on the next cron tick.
	 */
	public const ACTION_ASYNC = 'assinafy_send_document_async';

	/**
	 * Fired after either send with the mirror post id or a `WP_Error`, and the original `$args`.
	 */
	public const ACTION_RESULT = 'assinafy_send_document_result';

	/**
	 * @param SendService $sender The domain action every trigger routes through.
	 */
	public function __construct( private readonly SendService $sender ) {
	}

	/**
	 * Listen on both actions.
	 */
	public function register(): void {
		add_action( self::ACTION, array( $this, 'handle' ) );
		add_action( self::ACTION_ASYNC, array( $this, 'schedule' ) );
	}

	/**
	 * Perform one send and announce the outcome.
	 *
	 * Nothing is thrown back at the caller: an integration firing an action has no `try`
	 * around it and a fatal here would take down whatever page did the firing. Failures —
	 * the `WP_Error` `SendService` returns, and anything that escapes it — are reported
	 * through `assinafy_send_document_result` and recorded in the plugin log.
	 *
	 * @param mixed $args The `$args` array documented on this class. Anything else is refused.
	 */
	public function handle( mixed $args = array() ): void {
		$error = $this->validate( $args );

		if ( $error instanceof WP_Error ) {
			do_action( 'assinafy_send_document_result', $error, is_array( $args ) ? $args : array() );

			return;
		}

		/** @var array<string, mixed> $args */
		try {
			// SendService returns the mirror post id, or a WP_Error saying why it sent nothing.
			do_action( 'assinafy_send_document_result', $this->sender->send( $args ), $args );
		} catch ( \Throwable $e ) {
			do_action(
				'assinafy_send_document_result',
				new WP_Error( 'assinafy_send_failed', $e->getMessage(), array( 'type' => get_debug_type( $e ) ) ),
				$args
			);
		}
	}

	/**
	 * Queue one send for the next cron tick.
	 *
	 * The event re-fires `self::ACTION`, so the deferred path runs through exactly the same
	 * handler as the inline one — and any listener a site has added to the public action sees
	 * the deferred send too.
	 *
	 * @param mixed $args The `$args` array documented on this class.
	 */
	public function schedule( mixed $args = array() ): void {
		$error = $this->validate( $args );

		if ( $error instanceof WP_Error ) {
			do_action( 'assinafy_send_document_result', $error, is_array( $args ) ? $args : array() );

			return;
		}

		$scheduled = wp_schedule_single_event( time() + 1, self::ACTION, array( $args ), true );

		// A duplicate is the guard working, not a failure: the first schedule still runs.
		if ( is_wp_error( $scheduled ) && 'duplicate_event' !== $scheduled->get_error_code() ) {
			do_action( 'assinafy_send_document_result', $scheduled, $args );
		}
	}

	/**
	 * Check the shape a third party handed us.
	 *
	 * `SendService` validates the file and the signer detail; this only catches the mistakes
	 * that would otherwise surface as a `TypeError` inside somebody else's form handler.
	 *
	 * @param mixed $args Candidate argument array.
	 *
	 * @return WP_Error|null Null when the shape is usable.
	 */
	private function validate( mixed $args ): ?WP_Error {
		if ( ! is_array( $args ) ) {
			return new WP_Error(
				'assinafy_send_invalid_args',
				__( 'assinafy_send_document expects one array argument.', 'assinafy' )
			);
		}

		$source_problem = SourceReference::problem( $args );
		if ( null !== $source_problem ) {
			return $source_problem;
		}

		if ( ! $this->has_document( $args ) ) {
			return new WP_Error(
				'assinafy_send_no_document',
				__( 'assinafy_send_document needs either a file_path or an attachment_id.', 'assinafy' )
			);
		}

		if ( ! isset( $args['signers'] ) || ! is_array( $args['signers'] ) || array() === $args['signers'] ) {
			return new WP_Error(
				'assinafy_send_no_signers',
				__( 'assinafy_send_document needs at least one signer.', 'assinafy' )
			);
		}

		return null;
	}

	/**
	 * Whether either of the two PDF sources is present in a usable shape.
	 *
	 * @param array<mixed> $args Candidate argument array.
	 */
	private function has_document( array $args ): bool {
		$has_path = isset( $args['file_path'] ) && is_string( $args['file_path'] ) && '' !== $args['file_path'];

		return $has_path || ( isset( $args['attachment_id'] ) && 0 < (int) $args['attachment_id'] );
	}
}
