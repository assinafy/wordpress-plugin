<?php
/**
 * Native Gravity Forms feed adapter.
 *
 * @package Assinafy\WP\Addons\GravityForms
 */

declare(strict_types=1);

namespace Assinafy\WP\Addons\GravityForms;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use WP_Error;

/**
 * Gravity Forms owns feeds, conditions, asynchronous processing and entry storage.
 * The native framework requires inheritance and get_instance(); no extra container.
 * @SuppressWarnings("PHPMD.LongVariable")
 */
// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore -- Native Gravity Forms property names.
final class Addon extends \GFFeedAddOn {

	/** @var string Native framework version. */
	protected $_version = '0.1.0';
	/** @var string First host version persisting WP_Error feed status. */
	protected $_min_gravityforms_version = '2.9.4';
	/** @var string Native feed namespace. */
	protected $_slug = 'assinafy-gravity-forms';
	/** @var string Installed plugin basename. */
	protected $_path = 'assinafy-gravity-forms/assinafy-gravity-forms.php';
	/** @var string Main plugin file. */
	protected $_full_path = __DIR__ . '/assinafy-gravity-forms.php';
	/** @var string Native framework title. */
	protected $_title = 'Assinafy for Gravity Forms';
	/** @var string Form settings tab. */
	protected $_short_title = 'Assinafy';
	/** @var string Feed changes authorize signature invitations. */
	protected $_capabilities_form_settings = 'assinafy_send';
	/** @var bool Use the host's persisted feed queue. */
	public $_async_feed_processing = true;

	private static ?self $instance = null;
	private ?SendService $sender   = null;

	/** Native Add-On Framework factory. */
	public static function get_instance(): self {
		self::$instance ??= new self();
		return self::$instance;
	}

