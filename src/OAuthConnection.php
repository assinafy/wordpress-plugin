<?php
/**
 * Assinafy OAuth connection for this WordPress site.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Resources\OAuthResource;
use Assinafy\WP\Admin\Notice;
use WP_Error;

/**
 * Starts browser consent and exchanges the code pasted by the WordPress admin.
 * The hosted callback sees the code but never the PKCE verifier or tokens.
 */
final class OAuthConnection {

	public const REDIRECT_URI = 'https://integrations.assinafy.com.br/wordpress/oauth-callback';

	private const START_URI = 'https://integrations.assinafy.com.br/wordpress/oauth-start';

	private const TRANSACTION_TTL = 600;

	private const NOTICE = 'assinafy_oauth_notice_';

	/** @param Credentials $credentials Encrypted credential store. */
	public function __construct( private readonly Credentials $credentials ) {
	}

	/** The bundled public client ID, optionally overridden in wp-config.php. */
	public static function client_id(): string {
		return defined( 'ASSINAFY_OAUTH_CLIENT_ID' ) && is_string( constant( 'ASSINAFY_OAUTH_CLIENT_ID' ) )
			? (string) constant( 'ASSINAFY_OAUTH_CLIENT_ID' )
			: '';
	}

	/** Register admin-only start, completion, and disconnect handlers. */
	public function register(): void {
		add_action( 'admin_post_assinafy_oauth_start', array( $this, 'start' ) );
		add_action( 'admin_post_assinafy_oauth_complete', array( $this, 'complete' ) );
		add_action( 'admin_post_assinafy_oauth_disconnect', array( $this, 'disconnect' ) );
	}

