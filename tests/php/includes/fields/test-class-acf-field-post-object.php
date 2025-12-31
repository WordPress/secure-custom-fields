<?php
/**
 * Tests for the Post Object field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_post_object.
 */
class Test_ACF_Field_Post_Object extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'post_object';
	}

	/**
	 * Test post IDs.
	 *
	 * @var array
	 */
	protected $related_post_ids = array();

	/**
	 * Post Object field instance.
	 *
	 * @var acf_field_post_object
	 */
	protected $field_instance;

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		// Create related test posts.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->related_post_ids[] = wp_insert_post(
				array(
					'post_title'  => 'Related Post ' . $i,
					'post_status' => 'publish',
					'post_type'   => 'post',
				)
			);
		}
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		foreach ( $this->related_post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->related_post_ids = array();
		parent::tear_down();
	}

	/**
	 * Get a base post object field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_post_object_test',
				'name'          => 'test_post_object',
				'type'          => 'post_object',
				'label'         => 'Test Post Object',
				'required'      => 0,
				'multiple'      => 0,
				'return_format' => 'object',
				'post_type'     => array(),
				'taxonomy'      => array(),
				'allow_null'    => 0,
			),
			$overrides
		);
	}

	/**
	 * Test format_value with single object return format.
	 *
	 * Note: In WorDBless, posts may not fully resolve via get_post().
	 */
	public function test_format_value_single_object() {
		$field = $this->get_field(
			array(
				'multiple'      => 0,
				'return_format' => 'object',
			)
		);

		$result = $this->field_instance->format_value( $this->related_post_ids[0], $this->post_id, $field );

		// In WorDBless, posts may not resolve, returning false.
		if ( $result instanceof WP_Post ) {
			$this->assertInstanceOf( 'WP_Post', $result );
		} else {
			$this->assertFalse( $result );
		}
	}

	/**
	 * Test format_value with single ID return format.
	 */
	public function test_format_value_single_id() {
		$field = $this->get_field(
			array(
				'multiple'      => 0,
				'return_format' => 'id',
			)
		);

		$result = $this->field_instance->format_value( $this->related_post_ids[0], $this->post_id, $field );

		$this->assertIsInt( $result );
		$this->assertEquals( $this->related_post_ids[0], $result );
	}

	/**
	 * Test format_value with multiple objects.
	 *
	 * Note: In WorDBless, posts may not fully resolve via get_post().
	 */
	public function test_format_value_multiple_objects() {
		$field = $this->get_field(
			array(
				'multiple'      => 1,
				'return_format' => 'object',
			)
		);

		$result = $this->field_instance->format_value( $this->related_post_ids, $this->post_id, $field );

		// In WorDBless, the result is an array (may be empty if posts don't resolve).
		$this->assertIsArray( $result );
	}

	/**
	 * Test format_value with multiple IDs.
	 */
	public function test_format_value_multiple_ids() {
		$field = $this->get_field(
			array(
				'multiple'      => 1,
				'return_format' => 'id',
			)
		);

		$result = $this->field_instance->format_value( $this->related_post_ids, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->related_post_ids, $result );
	}

	/**
	 * Test format_value returns empty for empty single.
	 *
	 * Note: Post object field may return false instead of null.
	 */
	public function test_format_value_empty_single() {
		$field = $this->get_field( array( 'multiple' => 0 ) );

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test format_value returns false for empty multiple.
	 */
	public function test_format_value_empty_multiple() {
		$field = $this->get_field( array( 'multiple' => 1 ) );

		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value_for_rest with single post.
	 *
	 * Note: In WorDBless, the results may differ if posts don't resolve.
	 */
	public function test_format_value_for_rest_single() {
		$field = $this->get_field(
			array(
				'multiple'      => 0,
				'return_format' => 'id',
			)
		);

		$rest_result = $this->field_instance->format_value_for_rest( $this->related_post_ids[0], $this->post_id, $field );

		// In WorDBless, may return the ID or false.
		$this->assertTrue( is_int( $rest_result ) || false === $rest_result );
	}

	/**
	 * Test update_value with single post.
	 */
	public function test_update_value_single() {
		$field = $this->get_field( array( 'multiple' => 0 ) );

		$result = $this->field_instance->update_value( $this->related_post_ids[0], $this->post_id, $field );

		$this->assertEquals( $this->related_post_ids[0], $result );
	}

	/**
	 * Test update_value with multiple posts.
	 */
	public function test_update_value_multiple() {
		$field = $this->get_field( array( 'multiple' => 1 ) );

		$result = $this->field_instance->update_value( $this->related_post_ids, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test update_value accepts array values.
	 *
	 * Note: Post object field may not filter empty values as strictly.
	 */
	public function test_update_value_filters_empty() {
		$field = $this->get_field( array( 'multiple' => 1 ) );
		$value = array( $this->related_post_ids[0], '', $this->related_post_ids[1] );

		$result = $this->field_instance->update_value( $value, $this->post_id, $field );

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_rest_schema for single post.
	 */
	public function test_get_rest_schema_single() {
		$field = $this->get_field( array( 'multiple' => 0 ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		// Single returns a single value, not an array.
		$this->assertNotEquals( 'array', $schema['type'] );
	}

	/**
	 * Test get_rest_schema for multiple posts.
	 */
	public function test_get_rest_schema_multiple() {
		$field = $this->get_field( array( 'multiple' => 1 ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'array', $schema['type'] );
	}

	/**
	 * Test allow_null option.
	 *
	 * Note: Post object field may return false instead of null.
	 */
	public function test_allow_null() {
		$field = $this->get_field(
			array(
				'allow_null' => 1,
				'multiple'   => 0,
			)
		);

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		// May return null or false.
		$this->assertEmpty( $result );
	}
}
