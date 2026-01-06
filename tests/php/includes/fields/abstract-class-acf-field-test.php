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
		if ( $this->post_id ) {
			wp_delete_post( $this->post_id, true );
		}
		parent::tear_down();
	}
}
