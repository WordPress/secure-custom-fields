<?php
/**
 * Plugin Name: SCF Test Plugin V3 Block
 * Description: Test plugin for SCF block version 3 features
 * Version: 1.0.0
 * Author: SCF Test
 * Text Domain: scf-test-plugin-v3-block
 *
 * @package scf-test-plugins
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the v3 feature block.
 */
function scf_test_register_v3_block() {
	register_block_type( __DIR__ . '/blocks/scf-v3-block' );
}
add_action( 'init', 'scf_test_register_v3_block' );

/**
 * Register field group for the v3 block.
 */
function scf_test_register_v3_block_fields() {
	// Check if ACF function exists.
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_v3_block_fields',
			'title'                 => 'V3 Block Fields',
			'fields'                => array(
				array(
					'key'         => 'field_v3_title',
					'label'       => 'Title',
					'name'        => 'title',
					'type'        => 'text',
					'required'    => 1,
					'placeholder' => 'Enter a title...',
				),
				array(
					'key'         => 'field_v3_description',
					'label'       => 'Description',
					'name'        => 'description',
					'type'        => 'textarea',
					'placeholder' => 'Enter a description...',
					'rows'        => 3,
				),
				array(
					'key'           => 'field_v3_show_badge',
					'label'         => 'Show Badge',
					'name'          => 'show_badge',
					'type'          => 'true_false',
					'default_value' => 0,
					'ui'            => 1,
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'scf/v3-block',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => 'Fields for testing V3 block features',
			'show_in_rest'          => 0,
		)
	);
}
add_action( 'acf/init', 'scf_test_register_v3_block_fields' );
