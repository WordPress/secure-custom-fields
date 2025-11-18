<?php
/**
 * Plugin Name: SCF Test Plugin - Testimonial Block
 * Description: Test plugin for SCF testimonial block with various field types
 * Version: 1.0.0
 * Author: SCF Test
 * Text Domain: scf-test-plugin-testimonial-block
 *
 * @package scf-test-plugins
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the testimonial block.
 */
function scf_test_register_testimonial_block() {
	register_block_type( __DIR__ . '/blocks/scf-testimonial-block' );
}
add_action( 'init', 'scf_test_register_testimonial_block' );

/**
 * Register field group for the testimonial block.
 */
function scf_test_register_testimonial_fields() {
	// Check if ACF function exists.
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_testimonial_block',
			'title'                 => 'Testimonial Block Fields',
			'fields'                => array(
				array(
					'key'         => 'field_testimonial_quote',
					'label'       => 'Quote',
					'name'        => 'quote',
					'type'        => 'textarea',
					'required'    => 1,
					'placeholder' => 'Enter the testimonial quote...',
					'rows'        => 4,
				),
				array(
					'key'         => 'field_testimonial_author',
					'label'       => 'Author',
					'name'        => 'author',
					'type'        => 'text',
					'placeholder' => 'Author name',
				),
				array(
					'key'         => 'field_testimonial_role',
					'label'       => 'Role',
					'name'        => 'role',
					'type'        => 'text',
					'placeholder' => 'Author role or title',
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'scf/testimonial',
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
			'description'           => '',
			'show_in_rest'          => 0,
		)
	);
}
add_action( 'acf/init', 'scf_test_register_testimonial_fields' );
