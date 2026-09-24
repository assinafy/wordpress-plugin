<?php
/**
 * Settings API persistence and multisite client isolation.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;
use Assinafy\WP\Webhook\Route;

/**
 * @covers \Assinafy\WP\Settings
 */
final class SettingsTest extends AssinafyTestCase {

	private Settings $settings;

	private ClientFactory $clients;

	/** @var array<string, mixed> Original settings registry. */
	private array $registered;

	/** @var array<string, mixed> Original options.php allowlist. */
	private array $allowed;

	/**
	 * Build a request's settings graph and preserve WordPress's global registry.
	 */
	public function set_up(): void {
		parent::set_up();
		$credentials      = new Credentials();
		$log              = new Log();
		$this->clients    = new ClientFactory( $credentials, $log );
		$this->settings   = new Settings( $credentials, $this->clients, $log );
		$this->registered = get_registered_settings();
		$this->allowed    = $GLOBALS['new_allowed_options'] ?? array();
		$this->settings->register();
	}

	/**
	 * Core restores hooks; restore the two registries those hooks consult as well.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_registered_settings'] = $this->registered;
		$GLOBALS['new_allowed_options']    = $this->allowed;
		parent::tear_down();
	}

	/**
	 * options.php submits null for registered fields missing from the form.
	 */
	public function test_saving_settings_preserves_the_webhook_token_and_blank_api_key(): void {
		$this->configure_plugin();
		$this->settings->register_options();
		$token   = Route::rotate_token();
		$api_key = get_option( Settings::OPTION_API_KEY );
		update_option( Settings::OPTION_WEBHOOK_ENABLED, true );
		update_option( Settings::OPTION_DELETE_DATA, true );
		$submitted = array(
			Settings::OPTION_ACCOUNT_ID  => self::ACCOUNT_ID,
			Settings::OPTION_API_KEY     => '',
			Settings::OPTION_ENVIRONMENT => 'sandbox',
			Settings::OPTION_EXPIRY_DAYS => '14',
			Settings::OPTION_MESSAGE     => '<b>Please sign.</b>',
			Settings::OPTION_SENDER_CAP  => Capabilities::SEND,
		);
		$allowed   = apply_filters( 'allowed_options', array() );
		$this->assertNotEmpty( $allowed[ Settings::PAGE ] );
		foreach ( $allowed[ Settings::PAGE ] as $option ) {
			update_option( $option, $submitted[ $option ] ?? null );
		}

		$this->assertSame( $token, get_option( Settings::OPTION_WEBHOOK_TOKEN ) );
		$this->assertSame( $api_key, get_option( Settings::OPTION_API_KEY ) );
		$this->assertSame( 'sandbox', Settings::get( Settings::OPTION_ENVIRONMENT ) );
		$this->assertSame( 14, Settings::get( Settings::OPTION_EXPIRY_DAYS ) );
		$this->assertSame( 'Please sign.', Settings::get( Settings::OPTION_MESSAGE ) );
		$this->assertFalse( (bool) Settings::get( Settings::OPTION_WEBHOOK_ENABLED ) );
		$this->assertFalse( (bool) Settings::get( Settings::OPTION_DELETE_DATA ) );
	}

	/**
	 * options.php saves without an autoload, which core would resolve to an autoloaded row.
	 */
	public function test_saving_the_api_key_keeps_it_out_of_alloptions(): void {
		$this->settings->register_options();

		update_option( Settings::OPTION_API_KEY, 'form-submitted-key' );

		$this->assertNotSame( '', (string) get_option( Settings::OPTION_API_KEY ) );
		$this->assertArrayNotHasKey( Settings::OPTION_API_KEY, wp_load_alloptions( true ) );
	}

	/** A settings save cannot erase or autoload the encrypted OAuth connection. */
	public function test_settings_save_preserves_oauth_tokens(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection(
			array(
				'account_id'    => self::ACCOUNT_ID,
				'access_token'  => 'synthetic-access',
				'refresh_token' => 'synthetic-refresh',
				'expires_at'    => time() + 3600,
				'connected_at'  => time(),
				'scope'         => 'account:read documents:read documents:write webhooks:write',
			)
		);
		$before = get_option( Settings::OPTION_OAUTH_CONNECTION );
		$this->settings->register_options();
		$allowed = apply_filters( 'allowed_options', array() );
		$this->assertNotContains( Settings::OPTION_OAUTH_CONNECTION, $allowed[ Settings::PAGE ] );
		$this->assertNotContains( Settings::OPTION_AUTH_MODE, $allowed[ Settings::PAGE ] );
		$this->assertNotContains( Settings::OPTION_API_KEY, $allowed[ Settings::PAGE ] );
		$this->assertNotContains( Settings::OPTION_ACCOUNT_ID, $allowed[ Settings::PAGE ] );
		foreach ( $allowed[ Settings::PAGE ] as $option ) {
			update_option( $option, get_option( $option ) );
		}
		$this->assertSame( $before, get_option( Settings::OPTION_OAUTH_CONNECTION ) );
		$this->assertArrayNotHasKey( Settings::OPTION_OAUTH_CONNECTION, wp_load_alloptions( true ) );
	}

	/** A new HTTPS production site can start OAuth without exposing legacy credential fields. */
	public function test_new_production_settings_show_oauth_connect(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$previous_https   = $_SERVER['HTTPS'] ?? null;
		$_SERVER['HTTPS'] = 'on';
		ob_start();
		try {
			$this->settings->render_page();
			$html = (string) ob_get_contents();
		} finally {
			ob_end_clean();
			if ( null === $previous_https ) {
				unset( $_SERVER['HTTPS'] );
			} else {
				$_SERVER['HTTPS'] = $previous_https;
			}
		}

		$this->assertStringContainsString( 'form="assinafy-oauth-start"', $html );
		$this->assertStringContainsString( 'target="_blank" rel="noopener"', $html );
		$this->assertStringContainsString( 'form="assinafy-oauth-complete"', $html );
		$this->assertStringNotContainsString( 'name="assinafy_api_key_enc"', $html );
	}

	/**
	 * The same request-scoped factory must use the current site's account and key.
	 *
	 * @group ms-required
	 */
	public function test_switching_sites_resets_cached_credentials(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This case requires WordPress Multisite.' );
		}
		$this->configure_plugin();
		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );
		try {
			update_option( Settings::OPTION_ACCOUNT_ID, '204618a0000000000000000001' );
			update_option( Settings::OPTION_ENVIRONMENT, 'sandbox' );
			( new Credentials() )->set_api_key( 'second-site-test-key' );
		} finally {
			restore_current_blog();
		}

		$first = $this->clients->client();
		$this->assertNotNull( $first );
		$this->assertSame( self::ACCOUNT_ID, $first->getConfig()->getAccountId() );
		switch_to_blog( $site_id );
		try {
			$second = $this->clients->client();
			$this->assertNotNull( $second );
			$this->assertNotSame( $first, $second );
			$this->assertSame( '204618a0000000000000000001', $second->getConfig()->getAccountId() );
			$this->assertSame( 'second-site-test-key', $second->getConfig()->getApiKey() );
			$this->assertStringContainsString( 'sandbox', $second->getConfig()->getBaseUrl() );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( self::ACCOUNT_ID, $this->clients->client()->getConfig()->getAccountId() );
	}
}
