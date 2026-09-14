<?php
/**
 * The product Signature tab.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Integrations;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use WC_Product;

/**
 * The three product settings an order-completion send reads.
 *
 * A product carries its configuration on its own Signature tab: whether the feature is on,
 * which media-library PDF to send, and an optional message. {@see WooCommerce} owns the meta
 * keys, because it is what reads them when an order completes; this class owns the screen
 * that writes them.
 */
final class WooCommerceProductTab {

	/**
	 * Newest PDFs offered in the product picker.
	 *
	 * ponytail: a bounded select instead of the media modal, which would need its own script
	 * and enqueue. Swap in `wp_enqueue_media()` if a store keeps more PDFs than this.
	 */
	private const PICKER_LIMIT = 200;

	/**
	 * Hook the tab, its panel and its save handler.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_meta' ) );
		add_filter( 'woocommerce_rest_pre_insert_product_object', array( $this, 'restore_product_meta' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'restore_product_meta' ) );
	}

	/**
	 * Add the Signature tab to the product data panel.
	 *
	 * @param array<string, array<string, mixed>> $tabs Registered tabs.
	 *
	 * @return array<string, array<string, mixed>> Tabs with ours added.
	 */
	public function add_product_tab( array $tabs ): array {
		$tabs['assinafy'] = array(
			'label'    => __( 'Signature', 'assinafy' ),
			'target'   => 'assinafy_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 80,
		);

		return $tabs;
	}

