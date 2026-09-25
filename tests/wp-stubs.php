<?php
/**
 * The WordPress surface the unit suite touches, reimplemented in memory.
 *
 * Only the functions the classes under test actually call are defined, and each keeps the
 * real function's contract for the inputs the plugin gives it — `wp_remote_retrieve_headers()`
 * really does hand back an object with `getAll()`, `wp_insert_post()`-style falsy returns are
 * preserved, and `WP_Error` is a real class rather than a marker interface.
 *
 * Loaded only when no WordPress test library is available. Under the integration suite the
 * real functions are in scope and this file is never read.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

use Assinafy\WP\Tests\Unit\Support\FakeHttp;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__ ) . '/' );

// The salts `Credentials` derives its encryption key from when no `ASSINAFY_ENCRYPTION_KEY`
// constant is set. Values are arbitrary test material, not anything a site would use.
defined( 'LOGGED_IN_KEY' ) || define( 'LOGGED_IN_KEY', 'unit-test-logged-in-key' );
defined( 'LOGGED_IN_SALT' ) || define( 'LOGGED_IN_SALT', 'unit-test-logged-in-salt' );

/**
 * The unit suite uses configured salts; core's generated-salt fallback is integration-tested.
 *
 * @param string $scheme Authentication scheme.
 */
function wp_salt( string $scheme = 'auth' ): string {
	return LOGGED_IN_KEY . LOGGED_IN_SALT;
}

/** @param mixed $value Value prepared for WordPress metadata storage. */
function wp_slash( mixed $value ): mixed {
	if ( is_array( $value ) ) {
		return array_map( 'wp_slash', $value );
	}
	return is_string( $value ) ? addslashes( $value ) : $value;
}

