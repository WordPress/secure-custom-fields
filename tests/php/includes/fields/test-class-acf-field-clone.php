<?php
/**
 * Tests for acf_field_clone class.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Test acf_field_clone functionality.
 */
class Test_ACF_Field_Clone extends Abstract_ACF_Field_Test {

	/**
	 * Clone field instance.
	 *
	 * @var acf_field_clone
	 */
	private $clone_field;

	/**
	 * Get the field type for this test class.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'clone';
	}

	/**
	 * Get a base clone field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'          => 'field_clone_test',
				'name'         => 'test_clone',
				'type'         => 'clone',
				'label'        => 'Test Clone',
				'required'     => 0,
				'display'      => 'seamless',
				'clone'        => array(),
				'prefix_label' => 0,
				'prefix_name'  => 0,
			),
			$overrides
		);
	}

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		// Create a new instance of the clone field.
		$this->clone_field = new acf_field_clone();
	}

	/**
	 * Test acf_get_fields filter handles valid fields array.
	 */
	public function test_acf_get_fields_handles_valid_fields() {
		$fields = array(
			array(
				'key'   => 'field_123',
				'name'  => 'text_field',
				'type'  => 'text',
				'label' => 'Text Field',
			),
			array(
				'key'   => 'field_456',
				'name'  => 'number_field',
				'type'  => 'number',
				'label' => 'Number Field',
			),
		);

		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertEquals( 'text', $result[0]['type'] );
		$this->assertEquals( 'number', $result[1]['type'] );
	}

	/**
	 * Test acf_get_fields filter handles invalid entries without PHP warnings.
	 *
	 * This tests the fix for PHP warnings reported at:
	 * https://wordpress.org/support/topic/php-warnings-207/
	 *
	 * - "Trying to access array offset on null"
	 * - "Undefined array key"
	 */
	public function test_acf_get_fields_handles_invalid_entries() {
		$fields = array(
			array(
				'key'   => 'field_123',
				'name'  => 'text_field',
				'type'  => 'text',
				'label' => 'Text Field',
			),
			null,             // Invalid null entry.
			'invalid_string', // Invalid string entry.
			false,            // Invalid boolean entry.
			42,               // Invalid integer entry.
			array(
				'key'   => 'field_456',
				'name'  => 'number_field',
				'type'  => 'number',
				'label' => 'Number Field',
			),
		);

		// This should not trigger PHP warnings.
		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertIsArray( $result );
	}

	/**
	 * Test acf_get_fields filter processes seamless clone fields correctly.
	 */
	public function test_acf_get_fields_expands_seamless_clone_fields() {
		$fields = array(
			array(
				'key'        => 'field_clone_1',
				'name'       => 'my_clone',
				'type'       => 'clone',
				'label'      => 'Clone Field',
				'display'    => 'seamless',
				'sub_fields' => array(
					array(
						'key'   => 'field_sub_1',
						'name'  => 'sub_text',
						'type'  => 'text',
						'label' => 'Sub Text',
					),
					array(
						'key'   => 'field_sub_2',
						'name'  => 'sub_number',
						'type'  => 'number',
						'label' => 'Sub Number',
					),
				),
			),
		);

		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertIsArray( $result );
		// Seamless clone should be replaced with its sub_fields.
		$this->assertCount( 2, $result );
		$this->assertEquals( 'text', $result[0]['type'] );
		$this->assertEquals( 'number', $result[1]['type'] );
	}

	/**
	 * Test acf_get_fields filter expands every seamless clone field in the list.
	 *
	 * Expanding the first clone makes the list longer, which used to push the
	 * later clones past the loop bound and leave them unexpanded.
	 *
	 * @see https://github.com/WordPress/secure-custom-fields/issues/589
	 */
	public function test_acf_get_fields_expands_multiple_seamless_clone_fields() {
		$fields = array(
			array(
				'key'        => 'field_clone_1',
				'name'       => 'first_clone',
				'type'       => 'clone',
				'label'      => 'First Clone',
				'display'    => 'seamless',
				'sub_fields' => array(
					array(
						'key'   => 'field_sub_1',
						'name'  => 'sub_text_1',
						'type'  => 'text',
						'label' => 'Sub Text 1',
					),
					array(
						'key'   => 'field_sub_2',
						'name'  => 'sub_text_2',
						'type'  => 'text',
						'label' => 'Sub Text 2',
					),
				),
			),
			array(
				'key'        => 'field_clone_2',
				'name'       => 'second_clone',
				'type'       => 'clone',
				'label'      => 'Second Clone',
				'display'    => 'seamless',
				'sub_fields' => array(
					array(
						'key'   => 'field_sub_3',
						'name'  => 'sub_text_3',
						'type'  => 'text',
						'label' => 'Sub Text 3',
					),
					array(
						'key'   => 'field_sub_4',
						'name'  => 'sub_number_4',
						'type'  => 'number',
						'label' => 'Sub Number 4',
					),
				),
			),
		);

		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertSame(
			array( 'field_sub_1', 'field_sub_2', 'field_sub_3', 'field_sub_4' ),
			wp_list_pluck( $result, 'key' )
		);
	}

