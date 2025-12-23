<?php
/**
 * Tests for SCF_Field_Abilities class
 *
 * Tests the field abilities class behavior. Full integration tests are covered
 * by E2E tests.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load mock Abilities API functions before loading the class.
require_once __DIR__ . '/abilities-api-mocks.php';

// Load SCF_Field_Manager adapter class.
require_once dirname( __DIR__, 4 ) . '/includes/post-types/class-scf-field-manager.php';

// Load abilities class for testing.
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-field-abilities.php';

/**
 * Tests for SCF_Field_Abilities class
 */
class SCFFieldAbilitiesTest extends BaseTestCase {

	/**
	 * Instance of SCF_Field_Abilities for testing
	 *
	 * @var SCF_Field_Abilities
	 */
	private $abilities;

	/**
	 * Test field data for create/import operations
	 *
	 * @var array
	 */
	private $test_field = array(
		'key'    => 'field_phpunit_test',
		'label'  => 'PHPUnit Test Field',
		'name'   => 'phpunit_test',
		'type'   => 'text',
		'parent' => 123,
	);

	/**
	 * Reusable mock entity for mocked callback tests
	 *
	 * @var array
	 */
	private $mock_field = array(
		'ID'     => 456,
		'key'    => 'field_test_key',
		'label'  => 'Test Field',
		'name'   => 'test_field',
		'type'   => 'text',
		'parent' => 123,
	);

	/**
	 * Setup test fixtures
	 */
	public function setUp(): void {
		parent::setUp();
		$this->abilities = acf_get_instance( 'SCF_Field_Abilities' );
		// Mock the parent as existing by default for create/import tests.
		mock_parent_exists( 123, true );
	}

	/**
	 * Teardown test fixtures
	 */
	public function tearDown(): void {
		parent::tearDown();
		clear_mock_parent_exists();
	}

	/**
	 * Helper to inject a mock manager into the abilities object.
	 *
	 * @param array $method_returns Map of method names to return values.
	 * @return \PHPUnit\Framework\MockObject\MockObject The mock manager.
	 */
	private function inject_mock_manager( array $method_returns ) {
		$mock_manager = $this->createMock( SCF_Field_Manager::class );

		foreach ( $method_returns as $method => $return_value ) {
			$mock_manager->method( $method )->willReturn( $return_value );
		}

		$reflection = new ReflectionClass( SCF_Field_Abilities::class );
		$property   = $reflection->getProperty( 'manager' );
		$property->setAccessible( true );
		$property->setValue( $this->abilities, $mock_manager );

		return $mock_manager;
	}

	/**
	 * Helper to assert a callback returns a specific WP_Error code.
	 *
	 * @param array    $mock_returns   Mock method return values.
	 * @param callable $callback       The callback to invoke.
	 * @param array    $input          Input for the callback.
	 * @param string   $expected_code  Expected error code.
	 */
	private function assert_callback_error( array $mock_returns, callable $callback, array $input, string $expected_code ) {
		$this->inject_mock_manager( $mock_returns );
		$result = $callback( $input );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( $expected_code, $result->get_error_code() );
	}

	// Constructor tests.

	/**
	 * Test constructor registers WordPress action hooks
	 */
	public function test_constructor_registers_action_hooks() {
		$fresh_instance = new SCF_Field_Abilities();

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
	 * Test categories are registered when wp_abilities_api_categories_init fires
	 */
	public function test_categories_registered_on_action() {
		global $mock_registered_ability_categories;
		$mock_registered_ability_categories = array();

		do_action( 'wp_abilities_api_categories_init' );

		$this->assertArrayHasKey(
			'scf-fields',
			$mock_registered_ability_categories,
			'Category should be registered when action fires'
		);
	}

	/**
	 * Test abilities are registered when wp_abilities_api_init fires
	 */
	public function test_abilities_registered_on_action() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		do_action( 'wp_abilities_api_init' );

		$expected_abilities = array(
			'list-fields',
			'get-field',
			'create-field',
			'update-field',
			'delete-field',
			'duplicate-field',
			'export-field',
			'import-field',
			'trash-field',
			'untrash-field',
		);

		foreach ( $expected_abilities as $ability_name ) {
			$this->assertArrayHasKey(
				"scf/$ability_name",
				$mock_registered_abilities,
				"Ability '$ability_name' should be registered"
			);
		}
	}

	// List callback tests.

