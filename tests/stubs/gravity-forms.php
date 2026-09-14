<?php
/**
 * Documented Gravity Forms API contract doubles, NEVER packaged or host-certified.
 *
 * @package Assinafy\WP\Tests
 */

// phpcs:disable
class GFForms {
	public static function include_feed_addon_framework(): void {}
}
class GFAddOn {
	/** @var list<string> */
	public static array $registered = array();
	public static function register( string $class ): void { self::$registered[] = $class; }
}
class GFFeedAddOn extends GFAddOn {
	/** @var array<int, array<string, mixed>> */
	public static array $feeds = array();
	/** @var list<string> */
	public array $errors = array();
	/** @param array<string,mixed> $form @param array<array-key,mixed> $entry */
	public function get_field_value( array $form, array $entry, string $id ): mixed { return $entry[$id] ?? ''; }
	public function set_field_error( mixed $field, string $message ): void { $this->errors[] = $message; }
	/** @return array<int,array<string,mixed>> */
	public function get_feeds( int $form_id ): array { return array_values( array_filter( self::$feeds, static fn( array $feed ): bool => $feed['form_id'] === $form_id ) ); }
	/** @return array<string,mixed>|false */
	public function get_feed( int $id ): array|false { return self::$feeds[$id] ?? false; }
	public function save_entry_feed_status( mixed $result, int $entry_id, int $feed_id, int $form_id ): void {}
}
class GFAPI {
	/** @var array<int,array<array-key,mixed>> */
	public static array $entries = array();
	/** @var array<int,array<string,mixed>> */
	public static array $forms = array();
	/** @return array<array-key,mixed>|WP_Error */
	public static function get_entry( int $id ): array|WP_Error { return self::$entries[$id] ?? new WP_Error('missing'); }
	/** @return array<string,mixed>|false */
	public static function get_form( int $id ): array|false { return self::$forms[$id] ?? false; }
}
function gform_get_meta( int $id, string $key ): mixed { return get_option( 'assinafy_gf_contract_' . $id . '_' . $key, null ); }
function gform_update_meta( int $id, string $key, mixed $value, int $form_id = 0 ): void { update_option( 'assinafy_gf_contract_' . $id . '_' . $key, $value ); }
