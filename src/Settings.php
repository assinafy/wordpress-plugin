<?php
/**
 * Plugin options and settings screen.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Admin\SettingsAjax;
use Assinafy\WP\Admin\WebhookSettings;
use Assinafy\WP\Webhook\SigningSecret;
use WP_Error;

/**
 * The single source of truth for every option the plugin owns.
 *
 * `self::OPTIONS` drives registration, the settings screen and `uninstall.php` alike, so a
 * new option cannot be added in one place and forgotten in another — the uninstall routine
 * iterates this map rather than repeating it.
 */
final class Settings {

	public const PAGE = 'assinafy';

	public const OPTION_ACCOUNT_ID = Credentials::OPTION_ACCOUNT_ID;

	public const OPTION_API_KEY = Credentials::OPTION_API_KEY;

	public const OPTION_OAUTH_CONNECTION = Credentials::OPTION_OAUTH_CONNECTION;

	public const OPTION_AUTH_MODE = Credentials::OPTION_AUTH_MODE;

	public const OPTION_REFRESH_LOCK = OAuthTokens::OPTION_REFRESH_LOCK;

	public const OPTION_ENVIRONMENT = 'assinafy_environment';

	public const OPTION_WEBHOOK_TOKEN = 'assinafy_webhook_token';

	public const OPTION_WEBHOOK_ENABLED = 'assinafy_webhook_enabled';

	public const OPTION_WEBHOOK_ENDPOINT_ID = 'assinafy_webhook_endpoint_id';

	public const OPTION_WEBHOOK_SECRET_ENC = 'assinafy_webhook_secret_enc';

	public const OPTION_EXPIRY_DAYS = 'assinafy_default_expiry_days';

	public const OPTION_MESSAGE = 'assinafy_default_message';

	public const OPTION_SENDER_CAP = 'assinafy_sender_cap';

	public const OPTION_DELETE_DATA = 'assinafy_delete_data_on_uninstall';

	/**
	 * Every option the plugin writes: name => default, sanitiser, autoload.
	 *
	 * A sanitiser naming a method on this class is bound to the instance; anything else is
	 * used as a plain callable.
	 *
	 * @var array<string, array{default: mixed, sanitize: string, autoload: bool, type: string}>
	 */
	public const OPTIONS = array(
		self::OPTION_ACCOUNT_ID          => array(
			'default'  => '',
			'sanitize' => 'sanitize_text_field',
			'autoload' => true,
			'type'     => 'string',
		),
		self::OPTION_ENVIRONMENT         => array(
			'default'  => 'production',
			'sanitize' => 'sanitize_key',
			'autoload' => true,
			'type'     => 'string',
		),
		self::OPTION_API_KEY             => array(
			'default'  => '',
			'sanitize' => 'sanitize_api_key',
			'autoload' => false,
			'type'     => 'string',
		),
		self::OPTION_OAUTH_CONNECTION    => array(
			'default'  => '',
			'sanitize' => 'sanitize_text_field',
			'autoload' => false,
			'type'     => 'string',
		),
		self::OPTION_AUTH_MODE           => array(
			'default'  => '',
			'sanitize' => 'sanitize_key',
			'autoload' => true,
			'type'     => 'string',
		),
		self::OPTION_REFRESH_LOCK        => array(
			'default'  => '',
			'sanitize' => 'sanitize_text_field',
			'autoload' => false,
			'type'     => 'string',
		),
		self::OPTION_WEBHOOK_TOKEN       => array(
			'default'  => '',
			'sanitize' => 'sanitize_key',
			'autoload' => false,
			'type'     => 'string',
		),
		self::OPTION_WEBHOOK_ENDPOINT_ID => array(
			'default'  => '',
			'sanitize' => 'sanitize_text_field',
			'autoload' => false,
			'type'     => 'string',
		),
		self::OPTION_WEBHOOK_SECRET_ENC  => array(
			'default'  => '',
			'sanitize' => 'sanitize_webhook_secret',
			'autoload' => false,
			'type'     => 'string',
		),
		self::OPTION_WEBHOOK_ENABLED     => array(
			'default'  => false,
			'sanitize' => 'rest_sanitize_boolean',
			'autoload' => true,
			'type'     => 'boolean',
		),
		self::OPTION_EXPIRY_DAYS         => array(
			'default'  => 30,
			'sanitize' => 'absint',
			'autoload' => true,
			'type'     => 'integer',
		),
		self::OPTION_MESSAGE             => array(
			'default'  => '',
			'sanitize' => 'sanitize_textarea_field',
			'autoload' => true,
			'type'     => 'string',
		),
		self::OPTION_SENDER_CAP          => array(
			'default'  => Capabilities::SEND,
			'sanitize' => 'sanitize_key',
			'autoload' => true,
			'type'     => 'string',
		),
		self::OPTION_DELETE_DATA         => array(
			'default'  => false,
			'sanitize' => 'rest_sanitize_boolean',
			'autoload' => true,
			'type'     => 'boolean',
		),
	);

