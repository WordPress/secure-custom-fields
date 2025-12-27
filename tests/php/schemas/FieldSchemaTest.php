<?php
/**
 * Tests for the SCF Field JSON Schema validation.
 *
 * @package wordpress/secure-custom-fields
 */

use PHPUnit\Framework\TestCase;

require_once 'BaseSchemaTestCase.php';

/**
 * Class FieldSchemaTest
 *
 * Tests JSON Schema validation for SCF fields.
 * Validates standalone field definitions against field.schema.json.
 */
class FieldSchemaTest extends BaseSchemaTestCase {

	/**
	 * Get the schema type to test.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'field';
	}

	/**
	 * Get the path to the fixtures directory.
	 * Empty string - no fixtures, data providers only.
	 *
	 * @return string
	 */
	protected function get_fixtures_path(): string {
		return '';
	}

	/**
	 * Get the definition name in the schema.
	 *
	 * @return string
	 */
	protected function get_definition_name(): string {
		return 'field';
	}

	/**
	 * Get the required fields for this schema.
	 * Read from schema instead of hardcoding.
	 *
	 * @return array
	 */
	protected function get_required_fields(): array {
		$schema = $this->validator->load_schema( 'field' );
		return $schema->definitions->field->oneOf[0]->required ?? array();
	}

	/**
	 * Override: field schema has nested oneOf in definitions.field.
	 */
	public function test_schema_loads() {
		$schema = $this->validator->load_schema( 'field' );

		$this->assertNotNull( $schema, 'Field schema should load' );
		$this->assertObjectHasProperty( 'oneOf', $schema, 'Schema should have top-level oneOf' );
		$this->assertObjectHasProperty( 'definitions', $schema, 'Schema should have definitions' );
		$this->assertObjectHasProperty( 'field', $schema->definitions, 'Schema should define field' );
		$this->assertObjectHasProperty( 'oneOf', $schema->definitions->field, 'Field definition should have oneOf for type variants' );
	}

	/**
	 * Test that all implemented field types reject unknown properties.
	 * Dynamically extracts implemented types from the schema.
	 */
	public function test_additional_properties_rejected_for_all_implemented_types() {
		$schema = $this->validator->load_schema( 'field' );

		// Extract types from oneOf variants that have additionalProperties: false.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- JSON Schema property name.
		$implemented_types = array();
		foreach ( $schema->definitions->field->oneOf as $variant ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- JSON Schema property name.
			if ( isset( $variant->additionalProperties ) && false === $variant->additionalProperties ) {
				$type = $variant->properties->type->enum[0] ?? null;
				if ( $type ) {
					$implemented_types[] = $type;
				}
			}
		}

		$this->assertNotEmpty( $implemented_types, 'Should have at least one implemented type with additionalProperties: false' );

		foreach ( $implemented_types as $type ) {
			$field = array(
				'key'                   => 'field_test_' . $type,
				'label'                 => 'Test ' . ucfirst( $type ),
				'name'                  => 'test_' . $type,
				'type'                  => $type,
				'parent'                => 'group_test',
				'unknown_fake_property' => 'should_fail',
			);

			$result = $this->validator->validate( $field, 'field' );
			$this->assertFalse( $result, "$type field should reject unknown properties" );
		}
	}

