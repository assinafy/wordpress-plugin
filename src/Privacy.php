<?php
/**
 * Personal-data exporter and eraser.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentIndex;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;

/**
 * The GDPR/LGPD tools WordPress exposes under Tools, wired to every store the plugin owns.
 *
 * ## Redact identity, retain evidence
 *
 * A signature record is not ordinary marketing data. The whole point of an electronic
 * signature is that it can still be shown to be that person's signature years later, so
 * erasing the evidence would destroy the thing the signer and the site owner both relied on.
 *
 * The eraser therefore replaces the identity — signer name, signer email, and the signing URL
 * that carries the address as a query parameter — with a stable `[redacted-<HMAC prefix>]`
 * token, and leaves the evidence alone: the Assinafy document id, the assignment id, the
 * signing order, and every timestamp survive untouched. It reports
 * `items_removed: true, items_retained: true` with a message saying so, which is exactly what
 * those flags are for.
 *
 * The token uses the site's secret salt, so public email-hash dictionaries cannot identify
 * the signer. Identifiers and signature evidence remain personal data; this is redaction,
 * not anonymization. The signer-id registry prevents future syncs restoring the identity.
 *
 * ## Every store, not just the obvious one
 *
 * Three places hold something about a signer, and an eraser that misses one is worse than no
 * eraser at all because it reports success:
 *
 * 1. `assinafy_document` post meta — the signer list on each local record.
 * 2. The `Log` ring buffer — a send or a failure may have carried the address into a context
 *    array.
 * 3. The `assinafy_send_lock_*` transients — five-minute double-send guards keyed by a hash
 *    that a send may have derived from the address.
 */
final class Privacy {

	/**
	 * Records handled per page. WordPress calls back until `done` is true.
	 */
	private const PER_PAGE = 100;

	/**
	 * Export group slug.
	 */
	private const GROUP = 'assinafy-signature-requests';

	/**
	 * @param DocumentRecord $records Typed access to local document records.
	 * @param Log            $log     Plugin event log.
	 * @param DocumentIndex  $index   Pages through every record the plugin owns.
	 */
	public function __construct(
		private readonly DocumentRecord $records,
		private readonly Log $log,
		private readonly DocumentIndex $index = new DocumentIndex()
	) {
	}

	/**
	 * Register both tools and WordPress privacy-policy guidance.
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'policy_content' ) );
	}

	/**
	 * Explain the external signature service and the local eraser's retention limits.
	 */
	public function policy_content(): void {
		wp_add_privacy_policy_content(
			'Assinafy',
			wp_kses_post(
				__( '<p>When we request an electronic signature, we send the document, signer names and email addresses, and signature instructions to Assinafy. Assinafy processes the document and may send signature invitations. We keep local records of document and assignment identifiers, signer details, signing links, signature progress, timestamps, and operational logs.</p><p>WordPress personal-data tools export local signature-request details and redact signer names, email addresses and signing links. Document and signer identifiers, signature evidence, and timestamps are retained. Documents held by Assinafy, their contents, and WooCommerce order data are not erased by this tool; requests concerning these records must be handled separately. Describe your retention period and the applicable Assinafy service terms and privacy policy here.</p>', 'assinafy' )
			)
		);
	}