	/**
	 * Test seamless clones are expanded in place when mixed with other fields.
	 *
	 * @see https://github.com/WordPress/secure-custom-fields/issues/589
	 */
	public function test_acf_get_fields_expands_seamless_clones_mixed_with_other_fields() {
		$sub_fields = static function ( $prefix ) {
			return array(
				array(
					'key'   => "field_{$prefix}_a",
					'name'  => "{$prefix}_a",
					'type'  => 'text',
					'label' => "{$prefix} A",
				),
				array(
					'key'   => "field_{$prefix}_b",
					'name'  => "{$prefix}_b",
					'type'  => 'text',
					'label' => "{$prefix} B",
				),
				array(
					'key'   => "field_{$prefix}_c",
					'name'  => "{$prefix}_c",
					'type'  => 'text',
					'label' => "{$prefix} C",
				),
			);
		};

		$fields = array(
			array(
				'key'        => 'field_clone_1',
				'name'       => 'first_clone',
				'type'       => 'clone',
				'label'      => 'First Clone',
				'display'    => 'seamless',
				'sub_fields' => $sub_fields( 'one' ),
			),
			array(
				'key'   => 'field_title',
				'name'  => 'title',
				'type'  => 'text',
				'label' => 'Title',
			),
			array(
				'key'        => 'field_clone_2',
				'name'       => 'second_clone',
				'type'       => 'clone',
				'label'      => 'Second Clone',
				'display'    => 'seamless',
				'sub_fields' => $sub_fields( 'two' ),
			),
			array(
				'key'        => 'field_clone_group',
				'name'       => 'group_clone',
				'type'       => 'clone',
				'label'      => 'Group Clone',
				'display'    => 'group',
				'sub_fields' => $sub_fields( 'grouped' ),
			),
			array(
				'key'        => 'field_clone_3',
				'name'       => 'third_clone',
				'type'       => 'clone',
				'label'      => 'Third Clone',
				'display'    => 'seamless',
				'sub_fields' => $sub_fields( 'three' ),
			),
		);

		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertSame(
			array(
				'field_one_a',
				'field_one_b',
				'field_one_c',
				'field_title',
				'field_two_a',
				'field_two_b',
				'field_two_c',
				'field_clone_group',
				'field_three_a',
				'field_three_b',
				'field_three_c',
			),
			wp_list_pluck( $result, 'key' )
		);
	}

	/**
	 * Test seamless clones expose bidirectional fields at the parent level.
	 */
	public function test_acf_get_fields_exposes_bidirectional_field_from_seamless_clone() {
		$fields = array(
			array(
				'key'        => 'field_clone_bidirectional',
				'name'       => 'bidirectional_clone',
				'type'       => 'clone',
				'label'      => 'Bidirectional Clone',
				'display'    => 'seamless',
				'sub_fields' => array(
					array(
						'key'                  => 'field_cloned_relationship',
						'name'                 => 'cloned_relationship',
						'type'                 => 'relationship',
						'label'                => 'Cloned Relationship',
						'bidirectional'        => true,
						'bidirectional_target' => array( 'field_relationship_target' ),
					),
				),
			),
		);

		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertCount( 1, $result );
		$this->assertSame( 'field_cloned_relationship', $result[0]['key'] );
		$this->assertSame( 'relationship', $result[0]['type'] );
		$this->assertTrue( $result[0]['bidirectional'] );
		$this->assertSame( array( 'field_relationship_target' ), $result[0]['bidirectional_target'] );
	}

