<?php
/**
 * Tests for the SCF Field Group JSON Schema validation.
 *
 * @package wordpress/secure-custom-fields
 */

use PHPUnit\Framework\TestCase;

require_once 'BaseSchemaTestCase.php';

/**
 * Class FieldGroupSchemaTest
 *
 * Tests JSON Schema validation for SCF field groups.
 */
class FieldGroupSchemaTest extends BaseSchemaTestCase {

	/**
	 * Get the schema type to test.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'field-group';
	}

	/**
	 * Get the path to the fixtures directory.
	 *
	 * @return string
	 */
	protected function get_fixtures_path(): string {
		return dirname( __DIR__ ) . '/fixtures/schemas/field-groups/';
	}

	/**
	 * Get the definition name in the schema.
	 *
	 * @return string
	 */
	protected function get_definition_name(): string {
		return 'fieldGroup';
	}

	/**
	 * Get the required fields for this schema.
	 *
	 * @return array
	 */
	protected function get_required_fields(): array {
		return array( 'key', 'title', 'fields' );
	}

	/**
	 * Data provider for valid field groups.
	 *
	 * @return array
	 */
	public function validEntitiesProvider(): array {
		return array(
			'basic valid'                   => array(
				array(
					'key'    => 'group_test123',
					'title'  => 'Test Field Group',
					'fields' => array(),
				),
				'Basic field group should validate successfully',
			),
			'array with two items'          => array(
				array(
					array(
						'key'    => 'group_test123',
						'title'  => 'Test Field Group',
						'fields' => array(),
					),
					array(
						'key'    => 'group_another456',
						'title'  => 'Another Field Group',
						'fields' => array(),
					),
				),
				'Array of two field groups should validate successfully',
			),
			'with location rules'           => array(
				array(
					'key'      => 'group_with_location',
					'title'    => 'Field Group with Location',
					'fields'   => array(),
					'location' => array(
						array(
							array(
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'post',
							),
						),
					),
				),
				'Field group with location rules should be valid',
			),
			'with multiple location groups' => array(
				array(
					'key'      => 'group_multi_location',
					'title'    => 'Multi Location Group',
					'fields'   => array(),
					'location' => array(
						array(
							array(
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'post',
							),
						),
						array(
							array(
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'page',
							),
						),
					),
				),
				'Field group with OR location logic should be valid',
			),
			'with field'                    => array(
				array(
					'key'    => 'group_with_field',
					'title'  => 'Field Group with Field',
					'fields' => array(
						array(
							'key'   => 'field_text_abc',
							'label' => 'Text Field',
							'name'  => 'text_field',
							'type'  => 'text',
						),
					),
				),
				'Field group with a text field should be valid',
			),
			'with all positions'            => array(
				array(
					'key'      => 'group_position_normal',
					'title'    => 'Normal Position',
					'fields'   => array(),
					'position' => 'normal',
				),
				'Field group with normal position should be valid',
			),
			'position side'                 => array(
				array(
					'key'      => 'group_position_side',
					'title'    => 'Side Position',
					'fields'   => array(),
					'position' => 'side',
				),
				'Field group with side position should be valid',
			),
			'position after title'          => array(
				array(
					'key'      => 'group_position_after',
					'title'    => 'After Title Position',
					'fields'   => array(),
					'position' => 'acf_after_title',
				),
				'Field group with acf_after_title position should be valid',
			),
			'style default'                 => array(
				array(
					'key'    => 'group_style_default',
					'title'  => 'Default Style',
					'fields' => array(),
					'style'  => 'default',
				),
				'Field group with default style should be valid',
			),
			'style seamless'                => array(
				array(
					'key'    => 'group_style_seamless',
					'title'  => 'Seamless Style',
					'fields' => array(),
					'style'  => 'seamless',
				),
				'Field group with seamless style should be valid',
			),
			'active as boolean'             => array(
				array(
					'key'    => 'group_active_bool',
					'title'  => 'Active Boolean',
					'fields' => array(),
					'active' => true,
				),
				'Field group with active as boolean should be valid',
			),
			'active as integer'             => array(
				array(
					'key'    => 'group_active_int',
					'title'  => 'Active Integer',
					'fields' => array(),
					'active' => 1,
				),
				'Field group with active as integer should be valid',
			),
			'show_in_rest boolean'          => array(
				array(
					'key'          => 'group_rest_bool',
					'title'        => 'REST Boolean',
					'fields'       => array(),
					'show_in_rest' => true,
				),
				'Field group with show_in_rest as boolean should be valid',
			),
			'show_in_rest integer'          => array(
				array(
					'key'          => 'group_rest_int',
					'title'        => 'REST Integer',
					'fields'       => array(),
					'show_in_rest' => 0,
				),
				'Field group with show_in_rest as integer should be valid',
			),
			'hide_on_screen items'          => array(
				array(
					'key'            => 'group_hide_screen',
					'title'          => 'Hide Screen Elements',
					'fields'         => array(),
					'hide_on_screen' => array( 'the_content', 'excerpt', 'comments' ),
				),
				'Field group with hide_on_screen should be valid',
			),
			'full export format'            => array(
				array(
					'key'                   => 'group_full_export',
					'title'                 => 'Full Export Format',
					'fields'                => array(),
					'location'              => array(),
					'menu_order'            => 5,
					'position'              => 'normal',
					'style'                 => 'default',
					'label_placement'       => 'top',
					'instruction_placement' => 'label',
					'hide_on_screen'        => array(),
					'active'                => true,
					'description'           => 'A test field group',
					'show_in_rest'          => false,
					'display_title'         => 'Custom Title',
					'modified'              => 1700000000,
				),
				'Complete field group with all properties should be valid',
			),
			'field with conditional_logic'  => array(
				array(
					'key'    => 'group_conditional',
					'title'  => 'Conditional Group',
					'fields' => array(
						array(
							'key'               => 'field_toggle',
							'label'             => 'Toggle',
							'name'              => 'toggle',
							'type'              => 'true_false',
							'conditional_logic' => 0,
						),
						array(
							'key'               => 'field_dependent',
							'label'             => 'Dependent',
							'name'              => 'dependent',
							'type'              => 'text',
							'conditional_logic' => array(
								array(
									array(
										'field'    => 'field_toggle',
										'operator' => '==',
										'value'    => '1',
									),
								),
							),
						),
					),
				),
				'Field group with conditional logic should be valid',
			),
			'field required as boolean'     => array(
				array(
					'key'    => 'group_req_bool',
					'title'  => 'Required Boolean',
					'fields' => array(
						array(
							'key'      => 'field_req_bool',
							'label'    => 'Required Field',
							'name'     => 'required_field',
							'type'     => 'text',
							'required' => true,
						),
					),
				),
				'Field with required as boolean should be valid',
			),
			'field required as integer'     => array(
				array(
					'key'    => 'group_req_int',
					'title'  => 'Required Integer',
					'fields' => array(
						array(
							'key'      => 'field_req_int',
							'label'    => 'Required Field',
							'name'     => 'required_field',
							'type'     => 'text',
							'required' => 1,
						),
					),
				),
				'Field with required as integer should be valid',
			),
		);
	}

