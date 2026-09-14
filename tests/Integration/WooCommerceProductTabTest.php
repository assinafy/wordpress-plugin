<?php
/**
 * WooCommerce's native product editor integration.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Assinafy\WP\Integrations\WooCommerce;
use Assinafy\WP\Integrations\WooCommerceProductTab;

/**
 * @covers \Assinafy\WP\Integrations\WooCommerceProductTab
 */
final class WooCommerceProductTabTest extends AssinafyTestCase {

	/**
	 * Load the product editor's native field renderers, normally loaded only in wp-admin.
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for product editor tests.' );
		}
		require_once WC_ABSPATH . 'includes/admin/wc-meta-box-functions.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Native product saves retain the sanitized tab values through WooCommerce CRUD.
	 */
	public function test_product_object_save_persists_valid_configuration(): void {
		$product    = new \WC_Product_Simple();
		$attachment = $this->attachment();
		$_POST      = array(
			WooCommerce::PRODUCT_ENABLED    => 'yes',
			WooCommerce::PRODUCT_ATTACHMENT => (string) $attachment,
			WooCommerce::PRODUCT_MESSAGE    => wp_slash( "Please sign Jane's <b>agreement</b>.\nThank you." ),
		);
		do_action( 'woocommerce_admin_process_product_object', $product );
		$this->assertSame( 0, $product->get_id(), 'The callback must let WooCommerce save once.' );
		$product->save();
		$reloaded = wc_get_product( $product->get_id() );
		$this->assertSame( 'yes', $reloaded->get_meta( WooCommerce::PRODUCT_ENABLED ) );
		$this->assertSame( (string) $attachment, $reloaded->get_meta( WooCommerce::PRODUCT_ATTACHMENT ) );
		$this->assertSame( "Please sign Jane's agreement.\nThank you.", $reloaded->get_meta( WooCommerce::PRODUCT_MESSAGE ) );
	}

	/**
	 * Other product save paths must not wipe a tab that was absent from their request.
	 */
	public function test_an_absent_panel_preserves_existing_settings(): void {
		$product = new \WC_Product_Simple();
		$product->update_meta_data( WooCommerce::PRODUCT_ENABLED, 'yes' );
		$product->update_meta_data( WooCommerce::PRODUCT_ATTACHMENT, '123' );
		$product->update_meta_data( WooCommerce::PRODUCT_MESSAGE, 'Keep this.' );
		$_POST = array();
		do_action( 'woocommerce_admin_process_product_object', $product );
		$this->assertSame( 'yes', $product->get_meta( WooCommerce::PRODUCT_ENABLED ) );
		$this->assertSame( '123', $product->get_meta( WooCommerce::PRODUCT_ATTACHMENT ) );
		$this->assertSame( 'Keep this.', $product->get_meta( WooCommerce::PRODUCT_MESSAGE ) );
	}

	/**
	 * Clearing the checkbox works, and crafted array/non-PDF values cannot select a document.
	 *
	 * @dataProvider invalid_attachments
	 * @param mixed $value Submitted attachment field.
	 */
	public function test_invalid_attachment_and_unchecked_box_are_rejected( mixed $value ): void {
		$product = new \WC_Product_Simple();
		$product->update_meta_data( WooCommerce::PRODUCT_ENABLED, 'yes' );
		$_POST = array(
			WooCommerce::PRODUCT_ATTACHMENT => $value,
			WooCommerce::PRODUCT_MESSAGE    => array( 'unexpected' ),
		);
		do_action( 'woocommerce_admin_process_product_object', $product );
		$this->assertSame( 'no', $product->get_meta( WooCommerce::PRODUCT_ENABLED ) );
		$this->assertSame( '0', $product->get_meta( WooCommerce::PRODUCT_ATTACHMENT ) );
		$this->assertSame( '', $product->get_meta( WooCommerce::PRODUCT_MESSAGE ) );
	}

	/**
	 * Invalid request shapes must not become valid attachment ids.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function invalid_attachments(): array {
		return array(
			'unknown id' => array( '99999999' ),
			'array'      => array( array( '1' ) ),
			'empty'      => array( '' ),
		);
	}

	/**
	 * A saved PDF must remain selected when the recent-files query no longer includes it.
	 */
	public function test_picker_preserves_the_selected_pdf_outside_the_recent_results(): void {
		$attachment = $this->attachment();
		$product    = new \WC_Product_Simple();
		$product->update_meta_data( WooCommerce::PRODUCT_ATTACHMENT, (string) $attachment );
		$product->save();
		$GLOBALS['post'] = get_post( $product->get_id() );
		$exclude         = static function ( \WP_Query $query ) use ( $attachment ): void {
			if ( 'attachment' === $query->get( 'post_type' ) ) {
				$query->set( 'post__not_in', array( $attachment ) );
			}
		};
		add_action( 'pre_get_posts', $exclude );
		try {
			ob_start();
			( new WooCommerceProductTab() )->render_product_panel();
			$html = (string) ob_get_clean();
		} finally {
			remove_action( 'pre_get_posts', $exclude );
		}
		$this->assertMatchesRegularExpression( '/<option value="' . $attachment . '"[^>]*selected=/', $html );
	}

