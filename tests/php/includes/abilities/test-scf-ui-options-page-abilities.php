<?php
/**
 * Tests for SCF_UI_Options_Page_Abilities class
 *
 * Integration-style tests for the UI options page CRUD ability handlers.
 * Base class behavior (schemas, annotations, error paths with mocked
 * instances) is covered by Test_SCF_Internal_Post_Type_Abilities; this file
 * exercises the real 'acf-ui-options-page' instance through WorDBless.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load mock Abilities API functions before loading the class.
require_once __DIR__ . '/abilities-api-mocks.php';

// Load abilities classes for testing.
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-internal-post-type-abilities.php';
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-ui-options-page-abilities.php';

/**
 * Tests for SCF_UI_Options_Page_Abilities class
 */
class Test_SCF_UI_Options_Page_Abilities extends BaseTestCase {

	/**
	 * Instance of SCF_UI_Options_Page_Abilities for testing
	 *
	 * @var SCF_UI_Options_Page_Abilities
	 */
	private $abilities;

	/**
	 * IDs of posts created during a test, deleted in tearDown
	 *
	 * @var array
	 */
	private $cleanup_post_ids = array();

	/**
	 * Setup test fixtures
	 */
	public function setUp(): void {
		parent::setUp();
		$this->abilities = acf_get_instance( 'SCF_UI_Options_Page_Abilities' );
	}

	/**
	 * Teardown test fixtures
	 */
	public function tearDown(): void {
		foreach ( $this->cleanup_post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->cleanup_post_ids = array();
		$_POST                  = array();

		parent::tearDown();
	}

	/**
	 * Helper to build unique UI options page input data.
	 *
	 * @return array
	 */
	private function new_options_page_input() {
		$suffix = substr( uniqid(), -8 );
		return array(
			'key'        => 'ui_options_page_phpunit_' . $suffix,
			'title'      => 'PHPUnit Options Page ' . $suffix,
			'menu_slug'  => 'phpunit-options-' . $suffix,
			'page_title' => 'PHPUnit Options Page ' . $suffix,
		);
	}

	/**
	 * Helper to create an options page through the ability and register cleanup.
	 *
	 * @return array The created entity.
	 */
	private function create_options_page() {
		$entity = $this->abilities->create_callback( $this->new_options_page_input() );

		$this->assertIsArray( $entity, 'Fixture options page creation should succeed' );
		$this->cleanup_post_ids[] = $entity['ID'];

		return $entity;
	}

	// Registration tests.

	/**
	 * Test register_categories registers the scf-ui-options-pages category
	 */
	public function test_register_categories_registers_scf_ui_options_pages() {
		global $mock_registered_ability_categories;
		$mock_registered_ability_categories = array();

		$this->abilities->register_categories();

		$this->assertArrayHasKey( 'scf-ui-options-pages', $mock_registered_ability_categories );
	}

	/**
	 * Test register_abilities registers all expected UI options page abilities
	 */
	public function test_register_abilities_registers_all_abilities() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$expected_abilities = array(
			'scf/list-ui-options-pages',
			'scf/get-ui-options-page',
			'scf/create-ui-options-page',
			'scf/update-ui-options-page',
			'scf/delete-ui-options-page',
			'scf/duplicate-ui-options-page',
			'scf/export-ui-options-page',
			'scf/import-ui-options-page',
			'scf/trash-ui-options-page',
			'scf/untrash-ui-options-page',
		);

		foreach ( $expected_abilities as $ability_name ) {
			$this->assertArrayHasKey( $ability_name, $mock_registered_abilities, "Missing ability: $ability_name" );
			$this->assertEquals(
				'scf-ui-options-pages',
				$mock_registered_abilities[ $ability_name ]['category'],
				"Ability $ability_name has wrong category"
			);
			$this->assertEquals(
				'scf_current_user_has_capability',
				$mock_registered_abilities[ $ability_name ]['permission_callback'],
				"Ability $ability_name should gate access via scf_current_user_has_capability"
			);
		}
	}

	// CRUD callback tests.

