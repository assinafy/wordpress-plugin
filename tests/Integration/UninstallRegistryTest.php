<?php
/**
 * The uninstall routine against the option registry.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;

/**
 * The anti-drift test. `uninstall.php` walks `Settings::OPTIONS` rather than repeating the
 * list, and this is what keeps it that way: an option added to the registry and forgotten in
 * the uninstall routine would be left behind on every site that removed the plugin, and an
 * option deleted here but never registered would be somebody else's data.
 *
 * `uninstall.php` is not a class, so it is `include`d the way WordPress includes it, with
 * `WP_UNINSTALL_PLUGIN` defined. Everything it touches is inside the test transaction and is
 * rolled back afterwards.
 *
 * @covers \Assinafy\WP\Settings
 */
final class UninstallRegistryTest extends AssinafyTestCase {

	/**
	 * Returned by `get_option()` when the row is really gone, rather than when it merely
	 * holds a falsey value.
	 */
	private const ABSENT = '__assinafy_absent__';

	/**
	 * An option the plugin does not own. Nothing may remove it.
	 */
	private const FOREIGN_OPTION = 'assinafy_lookalike_from_another_plugin';

	/**
	 * Define the constant WordPress defines before it loads the file.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'assinafy/assinafy.php' );
		}
	}

	/**
	 * Every registered option goes, and nothing else does.
	 */
	public function test_uninstall_deletes_exactly_the_registered_options(): void {
		$this->assertNotEmpty( Settings::OPTIONS, 'An empty registry would make this test vacuous.' );

		$this->fill_every_store();

		$this->run_uninstall();

		foreach ( array_keys( Settings::OPTIONS ) as $option ) {
			$this->assertSame(
				self::ABSENT,
				get_option( $option, self::ABSENT ),
				$option . ' is registered in Settings::OPTIONS but survived uninstall.'
			);
		}

		$this->assertSame(
			'kept',
			get_option( self::FOREIGN_OPTION, self::ABSENT ),
			'Uninstall deleted an option the plugin does not own.'
		);
	}

	/**
	 * An opted-in uninstall revokes the OAuth grant before deleting it: once the plugin is
	 * gone, nothing is left that could end it.
	 */
	public function test_uninstall_revokes_the_oauth_connection_it_deletes(): void {
		update_option( Settings::OPTION_DELETE_DATA, true );
		( new Credentials() )->set_oauth_connection(
			array(
				'account_id'    => self::ACCOUNT_ID,
				'access_token'  => 'synthetic-access',
				'refresh_token' => 'synthetic-refresh',
				'expires_at'    => time() + 3600,
				'connected_at'  => time(),
				'scope'         => 'account:read documents:read documents:write webhooks:write',
			)
		);
		$this->fake_raw_response( '/oauth/revoke', 200, '' );

		$this->run_uninstall();

		$this->assertCount( 1, $this->requests );
		$this->assertStringEndsWith( '/v1/oauth/revoke', $this->requests[0]['url'] );
		$this->assertStringContainsString( 'token=synthetic-refresh', (string) $this->requests[0]['args']['body'] );
		$this->assertSame( self::ABSENT, get_option( Settings::OPTION_OAUTH_CONNECTION, self::ABSENT ) );
	}

