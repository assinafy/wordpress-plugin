<?php
/**
 * The trust boundary on the document action handlers.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Admin\DocumentActions;
use Assinafy\WP\Admin\Notice;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Log;

/**
 * @covers \Assinafy\WP\Admin\DocumentActions
 */
final class DocumentActionsTest extends AssinafyTestCase {

	/**
	 * No invalid nonce, unauthorized user, or unrelated post can reach the account API.
	 *
	 * @dataProvider denied_actions
	 * @param string $action Handler action.
	 * @param string $role Current role.
	 * @param bool $valid_nonce Whether the request has its action-specific nonce.
	 * @param bool $document Whether the target post belongs to the plugin.
	 */
	public function test_denied_actions_never_reach_the_api( string $action, string $role, bool $valid_nonce, bool $document ): void {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		$post_id              = $document ? $this->create_document( '104618d0d63884bc446c534e5ff5' ) : self::factory()->post->create();
		$_POST['post']        = $post_id;
		$_REQUEST['_wpnonce'] = $valid_nonce ? wp_create_nonce( $action . '_' . $post_id ) : 'invalid';
		$handler              = new DocumentActions( new ClientFactory( new Credentials(), new Log() ), new DocumentRecord() );
		$method               = 'handle_' . substr( $action, strlen( 'assinafy_' ) );
		$this->expectException( \WPDieException::class );
		try {
			$handler->$method();
		} finally {
			$this->assertSame( array(), $this->requests );
			$_POST    = array();
			$_REQUEST = array();
		}
	}

	/**
	 * The cancel flash is printed by the screen its redirect lands on, and consumed there.
	 *
	 * `handle_cancel()` trashes the post and goes to the list screen, where there is no meta
	 * box to read the channel. Unread, the notice would surface on the next document opened
	 * within the transient's minute — one that was never cancelled.
	 */
	public function test_cancel_flash_renders_on_the_document_list_screen(): void {
		( new DocumentActions( new ClientFactory( new Credentials(), new Log() ), new DocumentRecord() ) )->register();
		Notice::set( DocumentActions::FLASH_PREFIX, 'success', 'Cancelled and deleted.' );

		$screen = get_current_screen();
		set_current_screen( 'edit-' . DocumentPostType::POST_TYPE );
		try {
			ob_start();
			do_action( 'admin_notices' );
			$first = (string) ob_get_clean();

			ob_start();
			do_action( 'admin_notices' );
			$second = (string) ob_get_clean();
		} finally {
			$GLOBALS['current_screen'] = $screen;
		}

		$this->assertStringContainsString( 'Cancelled and deleted.', $first );
		$this->assertStringNotContainsString( 'Cancelled and deleted.', $second );
	}

	/**
	 * @return array<string, array{string, string, bool, bool}> All action trust boundaries.
	 */
	public static function denied_actions(): array {
		$cases = array();
		foreach ( array( DocumentActions::ACTION_RESEND, DocumentActions::ACTION_EXTEND, DocumentActions::ACTION_RENAME, DocumentActions::ACTION_CANCEL ) as $action ) {
			$cases[ $action . ' invalid nonce' ]  = array( $action, 'administrator', false, true );
			$cases[ $action . ' unauthorized' ]   = array( $action, 'subscriber', true, true );
			$cases[ $action . ' unrelated post' ] = array( $action, 'administrator', true, false );
		}
		return $cases;
	}
}
