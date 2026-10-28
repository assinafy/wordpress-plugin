<?php
/**
 * The webhook section of the settings screen.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Credentials;
use Assinafy\WP\Settings;
use Assinafy\WP\Webhook\Route;

/**
 * Renders the delivery toggle, this site's endpoint and the signing-secret control.
 *
 * Kept out of {@see Settings} for the same reason {@see ConnectionSettings} exists: the
 * option registry should not also carry a screen's worth of markup.
 */
final class WebhookSettings {

	/** @param Credentials $credentials Credential store. */
	public function __construct( private readonly Credentials $credentials ) {
	}

	/**
	 * Render the webhook toggle, this site's endpoint and the signing-secret row.
	 */
	public function render(): void {
		$webhook_url = Route::url();
		$webhook_on  = (bool) Settings::get( Settings::OPTION_WEBHOOK_ENABLED );
		$endpoint_id = (string) Settings::get( Settings::OPTION_WEBHOOK_ENDPOINT_ID );
		$has_secret  = '' !== (string) Settings::get( Settings::OPTION_WEBHOOK_SECRET_ENC );
		?>
		<h2><?php esc_html_e( 'Webhook', 'assinafy' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Status updates', 'assinafy' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_WEBHOOK_ENABLED ); ?>"
							value="1" <?php checked( $webhook_on ); ?> />
						<?php esc_html_e( 'Accept webhook deliveries from Assinafy', 'assinafy' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Document status is also reconciled hourly, so webhooks are an optimisation rather than a requirement.', 'assinafy' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="assinafy-webhook-url"><?php esc_html_e( 'Endpoint', 'assinafy' ); ?></label>
				</th>
				<td>
					<input type="text" class="large-text code" id="assinafy-webhook-url"
						value="<?php echo esc_attr( $webhook_url ); ?>" readonly />
					<p>
						<button type="button" class="button" id="assinafy-register-webhook">
							<?php esc_html_e( 'Register this site with Assinafy', 'assinafy' ); ?>
						</button>
						<span id="assinafy-register-webhook-result" class="assinafy-result" role="status" aria-live="polite"></span>
					</p>
					<p class="description"><?php esc_html_e( 'An Assinafy account has one webhook endpoint, or up to three on paid plans. Registering adds or updates this site\'s own endpoint and leaves any others untouched.', 'assinafy' ); ?></p>
					<?php if ( '' !== $endpoint_id ) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: webhook endpoint ID on the Assinafy account. */
								esc_html__( 'Registered endpoint: %s', 'assinafy' ),
								'<code>' . esc_html( $endpoint_id ) . '</code>'
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<?php $this->render_signing_secret_row( $has_secret ); ?>
		</table>
		<?php
	}

	/**
	 * Render the signing-secret row: a paste-in field for OAuth sites, a status line for
	 * API-key sites.
	 *
	 * API-key sites have no field because registration reads the endpoint's secret from the
	 * API, and a field in the saved group without a matching input would erase the stored
	 * secret on every settings save — the option is registered as internal for them. OAuth
	 * applications cannot re-read a secret; registration rotates a lost one. This field also
	 * restores a saved `whsec_` value when needed. The field always renders empty:
	 * ciphertext never reaches the browser, and a blank submission keeps the stored value.
	 *
	 * @param bool $has_secret Whether a secret is currently stored.
	 */
	private function render_signing_secret_row( bool $has_secret ): void {
		$oauth = $this->credentials->uses_oauth();
		?>
		<tr>
			<th scope="row">
				<?php if ( $oauth ) : ?>
					<label for="assinafy-webhook-secret"><?php esc_html_e( 'Signing secret', 'assinafy' ); ?></label>
				<?php endif; ?>
				<?php if ( ! $oauth ) : ?>
					<?php esc_html_e( 'Signing secret', 'assinafy' ); ?>
				<?php endif; ?>
			</th>
			<td>
				<?php if ( $oauth ) : ?>
					<input type="text" class="regular-text code" id="assinafy-webhook-secret" autocomplete="off" spellcheck="false"
						name="<?php echo esc_attr( Settings::OPTION_WEBHOOK_SECRET_ENC ); ?>" value=""
						placeholder="<?php echo esc_attr( $has_secret ? __( 'Saved — leave blank to keep it', 'assinafy' ) : __( 'Paste the whsec_ value', 'assinafy' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Registration stores the endpoint signing secret. OAuth cannot read it again, so registering this site again rotates a missing or unreadable secret. You can also paste a saved whsec_ value here. Leaving the field blank keeps the stored secret. Registered endpoints refuse unsigned or stale deliveries.', 'assinafy' ); ?></p>
				<?php endif; ?>
				<?php if ( ! $oauth ) : ?>
					<p class="description">
						<?php
						echo esc_html(
							$has_secret
								? __( 'Managed automatically: registration reads the endpoint\'s secret from the API. A signing secret is currently configured.', 'assinafy' )
								: __( 'Managed automatically: registration reads the endpoint\'s secret from the API. No signing secret is stored yet — register this site to fetch one.', 'assinafy' )
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
