<?php
/**
 * Plugin Name: SCF Test Plugin, Get Field Movie Title Block
 * Plugin URI: https://github.com/WordPress/secure-custom-fields
 * Author: SCF Team
 *
 * @package scf-test-plugins
 */

/**
 * Registers a custom block type for testing.
 *
 * @return void
 */
function scf_test_register_acf_blocks() {
	register_block_type( __DIR__ . '/blocks/scf-movie-title-block' );
}
add_action( 'init', 'scf_test_register_acf_blocks' );
