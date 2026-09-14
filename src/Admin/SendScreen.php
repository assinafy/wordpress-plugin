<?php
/**
 * The "Send for signature" compose screen.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Settings;

/**
 * Composes a signature request and submits it through its own `admin_post_assinafy_send`
 * handler.
 *
 * The handler is deliberately not `save_post`. A signature request spends a document from
 * the account balance and notifies real people, so it must never be reachable from a hook
 * that WordPress replays: `wp_update_post()`, a bulk edit, a revision restore and a REST
 * write all fire `save_post`, and any of them would send the document a second time. An
 * explicit form post is the only path in.
 */
final class SendScreen {

	/**
	 * Menu slug for the compose screen.
	 */
	public const PAGE = 'assinafy-send';

	/**
	 * `admin_post_` action the compose form submits to.
	 */
	public const ACTION = 'assinafy_send';

	/**
	 * Prefix of the per-user transient carrying the post-redirect flash notice.
	 */
	private const FLASH_PREFIX = 'assinafy_send_notice_';

	/**
	 * Hook suffix of the compose screen, set once `admin_menu` has run.
	 */
	private string $hook_suffix = '';

	/**
	 * @param SendService $send The domain action that actually spends the document.
	 */
	public function __construct( private readonly SendService $send ) {
	}

	/**
	 * Attach the screen and its handler.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_send' ) );
	}

	/**
	 * Add the compose screen under the Assinafy menu.
	 */
	public function register_menu(): void {
		$hook_suffix = add_submenu_page(
			Settings::PAGE,
			__( 'Send for signature', 'assinafy' ),
			__( 'Send for signature', 'assinafy' ),
			self::capability(),
			self::PAGE,
			array( $this, 'render_page' )
		);

		$this->hook_suffix = (string) $hook_suffix;
	}

	/**
	 * The capability a user needs to send.
	 *
	 * Sites can widen or narrow this from the settings screen; an empty or unset option
	 * falls back to the capability the plugin installs.
	 */
	public static function capability(): string {
		$capability = Settings::get( Settings::OPTION_SENDER_CAP );

		return is_string( $capability ) && '' !== $capability ? $capability : Capabilities::SEND;
	}

