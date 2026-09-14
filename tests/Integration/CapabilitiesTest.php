<?php
/**
 * Capability tests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Assinafy\WP\Documents\DocumentPostType;
use WP_Post_Type;

/**
 * Declaring three capabilities is worth nothing on its own — what matters is that a check
 * against the document post type ends up requiring one of them. Every test here therefore
 * asks WordPress the question a screen asks (`user_can( $user, 'edit_post', $id )`) rather
 * than inspecting the role store and hoping the bridge is wired.
 *
 * @covers \Assinafy\WP\Capabilities
 */
final class CapabilitiesTest extends AssinafyTestCase {

	/**
	 * The three capabilities, for the tests that sweep all of them.
	 *
	 * @var array<int, string>
	 */
	private const ALL = array( Capabilities::SEND, Capabilities::MANAGE, Capabilities::VIEW );

	/**
	 * A mirror record every capability check is made against.
	 */
	private int $post_id;

	/**
	 * Create the record the checks address.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->post_id = $this->create_document( '104618b275d321f5de22240ebfda' );
	}

	/**
	 * Install puts each capability on the roles that should hold it, and on no others.
	 *
	 * @dataProvider role_grants
	 *
	 * @param string             $role     Role name.
	 * @param array<int, string> $expected Capabilities that role should hold.
	 */
	public function test_install_grants_the_expected_capabilities( string $role, array $expected ): void {
		$user = (int) self::factory()->user->create( array( 'role' => $role ) );

		foreach ( self::ALL as $capability ) {
			$this->assertSame(
				in_array( $capability, $expected, true ),
				user_can( $user, $capability ),
				$role . ' holds the wrong verdict for ' . $capability
			);
		}
	}

	/**
	 * The four default roles and what each is meant to hold.
	 *
	 * Sending spends account credit and puts a signer's address on an outbound email, so it
	 * stops at editor. Deleting destroys the local evidence of a signature, so it stops at
	 * administrator.
	 *
	 * @return array<string, array{0: string, 1: array<int, string>}>
	 */
	public function role_grants(): array {
		return array(
			'administrator' => array( 'administrator', array( Capabilities::SEND, Capabilities::MANAGE, Capabilities::VIEW ) ),
			'editor'        => array( 'editor', array( Capabilities::SEND, Capabilities::VIEW ) ),
			'author'        => array( 'author', array( Capabilities::VIEW ) ),
			'subscriber'    => array( 'subscriber', array() ),
		);
	}

	/**
	 * Install can run twice — the activation hook fires on every reactivation — without
	 * duplicating or dropping anything.
	 */
	public function test_install_is_idempotent(): void {
		Capabilities::install();
		Capabilities::install();

		$author = (int) self::factory()->user->create( array( 'role' => 'author' ) );

		$this->assertTrue( user_can( $author, Capabilities::VIEW ) );
		$this->assertFalse( user_can( $author, Capabilities::SEND ) );
	}

