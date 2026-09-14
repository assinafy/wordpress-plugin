<?php
/**
 * WooCommerce order-completion trigger.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Integrations;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use WC_Order;
use WC_Product;

/**
 * Sends a product's agreement to the customer when their order completes.
 *
 * A product carries three pieces of configuration on its own Signature tab: whether the
 * feature is on, which media-library PDF to send, and an optional message. When an order
 * containing that product reaches `completed`, the billing name and address on the order
 * become the single signer.
 *
 * ## Double-send protection is two-layered
 *
 * `woocommerce_order_status_completed` can reach the same order more than once. Re-completing
 * a refunded order fires it again, so does a bulk "mark completed" action over the order list,
 * and so does a delayed gateway callback arriving after a human already completed the order.
 * Two independent guards therefore stand between that and a second signature request:
 *
 * 1. An order-meta map of attachment id to local document post id records what has already
 *    been sent for this order, and is consulted before anything else. It survives forever,
 *    which is what makes a re-completion three weeks later a no-op.
 * 2. A deterministic `idempotency_key` (order id plus attachment id) reaches `SendService`,
 *    whose database lock catches two requests racing before either has saved the map.
 *    The sender also retains a recovery record for this key after its transient expires.
 *
 * ## HPOS
 *
 * Every order read and write goes through `wc_get_order()` and the order object's own
 * `get_meta()` / `update_meta_data()` / `save()`. `get_post_meta()` on an order id returns
 * nothing once high-performance order storage is enabled, and silently — the order would
 * simply look as though it had never been sent, and would be sent again on every save.
 * Compatibility with that storage is declared from `declare_compatibility()`
 * on `before_woocommerce_init`.
 */
final class WooCommerce {

	/**
	 * Product meta: `yes` when this product triggers a signature request.
	 */
	public const PRODUCT_ENABLED = '_assinafy_enabled';

	/**
	 * Product meta: attachment id of the PDF to send.
	 */
	public const PRODUCT_ATTACHMENT = '_assinafy_attachment_id';

	/**
	 * Product meta: optional invitation message.
	 */
	public const PRODUCT_MESSAGE = '_assinafy_message';

	/**
	 * Order meta: map of attachment id to the local document post id already sent for it.
	 */
	public const ORDER_SENT = '_assinafy_sent';

	/**
	 * `DocumentRecord` is a stateless accessor over post meta; the bundled bootstrap injects
	 * the core's shared instance.
	 *
	 * @param SendService    $sender  The domain action every trigger routes through.
	 * @param DocumentRecord $records Typed access to local document records.
	 */
	public function __construct(
		private readonly SendService $sender,
		private readonly DocumentRecord $records = new DocumentRecord()
	) {
	}

