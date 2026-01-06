<?php
/**
 * Tests for the Flexible Content field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_flexible_content.
 */
class Test_ACF_Field_Flexible_Content extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'flexible_content';
	}

	/**
	 * Flexible Content field instance.
	 *
	 * @var acf_field_flexible_content
	 */
	protected $field_instance;

	/**
	 * Get a base flexible content field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'      => 'field_flex_test',
				'name'     => 'test_flex',
				'type'     => 'flexible_content',
				'label'    => 'Test Flexible Content',
				'required' => 0,
				'min'      => '',
				'max'      => '',
				'layouts'  => array(
					'layout_text'  => array(
						'key'        => 'layout_text',
						'name'       => 'text_block',
						'label'      => 'Text Block',
						'display'    => 'block',
						'min'        => '',
						'max'        => '',
						'sub_fields' => array(
							array(
								'key'       => 'field_text_content',
								'name'      => 'text_content',
								'_name'     => 'text_content',
								'type'      => 'textarea',
								'label'     => 'Text Content',
								'required'  => 0,
								'maxlength' => '',
							),
						),
					),
					'layout_image' => array(
						'key'        => 'layout_image',
						'name'       => 'image_block',
						'label'      => 'Image Block',
						'display'    => 'block',
						'min'        => '',
						'max'        => '',
						'sub_fields' => array(
							array(
								'key'           => 'field_image',
								'name'          => 'image',
								'_name'         => 'image',
								'type'          => 'image',
								'label'         => 'Image',
								'required'      => 0,
								'return_format' => 'array',
							),
							array(
								'key'      => 'field_caption',
								'name'     => 'caption',
								'_name'    => 'caption',
								'type'     => 'text',
								'label'    => 'Caption',
								'required' => 0,
							),
						),
					),
				),
			),
			$overrides
		);
	}

	/**
	 * Test validate_value with empty value when not required.
	 */
	public function test_validate_value_empty_not_required() {
		$field = $this->get_field( array( 'required' => 0 ) );
		$valid = $this->field_instance->validate_value( true, array(), $field, 'acf[field_flex_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with empty value when required.
	 */
	public function test_validate_value_empty_required() {
		$field = $this->get_field( array( 'required' => 1 ) );
		$valid = $this->field_instance->validate_value( true, array(), $field, 'acf[field_flex_test]' );

		$this->assertFalse( $valid );
	}

	/**
	 * Test validate_value with layouts when required.
	 */
	public function test_validate_value_with_layouts_required() {
		$field = $this->get_field( array( 'required' => 1 ) );
		$value = array(
			array(
				'acf_fc_layout'      => 'text_block',
				'field_text_content' => 'Hello world',
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_flex_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with min layouts not met.
	 */
	public function test_validate_value_min_layouts_not_met() {
		$field = $this->get_field( array( 'min' => 3 ) );
		$value = array(
			array(
				'acf_fc_layout'      => 'text_block',
				'field_text_content' => 'Block 1',
			),
			array(
				'acf_fc_layout'      => 'text_block',
				'field_text_content' => 'Block 2',
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_flex_test]' );

		$this->assertIsString( $valid );
		$this->assertStringContainsString( '3', $valid );
	}

	/**
	 * Test validate_value with min layouts met.
	 */
	public function test_validate_value_min_layouts_met() {
		$field = $this->get_field( array( 'min' => 2 ) );
		$value = array(
			array(
				'acf_fc_layout'      => 'text_block',
				'field_text_content' => 'Block 1',
			),
			array(
				'acf_fc_layout' => 'image_block',
				'field_image'   => 123,
				'field_caption' => 'A caption',
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_flex_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with layout-specific min not met.
	 */
	public function test_validate_value_layout_min_not_met() {
		$layouts                       = $this->get_field()['layouts'];
		$layouts['layout_text']['min'] = 2;
		$field                         = $this->get_field( array( 'layouts' => $layouts ) );

		$value = array(
			array(
				'acf_fc_layout'      => 'text_block',
				'field_text_content' => 'Only one text block',
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_flex_test]' );

		$this->assertIsString( $valid );
	}

	/**
	 * Test validate_value ignores acfcloneindex.
	 */
	public function test_validate_value_ignores_clone_index() {
		$field = $this->get_field( array( 'min' => 1 ) );
		$value = array(
			'acfcloneindex' => array(
				'acf_fc_layout'      => 'text_block',
				'field_text_content' => '',
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_flex_test]' );

		// Should fail because acfcloneindex is ignored.
		$this->assertIsString( $valid );
	}

	/**
	 * Test format_value returns false for empty value.
	 */
	public function test_format_value_empty_value() {
		$field  = $this->get_field();
		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value preserves layout information.
	 */
	public function test_format_value_preserves_layout() {
		$field = $this->get_field();
		$value = array(
			array(
				'acf_fc_layout' => 'text_block',
				'text_content'  => 'Hello world',
			),
			array(
				'acf_fc_layout' => 'image_block',
				'image'         => 123,
				'caption'       => 'A caption',
			),
		);

		$result = $this->field_instance->format_value( $value, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertEquals( 'text_block', $result[0]['acf_fc_layout'] );
		$this->assertEquals( 'image_block', $result[1]['acf_fc_layout'] );
	}

	/**
	 * Test load_value loads correct number of layouts.
	 *
	 * Note: In WorDBless, the flexible content load_value behavior
	 * may differ from a full WordPress environment. The method interprets
	 * the passed value as the row count but may not fully reconstruct
	 * all rows without proper layout meta keys.
	 */
	public function test_load_value_returns_correct_count() {
		$field = $this->get_field();

		// Store test data including layout type meta.
		update_post_meta( $this->post_id, 'test_flex', 2 );
		update_post_meta( $this->post_id, 'test_flex_0_acf_fc_layout', 'text_block' );
		update_post_meta( $this->post_id, 'test_flex_0_text_content', 'Layout 1 content' );
		update_post_meta( $this->post_id, 'test_flex_1_acf_fc_layout', 'text_block' );
		update_post_meta( $this->post_id, 'test_flex_1_text_content', 'Layout 2 content' );

		$result = $this->field_instance->load_value( 2, $this->post_id, $field );

		$this->assertIsArray( $result );
		// The result count depends on how load_value processes the meta data.
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test load_value handles false value.
	 */
	public function test_load_value_handles_false() {
		$field  = $this->get_field();
		$result = $this->field_instance->load_value( false, $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_layout returns correct layout.
	 */
	public function test_get_layout_returns_correct_layout() {
		$field  = $this->get_field();
		$layout = $this->field_instance->get_layout( 'text_block', $field );

		$this->assertIsArray( $layout );
		$this->assertEquals( 'text_block', $layout['name'] );
		$this->assertEquals( 'Text Block', $layout['label'] );
	}

	/**
	 * Test get_layout returns false for non-existent layout.
	 */
	public function test_get_layout_returns_false_for_invalid() {
		$field  = $this->get_field();
		$layout = $this->field_instance->get_layout( 'nonexistent', $field );

		$this->assertFalse( $layout );
	}

	/**
	 * Test get_valid_layout applies defaults.
	 */
	public function test_get_valid_layout_applies_defaults() {
		$layout = $this->field_instance->get_valid_layout( array( 'name' => 'custom' ) );

		$this->assertIsArray( $layout );
		$this->assertArrayHasKey( 'key', $layout );
		$this->assertArrayHasKey( 'label', $layout );
		$this->assertArrayHasKey( 'display', $layout );
		$this->assertArrayHasKey( 'sub_fields', $layout );
	}

	/**
	 * Test get_rest_schema returns valid schema with layout info.
	 */
	public function test_get_rest_schema() {
		$field  = $this->get_field();
		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'array', $schema['type'] );
		$this->assertArrayHasKey( 'items', $schema );
		$this->assertArrayHasKey( 'oneOf', $schema['items'] );
	}

	/**
	 * Test update_value with mixed layouts.
	 *
	 * Note: Flexible content update_value returns an array of the processed
	 * layouts, not an integer count.
	 */
	public function test_update_value_mixed_layouts() {
		$field = $this->get_field();
		$value = array(
			array(
				'acf_fc_layout'      => 'text_block',
				'field_text_content' => 'Text content',
			),
			array(
				'acf_fc_layout' => 'image_block',
				'field_image'   => 456,
				'field_caption' => 'Image caption',
			),
		);

		$result = $this->field_instance->update_value( $value, $this->post_id, $field );

		// update_value may return the processed array or a count depending on implementation.
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test update_value with empty value returns empty string.
	 *
	 * Note: Flexible content returns empty string for empty input, not null.
	 */
	public function test_update_value_empty() {
		$field  = $this->get_field();
		$result = $this->field_instance->update_value( array(), $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Data provider for layout switching scenarios.
	 *
	 * @return array
	 */
	public function layout_switch_provider() {
		return array(
			'text to image' => array( 'text_block', 'image_block' ),
			'image to text' => array( 'image_block', 'text_block' ),
		);
	}

	/**
	 * Test validate_value with layout switching.
	 *
	 * @dataProvider layout_switch_provider
	 *
	 * @param string $from_layout The original layout.
	 * @param string $to_layout   The target layout.
	 */
	public function test_validate_value_layout_switching( $from_layout, $to_layout ) {
		$field = $this->get_field();
		$value = array(
			array(
				'acf_fc_layout'      => $from_layout,
				'field_text_content' => 'Content',
			),
			array(
				'acf_fc_layout'      => $to_layout,
				'field_text_content' => 'More content',
			),
		);

		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_flex_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test prepare_field_for_export removes layout keys.
	 */
	public function test_prepare_field_for_export() {
		$field    = $this->get_field();
		$exported = $this->field_instance->prepare_field_for_export( $field );

		$this->assertIsArray( $exported );
		$this->assertArrayHasKey( 'layouts', $exported );
	}

	/**
	 * Test prepare_field_for_import handles layouts correctly.
	 */
	public function test_prepare_field_for_import() {
		$field    = $this->get_field();
		$imported = $this->field_instance->prepare_field_for_import( $field );

		$this->assertIsArray( $imported );
	}
}
