<?php
/**
 * The WooCommerce order-completion trigger.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\DocumentIndex;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Integrations\WooCommerce;
use Assinafy\WP\Plugin;
use ReflectionMethod;
use WC_Order;

/**
 * `woocommerce_order_status_completed` is not a once-per-order event. It fires again when a
 * shop manager re-completes a refunded order, when a bulk "mark completed" sweeps the order
 * list, and when a delayed gateway callback lands on an order a human already completed. Each
 * of those is a second signature request the customer neither expects nor wants, and each is
 * billed. The two layers that stop it are what most of this file is about.
 *
 * The completion round trip runs against both legacy order posts and HPOS with sync off.
 * WooCommerce is optional at runtime, but installed in wp-env and the integration CI job.
 *
 * @covers \Assinafy\WP\Integrations\WooCommerce
 */
final class WooCommerceTest extends AssinafyTestCase {

	/**
	 * Both order stores and checkout presentations use the native order-status integration.
	 */
	public function test_woocommerce_feature_compatibility_is_declared(): void {
		// Asserted through the hook rather than through FeaturesUtil on purpose.
		// `FeaturesController::get_compatible_plugins_for_feature()` only ever reports plugins
		// returned by `get_woocommerce_aware_plugins()`, which reads WordPress's installed
		// plugin list; the suite `require`s this plugin instead of installing and activating
		// it, so it is absent from that list and no declaration can ever appear there. The
		// attachment below is the part this plugin owns, and deleting the `add_action()` in
		// integrations/bootstrap.php — the way this would actually regress — fails this test.
		$this->assertNotFalse(
			has_action( 'before_woocommerce_init', array( WooCommerce::class, 'declare_compatibility' ) ),
			'Feature compatibility is not attached to before_woocommerce_init.'
		);
	}

	/**
	 * Document id the fake upload hands back.
	 */
	private const DOCUMENT_ID = '104618d0d63884bc446c534e5ff5';

	/**
	 * Assignment id the fake assignment create hands back.
	 */
	private const ASSIGNMENT_ID = '1a09c15990f0144256b98ff38aa';

	/**
	 * Signer id the fake lookup hands back.
	 */
	private const SIGNER_ID = '19e6b92e7895332ed9708535d8c';

	/**
	 * The customer's address.
	 */
	private const BILLING_EMAIL = 'jane@example.com';

	/**
	 * Give the plugin credentials.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->configure_plugin();
	}

	/**
	 * Nothing is attached on a site without WooCommerce. The integration is registered behind
	 * a `class_exists()` guard, and every callback in it would fatal on a `WC_Order` type hint
	 * that cannot be resolved.
	 */
	public function test_the_integration_is_attached_only_when_woocommerce_is_present(): void {
		$present = class_exists( 'WooCommerce' );

		$this->assertSame( $present, false !== has_action( 'woocommerce_order_status_completed' ) );
		$this->assertSame( $present, false !== has_filter( 'woocommerce_product_data_tabs' ) );
		$this->assertSame( $present, false !== has_action( 'woocommerce_email_after_order_table' ) );
	}

	/**
	 * Repeated core boot calls must not register a second order-completion adapter.
	 */
	public function test_core_boot_registers_the_bundled_adapter_once(): void {
		$this->require_woocommerce();
		Plugin::boot();
		Plugin::boot();

		$callbacks = $GLOBALS['wp_filter']['woocommerce_order_status_completed']->callbacks[5] ?? array();
		$adapters  = array_filter(
			$callbacks,
			static fn( array $callback ): bool => is_array( $callback['function'] ) && $callback['function'][0] instanceof WooCommerce
		);
		$this->assertCount( 1, $adapters );
		$this->assertSame( 2, array_values( $adapters )[0]['accepted_args'] );
	}

	/**
	 * Every order read and write goes through the order object. `get_post_meta()` on an order
	 * id is the HPOS trap, and it is silent.
	 */
	public function test_order_state_never_goes_through_post_meta(): void {
		foreach ( array( 'send_for_order', 'render_email_links', 'send_one', 'note_failure', 'attachments_for' ) as $method ) {
			$body = $this->method_body( $method );

			$this->assertStringNotContainsString(
				'get_post_meta(',
				$body,
				$method . '() reads order meta with get_post_meta(), which returns nothing under HPOS.'
			);
			$this->assertStringNotContainsString(
				'update_post_meta(',
				$body,
				$method . '() writes order meta with update_post_meta(), which is lost under HPOS.'
			);
		}
	}

