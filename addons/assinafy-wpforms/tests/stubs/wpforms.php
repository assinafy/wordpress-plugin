<?php
/** Static-analysis signatures from WPForms Lite 2.0.1.1; never loaded by integration tests. */
namespace WPForms {
	class WPForms {
		public \WPForms_Process $process;
		public function is_pro(): bool {}
	}
}

namespace {
	define( 'WPFORMS_VERSION', '2.0.1.1' );
	class WPForms_Process {
		/** @var array<int, array<int|string, mixed>> */
		public array $errors;
	}
	function wpforms(): \WPForms\WPForms {}
	/** @param string|array<string> $caps */
	function wpforms_current_user_can( array|string $caps = [], int $id = 0 ): bool {}
	/** @param array<string, mixed> $form_data @param array<string, mixed> $args */
	function wpforms_panel_field( string $option, string $panel, string $field, array $form_data, string $label, array $args = [], bool $do_echo = true ): ?string {}
	/** @return array<string, mixed>|false|null */
	function wpforms_decode( string $data ): array|false|null {}
	function wpforms_encode( mixed $data = false ): string|false {}
	/** @param array<string, mixed> $args */
	function wpforms_log( string $title = '', mixed $message = '', array $args = [] ): void {}
}