	/**
	 * Editing products is not permission to send: shop managers hold no Assinafy capability.
	 */
	public function test_product_editing_alone_cannot_configure_a_send(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$product = new \WC_Product_Simple();
		$_POST   = array(
			WooCommerce::PRODUCT_ENABLED    => 'yes',
			WooCommerce::PRODUCT_ATTACHMENT => (string) $this->attachment(),
			WooCommerce::PRODUCT_MESSAGE    => 'Please sign.',
		);
		do_action( 'woocommerce_admin_process_product_object', $product );
		$this->assertSame( '', $product->get_meta( WooCommerce::PRODUCT_ENABLED ) );
		$this->assertSame( '', $product->get_meta( WooCommerce::PRODUCT_ATTACHMENT ) );
		ob_start();
		( new WooCommerceProductTab() )->render_product_panel();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * WooCommerce's own products controller copies every `meta_data` entry onto the product
	 * with `update_meta_data()` and no protected-meta check, so `edit_products` alone reaches
	 * the three keys without ever passing through `save_product_meta()`. Editing a product is
	 * not permission to spend the account's balance; the rest of the request still goes through.
	 */
	public function test_rest_writes_to_the_three_keys_need_the_send_capability(): void {
		$attachment = $this->attachment();
		$product    = new \WC_Product_Simple();
		$product->save();
		$product_id = $product->get_id();

		// A shop manager edits every product in the store and holds no Assinafy capability.
		$shop_manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		$this->assertFalse( user_can( $shop_manager, Capabilities::SEND ) );

		wp_set_current_user( $shop_manager );
		$this->rest_update_product( $product_id, 'Renamed by the shop manager', $attachment, 'Please sign.' );

		foreach ( array( WooCommerce::PRODUCT_ENABLED, WooCommerce::PRODUCT_ATTACHMENT, WooCommerce::PRODUCT_MESSAGE ) as $key ) {
			$this->assertSame( '', wc_get_product( $product_id )->get_meta( $key ), $key . ' was set over REST without the send capability.' );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->rest_update_product( $product_id, 'Configured by an administrator', $attachment, 'Please sign.' );

		$configured = wc_get_product( $product_id );
		$this->assertSame( 'yes', $configured->get_meta( WooCommerce::PRODUCT_ENABLED ) );
		$this->assertSame( (string) $attachment, $configured->get_meta( WooCommerce::PRODUCT_ATTACHMENT ) );
		$this->assertSame( 'Please sign.', $configured->get_meta( WooCommerce::PRODUCT_MESSAGE ) );

		// Nor may a shop manager repoint a product somebody with the capability configured.
		wp_set_current_user( $shop_manager );
		$this->rest_update_product( $product_id, 'Renamed again', $this->attachment(), 'Sign this one instead.' );

		$reloaded = wc_get_product( $product_id );
		$this->assertSame( (string) $attachment, $reloaded->get_meta( WooCommerce::PRODUCT_ATTACHMENT ) );
		$this->assertSame( 'Please sign.', $reloaded->get_meta( WooCommerce::PRODUCT_MESSAGE ) );

		// A `meta:` column in a product CSV is the same write from the same capability.
		$reloaded->update_meta_data( WooCommerce::PRODUCT_MESSAGE, 'Imported from a spreadsheet.' );
		$imported = apply_filters( 'woocommerce_product_import_pre_insert_product_object', $reloaded, array() );
		$this->assertSame( 'Please sign.', $imported->get_meta( WooCommerce::PRODUCT_MESSAGE ) );
	}

	/**
	 * Push the three keys at one product through WooCommerce's own products controller.
	 *
	 * The name rides along to prove the request itself was accepted, so an unchanged tab
	 * cannot be mistaken for a request the permission check refused outright.
	 *
	 * @param int    $product_id Product to update.
	 * @param string $name       New product name.
	 * @param int    $attachment PDF the request asks for.
	 * @param string $message    Message the request asks for.
	 */
	private function rest_update_product( int $product_id, string $name, int $attachment, string $message ): void {
		$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/' . $product_id );
		$request->set_body_params(
			array(
				'name'      => $name,
				'meta_data' => array(
					array(
						'key'   => WooCommerce::PRODUCT_ENABLED,
						'value' => 'yes',
					),
					array(
						'key'   => WooCommerce::PRODUCT_ATTACHMENT,
						'value' => (string) $attachment,
					),
					array(
						'key'   => WooCommerce::PRODUCT_MESSAGE,
						'value' => $message,
					),
				),
			)
		);

		$this->assertSame( 200, rest_do_request( $request )->get_status(), 'The product update itself must be accepted.' );
		$this->assertSame( $name, wc_get_product( $product_id )->get_name() );
	}

	/**
	 * Create one PDF media item.
	 */
	private function attachment(): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'agreement.pdf',
			)
		);
	}
}
