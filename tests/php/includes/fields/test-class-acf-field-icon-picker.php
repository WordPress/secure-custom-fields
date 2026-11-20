<?php
/**
 * Tests for the Icon Picker field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_icon_picker.
 */
class Test_ACF_Field_Icon_Picker extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'icon_picker';
	}

	/**
	 * Icon Picker field instance.
	 *
	 * @var acf_field_icon_picker
	 */
	protected $field_instance;

	/**
	 * Get a base icon picker field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_icon_picker_test',
				'name'          => 'test_icon_picker',
				'type'          => 'icon_picker',
				'label'         => 'Test Icon Picker',
				'required'      => 0,
				'return_format' => 'array',
				'library'       => 'dashicons',
			),
			$overrides
		);
	}

	/**
	 * Get sample icon data.
	 *
	 * @return array
	 */
	protected function get_sample_icon() {
		return array(
			'type'  => 'dashicons',
			'value' => 'dashicons-admin-home',
		);
	}

	/**
	 * Test format_value with array return format.
	 */
	public function test_format_value_array_format() {
		$field = $this->get_field( array( 'return_format' => 'array' ) );
		$icon  = $this->get_sample_icon();

		$result = $this->field_instance->format_value( $icon, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'type', $result );
		$this->assertArrayHasKey( 'value', $result );
	}

	/**
	 * Test format_value with string return format.
	 */
	public function test_format_value_string_format() {
		$field = $this->get_field( array( 'return_format' => 'string' ) );
		$icon  = $this->get_sample_icon();

		$result = $this->field_instance->format_value( $icon, $this->post_id, $field );

		// Should return the icon value as string.
		$this->assertIsString( $result );
	}

	/**
	 * Test format_value returns empty for empty input.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test format_value_for_rest returns icon data.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();
		$icon  = $this->get_sample_icon();

		$result = $this->field_instance->format_value_for_rest( $icon, $this->post_id, $field );

		$this->assertNotEmpty( $result );
	}

	/**
	 * Test validate_value with valid icon.
	 */
	public function test_validate_value_valid() {
		$field = $this->get_field( array( 'required' => 1 ) );
		$icon  = $this->get_sample_icon();

		$valid = $this->field_instance->validate_value( true, $icon, $field, 'acf[field_icon_picker_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with empty when required.
	 */
	public function test_validate_value_empty_required() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, '', $field, 'acf[field_icon_picker_test]' );

		$this->assertFalse( $valid );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
	}

	/**
	 * Test library option.
	 */
	public function test_library_option() {
		$field = $this->get_field( array( 'library' => 'dashicons' ) );

		$this->assertEquals( 'dashicons', $field['library'] );
	}

	/**
	 * Test different icon types.
	 */
	public function test_different_icon_types() {
		$field = $this->get_field();

		// Dashicons.
		$dashicon = array(
			'type'  => 'dashicons',
			'value' => 'dashicons-menu',
		);
		$result   = $this->field_instance->format_value( $dashicon, $this->post_id, $field );
		$this->assertEquals( 'dashicons', $result['type'] );

		// Media library.
		$media  = array(
			'type'  => 'media_library',
			'value' => 123,
		);
		$result = $this->field_instance->format_value( $media, $this->post_id, $field );
		$this->assertEquals( 'media_library', $result['type'] );
	}

	/**
	 * Test format_value with unrecognized return format falls back to array.
	 */
	public function test_format_value_fallback_format() {
		$field = $this->get_field( array( 'return_format' => 'unknown' ) );
		$icon  = $this->get_sample_icon();

		$result = $this->field_instance->format_value( $icon, $this->post_id, $field );

		// Unrecognized format falls back to returning the value array.
		$this->assertIsArray( $result );
	}

	/**
	 * Renders the field with the Media Library tab and returns the attachment IDs it tried to load.
	 *
	 * @param array $value The field value.
	 * @return array
	 */
	protected function get_attachment_ids_loaded_by_render( $value ) {
		$loaded = array();
		$spy    = static function ( $downsize, $attachment_id ) use ( &$loaded ) {
			$loaded[] = $attachment_id;
			return $downsize;
		};
		add_filter( 'image_downsize', $spy, 10, 2 );

		// The Media Library tab is only shown to users who can upload files.
		$add_media_tab = static function ( $tabs ) {
			$tabs['media_library'] = 'Media Library';
			return $tabs;
		};
		add_filter( 'acf/fields/icon_picker/tabs', $add_media_tab );

		$field = $this->get_field(
			array(
				'id'    => 'acf-field_icon_picker_test',
				'class' => '',
				'name'  => 'acf[field_icon_picker_test]',
				'tabs'  => array( 'media_library' ),
				'value' => $value,
			)
		);

		ob_start();
		$this->field_instance->render_field( $field );
		ob_end_clean();

		remove_filter( 'image_downsize', $spy, 10 );
		remove_filter( 'acf/fields/icon_picker/tabs', $add_media_tab );

		return $loaded;
	}

	/**
	 * Test render_field only loads an attachment preview for Media Library icons.
	 */
	public function test_render_field_only_loads_media_library_preview() {
		$this->assertSame(
			array(),
			$this->get_attachment_ids_loaded_by_render(
				array(
					'type'  => 'url',
					'value' => 'https://example.org/icon.svg',
				)
			)
		);

		$this->assertSame(
			array(),
			$this->get_attachment_ids_loaded_by_render( $this->get_sample_icon() )
		);

		$this->assertSame(
			array( 123 ),
			$this->get_attachment_ids_loaded_by_render(
				array(
					'type'  => 'media_library',
					'value' => '123',
				)
			)
		);
	}
}