	/**
	 * Uninstall cannot ask anyone to retry, so it waits for the refresh lock instead of deleting
	 * a grant mid-rotation, then revokes the token the database holds, not a cached older one.
	 */
	public function test_uninstall_waits_for_the_refresh_lock_and_revokes_the_latest_token(): void {
		update_option( Settings::OPTION_DELETE_DATA, true );
		$credentials = new Credentials();
		$connection  = array(
			'account_id'    => self::ACCOUNT_ID,
			'access_token'  => 'synthetic-access',
			'refresh_token' => 'synthetic-refresh',
			'expires_at'    => time() + 3600,
			'connected_at'  => time(),
			'scope'         => 'account:read documents:read documents:write webhooks:write',
		);
		$credentials->set_oauth_connection( $connection );
		// Another request rotated the grant behind this request's cache and holds the lock until
		// its lease ends at the next second.
		$connection['refresh_token'] = 'synthetic-rotated-refresh';
		global $wpdb;
		$wpdb->update(
			$wpdb->options,
			array( 'option_value' => $credentials->encrypt( (string) wp_json_encode( $connection ) ) ),
			array( 'option_name' => Settings::OPTION_OAUTH_CONNECTION )
		);
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => Settings::OPTION_REFRESH_LOCK,
				'option_value' => time() . ':refreshing-request',
				'autoload'     => 'off',
			)
		);
		$this->fake_raw_response( '/oauth/revoke', 200, '' );

		$this->run_uninstall();

		$this->assertCount( 1, $this->requests );
		$this->assertStringContainsString( 'token=synthetic-rotated-refresh', (string) $this->requests[0]['args']['body'] );
		$this->assertSame( self::ABSENT, get_option( Settings::OPTION_OAUTH_CONNECTION, self::ABSENT ) );
		$this->assertSame( self::ABSENT, get_option( Settings::OPTION_REFRESH_LOCK, self::ABSENT ) );
	}

	/**
	 * The event log is deleted alongside the registry. It is the one option the plugin owns
	 * that is not a setting, so it is named on its own line in `uninstall.php` and has to be
	 * asserted on its own line here.
	 */
	public function test_uninstall_deletes_the_event_log(): void {
		$this->fill_every_store();

		$this->run_uninstall();

		$this->assertSame( self::ABSENT, get_option( Log::OPTION, self::ABSENT ) );
	}

	/**
	 * The mirror records and the transients the plugin uses as guards go with them.
	 */
	public function test_uninstall_removes_the_records_and_the_transients(): void {
		$this->fill_every_store();

		$this->run_uninstall();

		$this->assertSame(
			array(),
			get_posts(
				array(
					'post_type'   => DocumentPostType::POST_TYPE,
					'post_status' => 'any',
					'fields'      => 'ids',
				)
			)
		);
		$this->assertFalse( get_transient( 'assinafy_send_lock_test' ) );
	}

	/**
	 * All local statuses are removed, including the trash that `any` omits.
	 */
	public function test_uninstall_removes_trashed_records_and_atomic_send_locks(): void {
		$this->fill_every_store();
		$post_id = $this->create_document( '204618d0d63884bc446c534e5ff5' );
		wp_trash_post( $post_id );
		add_option( 'assinafy_send_lock_test.lock', time(), '', false );

		$this->run_uninstall();

		$this->assertNull( get_post( $post_id ) );
		$this->assertFalse( get_option( 'assinafy_send_lock_test.lock' ) );
	}

	/**
	 * Retention filters cannot trap uninstall on the same full page forever.
	 */
	public function test_uninstall_pages_past_records_that_wordpress_retains(): void {
		update_option( Settings::OPTION_DELETE_DATA, true );
		$kept = self::factory()->post->create_many( 100, array( 'post_type' => DocumentPostType::POST_TYPE ) );
		$last = $this->create_document( '204618d0d63884bc446c534e5ff5' );
		$hold = static function ( $delete, $post ) use ( $kept ) {
			return in_array( $post->ID, $kept, true ) ? false : $delete;
		};
		add_filter( 'pre_delete_post', $hold, 10, 2 );
		try {
			$this->run_uninstall();
		} finally {
			remove_filter( 'pre_delete_post', $hold, 10 );
		}

		$this->assertNotNull( get_post( $kept[0] ) );
		$this->assertNull( get_post( $last ) );
	}

	/**
	 * Deletion is network-wide, while every site's data-retention choice stays local.
	 *
	 * @group ms-required
	 */
	public function test_multisite_uninstall_respects_each_sites_opt_in(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This case requires WordPress Multisite.' );
		}

		$this->fill_every_store();
		update_option( Settings::OPTION_DELETE_DATA, false );
		$origin  = get_current_blog_id();
		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );
		try {
			$this->fill_every_store();
		} finally {
			restore_current_blog();
		}

		$this->run_uninstall();

		$this->assertSame( $origin, get_current_blog_id() );
		$this->assertSame( self::ACCOUNT_ID, get_option( Settings::OPTION_ACCOUNT_ID ) );
		switch_to_blog( $site_id );
		try {
			$this->assertFalse( get_option( Settings::OPTION_ACCOUNT_ID ) );
			$this->assertFalse( get_transient( 'assinafy_send_lock_test' ) );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Nothing is deleted unless the site asked for it. The setting is off by default, and a
	 * plugin that wipes a workspace's signature history on a routine deactivate-and-remove
	 * would be unforgivable.
	 */
	public function test_uninstall_keeps_everything_when_the_site_did_not_opt_in(): void {
		$this->fill_every_store();

		update_option( Settings::OPTION_DELETE_DATA, false );

		$this->run_uninstall();

		$this->assertSame( self::ACCOUNT_ID, get_option( Settings::OPTION_ACCOUNT_ID, self::ABSENT ) );
		$this->assertNotSame( self::ABSENT, get_option( Log::OPTION, self::ABSENT ) );
		$this->assertCount(
			1,
			get_posts(
				array(
					'post_type'   => DocumentPostType::POST_TYPE,
					'post_status' => 'any',
					'fields'      => 'ids',
				)
			)
		);
	}

	/**
	 * The role grants go whichever way the site chose. The retention setting covers settings
	 * and document records, and a capability left in `wp_user_roles` would outlive the only
	 * code that can remove it.
	 */
	public function test_uninstall_revokes_the_capabilities_without_opt_in(): void {
		update_option( Settings::OPTION_DELETE_DATA, false );

		$role = get_role( 'administrator' );

		$this->assertNotNull( $role );
		$this->assertTrue( $role->has_cap( Capabilities::VIEW ) );

		$this->run_uninstall();

		$this->assertArrayNotHasKey( Capabilities::VIEW, $role->capabilities );
	}

	/**
	 * Write to every store the uninstall routine claims to clear, plus one that belongs to
	 * somebody else.
	 */
	private function fill_every_store(): void {
		foreach ( array_keys( Settings::OPTIONS ) as $option ) {
			update_option( $option, 'assinafy-test-value' );
		}

		update_option( Settings::OPTION_ACCOUNT_ID, self::ACCOUNT_ID );
		update_option( Settings::OPTION_DELETE_DATA, true );
		update_option( Log::OPTION, array( array( 'event' => 'sent' ) ), false );
		update_option( self::FOREIGN_OPTION, 'kept' );

		set_transient( 'assinafy_send_lock_test', 1, 300 );

		$this->create_document( '104618d0d63884bc446c534e5ff5' );
	}

	/**
	 * Run the file WordPress runs when the plugin is deleted.
	 *
	 * Reads immediately after deletion also verify that the Options API invalidates caches.
	 */
	private function run_uninstall(): void {
		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}
}
