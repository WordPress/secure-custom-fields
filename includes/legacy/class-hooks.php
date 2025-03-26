<?php
/**
 * Legacy hook handling system
 *
 * @package wordpress/secure-custom-fields
 */

namespace WordPress\SCF\Legacy;

/**
 * Class to handle legacy hook mappings.
 *
 * @since 6.5.0
 */
class Hooks {
	/**
	 * Hook mappings from new to legacy format.
	 *
	 * @since 6.5.0
	 *
	 * @var array
	 */
	private $hook_mappings = array(
		'filters' => array(
			'scf_blocks_binding_value'                 => array(
				'hook' => 'acf/blocks/binding_value',
				'args' => 4,
			),
			'scf_bindings_field_not_supported_message' => array(
				'hook' => 'acf/bindings/field_not_supported_message',
				'args' => 1,
			),
			'scf_bindings_field_not_allowed_message'   => array(
				'hook' => 'acf/bindings/field_not_allowed_message',
				'args' => 1,
			),
		),
		'actions' => array(
			'scf_init'                     => array(
				'hook' => 'acf/init',
				'args' => 1,
			),
			'scf_include_field_types'      => 'acf/include_field_types',
			'scf_include_location_rules'   => 'acf/include_location_rules',
			'scf_include_fields'           => 'acf/include_fields',
			'scf_include_post_types'       => 'acf/include_post_types',
			'scf_include_taxonomies'       => 'acf/include_taxonomies',
			'scf_include_options_pages'    => 'acf/include_options_pages',
			'scf_first_activated'          => 'acf/first_activated',
			'scf_init_internal_post_types' => 'acf/init_internal_post_types',
		),
	);

	/**
	 * Constructor.
	 *
	 * @since 6.5.0
	 */
	public function __construct() {
		if ( ! $this->is_legacy_hooks_enabled() ) {
			return;
		}

		$this->setup_legacy_hooks();
	}

	/**
	 * Check if legacy hooks should be enabled.
	 *
	 * @since 6.5.0
	 *
	 * @return bool
	 */
	private function is_legacy_hooks_enabled() {
		/**
		 * Filter whether legacy hooks should be enabled.
		 *
		 * @since 6.5.0
		 *
		 * @param bool $enabled Whether legacy hooks are enabled.
		 */
		return apply_filters( 'scf_enable_legacy_hooks', true );
	}

	/**
	 * Setup all legacy hook mappings.
	 *
	 * @since 6.5.0
	 */
	private function setup_legacy_hooks() {
		foreach ( $this->hook_mappings['filters'] as $new_hook => $config ) {
			$this->setup_legacy_filter( $new_hook, $config['hook'], $config['args'] );
		}

		foreach ( $this->hook_mappings['actions'] as $new_hook => $config ) {
			if ( is_array( $config ) ) {
				$this->setup_legacy_action( $new_hook, $config['hook'], $config['args'] );
			} else {
				// Handle string format for actions without args.
				$this->setup_legacy_action( $new_hook, $config, 1 );
			}
		}
	}

	/**
	 * Setup a legacy filter mapping.
	 *
	 * @since 6.5.0
	 *
	 * @param string $new_hook New hook name.
	 * @param string $legacy_hook Legacy hook name.
	 * @param int    $accepted_args Number of arguments the filter accepts.
	 */
	private function setup_legacy_filter( $new_hook, $legacy_hook, $accepted_args ) {
		add_filter(
			$new_hook,
			function () use ( $new_hook, $legacy_hook ) {
				$args  = func_get_args();
				$value = $args[0];

				// Run the legacy filter if it has any callbacks
				if ( has_filter( $legacy_hook ) ) {
					$value = apply_filters( $legacy_hook, $value, ...array_slice( $args, 1 ) );
				}

				return $value;
			},
			1,
			$accepted_args
		);
	}

	/**
	 * Setup a legacy action mapping.
	 *
	 * @since 6.5.0
	 *
	 * @param string $new_hook New hook name.
	 * @param string $legacy_hook Legacy hook name.
	 * @param int    $accepted_args Number of arguments the action accepts.
	 */
	private function setup_legacy_action( $new_hook, $legacy_hook, $accepted_args ) {
		add_action(
			$new_hook,
			function () use ( $new_hook, $legacy_hook ) {
				$args = func_get_args();

				// Run the legacy action if it has any callbacks
				if ( has_action( $legacy_hook ) ) {
					do_action( $legacy_hook, ...$args );
				}
			},
			1,
			$accepted_args
		);
	}
}

new Hooks();