/** @param mixed $value Value after WordPress metadata unslashing. */
function wp_unslash( mixed $value ): mixed {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal stand-in for the WordPress error object.
	 */
	class WP_Error { // phpcs:ignore
		/** @var array<string, array<int, string>> */
		private array $errors = array();

		/** @var array<string, mixed> */
		private array $error_data = array();

		/**
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( string $code = '', string $message = '', $data = null ) {
			if ( '' === $code ) {
				return;
			}

			$this->errors[ $code ][] = $message;

			if ( null !== $data ) {
				$this->error_data[ $code ] = $data;
			}
		}

		/**
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function add( string $code, string $message = '', $data = null ): void {
			$this->errors[ $code ][] = $message;

			if ( null !== $data ) {
				$this->error_data[ $code ] = $data;
			}
		}

		/**
		 * @return string
		 */
		public function get_error_code() {
			$codes = array_keys( $this->errors );

			return $codes[0] ?? '';
		}

		/**
		 * @param string $code Error code.
		 * @return string
		 */
		public function get_error_message( string $code = '' ) {
			$code = '' === $code ? $this->get_error_code() : $code;

			return $this->errors[ $code ][0] ?? '';
		}

		/**
		 * @param string $code Error code.
		 * @return mixed
		 */
		public function get_error_data( string $code = '' ) {
			$code = '' === $code ? $this->get_error_code() : $code;

			return $this->error_data[ $code ] ?? null;
		}

		public function has_errors(): bool {
			return array() !== $this->errors;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Candidate.
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 */
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 */
	function esc_html__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url       URL to parse.
	 * @param int    $component Component constant.
	 * @return mixed
	 */
	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * @param array<string, scalar> $args Query arguments.
	 * @param string                $url  URL to extend.
	 */
	function add_query_arg( array $args, string $url ): string {
		$separator = str_contains( $url, '?' ) ? '&' : '?';

		return $url . $separator . http_build_query( $args );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Value to encode.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}

if ( ! function_exists( 'wp_check_filetype' ) ) {
	/**
	 * @param string $filename File name.
	 * @return array{ext: string|false, type: string|false}
	 */
	function wp_check_filetype( string $filename ): array {
		$known = array(
			'pdf' => 'application/pdf',
			'png' => 'image/png',
			'txt' => 'text/plain',
		);

		$extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );

		return array(
			'ext'  => isset( $known[ $extension ] ) ? $extension : false,
			'type' => $known[ $extension ] ?? false,
		);
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Registered actions: hook => list of [callback, priority]. Order and priority are enough
	 * for the transport's add/remove pair; nothing in the unit suite fires actions by priority.
	 *
	 * @var array<string, list<array{0: callable, 1: int}>>
	 */
	$GLOBALS['wp_stub_actions'] = array();

	/**
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted arguments.
	 */
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['wp_stub_actions'][ $hook ][] = array( $callback, $priority );

		return true;
	}

	/**
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority it was added with.
	 */
	function remove_action( string $hook, callable $callback, int $priority = 10 ): bool {
		foreach ( $GLOBALS['wp_stub_actions'][ $hook ] ?? array() as $index => $entry ) {
			if ( $entry[0] === $callback && $entry[1] === $priority ) {
				unset( $GLOBALS['wp_stub_actions'][ $hook ][ $index ] );
				$GLOBALS['wp_stub_actions'][ $hook ] = array_values( $GLOBALS['wp_stub_actions'][ $hook ] );

				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	/**
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args Request arguments.
	 * @return array<string, mixed>|WP_Error
	 */
	function wp_remote_request( string $url, array $args = array() ) {
		return FakeHttp::handle( $url, $args );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * @param array<string, mixed>|WP_Error $response Response.
	 * @return int|string
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) ? ( $response['response']['code'] ?? '' ) : '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_headers' ) ) {
	/**
	 * @param array<string, mixed>|WP_Error $response Response.
	 * @return mixed
	 */
	function wp_remote_retrieve_headers( $response ) {
		return is_array( $response ) ? ( $response['headers'] ?? array() ) : array();
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * @param array<string, mixed>|WP_Error $response Response.
	 */
	function wp_remote_retrieve_body( $response ): string {
		return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
	}
}

$GLOBALS['assinafy_test_options']    = array();
$GLOBALS['assinafy_test_transients'] = array();
$GLOBALS['assinafy_test_meta']       = array();
$GLOBALS['assinafy_test_posts']      = array();

/** Minimal option SQL used by OAuth: the refresh lock and conditional token writes. */
$GLOBALS['wpdb'] = new class() {
	public string $options = 'wp_options';

	/** @param mixed ...$arguments Prepared values. */
	public function prepare( string $query, mixed ...$arguments ): string {
		return (string) json_encode( array( strtok( $query, ' ' ), $arguments ) );
	}

	/** INSERT IGNORE adds a missing option; UPDATE and DELETE need the expected value to still exist. */
	public function query( string $prepared ): int {
		list( $verb, $values ) = json_decode( $prepared, true );
		if ( 'INSERT' === $verb ) {
			return add_option( $values[0], $values[1] ) ? 1 : 0;
		}
		$option   = 'UPDATE' === $verb ? $values[1] : $values[0];
		$expected = 'UPDATE' === $verb ? $values[2] : $values[1];
		if ( get_option( $option, '' ) !== $expected ) {
			return 0;
		}
		if ( 'UPDATE' === $verb ) {
			update_option( $option, $values[0] );
		} else {
			delete_option( $option );
		}

		return 1;
	}

	/** SELECT option_value by name. */
	public function get_var( string $prepared ): ?string {
		$option = json_decode( $prepared, true )[1][0];

		return isset( $GLOBALS['assinafy_test_options'][ $option ] ) ? (string) $GLOBALS['assinafy_test_options'][ $option ] : null;
	}
};

/** @param string $key Cache key. @param string $group Cache group. */
function wp_cache_delete( string $key, string $group = '' ): bool {
	return true;
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $option  Option name.
	 * @param mixed  $default_value Fallback.
	 * @return mixed
	 */
	function get_option( string $option, $default_value = false ) {
		return $GLOBALS['assinafy_test_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string    $option   Option name.
	 * @param mixed     $value    Value.
	 * @param bool|null $autoload Autoload flag, ignored here.
	 */
	function update_option( string $option, $value, $autoload = null ): bool {
		$GLOBALS['assinafy_test_options'][ $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * @param string    $option   Option name.
	 * @param mixed     $value    Value.
	 * @param string    $deprecated Unused legacy argument.
	 * @param bool|null $autoload Autoload flag.
	 */
	function add_option( string $option, $value = '', string $deprecated = '', $autoload = null ): bool {
		if ( array_key_exists( $option, $GLOBALS['assinafy_test_options'] ) ) {
			return false;
		}

		$GLOBALS['assinafy_test_options'][ $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * @param string $option Option name.
	 */
	function delete_option( string $option ): bool {
		unset( $GLOBALS['assinafy_test_options'][ $option ] );

		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @param string $transient Transient name.
	 * @return mixed
	 */
	function get_transient( string $transient ) {
		$entry = $GLOBALS['assinafy_test_transients'][ $transient ] ?? null;

		if ( null === $entry ) {
			return false;
		}

		if ( 0 !== $entry['expires'] && $entry['expires'] < time() ) {
			unset( $GLOBALS['assinafy_test_transients'][ $transient ] );

			return false;
		}

		return $entry['value'];
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param string $transient  Transient name.
	 * @param mixed  $value      Value.
	 * @param int    $expiration Lifetime in seconds.
	 */
	function set_transient( string $transient, $value, int $expiration = 0 ): bool {
		$GLOBALS['assinafy_test_transients'][ $transient ] = array(
			'value'   => $value,
			'expires' => 0 === $expiration ? 0 : time() + $expiration,
		);

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * @param string $transient Transient name.
	 */
	function delete_transient( string $transient ): bool {
		unset( $GLOBALS['assinafy_test_transients'][ $transient ] );

		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 * @param bool   $single  Return a single value.
	 * @return mixed
	 */
	function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
		$value = $GLOBALS['assinafy_test_meta'][ $post_id ][ $key ] ?? null;

		if ( $single ) {
			return $value ?? '';
		}

		return null === $value ? array() : array( $value );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 */
	function update_post_meta( int $post_id, string $key, $value ): bool {
		$GLOBALS['assinafy_test_meta'][ $post_id ][ $key ] = wp_unslash( $value );

		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 */
	function delete_post_meta( int $post_id, string $key ): bool {
		unset( $GLOBALS['assinafy_test_meta'][ $post_id ][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	/**
	 * @param string $field   Field name.
	 * @param int    $post_id Post id.
	 * @return mixed
	 */
	function get_post_field( string $field, int $post_id ) {
		return $GLOBALS['assinafy_test_posts'][ $post_id ][ $field ] ?? '';
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	/**
	 * @param array<string, mixed> $postarr Post fields.
	 * @return int
	 */
	function wp_update_post( array $postarr ): int {
		$post_id = (int) ( $postarr['ID'] ?? 0 );

		if ( 0 === $post_id ) {
			return 0;
		}

		$GLOBALS['assinafy_test_posts'][ $post_id ] = array_merge(
			$GLOBALS['assinafy_test_posts'][ $post_id ] ?? array(),
			$postarr
		);

		return $post_id;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Enough of `WP_Query` for the record lookups: post type, an exact meta match, ordering
	 * by id and a result limit. `meta_query` is not interpreted — the queue ordering it
	 * drives belongs to the integration suite, which runs against real SQL.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, int>
	 */
	function get_posts( array $args = array() ): array {
		$post_type = (string) ( $args['post_type'] ?? 'post' );
		$matches   = array();

		foreach ( $GLOBALS['assinafy_test_posts'] as $post_id => $post ) {
			if ( ( $post['post_type'] ?? 'post' ) !== $post_type ) {
				continue;
			}

			$meta_key = isset( $args['meta_key'] ) ? (string) $args['meta_key'] : '';

			if ( '' !== $meta_key && ! isset( $GLOBALS['assinafy_test_meta'][ $post_id ][ $meta_key ] ) ) {
				continue;
			}

			if ( isset( $args['meta_value'] )
				&& ( $GLOBALS['assinafy_test_meta'][ $post_id ][ $meta_key ] ?? null ) !== $args['meta_value'] ) {
				continue;
			}

			$matches[] = (int) $post_id;
		}

		if ( 'DESC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ) {
			rsort( $matches );
		} else {
			sort( $matches );
		}

		$matches = array_slice( $matches, (int) ( $args['offset'] ?? 0 ) );

		$limit = (int) ( $args['numberposts'] ?? 5 );

		return $limit < 0 ? $matches : array_slice( $matches, 0, $limit );
	}
}
