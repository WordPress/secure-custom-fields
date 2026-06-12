<?php
/**
 * Tests for SCF_Field_Group_Abilities class
 *
 * Integration-style tests for field group abilities: registration wiring,
 * permission configuration, and CRUD/duplicate/export/import callbacks
 * running against real field groups persisted through WorDBless.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load mock Abilities API functions before loading the class.
require_once __DIR__ . '/abilities-api-mocks.php';

// Load abilities classes for testing.
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-internal-post-type-abilities.php';
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-field-group-abilities.php';

/**
 * Tests for SCF_Field_Group_Abilities class
 */
class Test_SCF_Field_Group_Abilities extends BaseTestCase {

	/**
	 * Instance of SCF_Field_Group_Abilities for testing
	 *
	 * @var SCF_Field_Group_Abilities
	 */
	private $abilities;

	/**
	 * ID of the field group created in setUp
	 *
	 * @var int
	 */
	private $field_group_id;

	/**
	 * Key of the field group created in setUp
	 *
	 * @var string
	 */
	private $field_group_key;

	/**
	 * IDs of extra posts created during a test, deleted in tearDown
	 *
	 * @var array
	 */
	private $cleanup_post_ids = array();

	/**
	 * Setup test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'fields' )->reset();

		$this->abilities       = acf_get_instance( 'SCF_Field_Group_Abilities' );
		$this->field_group_key = 'group_abilities_' . uniqid();

		$field_group = acf_update_field_group(
			array(
				'key'    => $this->field_group_key,
				'title'  => 'Abilities Test Field Group',
				'active' => true,
			)
		);

		$this->field_group_id = $field_group['ID'];
	}

	/**
	 * Teardown test fixtures
	 */
	public function tearDown(): void {
		if ( $this->field_group_id ) {
			wp_delete_post( $this->field_group_id, true );
		}
		foreach ( $this->cleanup_post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->cleanup_post_ids = array();

		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'fields' )->reset();
		$_POST = array();

		parent::tearDown();
	}

	/**
	 * Helper to register a post for cleanup.
	 *
	 * @param array|bool $entity Entity array with an ID, as returned by a callback.
	 */
	private function register_cleanup( $entity ) {
		if ( is_array( $entity ) && ! empty( $entity['ID'] ) ) {
			$this->cleanup_post_ids[] = $entity['ID'];
		}
	}

	// Registration tests.

	/**
	 * Test constructor registers WordPress action hooks
	 */
	public function test_constructor_registers_action_hooks() {
		$fresh_instance = new SCF_Field_Group_Abilities();

		$this->assertNotFalse(
			has_action( 'wp_abilities_api_categories_init', array( $fresh_instance, 'register_categories' ) ),
			'Should register wp_abilities_api_categories_init action'
		);
		$this->assertNotFalse(
			has_action( 'wp_abilities_api_init', array( $fresh_instance, 'register_abilities' ) ),
			'Should register wp_abilities_api_init action'
		);
	}

	/**
	 * Test register_categories registers the scf-field-groups category
	 */
	public function test_register_categories_registers_scf_field_groups() {
		global $mock_registered_ability_categories;
		$mock_registered_ability_categories = array();

		$this->abilities->register_categories();

		$this->assertArrayHasKey( 'scf-field-groups', $mock_registered_ability_categories );
		$this->assertArrayHasKey( 'label', $mock_registered_ability_categories['scf-field-groups'] );
	}

	/**
	 * Test register_abilities registers all expected field group abilities
	 */
	public function test_register_abilities_registers_all_abilities() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$expected_abilities = array(
			'scf/list-field-groups',
			'scf/get-field-group',
			'scf/create-field-group',
			'scf/update-field-group',
			'scf/delete-field-group',
			'scf/duplicate-field-group',
			'scf/export-field-group',
			'scf/import-field-group',
			'scf/trash-field-group',
			'scf/untrash-field-group',
		);