	/**
	 * @param Credentials   $credentials Credential store.
	 * @param ClientFactory $clients     API client factory.
	 * @param Log           $log         Plugin event log.
	 */
	public function __construct(
		private readonly Credentials $credentials,
		private readonly ClientFactory $clients,
		private readonly Log $log
	) {
	}

	/**
	 * Read an option through its registered default.
	 *
	 * @param string $option Option name from `self::OPTIONS`.
	 *
	 * @return mixed Stored value, or the declared default.
	 */
	public static function get( string $option ): mixed {
		if ( ! isset( self::OPTIONS[ $option ] ) ) {
			return null;
		}

		return get_option( $option, self::OPTIONS[ $option ]['default'] );
	}

	/**
	 * Hook the settings screen, the option registrations and the admin AJAX endpoints.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_options' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		// All three, not just `updated_option`: storing a credential for the first time fires
		// `added_option`, and clearing one fires `deleted_option`. Listening to only the update
		// would leave the memoised client holding credentials the site no longer has.
		add_action( 'updated_option', array( $this, 'maybe_reset_client' ) );
		add_action( 'added_option', array( $this, 'maybe_reset_client' ) );
		add_action( 'deleted_option', array( $this, 'maybe_reset_client' ) );
		add_action( 'switch_blog', array( $this->clients, 'reset' ) );
		add_filter( 'pre_update_option_' . self::OPTION_API_KEY, array( $this, 'keep_credential_when_blank' ), 10, 2 );
		add_filter( 'pre_update_option_' . self::OPTION_WEBHOOK_SECRET_ENC, array( $this, 'keep_credential_when_blank' ), 10, 2 );
		// The settings form posts to options.php, which writes each option with no autoload at
		// all, so core's heuristic stores `auto` — autoloaded — and the encrypted key would be
		// read into `alloptions` on every request. This makes `self::OPTIONS` the authority.
		add_filter(
			'wp_default_autoload_value',
			static fn( mixed $autoload, string $option ): mixed => self::OPTIONS[ $option ]['autoload'] ?? $autoload,
			10,
			2
		);

		// Constructed here rather than in `Plugin`: the two endpoints exist only to serve this
		// screen's buttons, and they need nothing the screen does not already hold.
		( new SettingsAjax( $this->clients, $this->log ) )->register();
	}

	/**
	 * Register every option from the map.
	 */
	public function register_options(): void {
		$connection_settings = new ConnectionSettings( $this->credentials );
		foreach ( self::OPTIONS as $name => $spec ) {
			$sanitize = method_exists( $this, $spec['sanitize'] )
				? array( $this, $spec['sanitize'] )
				: $spec['sanitize'];

			// `autoload` is deliberately absent: `register_setting()` stores it in the
			// settings registry and nothing in core ever reads it back, so passing it here
			// would only look like it were doing something. The `wp_default_autoload_value`
			// filter in `register()` applies the value `self::OPTIONS` declares instead.
			$internal   = $this->is_internal_option( $name, $connection_settings );
			$registered = get_registered_settings()[ $name ]['group'] ?? null;
			if ( is_string( $registered ) ) {
				unregister_setting( $registered, $name );
			}

			register_setting(
				$internal ? self::PAGE . '_internal' : self::PAGE,
				$name,
				array(
					'type'              => $spec['type'],
					'default'           => $spec['default'],
					'sanitize_callback' => $sanitize,
					'show_in_rest'      => false,
				)
			);
		}
	}

	/**
	 * Whether an option stays out of the saved settings group.
	 *
	 * An option the form cannot post would be erased by options.php as an omitted input: the
	 * webhook token and endpoint id have no field at all, the signing secret has one only for
	 * OAuth sites — API-key sites read it from the API instead — and the legacy credentials
	 * follow the same rule as the connection fields.
	 *
	 * @param string             $name                Option name from `self::OPTIONS`.
	 * @param ConnectionSettings $connection_settings The connection field rules.
	 */
	private function is_internal_option( string $name, ConnectionSettings $connection_settings ): bool {
		return in_array( $name, array( self::OPTION_WEBHOOK_TOKEN, self::OPTION_WEBHOOK_ENDPOINT_ID, self::OPTION_OAUTH_CONNECTION, self::OPTION_AUTH_MODE, self::OPTION_REFRESH_LOCK ), true )
			|| ( self::OPTION_WEBHOOK_SECRET_ENC === $name && ! $this->credentials->uses_oauth() )
			|| ( ! $connection_settings->show_legacy_fields() && in_array( $name, array( self::OPTION_ACCOUNT_ID, self::OPTION_API_KEY ), true ) )
			|| ( self::OPTION_ACCOUNT_ID === $name && $this->credentials->is_account_id_constant() );
	}