	/**
	 * Declare supported order storage and checkout features before WooCommerce initializes.
	 */
	public static function declare_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ASSINAFY_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ASSINAFY_FILE, true );
		}
	}

	/**
	 * Attach the product tab, the order trigger and the customer email link.
	 */
	public function register(): void {
		// WooCommerce sends its completion email at priority 10, using this same order object.
		add_action( 'woocommerce_order_status_completed', array( $this, 'send_for_order' ), 5, 2 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'render_email_links' ), 10, 3 );

		// The adapter owns the product configuration as well as the order trigger.
		( new WooCommerceProductTab() )->register();
	}

	/**
	 * Send every configured document on a completed order.
	 *
	 * @param int   $order_id Order that reached `completed`.
	 * @param mixed $order    Order instance supplied by WooCommerce, if available.
	 */
	public function send_for_order( int $order_id, mixed $order = null ): void {
		$order ??= wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$sent = $order->get_meta( self::ORDER_SENT );
		$sent = is_array( $sent ) ? $sent : array();

		$email = sanitize_email( $order->get_billing_email() );
		$name  = trim( $order->get_formatted_billing_full_name() );

		$dirty = false;

		foreach ( $this->attachments_for( $order ) as $attachment_id => $message ) {
			if ( isset( $sent[ $attachment_id ] ) ) {
				continue;
			}

			if ( '' === $email || ! is_email( $email ) ) {
				$order->add_order_note(
					__( 'Assinafy: no signature request sent — the order has no usable billing email address.', 'assinafy' )
				);

				break;
			}

			$post_id = $this->send_one( $order, $attachment_id, $message, $name, $email );

			if ( 0 < $post_id ) {
				$sent[ $attachment_id ] = $post_id;
				$dirty                  = true;
			}
		}

		if ( $dirty ) {
			$order->update_meta_data( self::ORDER_SENT, $sent );
			$order->save();
		}
	}

	/**
	 * Add the customer's signing links to the order email.
	 *
	 * The link carries no secret — it is the same public URL Assinafy mails the signer, and
	 * the one-time code that gets past it is requested by the signer themselves — so repeating
	 * it in the order email costs nothing and saves a support ticket.
	 *
	 * WooCommerce fires one hook for the customer's copy and the shop manager's alike, and
	 * `$sent_to_admin` is how a callback tells them apart. The manager's copy carries no links:
	 * the manager is not a signer. The third argument selects the plain-text template.
	 *
	 * @param mixed $order         The order the email is about.
	 * @param mixed $sent_to_admin Whether this copy is going to a shop manager.
	 * @param mixed $plain_text    Whether WooCommerce is rendering its plain-text template.
	 */
	public function render_email_links( mixed $order, mixed $sent_to_admin, mixed $plain_text ): void {
		if ( $sent_to_admin || ! $order instanceof WC_Order ) {
			return;
		}

		$this->render_signing_links( $order, (bool) $plain_text );
	}

	/**
	 * Print the customer's signing links for one order.
	 *
	 * @param WC_Order $order      Order the email is about.
	 * @param bool     $plain_text Whether to print text instead of HTML.
	 */
	private function render_signing_links( WC_Order $order, bool $plain_text ): void {
		$sent = $order->get_meta( self::ORDER_SENT );

		if ( ! is_array( $sent ) || array() === $sent ) {
			return;
		}

		$email = sanitize_email( $order->get_billing_email() );
		$links = array();

		foreach ( $sent as $post_id ) {
			$url = $this->signing_url( (int) $post_id, $email );

			if ( '' !== $url ) {
				$links[ (int) $post_id ] = $url;
			}
		}

		if ( array() === $links ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Documents to sign', 'assinafy' ) . "\n";

			foreach ( $links as $post_id => $url ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email; strip tags and preserve URL query separators literally.
				echo wp_strip_all_tags( get_the_title( $post_id ) ) . ': ' . esc_url_raw( $url ) . "\n";
			}

			return;
		}

		echo '<h2>' . esc_html__( 'Documents to sign', 'assinafy' ) . '</h2><ul>';

		foreach ( $links as $post_id => $url ) {
			echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></li>';
		}

		echo '</ul>';
	}

	/**
	 * Send one document and write the order note either way.
	 *
	 * @param WC_Order $order         Order being completed.
	 * @param int      $attachment_id PDF to send.
	 * @param string   $message       Product-level message, possibly empty.
	 * @param string   $name          Billing name.
	 * @param string   $email         Billing email, already validated.
	 *
	 * @return int Local document post id, or 0 when the send failed.
	 */
	private function send_one( WC_Order $order, int $attachment_id, string $message, string $name, string $email ): int {
		$args = array(
			'attachment_id'   => $attachment_id,
			'signers'         => array(
				array(
					'full_name' => '' !== $name ? $name : $email,
					'email'     => $email,
				),
			),
			'idempotency_key' => 'wc-order-' . $order->get_id() . '-attachment-' . $attachment_id,
			'source'          => array(
				'integration' => 'woocommerce',
				'record_id'   => (string) $order->get_id(),
			),
		);

		if ( '' !== $message ) {
			$args['message'] = $message;
		}

		try {
			$post_id = $this->sender->send( $args );

			if ( is_wp_error( $post_id ) ) {
				$this->note_failure( $order, $attachment_id, $post_id->get_error_message() );

				return 0;
			}

			$order->add_order_note(
				sprintf(
					/* translators: 1: document name, 2: signer email address. */
					__( 'Assinafy: signature request for "%1$s" sent to %2$s.', 'assinafy' ),
					get_the_title( $attachment_id ),
					$email
				)
			);

			return $post_id;
		} catch ( \Throwable $e ) {
			$this->note_failure( $order, $attachment_id, $e->getMessage() );

			return 0;
		}
	}

	/**
	 * Record why a document was not sent, where whoever is investigating will look first.
	 *
	 * @param WC_Order $order         Order being completed.
	 * @param int      $attachment_id PDF that was not sent.
	 * @param string   $reason        Message from Assinafy or from the plugin.
	 */
	private function note_failure( WC_Order $order, int $attachment_id, string $reason ): void {
		$order->add_order_note(
			sprintf(
				/* translators: 1: document name, 2: error message from Assinafy. */
				__( 'Assinafy: signature request for "%1$s" failed — %2$s. Re-completing the order will try again.', 'assinafy' ),
				get_the_title( $attachment_id ),
				$reason
			)
		);
	}

	/**
	 * The documents a completed order owes, keyed by attachment id.
	 *
	 * Two line items pointing at the same PDF produce one entry, which is the right answer:
	 * a customer buying two copies of the same agreement signs it once.
	 *
	 * @param WC_Order $order Order being completed.
	 *
	 * @return array<int, string> Attachment id to product-level message.
	 */
	private function attachments_for( WC_Order $order ): array {
		$documents = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			// A variation carries no tab of its own; the configuration lives on its parent.
			if ( 0 < $product->get_parent_id() ) {
				$parent = wc_get_product( $product->get_parent_id() );

				if ( $parent instanceof WC_Product ) {
					$product = $parent;
				}
			}

			if ( 'yes' !== $product->get_meta( self::PRODUCT_ENABLED ) ) {
				continue;
			}

			$attachment_id = (int) $product->get_meta( self::PRODUCT_ATTACHMENT );

			if ( 0 >= $attachment_id || 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
				continue;
			}

			$documents[ $attachment_id ] = (string) $product->get_meta( self::PRODUCT_MESSAGE );
		}

		return $documents;
	}

	/**
	 * The signing URL stored on a local document record for one signer.
	 *
	 * Only notified signers have one; a second-step signer has `notified: false` and no URL
	 * until everyone before them has finished, so absence is normal and is not an error.
	 *
	 * @param int    $post_id Local document post id.
	 * @param string $email   Signer's email address.
	 *
	 * @return string The URL, or '' when there is none to show.
	 */
	private function signing_url( int $post_id, string $email ): string {
		if ( 0 >= $post_id || '' === $email ) {
			return '';
		}

		foreach ( $this->records->signers( $post_id ) as $signer ) {
			if ( 0 === strcasecmp( $signer['email'], $email ) ) {
				return $signer['signing_url'];
			}
		}

		return '';
	}
}
