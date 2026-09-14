<?php
/**
 * The core readiness contract used by bundled and external adapters.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Integrations\Hook;
use Assinafy\WP\Plugin;
use ReflectionProperty;

/**
 * @covers \Assinafy\WP\Plugin
 */
final class BootstrapTest extends AssinafyTestCase {

	/**
	 * An adapter receives usable core services once, after the core hooks are attached.
	 */
	public function test_readiness_passes_services_once_after_core_registration(): void {
		$property = new ReflectionProperty( Plugin::class, 'instance' );
		$original = $property->getValue();
		$property->setValue( null, null );
		remove_all_actions( 'assinafy_ready' );
		$ready = array();
		add_action(
			'assinafy_ready',
			static function ( SendService $send, DocumentRecord $records ) use ( &$ready ): void {
				$ready[] = array(
					'send'        => $send,
					'records'     => $records,
					'post_type'   => post_type_exists( DocumentPostType::POST_TYPE ),
					'send_hook'   => has_action( Hook::ACTION ),
					'status_hook' => has_action( Plugin::CRON_HOOK ),
				);
			},
			10,
			2
		);

		try {
			Plugin::boot();
			Plugin::boot();
			$this->assertCount( 1, $ready );
			$this->assertInstanceOf( SendService::class, $ready[0]['send'] );
			$this->assertInstanceOf( DocumentRecord::class, $ready[0]['records'] );
			$this->assertTrue( $ready[0]['post_type'] );
			$this->assertTrue( $ready[0]['send_hook'] );
			$this->assertTrue( $ready[0]['status_hook'] );
		} finally {
			$property->setValue( null, $original );
		}
	}
}
