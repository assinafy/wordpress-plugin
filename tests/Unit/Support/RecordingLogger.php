<?php
/**
 * PSR-3 logger that keeps what it was given.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit\Support;

use Assinafy\WP\Vendor\Psr\Log\AbstractLogger;

/**
 * Exists so a test can assert on what the transport logs — in particular that no credential,
 * body or request URI ever reaches a log line.
 */
final class RecordingLogger extends AbstractLogger {

	/**
	 * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
	 */
	public array $records = array();

	/**
	 * PSR-3 1.x declares `log()` without parameter types, so neither `$level` nor `$message`
	 * may be typed here: adding a parameter type an interface does not declare is a
	 * contravariance violation and fatals under `composer update --prefer-lowest`. The types
	 * live in the docblock, where PHPStan still reads them.
	 *
	 * @param mixed                $level   Log level.
	 * @param string|\Stringable   $message Log message.
	 * @param array<string, mixed> $context Structured context.
	 */
	public function log( $level, $message, array $context = array() ): void {
		$this->records[] = array(
			'level'   => (string) $level,
			'message' => (string) $message,
			'context' => $context,
		);
	}

	/**
	 * Everything logged, flattened to one searchable string.
	 */
	public function dump(): string {
		return (string) wp_json_encode( $this->records );
	}
}
