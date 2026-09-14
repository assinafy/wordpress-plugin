<?php
/**
 * Plugin capabilities.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

/**
 * The three capabilities the plugin gates on, and the bridge from the document post
 * type's own capabilities onto them.
 *
 * The post type declares `capability_type => array( 'assinafy_document', 'assinafy_documents' )`
 * with `map_meta_cap => true`, so WordPress expands every check into primitive capabilities
 * such as `edit_assinafy_documents` and `delete_others_assinafy_documents`. Those primitives
 * are granted to nobody; `map_meta_cap()` below rewrites them to the three capabilities that
 * are actually assigned to roles. A post type left at `capability_type => 'post'` would map
 * everything back onto `edit_posts` and the custom capabilities would gate nothing at all.
 */
final class Capabilities {

	public const SEND = 'assinafy_send';

	public const MANAGE = 'assinafy_manage';

	public const VIEW = 'assinafy_view';

	/**
	 * Capabilities granted per role at activation.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const ROLE_CAPS = array(
		'administrator' => array( self::SEND, self::MANAGE, self::VIEW ),
		'editor'        => array( self::SEND, self::VIEW ),
		'author'        => array( self::VIEW ),
	);

	/**
	 * Post-type primitive capability => plugin capability.
	 *
	 * Reading and opening a document record is `view`; creating one is `send`; destroying
	 * one is `manage`.
	 *
	 * @var array<string, string>
	 */
	private const POST_TYPE_CAPS = array(
		'read_assinafy_documents'             => self::VIEW,
		'read_private_assinafy_documents'     => self::VIEW,
		'edit_assinafy_documents'             => self::VIEW,
		'edit_others_assinafy_documents'      => self::VIEW,
		'edit_private_assinafy_documents'     => self::VIEW,
		'edit_published_assinafy_documents'   => self::VIEW,
		'create_assinafy_documents'           => self::SEND,
		'publish_assinafy_documents'          => self::SEND,
		'delete_assinafy_documents'           => self::MANAGE,
		'delete_others_assinafy_documents'    => self::MANAGE,
		'delete_private_assinafy_documents'   => self::MANAGE,
		'delete_published_assinafy_documents' => self::MANAGE,
	);

	/**
	 * Hook the capability bridge.
	 */
	public function register(): void {
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * Rewrite the post type's primitive capabilities onto the plugin's own.
	 *
	 * @param array<int, string> $caps    Primitive capabilities WordPress will require.
	 * @param string             $cap     Capability being checked.
	 * @param int                $user_id User being checked.
	 * @param array<int, mixed>  $args    Context, typically the post id.
	 *
	 * @return array<int, string>
	 */
	public function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		// The already-expanded primitive list is the only input that matters here.
		unset( $cap, $user_id, $args );

		foreach ( $caps as $index => $required ) {
			if ( isset( self::POST_TYPE_CAPS[ $required ] ) ) {
				$caps[ $index ] = self::POST_TYPE_CAPS[ $required ];
			}
		}

		return $caps;
	}

	/**
	 * Grant the capabilities to the default roles. Idempotent.
	 */
	public static function install(): void {
		foreach ( self::ROLE_CAPS as $role_name => $caps ) {
			$role = get_role( $role_name );

			if ( null === $role ) {
				continue;
			}

			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove the capabilities from every role, including roles a site added by hand.
	 */
	public static function uninstall(): void {
		$roles = wp_roles();

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( (string) $role_name );

			if ( null === $role ) {
				continue;
			}

			foreach ( array( self::SEND, self::MANAGE, self::VIEW ) as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
