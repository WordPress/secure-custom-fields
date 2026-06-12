<?php
/**
 * Opt-in Interactivity API view bundle for front-end forms.
 *
 * When the `frontend_interactivity_form` setting is enabled and every field
 * rendered by an `acf_form()` call is a "simple" type, the classic jQuery
 * input stack is replaced by a small `@wordpress/interactivity` script module
 * (assets/src/js/frontend/scf-form-view.js) so the page loads without jQuery.
 * Any complex field — or a WordPress version without script-module support —
 * falls back to the classic stack automatically.
 *
 * @package Secure Custom Fields
 * @since SCF 6.8.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Returns whether front-end forms may use the Interactivity API view bundle.
 *
 * Requires the opt-in `frontend_interactivity_form` setting (set via
 * `acf_update_setting()` or the `acf/settings/frontend_interactivity_form`
 * filter) and WordPress 6.5+ script-module/Interactivity API support.
 *
 * @since SCF 6.8.9
 *
 * @return boolean
 */
function scf_frontend_form_interactivity_enabled(): bool {
	if ( ! function_exists( 'wp_register_script_module' ) || ! function_exists( 'wp_interactivity_state' ) ) {
		return false;
	}

	return (bool) acf_get_setting( 'frontend_interactivity_form' );
}

/**
 * Returns the field types the view bundle can drive without jQuery-era
 * libraries.
 *
 * Select fields are only compatible when rendered without the Select2 UI;
 * that per-field condition is checked in
 * scf_frontend_form_fields_are_view_compatible().
 *
 * @since SCF 6.8.9
 *
 * @return array
 */
function scf_get_frontend_form_view_field_types(): array {
	return array(
		'text',
		'textarea',
		'number',
		'email',
		'url',
		'password',
		'range',
		'select',
		'checkbox',
		'radio',
		'button_group',
		'true_false',
	);
}

/**
 * Returns whether every given field can be handled by the view bundle.
 *
 * Any field type outside the simple allow-list (repeaters, galleries,
 * pickers, Select2-backed types, wysiwyg, etc.) requires the classic jQuery
 * stack, as does a select field using the Select2 UI or AJAX loading.
 *
 * @since SCF 6.8.9
 *
 * @param array $fields The field arrays the form will render.
 * @return boolean
 */
function scf_frontend_form_fields_are_view_compatible( array $fields ): bool {
	$simple_types = scf_get_frontend_form_view_field_types();

	foreach ( $fields as $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : '';

		if ( ! in_array( $type, $simple_types, true ) ) {
			return false;
		}

		if ( 'select' === $type && ( ! empty( $field['ui'] ) || ! empty( $field['ajax'] ) ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Adds the Interactivity API directives the view bundle needs to the
 * `<form>` element attributes.
 *
 * `novalidate` is added because the bundle performs its own constraint
 * validation pass, rendering the browser's native validation messages with
 * the classic SCF error markup instead of browser bubbles.
 *
 * @since SCF 6.8.9
 *
 * @param array $attributes The form_attributes array from acf_form() args.
 * @return array
 */
function scf_frontend_form_view_attributes( array $attributes ): array {
	$attributes['data-wp-interactive'] = 'scf/form';
	$attributes['data-wp-on--submit']  = 'actions.handleSubmit';
	$attributes['data-wp-on--input']   = 'actions.clearError';
	$attributes['data-wp-on--change']  = 'actions.clearError';
	$attributes['novalidate']          = 'novalidate';

	return $attributes;
}

/**
 * Registers and enqueues the front-end form view script module, its
 * interactivity state, and the shared form styles.
 *
 * Safe to call at render time: script modules are printed in the footer, and
 * styles enqueued for an `acf_form()` page were already queued during
 * acf_form_head() (see acf_form_front::enqueue_form()).
 *
 * @since SCF 6.8.9
 *
 * @return void
 */
function scf_enqueue_frontend_form_view() {
	$suffix  = defined( 'SCF_DEVELOPMENT_MODE' ) && SCF_DEVELOPMENT_MODE ? '' : '.min';
	$version = acf_get_setting( 'version' );

	$asset_path = acf_get_path( 'assets/build/js/frontend/scf-form-view' . $suffix . '.asset.php' );
	$asset      = file_exists( $asset_path ) ? require $asset_path : null;

	wp_register_script_module(
		'scf-form-view',
		acf_get_url( 'assets/build/js/frontend/scf-form-view' . $suffix . '.js' ),
		$asset ? $asset['dependencies'] : array( '@wordpress/interactivity' ),
		$asset ? $asset['version'] : $version
	);
	wp_enqueue_script_module( 'scf-form-view' );

	wp_interactivity_state(
		'scf/form',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'acf_nonce' ),
			'i18n'    => array(
				'validationFailed'          => __( 'Validation failed', 'secure-custom-fields' ),
				'oneFieldRequiresAttention' => __( '1 field requires attention', 'secure-custom-fields' ),
				/* translators: %d: number of fields */
				'fieldsRequireAttention'    => __( '%d fields require attention', 'secure-custom-fields' ),
			),
		)
	);

	// Form styling is shared with the classic stack.
	wp_enqueue_style( 'acf-input' );
}
