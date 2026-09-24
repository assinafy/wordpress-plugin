<?php
/**
 * Assinafy connection controls on the settings screen.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/** Keeps the OAuth and legacy credential fields together. */
final class ConnectionSettings {

	/** @param Credentials $credentials Credential store. */
	public function __construct( private readonly Credentials $credentials ) {
	}

	/**
	 * Render OAuth first, with existing API-key fields only for legacy and sandbox sites.
	 *
	 * @param bool $key_present Whether a usable API key is already stored.
	 */
	public function render( bool $key_present ): void {
		$environment = (string) Settings::get( Settings::OPTION_ENVIRONMENT );
		$connection  = $this->credentials->oauth_connection();
		$show_legacy = $this->show_legacy_fields();
		?>
		<h2><?php esc_html_e( 'Connection', 'assinafy' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Assinafy OAuth', 'assinafy' ); ?></th>
				<td>
					<?php $this->render_oauth_controls( $connection, $environment ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="assinafy-environment"><?php esc_html_e( 'Environment', 'assinafy' ); ?></label>
				</th>
				<td>
					<select name="<?php echo esc_attr( Settings::OPTION_ENVIRONMENT ); ?>" id="assinafy-environment">
						<option value="production" <?php selected( $environment, 'production' ); ?>>
							<?php esc_html_e( 'Production', 'assinafy' ); ?>
						</option>
						<option value="sandbox" <?php selected( $environment, 'sandbox' ); ?>>
							<?php esc_html_e( 'Sandbox', 'assinafy' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'Sandbox documents have no legal effect and are billed separately.', 'assinafy' ); ?></p>
				</td>
			</tr>
			<?php if ( $show_legacy ) : ?>
			<tr>
				<th scope="row">
					<label for="assinafy-account-id"><?php esc_html_e( 'Legacy account ID', 'assinafy' ); ?></label>
				</th>
				<td>
					<?php $this->render_account_id_field(); ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="assinafy-api-key"><?php esc_html_e( 'Legacy / Sandbox API key', 'assinafy' ); ?></label>
				</th>
				<td>
					<?php $this->render_api_key_field( $key_present ); ?>
					<p>
						<button type="button" class="button" id="assinafy-test-connection">
							<?php esc_html_e( 'Test connection', 'assinafy' ); ?>
						</button>
						<span id="assinafy-test-connection-result" class="assinafy-result" role="status" aria-live="polite"></span>
					</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/** @param array<string, mixed>|WP_Error|null $connection Current OAuth connection. */
	private function render_oauth_controls( array|WP_Error|null $connection, string $environment ): void {
		$unavailable = $this->oauth_unavailable_message( $environment );
		if ( $connection instanceof WP_Error ) {
			echo '<p class="description">' . esc_html( $connection->get_error_message() ) . '</p>';
		}
		if ( is_array( $connection ) ) {
			/* translators: %s: Assinafy workspace ID. */
			echo '<p>' . esc_html( sprintf( __( 'Production connected to workspace %s.', 'assinafy' ), $connection['account_id'] ) ) . '</p>';
		}
		if ( '' !== $unavailable ) {
			echo '<p class="description">' . esc_html( $unavailable ) . '</p>';
		}
		if ( '' === $unavailable ) {
			$label = is_array( $connection ) ? __( 'Reconnect Assinafy', 'assinafy' ) : __( 'Connect Assinafy', 'assinafy' );
			echo '<button type="submit" form="assinafy-oauth-start" class="button button-primary">' . esc_html( $label ) . '</button>';
			?>
			<p class="description"><?php esc_html_e( 'Authorization opens in a new tab. Copy the code shown by Assinafy and paste it here within 60 seconds.', 'assinafy' ); ?></p>
			<label for="assinafy-oauth-code"><?php esc_html_e( 'Authorization code', 'assinafy' ); ?></label><br>
			<input type="text" id="assinafy-oauth-code" name="code" form="assinafy-oauth-complete" class="regular-text code" autocomplete="off" spellcheck="false" required />
			<button type="submit" form="assinafy-oauth-complete" class="button"><?php esc_html_e( 'Complete connection', 'assinafy' ); ?></button>
			<?php
		}
		if ( is_array( $connection ) || $connection instanceof WP_Error ) {
			echo '<button type="submit" form="assinafy-oauth-disconnect" class="button">' . esc_html__( 'Disconnect', 'assinafy' ) . '</button>';
		}
	}

	/** Why the connect button cannot be used yet. */
	private function oauth_unavailable_message( string $environment ): string {
		if ( 'sandbox' === $environment ) {
			return __( 'OAuth is available in Production. Sandbox still uses an API key.', 'assinafy' );
		}
		if ( '' === OAuthConnection::client_id() ) {
			return __( 'The Assinafy WordPress OAuth app is awaiting registration.', 'assinafy' );
		}
		if ( ! is_ssl() ) {
			return __( 'Connect from an HTTPS WordPress admin page.', 'assinafy' );
		}
		if ( ! $this->credentials->has_server_key_material() ) {
			return Credentials::missing_key_material_message();
		}

		return '';
	}

	/** Keep the form and Settings API registration in step for legacy credentials. */
	public function show_legacy_fields(): bool {
		return ! $this->credentials->uses_oauth()
			&& ( 'sandbox' === Settings::get( Settings::OPTION_ENVIRONMENT )
				|| $this->credentials->is_api_key_constant()
				|| '' !== (string) get_option( Settings::OPTION_API_KEY, '' )
				|| '' !== (string) get_option( Settings::OPTION_ACCOUNT_ID, '' ) );
	}

	/**
	 * Render the account id input, read-only when a constant defines it.
	 *
	 * A constant wins over the option, so offering an editable field would invite an operator
	 * to change a value the site then ignores.
	 */
	private function render_account_id_field(): void {
		if ( $this->credentials->is_account_id_constant() ) {
			?>
			<input type="text" class="regular-text" id="assinafy-account-id"
				value="<?php echo esc_attr( $this->credentials->account_id() ); ?>" readonly />
			<p class="description"><?php esc_html_e( 'Defined by ASSINAFY_ACCOUNT_ID in wp-config.php.', 'assinafy' ); ?></p>
			<?php

			return;
		}
		?>
		<input type="text" class="regular-text" id="assinafy-account-id"
			name="<?php echo esc_attr( Settings::OPTION_ACCOUNT_ID ); ?>"
			value="<?php echo esc_attr( $this->credentials->account_id() ); ?>" />
		<?php
	}

	/**
	 * Render the API key input, read-only when a constant defines it.
	 *
	 * The stored key is never rendered back into the page in either branch: the field posts a
	 * replacement or is left blank to keep what is on file.
	 *
	 * @param bool $key_present Whether a usable API key is already stored.
	 */
	private function render_api_key_field( bool $key_present ): void {
		if ( $this->credentials->is_api_key_constant() ) {
			?>
			<input type="password" class="regular-text" id="assinafy-api-key" value="" readonly
				placeholder="<?php esc_attr_e( 'Defined in wp-config.php', 'assinafy' ); ?>" />
			<p class="description"><?php esc_html_e( 'Defined by ASSINAFY_API_KEY in wp-config.php.', 'assinafy' ); ?></p>
			<?php

			return;
		}
		?>
		<input type="password" class="regular-text" id="assinafy-api-key" autocomplete="off"
			name="<?php echo esc_attr( Settings::OPTION_API_KEY ); ?>" value=""
			placeholder="<?php echo esc_attr( $key_present ? __( 'Saved — leave blank to keep it', 'assinafy' ) : __( 'Paste your API key', 'assinafy' ) ); ?>" />
		<p class="description"><?php esc_html_e( 'Stored encrypted. Leave the field blank to keep the current key.', 'assinafy' ); ?></p>
		<?php if ( ! $this->credentials->has_server_key_material() ) : ?>
			<p class="description"><?php echo esc_html( Credentials::missing_key_material_message() ); ?></p>
		<?php endif; ?>
		<?php
	}
}