	/**
	 * The order is loaded with `wc_get_order()` and the sent map is written back through the
	 * order object's own meta API, which is the only storage-agnostic way to do it.
	 */
	public function test_the_sent_map_is_read_and_written_through_the_order_object(): void {
		$body = $this->method_body( 'send_for_order' );

		$this->assertStringContainsString( 'wc_get_order( $order_id )', $body );
		$this->assertStringContainsString( '$order->get_meta( self::ORDER_SENT )', $body );
		$this->assertStringContainsString( '$order->update_meta_data( self::ORDER_SENT, $sent )', $body );
		$this->assertStringContainsString( '$order->save()', $body );
	}

	/**
	 * A completed order sends the product's document to the billing address and records the
	 * local document against the attachment it came from.
	 *
	 * @dataProvider order_storage
	 * @param string $hpos Whether the dedicated WooCommerce order tables are enabled.
	 */
	public function test_a_completed_order_sends_the_products_document( string $hpos ): void {
		$this->require_woocommerce();
		update_option( 'woocommerce_custom_orders_table_enabled', $hpos );
		update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
		$this->assertSame( 'yes' === $hpos, \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
		$this->fake_successful_send();

		$attachment_id = $this->create_pdf_attachment();
		$order         = $this->create_order( $attachment_id );

		$this->complete( $order );

		$post_id = $this->sent_map( $order->get_id() )[ $attachment_id ] ?? 0;

		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( DocumentPostType::POST_TYPE, get_post_type( $post_id ) );
		$this->assertSame(
			array(
				'integration' => 'woocommerce',
				'record_id'   => (string) $order->get_id(),
			),
			( new DocumentRecord() )->source( $post_id )
		);
		// Adding adapter identity must preserve keys written by previous plugin versions.
		$legacy_key = SendService::LOCK_PREFIX . md5(
			(string) wp_json_encode(
				array(
					'account'     => self::ACCOUNT_ID,
					'environment' => self::API_HOST . 'v1',
					'request'     => 'wc-order-' . $order->get_id() . '-attachment-' . $attachment_id,
				)
			)
		);
		$this->assertSame( $post_id, ( new DocumentIndex() )->find_by_send_key( $legacy_key ) );
		$this->assertStringContainsString(
			'sent to ' . self::BILLING_EMAIL,
			$this->latest_note( $order->get_id() )
		);
		$this->assertSame( 0, $order->get_customer_id(), 'Guest orders must be supported.' );

		if ( 'yes' === $hpos ) {
			$this->assertSame( '', get_post_meta( $order->get_id(), WooCommerce::ORDER_SENT, true ) );
		}

		$after_first = count( $this->requests );
		$this->complete( $this->reload( $order->get_id() ) );
		$this->assertCount( $after_first, $this->requests );
	}

	/**
	 * Exercise both WooCommerce storage engines.
	 *
	 * @return array<string, array{string}>
	 */
	public static function order_storage(): array {
		return array(
			'legacy'                       => array( 'no' ),
			'HPOS without synchronization' => array( 'yes' ),
		);
	}

	/**
	 * The real transition must finish sending before WooCommerce builds the first email,
	 * including when another listener already loaded the order's metadata.
	 */
	public function test_real_completion_email_contains_signing_link(): void {
		$this->require_woocommerce();
		$this->fake_successful_send();
		$order = $this->create_order( $this->create_pdf_attachment() );
		$order->get_meta( WooCommerce::ORDER_SENT );
		// WordPress restores hooks between tests, while WooCommerce retains its singleton.
		// Reconstruct the email controller so its template and notification hooks are present.
		$mailer  = ( new \WC_Emails() )->get_emails()['WC_Email_Customer_Completed_Order'];
		$message = '';
		$save    = static function ( mixed $preempt, array $mail ) use ( &$message ): bool {
			$message .= $mail['message'];
			return true;
		};
		add_filter( 'pre_wp_mail', $save, 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', '__return_true' );

		// Make this deterministic even when the store has opted in to deferred emails.
		remove_action( 'woocommerce_order_status_completed', array( 'WC_Emails', 'queue_transactional_email' ), 10 );
		add_action( 'woocommerce_order_status_completed', array( 'WC_Emails', 'send_transactional_email' ), 10, 10 );
		try {
			$order->update_status( 'completed' );
		} finally {
			remove_filter( 'pre_wp_mail', $save, 10 );
		}

		$this->assertStringContainsString( 'Documents to sign', $message );
		$this->assertStringContainsString( '/sign/' . self::DOCUMENT_ID, $message );
		$this->assertSame( $order, $mailer->object );
	}

	/**
	 * A processing checkout order only sends after it becomes completed.
	 */
	public function test_processing_does_not_send_and_completion_does(): void {
		$this->require_woocommerce();
		$this->fake_successful_send();
		$order = $this->create_order( $this->create_pdf_attachment() );
		$order->update_status( 'processing' );
		$this->assertSame( array(), $this->requests );
		$order->update_status( 'completed' );
		$this->assertNotEmpty( $this->sent_map( $order->get_id() ) );
	}

	/**
	 * Variations inherit the parent tab and repeated products with one PDF send only once.
	 */
	public function test_variations_inherit_the_parent_and_duplicate_pdfs_send_once(): void {
		$this->require_woocommerce();
		$this->fake_successful_send();
		$attachment_id = $this->create_pdf_attachment();
		$order         = $this->create_order( $attachment_id );
		$parent        = new \WC_Product_Variable();
		$parent->set_name( 'Variable agreement' );
		$parent->update_meta_data( WooCommerce::PRODUCT_ENABLED, 'yes' );
		$parent->update_meta_data( WooCommerce::PRODUCT_ATTACHMENT, $attachment_id );
		$parent->save();
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->save();
		$order->add_product( $variation, 2 );
		$order->save();

		$this->complete( $order );
		$this->assertCount( 1, $this->sent_map( $order->get_id() ) );
		$uploads = array_filter( $this->requests, static fn( array $request ): bool => str_ends_with( $request['url'], '/documents' ) );
		$this->assertCount( 1, $uploads );

		// With the simple product removed, the variation must still supply the document.
		$order->delete_meta_data( WooCommerce::ORDER_SENT );
		$items = $order->get_items();
		$order->remove_item( array_key_first( $items ) );
		$order->save();
		$this->complete( $order );
		$this->assertCount( 1, $this->sent_map( $order->get_id() ) );
	}

	/**
	 * HTML and text emails retain every document even if filenames match, and admin copies
	 * never disclose customer signing links.
	 */
	public function test_email_formats_keep_same_named_documents_and_exclude_admins(): void {
		$this->require_woocommerce();
		$order   = $this->create_order( $this->create_pdf_attachment() );
		$records = new DocumentRecord();
		$sent    = array();
		foreach ( array( 'one', 'two' ) as $suffix ) {
			$post_id = $this->create_document( $suffix );
			$records->hydrate_from_api(
				$post_id,
				array(
					'id'         => $suffix,
					'assignment' => array(
						'signers'      => array(
							array(
								'id'    => self::SIGNER_ID,
								'email' => self::BILLING_EMAIL,
							),
						),
						'signing_urls' => array(
							array(
								'signer_id' => self::SIGNER_ID,
								'url'       => 'https://app.assinafy.com.br/sign/' . $suffix . '?a=1&b=2',
							),
						),
					),
				)
			);
			$sent[] = $post_id;
		}
		$order->update_meta_data( WooCommerce::ORDER_SENT, $sent );
		$order->save();

		ob_start();
		do_action( 'woocommerce_email_after_order_table', $order, false, false );
		$html = (string) ob_get_clean();
		$this->assertSame( 2, substr_count( $html, '<li>' ) );
		ob_start();
		do_action( 'woocommerce_email_after_order_table', $order, false, true );
		$plain = (string) ob_get_clean();
		$this->assertStringNotContainsString( '<', $plain );
		$this->assertStringContainsString( '/one?a=1&b=2', $plain );
		$this->assertStringContainsString( '/two?a=1&b=2', $plain );
		ob_start();
		do_action( 'woocommerce_email_after_order_table', $order, true, false );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Layer one: the order-meta map. It survives forever, which is what makes a re-completion
	 * three weeks later a no-op.
	 */
	public function test_completing_the_order_again_sends_nothing(): void {
		$this->require_woocommerce();
		$this->fake_successful_send();

		$attachment_id = $this->create_pdf_attachment();
		$order         = $this->create_order( $attachment_id );

		$this->complete( $order );

		$after_first = count( $this->requests );
		$post_id     = $this->sent_map( $order->get_id() )[ $attachment_id ];

		// A shop manager re-completing a refunded order, and a bulk "mark completed" sweep.
		$this->complete( $order );
		$this->complete( $order );

		$this->assertCount( $after_first, $this->requests, 'A repeated completion must not reach the API.' );
		$this->assertSame( array( $attachment_id => $post_id ), $this->sent_map( $order->get_id() ) );
		$this->assertCount(
			1,
			get_posts(
				array(
					'post_type' => DocumentPostType::POST_TYPE,
					'fields'    => 'ids',
				)
			)
		);
	}

	/**
	 * Layer two: the deterministic idempotency key. A gateway callback racing the first
	 * completion arrives before the map has been saved, so the map cannot catch it — the send
	 * lock behind the key does, and hands back the record the first one created.
	 */
	public function test_a_gateway_callback_racing_the_first_completion_sends_nothing(): void {
		$this->require_woocommerce();
		$this->fake_successful_send();

		$attachment_id = $this->create_pdf_attachment();
		$order         = $this->create_order( $attachment_id );

		$this->complete( $order );

		$after_first = count( $this->requests );
		$post_id     = $this->sent_map( $order->get_id() )[ $attachment_id ];

		// What the racing request sees: the first one has not written its map yet.
		$order = $this->reload( $order->get_id() );
		$order->update_meta_data( WooCommerce::ORDER_SENT, array() );
		$order->save();

		$this->complete( $order );

		$this->assertCount( $after_first, $this->requests, 'The send lock must catch what the map could not.' );
		$this->assertSame( array( $attachment_id => $post_id ), $this->sent_map( $order->get_id() ) );
	}

	/**
	 * A refusal is written where whoever is investigating looks first, and nothing is recorded
	 * as sent — so re-completing the order tries again.
	 */
	public function test_a_failed_send_is_noted_on_the_order(): void {
		$this->require_woocommerce();
		$this->fake_successful_send();

		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/documents',
			422,
			array(
				'status'  => 422,
				'message' => 'O arquivo enviado não é um PDF válido.',
				'data'    => null,
			)
		);

		$attachment_id = $this->create_pdf_attachment();
		$order         = $this->create_order( $attachment_id );

		$this->complete( $order );

		$note = $this->latest_note( $order->get_id() );

		$this->assertStringContainsString( 'failed', $note );
		$this->assertStringContainsString( 'O arquivo enviado não é um PDF válido.', $note );
		$this->assertSame( array(), $this->sent_map( $order->get_id() ) );
	}

	/**
	 * An order with no usable billing address cannot be sent to. The note says so rather than
	 * the order looking as though nothing was ever configured.
	 */
	public function test_an_order_without_a_billing_address_is_noted_and_not_sent(): void {
		$this->require_woocommerce();

		$attachment_id = $this->create_pdf_attachment();
		$order         = $this->create_order( $attachment_id, '' );

		$this->complete( $order );

		$this->assertStringContainsString(
			'no usable billing email address',
			$this->latest_note( $order->get_id() )
		);
		$this->assertSame( array(), $this->requests );
		$this->assertSame( array(), $this->sent_map( $order->get_id() ) );
	}

	/**
	 * A product without the feature turned on is not a signature request, however many of
	 * them the order contains.
	 */
	public function test_an_order_of_products_that_are_not_configured_sends_nothing(): void {
		$this->require_woocommerce();

		$attachment_id = $this->create_pdf_attachment();
		$order         = $this->create_order( $attachment_id, self::BILLING_EMAIL, false );

		$this->complete( $order );

		$this->assertSame( array(), $this->requests );
		$this->assertSame( array(), $this->sent_map( $order->get_id() ) );
	}

	/**
	 * Skip, loudly, when the optional integration is not installed.
	 */
	private function require_woocommerce(): void {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped(
				'WooCommerce is not installed in this environment, so the order-completion trigger cannot run. '
				. 'Add "woocommerce" to the plugins list in .wp-env.json and re-run wp-env start to exercise it.'
			);
		}
	}

	/**
	 * Fire the completion the way WooCommerce fires it.
	 *
	 * The action is used rather than `$order->update_status()` because the status transition
	 * is exactly what is not guaranteed to happen once per order: a bulk re-apply and a late
	 * gateway callback both fire this on an order that is already complete.
	 *
	 * @param WC_Order $order Order reaching `completed`.
	 */
	private function complete( WC_Order $order ): void {
		do_action( 'woocommerce_order_status_completed', $order->get_id() );
	}

	/**
	 * The order's sent map, read back from storage rather than from the object in hand.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array<int, int> Attachment id to local document post id.
	 */
	private function sent_map( int $order_id ): array {
		$sent = $this->reload( $order_id )->get_meta( WooCommerce::ORDER_SENT );

		return is_array( $sent ) ? $sent : array();
	}

	/**
	 * The most recent Assinafy note on an order, ignoring WooCommerce's email/status notes.
	 *
	 * @param int $order_id Order id.
	 */
	private function latest_note( int $order_id ): string {
		$notes = wc_get_order_notes( array( 'order_id' => $order_id ) );

		$this->assertNotEmpty( $notes, 'The trigger must say what it did, either way.' );

		foreach ( $notes as $note ) {
			if ( str_starts_with( (string) $note->content, 'Assinafy:' ) ) {
				return (string) $note->content;
			}
		}

		$this->fail( 'No Assinafy order note was written.' );
	}

	/**
	 * Read an order from storage.
	 *
	 * @param int $order_id Order id.
	 */
	private function reload( int $order_id ): WC_Order {
		$order = wc_get_order( $order_id );

		$this->assertInstanceOf( WC_Order::class, $order );

		return $order;
	}

	/**
	 * One order for one configured product.
	 *
	 * @param int    $attachment_id PDF the product sends.
	 * @param string $email         Billing address.
	 * @param bool   $enabled       Whether the product asks for a signature.
	 */
	private function create_order( int $attachment_id, string $email = self::BILLING_EMAIL, bool $enabled = true ): WC_Order {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Service agreement' );
		$product->update_meta_data( WooCommerce::PRODUCT_ENABLED, $enabled ? 'yes' : 'no' );
		$product->update_meta_data( WooCommerce::PRODUCT_ATTACHMENT, (string) $attachment_id );
		$product->update_meta_data( WooCommerce::PRODUCT_MESSAGE, 'Please sign the service agreement.' );
		$product->save();

		$order = wc_create_order();

		$this->assertInstanceOf( WC_Order::class, $order );

		$order->add_product( wc_get_product( $product->get_id() ), 1 );
		$order->set_billing_first_name( 'Jane' );
		$order->set_billing_last_name( 'Doe' );
		$order->set_billing_email( $email );
		$order->save();

		return $order;
	}

	/**
	 * An attachment pointing at the checked-in PDF.
	 */
	private function create_pdf_attachment(): int {
		$attachment_id = (int) self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'service-agreement.pdf',
			)
		);