	/**
	 * The bridge is attached, so every meta-capability check on the post type runs through
	 * it.
	 */
	public function test_the_capability_bridge_is_attached(): void {
		$attached = false;

		foreach ( $GLOBALS['wp_filter']['map_meta_cap']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$attached = $attached
					|| ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Capabilities );
			}
		}

		$this->assertTrue( $attached, 'Without the bridge the plugin capabilities gate nothing.' );
	}

	/**
	 * Opening a record requires the view capability and nothing more. This is the assertion
	 * that proves the custom capabilities gate at all: the user starts with no plugin
	 * capability and is refused, and the single grant is what flips the verdict.
	 */
	public function test_viewing_a_record_gates_on_the_view_capability(): void {
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );

		$this->assertFalse( user_can( $user->ID, 'edit_post', $this->post_id ) );

		$user->add_cap( Capabilities::VIEW );

		$this->assertTrue( user_can( $user->ID, 'edit_post', $this->post_id ) );

		$user->remove_cap( Capabilities::VIEW );

		$this->assertFalse( user_can( $user->ID, 'edit_post', $this->post_id ) );
	}

	/**
	 * Deleting a record gates on manage, which view alone does not grant. A record is the
	 * local evidence that a signature was requested; whoever may read it may not destroy it.
	 */
	public function test_deleting_a_record_gates_on_the_manage_capability(): void {
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );

		$user->add_cap( Capabilities::VIEW );

		$this->assertFalse( user_can( $user->ID, 'delete_post', $this->post_id ) );

		$user->add_cap( Capabilities::MANAGE );

		$this->assertTrue( user_can( $user->ID, 'delete_post', $this->post_id ) );
	}

	/**
	 * An editor can send and read but cannot delete; an administrator can do all three.
	 */
	public function test_the_default_roles_get_the_verdicts_they_were_granted(): void {
		$editor        = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		$administrator = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertTrue( user_can( $editor, 'edit_post', $this->post_id ) );
		$this->assertFalse( user_can( $editor, 'delete_post', $this->post_id ) );

		$this->assertTrue( user_can( $administrator, 'edit_post', $this->post_id ) );
		$this->assertTrue( user_can( $administrator, 'delete_post', $this->post_id ) );
	}

	/**
	 * A subscriber is refused every way into a record.
	 *
	 * The native read capability also resolves to the plugin's view grant.
	 */
	public function test_a_subscriber_is_refused(): void {
		$subscriber = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertFalse( user_can( $subscriber, 'edit_post', $this->post_id ) );
		$this->assertFalse( user_can( $subscriber, 'read_post', $this->post_id ) );
		$this->assertFalse( user_can( $subscriber, 'delete_post', $this->post_id ) );
		$this->assertFalse( user_can( $subscriber, 'edit_posts' ) );

		foreach ( self::ALL as $capability ) {
			$this->assertFalse( user_can( $subscriber, $capability ) );
		}
	}

	/**
	 * Published mirror records still require the plugin's view permission.
	 */
	public function test_native_read_checks_require_the_view_capability(): void {
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		wp_update_post(
			array(
				'ID'          => $this->post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertFalse( user_can( $user, 'read_post', $this->post_id ) );
		$this->assertFalse( user_can( $user, 'read_assinafy_document', $this->post_id ) );
		$user->add_cap( Capabilities::VIEW );
		$this->assertTrue( user_can( $user, 'read_post', $this->post_id ) );
		$this->assertTrue( user_can( $user, 'read_assinafy_document', $this->post_id ) );
	}

	/**
	 * Holding `edit_posts` is not holding an Assinafy capability. Were the post type left at
	 * `capability_type => 'post'`, every contributor on the site would inherit access to
	 * every signature request.
	 */
	public function test_the_core_post_capabilities_do_not_open_a_record(): void {
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );

		foreach ( array( 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'delete_posts', 'delete_others_posts' ) as $capability ) {
			$user->add_cap( $capability );
		}

		$this->assertTrue( user_can( $user->ID, 'edit_posts' ) );
		$this->assertFalse( user_can( $user->ID, 'edit_post', $this->post_id ) );
		$this->assertFalse( user_can( $user->ID, 'delete_post', $this->post_id ) );
	}

	/**
	 * The primitive capabilities the post type expands into are granted to nobody directly;
	 * the bridge is the only thing that makes a check pass.
	 *
	 * @dataProvider post_type_primitives
	 *
	 * @param string $primitive Primitive capability the post type requires.
	 * @param string $mapped    Plugin capability it is rewritten to.
	 */
	public function test_a_post_type_primitive_is_rewritten_onto_a_plugin_capability( string $primitive, string $mapped ): void {
		$this->assertSame( array( $mapped ), ( new Capabilities() )->map_meta_cap( array( $primitive ), 'edit_post', 1, array( $this->post_id ) ) );

		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );

		$this->assertFalse( user_can( $user->ID, $primitive ) );

		$user->add_cap( $mapped );

		$this->assertTrue( user_can( $user->ID, $primitive ) );
	}

	/**
	 * Every primitive the post type's `capability_type` expands into, and the plugin
	 * capability it answers to.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function post_type_primitives(): array {
		$map = array(
			'read_assinafy_documents'             => Capabilities::VIEW,
			'read_private_assinafy_documents'     => Capabilities::VIEW,
			'edit_assinafy_documents'             => Capabilities::VIEW,
			'edit_others_assinafy_documents'      => Capabilities::VIEW,
			'edit_private_assinafy_documents'     => Capabilities::VIEW,
			'edit_published_assinafy_documents'   => Capabilities::VIEW,
			'create_assinafy_documents'           => Capabilities::SEND,
			'publish_assinafy_documents'          => Capabilities::SEND,
			'delete_assinafy_documents'           => Capabilities::MANAGE,
			'delete_others_assinafy_documents'    => Capabilities::MANAGE,
			'delete_private_assinafy_documents'   => Capabilities::MANAGE,
			'delete_published_assinafy_documents' => Capabilities::MANAGE,
		);

		$cases = array();

		foreach ( $map as $primitive => $mapped ) {
			$cases[ $primitive ] = array( $primitive, $mapped );
		}

		return $cases;
	}

	/**
	 * A capability the plugin does not own passes through the bridge untouched, so the filter
	 * cannot change the verdict on an unrelated post type.
	 */
	public function test_the_bridge_leaves_unrelated_capabilities_alone(): void {
		$caps = array( 'edit_others_posts', 'edit_published_posts' );

		$this->assertSame( $caps, ( new Capabilities() )->map_meta_cap( $caps, 'edit_post', 1, array( 1 ) ) );

		$editor = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		$page   = (int) self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertTrue( user_can( $editor, 'edit_post', $page ) );
	}

	/**
	 * `do_not_allow` survives the bridge. It is what the post type uses to deny record
	 * creation outright, and a rewrite would turn that denial into a grant.
	 */
	public function test_do_not_allow_is_not_rewritten(): void {
		$object = get_post_type_object( DocumentPostType::POST_TYPE );

		$this->assertInstanceOf( WP_Post_Type::class, $object );

		$denial = $object->cap->create_posts;

		$this->assertSame(
			array( $denial ),
			( new Capabilities() )->map_meta_cap( array( $denial ), 'create_posts', 1, array() )
		);

		$administrator = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertFalse( user_can( $administrator, $denial ) );
	}

	/**
	 * Uninstall clears the capabilities from every role the site has, not just the four the
	 * installer touched — a site that added its own role and granted it access must not keep
	 * a dangling capability after the plugin is gone.
	 */
	public function test_uninstall_removes_the_capabilities_from_every_role(): void {
		add_role( 'assinafy_tester', 'Assinafy Tester', array( 'read' => true ) );

		$custom = get_role( 'assinafy_tester' );

		$this->assertNotNull( $custom );

		foreach ( self::ALL as $capability ) {
			$custom->add_cap( $capability );
		}

		$tester = (int) self::factory()->user->create( array( 'role' => 'assinafy_tester' ) );

		$this->assertTrue( user_can( $tester, Capabilities::VIEW ) );

		Capabilities::uninstall();

		foreach ( array_keys( wp_roles()->roles ) as $role_name ) {
			$role = get_role( (string) $role_name );

			$this->assertNotNull( $role );

			foreach ( self::ALL as $capability ) {
				$this->assertArrayNotHasKey( $capability, $role->capabilities, $role_name . ' kept ' . $capability );
			}
		}

		remove_role( 'assinafy_tester' );
	}

	/**
	 * With the capabilities gone, nobody can reach a record — including the administrator who
	 * could a moment earlier.
	 */
	public function test_uninstall_closes_every_record(): void {
		$administrator = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertTrue( user_can( $administrator, 'edit_post', $this->post_id ) );

		Capabilities::uninstall();

		wp_cache_delete( $administrator, 'user_meta' );

		$this->assertFalse( user_can( get_userdata( $administrator ), 'edit_post', $this->post_id ) );
	}

	/**
	 * The post type this all gates is the document mirror, and nothing else.
	 */
	public function test_the_gated_post_type_is_the_document_mirror(): void {
		$this->assertSame( DocumentPostType::POST_TYPE, get_post_type( $this->post_id ) );
	}
}
