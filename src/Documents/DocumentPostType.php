<?php
/**
 * The document mirror post type.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Assinafy\WP\Settings;

/**
 * Registers `assinafy_document`, the local mirror of a remote Assinafy document, and the
 * columns its list table shows.
 *
 * The post type is `public => false`: a document mirror has no front-end representation and
 * must never be reachable by URL. It exists so the admin can list, open and act on signature
 * requests with the ordinary WordPress furniture, and so post meta can hold the mirrored
 * state (see {@see DocumentRecord}).
 *
 * `create_posts` is denied outright. A record is only meaningful alongside a remote document,
 * and an empty record created from an "Add New" screen would have nothing to mirror; the
 * send screen is the only way in.
 */
final class DocumentPostType {

	public const POST_TYPE = 'assinafy_document';

	/**
	 * The record reader is defaulted rather than required: the post type is also registered
	 * from the activation hook, where no object graph has been built yet.
	 *
	 * @param DocumentRecord $records Typed reader for the mirrored state.
	 */
	public function __construct(
		private readonly DocumentRecord $records = new DocumentRecord()
	) {
	}

	/**
	 * Register the post type, its list table columns and the styles they need.
	 */
	public function register(): void {
		register_post_type( self::POST_TYPE, $this->arguments() );

		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Load the admin styles on the two screens that render badges: this post type's list table
	 * and its editor, where {@see \Assinafy\WP\Admin\DocumentMetaBox} renders the same badge.
	 *
	 * The hook suffix is `edit.php` and `post.php` for every post type, so the screen's post
	 * type is what distinguishes ours.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'edit.php', 'post.php' ), true )
			|| self::POST_TYPE !== get_current_screen()?->post_type ) {
			return;
		}

		wp_enqueue_style(
			'assinafy-admin',
			ASSINAFY_URL . 'assets/css/admin.css',
			array(),
			ASSINAFY_VERSION
		);
	}

	/**
	 * The human label for a document status code.
	 *
	 * The eleven codes are the complete vocabulary of `GET /documents/statuses`; an unknown
	 * code is returned as-is so a status added to the platform shows through rather than
	 * disappearing behind a blank badge.
	 *
	 * @param string $code Status code, e.g. `pending_signature`.
	 */
	public static function status_label( string $code ): string {
		$labels = array(
			'uploading'           => __( 'Uploading', 'assinafy' ),
			'uploaded'            => __( 'Uploaded', 'assinafy' ),
			'metadata_processing' => __( 'Processing', 'assinafy' ),
			'metadata_ready'      => __( 'Ready to send', 'assinafy' ),
			'pending_signature'   => __( 'Awaiting signatures', 'assinafy' ),
			'certificating'       => __( 'All signed — certifying', 'assinafy' ),
			'certificated'        => __( 'Signed and certified', 'assinafy' ),
			'rejected_by_signer'  => __( 'Declined by a signer', 'assinafy' ),
			'rejected_by_user'    => __( 'Cancelled', 'assinafy' ),
			'expired'             => __( 'Expired', 'assinafy' ),
			'failed'              => __( 'Failed', 'assinafy' ),
		);

		return $labels[ $code ] ?? $code;
	}

	/**
	 * The list table columns, replacing the default date column with the send time.
	 *
	 * @param array<string, string> $columns Core's columns.
	 *
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		unset( $columns['date'] );

		$columns['assinafy_status']  = __( 'Status', 'assinafy' );
		$columns['assinafy_signers'] = __( 'Signers', 'assinafy' );
		$columns['assinafy_sent']    = __( 'Sent', 'assinafy' );
		$columns['assinafy_actions'] = __( 'Actions', 'assinafy' );

		return $columns;
	}

	/**
	 * Render one custom column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Local post id.
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'assinafy_status':
				echo wp_kses_post( self::status_badge( $this->records->status( $post_id ) ) );

				$error = $this->records->last_error( $post_id );

				if ( '' !== $error ) {
					printf( '<br /><span class="assinafy-sync-error">%s</span>', esc_html( $error ) );
				}
				break;

			case 'assinafy_signers':
				echo esc_html( self::signer_summary( $this->records->signers( $post_id ) ) );
				break;

			case 'assinafy_sent':
				$sent_at = get_the_time( (string) get_option( 'date_format' ), $post_id );

				echo esc_html( false === $sent_at ? '—' : (string) $sent_at );
				break;

			case 'assinafy_actions':
				echo wp_kses_post( self::download_links( $post_id, $this->records->artifacts( $post_id ) ) );
				break;
		}
	}

	/**
	 * Markup for one status badge.
	 *
	 * @param string $status Status code, possibly empty on a record that never synced.
	 */
	private static function status_badge( string $status ): string {
		if ( '' === $status ) {
			return '<span class="assinafy-status assinafy-status--unknown">' . esc_html__( 'Not synced', 'assinafy' ) . '</span>';
		}

		return sprintf(
			'<span class="assinafy-status assinafy-status--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( self::status_label( $status ) )
		);
	}

	/**
	 * A one-line progress summary, e.g. `1 of 2 signed`.
	 *
	 * @param array<int, array{completed: bool}> $signers Stored signer list.
	 */
	private static function signer_summary( array $signers ): string {
		$total = count( $signers );

		if ( 0 === $total ) {
			return __( 'No assignment yet', 'assinafy' );
		}

		$completed = 0;

		foreach ( $signers as $signer ) {
			if ( ! empty( $signer['completed'] ) ) {
				++$completed;
			}
		}

		return sprintf(
			/* translators: 1: number of signers who have signed, 2: total number of signers. */
			__( '%1$d of %2$d signed', 'assinafy' ),
			$completed,
			$total
		);
	}

	/**
	 * Download links for the artifacts the remote document actually has.
	 *
	 * `thumbnail` is skipped: it is a key in the `artifacts` map but is not a valid name on
	 * the download route, which has its own endpoint for it.
	 *
	 * @param int                $post_id   Local post id.
	 * @param array<int, string> $artifacts Stored artifact names.
	 */
	private static function download_links( int $post_id, array $artifacts ): string {
		$labels = array(
			'original'         => __( 'Original', 'assinafy' ),
			'certificated'     => __( 'Signed', 'assinafy' ),
			'certificate-page' => __( 'Certificate', 'assinafy' ),
			'pades'            => __( 'PAdES', 'assinafy' ),
			'bundle'           => __( 'Bundle', 'assinafy' ),
		);

		$links = array();

		foreach ( $artifacts as $artifact ) {
			if ( ! isset( $labels[ $artifact ] ) ) {
				continue;
			}

			$links[] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( DownloadProxy::url( $post_id, $artifact ) ),
				esc_html( $labels[ $artifact ] )
			);
		}

		return array() === $links ? '&mdash;' : implode( ' | ', $links );
	}

	/**
	 * Post type registration arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function arguments(): array {
		return array(
			'labels'              => array(
				'name'               => __( 'Documents', 'assinafy' ),
				'singular_name'      => __( 'Document', 'assinafy' ),
				'menu_name'          => __( 'Documents', 'assinafy' ),
				'all_items'          => __( 'Documents', 'assinafy' ),
				'edit_item'          => __( 'Document', 'assinafy' ),
				'view_item'          => __( 'View document', 'assinafy' ),
				'search_items'       => __( 'Search documents', 'assinafy' ),
				'not_found'          => __( 'No documents sent yet.', 'assinafy' ),
				'not_found_in_trash' => __( 'No documents in the trash.', 'assinafy' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => Settings::PAGE,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'hierarchical'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
			'capability_type'     => array( 'assinafy_document', 'assinafy_documents' ),
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'create_posts' => 'do_not_allow',
				'read'         => Capabilities::VIEW,
			),
		);
	}
}