	/**
	 * Test list_callback returns filtered fields
	 */
	public function test_list_callback_returns_filtered_fields() {
		$fields = array( $this->mock_field );
		$this->inject_mock_manager(
			array(
				'get_posts'    => $fields,
				'filter_posts' => $fields,
			)
		);

		$result = $this->abilities->list_callback( array( 'filter' => array( 'type' => 'text' ) ) );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'field_test_key', $result[0]['key'] );
	}

	/**
	 * Test list_callback with empty filter returns all fields
	 */
	public function test_list_callback_with_empty_filter() {
		$fields = array( $this->mock_field );
		$this->inject_mock_manager(
			array(
				'get_posts'    => $fields,
				'filter_posts' => $fields,
			)
		);

		$result = $this->abilities->list_callback( array() );

		$this->assertIsArray( $result );
	}

	// Get callback tests.

	/**
	 * Test get_callback returns field when found
	 */
	public function test_get_callback_returns_field_when_found() {
		$this->inject_mock_manager( array( 'get_post' => $this->mock_field ) );

		$result = $this->abilities->get_callback( array( 'identifier' => 'field_test_key' ) );

		$this->assertIsArray( $result );
		$this->assertEquals( 'field_test_key', $result['key'] );
	}

	/**
	 * Test get_callback returns error when field not found
	 */
	public function test_get_callback_returns_error_when_not_found() {
		$this->assert_callback_error(
			array( 'get_post' => false ),
			array( $this->abilities, 'get_callback' ),
			array( 'identifier' => 'nonexistent' ),
			'not_found'
		);
	}

	// Create callback tests.

	/**
	 * Test create_callback creates new field
	 */
	public function test_create_callback_creates_new_field() {
		$this->inject_mock_manager(
			array(
				'get_post'    => false,
				'update_post' => $this->mock_field,
			)
		);

		$result = $this->abilities->create_callback( $this->test_field );

		$this->assertIsArray( $result );
		$this->assertEquals( 'field_test_key', $result['key'] );
	}

	/**
	 * Test create_callback returns error when field with key already exists
	 */
	public function test_create_callback_returns_error_when_key_exists() {
		$this->assert_callback_error(
			array( 'get_post' => $this->mock_field ),
			array( $this->abilities, 'create_callback' ),
			$this->test_field,
			'already_exists'
		);
	}

	/**
	 * Test create_callback returns error when parent is missing
	 */
	public function test_create_callback_returns_error_when_parent_missing() {
		$this->inject_mock_manager( array( 'get_post' => false ) );

		$field_without_parent = $this->test_field;
		unset( $field_without_parent['parent'] );

		$result = $this->abilities->create_callback( $field_without_parent );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test create_callback returns error when creation fails
	 */
	public function test_create_callback_returns_error_when_creation_fails() {
		$this->assert_callback_error(
			array(
				'get_post'    => false,
				'update_post' => false,
			),
			array( $this->abilities, 'create_callback' ),
			$this->test_field,
			'create_failed'
		);
	}

	// Update callback tests.

	/**
	 * Test update_callback updates existing field
	 */
	public function test_update_callback_updates_existing_field() {
		$updated_field          = $this->mock_field;
		$updated_field['label'] = 'Updated Label';

		$this->inject_mock_manager(
			array(
				'get_post'    => $this->mock_field,
				'update_post' => $updated_field,
			)
		);

		$result = $this->abilities->update_callback(
			array(
				'ID'    => 456,
				'label' => 'Updated Label',
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'Updated Label', $result['label'] );
	}

	/**
	 * Test update_callback returns error when ID is missing
	 */
	public function test_update_callback_returns_error_when_id_missing() {
		$result = $this->abilities->update_callback( array( 'label' => 'New Label' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test update_callback returns error when field not found
	 */
	public function test_update_callback_returns_error_when_not_found() {
		$this->assert_callback_error(
			array( 'get_post' => false ),
			array( $this->abilities, 'update_callback' ),
			array(
				'ID'    => 999,
				'label' => 'New Label',
			),
			'not_found'
		);
	}

	/**
	 * Test update_callback returns error when update fails
	 */
	public function test_update_callback_returns_error_when_update_fails() {
		$this->assert_callback_error(
			array(
				'get_post'    => $this->mock_field,
				'update_post' => false,
			),
			array( $this->abilities, 'update_callback' ),
			array(
				'ID'    => 456,
				'label' => 'New Label',
			),
			'update_failed'
		);
	}

	// Delete callback tests.

	/**
	 * Test delete_callback deletes field
	 */
	public function test_delete_callback_deletes_field() {
		$this->inject_mock_manager(
			array(
				'get_post'    => $this->mock_field,
				'delete_post' => true,
			)
		);

		$result = $this->abilities->delete_callback( array( 'identifier' => 'field_test_key' ) );

		$this->assertTrue( $result );
	}

	/**
	 * Test delete_callback returns error when field not found
	 */
	public function test_delete_callback_returns_error_when_not_found() {
		$this->assert_callback_error(
			array( 'get_post' => false ),
			array( $this->abilities, 'delete_callback' ),
			array( 'identifier' => 'nonexistent' ),
			'not_found'
		);
	}

	/**
	 * Test delete_callback returns error when deletion fails
	 */
	public function test_delete_callback_returns_error_when_deletion_fails() {
		$this->assert_callback_error(
			array(
				'get_post'    => $this->mock_field,
				'delete_post' => false,
			),
			array( $this->abilities, 'delete_callback' ),
			array( 'identifier' => 'field_test_key' ),
			'delete_failed'
		);
	}

	// Duplicate callback tests.

	/**
	 * Test duplicate_callback duplicates field
	 */
	public function test_duplicate_callback_duplicates_field() {
		$duplicated_field        = $this->mock_field;
		$duplicated_field['key'] = 'field_duplicated';

		$this->inject_mock_manager(
			array(
				'get_post'       => $this->mock_field,
				'duplicate_post' => $duplicated_field,
			)
		);

		$result = $this->abilities->duplicate_callback( array( 'identifier' => 'field_test_key' ) );

		$this->assertIsArray( $result );
		$this->assertEquals( 'field_duplicated', $result['key'] );
	}

	/**
	 * Test duplicate_callback returns error when field not found
	 */
	public function test_duplicate_callback_returns_error_when_not_found() {
		$this->assert_callback_error(
			array( 'get_post' => false ),
			array( $this->abilities, 'duplicate_callback' ),
			array( 'identifier' => 'nonexistent' ),
			'not_found'
		);
	}

	/**
	 * Test duplicate_callback returns error when duplication fails
	 */
	public function test_duplicate_callback_returns_error_when_duplication_fails() {
		$this->assert_callback_error(
			array(
				'get_post'       => $this->mock_field,
				'duplicate_post' => false,
			),
			array( $this->abilities, 'duplicate_callback' ),
			array( 'identifier' => 'field_test_key' ),
			'duplicate_failed'
		);
	}

	// Export callback tests.

	/**
	 * Test export_callback exports field
	 */
	public function test_export_callback_exports_field() {
		$exported_field = $this->mock_field;
		unset( $exported_field['ID'] );

		$this->inject_mock_manager(
			array(
				'get_post'                => $this->mock_field,
				'prepare_post_for_export' => $exported_field,
			)
		);

		$result = $this->abilities->export_callback( array( 'identifier' => 'field_test_key' ) );

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'ID', $result );
	}

	/**
	 * Test export_callback returns error when field not found
	 */
	public function test_export_callback_returns_error_when_not_found() {
		$this->assert_callback_error(
			array( 'get_post' => false ),
			array( $this->abilities, 'export_callback' ),
			array( 'identifier' => 'nonexistent' ),
			'not_found'
		);
	}

	/**
	 * Test export_callback returns error when export fails
	 */
	public function test_export_callback_returns_error_when_export_fails() {
		$this->assert_callback_error(
			array(
				'get_post'                => $this->mock_field,
				'prepare_post_for_export' => false,
			),
			array( $this->abilities, 'export_callback' ),
			array( 'identifier' => 'field_test_key' ),
			'export_failed'
		);
	}

	// Import callback tests.

	/**
	 * Test import_callback imports field
	 */
	public function test_import_callback_imports_field() {
		$this->inject_mock_manager( array( 'import_post' => $this->mock_field ) );

		$result = $this->abilities->import_callback( $this->test_field );

		$this->assertIsArray( $result );
		$this->assertEquals( 'field_test_key', $result['key'] );
	}

	/**
	 * Test import_callback returns error when import fails
	 */
	public function test_import_callback_returns_error_when_import_fails() {
		$this->assert_callback_error(
			array( 'import_post' => false ),
			array( $this->abilities, 'import_callback' ),
			$this->test_field,
			'import_failed'
		);
	}

	// Trash callback tests.

	/**
	 * Test trash_callback trashes field
	 */
	public function test_trash_callback_trashes_field() {
		$this->inject_mock_manager(
			array(
				'get_post'   => $this->mock_field,
				'trash_post' => true,
			)
		);

		$result = $this->abilities->trash_callback( array( 'identifier' => 'field_test_key' ) );

		$this->assertTrue( $result );
	}

	/**
	 * Test trash_callback returns error when field not found
	 */
	public function test_trash_callback_returns_error_when_not_found() {
		$this->assert_callback_error(
			array( 'get_post' => false ),
			array( $this->abilities, 'trash_callback' ),
			array( 'identifier' => 'nonexistent' ),
			'not_found'
		);
	}

	/**
	 * Test trash_callback returns error when trash fails
	 */
	public function test_trash_callback_returns_error_when_trash_fails() {
		$this->assert_callback_error(
			array(
				'get_post'   => $this->mock_field,
				'trash_post' => false,
			),
			array( $this->abilities, 'trash_callback' ),
			array( 'identifier' => 'field_test_key' ),
			'trash_failed'
		);
	}

	// Untrash callback tests.

	/**
	 * Test untrash_callback untrashes field
	 */
	public function test_untrash_callback_untrashes_field() {
		$this->inject_mock_manager( array( 'untrash_post' => true ) );

		$result = $this->abilities->untrash_callback( array( 'identifier' => 'field_test_key' ) );

		$this->assertTrue( $result );
	}

	/**
	 * Test untrash_callback returns error when untrash fails
	 */
	public function test_untrash_callback_returns_error_when_untrash_fails() {
		$this->inject_mock_manager( array( 'untrash_post' => false ) );

		$result = $this->abilities->untrash_callback( array( 'identifier' => 'nonexistent' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'untrash_failed', $result->get_error_code() );
	}
}
