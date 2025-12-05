<?php
/**
 * Tests for the SCF UI Options Page JSON Schema validation.
 *
 * @package wordpress/secure-custom-fields
 */

use PHPUnit\Framework\TestCase;

require_once 'BaseSchemaTestCase.php';

/**
 * Class UIOptionsPageSchemaTest
 *
 * Tests JSON Schema validation for SCF UI Options Pages.
 */
class UIOptionsPageSchemaTest extends BaseSchemaTestCase {

	/**
	 * Get the schema type to test.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'ui-options-page';
	}

	/**
	 * Get the path to the fixtures directory.
	 *
	 * @return string
	 */
	protected function get_fixtures_path(): string {
		return dirname( __DIR__ ) . '/fixtures/schemas/ui-options-pages/';
	}

	/**
	 * Get the definition name in the schema.
	 *
	 * @return string
	 */
	protected function get_definition_name(): string {
		return 'uiOptionsPage';
	}

	/**
	 * Get the required fields for this schema.
	 *
	 * @return array
	 */
	protected function get_required_fields(): array {
		return array( 'key', 'title', 'menu_slug' );
	}

	/**
	 * Data provider for valid UI Options Pages.
	 *
	 * @return array
	 */
	public function validEntitiesProvider(): array {
		return array(
			'minimal valid'                     => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
				),
				'Minimal options page should validate successfully',
			),
			'array with two items'              => array(
				array(
					array(
						'key'       => 'ui_options_page_settings',
						'title'     => 'Settings',
						'menu_slug' => 'settings',
					),
					array(
						'key'       => 'ui_options_page_advanced',
						'title'     => 'Advanced',
						'menu_slug' => 'advanced',
					),
				),
				'Array of two options pages should validate successfully',
			),
			'with dashes in menu_slug'          => array(
				array(
					'key'       => 'ui_options_page_site_settings',
					'title'     => 'Site Settings',
					'menu_slug' => 'site-settings',
				),
				'Options page with dashes in menu_slug should be valid',
			),
			'with underscores in menu_slug'     => array(
				array(
					'key'       => 'ui_options_page_site_settings',
					'title'     => 'Site Settings',
					'menu_slug' => 'site_settings',
				),
				'Options page with underscores in menu_slug should be valid',
			),
			'with numbers in menu_slug'         => array(
				array(
					'key'       => 'ui_options_page_settings2',
					'title'     => 'Settings 2',
					'menu_slug' => 'settings2',
				),
				'Options page with numbers in menu_slug should be valid',
			),
			'parent page with menu_icon object' => array(
				array(
					'key'         => 'ui_options_page_parent',
					'title'       => 'Parent Page',
					'menu_slug'   => 'parent-page',
					'parent_slug' => 'none',
					'menu_icon'   => array(
						'type'  => 'dashicons',
						'value' => 'dashicons-admin-settings',
					),
					'position'    => 80,
				),
				'Parent page with menu_icon object should be valid',
			),
			'menu_icon as string'               => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'menu_icon' => 'dashicons-admin-generic',
				),
				'Options page with menu_icon as string should be valid',
			),
			'menu_icon with url type'           => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'menu_icon' => array(
						'type'  => 'url',
						'value' => 'https://example.com/icon.png',
					),
				),
				'Options page with menu_icon url type should be valid',
			),
			'menu_icon with media_library type' => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'menu_icon' => array(
						'type'  => 'media_library',
						'value' => 123,
					),
				),
				'Options page with menu_icon media_library type should be valid',
			),
			'child page with parent_slug'       => array(
				array(
					'key'         => 'ui_options_page_child',
					'title'       => 'Child Page',
					'menu_slug'   => 'child-page',
					'parent_slug' => 'parent-page',
				),
				'Child page with parent_slug should be valid',
			),
			'with custom capability'            => array(
				array(
					'key'        => 'ui_options_page_settings',
					'title'      => 'Settings',
					'menu_slug'  => 'settings',
					'capability' => 'manage_options',
				),
				'Options page with custom capability should be valid',
			),
			'with data_storage options'         => array(
				array(
					'key'          => 'ui_options_page_settings',
					'title'        => 'Settings',
					'menu_slug'    => 'settings',
					'data_storage' => 'options',
				),
				'Options page with data_storage options should be valid',
			),
			'with data_storage post_id'         => array(
				array(
					'key'          => 'ui_options_page_settings',
					'title'        => 'Settings',
					'menu_slug'    => 'settings',
					'data_storage' => 'post_id',
					'post_id'      => 'user_2',
				),
				'Options page with custom post_id storage should be valid',
			),
			'with post_id as integer'           => array(
				array(
					'key'          => 'ui_options_page_settings',
					'title'        => 'Settings',
					'menu_slug'    => 'settings',
					'data_storage' => 'post_id',
					'post_id'      => 123,
				),
				'Options page with numeric post_id should be valid',
			),
			'redirect true'                     => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'redirect'  => true,
				),
				'Options page with redirect true should be valid',
			),
			'redirect false'                    => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'redirect'  => false,
				),
				'Options page with redirect false should be valid',
			),
			'autoload true'                     => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'autoload'  => true,
				),
				'Options page with autoload true should be valid',
			),
			'position as integer'               => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'position'  => 80,
				),
				'Options page with position as integer should be valid',
			),
			'position as empty string'          => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'position'  => '',
				),
				'Options page with position as empty string should be valid',
			),
			'position as null'                  => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'position'  => null,
				),
				'Options page with position as null should be valid',
			),
			'advanced_configuration as integer' => array(
				array(
					'key'                    => 'ui_options_page_settings',
					'title'                  => 'Settings',
					'menu_slug'              => 'settings',
					'advanced_configuration' => 1,
				),
				'Options page with advanced_configuration as integer should be valid',
			),
			'advanced_configuration as boolean' => array(
				array(
					'key'                    => 'ui_options_page_settings',
					'title'                  => 'Settings',
					'menu_slug'              => 'settings',
					'advanced_configuration' => true,
				),
				'Options page with advanced_configuration as boolean should be valid',
			),
			'with all optional string fields'   => array(
				array(
					'key'             => 'ui_options_page_settings',
					'title'           => 'Settings',
					'menu_slug'       => 'settings',
					'page_title'      => 'Site Settings Page',
					'menu_title'      => 'Settings',
					'description'     => 'A description of this options page',
					'update_button'   => 'Save Changes',
					'updated_message' => 'Settings saved successfully',
					'icon_url'        => 'dashicons-admin-settings',
				),
				'Options page with all optional string fields should be valid',
			),
			'with export metadata fields'       => array(
				array(
					'key'           => 'ui_options_page_settings',
					'title'         => 'Settings',
					'menu_slug'     => 'settings',
					'import_source' => 'local',
					'import_date'   => '2024-01-15',
					'modified'      => 1705334400,
				),
				'Options page with export metadata fields should be valid',
			),
			'full options page with all fields' => array(
				array(
					'key'                    => 'ui_options_page_full_example',
					'title'                  => 'Full Example',
					'menu_slug'              => 'full-example',
					'page_title'             => 'Full Example Page',
					'parent_slug'            => 'none',
					'menu_title'             => 'Full Example',
					'active'                 => true,
					'advanced_configuration' => 0,
					'import_source'          => '',
					'import_date'            => '',
					'modified'               => 1701619200,
					'menu_order'             => 5,
					'icon_url'               => '',
					'menu_icon'              => array(
						'type'  => 'dashicons',
						'value' => 'dashicons-admin-settings',
					),
					'position'               => 80,
					'redirect'               => true,
					'description'            => 'A complete options page',
					'update_button'          => 'Save Changes',
					'updated_message'        => 'Options saved successfully',
					'capability'             => 'manage_options',
					'data_storage'           => 'options',
					'post_id'                => '',
					'autoload'               => true,
				),
				'Full options page with all fields should be valid',
			),
		);
	}

	/**
	 * Data provider for invalid UI Options Pages.
	 *
	 * @return array
	 */
	public function invalidEntitiesProvider(): array {
		return array(
			'missing key'                    => array(
				array(
					'title'     => 'Settings',
					'menu_slug' => 'settings',
				),
				'Options page missing key should fail validation',
			),
			'missing title'                  => array(
				array(
					'key'       => 'ui_options_page_settings',
					'menu_slug' => 'settings',
				),
				'Options page missing title should fail validation',
			),
			'missing menu_slug'              => array(
				array(
					'key'   => 'ui_options_page_settings',
					'title' => 'Settings',
				),
				'Options page missing menu_slug should fail validation',
			),
			'empty key'                      => array(
				array(
					'key'       => '',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
				),
				'Options page with empty key should fail validation',
			),
			'empty title'                    => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => '',
					'menu_slug' => 'settings',
				),
				'Options page with empty title should fail validation',
			),
			'empty menu_slug'                => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => '',
				),
				'Options page with empty menu_slug should fail validation',
			),
			'wrong key prefix'               => array(
				array(
					'key'       => 'post_type_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
				),
				'Options page with wrong key prefix should fail validation',
			),
			'key without prefix'             => array(
				array(
					'key'       => 'settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
				),
				'Options page with key without prefix should fail validation',
			),
			'menu_slug with uppercase'       => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'Site-Settings',
				),
				'Options page with uppercase menu_slug should fail validation',
			),
			'menu_slug with special chars'   => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings!page',
				),
				'Options page with special characters in menu_slug should fail validation',
			),
			'menu_slug with spaces'          => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'site settings',
				),
				'Options page with spaces in menu_slug should fail validation',
			),
			'invalid menu_icon type enum'    => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'menu_icon' => array(
						'type'  => 'invalid_type',
						'value' => 'some-value',
					),
				),
				'Options page with invalid menu_icon type should fail validation',
			),
			'menu_icon object missing type'  => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'menu_icon' => array(
						'value' => 'dashicons-admin-settings',
					),
				),
				'Options page with menu_icon missing type should fail validation',
			),
			'menu_icon object missing value' => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'menu_icon' => array(
						'type' => 'dashicons',
					),
				),
				'Options page with menu_icon missing value should fail validation',
			),
			'invalid data_storage enum'      => array(
				array(
					'key'          => 'ui_options_page_settings',
					'title'        => 'Settings',
					'menu_slug'    => 'settings',
					'data_storage' => 'invalid_storage',
				),
				'Options page with invalid data_storage value should fail validation',
			),
			'additional properties'          => array(
				array(
					'key'              => 'ui_options_page_settings',
					'title'            => 'Settings',
					'menu_slug'        => 'settings',
					'invalid_property' => 'some value',
				),
				'Options page with additional properties should fail validation',
			),
			'modified as negative integer'   => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'modified'  => -1,
				),
				'Options page with negative modified timestamp should fail validation',
			),
			'menu_order as negative integer' => array(
				array(
					'key'        => 'ui_options_page_settings',
					'title'      => 'Settings',
					'menu_slug'  => 'settings',
					'menu_order' => -1,
				),
				'Options page with negative menu_order should fail validation',
			),
			'redirect as string'             => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'redirect'  => 'true',
				),
				'Options page with redirect as string should fail validation',
			),
			'autoload as string'             => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'autoload'  => 'yes',
				),
				'Options page with autoload as string should fail validation',
			),
			'active as string'               => array(
				array(
					'key'       => 'ui_options_page_settings',
					'title'     => 'Settings',
					'menu_slug' => 'settings',
					'active'    => 'true',
				),
				'Options page with active as string should fail validation',
			),
		);
	}
}
