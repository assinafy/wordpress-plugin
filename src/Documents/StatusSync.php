<?php
/**
 * Remote-to-local status reconciliation.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Http\RateLimit;
use WP_Error;

/**
 * Pulls the authoritative state of a document back from the API and fires the hooks
 * integrators bind to.
 *
 * This class is what makes webhooks optional. A site that never registers an endpoint — or
 * whose endpoint was taken over by another integration sharing the same Assinafy account, or
 * which sat behind a circuit breaker for an hour — stays correct because the cron keeps
 * asking. The webhook receiver uses the same code path: a delivery is a hint that something
 * changed, and {@see self::sync_one()} is the only thing that writes local state.
 */
final class StatusSync {

	/**
	 * Records reconciled per cron run. Each one costs a single `GET /documents/{id}` against
	 * a budget of 120 requests a minute.
	 */
	private const BATCH = 20;

	/**
	 * Stop the run when fewer than this many requests remain in the rate-limit window, so a
	 * background sweep can never starve an administrator's send.
	 */
	private const RATE_FLOOR = 30;

	/**
	 * @param ClientFactory  $clients API client factory.
	 * @param DocumentRecord $records Local mirror.
	 * @param DocumentIndex  $index   Finds the records to reconcile.
	 */
	public function __construct(
		private readonly ClientFactory $clients,
		private readonly DocumentRecord $records,
		private readonly DocumentIndex $index = new DocumentIndex()
	) {
	}

	/**
	 * Re-read one document from the API and write the result onto its local record.
	 *
	 * ```http
	 * GET /v1/documents/104618b275d321f5de22240ebfda
	 * X-Api-Key: {API_KEY}
	 * Accept: application/json
	 * ```
	 * ```json
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
	 *       "id": "1a09c15990f0144256b98ff38aa", "method": "virtual",
	 *       "sender_email": "sender@example.com", "expires_at": "2026-12-31T23:59:59Z",
	 *       "message": "Please review and sign.", "copy_receivers": [], "items": [ … ],
	 *       "signers": [{"id":"19e6b92e7895332ed9708535d8c","full_name":"Jane Doe",
	 *                    "email":"jane@example.com","completed":false,"step":1,"notified":true}],
	 *       "summary": {"signer_count":1,"completed_count":0,"signers":[ … ]},
	 *       "signing_urls": [{"signer_id":"19e6b92e7895332ed9708535d8c",
	 *         "url":"https://app-sandbox.assinafy.com.br/sign/104618b275d321f5de22240ebfda?email=jane%40example.com"}]
	 *     },
	 *     "pages": [{"id":"104618b2ac6a3b2f99d0fd2c5f43","number":1,"height":1755,"width":1240,
	 *                "download_url":"https://sandbox.assinafy.com.br/v1/documents/104618b2…/pages/104618b2…/download"}]
	 *   }
	 * }
	 * ```
	 *
	 * A deleted or unknown document answers
	 * `404 {"status":404,"data":null,"message":"Documento não encontrado."}`.
	 *
	 * This single call recovers everything about a signature request: there is no
	 * assignment-detail endpoint, `?expand=assignment` is a no-op, and both `assignment` and
	 * `pages` are always embedded.
	 *
	 * @param string $document_id Remote document id.
	 *
	 * @return int|WP_Error The mirror post id.
	 */
	public function sync_one( string $document_id ): int|WP_Error {
		$post_id = $this->index->find_by_document_id( $document_id );

		if ( $post_id < 1 ) {
			return new WP_Error(
				'assinafy_unknown_document',
				__( 'This site has no record of that Assinafy document.', 'assinafy' )
			);
		}

		return $this->sync_post( $post_id, $document_id );
	}

	/**
	 * The hourly cron runner: bring the oldest open records up to date.
	 *
	 * Only records the plugin itself sent are touched, and only while they are open — a
	 * `certificated`, `expired`, `failed` or declined document reports `is_closed: true` and
	 * leaves the queue for good. Within one run each record costs one
	 * `GET /documents/{documentId}`; the loop stops early when the rate-limit budget
	 * recorded by the transport (`X-Rate-Limit-Remaining`, out of 120 per minute) is close
	 * to spent, because an interactive send must never queue behind a background sweep.
	 *
	 * @return int Records reconciled in this run.
	 */
	public function reconcile(): int {
		if ( null === $this->clients->client() ) {
			return 0;
		}

		$synced = 0;

		foreach ( $this->index->stale_post_ids( self::BATCH ) as $post_id ) {
			if ( self::budget_spent() ) {
				break;
			}

			$document_id = $this->records->document_id( $post_id );

			if ( '' === $document_id ) {
				$this->records->touch( $post_id );

				continue;
			}

			if ( ! $this->sync_post( $post_id, $document_id ) instanceof WP_Error ) {
				++$synced;
			}
		}

		return $synced;
	}

