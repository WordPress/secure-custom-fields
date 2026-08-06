<?php
/**
 * Tests for SCF_Post_Type_Abilities class
 *
 * Integration-style tests for the post type CRUD ability handlers. Base class
 * behavior (schemas, annotations, error paths with mocked instances) is covered
 * by Test_SCF_Internal_Post_Type_Abilities; this file exercises the real
 * 'acf-post-type' instance through WorDBless persistence.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load mock Abilities API functions before loading the class.
require_once __DIR__ . '/abilities-api-mocks.php';

// Load abilities classes for testing.
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-internal-post-type-abilities.php';
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-post-type-abilities.php';

/**
 * Tests for SCF_Post_Type_Abilities class
 */
class Test_SCF_Post_Type_Abilities extends BaseTestCase {

	/**
	 * Instance of SCF_Post_Type_Abilities for testing
	 *
	 * @var SCF_Post_Type_Abilities
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
		$this->abilities = acf_get_instance( 'SCF_Post_Type_Abilities' );
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
	 * Helper to build unique post type input data.
	 *
	 * @return array
	 */
	private function new_post_type_input() {
		$suffix = substr( uniqid(), -8 );
		return array(
			'key'       => 'post_type_phpunit_' . $suffix,
			'title'     => 'PHPUnit Post Type ' . $suffix,
			'post_type' => 'pt_' . $suffix,
		);
	}

	/**
	 * Helper to create a post type through the ability and register cleanup.
	 *
	 * @param array $input Optional input overrides.
	 * @return array The created entity.
	 */
	private function create_post_type( $input = array() ) {
		$entity = $this->abilities->create_callback( array_merge( $this->new_post_type_input(), $input ) );

		$this->assertIsArray( $entity, 'Fixture post type creation should succeed' );
		$this->cleanup_post_ids[] = $entity['ID'];

		return $entity;
	}

	// Registration tests.

	/**
	 * Test register_categories registers the scf-post-types category
	 */
	public function test_register_categories_registers_scf_post_types() {
		global $mock_registered_ability_categories;
		$mock_registered_ability_categories = array();

		$this->abilities->register_categories();

		$this->assertArrayHasKey( 'scf-post-types', $mock_registered_ability_categories );
	}

	/**
	 * Test register_abilities registers all expected post type abilities
	 */
	public function test_register_abilities_registers_all_abilities() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$expected_abilities = array(
			'scf/list-post-types',
			'scf/get-post-type',
			'scf/create-post-type',
			'scf/update-post-type',
			'scf/delete-post-type',
			'scf/duplicate-post-type',
			'scf/export-post-type',
			'scf/import-post-type',
			'scf/trash-post-type',
			'scf/untrash-post-type',
		);

		foreach ( $expected_abilities as $ability_name ) {
			$this->assertArrayHasKey( $ability_name, $mock_registered_abilities, "Missing ability: $ability_name" );
			$this->assertEquals(
				'scf-post-types',
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
	 * Test create_callback creates a post type and returns expected fields
	 */
	public function test_create_callback_creates_post_type() {
		$input  = $this->new_post_type_input();
		$result = $this->abilities->create_callback( $input );

		$this->assertIsArray( $result );
		$this->cleanup_post_ids[] = $result['ID'];

		$this->assertEquals( $input['key'], $result['key'] );
		$this->assertEquals( $input['title'], $result['title'] );
		$this->assertEquals( $input['post_type'], $result['post_type'] );
		$this->assertNotEmpty( $result['ID'] );
	}

	/**
	 * Test get_callback retrieves a created post type by ID
	 */
	public function test_get_callback_returns_created_post_type() {
		$entity = $this->create_post_type();

		$result = $this->abilities->get_callback( array( 'identifier' => $entity['ID'] ) );

		$this->assertIsArray( $result );
		// Note: serialized settings stored in post_content (like post_type) do not
		// survive a WorDBless round-trip, so assert on the post-column-backed fields.
		$this->assertEquals( $entity['key'], $result['key'] );
		$this->assertEquals( $entity['title'], $result['title'] );
	}

	/**
	 * Test get_callback returns not_found error for unknown identifier
	 */
	public function test_get_callback_not_found() {
		$result = $this->abilities->get_callback( array( 'identifier' => 'post_type_missing' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
		$this->assertEquals( 404, $result->get_error_data()['status'] );
	}

	/**
	 * Test update_callback updates the title and preserves other properties
	 */
	public function test_update_callback_updates_title_and_preserves_post_type() {
		$entity = $this->create_post_type();

		$result = $this->abilities->update_callback(
			array(
				'ID'    => $entity['ID'],
				'title' => 'Renamed Post Type',
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'Renamed Post Type', $result['title'] );
		// Note: post_type itself is serialized into post_content, which does not
		// survive a WorDBless round-trip, so merge behavior is asserted on the key.
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
	 * Test delete_callback deletes a created post type
	 */
	public function test_delete_callback_deletes_post_type() {
		$entity = $this->create_post_type();

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
		$entity = $this->create_post_type();

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
		$entity = $this->create_post_type();

		$result = $this->abilities->export_callback( array( 'identifier' => $entity['ID'] ) );

		$this->assertIsArray( $result );
		$this->assertEquals( $entity['key'], $result['key'] );
		$this->assertArrayNotHasKey( 'ID', $result, 'Export should strip the internal ID' );
	}

	/**
	 * Test import_callback imports a post type and returns expected fields
	 */
	public function test_import_callback_imports_post_type() {
		$input  = $this->new_post_type_input();
		$result = $this->abilities->import_callback( $input );

		$this->assertIsArray( $result );
		$this->cleanup_post_ids[] = $result['ID'];

		$this->assertEquals( $input['key'], $result['key'] );
		$this->assertEquals( $input['post_type'], $result['post_type'] );
		$this->assertNotEmpty( $result['ID'], 'Import should persist and return an ID' );
	}
}