	/** @param SendService $sender Shared core service. */
	public function connect( SendService $sender ): void {
		if ( null !== $this->sender ) {
			return;
		}
		$this->sender = $sender;
		add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'entry_boxes' ), 10, 2 );
		add_action( 'admin_post_assinafy_gravity_forms_retry', array( $this, 'retry' ) );
	}

	/** @return array<int, array<string, mixed>> Native feed fields. */
	public function feed_settings_fields(): array {
		return array(
			array(
				'title'  => esc_html__( 'Signature request', 'assinafy-gravity-forms' ),
				'fields' => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'assinafy-gravity-forms' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'name'                => 'attachment_id',
						'label'               => esc_html__( 'PDF attachment ID', 'assinafy-gravity-forms' ),
						'type'                => 'text',
						'required'            => true,
						'validation_callback' => array( $this, 'validate_pdf' ),
					),
					array(
						'name'      => 'signer',
						'label'     => esc_html__( 'Signer', 'assinafy-gravity-forms' ),
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'       => 'name',
								'label'      => esc_html__( 'Full name', 'assinafy-gravity-forms' ),
								'required'   => true,
								'field_type' => array( 'name', 'text' ),
							),
							array(
								'name'       => 'email',
								'label'      => esc_html__( 'Email', 'assinafy-gravity-forms' ),
								'required'   => true,
								'field_type' => array( 'email' ),
							),
						),
					),
					array(
						'name'  => 'message',
						'label' => esc_html__( 'Message', 'assinafy-gravity-forms' ),
						'type'  => 'textarea',
					),
					array(
						'name'  => 'condition',
						'label' => esc_html__( 'Condition', 'assinafy-gravity-forms' ),
						'type'  => 'feed_condition',
					),
				),
			),
		);
	}

	/**
	 * Native validation runs behind Gravity Forms' settings nonce and permission checks.
	 * @param mixed $field Native settings field.
	 * @param mixed $value Selected attachment.
	 */
	public function validate_pdf( $field, $value ): void {
		$id   = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		$file = false !== $id ? get_attached_file( $id ) : false;
		if ( false === $id || ! is_string( $file ) || ! is_readable( $file ) || ! current_user_can( 'assinafy_send' ) || ! current_user_can( 'gravityforms_edit_forms' ) || ! current_user_can( 'edit_post', $id ) || 'application/pdf' !== get_post_mime_type( $id ) ) {
			$this->set_field_error( $field, esc_html__( 'Choose a PDF attachment you can edit. Sending and form editing permissions are required.', 'assinafy-gravity-forms' ) );
		}
	}

	/**
	 * @param array<string,mixed> $feed Saved native feed.
	 * @param array<array-key,mixed> $entry Saved entry.
	 * @param array<string,mixed> $form Saved form.
	 * @return true|WP_Error Native feed result.
	 */
	public function process_feed( $feed, $entry, $form ): bool|WP_Error {
		return null === $this->sender ? $this->problem() : ( new Requests( $this, $this->sender ) )->process( $feed, $entry, $form );
	}

	/**
	 * @param array<string, mixed> $boxes Native meta boxes.
	 * @param array<array-key, mixed> $entry Current entry.
	 * @return array<string, mixed> Meta boxes.
	 */
	public function entry_boxes( array $boxes, array $entry ): array {
		if ( current_user_can( 'assinafy_view' ) && current_user_can( 'gravityforms_view_entries' ) && (int) ( $entry['id'] ?? 0 ) > 0 ) {
			$boxes['assinafy'] = array(
				'title'    => 'Assinafy',
				'callback' => array( $this, 'entry_box' ),
				'context'  => 'side',
			);
		}
		return $boxes;
	}

	/** @param array{entry:array<array-key,mixed>} $args Native entry/form context. */
	public function entry_box( array $args ): void {
		if ( ! current_user_can( 'assinafy_view' ) || ! current_user_can( 'gravityforms_view_entries' ) ) {
			return;
		}
		$entry = $args['entry'];
		foreach ( $this->get_feeds( (int) ( $entry['form_id'] ?? 0 ) ) as $feed ) {
			$receipt = gform_get_meta( (int) $entry['id'], 'assinafy_feed_' . (int) $feed['id'] );
			if ( ! is_array( $receipt ) ) {
				continue;
			}
			$post_id = (int) ( $receipt['post_id'] ?? 0 );
			$records = new DocumentRecord();
			echo '<p>' . esc_html( (string) ( $feed['meta']['feedName'] ?? 'Assinafy' ) ) . ': ' . esc_html( $records->status( $post_id ) ) . '</p>';
			if ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
				echo '<p><a href="' . esc_url( (string) get_edit_post_link( $post_id, 'raw' ) ) . '">' . esc_html__( 'Open signature request', 'assinafy-gravity-forms' ) . '</a></p>';
			}
			if ( current_user_can( 'assinafy_send' ) && '' === $records->assignment_id( $post_id ) ) {
				$url = add_query_arg(
					array(
						'action'   => 'assinafy_gravity_forms_retry',
						'entry_id' => (int) $entry['id'],
						'feed_id'  => (int) $feed['id'],
					),
					admin_url( 'admin-post.php' )
				);
				echo '<p><a class="button" href="' . esc_url( wp_nonce_url( $url, 'assinafy_gf_retry_' . (int) $entry['id'] . '_' . (int) $feed['id'] ) ) . '">' . esc_html__( 'Retry signature request', 'assinafy-gravity-forms' ) . '</a></p>';
			}
		}
	}

	/** Reprocess an existing failed receipt, without inventing a second queue. */
	public function retry(): void {
		if ( ! $this->can_retry() ) {
			wp_die( esc_html__( 'You cannot retry signature requests.', 'assinafy-gravity-forms' ), '', array( 'response' => 403 ) );
		}
		$entry_id = isset( $_GET['entry_id'] ) ? absint( wp_unslash( $_GET['entry_id'] ) ) : 0;
		$feed_id  = isset( $_GET['feed_id'] ) ? absint( wp_unslash( $_GET['feed_id'] ) ) : 0;
		check_admin_referer( 'assinafy_gf_retry_' . $entry_id . '_' . $feed_id );
		$entry = \GFAPI::get_entry( $entry_id );
		$feed  = $this->get_feed( $feed_id );
		if ( ! is_array( $entry ) || ! is_array( $feed ) || ! is_array( gform_get_meta( $entry_id, 'assinafy_feed_' . $feed_id ) ) ) {
			wp_die( esc_html__( 'The original signature request is unavailable.', 'assinafy-gravity-forms' ) );
		}
		$form   = \GFAPI::get_form( (int) $entry['form_id'] );
		$result = $this->process_feed( $feed, $entry, is_array( $form ) ? $form : array() );
		$this->save_entry_feed_status( $result, $entry_id, $feed_id, (int) $entry['form_id'] );
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'gf_entries',
					'view' => 'entry',
					'id'   => (int) $entry['form_id'],
					'lid'  => $entry_id,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Retry authorization applies to both the host entry and the signature request. */
	private function can_retry(): bool {
		return current_user_can( 'assinafy_send' ) && current_user_can( 'gravityforms_view_entries' );
	}

	/** @return WP_Error Safe refusal for absent or changed local prerequisites. */
	private function problem(): WP_Error {
		return new WP_Error( 'assinafy_feed_unavailable', __( 'This signature feed or its original record is unavailable. Check the active entry, feed and Assinafy configuration.', 'assinafy-gravity-forms' ) );
	}
}
