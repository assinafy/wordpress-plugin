<?php
/**
 * Authorize changes to public Elementor signature workflows.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Integrations;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Capabilities;
use Elementor\Core\Base\Document;

/**
 * Native document-save validation protects configured forms, including their field rules.
 */
final class ElementorPermissions {

	/**
	 * Preserve authorized forms when an editor without send permission changes a page.
	 *
	 * Elementor invokes this filter before saving its document data. Throwing aborts the
	 * native save rather than silently dropping an editor's changes. Existing configured
	 * forms can stay unchanged while unrelated widgets are edited. Protecting their entire
	 * settings also covers dynamic tags, mapped fields and validation/consent controls.
	 *
	 * @param array<string, mixed> $data Pending native document data.
	 * @param Document $document Native document before saving.
	 * @return array<string, mixed> Unchanged, authorized data.
	 * @throws \RuntimeException When a user changes a workflow without send permission or selects an unavailable PDF.
	 */
	public function before_save( array $data, Document $document ): array {
		if ( ! isset( $data['elements'] ) || ! is_array( $data['elements'] ) ) {
			return $data;
		}
		$forms = $this->forms( $data['elements'] );
		if ( ! current_user_can( Capabilities::SEND ) ) {
			if ( $forms !== $this->forms( $document->get_elements_data() ) ) {
				throw new \RuntimeException( esc_html__( 'You need permission to send Assinafy documents before changing a signature form.', 'assinafy' ) );
			}
			return $data;
		}
		foreach ( $forms as $form ) {
			$this->validate_pdf( $form['settings'] );
		}
		return $data;
	}

	/**
	 * An enabled action must reference a PDF its configuring user can read.
	 *
	 * @param array<string, mixed> $settings Form configuration.
	 * @throws \RuntimeException When an enabled action has no accessible local PDF.
	 */
	private function validate_pdf( array $settings ): void {
		$actions = $settings['submit_actions'] ?? array();
		if ( ! is_array( $actions ) || ! in_array( 'assinafy', $actions, true ) ) {
			return;
		}
		$media = $settings['assinafy_pdf'] ?? array();
		$pdf   = is_array( $media ) ? (int) ( $media['id'] ?? 0 ) : 0;
		if ( ! self::local_pdf( $pdf ) || ! current_user_can( 'read_post', $pdf ) ) {
			throw new \RuntimeException( esc_html__( 'Choose an accessible PDF from this site before enabling the Assinafy form action.', 'assinafy' ) );
		}
	}

	/**
	 * Form submissions cannot supply remote URLs or arbitrary filesystem paths.
	 *
	 * @param int $attachment_id Configured Media Library item.
	 */
	public static function local_pdf( int $attachment_id ): bool {
		$file = $attachment_id > 0 ? get_attached_file( $attachment_id ) : false;
		return 'application/pdf' === get_post_mime_type( $attachment_id )
			&& is_string( $file ) && is_file( $file ) && is_readable( $file );
	}

	/**
	 * Collect every configured form, retaining duplicate IDs rather than overwriting them.
	 *
	 * @param array<mixed> $elements Native nested element tree.
	 * @return array<int, array{id: string, settings: array<string, mixed>}> Configured forms.
	 */
	private function forms( array $elements ): array {
		$forms = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = $element['settings'] ?? array();
			if ( 'form' === ( $element['widgetType'] ?? '' ) && is_array( $settings ) && $this->configured( $settings ) ) {
				$forms[] = array(
					'id'       => (string) ( $element['id'] ?? '' ),
					'settings' => $settings,
				);
			}
			if ( is_array( $element['elements'] ?? null ) ) {
				$forms = array_merge( $forms, $this->forms( $element['elements'] ) );
			}
		}
		return $forms;
	}

	/**
	 * Include disabled configurations too, so their privileged settings cannot be replaced.
	 *
	 * @param array<string, mixed> $settings Native form settings.
	 */
	private function configured( array $settings ): bool {
		$actions = $settings['submit_actions'] ?? array();
		if ( is_array( $actions ) && in_array( 'assinafy', $actions, true ) ) {
			return true;
		}
		foreach ( array_keys( $settings ) as $key ) {
			if ( str_starts_with( (string) $key, 'assinafy_' ) ) {
				return true;
			}
		}
		return false;
	}
}