	/**
	 * Add the top-level menu and its settings entry.
	 *
	 * The top-level entry is visible to anyone who may view documents so the document list
	 * and the send screen are reachable; the settings page itself stays on `manage_options`,
	 * enforced both by the submenu capability and by `render_page()`.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Assinafy', 'assinafy' ),
			__( 'Assinafy', 'assinafy' ),
			Capabilities::VIEW,
			self::PAGE,
			array( $this, 'render_page' ),
			'dashicons-edit-page',
			26
		);

		add_submenu_page(
			self::PAGE,
			__( 'Assinafy Settings', 'assinafy' ),
			__( 'Settings', 'assinafy' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Load the admin styles and the settings script on this screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'assinafy-admin',
			ASSINAFY_URL . 'assets/css/admin.css',
			array(),
			ASSINAFY_VERSION
		);

		wp_enqueue_script(
			'assinafy-settings',
			ASSINAFY_URL . 'assets/js/settings.js',
			array(),
			ASSINAFY_VERSION,
			true
		);

		wp_localize_script(
			'assinafy-settings',
			'assinafySettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'assinafy_admin' ),
				'i18n'    => array(
					'testing' => __( 'Testing…', 'assinafy' ),
					'working' => __( 'Working…', 'assinafy' ),
					'failed'  => __( 'Request failed.', 'assinafy' ),
					'expired' => __( 'This page has expired. Reload it and try again.', 'assinafy' ),
				),
			)
		);
	}

	/**
	 * Keep the stored credential when its field is submitted empty.
	 *
	 * Both encrypted-credential fields — the API key and the webhook signing secret — render
	 * with `value=""` so ciphertext never reaches the browser, which means every save that
	 * does not change the credential arrives blank.
	 *
	 * @param mixed $value     Incoming value.
	 * @param mixed $old_value Currently stored value.
	 *
	 * @return mixed
	 */
	public function keep_credential_when_blank( mixed $value, mixed $old_value ): mixed {
		return '' === $value || null === $value ? $old_value : $value;
	}

	/**
	 * Encrypt a submitted API key.
	 *
	 * @param mixed $value Submitted value.
	 */
	public function sanitize_api_key( mixed $value ): string {
		return $this->encrypt_credential( $value, self::OPTION_API_KEY );
	}

	/**
	 * Encrypt a submitted webhook signing secret.
	 *
	 * @param mixed $value Submitted value.
	 */
	public function sanitize_webhook_secret( mixed $value ): string {
		return $this->encrypt_credential( $value, self::OPTION_WEBHOOK_SECRET_ENC );
	}

	/**
	 * Encrypt a submitted credential for storage.
	 *
	 * Values that already decrypt under the current key are passed through untouched, so the
	 * option's sanitisation stays idempotent no matter which code path writes it. Without
	 * server key material the old value stays and the screen explains why.
	 *
	 * @param mixed  $value  Submitted value.
	 * @param string $option Option being sanitised.
	 */
	private function encrypt_credential( mixed $value, string $option ): string {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value || $this->credentials->is_encrypted( $value ) ) {
			return $value;
		}
		if ( ! $this->credentials->has_server_key_material() ) {
			add_settings_error( $option, 'assinafy_missing_key_material', Credentials::missing_key_material_message() );

			return (string) get_option( $option, '' );
		}

