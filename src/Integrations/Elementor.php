<?php
/**
 * Elementor Pro Forms adapter registration.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Integrations;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\SendService;
use ElementorPro\Modules\Forms\Registrars\Form_Actions_Registrar;
use WP_Error;

/**
 * Join Elementor's action-registration event with the core's shared send service.
 *
 * Register this bridge while plugin files load. Elementor Pro can build its forms
 * registrar before Assinafy boots on init. Register the action during Elementor's own
 * event, then resolve the shared service when a validated submission runs the action.
 * The Action_Base subclass is only autoloaded after Elementor supplies its registrar.
 */
final class Elementor {

	private ?SendService $sender = null;

	/**
	 * Attach early enough to observe both hosts' initialization events.
	 */
	public function register(): void {
		add_action( 'elementor_pro/forms/actions/register', array( $this, 'actions_ready' ) );
		add_action( 'assinafy_ready', array( $this, 'core_ready' ) );
		add_filter( 'elementor/document/save/data', array( new ElementorPermissions(), 'before_save' ), 10, 2 );
	}

	/**
	 * Receive the native registrar when Elementor Pro Forms initializes.
	 *
	 * @param Form_Actions_Registrar $registrar Native form-action registrar.
	 */
	public function actions_ready( Form_Actions_Registrar $registrar ): void {
		$registrar->register( new ElementorAction( $this->send( ... ) ) );
	}

	/**
	 * Receive the shared service after the Assinafy core has booted.
	 *
	 * @param SendService $sender Core send service.
	 */
	public function core_ready( SendService $sender ): void {
		$this->sender = $sender;
	}

	/**
	 * Dispatch through the injected core service, after the normal WordPress boot.
	 *
	 * @param array<string, mixed> $args Validated form mapping.
	 * @return int|WP_Error Local document ID or send failure.
	 */
	public function send( array $args ): int|WP_Error {
		return null === $this->sender
			? new WP_Error( 'assinafy_not_ready', __( 'Assinafy is not ready to send documents.', 'assinafy' ) )
			: $this->sender->send( $args );
	}
}
