<?php
/**
 * WordPress activation, deactivation and multisite provisioning.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Assinafy\WP\Plugin;
use Assinafy\WP\Integrations\Hook;
use Assinafy\WP\Settings;

/**
 * @covers \Assinafy\WP\Plugin
 */
final class LifecycleTest extends AssinafyTestCase {

	/**
	 * Reactivation preserves the endpoint and creates only one recurring event.
	 */
	public function test_activation_is_idempotent_and_deactivation_retains_data(): void {
		Plugin::activate( false );
		$token = get_option( Settings::OPTION_WEBHOOK_TOKEN );
		$event = wp_next_scheduled( Plugin::CRON_HOOK );
		Plugin::activate( false );

		$this->assertIsString( $token );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $token );
		$this->assertSame( $token, get_option( Settings::OPTION_WEBHOOK_TOKEN ) );
		$this->assertNotFalse( $event );
		$this->assertSame( $event, wp_next_scheduled( Plugin::CRON_HOOK ) );

		$args = array( array( 'signers' => array( array( 'email' => 'jane@example.com' ) ) ) );
		wp_schedule_single_event( time() + 60, Hook::ACTION, $args );
		Plugin::deactivate( false );
		$this->assertFalse( wp_next_scheduled( Hook::ACTION, $args ) );
		$this->assertFalse( wp_next_scheduled( Plugin::CRON_HOOK ) );
		$this->assertSame( $token, get_option( Settings::OPTION_WEBHOOK_TOKEN ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::MANAGE ) );
	}

	/**
	 * Every existing site receives its own capabilities, token and cron event.
	 *
	 * @group ms-required
	 */
	public function test_network_activation_and_deactivation_visit_all_sites(): void {
		$this->require_multisite();
		$origin  = get_current_blog_id();
		$site_id = self::factory()->blog->create();
		Plugin::activate( true );
		$this->assertSame( $origin, get_current_blog_id() );
		$token = get_option( Settings::OPTION_WEBHOOK_TOKEN );

		switch_to_blog( $site_id );
		try {
			$this->assertTrue( get_role( 'editor' )->has_cap( Capabilities::SEND ) );
			$this->assertNotEmpty( get_option( Settings::OPTION_WEBHOOK_TOKEN ) );
			$this->assertNotSame( $token, get_option( Settings::OPTION_WEBHOOK_TOKEN ) );
			$this->assertNotFalse( wp_next_scheduled( Plugin::CRON_HOOK ) );
		} finally {
			restore_current_blog();
		}

		Plugin::deactivate( true );
		$this->assertSame( $origin, get_current_blog_id() );
		$this->assertFalse( wp_next_scheduled( Plugin::CRON_HOOK ) );
		switch_to_blog( $site_id );
		try {
			$this->assertFalse( wp_next_scheduled( Plugin::CRON_HOOK ) );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * New sites are provisioned only when the plugin is active on their network.
	 *
	 * @group ms-required
	 */
	public function test_network_active_plugin_provisions_new_sites(): void {
		$this->require_multisite();
		$active = get_site_option( 'active_sitewide_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array( plugin_basename( ASSINAFY_FILE ) => time() ) );
		try {
			$site_id = self::factory()->blog->create();
		} finally {
			update_site_option( 'active_sitewide_plugins', $active );
		}

		switch_to_blog( $site_id );
		try {
			$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::MANAGE ) );
			$this->assertNotEmpty( get_option( Settings::OPTION_WEBHOOK_TOKEN ) );
			$this->assertNotFalse( wp_next_scheduled( Plugin::CRON_HOOK ) );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Activating on one site must not grant plugin access on future unrelated sites.
	 *
	 * @group ms-required
	 */
	public function test_site_activation_does_not_provision_other_sites(): void {
		$this->require_multisite();
		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );
		try {
			$this->assertFalse( get_role( 'administrator' )->has_cap( Capabilities::MANAGE ) );
			$this->assertFalse( get_option( Settings::OPTION_WEBHOOK_TOKEN ) );
			$this->assertFalse( wp_next_scheduled( Plugin::CRON_HOOK ) );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * A site that remains individually active still needs its hourly reconciliation.
	 *
	 * @group ms-required
	 */
	public function test_network_deactivation_preserves_individually_active_sites(): void {
		$this->require_multisite();
		$site_id = self::factory()->blog->create();
		Plugin::activate( true );
		switch_to_blog( $site_id );
		try {
			update_option( 'active_plugins', array( plugin_basename( ASSINAFY_FILE ) ) );
		} finally {
			restore_current_blog();
		}

		Plugin::deactivate( true );
		switch_to_blog( $site_id );
		try {
			$this->assertNotFalse( wp_next_scheduled( Plugin::CRON_HOOK ) );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Even a failed lifecycle callback must leave WordPress on its original site.
	 *
	 * @group ms-required
	 */
	public function test_site_context_is_restored_when_a_callback_fails(): void {
		$this->require_multisite();
		$origin = get_current_blog_id();
		self::factory()->blog->create();
		try {
			Plugin::for_each_site(
				static function (): void {
					throw new \RuntimeException( 'Lifecycle failure.' );
				}
			);
			$this->fail( 'The callback exception should propagate.' );
		} catch ( \RuntimeException $error ) {
			$this->assertSame( 'Lifecycle failure.', $error->getMessage() );
		}

		$this->assertSame( $origin, get_current_blog_id() );
		$this->assertFalse( ms_is_switched() );
	}

	/**
	 * The regular WordPress suite also discovers these network-only cases.
	 */
	private function require_multisite(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This case requires WordPress Multisite.' );
		}
	}
}
