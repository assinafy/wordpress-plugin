<?php
/**
 * Contact Form 7 editor and accepted-submission adapter.
 *
 * @package Assinafy\WP\Addons\ContactForm7
 */

declare(strict_types=1);

namespace Assinafy\WP\Addons\ContactForm7;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use WPCF7_ContactForm;
use WPCF7_Submission;

/** Only accepted, non-demo submissions can reserve a signature request. */
final class Adapter {

	public const SETTINGS = FormSettings::SETTINGS;
	private readonly FormSettings $settings;
	private readonly Requests $requests;

	/** @var \WeakMap<WPCF7_Submission, int> A CF7 submission is request-local, not an entry. */
	private \WeakMap $handled;

	/**
	 * @param SendService $send Shared core sender.
	 * @param DocumentRecord $records Shared core records.
	 */
	public function __construct( SendService $send, DocumentRecord $records ) {
		$this->requests = new Requests( $send, $records );
		$this->handled  = new \WeakMap();
		$this->settings = new FormSettings();
	}

	/** Attach to native host lifecycle hooks after core readiness. */
	public function register(): void {
		$this->requests->register();
		$this->settings->register();
		add_action( 'wpcf7_submit', array( $this, 'submitted' ), 10, 2 );
	}

	/**
	 * Run after CF7 validation, acceptance, spam checks and mail success. No browser hook.
	 *
	 * @param WPCF7_ContactForm $form Submitted form.
	 * @param array<string, mixed> $result CF7 outcome, independent of the signature outcome.
	 */
	public function submitted( WPCF7_ContactForm $form, array $result ): void {
		$submission = $this->accepted( $form, $result );
		if ( null === $submission || $submission->get_meta( 'do_not_store' ) ) {
			return;
		}
		$settings = $this->settings( $form );
		if ( empty( $settings['enabled'] ) || ! $this->settings->valid_settings( $form, $settings ) ) {
			return;
		}
		if ( '' !== ( $settings['consent'] ?? '' ) && array( '1' ) !== $submission->get_posted_data( $settings['consent'] ) ) {
			return;
		}
		$signer = $this->signer( $submission, $settings );
		if ( null === $signer ) {
			update_post_meta( $form->id(), FormSettings::ERROR, __( 'A successful submission had no usable mapped signer name or email. No signature was requested.', 'assinafy-contact-form-7' ) );
			return;
		}
		$args                         = array(
			'attachment_id' => $settings['attachment_id'],
			'signers'       => array( $signer ),
			'message'       => $settings['message'] ?? '',
		);
		$id                           = $this->requests->create( (int) $form->id(), $args );
		$this->handled[ $submission ] = 0;
		if ( is_wp_error( $id ) ) {
			update_post_meta( $form->id(), FormSettings::ERROR, $id->get_error_message() );
			return;
		}
		$this->handled[ $submission ] = $id;
		$this->requests->send( $id );
	}

	/**
	 * @param WPCF7_ContactForm $form Host form.
	 * @param array<string, mixed> $result Host result.
	 * @return WPCF7_Submission|null Eligible request-local submission.
	 */
	private function accepted( WPCF7_ContactForm $form, array $result ): ?WPCF7_Submission {
		$submission = WPCF7_Submission::get_instance();
		if ( 'mail_sent' !== ( $result['status'] ?? '' ) || ! empty( $result['demo_mode'] ) || $form->in_demo_mode()
			|| ! $submission instanceof WPCF7_Submission || ! $submission->is( 'mail_sent' )
			|| $submission->get_contact_form()->id() !== $form->id() || isset( $this->handled[ $submission ] ) ) {
			return null;
		}
		return $submission;
	}

	/**
	 * @param WPCF7_Submission $submission Accepted host submission.
	 * @param array<string, mixed> $settings Field mapping.
	 * @return array{full_name: string, email: string}|null Usable mapped identity.
	 */
	private function signer( WPCF7_Submission $submission, array $settings ): ?array {
		$name  = $submission->get_posted_data( $settings['name'] );
		$email = $submission->get_posted_data( $settings['email'] );
		if ( ! is_string( $name ) || '' === trim( $name ) || ! is_string( $email ) || ! is_email( $email ) ) {
			return null;
		}
		return array(
			'full_name' => sanitize_text_field( $name ),
			'email'     => $email,
		);
	}

	/**
	 * @param WPCF7_ContactForm $form Host form.
	 * @return array<string, mixed> Adapter-owned configuration.
	 */
	public function settings( WPCF7_ContactForm $form ): array {
		return $this->settings->settings( $form );
	}
}
