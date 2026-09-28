<?php
/**
 * Abstract base test class for ACF field types.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

use WorDBless\BaseTestCase;

/**
 * Abstract base test class providing common functionality for ACF field tests.
 */
abstract class Abstract_ACF_Field_Test extends BaseTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * Field instance.
	 *
	 * @var acf_field
	 */
	protected $field_instance;

	/**
	 * Get the field type for this test class.
	 *
	 * @return string The field type identifier.
	 */
	abstract protected function get_field_type();

	/**
	 * Get a base field configuration with optional overrides.
	 *
	 * @param array $overrides Optional field configuration overrides.
	 * @return array The field configuration array.
	 */
	abstract protected function get_field( $overrides = array() );

	/**
	 * Get the include path(s) for the field class.
	 *
	 * Override this method to provide custom include paths.
	 * By default, derives the path from the field type name.
	 *
	 * Note: Field filenames are NOT consistent - some use underscores
	 * (e.g., 'true_false', 'color_picker'), others use hyphens
	 * (e.g., 'button-group', 'flexible-content'). This method handles
	 * the known inconsistencies.
	 *
	 * @return string|array Include path(s) relative to ACF plugin directory.
	 */
	protected function get_field_include_path() {
		$type = $this->get_field_type();

		// These fields use hyphens in filenames but underscores in type names.
		$hyphen_fields = array(
			'button_group',
			'flexible_content',
			'google_map',
			'nav_menu',
		);

		if ( in_array( $type, $hyphen_fields, true ) ) {
			$filename = str_replace( '_', '-', $type );
		} else {
			$filename = $type;
		}

		return "includes/fields/class-acf-field-{$filename}.php";
	}

	/**
	 * Render a field and return the generated HTML.
	 *
	 * The field type defaults are merged in first so the field matches what the
	 * render pipeline would pass after `validate_field` has run.
	 *
	 * @param array $field The field configuration.
	 * @return string The rendered HTML.
	 */
	protected function render_field_html( $field ) {
		if ( isset( $this->field_instance->defaults ) && is_array( $this->field_instance->defaults ) ) {
			$field = array_merge( $this->field_instance->defaults, $field );
		}

		$field['id']    = isset( $field['id'] ) ? $field['id'] : 'acf-field-test';
		$field['class'] = isset( $field['class'] ) ? $field['class'] : '';

		ob_start();
		$this->field_instance->render_field( $field );
		return ob_get_clean();
	}

	/**
	 * Enable the native pickers beta feature for the current test.
	 *
	 * @return void
	 */
	protected function enable_native_pickers() {
		update_option( 'scf_beta_feature_native_pickers_enabled', true );
	}

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		// Load the field class file(s).
		$paths = $this->get_field_include_path();
		$paths = is_array( $paths ) ? $paths : array( $paths );
		foreach ( $paths as $path ) {
			acf_include( $path );
		}

		// Create a test post.
		$this->post_id = wp_insert_post(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Get the field instance.
		$this->field_instance = acf_get_field_type( $this->get_field_type() );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		delete_option( 'scf_beta_feature_native_pickers_enabled' );

		if ( $this->post_id ) {
			wp_delete_post( $this->post_id, true );
		}
		parent::tear_down();
	}
}
