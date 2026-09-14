<?php
/**
 * Webhook account selection when wp-config.php overrides saved settings.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\SDK\Support\WebhookEventParser;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\StatusSync;
use Assinafy\WP\Log;
use Assinafy\WP\Webhook\Handler;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * REST dispatch is covered by WebhookRouteTest. Isolate the account-selection boundary
 * here because PHP constants cannot be undone between tests in a WordPress process.
 *
 * @covers \Assinafy\WP\Webhook\Handler
 */
final class WebhookAccountTest extends TestCase {

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_configured_account_constant_overrides_a_stale_saved_account(): void {
		define( 'ASSINAFY_ACCOUNT_ID', '104618a0000000000000000001' );
		update_option( Credentials::OPTION_ACCOUNT_ID, '104618a0000000000000000002' );

		$document_id                        = '104618b275d321f5de22240ebfda';
		$GLOBALS['assinafy_test_posts'][11] = array(
			'ID'        => 11,
			'post_type' => DocumentPostType::POST_TYPE,
		);
		update_post_meta( 11, DocumentRecord::META_DOCUMENT_ID, $document_id );

		$credentials = new Credentials();
		$log         = new Log();
		$handler     = new Handler( new StatusSync( new ClientFactory( $credentials, $log ), new DocumentRecord() ), $log );
		$selection   = new ReflectionMethod( Handler::class, 'document_to_refetch' );

		$this->assertSame(
			$document_id,
			$selection->invoke(
				$handler,
				new WebhookEventParser(),
				array(
					'account_id' => $credentials->account_id(),
					'object'     => array(
						'id'   => $document_id,
						'type' => 'Document',
					),
				),
				'signer_signed_document'
			)
		);
		$this->assertSame( array(), $log->entries() );
	}
}