		update_post_meta( $attachment_id, '_wp_attached_file', dirname( __DIR__ ) . '/fixtures/sample.pdf' );

		return $attachment_id;
	}

	/**
	 * The source of one method of the integration.
	 *
	 * @param string $method Method name.
	 */
	private function method_body( string $method ): string {
		$reflected = new ReflectionMethod( WooCommerce::class, $method );
		$file      = (string) $reflected->getFileName();
		$lines     = (array) file( $file );

		return implode(
			'',
			array_slice(
				$lines,
				$reflected->getStartLine() - 1,
				$reflected->getEndLine() - $reflected->getStartLine() + 1
			)
		);
	}

	/**
	 * Queue the three calls one successful send makes.
	 */
	private function fake_successful_send(): void {
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/documents',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'  => 'document',
					'id'        => self::DOCUMENT_ID,
					'name'      => 'service-agreement.pdf',
					'status'    => 'uploaded',
					'artifacts' => array( 'original' => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/download/original' ),
					'is_closed' => false,
					'tags'      => array(),
					'pages'     => array(),
				),
			)
		);

		$this->fake_response(
			'/signers?',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					array(
						'resource'              => 'signer',
						'id'                    => self::SIGNER_ID,
						'full_name'             => 'Jane Doe',
						'email'                 => self::BILLING_EMAIL,
						'whatsapp_phone_number' => null,
					),
				),
			)
		);

		$this->fake_response(
			'/assignments',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'     => 'assignment',
					'id'           => self::ASSIGNMENT_ID,
					'method'       => 'virtual',
					'signers'      => array(
						array(
							'id'        => self::SIGNER_ID,
							'full_name' => 'Jane Doe',
							'email'     => self::BILLING_EMAIL,
							'completed' => false,
							'step'      => 1,
							'notified'  => true,
						),
					),
					'signing_urls' => array(
						array(
							'signer_id' => self::SIGNER_ID,
							'url'       => 'https://app.assinafy.com.br/sign/' . self::DOCUMENT_ID . '?email=jane%40example.com',
						),
					),
				),
			)
		);
	}
}
