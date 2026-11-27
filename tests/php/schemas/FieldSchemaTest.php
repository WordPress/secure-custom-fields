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
			'array of fields'                 => array(
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
			'field with conditional_logic'    => array(
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
			'extension field type'            => array(
				array(
					'key'            => 'field_extension',
					'label'          => 'Extension Field',
					'name'           => 'extension_field',
					'type'           => 'third_party_type',
					'parent'         => 'group_test',
					'custom_setting' => 'preserved',
				),
				'Third-party field types should use the permissive fallback',
			),

			// Multi-type property tests (properties accepting different types).
			'maxlength as empty string'       => array(
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
			'required as integer'             => array(
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
			'text field complete'             => array(
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
			'textarea field complete'         => array(
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
			'number field complete'           => array(
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
			'range field complete'            => array(
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
			'email field complete'            => array(
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
			'url field complete'              => array(
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
			'password field complete'         => array(
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

			// Content fields.
			'image field complete'            => array(
				array(
					'key'           => 'field_image_full',
					'label'         => 'Full Image',
					'name'          => 'image_full',
					'type'          => 'image',
					'parent'        => 'group_test',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'min_width'     => 0,
					'min_height'    => 0,
					'min_size'      => 0,
					'max_width'     => 0,
					'max_height'    => 0,
					'max_size'      => 0,
					'mime_types'    => '',
				),
				'Image field with all type-specific properties should be valid',
			),
			'file field complete'             => array(
				array(
					'key'           => 'field_file_full',
					'label'         => 'Full File',
					'name'          => 'file_full',
					'type'          => 'file',
					'parent'        => 'group_test',
					'return_format' => 'array',
					'library'       => 'all',
					'min_size'      => 0,
					'max_size'      => 0,
					'mime_types'    => '',
				),
				'File field with all type-specific properties should be valid',
			),
			'gallery field complete'          => array(
				array(
					'key'           => 'field_gallery_full',
					'label'         => 'Full Gallery',
					'name'          => 'gallery_full',
					'type'          => 'gallery',
					'parent'        => 'group_test',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'min'           => 0,
					'max'           => 0,
					'min_width'     => 0,
					'min_height'    => 0,
					'min_size'      => 0,
					'max_width'     => 0,
					'max_height'    => 0,
					'max_size'      => 0,
					'mime_types'    => '',
					'insert'        => 'append',
				),
				'Gallery field with all type-specific properties should be valid',
			),
			'wysiwyg field complete'          => array(
				array(
					'key'           => 'field_wysiwyg_full',
					'label'         => 'Full WYSIWYG',
					'name'          => 'wysiwyg_full',
					'type'          => 'wysiwyg',
					'parent'        => 'group_test',
					'default_value' => '',
					'tabs'          => 'all',
					'toolbar'       => 'full',
					'media_upload'  => 1,
					'delay'         => 0,
				),
				'WYSIWYG field with all type-specific properties should be valid',
			),
			'oembed field complete'           => array(
				array(
					'key'    => 'field_oembed_full',
					'label'  => 'Full oEmbed',
					'name'   => 'oembed_full',
					'type'   => 'oembed',
					'parent' => 'group_test',
					'width'  => '',
					'height' => '',
				),
				'oEmbed field with all type-specific properties should be valid',
			),

			// Choice fields.
			'select field complete'           => array(
				array(
					'key'            => 'field_select_full',
					'label'          => 'Full Select',
					'name'           => 'select_full',
					'type'           => 'select',
					'parent'         => 'group_test',
					'choices'        => array(),
					'default_value'  => '',
					'return_format'  => 'value',
					'multiple'       => 0,
					'allow_null'     => 0,
					'placeholder'    => '',
					'ui'             => 1,
					'ajax'           => 0,
					'create_options' => 0,
					'save_options'   => 0,
				),
				'Select field with all type-specific properties should be valid',
			),
			'checkbox field complete'         => array(
				array(
					'key'                       => 'field_checkbox_full',
					'label'                     => 'Full Checkbox',
					'name'                      => 'checkbox_full',
					'type'                      => 'checkbox',
					'parent'                    => 'group_test',
					'choices'                   => array(),
					'default_value'             => '',
					'return_format'             => 'value',
					'layout'                    => 'vertical',
					'toggle'                    => 0,
					'allow_custom'              => 0,
					'save_custom'               => 0,
					'custom_choice_button_text' => '',
				),
				'Checkbox field with all type-specific properties should be valid',
			),
			'radio field complete'            => array(
				array(
					'key'               => 'field_radio_full',
					'label'             => 'Full Radio',
					'name'              => 'radio_full',
					'type'              => 'radio',
					'parent'            => 'group_test',
					'choices'           => array(),
					'default_value'     => '',
					'return_format'     => 'value',
					'layout'            => 'vertical',
					'allow_null'        => 0,
					'other_choice'      => 0,
					'save_other_choice' => 0,
				),
				'Radio field with all type-specific properties should be valid',
			),
			'button_group field complete'     => array(
				array(
					'key'           => 'field_button_group_full',
					'label'         => 'Full Button Group',
					'name'          => 'button_group_full',
					'type'          => 'button_group',
					'parent'        => 'group_test',
					'choices'       => array(),
					'default_value' => '',
					'return_format' => 'value',
					'layout'        => 'horizontal',
					'allow_null'    => 0,
				),
				'Button Group field with all type-specific properties should be valid',
			),
			'true_false field complete'       => array(
				array(
					'key'           => 'field_true_false_full',
					'label'         => 'Full True/False',
					'name'          => 'true_false_full',
					'type'          => 'true_false',
					'parent'        => 'group_test',
					'default_value' => 0,
					'message'       => 'Toggle this option',
					'ui'            => 1,
					'ui_on_text'    => 'Yes',
					'ui_off_text'   => 'No',
				),
				'True/False field with all type-specific properties should be valid',
			),
			'nav_menu field complete'         => array(
				array(
					'key'         => 'field_nav_menu_full',
					'label'       => 'Full Nav Menu',
					'name'        => 'nav_menu_full',
					'type'        => 'nav_menu',
					'parent'      => 'group_test',
					'allow_null'  => 0,
					'save_format' => 'id',
					'container'   => 'div',
				),
				'Nav Menu field with all type-specific properties should be valid',
			),

			// Relational fields.
			'post_object field complete'      => array(
				array(
					'key'                  => 'field_post_object_full',
					'label'                => 'Full Post Object',
					'name'                 => 'post_object_full',
					'type'                 => 'post_object',
					'parent'               => 'group_test',
					'post_type'            => array(),
					'taxonomy'             => array(),
					'allow_null'           => 0,
					'multiple'             => 0,
					'return_format'        => 'object',
					'ui'                   => 1,
					'bidirectional_target' => array(),
				),
				'Post Object field with all type-specific properties should be valid',
			),
			'page_link field complete'        => array(
				array(
					'key'            => 'field_page_link_full',
					'label'          => 'Full Page Link',
					'name'           => 'page_link_full',
					'type'           => 'page_link',
					'parent'         => 'group_test',
					'post_type'      => array(),
					'taxonomy'       => array(),
					'allow_null'     => 0,
					'multiple'       => 0,
					'allow_archives' => 1,
				),
				'Page Link field with all type-specific properties should be valid',
			),
			'relationship field complete'     => array(
				array(
					'key'                  => 'field_relationship_full',
					'label'                => 'Full Relationship',
					'name'                 => 'relationship_full',
					'type'                 => 'relationship',
					'parent'               => 'group_test',
					'post_type'            => array(),
					'taxonomy'             => array(),
					'min'                  => 0,
					'max'                  => 5,
					'filters'              => array( 'search', 'post_type' ),
					'elements'             => array(),
					'return_format'        => 'object',
					'bidirectional_target' => array(),
				),
				'Relationship field with all type-specific properties should be valid',
			),
			'taxonomy field complete'         => array(
				array(
					'key'                  => 'field_taxonomy_full',
					'label'                => 'Full Taxonomy',
					'name'                 => 'taxonomy_full',
					'type'                 => 'taxonomy',
					'parent'               => 'group_test',
					'taxonomy'             => 'category',
					'field_type'           => 'checkbox',
					'multiple'             => 0,
					'allow_null'           => 0,
					'return_format'        => 'id',
					'add_term'             => 1,
					'load_terms'           => 0,
					'save_terms'           => 0,
					'bidirectional_target' => array(),
				),
				'Taxonomy field with all type-specific properties should be valid',
			),
			'user field complete'             => array(
				array(
					'key'                  => 'field_user_full',
					'label'                => 'Full User',
					'name'                 => 'user_full',
					'type'                 => 'user',
					'parent'               => 'group_test',
					'role'                 => '',
					'multiple'             => 0,
					'allow_null'           => 0,
					'return_format'        => 'array',
					'bidirectional_target' => array(),
				),
				'User field with all type-specific properties should be valid',
			),
			'link field complete'             => array(
				array(
					'key'           => 'field_link_full',
					'label'         => 'Full Link',
					'name'          => 'link_full',
					'type'          => 'link',
					'parent'        => 'group_test',
					'return_format' => 'array',
				),
				'Link field with all type-specific properties should be valid',
			),

			// Advanced fields.
			'date_picker field complete'      => array(
				array(
					'key'                     => 'field_date_picker_full',
					'label'                   => 'Full Date Picker',
					'name'                    => 'date_picker_full',
					'type'                    => 'date_picker',
					'parent'                  => 'group_test',
					'display_format'          => 'd/m/Y',
					'return_format'           => 'd/m/Y',
					'first_day'               => 1,
					'default_to_current_date' => 0,
				),
				'Date Picker field with all type-specific properties should be valid',
			),
			'date_time_picker field complete' => array(
				array(
					'key'                     => 'field_date_time_picker_full',
					'label'                   => 'Full Date Time Picker',
					'name'                    => 'date_time_picker_full',
					'type'                    => 'date_time_picker',
					'parent'                  => 'group_test',
					'display_format'          => 'd/m/Y g:i a',
					'return_format'           => 'd/m/Y g:i a',
					'first_day'               => 1,
					'default_to_current_date' => 0,
				),
				'Date Time Picker field with all type-specific properties should be valid',
			),
			'time_picker field complete'      => array(
				array(
					'key'            => 'field_time_picker_full',
					'label'          => 'Full Time Picker',
					'name'           => 'time_picker_full',
					'type'           => 'time_picker',
					'parent'         => 'group_test',
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				'Time Picker field with all type-specific properties should be valid',
			),
			'color_picker field complete'     => array(
				array(
					'key'                   => 'field_color_picker_full',
					'label'                 => 'Full Color Picker',
					'name'                  => 'color_picker_full',
					'type'                  => 'color_picker',
					'parent'                => 'group_test',
					'default_value'         => '',
					'enable_opacity'        => false,
					'custom_palette_source' => '',
					'palette_colors'        => '',
					'show_color_wheel'      => true,
					'return_format'         => 'string',
				),
				'Color Picker field with all type-specific properties should be valid',
			),
			'icon_picker field complete'      => array(
				array(
					'key'           => 'field_icon_picker_full',
					'label'         => 'Full Icon Picker',
					'name'          => 'icon_picker_full',
					'type'          => 'icon_picker',
					'parent'        => 'group_test',
					'library'       => 'all',
					'tabs'          => array( 'dashicons', 'media_library', 'url' ),
					'return_format' => 'string',
					'default_value' => array(
						'type'  => null,
						'value' => null,
					),
				),
				'Icon Picker field with all type-specific properties should be valid',
			),
			'google_map field complete'       => array(
				array(
					'key'        => 'field_google_map_full',
					'label'      => 'Full Google Map',
					'name'       => 'google_map_full',
					'type'       => 'google_map',
					'parent'     => 'group_test',
					'height'     => '',
					'center_lat' => '',
					'center_lng' => '',
					'zoom'       => '',
				),
				'Google Map field with all type-specific properties should be valid',
			),

			// Layout fields.
			'group field complete'            => array(
				array(
					'key'        => 'field_group_full',
					'label'      => 'Full Group',
					'name'       => 'group_full',
					'type'       => 'group',
					'parent'     => 'group_test',
					'sub_fields' => array(),
					'layout'     => 'block',
				),
				'Group field with all type-specific properties should be valid',
			),
			'repeater field complete'         => array(
				array(
					'key'           => 'field_repeater_full',
					'label'         => 'Full Repeater',
					'name'          => 'repeater_full',
					'type'          => 'repeater',
					'parent'        => 'group_test',
					'sub_fields'    => array(),
					'min'           => 0,
					'max'           => 0,
					'layout'        => 'table',
					'button_label'  => '',
					'rows_per_page' => 20,
					'collapsed'     => '',
				),
				'Repeater field with all type-specific properties should be valid',
			),
			'flexible_content field complete' => array(
				array(
					'key'          => 'field_flexible_content_full',
					'label'        => 'Full Flexible Content',
					'name'         => 'flexible_content_full',
					'type'         => 'flexible_content',
					'parent'       => 'group_test',
					'layouts'      => array(),
					'min'          => '',
					'max'          => '',
					'button_label' => 'Add Row',
				),
				'Flexible Content field with all type-specific properties should be valid',
			),
			'tab field complete'              => array(
				array(
					'key'       => 'field_tab_full',
					'label'     => 'Full Tab',
					'name'      => 'tab_full',
					'type'      => 'tab',
					'parent'    => 'group_test',
					'placement' => 'top',
					'endpoint'  => 0,
					'selected'  => 0,
				),
				'Tab field with all type-specific properties should be valid',
			),
			'accordion field complete'        => array(
				array(
					'key'          => 'field_accordion_full',
					'label'        => 'Full Accordion',
					'name'         => 'accordion_full',
					'type'         => 'accordion',
					'parent'       => 'group_test',
					'open'         => 0,
					'multi_expand' => 0,
					'endpoint'     => 0,
				),
				'Accordion field with all type-specific properties should be valid',
			),
			'message field complete'          => array(
				array(
					'key'       => 'field_message_full',
					'label'     => 'Full Message',
					'name'      => 'message_full',
					'type'      => 'message',
					'parent'    => 'group_test',
					'message'   => 'Hello world',
					'esc_html'  => 0,
					'new_lines' => 'wpautop',
				),
				'Message field with all type-specific properties should be valid',
			),
			'clone field complete'            => array(
				array(
					'key'          => 'field_clone_full',
					'label'        => 'Full Clone',
					'name'         => 'clone_full',
					'type'         => 'clone',
					'parent'       => 'group_test',
					'clone'        => '',
					'prefix_label' => 0,
					'prefix_name'  => 0,
					'display'      => 'seamless',
					'layout'       => 'block',
				),
				'Clone field with all type-specific properties should be valid',
			),
			'separator field complete'        => array(
				array(
					'key'    => 'field_separator_full',
					'label'  => 'Full Separator',
					'name'   => 'separator_full',
					'type'   => 'separator',
					'parent' => 'group_test',
				),
				'Separator field with all type-specific properties should be valid',
			),
			'output field complete'           => array(
				array(
					'key'    => 'field_output_full',
					'label'  => 'Full Output',
					'name'   => 'output_full',
					'type'   => 'output',
					'parent' => 'group_test',
					'html'   => true,
				),
				'Output field with all type-specific properties should be valid',
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
			'missing key'                              => array(
				array(
					'label'  => 'No Key Field',
					'name'   => 'no_key',
					'type'   => 'text',
					'parent' => 'group_test',
				),
				'Field without key should fail validation',
			),
			'missing label'                            => array(
				array(
					'key'    => 'field_no_label',
					'name'   => 'no_label',
					'type'   => 'text',
					'parent' => 'group_test',
				),
				'Field without label should fail validation',
			),
			'missing type'                             => array(
				array(
					'key'    => 'field_no_type',
					'label'  => 'No Type',
					'name'   => 'no_type',
					'parent' => 'group_test',
				),
				'Field without type should fail validation',
			),
			'missing parent'                           => array(
				array(
					'key'   => 'field_no_parent',
					'label' => 'No Parent',
					'name'  => 'no_parent',
					'type'  => 'text',
				),
				'Standalone field without parent should fail validation',
			),

			// Pattern validation.
			'invalid key pattern'                      => array(
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
			'invalid new_lines enum'                   => array(
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

			// Content field validation.
			'invalid image return_format'              => array(
				array(
					'key'           => 'field_image_bad',
					'label'         => 'Bad Image',
					'name'          => 'bad_image',
					'type'          => 'image',
					'parent'        => 'group_test',
					'return_format' => 'invalid_format',
				),
				'Image with invalid return_format should fail validation',
			),
			'invalid image library'                    => array(
				array(
					'key'     => 'field_image_bad_lib',
					'label'   => 'Bad Image Library',
					'name'    => 'bad_image_lib',
					'type'    => 'image',
					'parent'  => 'group_test',
					'library' => 'invalid_library',
				),
				'Image with invalid library should fail validation',
			),
			'invalid gallery insert'                   => array(
				array(
					'key'    => 'field_gallery_bad_insert',
					'label'  => 'Bad Gallery Insert',
					'name'   => 'bad_gallery_insert',
					'type'   => 'gallery',
					'parent' => 'group_test',
					'insert' => 'invalid_insert',
				),
				'Gallery with invalid insert should fail validation',
			),
			'invalid wysiwyg tabs'                     => array(
				array(
					'key'    => 'field_wysiwyg_bad_tabs',
					'label'  => 'Bad WYSIWYG Tabs',
					'name'   => 'bad_wysiwyg_tabs',
					'type'   => 'wysiwyg',
					'parent' => 'group_test',
					'tabs'   => 'invalid_tabs',
				),
				'WYSIWYG with invalid tabs should fail validation',
			),

			// Choice field validation.
			'invalid select return_format'             => array(
				array(
					'key'           => 'field_select_bad',
					'label'         => 'Bad Select',
					'name'          => 'bad_select',
					'type'          => 'select',
					'parent'        => 'group_test',
					'return_format' => 'invalid_format',
				),
				'Select with invalid return_format should fail validation',
			),
			'invalid checkbox layout'                  => array(
				array(
					'key'    => 'field_checkbox_bad',
					'label'  => 'Bad Checkbox',
					'name'   => 'bad_checkbox',
					'type'   => 'checkbox',
					'parent' => 'group_test',
					'layout' => 'diagonal',
				),
				'Checkbox with invalid layout should fail validation',
			),
			'invalid nav_menu save_format'             => array(
				array(
					'key'         => 'field_nav_menu_bad',
					'label'       => 'Bad Nav Menu',
					'name'        => 'bad_nav_menu',
					'type'        => 'nav_menu',
					'parent'      => 'group_test',
					'save_format' => 'invalid_format',
				),
				'Nav Menu with invalid save_format should fail validation',
			),

			// Relational field validation.
			'invalid post_object return_format'        => array(
				array(
					'key'           => 'field_post_object_bad',
					'label'         => 'Bad Post Object',
					'name'          => 'bad_post_object',
					'type'          => 'post_object',
					'parent'        => 'group_test',
					'return_format' => 'array',
				),
				'Post Object with invalid return_format should fail validation',
			),
			'invalid relationship return_format'       => array(
				array(
					'key'           => 'field_relationship_bad',
					'label'         => 'Bad Relationship',
					'name'          => 'bad_relationship',
					'type'          => 'relationship',
					'parent'        => 'group_test',
					'return_format' => 'array',
				),
				'Relationship with invalid return_format should fail validation',
			),
			'invalid taxonomy field_type'              => array(
				array(
					'key'        => 'field_taxonomy_bad_type',
					'label'      => 'Bad Taxonomy Type',
					'name'       => 'bad_taxonomy_type',
					'type'       => 'taxonomy',
					'parent'     => 'group_test',
					'field_type' => 'invalid_type',
				),
				'Taxonomy with invalid field_type should fail validation',
			),
			'invalid taxonomy return_format'           => array(
				array(
					'key'           => 'field_taxonomy_bad_format',
					'label'         => 'Bad Taxonomy Format',
					'name'          => 'bad_taxonomy_format',
					'type'          => 'taxonomy',
					'parent'        => 'group_test',
					'return_format' => 'array',
				),
				'Taxonomy with invalid return_format should fail validation',
			),
			'invalid user return_format'               => array(
				array(
					'key'           => 'field_user_bad',
					'label'         => 'Bad User',
					'name'          => 'bad_user',
					'type'          => 'user',
					'parent'        => 'group_test',
					'return_format' => 'invalid_format',
				),
				'User with invalid return_format should fail validation',
			),
			'invalid link return_format'               => array(
				array(
					'key'           => 'field_link_bad',
					'label'         => 'Bad Link',
					'name'          => 'bad_link',
					'type'          => 'link',
					'parent'        => 'group_test',
					'return_format' => 'object',
				),
				'Link with invalid return_format should fail validation',
			),

			// Advanced field validation.
			'invalid color_picker return_format'       => array(
				array(
					'key'           => 'field_color_picker_bad',
					'label'         => 'Bad Color Picker',
					'name'          => 'bad_color_picker',
					'type'          => 'color_picker',
					'parent'        => 'group_test',
					'return_format' => 'invalid_format',
				),
				'Color Picker with invalid return_format should fail validation',
			),
			'invalid color_picker enable_opacity type' => array(
				array(
					'key'            => 'field_color_picker_bad_opacity',
					'label'          => 'Bad Color Picker Opacity',
					'name'           => 'bad_color_picker_opacity',
					'type'           => 'color_picker',
					'parent'         => 'group_test',
					'enable_opacity' => 'yes',
				),
				'Color Picker with non-boolean enable_opacity should fail validation',
			),
			'invalid date_picker first_day type'       => array(
				array(
					'key'       => 'field_date_picker_bad',
					'label'     => 'Bad Date Picker',
					'name'      => 'bad_date_picker',
					'type'      => 'date_picker',
					'parent'    => 'group_test',
					'first_day' => 'monday',
				),
				'Date Picker with non-integer first_day should fail validation',
			),
			'invalid date_time_picker default_to_current_date type' => array(
				array(
					'key'                     => 'field_date_time_picker_bad',
					'label'                   => 'Bad Date Time Picker',
					'name'                    => 'bad_date_time_picker',
					'type'                    => 'date_time_picker',
					'parent'                  => 'group_test',
					'default_to_current_date' => true,
				),
				'Date Time Picker with non-integer default_to_current_date should fail validation',
			),
			'invalid icon_picker tabs type'            => array(
				array(
					'key'    => 'field_icon_picker_bad',
					'label'  => 'Bad Icon Picker',
					'name'   => 'bad_icon_picker',
					'type'   => 'icon_picker',
					'parent' => 'group_test',
					'tabs'   => 'dashicons',
				),
				'Icon Picker with non-array tabs should fail validation',
			),
			'invalid google_map height type'           => array(
				array(
					'key'    => 'field_google_map_bad',
					'label'  => 'Bad Google Map',
					'name'   => 'bad_google_map',
					'type'   => 'google_map',
					'parent' => 'group_test',
					'height' => 400,
				),
				'Google Map with non-string height should fail validation',
			),

			// Layout field validation.
			'invalid group layout'                     => array(
				array(
					'key'    => 'field_group_bad',
					'label'  => 'Bad Group',
					'name'   => 'bad_group',
					'type'   => 'group',
					'parent' => 'group_test',
					'layout' => 'invalid_layout',
				),
				'Group with invalid layout should fail validation',
			),
			'invalid repeater layout'                  => array(
				array(
					'key'    => 'field_repeater_bad',
					'label'  => 'Bad Repeater',
					'name'   => 'bad_repeater',
					'type'   => 'repeater',
					'parent' => 'group_test',
					'layout' => 'invalid_layout',
				),
				'Repeater with invalid layout should fail validation',
			),
			'invalid repeater rows_per_page type'      => array(
				array(
					'key'           => 'field_repeater_bad_rows',
					'label'         => 'Bad Repeater Rows',
					'name'          => 'bad_repeater_rows',
					'type'          => 'repeater',
					'parent'        => 'group_test',
					'rows_per_page' => 'twenty',
				),
				'Repeater with non-integer rows_per_page should fail validation',
			),
			'invalid tab placement'                    => array(
				array(
					'key'       => 'field_tab_bad',
					'label'     => 'Bad Tab',
					'name'      => 'bad_tab',
					'type'      => 'tab',
					'parent'    => 'group_test',
					'placement' => 'bottom',
				),
				'Tab with invalid placement should fail validation',
			),
			'invalid accordion open type'              => array(
				array(
					'key'    => 'field_accordion_bad_open',
					'label'  => 'Bad Accordion Open',
					'name'   => 'bad_accordion_open',
					'type'   => 'accordion',
					'parent' => 'group_test',
					'open'   => 'yes',
				),
				'Accordion with non-integer open should fail validation',
			),
			'invalid message new_lines'                => array(
				array(
					'key'       => 'field_message_bad',
					'label'     => 'Bad Message',
					'name'      => 'bad_message',
					'type'      => 'message',
					'parent'    => 'group_test',
					'new_lines' => 'invalid_value',
				),
				'Message with invalid new_lines should fail validation',
			),
			'invalid clone display'                    => array(
				array(
					'key'     => 'field_clone_bad',
					'label'   => 'Bad Clone',
					'name'    => 'bad_clone',
					'type'    => 'clone',
					'parent'  => 'group_test',
					'display' => 'invalid_display',
				),
				'Clone with invalid display should fail validation',
			),
			'invalid clone layout'                     => array(
				array(
					'key'    => 'field_clone_bad_layout',
					'label'  => 'Bad Clone Layout',
					'name'   => 'bad_clone_layout',
					'type'   => 'clone',
					'parent' => 'group_test',
					'layout' => 'invalid_layout',
				),
				'Clone with invalid layout should fail validation',
			),
			'invalid output html type'                 => array(
				array(
					'key'    => 'field_output_bad',
					'label'  => 'Bad Output',
					'name'   => 'bad_output',
					'type'   => 'output',
					'parent' => 'group_test',
					'html'   => 'yes',
				),
				'Output with non-boolean html should fail validation',
			),
		);
	}
}