	/**
	 * Test acf_get_fields filter does not expand group display clone fields.
	 */
	public function test_acf_get_fields_preserves_group_display_clone_fields() {
		$fields = array(
			array(
				'key'        => 'field_clone_1',
				'name'       => 'my_clone',
				'type'       => 'clone',
				'label'      => 'Clone Field',
				'display'    => 'group', // Not seamless.
				'sub_fields' => array(
					array(
						'key'   => 'field_sub_1',
						'name'  => 'sub_text',
						'type'  => 'text',
						'label' => 'Sub Text',
					),
				),
			),
		);

		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertIsArray( $result );
		// Group display clone should NOT be replaced.
		$this->assertCount( 1, $result );
		$this->assertEquals( 'clone', $result[0]['type'] );
	}

	/**
	 * Registers the Clone and Password field types. The test bootstrap doesn't load them, and
	 * constructing a field in set_up() replaces the registered Clone type with its info stub.
	 */
	private function ensure_field_types() {
		if ( ! acf_get_field_type( 'clone' ) instanceof acf_field ) {
			acf_register_field_type( $this->clone_field );
		}
		if ( ! class_exists( 'acf_field_password' ) ) {
			acf_include( 'includes/fields/class-acf-field-password.php' );
		}
		if ( ! has_filter( 'acf/format_value/type=password' ) ) {
			acf_register_field_type( 'acf_field_password' );
		}
	}

	/**
	 * A Clone field mapped to a Schema.org property with a Password and a Text sub field.
	 *
	 * @return array
	 */
	private function get_jsonld_field() {
		return $this->get_field(
			array(
				'key'             => 'field_clone_jsonld',
				'name'            => 'clone_jsonld',
				'_name'           => 'clone_jsonld',
				'display'         => 'group',
				'schema_property' => 'Thing.description',
				'sub_fields'      => array(
					array(
						'key'             => 'field_clone_jsonld_field_pwd',
						'__key'           => 'field_pwd',
						'name'            => 'clone_pwd',
						'_name'           => 'clone_pwd',
						'__name'          => 'clone_pwd',
						'type'            => 'password',
						'label'           => 'Stored Password',
						'schema_property' => 'identifier',
					),
					array(
						'key'             => 'field_clone_jsonld_field_text',
						'__key'           => 'field_text',
						'name'            => 'clone_text',
						'_name'           => 'clone_text',
						'__name'          => 'clone_text',
						'type'            => 'text',
						'label'           => 'Text',
						'schema_property' => 'name',
					),
					array(
						'key'    => 'field_clone_jsonld_field_note',
						'__key'  => 'field_note',
						'name'   => 'clone_note',
						'_name'  => 'clone_note',
						'__name' => 'clone_note',
						'type'   => 'text',
						'label'  => 'Unmapped note',
					),
				),
			)
		);
	}

	/**
	 * JSON-LD output of a mapped Clone only carries mapped sub fields, formatted by their own type.
	 */
	public function test_format_value_for_jsonld_outputs_only_mapped_sub_fields() {
		$this->ensure_field_types();

		$field = $this->get_jsonld_field();
		$value = array(
			'field_clone_jsonld_field_pwd'  => 'CLONE_SECRET_123',
			'field_clone_jsonld_field_text' => 'Visible name',
			'field_clone_jsonld_field_note' => 'Not mapped',
		);

		$formatted = \SCF\AI\GEO\GEO::format_field_value_for_jsonld( $value, $field );

		$this->assertSame(
			array(
				'identifier' => false,
				'name'       => 'Visible name',
			),
			$formatted
		);
		$this->assertStringNotContainsString( 'CLONE_SECRET_123', wp_json_encode( $formatted ) );
	}

	/**
	 * A mapped Clone with no mapped sub field values is left out of the JSON-LD output.
	 */
	public function test_format_value_for_jsonld_returns_null_without_mapped_values() {
		$field = $this->get_jsonld_field();

		$this->assertNull( $this->clone_field->format_value_for_jsonld( array(), $this->post_id, $field ) );
		$this->assertNull(
			$this->clone_field->format_value_for_jsonld(
				array( 'field_clone_jsonld_field_note' => 'Not mapped' ),
				$this->post_id,
				$field
			)
		);
	}
}
