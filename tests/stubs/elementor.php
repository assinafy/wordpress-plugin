<?php
/**
 * Minimal documented Elementor contracts, not the Elementor Pro implementation.
 *
 * Used for adapter contract tests and static analysis only. Real-host tests must load
 * licensed Elementor Pro separately; this file is never shipped with the plugin.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Elementor {

	defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	define( 'ASSINAFY_ELEMENTOR_CONTRACT_STUBS', true );

	/** Native control identifiers used by the adapter. */
	class Controls_Manager {
		public const TEXT     = 'text';
		public const TEXTAREA = 'textarea';
		public const MEDIA    = 'media';
	}

	/** Recording editor control surface. */
	class Widget_Base {
		/** @var array<string, array<string, mixed>> */
		public array $controls = array();
		/** @param array<string, mixed> $args */
		public function start_controls_section( string $name, array $args ): void {}
		/** @param array<string, mixed> $args */
		public function add_control( string $name, array $args ): void {
			$this->controls[ $name ] = $args;
		}
		public function end_controls_section(): void {}
	}
}

namespace Elementor\Core\Base {

	/** The document data available to the public before-save filter. */
	abstract class Document {
		/** @param array<mixed> $elements */
		public function __construct( private array $elements = array() ) {}
		/** @return array<mixed> */
		public function get_elements_data(): array {
			return $this->elements;
		}
	}
}

namespace ElementorPro\Modules\Forms\Classes {

	/** Public form action contract documented by Elementor. */
	abstract class Action_Base {
		abstract public function get_name(): string;
		abstract public function get_label(): string;
		/** @param \Elementor\Widget_Base $widget */
		abstract public function register_settings_section( $widget ): void;
		/**
		 * @param Form_Record $record
		 * @param Ajax_Handler $ajax_handler
		 */
		abstract public function run( $record, $ajax_handler ): void;
		/**
		 * @param array<string, mixed> $element
		 * @return array<string, mixed>
		 */
		abstract public function on_export( $element ): array;
	}

	/** Recording native validated-record getters. */
	class Form_Record {
		/** @param array<string, mixed> $data */
		public function __construct( private array $data ) {}
		public function get( string $key ): mixed {
			return $this->data[ $key ] ?? null;
		}
	}

	/** Only the documented field-error API is reproduced. */
	class Ajax_Handler {
		/** @var array<string, string> */
		public array $errors = array();
		public function add_error( string $field_id, string $message ): void {
			$this->errors[ $field_id ] = $message;
		}
	}
}

namespace ElementorPro\Modules\Forms\Registrars {

	use ElementorPro\Modules\Forms\Classes\Action_Base;

	/** Recording registrar, with the native action-name identity. */
	class Form_Actions_Registrar {
		/** @var array<string, Action_Base> */
		public array $actions = array();
		public function register( Action_Base $action ): void {
			$this->actions[ $action->get_name() ] = $action;
		}
	}
}