		foreach ( $expected_abilities as $ability_name ) {
			$this->assertArrayHasKey( $ability_name, $mock_registered_abilities, "Missing ability: $ability_name" );
			$this->assertEquals(
				'scf-field-groups',
				$mock_registered_abilities[ $ability_name ]['category'],
				"Ability $ability_name has wrong category"
			);
		}
	}

	/**
	 * Test every registered field group ability uses the SCF capability permission callback
	 */
	public function test_all_abilities_use_capability_permission_callback() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$this->assertNotEmpty( $mock_registered_abilities );

		foreach ( $mock_registered_abilities as $name => $args ) {
			$this->assertArrayHasKey( 'permission_callback', $args, "Ability $name missing permission_callback" );
			$this->assertEquals(
				'scf_current_user_has_capability',
				$args['permission_callback'],
				"Ability $name should gate access via scf_current_user_has_capability"
			);
		}
	}

	// Permission callback behavior tests.

	/**
	 * Test the permission callback denies logged-out users
	 */
	public function test_permission_callback_denies_logged_out_user() {
		wp_set_current_user( 0 );

		$this->assertFalse(
			scf_current_user_has_capability(),
			'Logged-out users should not pass the abilities permission check'
		);
	}

	/**
	 * Test the permission callback allows administrators
	 */
	public function test_permission_callback_allows_administrator() {
		$admin_id = wp_insert_user(
			array(
				'user_login' => 'abilities_admin_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );

		$this->assertTrue(
			scf_current_user_has_capability(),
			'Administrators should pass the abilities permission check'
		);

		wp_set_current_user( 0 );
		wp_delete_user( $admin_id );
	}

	// List callback tests.

	/**
	 * Test list_callback forces ignore_location_rules while preserving other filters
	 *
	 * Field groups use location rules as a UX feature for edit screens, not access
	 * control, so the ability must list all field groups regardless of location.
	 */
	public function test_list_callback_forces_ignore_location_rules() {
		global $acf_instances;

		// The internal-post-types store maps post type to a class name which is
		// then resolved via acf_get_instance(), so the mock goes into $acf_instances.
		$store             = acf_get_store( 'internal-post-types' );
		$class_name        = $store->get( 'acf-field-group' );
		$original_instance = $acf_instances[ $class_name ] ?? null;

		$captured_filter = null;

		$mock_instance = $this->createMock( ACF_Field_Group::class );
		$mock_instance->method( 'get_posts' )->willReturn( array() );
		$mock_instance->method( 'filter_posts' )->willReturnCallback(
			function ( $posts, $filter ) use ( &$captured_filter ) {
				$captured_filter = $filter;
				return $posts;
			}
		);

		$acf_instances[ $class_name ] = $mock_instance;

		try {
			$this->abilities->list_callback( array( 'filter' => array( 'active' => true ) ) );

			$this->assertIsArray( $captured_filter );
			$this->assertTrue(
				$captured_filter['ignore_location_rules'],
				'list_callback should always set ignore_location_rules'
			);
			$this->assertTrue(
				$captured_filter['active'],
				'list_callback should preserve caller-provided filters'
			);
		} finally {
			if ( $original_instance ) {
				$acf_instances[ $class_name ] = $original_instance;
			}
		}
	}

	/**
	 * Test list_callback tolerates a non-array filter (REST may pass empty string)
	 */
	public function test_list_callback_with_non_array_filter() {
		$result = $this->abilities->list_callback( array( 'filter' => '' ) );

		$this->assertIsArray( $result );
	}

	// Get callback tests.

	/**
	 * Test get_callback returns field group by ID
	 */
	public function test_get_callback_returns_field_group_by_id() {
		$result = $this->abilities->get_callback( array( 'identifier' => $this->field_group_id ) );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->field_group_key, $result['key'] );
		$this->assertEquals( 'Abilities Test Field Group', $result['title'] );
	}

	/**
	 * Test get_callback returns field group by key
	 */
	public function test_get_callback_returns_field_group_by_key() {
		// Prime the store by ID first: key lookups resolve through the store
		// alias in this environment (WP_Query is unavailable under WorDBless).
		acf_get_field_group( $this->field_group_id );

		$result = $this->abilities->get_callback( array( 'identifier' => $this->field_group_key ) );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->field_group_id, $result['ID'] );
	}

	/**
	 * Test get_callback returns not_found error with 404 status for unknown identifier
	 */
	public function test_get_callback_not_found() {
		$result = $this->abilities->get_callback( array( 'identifier' => 'group_does_not_exist' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
		$this->assertEquals( 404, $result->get_error_data()['status'] );
	}

	// Create callback tests.

	/**
	 * Test create_callback creates a new field group
	 */
	public function test_create_callback_creates_field_group() {
		$key    = 'group_created_' . uniqid();
		$result = $this->abilities->create_callback(
			array(
				'key'   => $key,
				'title' => 'Created Via Ability',
			)
		);
		$this->register_cleanup( $result );

		$this->assertIsArray( $result );
		$this->assertEquals( $key, $result['key'] );
		$this->assertEquals( 'Created Via Ability', $result['title'] );
		$this->assertNotEmpty( $result['ID'] );

		// The created field group must be retrievable afterwards.
		$fetched = $this->abilities->get_callback( array( 'identifier' => $result['ID'] ) );
		$this->assertIsArray( $fetched );
		$this->assertEquals( $key, $fetched['key'] );
	}

	/**
	 * Test create_callback rejects a duplicate key
	 */
	public function test_create_callback_rejects_duplicate_key() {
		// Prime the store by ID so the existing key is resolvable (see note in
		// test_get_callback_returns_field_group_by_key).
		acf_get_field_group( $this->field_group_id );

		$result = $this->abilities->create_callback(
			array(
				'key'   => $this->field_group_key,
				'title' => 'Duplicate Key Group',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'already_exists', $result->get_error_code() );
	}

	// Update callback tests.

	/**
	 * Test update_callback updates the title and preserves other properties
	 */
	public function test_update_callback_updates_title_and_preserves_key() {
		$result = $this->abilities->update_callback(
			array(
				'ID'    => $this->field_group_id,
				'title' => 'Updated Title',
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'Updated Title', $result['title'] );
		$this->assertEquals( $this->field_group_key, $result['key'], 'Merge behavior should preserve the key' );
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

	// Delete callback tests.

	/**
	 * Test delete_callback deletes an existing field group
	 */
	public function test_delete_callback_deletes_field_group() {
		$result = $this->abilities->delete_callback( array( 'identifier' => $this->field_group_id ) );

		$this->assertTrue( $result );

		// Reset caches so the follow-up lookup hits persistence, not stale cache.
		acf_get_store( 'field-groups' )->reset();
		$this->assertFalse(
			acf_get_field_group( $this->field_group_id ),
			'Deleted field group should no longer be retrievable'
		);
		$this->field_group_id = 0;
	}

	/**
	 * Test delete_callback returns not_found for unknown identifier
	 */
	public function test_delete_callback_not_found() {
		$result = $this->abilities->delete_callback( array( 'identifier' => 'group_missing_' . uniqid() ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
		$this->assertEquals( 404, $result->get_error_data()['status'] );
	}

	// Duplicate callback tests.

	/**
	 * Test duplicate_callback creates a copy with a new ID and key
	 */
	public function test_duplicate_callback_creates_copy() {
		$result = $this->abilities->duplicate_callback( array( 'identifier' => $this->field_group_id ) );
		$this->register_cleanup( $result );

		$this->assertIsArray( $result );
		$this->assertNotEquals( $this->field_group_id, $result['ID'], 'Duplicate should get a new ID' );
		$this->assertNotEquals( $this->field_group_key, $result['key'], 'Duplicate should get a new key' );
	}

	/**
	 * Test duplicate_callback returns not_found for unknown identifier
	 */
	public function test_duplicate_callback_not_found() {
		$result = $this->abilities->duplicate_callback( array( 'identifier' => 999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
	}

	/**
	 * Test duplicate_callback rejects a new_post_id that does not exist
	 */
	public function test_duplicate_callback_rejects_invalid_new_post_id() {
		$result = $this->abilities->duplicate_callback(
			array(
				'identifier'  => $this->field_group_id,
				'new_post_id' => 999999,
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_new_post_id', $result->get_error_code() );
		$this->assertEquals( 400, $result->get_error_data()['status'] );
	}

	// Export callback tests.

	/**
	 * Test export_callback strips internal fields
	 */
	public function test_export_callback_strips_internal_fields() {
		$result = $this->abilities->export_callback( array( 'identifier' => $this->field_group_id ) );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->field_group_key, $result['key'] );
		$this->assertArrayNotHasKey( 'ID', $result, 'Export should strip the internal ID' );
	}

	/**
	 * Test export_callback returns not_found for unknown identifier
	 */
	public function test_export_callback_not_found() {
		$result = $this->abilities->export_callback( array( 'identifier' => 999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
	}

	// Import callback tests.

	/**
	 * Test import_callback imports a field group from exported data
	 */
	public function test_import_callback_imports_field_group() {
		$key    = 'group_imported_' . uniqid();
		$result = $this->abilities->import_callback(
			array(
				'key'    => $key,
				'title'  => 'Imported Field Group',
				'fields' => array(),
			)
		);
		$this->register_cleanup( $result );

		$this->assertIsArray( $result );
		$this->assertEquals( $key, $result['key'] );
		$this->assertEquals( 'Imported Field Group', $result['title'] );
		$this->assertNotEmpty( $result['ID'], 'Import should persist and return an ID' );
	}
}
