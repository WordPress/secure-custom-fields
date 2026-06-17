<?php
/**
 * Plugin Name: SCF Test Front Form
 * Plugin URI: https://github.com/WordPress/secure-custom-fields
 * Author: SCF Team
 *
 * Provides fixtures for the front-end acf_form() E2E tests:
 *
 * - A "simple" field group (text required + email) and a "complex" field
 *   group (text required + date_picker), both registered locally.
 * - Two published pages rendering acf_form() via shortcodes, targeting a
 *   dedicated post so submitted values can be verified on reload.
 * - A REST route to toggle the `frontend_interactivity_form` setting, which
 *   is driven by an option through the acf/settings filter.
 *
 * @package scf-test-plugins
 */

// The opt-in flag is driven by an option so tests can flip it via REST.
add_filter(
	'acf/settings/frontend_interactivity_form',
	function () {
		return (bool) get_option( 'scf_test_frontend_interactivity_form', false );
	}
);

// REST route to flip the flag.
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'scf-test/v1',
			'/frontend-interactivity',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => function ( $request ) {
					$enabled = (bool) $request->get_param( 'enabled' );
					update_option( 'scf_test_frontend_interactivity_form', $enabled ? 1 : 0 );

					return rest_ensure_response( array( 'enabled' => $enabled ) );
				},
			)
		);
	}
);

// Register the local field groups.
add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => 'group_scf_ff_simple',
				'title'    => 'Front Form Simple',
				'fields'   => array(
					array(
						'key'      => 'field_scf_ff_text',
						'name'     => 'ff_required_text',
						'label'    => 'Required Text',
						'type'     => 'text',
						'required' => 1,
					),
					array(
						'key'   => 'field_scf_ff_email',
						'name'  => 'ff_email',
						'label' => 'Email',
						'type'  => 'email',
					),
				),
				'location' => array(),
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_scf_ff_complex',
				'title'    => 'Front Form Complex',
				'fields'   => array(
					array(
						'key'      => 'field_scf_ff_complex_text',
						'name'     => 'ff_complex_text',
						'label'    => 'Complex Text',
						'type'     => 'text',
						'required' => 1,
					),
					array(
						'key'   => 'field_scf_ff_date',
						'name'  => 'ff_date',
						'label' => 'Date',
						'type'  => 'date_picker',
					),
				),
				'location' => array(),
			)
		);
	}
);

/**
 * Returns (creating if necessary) the ID of the post the front-end forms edit.
 *
 * @return int
 */
function scf_test_front_form_get_target_id() {
	$target_id = (int) get_option( 'scf_test_front_form_target' );

	if ( ! $target_id || ! get_post( $target_id ) ) {
		$target_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Front Form Target',
				'post_status' => 'publish',
			)
		);
		update_option( 'scf_test_front_form_target', $target_id );
	}

	return $target_id;
}

// Create the front-end form pages (idempotent).
add_action(
	'init',
	function () {
		$pages = array(
			'scf-front-form'         => array(
				'post_title'   => 'SCF Front Form',
				'post_content' => '[scf_front_form]',
			),
			'scf-front-form-complex' => array(
				'post_title'   => 'SCF Front Form Complex',
				'post_content' => '[scf_front_form_complex]',
			),
		);

		foreach ( $pages as $slug => $page ) {
			if ( get_page_by_path( $slug ) ) {
				continue;
			}

			wp_insert_post(
				array_merge(
					$page,
					array(
						'post_type'   => 'page',
						'post_name'   => $slug,
						'post_status' => 'publish',
					)
				)
			);
		}

		scf_test_front_form_get_target_id();
	},
	20
);

// acf_form_head() must run before any output on pages that render acf_form().
add_action(
	'template_redirect',
	function () {
		if ( function_exists( 'acf_form_head' ) && is_page( array( 'scf-front-form', 'scf-front-form-complex' ) ) ) {
			acf_form_head();
		}
	},
	1
);

add_shortcode(
	'scf_front_form',
	function () {
		if ( ! function_exists( 'acf_form' ) ) {
			return '';
		}

		ob_start();
		acf_form(
			array(
				'id'           => 'scf-front-form',
				'post_id'      => scf_test_front_form_get_target_id(),
				'field_groups' => array( 'group_scf_ff_simple' ),
				'submit_value' => 'Submit Form',
			)
		);

		return ob_get_clean();
	}
);

add_shortcode(
	'scf_front_form_complex',
	function () {
		if ( ! function_exists( 'acf_form' ) ) {
			return '';
		}

		ob_start();
		acf_form(
			array(
				'id'           => 'scf-front-form-complex',
				'post_id'      => scf_test_front_form_get_target_id(),
				'field_groups' => array( 'group_scf_ff_complex' ),
				'submit_value' => 'Submit Complex Form',
			)
		);

		return ob_get_clean();
	}
);
