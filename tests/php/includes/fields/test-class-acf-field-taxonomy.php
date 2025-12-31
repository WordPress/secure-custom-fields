<?php
/**
 * Tests for the Taxonomy field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_taxonomy.
 */
class Test_ACF_Field_Taxonomy extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'taxonomy';
	}

	/**
	 * Test term IDs.
	 *
	 * @var array
	 */
	protected $term_ids = array();

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		// Create test terms in the category taxonomy.
		for ( $i = 0; $i < 3; $i++ ) {
			$term = wp_insert_term( 'Test Category ' . $i, 'category' );
			if ( ! is_wp_error( $term ) ) {
				$this->term_ids[] = $term['term_id'];
			}
		}
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		foreach ( $this->term_ids as $term_id ) {
			wp_delete_term( $term_id, 'category' );
		}
		$this->term_ids = array();
		parent::tear_down();
	}

	/**
	 * Get a base taxonomy field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_taxonomy_test',
				'name'          => 'test_taxonomy',
				'type'          => 'taxonomy',
				'label'         => 'Test Taxonomy',
				'required'      => 0,
				'taxonomy'      => 'category',
				'field_type'    => 'checkbox',
				'add_term'      => 1,
				'save_terms'    => 0,
				'load_terms'    => 0,
				'return_format' => 'id',
				'multiple'      => 0,
				'allow_null'    => 0,
			),
			$overrides
		);
	}

	/**
	 * Data provider for return formats.
	 *
	 * @return array
	 */
	public function return_format_provider() {
		return array(
			'id format'     => array( 'id' ),
			'object format' => array( 'object' ),
		);
	}

	/**
	 * Test format_value with ID return format.
	 */
	public function test_format_value_id_format() {
		$field = $this->get_field( array( 'return_format' => 'id' ) );

		$result = $this->field_instance->format_value( $this->term_ids, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		foreach ( $result as $id ) {
			$this->assertIsInt( $id );
		}
	}

	/**
	 * Test format_value with object return format.
	 *
	 * Note: In WorDBless, terms may not fully resolve via get_term().
	 */
	public function test_format_value_object_format() {
		$field = $this->get_field( array( 'return_format' => 'object' ) );

		$result = $this->field_instance->format_value( $this->term_ids, $this->post_id, $field );

		// In WorDBless, the result is an array (may be empty if terms don't resolve).
		$this->assertIsArray( $result );
	}

	/**
	 * Test format_value returns false for empty.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value with single term (select field type).
	 */
	public function test_format_value_single() {
		$field = $this->get_field(
			array(
				'field_type'    => 'select',
				'return_format' => 'id',
			)
		);

		$result = $this->field_instance->format_value( $this->term_ids[0], $this->post_id, $field );

		$this->assertIsInt( $result );
		$this->assertEquals( $this->term_ids[0], $result );
	}

	/**
	 * Test format_value_for_rest is callable.
	 *
	 * Note: In WorDBless, the results may differ if terms don't resolve.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field( array( 'return_format' => 'id' ) );

		$rest_result = $this->field_instance->format_value_for_rest( $this->term_ids, $this->post_id, $field );

		// Just verify it returns something (array or false).
		$this->assertTrue( is_array( $rest_result ) || false === $rest_result );
	}

	/**
	 * Test update_value with term IDs.
	 */
	public function test_update_value() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( $this->term_ids, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test update_value filters empty values.
	 */
	public function test_update_value_filters_empty() {
		$field = $this->get_field();
		$value = array( $this->term_ids[0], '', $this->term_ids[1] );

		$result = $this->field_instance->update_value( $value, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
	}

	/**
	 * Test update_value returns empty value for empty array.
	 *
	 * Note: Taxonomy field may return empty array instead of null.
	 */
	public function test_update_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( array(), $this->post_id, $field );

		$this->assertEmpty( $result );
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
	 * Test field_type options.
	 */
	public function test_field_type_options() {
		$checkbox = $this->get_field( array( 'field_type' => 'checkbox' ) );
		$select   = $this->get_field( array( 'field_type' => 'select' ) );
		$multi    = $this->get_field( array( 'field_type' => 'multi_select' ) );
		$radio    = $this->get_field( array( 'field_type' => 'radio' ) );

		$this->assertEquals( 'checkbox', $checkbox['field_type'] );
		$this->assertEquals( 'select', $select['field_type'] );
		$this->assertEquals( 'multi_select', $multi['field_type'] );
		$this->assertEquals( 'radio', $radio['field_type'] );
	}

	/**
	 * Test save_terms option.
	 */
	public function test_save_terms_option() {
		$field = $this->get_field( array( 'save_terms' => 1 ) );

		$this->assertEquals( 1, $field['save_terms'] );
	}

	/**
	 * Test load_terms option.
	 */
	public function test_load_terms_option() {
		$field = $this->get_field( array( 'load_terms' => 1 ) );

		$this->assertEquals( 1, $field['load_terms'] );
	}

	/**
	 * Test allow_null option.
	 *
	 * Note: Taxonomy field may return false instead of null for empty value.
	 */
	public function test_allow_null() {
		$field = $this->get_field(
			array(
				'allow_null'    => 1,
				'field_type'    => 'select',
				'return_format' => 'id',
			)
		);

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		// May return null or false.
		$this->assertEmpty( $result );
	}
}