	/**
	 * Render the Signature tab.
	 */
	public function render_product_panel(): void {
		if ( ! current_user_can( Capabilities::SEND ) ) {
			return;
		}

		global $post;

		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		$product    = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$attachment_id = (int) $product->get_meta( WooCommerce::PRODUCT_ATTACHMENT );

		echo '<div id="assinafy_product_data" class="panel woocommerce_options_panel hidden">';

		woocommerce_wp_checkbox(
			array(
				'id'          => WooCommerce::PRODUCT_ENABLED,
				'value'       => 'yes' === $product->get_meta( WooCommerce::PRODUCT_ENABLED ) ? 'yes' : 'no',
				'label'       => __( 'Send for signature', 'assinafy' ),
				'description' => __( 'Request a signature from the customer when an order containing this product is completed.', 'assinafy' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => WooCommerce::PRODUCT_ATTACHMENT,
				'value'       => (string) $attachment_id,
				'label'       => __( 'Document', 'assinafy' ),
				'description' => __( 'The PDF the customer signs. Upload it to the media library first.', 'assinafy' ),
				'desc_tip'    => true,
				'options'     => $this->pdf_options( $attachment_id ),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => WooCommerce::PRODUCT_MESSAGE,
				'value'       => (string) $product->get_meta( WooCommerce::PRODUCT_MESSAGE ),
				'label'       => __( 'Message', 'assinafy' ),
				'description' => __( 'Shown in the invitation. Leave empty to use the default from the Assinafy settings.', 'assinafy' ),
				'desc_tip'    => true,
				'rows'        => 3,
			)
		);

		echo '</div>';
	}

	/**
	 * Persist the Signature tab.
	 *
	 * WC_Admin_Meta_Boxes verifies the nonce and edit permission before reaching this hook.
	 * That permission is `edit_post` on the product, which is not permission to spend the
	 * account's balance, so this is where the send capability is checked — enabling a product
	 * is what makes every completed order send. The product data metabox saves the object
	 * after this callback, including these changes.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public function save_product_meta( WC_Product $product ): void {
		if ( ! current_user_can( Capabilities::SEND ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WC_Admin_Meta_Boxes::save_meta_boxes() verifies the nonce and edit permission before this hook.
		if ( ! isset( $_POST[ WooCommerce::PRODUCT_ATTACHMENT ], $_POST[ WooCommerce::PRODUCT_MESSAGE ] ) ) {
			return;
		}

		$enabled    = isset( $_POST[ WooCommerce::PRODUCT_ENABLED ] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST[ WooCommerce::PRODUCT_ENABLED ] ) ) ? 'yes' : 'no';
		$attachment = is_string( $_POST[ WooCommerce::PRODUCT_ATTACHMENT ] ) ? absint( wp_unslash( $_POST[ WooCommerce::PRODUCT_ATTACHMENT ] ) ) : 0;
		$message    = sanitize_textarea_field( wp_unslash( $_POST[ WooCommerce::PRODUCT_MESSAGE ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! current_user_can( 'read_post', $attachment ) || 'application/pdf' !== get_post_mime_type( $attachment ) ) {
			$attachment = 0;
		}

		$product->update_meta_data( WooCommerce::PRODUCT_ENABLED, $enabled );
		$product->update_meta_data( WooCommerce::PRODUCT_ATTACHMENT, (string) $attachment );
		$product->update_meta_data( WooCommerce::PRODUCT_MESSAGE, $message );
	}

	/**
	 * Put the three keys back when whoever is writing them may not send.
	 *
	 * WooCommerce's own products controller copies every `meta_data` entry onto the product
	 * with `update_meta_data()` and no protected-meta check, and its CSV importer does the
	 * same with `meta:` columns, so `edit_products` alone reaches these keys without ever
	 * passing through `save_product_meta()`. `register_post_meta()` cannot close that: its
	 * `auth_callback` is only consulted by `map_meta_cap()` for `edit_post_meta`, which
	 * WooCommerce never checks — and registering the keys would expose them on
	 * `wp/v2/product`, where nothing can write them today. These two filters are where every
	 * products controller lands, v1 through v4 and the batch endpoint with them, and where
	 * every imported row lands.
	 *
	 * Reverting is silent, like the admin form, rather than a refusal: the same controller
	 * hands these keys back on a read, so a shop manager round-tripping an untouched
	 * `meta_data` array must not have their product update rejected. Products stay in post
	 * meta under HPOS — only orders move — so the stored value is read back with
	 * `get_post_meta()`, which answers `false` for the product being created.
	 *
	 * @param mixed $product Product WooCommerce is about to save.
	 *
	 * @return mixed The product, with any change to the three keys undone.
	 */
	public function restore_product_meta( mixed $product ): mixed {
		if ( ! $product instanceof WC_Product || current_user_can( Capabilities::SEND ) ) {
			return $product;
		}

		foreach ( array( WooCommerce::PRODUCT_ENABLED, WooCommerce::PRODUCT_ATTACHMENT, WooCommerce::PRODUCT_MESSAGE ) as $key ) {
			$stored = get_post_meta( $product->get_id(), $key, true );

			if ( ! is_string( $stored ) || '' === $stored ) {
				$product->delete_meta_data( $key );

				continue;
			}

			$product->update_meta_data( $key, $stored );
		}

		return $product;
	}

	/**
	 * The PDF picker's options.
	 *
	 * Keys are attachment ids, except the leading placeholder whose key is the empty string —
	 * the one key PHP does not fold into an integer.
	 *
	 * @param int $selected_id Currently configured PDF, even when older than the picker limit.
	 *
	 * @return array<int|string, string> Attachment id to title, newest first.
	 */
	private function pdf_options( int $selected_id ): array {
		$options = array( '' => __( '— Select a PDF —', 'assinafy' ) );

		$ids = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_mime_type'         => 'application/pdf',
				'post_status'            => 'inherit',
				'numberposts'            => self::PICKER_LIMIT,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $ids as $id ) {
			// PHP casts a numeric string key back to an integer, so these land as int keys
			// however they are written. The select field renders either the same way.
			$options[ (int) $id ] = get_the_title( (int) $id );
		}

		if ( 0 < $selected_id && 'application/pdf' === get_post_mime_type( $selected_id ) ) {
			$options[ $selected_id ] = get_the_title( $selected_id );
		}

		return $options;
	}
}
