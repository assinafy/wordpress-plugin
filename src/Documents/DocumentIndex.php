<?php
/**
 * Finding local document records.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * The queries that locate document mirrors, as opposed to reading one.
 *
 * Separate from {@see DocumentRecord} because they are a different kind of work with a
 * different cost: each one is a `get_posts()` over the whole post type with a meta join, and
 * every one of them is deliberately bounded — by a `numberposts` limit, by an exact match on
 * an indexed key, or by a caller-supplied page size. Reading a field off a record that is
 * already in hand costs nothing and needs none of that care.
 *
 * There is no index on signer email: the privacy exporter is required to page anyway, and a
 * serialised signer list cannot be searched reliably in SQL.
 */
final class DocumentIndex {

	/**
	 * `DocumentRecord` is a stateless accessor over post meta, so the default lets callers
	 * construct this with nothing.
	 *
	 * @param DocumentRecord $records Typed access to the records found.
	 */
	public function __construct( private readonly DocumentRecord $records = new DocumentRecord() ) {
	}

	/**
	 * Find the local post mirroring a remote document, or 0.
	 *
	 * @param string $document_id Remote document id, 26-28 opaque hex characters.
	 */
	public function find_by_document_id( string $document_id ): int {
		if ( ! DocumentRecord::is_valid_id( $document_id ) ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'              => DocumentPostType::POST_TYPE,
				'post_status'            => 'any',
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The mirror is addressed by remote id; there is no other way in.
				'meta_key'               => DocumentRecord::META_DOCUMENT_ID,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact match on an indexed meta_key, one row.
				'meta_value'             => $document_id,
			)
		);

		return isset( $posts[0] ) ? (int) $posts[0] : 0;
	}

	/**
	 * Find a reserved send, including one whose mirror has since been trashed.
	 *
	 * @param string $key Opaque send identity.
	 */
	public function find_by_send_key( string $key ): int {
		if ( '' === $key ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'              => DocumentPostType::POST_TYPE,
				'post_status'            => array_values( get_post_stati() ),
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One reserved request is addressed by its send identity.
				'meta_key'               => DocumentRecord::META_SEND_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact match on one indexed meta key, limited to one row.
				'meta_value'             => $key,
			)
		);

		return isset( $posts[0] ) ? (int) $posts[0] : 0;
	}


	/**
	 * The most recent remote document id this site knows about, or an empty string.
	 *
	 * Newest mirror first, by post id. It is not a stand-in for a particular document:
	 * `POST /documents/{id}/assignments/estimate-cost` refuses an already-assigned document,
	 * so a send prices the one it has just uploaded rather than whatever came last.
	 */
	public function latest_document_id(): string {
		$posts = get_posts(
			array(
				'post_type'              => DocumentPostType::POST_TYPE,
				'post_status'            => 'any',
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Restricts the scan to rows that actually mirror a remote document.
				'meta_key'               => DocumentRecord::META_DOCUMENT_ID,
			)
		);

		return isset( $posts[0] ) ? $this->records->document_id( (int) $posts[0] ) : '';
	}


	/**
	 * Open records, least recently synced first — the reconcile cron's work queue.
	 *
	 * @param int $limit Maximum records to return.
	 *
	 * @return array<int, int> Local post ids.
	 */
	public function stale_post_ids( int $limit ): array {
		if ( $limit < 1 ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'              => DocumentPostType::POST_TYPE,
				'post_status'            => 'any',
				'numberposts'            => $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
				'orderby'                => 'meta_value_num',
				'order'                  => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Ordering the queue by staleness requires the timestamp join.
				'meta_key'               => DocumentRecord::META_SYNCED_AT,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded by $limit and run once an hour from cron.
				'meta_query'             => array(
					array(
						'key'     => DocumentRecord::META_DOCUMENT_ID,
						'compare' => 'EXISTS',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => DocumentRecord::META_IS_CLOSED,
							'value'   => '1',
							'compare' => '!=',
						),
						array(
							'key'     => DocumentRecord::META_IS_CLOSED,
							'compare' => 'NOT EXISTS',
						),
					),
				),
			)
		);

		return array_map( 'intval', $posts );
	}


	/**
	 * A page of every record the plugin owns, newest first.
	 *
	 * The privacy exporter and eraser walk the mirror this way: there is no index on signer
	 * email, because an exporter is required to page anyway and a serialised signer list
	 * cannot be searched reliably in SQL.
	 *
	 * @param int $limit  Page size.
	 * @param int $offset Records to skip.
	 *
	 * @return array<int, int> Local post ids.
	 */
	public function post_ids( int $limit, int $offset = 0 ): array {
		if ( $limit < 1 ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'              => DocumentPostType::POST_TYPE,
				'post_status'            => array_values( get_post_stati() ),
				'numberposts'            => $limit,
				'offset'                 => max( 0, $offset ),
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
			)
		);

		return array_map( 'intval', $posts );
	}
}