	/**
	 * Add the exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 *
	 * @return array<string, array<string, mixed>> Exporters with ours added.
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['assinafy'] = array(
			'exporter_friendly_name' => __( 'Assinafy signature requests', 'assinafy' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Add the eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 *
	 * @return array<string, array<string, mixed>> Erasers with ours added.
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['assinafy'] = array(
			'eraser_friendly_name' => __( 'Assinafy signature requests', 'assinafy' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export one page of signature requests.
	 *
	 * @param string $email Address the request was made for.
	 * @param int    $page  1-based page number.
	 *
	 * @return array{data: array<int, array<string, mixed>>, done: bool} Export payload.
	 */
	public function export( string $email, int $page = 1 ): array {
		$page  = max( 1, $page );
		$posts = $this->index->post_ids( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );
		$data  = array();

		foreach ( $posts as $post_id ) {
			$signer = $this->signer_of( $post_id, $email );

			if ( null === $signer ) {
				continue;
			}

			$data[] = array(
				'group_id'    => self::GROUP,
				'group_label' => __( 'Assinafy signature requests', 'assinafy' ),
				'item_id'     => 'assinafy-document-' . $post_id,
				'data'        => array(
					array(
						'name'  => __( 'Document', 'assinafy' ),
						'value' => get_the_title( $post_id ),
					),
					array(
						'name'  => __( 'Assinafy document id', 'assinafy' ),
						'value' => $this->records->document_id( $post_id ),
					),
					array(
						'name'  => __( 'Status', 'assinafy' ),
						'value' => $this->records->status( $post_id ),
					),
					array(
						'name'  => __( 'Signer name', 'assinafy' ),
						'value' => (string) ( $signer['name'] ?? '' ),
					),
					array(
						'name'  => __( 'Signer email', 'assinafy' ),
						'value' => (string) ( $signer['email'] ?? '' ),
					),
					array(
						'name'  => __( 'Signing order', 'assinafy' ),
						'value' => (string) (int) ( $signer['step'] ?? 1 ),
					),
					array(
						'name'  => __( 'Invitation sent', 'assinafy' ),
						'value' => ( $signer['notified'] ?? false ) ? __( 'yes', 'assinafy' ) : __( 'no', 'assinafy' ),
					),
					array(
						'name'  => __( 'Signed', 'assinafy' ),
						'value' => ( $signer['completed'] ?? false ) ? __( 'yes', 'assinafy' ) : __( 'no', 'assinafy' ),
					),
					array(
						'name'  => __( 'Requested', 'assinafy' ),
						'value' => (string) get_post_field( 'post_date_gmt', $post_id ),
					),
					array(
						'name'  => __( 'Last updated', 'assinafy' ),
						'value' => (string) get_post_field( 'post_modified_gmt', $post_id ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $posts ) < self::PER_PAGE,
		);
	}

	/**
	 * Redact one page of signature requests.
	 *
	 * The walk is over every local record rather than a query matching the address, because
	 * the signer list is a serialised array that SQL cannot search reliably. That also makes
	 * the offset honest: redacting a record does not remove it from the set being paged, so
	 * no record is skipped the way a shrinking result set would skip one.
	 *
	 * @param string $email Address the request was made for.
	 * @param int    $page  1-based page number.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool} Eraser result.
	 */
	public function erase( string $email, int $page = 1 ): array {
		$page     = max( 1, $page );
		$token    = $this->token( $email );
		$posts    = $this->index->post_ids( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );
		$redacted = 0;

		foreach ( $posts as $post_id ) {
			if ( $this->redact_record( $post_id, $email, $token ) ) {
				++$redacted;
			}
		}

		$messages = array();
		$removed  = 0 < $redacted;

		if ( 1 === $page ) {
			// The log and the send locks are single, small stores: one pass clears both.
			$removed = $this->redact_log( $email, $token ) || $removed;
			$this->clear_send_locks();
		}

		if ( 0 < $redacted ) {
			$messages[] = __(
				'Assinafy signature records for this address were redacted: the signer name, email address and signing link were replaced with a token. The signature evidence itself — the Assinafy document id, the assignment id, the signing order and every timestamp — is retained, because an electronic signature has to stay verifiable for the life of the document it signed.',
				'assinafy'
			);

			// The key is not `token`: the SDK's `LogRedactor` masks any field by that name as a
			// credential, and this one is the opposite — it is what the address was replaced
			// with, and the entry is worthless without it.
			$this->log->add(
				'privacy_erasure',
				array(
					'records'     => $redacted,
					'redacted_as' => $token,
				)
			);
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => 0 < $redacted,
			'messages'       => $messages,
			'done'           => count( $posts ) < self::PER_PAGE,
		);
	}

	/**
	 * The signer entry on one record matching an address.
	 *
	 * @param int    $post_id Local document post id.
	 * @param string $email   Address to match.
	 *
	 * @return array<string, mixed>|null The entry, or null when the record only matched the
	 *                                   substring search.
	 */
	private function signer_of( int $post_id, string $email ): ?array {
		foreach ( $this->records->signers( $post_id ) as $signer ) {
			if ( is_array( $signer ) && $this->is_match( $signer, $email ) ) {
				return $signer;
			}
		}

		return null;
	}

	/**
	 * Redact one record in place.
	 *
	 * @param int    $post_id Local document post id.
	 * @param string $email   Address to redact.
	 * @param string $token   Replacement token.
	 *
	 * @return bool Whether anything changed.
	 */
	private function redact_record( int $post_id, string $email, string $token ): bool {
		$signers = $this->records->signers( $post_id );
		$changed = false;

		foreach ( $signers as $index => $signer ) {
			if ( ! is_array( $signer ) || ! $this->is_match( $signer, $email ) ) {
				continue;
			}

			foreach ( array( 'name', 'email' ) as $field ) {
				if ( array_key_exists( $field, $signer ) ) {
					$signer[ $field ] = $token;
				}
			}

			// The signing URL carries the address as a query parameter, so it goes too. It is
			// unusable after certification or expiry in any case.
			if ( array_key_exists( 'signing_url', $signer ) ) {
				$signer['signing_url'] = '';
			}

			$signers[ $index ] = $this->redact_strings( $signer, $email, $token );
			$changed           = true;
		}

		if ( $changed ) {
			$this->records->set_signers(
				$post_id,
				$signers,
				array(
					'email' => $email,
					'token' => $token,
				)
			);
		}

		return $changed;
	}

	/**
	 * Redact the plugin's own event log.
	 *
	 * @param string $email Address to redact.
	 * @param string $token Replacement token.
	 */
	private function redact_log( string $email, string $token ): bool {
		$entries = $this->log->entries();
		$cleaned = $this->redact_strings( $entries, $email, $token );

		if ( $cleaned !== $entries ) {
			return update_option( Log::OPTION, $cleaned, false );
		}

		return false;
	}

	/**
	 * Drop every outstanding send lock.
	 *
	 * ponytail: clears all of them, not only this signer's. The lock name contains a hash the
	 * address cannot be recovered from and the plugin cannot recompute, so selecting one is not
	 * possible. They are five-minute double-send guards, so the blast radius is a send racing
	 * an erasure request — and losing that race means one duplicate signature request, not lost
	 * data. Per-address lock indexing would be the upgrade, if it ever mattered.
	 *
	 * An external object cache stores transients outside the options table, where they cannot
	 * be enumerated; there they expire on their own within five minutes instead.
	 */
	private function clear_send_locks(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transients cannot be enumerated through any API, and caching a one-off erasure sweep would be pointless.
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . SendService::LOCK_PREFIX ) . '%'
			)
		);

		foreach ( $names as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}
	}

	/**
	 * Whether a signer entry belongs to an address.
	 *
	 * @param array<string, mixed> $signer Signer entry.
	 * @param string               $email  Address to match.
	 */
	private function is_match( array $signer, string $email ): bool {
		return is_string( $signer['email'] ?? null ) && 0 === strcasecmp( $signer['email'], $email );
	}

	/**
	 * Replace an address wherever it appears inside a structure.
	 *
	 * @param mixed  $value Structure to walk.
	 * @param string $email Address to redact.
	 * @param string $token Replacement token.
	 *
	 * @return mixed The structure with every occurrence replaced.
	 */
	private function redact_strings( mixed $value, string $email, string $token ): mixed {
		if ( is_array( $value ) ) {
			return array_map(
				fn( $item ) => $this->redact_strings( $item, $email, $token ),
				$value
			);
		}

		if ( is_string( $value ) && false !== stripos( $value, $email ) ) {
			return str_ireplace( $email, $token, $value );
		}

		return $value;
	}

	/**
	 * The replacement token for an address.
	 *
	 * @param string $email Address being redacted.
	 */
	private function token( string $email ): string {
		return '[redacted-' . substr( hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'auth' ) ), 0, 12 ) . ']';
	}
}