	/**
	 * Data provider for invalid field groups.
	 *
	 * @return array
	 */
	public function invalidEntitiesProvider(): array {
		return array(
			'missing key'               => array(
				array(
					'title'  => 'Missing Key',
					'fields' => array(),
				),
				'Field group missing key should fail validation',
			),
			'missing title'             => array(
				array(
					'key'    => 'group_missing_title',
					'fields' => array(),
				),
				'Field group missing title should fail validation',
			),
			'missing fields'            => array(
				array(
					'key'   => 'group_missing_fields',
					'title' => 'Missing Fields',
				),
				'Field group missing fields should fail validation',
			),
			'empty key'                 => array(
				array(
					'key'    => '',
					'title'  => 'Empty Key',
					'fields' => array(),
				),
				'Field group with empty key should fail validation',
			),
			'invalid key pattern'       => array(
				array(
					'key'    => 'invalid_key_without_prefix',
					'title'  => 'Invalid Key',
					'fields' => array(),
				),
				'Field group without group_ prefix should fail validation',
			),
			'additional properties'     => array(
				array(
					'key'              => 'group_extra_props',
					'title'            => 'Extra Properties',
					'fields'           => array(),
					'invalid_property' => 'some value',
				),
				'Field group with additional properties should fail validation',
			),
			'invalid position'          => array(
				array(
					'key'      => 'group_bad_position',
					'title'    => 'Bad Position',
					'fields'   => array(),
					'position' => 'invalid_position',
				),
				'Field group with invalid position should fail validation',
			),
			'invalid style'             => array(
				array(
					'key'    => 'group_bad_style',
					'title'  => 'Bad Style',
					'fields' => array(),
					'style'  => 'invalid_style',
				),
				'Field group with invalid style should fail validation',
			),
			'field missing key'         => array(
				array(
					'key'    => 'group_field_no_key',
					'title'  => 'Field Missing Key',
					'fields' => array(
						array(
							'label' => 'No Key Field',
							'name'  => 'no_key',
							'type'  => 'text',
						),
					),
				),
				'Field without key should fail validation',
			),
			'field invalid key'         => array(
				array(
					'key'    => 'group_field_bad_key',
					'title'  => 'Field Bad Key',
					'fields' => array(
						array(
							'key'   => 'invalid_field_key',
							'label' => 'Bad Key Field',
							'name'  => 'bad_key',
							'type'  => 'text',
						),
					),
				),
				'Field without field_ prefix should fail validation',
			),
			'field invalid type'        => array(
				array(
					'key'    => 'group_field_bad_type',
					'title'  => 'Field Bad Type',
					'fields' => array(
						array(
							'key'   => 'field_bad_type',
							'label' => 'Bad Type Field',
							'name'  => 'bad_type',
							'type'  => 'nonexistent_type',
						),
					),
				),
				'Field with invalid type should fail validation',
			),
			'invalid hide_on_screen'    => array(
				array(
					'key'            => 'group_bad_hide',
					'title'          => 'Bad Hide Screen',
					'fields'         => array(),
					'hide_on_screen' => array( 'invalid_element' ),
				),
				'Field group with invalid hide_on_screen value should fail validation',
			),
			'location missing param'    => array(
				array(
					'key'      => 'group_loc_no_param',
					'title'    => 'Location No Param',
					'fields'   => array(),
					'location' => array(
						array(
							array(
								'operator' => '==',
								'value'    => 'post',
							),
						),
					),
				),
				'Location rule missing param should fail validation',
			),
			'location invalid operator' => array(
				array(
					'key'      => 'group_loc_bad_op',
					'title'    => 'Location Bad Operator',
					'fields'   => array(),
					'location' => array(
						array(
							array(
								'param'    => 'post_type',
								'operator' => '===',
								'value'    => 'post',
							),
						),
					),
				),
				'Location rule with invalid operator should fail validation',
			),
		);
	}
}