	/**
	 * Data provider for valid fields.
	 *
	 * @return array
	 */
	public function validEntitiesProvider(): array {
		return array(
			// Base field tests.
			'array of fields'              => array(
				array(
					array(
						'key'    => 'field_first',
						'label'  => 'First',
						'name'   => 'first',
						'type'   => 'text',
						'parent' => 'group_test',
					),
					array(
						'key'    => 'field_second',
						'label'  => 'Second',
						'name'   => 'second',
						'type'   => 'number',
						'parent' => 'group_test',
					),
				),
				'Array of fields should validate successfully',
			),
			'field with conditional_logic' => array(
				array(
					'key'               => 'field_with_logic',
					'label'             => 'Conditional Field',
					'name'              => 'conditional',
					'type'              => 'text',
					'parent'            => 'group_test',
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
				'Field with conditional logic should be valid',
			),

			// Multi-type property tests (properties accepting different types).
			'maxlength as empty string'    => array(
				array(
					'key'       => 'field_maxlen_str',
					'label'     => 'Maxlength String',
					'name'      => 'maxlen_str',
					'type'      => 'text',
					'parent'    => 'group_test',
					'maxlength' => '',
				),
				'Maxlength as empty string should be valid',
			),
			'required as integer'          => array(
				array(
					'key'      => 'field_req_int',
					'label'    => 'Required Integer',
					'name'     => 'req_int',
					'type'     => 'text',
					'parent'   => 'group_test',
					'required' => 1,
				),
				'Required as integer should be valid',
			),

			// Complete field for each implemented type.
			'text field complete'          => array(
				array(
					'key'           => 'field_text_full',
					'label'         => 'Full Text Field',
					'name'          => 'text_full',
					'type'          => 'text',
					'parent'        => 'group_test',
					'required'      => true,
					'default_value' => 'Default',
					'maxlength'     => 100,
					'placeholder'   => 'Enter text...',
					'prepend'       => '$',
					'append'        => '.00',
				),
				'Text field with all type-specific properties should be valid',
			),
			'textarea field complete'      => array(
				array(
					'key'           => 'field_textarea_full',
					'label'         => 'Full Textarea',
					'name'          => 'textarea_full',
					'type'          => 'textarea',
					'parent'        => 'group_test',
					'default_value' => '',
					'maxlength'     => 500,
					'rows'          => 8,
					'placeholder'   => 'Enter text...',
					'new_lines'     => 'wpautop',
				),
				'Textarea field with all type-specific properties should be valid',
			),
			'number field complete'        => array(
				array(
					'key'           => 'field_number_full',
					'label'         => 'Full Number',
					'name'          => 'number_full',
					'type'          => 'number',
					'parent'        => 'group_test',
					'default_value' => 50,
					'min'           => 0,
					'max'           => 100,
					'step'          => 5,
					'placeholder'   => '0-100',
					'prepend'       => '#',
					'append'        => 'units',
				),
				'Number field with all type-specific properties should be valid',
			),
			'range field complete'         => array(
				array(
					'key'           => 'field_range_full',
					'label'         => 'Full Range',
					'name'          => 'range_full',
					'type'          => 'range',
					'parent'        => 'group_test',
					'default_value' => 50,
					'min'           => 0,
					'max'           => 100,
					'step'          => 10,
					'prepend'       => 'Min',
					'append'        => 'Max',
				),
				'Range field with all type-specific properties should be valid',
			),
			'email field complete'         => array(
				array(
					'key'           => 'field_email_full',
					'label'         => 'Full Email',
					'name'          => 'email_full',
					'type'          => 'email',
					'parent'        => 'group_test',
					'default_value' => '',
					'placeholder'   => 'name@example.com',
					'prepend'       => '@',
					'append'        => '',
				),
				'Email field with all type-specific properties should be valid',
			),
			'url field complete'           => array(
				array(
					'key'           => 'field_url_full',
					'label'         => 'Full URL',
					'name'          => 'url_full',
					'type'          => 'url',
					'parent'        => 'group_test',
					'default_value' => 'https://',
					'placeholder'   => 'https://example.com',
				),
				'URL field with all type-specific properties should be valid',
			),
			'password field complete'      => array(
				array(
					'key'         => 'field_password_full',
					'label'       => 'Full Password',
					'name'        => 'password_full',
					'type'        => 'password',
					'parent'      => 'group_test',
					'placeholder' => 'Enter password...',
					'prepend'     => 'Key:',
					'append'      => '',
				),
				'Password field with all type-specific properties should be valid',
			),
		);
	}

	/**
	 * Data provider for invalid fields.
	 *
	 * @return array
	 */
	public function invalidEntitiesProvider(): array {
		return array(
			// Required fields validation.
			'missing key'            => array(
				array(
					'label'  => 'No Key Field',
					'name'   => 'no_key',
					'type'   => 'text',
					'parent' => 'group_test',
				),
				'Field without key should fail validation',
			),
			'missing label'          => array(
				array(
					'key'    => 'field_no_label',
					'name'   => 'no_label',
					'type'   => 'text',
					'parent' => 'group_test',
				),
				'Field without label should fail validation',
			),
			'missing type'           => array(
				array(
					'key'    => 'field_no_type',
					'label'  => 'No Type',
					'name'   => 'no_type',
					'parent' => 'group_test',
				),
				'Field without type should fail validation',
			),
			'missing parent'         => array(
				array(
					'key'   => 'field_no_parent',
					'label' => 'No Parent',
					'name'  => 'no_parent',
					'type'  => 'text',
				),
				'Standalone field without parent should fail validation',
			),

			// Pattern validation.
			'invalid key pattern'    => array(
				array(
					'key'    => 'invalid_field_key',
					'label'  => 'Bad Key Field',
					'name'   => 'bad_key',
					'type'   => 'text',
					'parent' => 'group_test',
				),
				'Field without field_ prefix should fail validation',
			),

			// Enum validation.
			'invalid type'           => array(
				array(
					'key'    => 'field_bad_type',
					'label'  => 'Bad Type Field',
					'name'   => 'bad_type',
					'type'   => 'nonexistent_type',
					'parent' => 'group_test',
				),
				'Field with invalid type should fail validation',
			),
			'invalid new_lines enum' => array(
				array(
					'key'       => 'field_textarea_bad_nl',
					'label'     => 'Bad New Lines',
					'name'      => 'bad_new_lines',
					'type'      => 'textarea',
					'parent'    => 'group_test',
					'new_lines' => 'invalid_value',
				),
				'Textarea with invalid new_lines value should fail validation',
			),
		);
	}
}
