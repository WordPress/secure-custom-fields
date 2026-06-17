<?php
/**
 * Test the opt-in Interactivity API front-end form view bundle helpers.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the front-end form files.
acf_include( 'includes/forms/form-front-view.php' );
acf_include( 'includes/forms/form-front.php' );

/**
 * Class Test_Form_Front_View
 */
class Test_Form_Front_View extends BaseTestCase {

	/**
	 * Restore the default (disabled) setting after each test.
	 */
	public function tear_down() {
		acf_update_setting( 'frontend_interactivity_form', false );
		parent::tear_down();
	}

	/**
	 * Test the helper functions exist.
	 */
	public function test_functions_exist() {
		$this->assertTrue( function_exists( 'scf_frontend_form_interactivity_enabled' ) );
		$this->assertTrue( function_exists( 'scf_get_frontend_form_view_field_types' ) );
		$this->assertTrue( function_exists( 'scf_frontend_form_fields_are_view_compatible' ) );
		$this->assertTrue( function_exists( 'scf_frontend_form_view_attributes' ) );
		$this->assertTrue( function_exists( 'scf_enqueue_frontend_form_view' ) );
	}

	/**
	 * Test the setting defaults to false so behavior is unchanged out of the box.
	 */
	public function test_setting_defaults_to_disabled() {
		$this->assertFalse( acf_get_setting( 'frontend_interactivity_form' ) );
		$this->assertFalse( scf_frontend_form_interactivity_enabled() );
	}

	/**
	 * Test enabling the setting via acf_update_setting().
	 */
	public function test_setting_enabled_via_update_setting() {
		acf_update_setting( 'frontend_interactivity_form', true );

		$this->assertTrue( scf_frontend_form_interactivity_enabled() );
	}

	/**
	 * Test enabling the setting via the acf/settings filter.
	 */
	public function test_setting_enabled_via_filter() {
		add_filter( 'acf/settings/frontend_interactivity_form', '__return_true' );

		$this->assertTrue( scf_frontend_form_interactivity_enabled() );

		remove_filter( 'acf/settings/frontend_interactivity_form', '__return_true' );
	}

	/**
	 * Test the simple field type allow-list contents.
	 */
	public function test_view_field_types_allow_list() {
		$types = scf_get_frontend_form_view_field_types();

		$expected = array( 'text', 'textarea', 'number', 'email', 'url', 'password', 'range', 'select', 'checkbox', 'radio', 'button_group', 'true_false' );
		foreach ( $expected as $type ) {
			$this->assertContains( $type, $types, "Allow-list should contain {$type}" );
		}

		$complex = array( 'repeater', 'flexible_content', 'gallery', 'image', 'file', 'wysiwyg', 'date_picker', 'date_time_picker', 'time_picker', 'color_picker', 'google_map', 'relationship', 'post_object', 'page_link', 'user', 'taxonomy', 'oembed', 'link', 'clone', 'group', 'icon_picker', 'nav_menu' );
		foreach ( $complex as $type ) {
			$this->assertNotContains( $type, $types, "Allow-list should not contain {$type}" );
		}
	}

	/**
	 * Test fields compatibility check with only simple fields.
	 */
	public function test_simple_fields_are_view_compatible() {
		$fields = array(
			array( 'type' => 'text' ),
			array( 'type' => 'email' ),
			array(
				'type' => 'select',
				'ui'   => 0,
				'ajax' => 0,
			),
			array( 'type' => 'true_false' ),
		);

		$this->assertTrue( scf_frontend_form_fields_are_view_compatible( $fields ) );
	}

	/**
	 * Test an empty field list is compatible.
	 */
	public function test_empty_fields_are_view_compatible() {
		$this->assertTrue( scf_frontend_form_fields_are_view_compatible( array() ) );
	}

	/**
	 * Test a complex field forces the classic fallback.
	 */
	public function test_complex_field_is_not_view_compatible() {
		$fields = array(
			array( 'type' => 'text' ),
			array( 'type' => 'date_picker' ),
		);

		$this->assertFalse( scf_frontend_form_fields_are_view_compatible( $fields ) );
	}