	/**
	 * Load the media modal, the repeater script and the admin styles on this screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'assinafy-admin',
			ASSINAFY_URL . 'assets/css/admin.css',
			array(),
			ASSINAFY_VERSION
		);

		wp_enqueue_script(
			'assinafy-send-screen',
			ASSINAFY_URL . 'assets/js/send-screen.js',
			array( 'media-views' ),
			ASSINAFY_VERSION,
			true
		);

		wp_localize_script(
			'assinafy-send-screen',
			'assinafySendScreen',
			array(
				'i18n' => array(
					'chooseFile'     => __( 'Choose a PDF', 'assinafy' ),
					'useFile'        => __( 'Use this PDF', 'assinafy' ),
					'notPdf'         => __( 'Choose a PDF file.', 'assinafy' ),
					'invalidEmail'   => __( 'Enter a valid email address for every signer.', 'assinafy' ),
					'duplicateEmail' => __( 'Each signer must have a different email address.', 'assinafy' ),
				),
			)
		);
	}

	/**
	 * Render the compose form.
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to send documents for signature.', 'assinafy' ) );
		}

		$expiry_days = (int) Settings::get( Settings::OPTION_EXPIRY_DAYS );
		$default_due = $expiry_days > 0 ? gmdate( 'Y-m-d', time() + ( $expiry_days * DAY_IN_SECONDS ) ) : '';
		$message     = (string) Settings::get( Settings::OPTION_MESSAGE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Send for signature', 'assinafy' ); ?></h1>

			<?php Notice::render( self::FLASH_PREFIX, 'is-dismissible' ); ?>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="assinafy-send-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::ACTION ); ?>
				<input type="hidden" name="assinafy_request_id" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="assinafy-attachment-name"><?php esc_html_e( 'Document', 'assinafy' ); ?></label>
						</th>
						<td>
							<input type="hidden" name="assinafy_attachment_id" id="assinafy-attachment-id" value="" />
							<input type="text" class="regular-text" id="assinafy-attachment-name" readonly
								placeholder="<?php esc_attr_e( 'No PDF chosen', 'assinafy' ); ?>" value="" />
							<button type="button" class="button" id="assinafy-choose-file">
								<?php esc_html_e( 'Choose a PDF', 'assinafy' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Pick a PDF already in the media library, or upload one from the chooser. Files up to 25 MB are accepted.', 'assinafy' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Signers', 'assinafy' ); ?></th>
						<td>
							<table class="widefat striped assinafy-signers" id="assinafy-signers">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Name', 'assinafy' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Email', 'assinafy' ); ?></th>
										<th scope="col"><?php esc_html_e( 'WhatsApp (optional)', 'assinafy' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Order', 'assinafy' ); ?></th>
										<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'assinafy' ); ?></span></th>
									</tr>
								</thead>
								<tbody>
									<?php $this->render_signer_row( 0 ); ?>
								</tbody>
							</table>
							<p>
								<button type="button" class="button" id="assinafy-add-signer">
									<?php esc_html_e( 'Add another signer', 'assinafy' ); ?>
								</button>
							</p>
							<p class="description">
								<?php esc_html_e( 'Signers sharing an order number are invited at the same time. A higher number is invited only once every lower number has signed. Leave the column at 1 to invite everyone at once.', 'assinafy' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="assinafy-send-message"><?php esc_html_e( 'Message to signers', 'assinafy' ); ?></label>
						</th>
						<td>
							<textarea class="large-text" rows="3" id="assinafy-send-message"
								name="assinafy_message"><?php echo esc_textarea( $message ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="assinafy-expires-on"><?php esc_html_e( 'Signature deadline', 'assinafy' ); ?></label>
						</th>
						<td>
							<input type="date" id="assinafy-expires-on" name="assinafy_expires_on"
								min="<?php echo esc_attr( gmdate( 'Y-m-d', time() + DAY_IN_SECONDS ) ); ?>"
								value="<?php echo esc_attr( $default_due ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Signers can no longer sign after the end of this day (UTC). Leave the field blank to use the deadline set on the settings screen.', 'assinafy' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="description">
					<?php esc_html_e( 'Sending consumes one document from your Assinafy balance. Email invitations cost no credits. If the balance is short, the request is refused before anything is uploaded.', 'assinafy' ); ?>
				</p>

				<?php submit_button( __( 'Send for signature', 'assinafy' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one signer row.
	 *
	 * The row index is part of every field name. The repeater script re-indexes rows by DOM
	 * position after an add or a remove: two rows sharing an index would silently collapse
	 * into one entry in `$_POST` and drop a signer without any error.
	 *
	 * @param int $index Zero-based row index.
	 */
	private function render_signer_row( int $index ): void {
		$base = 'assinafy_signers[' . $index . ']';
		?>
		<tr class="assinafy-signer-row">
			<td>
				<label class="screen-reader-text" for="assinafy-signer-name-<?php echo esc_attr( (string) $index ); ?>">
					<?php esc_html_e( 'Signer name', 'assinafy' ); ?>
				</label>
				<input type="text" class="regular-text" required
					data-assinafy-field="name" id="assinafy-signer-name-<?php echo esc_attr( (string) $index ); ?>"
					name="<?php echo esc_attr( $base . '[name]' ); ?>" value="" />
			</td>
			<td>
				<label class="screen-reader-text" for="assinafy-signer-email-<?php echo esc_attr( (string) $index ); ?>">
					<?php esc_html_e( 'Signer email', 'assinafy' ); ?>
				</label>
				<input type="email" class="regular-text" required
					data-assinafy-field="email" id="assinafy-signer-email-<?php echo esc_attr( (string) $index ); ?>"
					name="<?php echo esc_attr( $base . '[email]' ); ?>" value="" />
			</td>
			<td>
				<label class="screen-reader-text" for="assinafy-signer-phone-<?php echo esc_attr( (string) $index ); ?>">
					<?php esc_html_e( 'Signer WhatsApp number', 'assinafy' ); ?>
				</label>
				<input type="tel" class="regular-text"
					data-assinafy-field="phone" id="assinafy-signer-phone-<?php echo esc_attr( (string) $index ); ?>"
					name="<?php echo esc_attr( $base . '[phone]' ); ?>" value="" />
			</td>
			<td>
				<label class="screen-reader-text" for="assinafy-signer-step-<?php echo esc_attr( (string) $index ); ?>">
					<?php esc_html_e( 'Signing order', 'assinafy' ); ?>
				</label>
				<input type="number" class="small-text" min="1" step="1"
					data-assinafy-field="step" id="assinafy-signer-step-<?php echo esc_attr( (string) $index ); ?>"
					name="<?php echo esc_attr( $base . '[step]' ); ?>" value="1" />
			</td>
			<td>
				<button type="button" class="button-link assinafy-remove-signer">
					<?php esc_html_e( 'Remove', 'assinafy' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Handle the compose form.
	 *
	 * Order is fixed and load-bearing: nonce, capability, sanitise, then confirm the chosen
	 * attachment really is a PDF this user is allowed to read. Checking the mime type without
	 * checking readability would let anyone who may send hand the plugin the id of a private
	 * PDF belonging to someone else and have Assinafy mail it out.
	 */
	public function handle_send(): void {
		check_admin_referer( self::ACTION );

		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to send documents for signature.', 'assinafy' ), '', array( 'response' => 403 ) );
		}

		$post_id = $this->send->send( $this->submitted_request() );

		if ( $post_id instanceof \WP_Error ) {
			$recovery = $post_id->get_error_data();
			if ( is_array( $recovery ) && ! empty( $recovery['post_id'] ) ) {
				Notice::set( DocumentActions::FLASH_PREFIX, 'error', $post_id->get_error_message() );
				wp_safe_redirect( get_edit_post_link( (int) $recovery['post_id'], 'raw' ) ?? admin_url( 'edit.php?post_type=assinafy_document' ) );
				exit;
			}
			$this->redirect_back(
				'error',
				sprintf(
					/* translators: %s: reason the send was refused. */
					__( 'The document was not sent: %s', 'assinafy' ),
					$post_id->get_error_message()
				)
			);
		}

		Notice::set( self::FLASH_PREFIX, 'success', __( 'Sent. Signers have been invited by email.', 'assinafy' ) );

		wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) ?? admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Read the compose form into the arguments `SendService` takes.
	 *
	 * Never returns a request worth refusing: each check redirects back to the form with its
	 * reason instead of returning, so the caller has nothing left to validate. The nonce and
	 * the capability are the caller's job and are already settled by the time this runs.
	 *
	 * @return array<string, mixed> Arguments for `SendService::send()`.
	 */
	private function submitted_request(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran in handle_send().
		$attachment_id = isset( $_POST['assinafy_attachment_id'] ) ? absint( wp_unslash( $_POST['assinafy_attachment_id'] ) ) : 0;
		$message       = isset( $_POST['assinafy_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['assinafy_message'] ) ) : '';
		$expires_on    = isset( $_POST['assinafy_expires_on'] ) ? sanitize_text_field( wp_unslash( $_POST['assinafy_expires_on'] ) ) : '';
		$request_id    = isset( $_POST['assinafy_request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['assinafy_request_id'] ) ) : '';

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$attachment_error = $this->validate_attachment( $attachment_id );

		if ( '' !== $attachment_error ) {
			$this->redirect_back( 'error', $attachment_error );
		}

		$signers = $this->read_signers( $this->posted_signer_rows() );

		if ( $signers instanceof \WP_Error ) {
			$this->redirect_back( 'error', $signers->get_error_message() );
		}

		// An empty field means no deadline at all; a value that will not parse is a mistake
		// worth reporting rather than silently dropping.
		$expires_at = Deadline::parse( $expires_on );

		if ( '' !== $expires_on && '' === $expires_at ) {
			$this->redirect_back( 'error', __( 'Choose a signature deadline in the future.', 'assinafy' ) );
		}

		return array(
			'attachment_id'   => $attachment_id,
			'signers'         => $signers,
			'message'         => $message,
			'expires_at'      => $expires_at,
			'idempotency_key' => $this->idempotency_key( $attachment_id, $signers, $message, $expires_at, $request_id ),
		);
	}

	/**
	 * The signer repeater exactly as it was posted.
	 *
	 * Deliberately unsanitised: `read_signers()` sanitises each leaf against the type that
	 * leaf is meant to be, and a blanket pass over the array here would have to guess.
	 *
	 * @return array<mixed> Unslashed rows; an empty array when the field is absent or malformed.
	 */
	private function posted_signer_rows(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran in handle_send().
		if ( ! isset( $_POST['assinafy_signers'] ) || ! is_array( $_POST['assinafy_signers'] ) ) {
			return array();
		}

		return wp_unslash( $_POST['assinafy_signers'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is sanitised in read_signers().
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Confirm the chosen attachment is a PDF the current user may read.
	 *
	 * @param int $attachment_id Attachment post id from the form.
	 *
	 * @return string Empty string when the attachment is usable, otherwise a translated reason.
	 */
	private function validate_attachment( int $attachment_id ): string {
		if ( 0 === $attachment_id ) {
			return __( 'Choose a PDF to send.', 'assinafy' );
		}

		$attachment = get_post( $attachment_id );

		if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
			return __( 'That file is no longer in the media library.', 'assinafy' );
		}

		if ( 'application/pdf' !== $attachment->post_mime_type ) {
			return __( 'Assinafy signs PDF files only. Choose a PDF.', 'assinafy' );
		}

		if ( ! current_user_can( 'read_post', $attachment_id ) ) {
			return __( 'You are not allowed to use that file.', 'assinafy' );
		}

		return '';
	}

	/**
	 * Read, sanitise and normalise the signer repeater.
	 *
	 * Reject the whole request if a row is invalid. Dropping a row silently would send a
	 * signature request without every person the operator selected.
	 *
	 * Steps are re-ranked to a contiguous sequence starting at 1, because the API refuses
	 * anything else and a form where someone typed 1 and 3 is asking for two sequential
	 * rounds, not an error.
	 *
	 * @param array<mixed> $rows Unslashed, unsanitised repeater rows.
	 *
	 * @return array<int, array{full_name: string, email: string, whatsapp_phone_number?: string, step: int}>|\WP_Error
	 */
	private function read_signers( array $rows ): array|\WP_Error {
		$signers = array();
		$seen    = array();
		$error   = new \WP_Error( 'assinafy_invalid_signers', __( 'Add a name and a valid, unique email address for every signer.', 'assinafy' ) );

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || array_filter( $row, 'is_array' ) ) {
				return $error;
			}

			$name  = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			$email = trim( (string) ( $row['email'] ?? '' ) );
			$phone = sanitize_text_field( (string) ( $row['phone'] ?? '' ) );
			$step  = max( 1, absint( $row['step'] ?? 1 ) );

			if ( '' === $name || ! is_email( $email ) || isset( $seen[ strtolower( $email ) ] ) ) {
				return $error;
			}

			$seen[ strtolower( $email ) ] = true;

			$signer = array(
				'full_name' => $name,
				'email'     => sanitize_email( $email ),
				'step'      => $step,
			);

			if ( '' !== $phone ) {
				$signer['whatsapp_phone_number'] = $phone;
			}

			$signers[] = $signer;
		}

		return array() === $signers ? $error : $this->rank_steps( $signers );
	}

	/**
	 * Collapse the entered step numbers onto 1..n, preserving their order.
	 *
	 * @param array<int, array{full_name: string, email: string, whatsapp_phone_number?: string, step: int}> $signers Sanitised signer rows.
	 *
	 * @return array<int, array{full_name: string, email: string, whatsapp_phone_number?: string, step: int}>
	 */
	private function rank_steps( array $signers ): array {
		$steps = array_values( array_unique( array_map( 'intval', array_column( $signers, 'step' ) ) ) );
		sort( $steps );

		$rank = array_flip( $steps );

		foreach ( $signers as $index => $signer ) {
			$signers[ $index ]['step'] = (int) $rank[ (int) $signer['step'] ] + 1;
		}

		return $signers;
	}

	/**
	 * Build the deterministic key that stops a double submit from sending twice.
	 *
	 * Everything that defines the request goes in, so re-sending the same PDF to the same
	 * people with the same terms inside the lock window is treated as the duplicate it is,
	 * while changing any of them is a new request. A fresh compose form carries a new request
	 * id, so an operator can intentionally send the same terms again.
	 *
	 * @param int                              $attachment_id Chosen attachment.
	 * @param array<int, array<string, mixed>> $signers       Sanitised signer rows.
	 * @param string                           $message       Message to signers.
	 * @param string                           $expires_at    Deadline, or an empty string.
	 * @param string                           $request_id    Unique compose-form submission id.
	 */
	private function idempotency_key( int $attachment_id, array $signers, string $message, string $expires_at, string $request_id ): string {
		return md5(
			(string) wp_json_encode(
				array(
					'request'    => $request_id,
					'user'       => get_current_user_id(),
					'attachment' => $attachment_id,
					'signers'    => $signers,
					'message'    => $message,
					'expires_at' => $expires_at,
				)
			)
		);
	}

	/**
	 * Store a one-shot notice for the current user and send them back to the form.
	 *
	 * @param string $type    `success` or `error`.
	 * @param string $message Translated, unescaped message.
	 */
	private function redirect_back( string $type, string $message ): never {
		Notice::set( self::FLASH_PREFIX, $type, $message );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}
}
