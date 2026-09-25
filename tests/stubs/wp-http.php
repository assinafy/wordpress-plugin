<?php
/**
 * The two facts about WordPress's HTTP stack that decide whether an Assinafy request may leave
 * through a proxy: whether a proxy applies, and whether the cURL transport is available.
 *
 * The real `WP_HTTP_Proxy` reads the `WP_PROXY_*` constants, which a test could never undefine,
 * and `WpOrg\Requests\Transport\Curl::test()` reads the PHP build. The unit suite switches both
 * through globals instead: `$GLOBALS['assinafy_test_proxy']`, a proxy every host goes through,
 * and `$GLOBALS['assinafy_test_curl']`, false for a site without cURL, where Requests falls back
 * to streams. Loaded by `tests/bootstrap-unit.php` only; the integration suite has the real
 * classes, and nothing here ships — `.distignore` excludes `/tests`.
 *
 * @package Assinafy\WP
 */

namespace {

	/**
	 * The proxy settings WordPress hands its HTTP transports.
	 */
	class WP_HTTP_Proxy {

		/**
		 * Whether a proxy is configured.
		 */
		public function is_enabled(): bool {
			return (bool) ( $GLOBALS['assinafy_test_proxy'] ?? false );
		}

		/**
		 * Whether this URL goes through the proxy. The stub proxy takes every host.
		 *
		 * @param string $uri Request URL.
		 */
		public function send_through_proxy( $uri ): bool {
			return true;
		}
	}
}

namespace WpOrg\Requests\Transport {

	/**
	 * The transport Requests tries first.
	 */
	final class Curl {

		/**
		 * Whether cURL can carry the request.
		 *
		 * @param array<string, bool> $capabilities Required capabilities, such as ssl.
		 */
		public static function test( $capabilities = array() ): bool {
			return (bool) ( $GLOBALS['assinafy_test_curl'] ?? true );
		}
	}
}
