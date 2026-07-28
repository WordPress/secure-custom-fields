<?php
/**
 * Plugin Name: SCF Test Options Page Field Scope
 * Plugin URI: https://github.com/WordPress/secure-custom-fields
 * Description: Registers isolated Options Page fixtures for E2E tests.
 * Author: SCF Team
 *
 * @package scf-test-plugins
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_LOW_SLUG       = 'scf-test-options-page-field-scope-low';
const SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_PROTECTED_SLUG = 'scf-test-options-page-field-scope-protected';
const SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_LOW_KEY        = 'field_scf_test_options_page_field_scope_low';
const SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_PROTECTED_KEY  = 'field_scf_test_options_page_field_scope_protected';
const SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_LOW_NAME       = 'scf_test_options_page_field_scope_low';
const SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_PROTECTED_NAME = 'scf_test_options_page_field_scope_protected';

/**
 * Registers the two Options Pages and their local field groups.
 *
 * Both pages intentionally use the same `options` storage while requiring
 * different capabilities.
 *
 * @return void
 */
function scf_test_options_page_field_scope_register_fixtures() {
	if ( ! function_exists( 'acf_add_options_page' ) || ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title' => 'SCF Field Scope Low',
			'menu_title' => 'SCF Field Scope Low',
			'menu_slug'  => SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_LOW_SLUG,
			'capability' => 'edit_posts',
			'post_id'    => 'options',
			'redirect'   => false,
		)
	);

	acf_add_options_page(
		array(
			'page_title' => 'SCF Field Scope Protected',
			'menu_title' => 'SCF Field Scope Protected',
			'menu_slug'  => SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_PROTECTED_SLUG,
			'capability' => 'manage_options',
			'post_id'    => 'options',
			'redirect'   => false,
		)
	);

	acf_add_local_field_group(
		array(
			'key'      => 'group_scf_test_options_page_field_scope_low',
			'title'    => 'SCF Field Scope Low Fields',
			'fields'   => array(
				array(
					'key'      => SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_LOW_KEY,
					'label'    => 'Low Field',
					'name'     => SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_LOW_NAME,
					'type'     => 'text',
					'required' => 1,
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_LOW_SLUG,
					),
				),
			),
			'active'   => true,
		)
	);

	acf_add_local_field_group(
		array(
			'key'      => 'group_scf_test_options_page_field_scope_protected',
			'title'    => 'SCF Field Scope Protected Fields',
			'fields'   => array(
				array(
					'key'      => SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_PROTECTED_KEY,
					'label'    => 'Protected Field',
					'name'     => SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_PROTECTED_NAME,
					'type'     => 'text',
					'required' => 1,
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_PROTECTED_SLUG,
					),
				),
			),
			'active'   => true,
		)
	);
}
add_action( 'acf/init', 'scf_test_options_page_field_scope_register_fixtures' );

/**
 * Returns the exact option key used by an Options Page field.
 *
 * @param string $field_name Field name.
 * @param bool   $hidden     Whether to return the hidden reference key.
 * @return string
 */
function scf_test_options_page_field_scope_get_option_key( $field_name, $hidden = false ) {
	return ( $hidden ? '_options_' : 'options_' ) . $field_name;
}

/**
 * Removes only the fixture's known option values and references.
 *
 * @return void
 */
function scf_test_options_page_field_scope_clear_options() {
	$field_names = array(
		SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_LOW_NAME,
		SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_PROTECTED_NAME,
	);

	foreach ( $field_names as $field_name ) {
		delete_option( scf_test_options_page_field_scope_get_option_key( $field_name ) );
		delete_option( scf_test_options_page_field_scope_get_option_key( $field_name, true ) );
	}
}

/**
 * Reads one fixture field's raw value and hidden reference.
 *
 * @param string $field_name Field name.
 * @return array
 */
function scf_test_options_page_field_scope_read_field_state( $field_name ) {
	$missing       = 'scf-test-options-page-field-scope-missing';
	$value         = get_option( scf_test_options_page_field_scope_get_option_key( $field_name ), $missing );
	$reference     = get_option( scf_test_options_page_field_scope_get_option_key( $field_name, true ), $missing );
	$value_exists  = $missing !== $value;
	$reference_set = $missing !== $reference;

	return array(
		'value_exists'     => $value_exists,
		'value'            => $value_exists ? $value : null,
		'reference_exists' => $reference_set,
		'reference'        => $reference_set ? $reference : null,
	);
}

/**
 * Returns the complete, bounded fixture state.
 *
 * @return array
 */
function scf_test_options_page_field_scope_get_state() {
	return array(
		'low'       => scf_test_options_page_field_scope_read_field_state( SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_LOW_NAME ),
		'protected' => scf_test_options_page_field_scope_read_field_state( SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_PROTECTED_NAME ),
	);
}

/**
 * Checks access to the fixture REST endpoints.
 *
 * @return bool
 */
function scf_test_options_page_field_scope_rest_permission() {
	return current_user_can( 'manage_options' );
}

/**
 * Handles the fixture state endpoint.
 *
 * @return WP_REST_Response
 */
function scf_test_options_page_field_scope_rest_get_state() {
	return rest_ensure_response( scf_test_options_page_field_scope_get_state() );
}

/**
 * Resets the fixture to one of three fixed states.
 *
 * No caller-provided field value or option name is accepted.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response|WP_Error
 */
function scf_test_options_page_field_scope_rest_reset( $request ) {
	$mode = $request->get_param( 'mode' );

	if ( ! in_array( $mode, array( 'baseline', 'without-protected', 'cleanup' ), true ) ) {
		return new WP_Error(
			'scf_test_options_page_field_scope_invalid_mode',
			'Invalid fixture reset mode.',
			array( 'status' => 400 )
		);
	}

	scf_test_options_page_field_scope_clear_options();

	if ( 'cleanup' === $mode ) {
		return rest_ensure_response( scf_test_options_page_field_scope_get_state() );
	}

	if ( ! function_exists( 'update_field' ) ) {
		return new WP_Error(
			'scf_test_options_page_field_scope_unavailable',
			'Secure Custom Fields is unavailable.',
			array( 'status' => 500 )
		);
	}

	update_field( SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_LOW_KEY, 'low-original', 'options' );

	if ( 'baseline' === $mode ) {
		update_field( SCF_TEST_OPTIONS_PAGE_FIELD_SCOPE_PROTECTED_KEY, 'protected-original', 'options' );
	}

	return rest_ensure_response( scf_test_options_page_field_scope_get_state() );
}

/**
 * Registers the admin-only, bounded state/reset endpoint.
 *
 * @return void
 */
function scf_test_options_page_field_scope_register_rest_route() {
	register_rest_route(
		'scf-test/v1',
		'/options-page-field-scope',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => 'scf_test_options_page_field_scope_rest_permission',
				'callback'            => 'scf_test_options_page_field_scope_rest_get_state',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => 'scf_test_options_page_field_scope_rest_permission',
				'callback'            => 'scf_test_options_page_field_scope_rest_reset',
				'args'                => array(
					'mode' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'baseline', 'without-protected', 'cleanup' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'scf_test_options_page_field_scope_register_rest_route' );

register_deactivation_hook( __FILE__, 'scf_test_options_page_field_scope_clear_options' );