	/**
	 * Test a Select2-backed select forces the classic fallback.
	 */
	public function test_select_with_ui_is_not_view_compatible() {
		$this->assertFalse(
			scf_frontend_form_fields_are_view_compatible(
				array(
					array(
						'type' => 'select',
						'ui'   => 1,
					),
				)
			)
		);

		$this->assertFalse(
			scf_frontend_form_fields_are_view_compatible(
				array(
					array(
						'type' => 'select',
						'ui'   => 0,
						'ajax' => 1,
					),
				)
			)
		);
	}

	/**
	 * Test a field without a type is treated as incompatible.
	 */
	public function test_field_without_type_is_not_view_compatible() {
		$this->assertFalse( scf_frontend_form_fields_are_view_compatible( array( array( 'name' => 'mystery' ) ) ) );
	}

	/**
	 * Test the view attributes add the Interactivity API directives.
	 */
	public function test_view_attributes_add_directives() {
		$attributes = scf_frontend_form_view_attributes(
			array(
				'id'    => 'acf-form',
				'class' => 'acf-form',
			)
		);

		$this->assertSame( 'scf/form', $attributes['data-wp-interactive'] );
		$this->assertSame( 'actions.handleSubmit', $attributes['data-wp-on--submit'] );
		$this->assertSame( 'actions.clearError', $attributes['data-wp-on--input'] );
		$this->assertSame( 'actions.clearError', $attributes['data-wp-on--change'] );
		$this->assertSame( 'novalidate', $attributes['novalidate'] );

		// Existing attributes are preserved.
		$this->assertSame( 'acf-form', $attributes['id'] );
		$this->assertSame( 'acf-form', $attributes['class'] );
	}

	/**
	 * Test render_form output contains the directives when the setting is
	 * enabled and every field is simple.
	 */
	public function test_render_form_adds_directives_when_enabled_and_simple() {
		acf_update_setting( 'frontend_interactivity_form', true );

		acf_add_local_field_group(
			array(
				'key'      => 'group_scf_ffv_simple',
				'title'    => 'View Bundle Simple',
				'fields'   => array(
					array(
						'key'      => 'field_scf_ffv_text',
						'name'     => 'ffv_text',
						'label'    => 'Text',
						'type'     => 'text',
						'required' => 1,
					),
				),
				'location' => array(),
			)
		);

		$form_front = new acf_form_front();

		ob_start();
		$form_front->render_form(
			array(
				'id'           => 'scf-ffv-simple',
				'post_id'      => 123,
				'field_groups' => array( 'group_scf_ffv_simple' ),
			)
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-wp-interactive="scf/form"', $output );
		$this->assertStringContainsString( 'data-wp-on--submit="actions.handleSubmit"', $output );
		$this->assertStringContainsString( 'novalidate', $output );
	}

	/**
	 * Test render_form output omits the directives for a complex field, falling
	 * back to the classic stack.
	 */
	public function test_render_form_omits_directives_for_complex_fields() {
		acf_update_setting( 'frontend_interactivity_form', true );

		acf_add_local_field_group(
			array(
				'key'      => 'group_scf_ffv_complex',
				'title'    => 'View Bundle Complex',
				'fields'   => array(
					array(
						'key'   => 'field_scf_ffv_date',
						'name'  => 'ffv_date',
						'label' => 'Date',
						'type'  => 'date_picker',
					),
				),
				'location' => array(),
			)
		);

		$form_front = new acf_form_front();

		ob_start();
		$form_front->render_form(
			array(
				'id'           => 'scf-ffv-complex',
				'post_id'      => 123,
				'field_groups' => array( 'group_scf_ffv_complex' ),
			)
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringNotContainsString( 'data-wp-interactive', $output );
	}

	/**
	 * Test render_form output is unchanged when the setting is disabled.
	 */
	public function test_render_form_unchanged_when_disabled() {
		acf_add_local_field_group(
			array(
				'key'      => 'group_scf_ffv_default',
				'title'    => 'View Bundle Default',
				'fields'   => array(
					array(
						'key'   => 'field_scf_ffv_default_text',
						'name'  => 'ffv_default_text',
						'label' => 'Text',
						'type'  => 'text',
					),
				),
				'location' => array(),
			)
		);

		$form_front = new acf_form_front();

		ob_start();
		$form_front->render_form(
			array(
				'id'           => 'scf-ffv-default',
				'post_id'      => 123,
				'field_groups' => array( 'group_scf_ffv_default' ),
			)
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringNotContainsString( 'data-wp-interactive', $output );
		$this->assertStringNotContainsString( 'novalidate', $output );
	}
}
