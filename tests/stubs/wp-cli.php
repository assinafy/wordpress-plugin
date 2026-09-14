<?php
/**
 * The WP-CLI surface this plugin uses.
 *
 * `php-stubs/wp-cli-stubs` still caps at `php-stubs/wordpress-stubs ^6.0`, and this plugin
 * targets WordPress 7.1, so the packaged stubs cannot be installed alongside the WordPress
 * stubs it needs. Only the symbols `src/Cli.php` actually calls are declared here.
 *
 * The declarations serve two readers:
 *
 * - PHPStan resolves `WP_CLI` and `WP_CLI\Utils` from this file (`scanFiles`), because WP-CLI
 *   is an optional integration and `Cli.php` is registered behind a `class_exists()` guard.
 * - `tests/Integration/CliTest.php` loads it at runtime as a recording double, so a subcommand
 *   can be run in-process and its output asserted. Nothing else loads it, and it is not
 *   shipped — `.distignore` excludes `/tests`.
 *
 * The two behaviours that matter for the double are the ones that end a command: `error()`
 * halts WP-CLI, and `confirm()` halts it when the answer is no. Both are represented as an
 * `ExitException`, which is the exception WP-CLI itself throws when it is told to capture the
 * exit rather than call `exit`.
 *
 * @package Assinafy\WP
 */

namespace {

	/**
	 * WP-CLI's command runner.
	 */
	class WP_CLI {

		/**
		 * Everything printed, in order.
		 *
		 * @var array<int, array{level: string, message: string, items: array<int, mixed>}>
		 */
		public static $calls = array();

		/**
		 * Commands registered, keyed by name.
		 *
		 * @var array<string, callable|string>
		 */
		public static $commands = array();

		/**
		 * Register a command.
		 *
		 * @param string               $name     Command name.
		 * @param callable|string      $callable Implementation.
		 * @param array<string, mixed> $args     Registration arguments.
		 */
		public static function add_command( $name, $callable, $args = array() ): bool {
			unset( $args );

			self::$commands[ $name ] = $callable;

			return true;
		}

		/**
		 * Print an error and halt.
		 *
		 * @param string|WP_Error $message Message.
		 * @param bool|int        $exit    Exit code, or false to continue.
		 *
		 * @throws WP_CLI\ExitException When the command is meant to stop here.
		 *
		 * @return void
		 */
		public static function error( $message, $exit = true ) {
			$text = $message instanceof WP_Error ? $message->get_error_message() : (string) $message;

			self::record( 'error', $text );

			if ( false !== $exit ) {
				throw new WP_CLI\ExitException( $text );
			}
		}

		/**
		 * Print a line.
		 *
		 * @param string $message Message.
		 */
		public static function line( $message = '' ): void {
			self::record( 'line', (string) $message );
		}

		/**
		 * Print a success message.
		 *
		 * @param string $message Message.
		 */
		public static function success( $message ): void {
			self::record( 'success', (string) $message );
		}

		/**
		 * Print a warning.
		 *
		 * @param string $message Message.
		 */
		public static function warning( $message ): void {
			self::record( 'warning', (string) $message );
		}

		/**
		 * Ask for confirmation, halting when refused.
		 *
		 * `--yes` answers it without asking. Without it there is no terminal to read, which
		 * is the same situation as a refusal: the command stops.
		 *
		 * @param string               $question   Question.
		 * @param array<string, mixed> $assoc_args Parsed associative arguments.
		 *
		 * @throws WP_CLI\ExitException When the question was not pre-answered.
		 */
		public static function confirm( $question, $assoc_args = array() ): void {
			self::record( 'confirm', (string) $question );

			if ( ! isset( $assoc_args['yes'] ) ) {
				throw new WP_CLI\ExitException( (string) $question );
			}
		}

		/**
		 * Record one call.
		 *
		 * @param string             $level Call kind.
		 * @param string             $message Message printed.
		 * @param array<int, mixed>  $items Rows, for `format_items`.
		 */
		public static function record( $level, $message, $items = array() ): void {
			self::$calls[] = array(
				'level'   => (string) $level,
				'message' => (string) $message,
				'items'   => $items,
			);
		}

		/**
		 * Forget everything recorded. Called between tests.
		 */
		public static function reset(): void {
			self::$calls    = array();
			self::$commands = array();
		}

		/**
		 * Every message printed at one level.
		 *
		 * @param string $level Call kind.
		 *
		 * @return array<int, string>
		 */
		public static function messages( $level ): array {
			$messages = array();

			foreach ( self::$calls as $call ) {
				if ( $call['level'] === $level ) {
					$messages[] = $call['message'];
				}
			}

			return $messages;
		}

		/**
		 * The rows handed to the most recent `format_items()` call.
		 *
		 * @return array<int, mixed>
		 */
		public static function rows(): array {
			foreach ( array_reverse( self::$calls ) as $call ) {
				if ( 'format_items' === $call['level'] ) {
					return $call['items'];
				}
			}

			return array();
		}
	}

}

namespace WP_CLI {

	/**
	 * What WP-CLI throws instead of calling `exit` when it is capturing the exit.
	 */
	class ExitException extends \RuntimeException {
	}
}

namespace WP_CLI\Utils {

	/**
	 * Render rows in the requested format.
	 *
	 * @param string                                  $format Output format: table, csv, json, yaml, ids or count.
	 * @param array<int, array<string, mixed>>|object $items  Rows.
	 * @param array<int, string>|string               $fields Field names.
	 */
	function format_items( $format, $items, $fields ): void {
		\WP_CLI::record(
			'format_items',
			$format . ':' . ( is_array( $fields ) ? implode( ',', $fields ) : (string) $fields ),
			is_array( $items ) ? $items : array()
		);
	}
}
