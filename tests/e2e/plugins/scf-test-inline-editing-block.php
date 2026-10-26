<?php
/**
 * Plugin Name: SCF Test Plugin Inline Editing Block
 * Description: Test plugin for SCF block version 3 inline text editing
 * Version: 1.0.0
 * Author: SCF Test
 * Text Domain: scf-test-plugin-inline-editing-block
 *
 * @package scf-test-plugins
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the inline editing block.
 */
function scf_test_register_inline_editing_block() {
	register_block_type( __DIR__ . '/blocks/scf-inline-editing-block' );
}
add_action( 'init', 'scf_test_register_inline_editing_block' );

/**
 * Register the field group for the inline editing block.
 */
function scf_test_register_inline_editing_block_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_inline_editing_block_fields',
			'title'    => 'Inline Editing Block Fields',
			'fields'   => array(
				array(
					'key'           => 'field_inline_editing_title',
					'label'         => 'Title',
					'name'          => 'title',
					'type'          => 'text',
					'default_value' => 'Hello',
				),
				array(
					'key'           => 'field_inline_editing_subtitle',
					'label'         => 'Subtitle',
					'name'          => 'subtitle',
					'type'          => 'text',
					'default_value' => 'World',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'scf/inline-editing-block',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'scf_test_register_inline_editing_block_fields' );