	/** Start OAuth from the settings screen. */
	public function start(): void {
		$this->require_admin();
		check_admin_referer( 'assinafy_oauth_start' );

		if ( '' === self::client_id() || 'production' !== Settings::get( Settings::OPTION_ENVIRONMENT ) || ! is_ssl() ) {
			$this->finish( 'error', __( 'OAuth requires a registered WordPress app and an HTTPS production site.', 'assinafy' ) );
		}
		if ( ! $this->credentials->has_server_key_material() ) {
			$this->finish( 'error', Credentials::missing_key_material_message() );
		}

		try {
			$transaction = ( new OAuthTokens( $this->credentials ) )->oauth()->startAuthorization(
				self::REDIRECT_URI,
				array(
					OAuthResource::SCOPE_ACCOUNT_READ,
					OAuthResource::SCOPE_DOCUMENTS_READ,
					OAuthResource::SCOPE_DOCUMENTS_WRITE,
					OAuthResource::SCOPE_WEBHOOKS_WRITE,
					OAuthResource::SCOPE_OFFLINE_ACCESS,
				)
			);
			$stored      = set_transient(
				self::transaction_key(),
				$transaction,
				self::TRANSACTION_TTL
			);
			if ( ! $stored ) {
				throw new \RuntimeException( 'Assinafy OAuth transaction could not be stored' );
			}

			$url = self::start_url( $transaction );
		} catch ( \Throwable $e ) {
			$this->finish( 'error', __( 'Could not start the Assinafy connection. Try again.', 'assinafy' ) );
		}

		wp_redirect( $url, 303, 'Assinafy' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fixed Assinafy HTTPS host.
		exit;
	}

	/** @param array<string, string> $transaction Authorization request and state. */
	private static function start_url( array $transaction ): string {
		return self::START_URI . '?' . http_build_query(
			array(
				'state'             => $transaction['state'],
				'authorization_url' => $transaction['authorization_url'],
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/** Complete consent with the short-lived code copied from Assinafy. */
	public function complete(): void {
		$this->require_admin();
		check_admin_referer( 'assinafy_oauth_complete' );
		if ( 'production' !== Settings::get( Settings::OPTION_ENVIRONMENT ) || ! is_ssl() ) {
			$this->finish( 'error', __( 'OAuth requires a registered WordPress app and an HTTPS production site.', 'assinafy' ) );
		}
		if ( ! $this->credentials->has_server_key_material() ) {
			$this->finish( 'error', Credentials::missing_key_material_message() );
		}
		$code        = $this->posted_code();
		$transaction = $this->consume_transaction();

		try {
			$token_manager = new OAuthTokens( $this->credentials );
			$oauth         = $token_manager->oauth();
			$tokens        = $oauth->exchangeCode( $code, $transaction );
			$previous      = $this->credentials->oauth_connection();
			$token_manager->save_tokens( $tokens );
			$old_revoked = $this->revoke_old_workspace_if_switched( $oauth, $previous );
			update_option( Settings::OPTION_ENVIRONMENT, 'production' );
		} catch ( \Throwable $e ) {
			$this->finish( 'error', __( 'Assinafy could not complete the connection. Try again.', 'assinafy' ) );
		}

		$this->finish(
			$old_revoked ? 'success' : 'error',
			$old_revoked
				? __( 'Assinafy is connected with OAuth.', 'assinafy' )
				: __( 'Assinafy is connected, but the previous workspace could not be revoked. Revoke it in Assinafy Connected Apps.', 'assinafy' )
		);
	}

	/** @param array<string, mixed>|WP_Error|null $previous Connection before consent. */
	private function revoke_old_workspace_if_switched( OAuthResource $oauth, array|WP_Error|null $previous ): bool {
		$current = $this->credentials->oauth_connection();
		// A different workspace has a separate grant; same-workspace consent may reuse one.
		if ( ! is_array( $previous ) || ! is_array( $current ) || $previous['account_id'] === $current['account_id'] ) {
			return true;
		}

		try {
			$oauth->revoke( $previous['refresh_token'], OAuthResource::TOKEN_TYPE_HINT_REFRESH );
			return true;
		} catch ( \Throwable $e ) {
			( new Log() )->add( 'oauth_previous_workspace_revoke_failed' );
			return false;
		}
	}

	/** @return array<string, mixed> Single-use PKCE transaction for the current admin. */
	private function consume_transaction(): array {
		$key     = self::transaction_key();
		$pending = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $pending ) || ! is_string( $pending['code_verifier'] ?? null ) || ! is_string( $pending['redirect_uri'] ?? null ) ) {
			$this->finish( 'error', __( 'The Assinafy connection expired or was invalid. Try again.', 'assinafy' ) );
		}

		/** @var array<string, mixed> $pending */
		return $pending;
	}

	/** Read an opaque code without changing valid percent sequences. */
	private function posted_code(): string {
		$raw  = wp_unslash( $_POST['code'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- complete() verifies the nonce; opaque codes are validated below.
		$code = is_string( $raw ) ? $raw : '';
		if ( '' === $code || strlen( $code ) > 2048 || 1 !== preg_match( '/^[\x21-\x7E]+$/D', $code ) ) {
			$this->finish( 'error', __( 'The Assinafy connection code was invalid. Try again.', 'assinafy' ) );
		}

		return $code;
	}

	/** Revoke the current connection from a nonce-protected admin form. */
	public function disconnect(): void {
		$this->require_admin();
		check_admin_referer( 'assinafy_oauth_disconnect' );
		$result = $this->disconnect_current();
		if ( 'local_failed' === $result ) {
			$this->finish( 'error', __( 'Assinafy could not remove the connection from this site. Try again.', 'assinafy' ) );
		}
		$this->finish(
			'success' === $result ? 'success' : 'error',
			'success' === $result
				? __( 'Assinafy has been disconnected.', 'assinafy' )
				: __( 'This site is disconnected, but Assinafy could not confirm revocation. Revoke the app in Assinafy Connected Apps.', 'assinafy' )
		);
	}

	/** Revoke remotely when possible, then remove the local grant even if revocation fails. */
	private function disconnect_current(): string {
		$connection = $this->credentials->oauth_connection();
		$revoked    = null === $connection;
		if ( is_array( $connection ) ) {
			try {
				( new OAuthTokens( $this->credentials ) )->oauth()->revoke( $connection['refresh_token'], OAuthResource::TOKEN_TYPE_HINT_REFRESH );
				$revoked = true;
			} catch ( \Throwable $e ) {
				( new Log() )->add( 'oauth_disconnect_revoke_failed' );
			}
		}

		if ( ! $this->credentials->clear_oauth_connection() ) {
			return 'local_failed';
		}

		return $revoked ? 'success' : 'remote_failed';
	}

	/** Keep each admin's pending verifier separate on this WordPress site. */
	private static function transaction_key(): string {
		return 'assinafy_oauth_pending_' . get_current_user_id();
	}

	/** Enforce the same capability on every browser-facing action. */
	private function require_admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Assinafy settings.', 'assinafy' ), '', array( 'response' => 403 ) );
		}
	}

	/** @param string $type success or error. @param string $message Translated notice. */
	private function finish( string $type, string $message ): never {
		Notice::set( self::NOTICE, $type, $message );
		wp_safe_redirect( admin_url( 'admin.php?page=' . Settings::PAGE ) );
		exit;
	}

	/** Print the result of the last OAuth action on the settings screen. */
	public static function render_notice(): void {
		Notice::render( self::NOTICE, 'is-dismissible' );
	}
}
