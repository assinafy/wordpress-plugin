<?php
/**
 * Contact Form 7 6.1.7 signatures consumed by its add-on. Static analysis only.
 *
 * Source: official contact-form-7/includes/{contact-form,submission,form-tag}.php.
 *
 * @package Assinafy\WP\Tests
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class WPCF7_ContactForm {
	public function id(): int {
		return 0; }
	public function in_demo_mode(): bool {
		return false; }
	/** @return array<int, WPCF7_FormTag> */
	public function scan_form_tags(): array {
		return array(); }
}

class WPCF7_Submission {
	public static function get_instance(): ?self {
		return null; }
	public function is( string $status ): bool {
		return false; }
	public function get_contact_form(): WPCF7_ContactForm {
		return new WPCF7_ContactForm(); }
	public function get_meta( string $name ): mixed {
		return null; }
	/** @return string|array<string|int, mixed>|null */
	public function get_posted_data( string $name = '' ): string|array|null {
		return null; }
}

class WPCF7_FormTag {
	public string $name     = '';
	public string $basetype = '';
	public function has_option( string $name ): bool {
		return false; }
}