	/**
	 * Fetch, hydrate, and announce the change.
	 *
	 * @param int    $post_id     Local post id.
	 * @param string $document_id Remote document id.
	 *
	 * @return int|WP_Error The mirror post id.
	 */
	private function sync_post( int $post_id, string $document_id ): int|WP_Error {
		$client = $this->clients->client();

		if ( null === $client ) {
			return $this->clients->error() ?? new WP_Error(
				'assinafy_not_configured',
				__( 'Assinafy is not configured.', 'assinafy' )
			);
		}

		try {
			$document = $client->documents()->get( $document_id );

			self::assert_document( $document, $document_id, $client->getConfig()->getAccountId() );
		} catch ( \Throwable $e ) {
			// Touch regardless: a record that cannot be synced must still move to the back
			// of the queue, or it blocks every record behind it on every run.
			$this->records->touch( $post_id );
			$this->records->set_last_error( $post_id, $e->getMessage() );

			return new WP_Error(
				$e instanceof ApiException && 404 === $e->getStatusCode()
					? 'assinafy_document_gone'
					: 'assinafy_sync_failed',
				$e->getMessage()
			);
		}

		$previous = $this->records->hydrate_from_api( $post_id, $document );
		$current  = $this->records->status( $post_id );

		$this->records->set_last_error( $post_id, '' );

		if ( $current !== $previous ) {
			$this->announce( $post_id, $current, $previous, $document );
		}

		return $post_id;
	}

	/**
	 * Refuse malformed success envelopes before they can erase the last known state.
	 *
	 * @param array<string, mixed> $document API response.
	 * @param string $document_id Requested document.
	 * @param string $account_id Configured account.
	 * @throws \UnexpectedValueException When the response cannot identify this document.
	 */
	public static function assert_document( array $document, string $document_id, string $account_id ): void {
		if ( ( $document['id'] ?? null ) !== $document_id
			|| ! is_string( $document['status'] ?? null ) || '' === $document['status']
			|| ( isset( $document['account_id'] ) && $document['account_id'] !== $account_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Translate the user-facing error here; consumers escape it when displaying HTML.
			throw new \UnexpectedValueException( __( 'Assinafy returned an invalid or mismatched document.', 'assinafy' ) );
		}
	}

	/**
	 * Fire the integration hooks for a status transition.
	 *
	 * `assinafy_document_status_changed` fires on every transition. The four specific hooks
	 * cover the terminal states an integrator usually cares about. Note that there is no
	 * "all signed" status to hook: the last signature moves the document to `certificating`
	 * and then `certificated`, and the platform's `document_ready` event means "the last
	 * signer signed", not a status named `ready`.
	 *
	 * @param int                  $post_id  Local post id.
	 * @param string               $current  New status code.
	 * @param string               $previous Status code held before the sync.
	 * @param array<string, mixed> $document The document as the API returned it.
	 */
	private function announce( int $post_id, string $current, string $previous, array $document ): void {
		/**
		 * Fires whenever a mirrored document changes status.
		 *
		 * @param int    $post_id  Local post id of the document mirror.
		 * @param string $current  New status code, one of the eleven `GET /documents/statuses` codes.
		 * @param string $previous Status code held before this sync, empty on the first one.
		 */
		do_action( 'assinafy_document_status_changed', $post_id, $current, $previous );

		/**
		 * Fires once a mirrored document reaches a terminal state.
		 *
		 * Four outcomes, four hooks: `assinafy_document_certificated`,
		 * `assinafy_document_rejected` (a signer declined or an account user cancelled),
		 * `assinafy_document_expired` and `assinafy_document_failed`.
		 *
		 * @param int                  $post_id  Local post id of the document mirror.
		 * @param array<string, mixed> $document The document exactly as the API returned it.
		 */
		switch ( $current ) {
			case 'certificated':
				do_action( 'assinafy_document_certificated', $post_id, $document );
				break;

			case 'rejected_by_signer':
			case 'rejected_by_user':
				do_action( 'assinafy_document_rejected', $post_id, $document );
				break;

			case 'expired':
				do_action( 'assinafy_document_expired', $post_id, $document );
				break;

			case 'failed':
				do_action( 'assinafy_document_failed', $post_id, $document );
				break;
		}
	}

	/**
	 * Whether the rate-limit budget is too thin to keep sweeping.
	 *
	 * The API reports `X-Rate-Limit-Limit: 120`, `X-Rate-Limit-Remaining` and
	 * `X-Rate-Limit-Reset` on every response, and the transport records the remaining
	 * budget from a success or a 429 because no SDK resource method hands back a
	 * `Response`. No recorded budget means nothing has been sent recently, which is not
	 * a reason to stop.
	 */
	private static function budget_spent(): bool {
		$budget = RateLimit::snapshot();

		return null !== $budget && $budget['remaining'] < self::RATE_FLOOR;
	}
}
