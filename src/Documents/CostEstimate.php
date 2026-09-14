<?php
/**
 * The affordability check that runs before a send.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\SDK\Resources\AssignmentResource;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Log;
use WP_Error;

/**
 * Prices a signature request against the account before it can be charged.
 *
 * The plan allowance is consumed by the assignment, not by the upload, so a refusal that
 * lands any time before `POST /documents/{id}/assignments` still costs the account nothing.
 */
final class CostEstimate {

	/**
	 * Returned when the check could not be run at all, as opposed to running and refusing.
	 * A send is never blocked by a pricing probe that failed.
	 */
	public const UNAVAILABLE = 'assinafy_estimate_unavailable';

	/**
	 * @param ClientFactory $clients API client factory.
	 * @param Log           $log     Plugin event log.
	 */
	public function __construct(
		private readonly ClientFactory $clients,
		private readonly Log $log
	) {
	}

	/**
	 * Price a send before its assignment is created, and read the verdict from the body.
	 *
	 * ```http
	 * POST /v1/documents/{documentId}/assignments/estimate-cost
	 * X-Api-Key: {API_KEY}
	 * Content-Type: application/json
	 *
	 * {"method":"virtual","signers":[{"verification_method":"Email","notification_methods":["Email"]}]}
	 * ```
	 * ```json
	 * {
	 *   "status": 200, "message": "",
	 *   "data": {
	 *     "documents": 1, "credits": 0,
	 *     "needs_extra_document": false, "extra_document_cost": 0,
	 *     "total_credits": 0, "breakdown": [],
	 *     "document_balance": 480, "credit_balance": 0,
	 *     "has_sufficient_resources": true, "blocking_reason": null, "message": null
	 *   }
	 * }
	 * ```
	 *
	 * A WhatsApp-notified signer adds
	 * `"credits":0.45,"breakdown":[{"code":"NotificationWhatsapp","name":"WhatsApp Notification",
	 * "cost":0.45,"quantity":1,"unit_cost":0.45}]`.
	 *
	 * **The refusal arrives as HTTP 200.** An account that cannot pay answers 200 with
	 * `has_sufficient_resources: false` and a `blocking_reason` of `PendingPayment`,
	 * `InsufficientDocuments` or `InsufficientCredits`, so branching on the status code
	 * would sail straight past it. A plan-gated verification method is the one refusal that
	 * does use a status code — `DigitalCertificate` answers
	 * 403 `"A assinatura com certificado digital não está disponível para o seu plano atual."`
	 * before any document is consumed.
	 *
	 * Signer ids are not required: the estimate is priced purely from the method mix. The
	 * route is addressed to a document, though, and **that document must not have started
	 * signing yet** — an already-assigned one answers 400 `"A atribuição não pode ser criada
	 * para um documento com status '…'"`. The only document that reliably satisfies that is
	 * the one the send has just uploaded, which is why {@see SendAttempt::run()} prices it
	 * there, after the upload and before the assignment that spends the allowance.
	 *
	 * @param array<int, array<string, mixed>> $signers     Signer rows, raw or normalised.
	 * @param string                           $document_id Uploaded document to address, not yet assigned.
	 *
	 * @return array<string, mixed>|WP_Error The `data` payload, or why it is unavailable.
	 */
	public function for_signers( array $signers, string $document_id ): array|WP_Error {
		$client = $this->clients->client();

		if ( null === $client ) {
			return new WP_Error( self::UNAVAILABLE, __( 'Assinafy is not configured.', 'assinafy' ) );
		}

		$normalized = Signers::normalize( $signers );

		if ( $normalized instanceof WP_Error ) {
			return $normalized;
		}

		try {
			$estimate = $client->assignments()->estimateCost(
				$document_id,
				$normalized,
				AssignmentResource::METHOD_VIRTUAL
			);
		} catch ( ApiException $e ) {
			if ( 403 === $e->getStatusCode() ) {
				return new WP_Error( 'assinafy_plan_restricted', $e->getMessage() );
			}

			$this->log->add(
				'estimate_unavailable',
				array(
					'status' => $e->getStatusCode(),
					'error'  => $e->getMessage(),
				)
			);

			return new WP_Error( self::UNAVAILABLE, $e->getMessage() );
		} catch ( \Throwable $e ) {
			$this->log->add( 'estimate_unavailable', array( 'error' => $e->getMessage() ) );

			return new WP_Error( self::UNAVAILABLE, $e->getMessage() );
		}

		if ( false === ( $estimate['has_sufficient_resources'] ?? true ) ) {
			$reason = is_string( $estimate['blocking_reason'] ?? null ) ? $estimate['blocking_reason'] : '';

			$this->log->add( 'send_blocked', array( 'blocking_reason' => $reason ) );

			return new WP_Error( 'assinafy_insufficient_resources', self::blocking_message( $reason ) );
		}

		return $estimate;
	}


	/**
	 * The refusal a send must honour, or null to proceed.
	 *
	 * Only an estimate that ran and refused stops a send. `assinafy_estimate_unavailable` says
	 * the probe itself could not run — an unconfigured client, a document the route will not
	 * price, a transport failure — and a send is never blocked by that.
	 *
	 * @param array<int, array<string, mixed>> $signers     Normalised signers.
	 * @param string                           $document_id Uploaded document to address.
	 */
	public function refusal( array $signers, string $document_id ): ?WP_Error {
		$gate = $this->for_signers( $signers, $document_id );

		if ( $gate instanceof WP_Error && self::UNAVAILABLE !== $gate->get_error_code() ) {
			return $gate;
		}

		return null;
	}


	/**
	 * Turn a `blocking_reason` into something an administrator can act on.
	 *
	 * @param string $reason One of `PendingPayment`, `InsufficientDocuments`,
	 *                       `InsufficientCredits`, or an unknown future value.
	 */
	private static function blocking_message( string $reason ): string {
		$messages = array(
			'PendingPayment'        => __( 'The Assinafy account has a payment outstanding. Settle it to send documents again.', 'assinafy' ),
			'InsufficientDocuments' => __( 'The Assinafy account has no document allowance left on its plan.', 'assinafy' ),
			'InsufficientCredits'   => __( 'The Assinafy account does not have enough credits for the selected notification method.', 'assinafy' ),
		);

		return $messages[ $reason ] ?? __( 'The Assinafy account cannot cover this signature request.', 'assinafy' );
	}
}