	/**
	 * Test create_callback creates a UI options page and returns expected fields
	 */
	public function test_create_callback_creates_options_page() {
		$input  = $this->new_options_page_input();
		$result = $this->abilities->create_callback( $input );

		$this->assertIsArray( $result );
		$this->cleanup_post_ids[] = $result['ID'];

		$this->assertEquals( $input['key'], $result['key'] );
		$this->assertEquals( $input['title'], $result['title'] );
		$this->assertEquals( $input['menu_slug'], $result['menu_slug'] );
		$this->assertNotEmpty( $result['ID'] );
	}

	/**
	 * Test get_callback retrieves a created options page by ID
	 */
	public function test_get_callback_returns_created_options_page() {
		$entity = $this->create_options_page();

		$result = $this->abilities->get_callback( array( 'identifier' => $entity['ID'] ) );

		$this->assertIsArray( $result );
		// Note: serialized settings stored in post_content (like menu_slug) do not
		// survive a WorDBless round-trip, so assert on the post-column-backed fields.
		$this->assertEquals( $entity['key'], $result['key'] );
		$this->assertEquals( $entity['title'], $result['title'] );
	}

	/**
	 * Test get_callback returns not_found error for unknown identifier
	 */
	public function test_get_callback_not_found() {
		$result = $this->abilities->get_callback( array( 'identifier' => 'ui_options_page_missing' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
		$this->assertEquals( 404, $result->get_error_data()['status'] );
	}

	/**
	 * Test update_callback updates the title and preserves other properties
	 */
	public function test_update_callback_updates_title_and_preserves_menu_slug() {
		$entity = $this->create_options_page();

		$result = $this->abilities->update_callback(
			array(
				'ID'    => $entity['ID'],
				'title' => 'Renamed Options Page',
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'Renamed Options Page', $result['title'] );
		// Note: menu_slug is serialized into post_content, which does not survive
		// a WorDBless round-trip, so merge behavior is asserted on the key.
		$this->assertEquals( $entity['key'], $result['key'], 'Merge behavior should preserve the key' );
	}

	/**
	 * Test update_callback returns not_found for unknown ID
	 */
	public function test_update_callback_not_found() {
		$result = $this->abilities->update_callback(
			array(
				'ID'    => 999999,
				'title' => 'Should Fail',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
	}

	/**
	 * Test delete_callback deletes a created options page
	 */
	public function test_delete_callback_deletes_options_page() {
		$entity = $this->create_options_page();

		$result = $this->abilities->delete_callback( array( 'identifier' => $entity['ID'] ) );

		$this->assertTrue( $result );
	}

	/**
	 * Test delete_callback returns not_found for unknown identifier
	 */
	public function test_delete_callback_not_found() {
		$result = $this->abilities->delete_callback( array( 'identifier' => 999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
	}

	/**
	 * Test duplicate_callback creates a copy with a new ID and key
	 */
	public function test_duplicate_callback_creates_copy() {
		$entity = $this->create_options_page();

		$result = $this->abilities->duplicate_callback( array( 'identifier' => $entity['ID'] ) );

		$this->assertIsArray( $result );
		$this->cleanup_post_ids[] = $result['ID'];

		$this->assertNotEquals( $entity['ID'], $result['ID'], 'Duplicate should get a new ID' );
		$this->assertNotEquals( $entity['key'], $result['key'], 'Duplicate should get a new key' );
	}

	/**
	 * Test export_callback strips internal fields
	 */
	public function test_export_callback_strips_internal_fields() {
		$entity = $this->create_options_page();

		$result = $this->abilities->export_callback( array( 'identifier' => $entity['ID'] ) );

		$this->assertIsArray( $result );
		$this->assertEquals( $entity['key'], $result['key'] );
		$this->assertArrayNotHasKey( 'ID', $result, 'Export should strip the internal ID' );
	}

	/**
	 * Test import_callback imports a UI options page and returns expected fields
	 */
	public function test_import_callback_imports_options_page() {
		$input  = $this->new_options_page_input();
		$result = $this->abilities->import_callback( $input );

		$this->assertIsArray( $result );
		$this->cleanup_post_ids[] = $result['ID'];

		$this->assertEquals( $input['key'], $result['key'] );
		$this->assertEquals( $input['menu_slug'], $result['menu_slug'] );
		$this->assertNotEmpty( $result['ID'], 'Import should persist and return an ID' );
	}
}