		return self::OPTION_WEBHOOK_SECRET_ENC === $option
			? SigningSecret::encrypt( $value, (string) self::get( self::OPTION_WEBHOOK_ENDPOINT_ID ) )
			: $this->credentials->encrypt( $value );
	}

	/**
	 * Drop the memoised client when one of the plugin's options is added, changed or removed.
	 *
	 * @param string $option Option that changed.
	 */
	public function maybe_reset_client( string $option ): void {
		if ( isset( self::OPTIONS[ $option ] ) ) {
			$this->clients->reset();
		}
	}

	/**
	 * Render the settings screen.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Assinafy settings.', 'assinafy' ) );
		}

		$api_key   = $this->credentials->uses_oauth() ? '' : $this->credentials->api_key();
		$key_error = $api_key instanceof WP_Error ? $api_key->get_error_message() : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Assinafy', 'assinafy' ); ?></h1>
			<?php settings_errors(); ?>
			<?php OAuthConnection::render_notice(); ?>

			<?php if ( '' !== $key_error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $key_error ); ?></p></div>
			<?php endif; ?>

			<form id="assinafy-oauth-start" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" target="_blank" rel="noopener">
				<input type="hidden" name="action" value="assinafy_oauth_start" />
				<?php wp_nonce_field( 'assinafy_oauth_start' ); ?>
			</form>
			<form id="assinafy-oauth-complete" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="assinafy_oauth_complete" />
				<?php wp_nonce_field( 'assinafy_oauth_complete' ); ?>
			</form>
			<form id="assinafy-oauth-disconnect" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="assinafy_oauth_disconnect" />
				<?php wp_nonce_field( 'assinafy_oauth_disconnect' ); ?>
			</form>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				( new ConnectionSettings( $this->credentials ) )->render( '' === $key_error && '' !== $api_key );
				( new WebhookSettings( $this->credentials ) )->render();
				$this->render_defaults_section();
				$this->render_data_section();
				submit_button();
				?>
			</form>

			<h2><?php esc_html_e( 'Recent activity', 'assinafy' ); ?></h2>
			<?php $this->render_log(); ?>
		</div>
		<?php
	}

	/**
	 * Render the per-send defaults: deadline, signer message, sender capability.
	 */
	private function render_defaults_section(): void {
		$sender_cap  = (string) self::get( self::OPTION_SENDER_CAP );
		$expiry_days = (int) self::get( self::OPTION_EXPIRY_DAYS );
		$message     = (string) self::get( self::OPTION_MESSAGE );
		?>
		<h2><?php esc_html_e( 'Defaults', 'assinafy' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="assinafy-expiry-days"><?php esc_html_e( 'Signature deadline', 'assinafy' ); ?></label>
				</th>
				<td>
					<input type="number" min="0" step="1" class="small-text" id="assinafy-expiry-days"
						name="<?php echo esc_attr( self::OPTION_EXPIRY_DAYS ); ?>"
						value="<?php echo esc_attr( (string) $expiry_days ); ?>" />
					<?php esc_html_e( 'days', 'assinafy' ); ?>
					<p class="description"><?php esc_html_e( 'Zero sends no deadline.', 'assinafy' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="assinafy-message"><?php esc_html_e( 'Message to signers', 'assinafy' ); ?></label>
				</th>
				<td>
					<textarea class="large-text" rows="3" id="assinafy-message"
						name="<?php echo esc_attr( self::OPTION_MESSAGE ); ?>"><?php echo esc_textarea( $message ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="assinafy-sender-cap"><?php esc_html_e( 'Capability required to send', 'assinafy' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" id="assinafy-sender-cap"
						name="<?php echo esc_attr( self::OPTION_SENDER_CAP ); ?>"
						value="<?php echo esc_attr( $sender_cap ); ?>" />
					<p class="description">
						<?php
						printf(
							/* translators: %s: default capability name. */
							esc_html__( 'Defaults to %s, which administrators and editors are granted on activation.', 'assinafy' ),
							'<code>' . esc_html( Capabilities::SEND ) . '</code>'
						);
						?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the uninstall data-retention choice.
	 */
	private function render_data_section(): void {
		$delete_data = (bool) self::get( self::OPTION_DELETE_DATA );
		?>
		<h2><?php esc_html_e( 'Data', 'assinafy' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'On uninstall', 'assinafy' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_DELETE_DATA ); ?>"
							value="1" <?php checked( $delete_data ); ?> />
						<?php esc_html_e( 'Delete the plugin settings and document records when the plugin is deleted', 'assinafy' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Documents already sent stay in your Assinafy account either way.', 'assinafy' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the event log, newest first.
	 */
	private function render_log(): void {
		$entries = array_reverse( $this->log->entries() );

		if ( array() === $entries ) {
			echo '<p>' . esc_html__( 'Nothing logged yet.', 'assinafy' ) . '</p>';

			return;
		}

		echo '<table class="widefat striped assinafy-log"><thead><tr>';
		echo '<th>' . esc_html__( 'When', 'assinafy' ) . '</th>';
		echo '<th>' . esc_html__( 'Event', 'assinafy' ) . '</th>';
		echo '<th>' . esc_html__( 'Detail', 'assinafy' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $entries as $entry ) {
			$context = is_array( $entry['context'] ?? null ) ? $entry['context'] : array();

			echo '<tr>';
			$logged_at = wp_date( 'Y-m-d H:i:s', (int) ( $entry['time'] ?? 0 ) );

			echo '<td>' . esc_html( false === $logged_at ? '—' : $logged_at ) . '</td>';
			echo '<td><code>' . esc_html( (string) ( $entry['event'] ?? '' ) ) . '</code></td>';
			echo '<td>' . esc_html( (string) wp_json_encode( $context ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
