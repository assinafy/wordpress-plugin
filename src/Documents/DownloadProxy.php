<?php
/**
 * Capability-gated artifact download.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Assinafy\WP\ClientFactory;

/**
 * Streams a document artifact to the browser through WordPress.
 *
 * Assinafy serves artifacts inline from the API — no redirect, no signed object-storage
 * URL — so fetching one requires the account API key. That makes the artifact URL itself a
 * credential-bearing endpoint, and it is why no artifact URL is ever stored, rendered or
 * handed to a browser: {@see DocumentRecord::artifacts()} keeps only the names, and this
 * proxy performs the authenticated fetch on the server after checking the caller.
 *
 * The artifact name is resolved against the names stored on the record. Probing the API is
 * not an alternative: every unavailable artifact and every invented name return the same
 * `404 {"status":404,"data":null,"message":"Artefato não está disponível."}`, so a probe
 * cannot distinguish "not generated yet" from "no such thing". This proxy answers with the
 * same single 404 for both, deliberately: nothing about the document's state leaks through
 * a download link.
 */
final class DownloadProxy {

	/**
	 * The `admin_post_` action name.
	 */
	public const ACTION = 'assinafy_download';

	/**
	 * Artifact names valid on `GET /documents/{documentId}/download/{artifactName}`.
	 *
	 * `thumbnail` is deliberately absent. It appears as a key in the document's `artifacts`
	 * map but has its own endpoint, and asking for it on the download route is one more
	 * indistinguishable 404.
	 *
	 * @var array<int, string>
	 */
	private const DOWNLOADABLE = array( 'original', 'certificated', 'certificate-page', 'pades', 'bundle' );

	/**
	 * @param ClientFactory  $clients API client factory.
	 * @param DocumentRecord $records Local mirror.
	 */
	public function __construct(
		private readonly ClientFactory $clients,
		private readonly DocumentRecord $records
	) {
	}

	/**
	 * Hook the handler. Logged-out visitors are not served: there is no `nopriv` variant.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * The nonce-protected URL for one artifact of one document.
	 *
	 * @param int    $post_id  Local post id.
	 * @param string $artifact Artifact name as stored on the record.
	 */
	public static function url( int $post_id, string $artifact ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => self::ACTION,
					'post'     => $post_id,
					'artifact' => $artifact,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $post_id
		);
	}

	/**
	 * Fetch the artifact and stream it back.
	 *
	 * ```http
	 * GET /v1/documents/104618b275d321f5de22240ebfda/download/original
	 * X-Api-Key: {API_KEY}
	 * ```
	 * ```http
	 * HTTP/2 200
	 * content-type: application/pdf
	 * content-length: 1146
	 * content-disposition: attachment; filename="test-3page.pdf"
	 * cache-control: must-revalidate, post-check=0, pre-check=0
	 * accept-ranges: bytes
	 *
	 * %PDF-1.4 …
	 * ```
	 *
	 * Anything unavailable answers identically, whatever the reason:
	 *
	 * ```http
	 * HTTP/2 404
	 * content-type: application/json; charset=UTF-8
	 * ```
	 * ```json
	 * {"status":404,"data":null,"message":"Artefato não está disponível."}
	 * ```
	 *
	 * `HEAD` is not routed on that path — it returns the 404 JSON even where `GET` returns
	 * the PDF — so availability is never tested by probing.
	 *
	 * The order here is capability, then nonce, then the artifact lookup: a visitor who may
	 * not view the record is turned away before the request reveals whether the record even
	 * exists.
	 */
	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is verified below; the post id is needed first because the nonce is scoped to it.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		if ( $post_id < 1 || ! current_user_can( Capabilities::VIEW, $post_id ) ) {
			wp_die(
				esc_html__( 'You are not allowed to download this document.', 'assinafy' ),
				esc_html__( 'Forbidden', 'assinafy' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::ACTION . '_' . $post_id );

		$artifact = isset( $_GET['artifact'] ) ? sanitize_key( wp_unslash( $_GET['artifact'] ) ) : '';
		$bytes    = $this->fetch( $post_id, $artifact );

		nocache_headers();
		header( 'Content-Type: ' . ( 'bundle' === $artifact ? 'application/zip' : 'application/pdf' ) );
		header( 'Content-Disposition: attachment; filename="' . self::filename( $post_id, $artifact ) . '"' );
		header( 'Content-Length: ' . strlen( $bytes ) );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw PDF or ZIP bytes; any escaping would corrupt the file.
		echo $bytes;

		exit;
	}

	/**
	 * Resolve the artifact against the record and fetch its bytes.
	 *
	 * Every miss answers with the same 404: a wrong post type, a name the caller invented, a
	 * name the document does not have yet, missing credentials and a refusal from the API are
	 * all indistinguishable from outside.
	 *
	 * @param int    $post_id  Local post id, already checked against the capability and nonce.
	 * @param string $artifact Artifact name as supplied by the caller.
	 *
	 * @return string Raw artifact bytes.
	 */
	private function fetch( int $post_id, string $artifact ): string {
		if ( DocumentPostType::POST_TYPE !== get_post_type( $post_id ) ) {
			self::not_found();
		}

		// The record's own artifact map is the only authority. A name the caller invents,
		// or one the document does not have yet, never becomes a request.
		if ( ! in_array( $artifact, self::DOWNLOADABLE, true )
			|| ! in_array( $artifact, $this->records->artifacts( $post_id ), true ) ) {
			self::not_found();
		}

		$document_id = $this->records->document_id( $post_id );
		$client      = $this->clients->client();

		if ( '' === $document_id || null === $client ) {
			self::not_found();
		}

		try {
			return $client->documents()->download( $document_id, $artifact );
		} catch ( \Throwable ) {
			self::not_found();
		}
	}

	/**
	 * The filename offered to the browser.
	 *
	 * @param int    $post_id  Local post id.
	 * @param string $artifact Artifact name.
	 */
	private static function filename( int $post_id, string $artifact ): string {
		$base = sanitize_file_name( (string) get_the_title( $post_id ) );
		$base = (string) preg_replace( '/\.pdf$/i', '', $base );

		if ( '' === $base ) {
			$base = 'document';
		}

		$extension = 'bundle' === $artifact ? 'zip' : 'pdf';

		return 'original' === $artifact
			? $base . '.' . $extension
			: $base . '-' . $artifact . '.' . $extension;
	}

	/**
	 * The single answer to every miss: wrong name, not generated, no record, no credentials.
	 */
	private static function not_found(): never {
		wp_die(
			esc_html__( 'That document is not available for download.', 'assinafy' ),
			esc_html__( 'Not found', 'assinafy' ),
			array( 'response' => 404 )
		);
	}
}
