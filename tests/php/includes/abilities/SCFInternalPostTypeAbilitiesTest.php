<?php
/**
 * Tests for SCF_Internal_Post_Type_Abilities base class
 *
 * Tests the abstract base class behavior using SCF_Taxonomy_Abilities as the
 * concrete implementation. Full integration tests are covered by E2E tests.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load mock Abilities API functions before loading the class.
require_once __DIR__ . '/abilities-api-mocks.php';

// Load ACF internal post type class to register the taxonomy instance.
require_once dirname( __DIR__, 4 ) . '/includes/post-types/class-acf-taxonomy.php';

// Load the abilities classes after ACF classes are loaded.
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-internal-post-type-abilities.php';
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-taxonomy-abilities.php';

/**
 * Tests for SCF_Internal_Post_Type_Abilities base class
 *
 * Uses SCF_Taxonomy_Abilities as concrete implementation to test
 * the shared base class behavior.
 */
class SCFInternalPostTypeAbilitiesTest extends BaseTestCase {

	/**
	 * Instance of SCF_Taxonomy_Abilities for testing
	 *
	 * @var SCF_Taxonomy_Abilities
	 */
	private $abilities;

	/**
	 * Test taxonomy data
	 *
	 * @var array
	 */
	private $test_taxonomy = array(
		'key'      => 'taxonomy_phpunit_test',
		'title'    => 'PHPUnit Test Taxonomy',
		'taxonomy' => 'phpunit_test',
	);

	/**
	 * Setup test fixtures
	 */
	public function setUp(): void {
		parent::setUp();
		$this->abilities = acf_get_instance( 'SCF_Taxonomy_Abilities' );
	}

	/**
	 * Helper to inject a mock instance into the abilities object.
	 *
	 * @param array $method_returns Map of method names to return values.
	 * @return \PHPUnit\Framework\MockObject\MockObject The mock instance.
	 */
	private function inject_mock_instance( array $method_returns ) {
		$mock_instance            = $this->createMock( ACF_Taxonomy::class );
		$mock_instance->hook_name = 'acf_taxonomy';

		foreach ( $method_returns as $method => $return_value ) {
			$mock_instance->method( $method )->willReturn( $return_value );
		}

		$reflection = new ReflectionClass( SCF_Internal_Post_Type_Abilities::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( $this->abilities, $mock_instance );

		return $mock_instance;
	}

	// Constructor tests.

	/**
	 * Test constructor registers WordPress action hooks
	 */
	public function test_constructor_registers_action_hooks() {
		// The constructor should have registered these action hooks.
		$this->assertNotFalse(
			has_action( 'wp_abilities_api_categories_init', array( $this->abilities, 'register_categories' ) ),
			'Should register wp_abilities_api_categories_init action'
		);
		$this->assertNotFalse(
			has_action( 'wp_abilities_api_init', array( $this->abilities, 'register_abilities' ) ),
			'Should register wp_abilities_api_init action'
		);
	}

	/**
	 * Test constructor does not register hooks when schema validation fails
	 */
	public function test_constructor_skips_hooks_when_schemas_invalid() {
		global $acf_instances;

		// Store original validator.
		$original_validator = $acf_instances['SCF_JSON_Schema_Validator'] ?? null;

		// Create mock validator that returns false.
		$mock_validator                             = new class() {
			/**
			 * Mock validation that always fails.
			 *
			 * @return bool Always returns false.
			 */
			public function validate_required_schemas() {
				return false;
			}
		};
		$acf_instances['SCF_JSON_Schema_Validator'] = $mock_validator;

		// Create a fresh instance (bypassing acf_get_instance cache for this class).
		$test_instance = new SCF_Taxonomy_Abilities();

		// Verify NO hooks were registered for this instance.
		$this->assertFalse(
			has_action( 'wp_abilities_api_categories_init', array( $test_instance, 'register_categories' ) ),
			'Should NOT register hooks when schema validation fails'
		);

		// Restore original validator.
		if ( $original_validator ) {
			$acf_instances['SCF_JSON_Schema_Validator'] = $original_validator;
		}
	}

	// Registration tests.

	/**
	 * Test register_categories registers the scf-taxonomies category
	 */
	public function test_register_categories_registers_scf_taxonomies() {
		global $mock_registered_ability_categories;
		$mock_registered_ability_categories = array();

		$this->abilities->register_categories();

		$this->assertArrayHasKey( 'scf-taxonomies', $mock_registered_ability_categories );
		$this->assertArrayHasKey( 'label', $mock_registered_ability_categories['scf-taxonomies'] );
	}

	/**
	 * Test register_abilities registers all expected abilities
	 */
	public function test_register_abilities_registers_all_abilities() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$expected_abilities = array(
			'scf/list-taxonomies',
			'scf/get-taxonomy',
			'scf/create-taxonomy',
			'scf/update-taxonomy',
			'scf/delete-taxonomy',
			'scf/duplicate-taxonomy',
			'scf/export-taxonomy',
			'scf/import-taxonomy',
		);

		foreach ( $expected_abilities as $ability_name ) {
			$this->assertArrayHasKey( $ability_name, $mock_registered_abilities, "Missing ability: $ability_name" );
		}
	}

	/**
	 * Test registered abilities have correct category
	 */
	public function test_registered_abilities_have_correct_category() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		foreach ( $mock_registered_abilities as $name => $args ) {
			$this->assertEquals(
				'scf-taxonomies',
				$args['category'],
				"Ability $name has wrong category"
			);
		}
	}

	/**
	 * Test registered abilities have execute callbacks
	 */
	public function test_registered_abilities_have_execute_callbacks() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		foreach ( $mock_registered_abilities as $name => $args ) {
			$this->assertArrayHasKey( 'execute_callback', $args, "Ability $name missing execute_callback" );
			$this->assertIsCallable( $args['execute_callback'], "Ability $name execute_callback is not callable" );
		}
	}

	/**
	 * Test registered abilities have input schemas
	 */
	public function test_registered_abilities_have_input_schemas() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		foreach ( $mock_registered_abilities as $name => $args ) {
			$this->assertArrayHasKey( 'input_schema', $args, "Ability $name missing input_schema" );
		}
	}

	/**
	 * Test delete ability is marked as destructive
	 */
	public function test_delete_ability_is_destructive() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$this->assertArrayHasKey( 'scf/delete-taxonomy', $mock_registered_abilities );
		$ability     = $mock_registered_abilities['scf/delete-taxonomy'];
		$meta        = $ability['meta'] ?? array();
		$annotations = $meta['annotations'] ?? array();
		$this->assertTrue( $annotations['destructive'] ?? false, 'Delete ability should be marked destructive' );
	}

	/**
	 * Test export ability is marked as readonly
	 */
	public function test_export_ability_is_readonly() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$this->assertArrayHasKey( 'scf/export-taxonomy', $mock_registered_abilities );
		$ability     = $mock_registered_abilities['scf/export-taxonomy'];
		$meta        = $ability['meta'] ?? array();
		$annotations = $meta['annotations'] ?? array();
		$this->assertTrue( $annotations['readonly'] ?? false, 'Export ability should be marked readonly' );
	}

	/**
	 * Test list ability is marked as readonly
	 */
	public function test_list_ability_is_readonly() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$this->assertArrayHasKey( 'scf/list-taxonomies', $mock_registered_abilities );
		$ability     = $mock_registered_abilities['scf/list-taxonomies'];
		$meta        = $ability['meta'] ?? array();
		$annotations = $meta['annotations'] ?? array();
		$this->assertTrue( $annotations['readonly'] ?? false, 'List ability should be marked readonly' );
	}

	/**
	 * Test get ability is marked as readonly
	 */
	public function test_get_ability_is_readonly() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$this->assertArrayHasKey( 'scf/get-taxonomy', $mock_registered_abilities );
		$ability     = $mock_registered_abilities['scf/get-taxonomy'];
		$meta        = $ability['meta'] ?? array();
		$annotations = $meta['annotations'] ?? array();
		$this->assertTrue( $annotations['readonly'] ?? false, 'Get ability should be marked readonly' );
	}

	/**
	 * Test abilities that return data have output_schema
	 */
	public function test_data_returning_abilities_have_output_schema() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$abilities_with_output = array(
			'scf/list-taxonomies',
			'scf/get-taxonomy',
			'scf/create-taxonomy',
			'scf/update-taxonomy',
			'scf/duplicate-taxonomy',
			'scf/export-taxonomy',
			'scf/import-taxonomy',
		);

		foreach ( $abilities_with_output as $ability_name ) {
			$this->assertArrayHasKey( $ability_name, $mock_registered_abilities );
			$this->assertArrayHasKey(
				'output_schema',
				$mock_registered_abilities[ $ability_name ],
				"Ability $ability_name should have output_schema"
			);
		}
	}

	/**
	 * Test delete ability returns boolean success schema
	 */
	public function test_delete_ability_has_boolean_output_schema() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$this->assertArrayHasKey( 'scf/delete-taxonomy', $mock_registered_abilities );
		$ability = $mock_registered_abilities['scf/delete-taxonomy'];
		$this->assertArrayHasKey( 'output_schema', $ability );
		// Delete returns boolean true on success.
		$this->assertEquals( 'boolean', $ability['output_schema']['type'] );
	}

	// Callback tests.

	/**
	 * Test list_callback returns array
	 *
	 * Note: Full CRUD flow tests require WordPress post persistence
	 * which WorDBless doesn't fully support. These are tested in E2E.
	 */
	public function test_list_taxonomies_callback_returns_array() {
		$result = $this->abilities->list_callback( array() );

		$this->assertIsArray( $result );
	}

	/**
	 * Test list_callback with filter parameter
	 */
	public function test_list_taxonomies_callback_with_filter() {
		$result = $this->abilities->list_callback(
			array( 'filter' => array( 'active' => true ) )
		);

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_callback returns WP_Error for non-existent ID
	 */
	public function test_get_taxonomy_callback_not_found_returns_error() {
		$result = $this->abilities->get_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
	}

	/**
	 * Test get_callback returns 404 status for non-existent key
	 */
	public function test_get_taxonomy_callback_not_found_returns_404_status() {
		$result = $this->abilities->get_callback(
			array( 'identifier' => 'nonexistent_taxonomy' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$error_data = $result->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test create_callback returns array with key
	 */
	public function test_create_taxonomy_callback_returns_array_with_key() {
		$result = $this->abilities->create_callback( $this->test_taxonomy );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'key', $result );
		$this->assertEquals( $this->test_taxonomy['key'], $result['key'] );
	}

	/**
	 * Test create_callback returns array with title
	 */
	public function test_create_taxonomy_callback_returns_array_with_title() {
		$result = $this->abilities->create_callback( $this->test_taxonomy );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertEquals( $this->test_taxonomy['title'], $result['title'] );
	}

	/**
	 * Test update_callback returns WP_Error for non-existent ID
	 */
	public function test_update_taxonomy_callback_not_found_returns_error() {
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
	 * Test delete_callback returns WP_Error for non-existent ID
	 */
	public function test_delete_taxonomy_callback_not_found_returns_error() {
		$result = $this->abilities->delete_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
	}

	/**
	 * Test delete_callback returns 404 status for non-existent key
	 */
	public function test_delete_taxonomy_callback_not_found_returns_404_status() {
		$result = $this->abilities->delete_callback(
			array( 'identifier' => 'nonexistent_delete_target' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$error_data = $result->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test duplicate_callback returns WP_Error for non-existent ID
	 */
	public function test_duplicate_taxonomy_callback_not_found_returns_error() {
		$result = $this->abilities->duplicate_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
	}

	/**
	 * Test export_callback returns WP_Error for non-existent ID
	 */
	public function test_export_taxonomy_callback_not_found_returns_error() {
		$result = $this->abilities->export_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
	}

	/**
	 * Test import_callback returns array
	 */
	public function test_import_taxonomy_callback_returns_array() {
		$result = $this->abilities->import_callback( $this->test_taxonomy );

		$this->assertIsArray( $result );
	}

	/**
	 * Test import_callback returns correct taxonomy
	 */
	public function test_import_taxonomy_callback_returns_correct_taxonomy() {
		$result = $this->abilities->import_callback( $this->test_taxonomy );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->test_taxonomy['taxonomy'], $result['taxonomy'] );
	}

	/**
	 * Test import_callback returns correct title
	 */
	public function test_import_taxonomy_callback_returns_correct_title() {
		$result = $this->abilities->import_callback( $this->test_taxonomy );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->test_taxonomy['title'], $result['title'] );
	}

	// Schema method tests.

	/**
	 * Test get_entity_schema returns valid schema
	 */
	public function test_get_entity_schema_returns_array() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'get_entity_schema' );
		$method->setAccessible( true );

		$schema = $method->invoke( $this->abilities );

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'type', $schema );
		$this->assertEquals( 'object', $schema['type'] );
	}

	/**
	 * Test get_entity_schema has required fields
	 */
	public function test_get_entity_schema_has_required_fields() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'get_entity_schema' );
		$method->setAccessible( true );

		$schema = $method->invoke( $this->abilities );

		$this->assertArrayHasKey( 'required', $schema );
		$this->assertContains( 'key', $schema['required'] );
		$this->assertContains( 'title', $schema['required'] );
		$this->assertContains( 'taxonomy', $schema['required'] );
	}

	/**
	 * Test get_scf_identifier_schema returns valid schema
	 */
	public function test_get_scf_identifier_schema_returns_array() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'get_scf_identifier_schema' );
		$method->setAccessible( true );

		$schema = $method->invoke( $this->abilities );

		$this->assertIsArray( $schema );
		// Should have description and allow integer or string.
		$this->assertArrayHasKey( 'description', $schema );
	}

	/**
	 * Test get_internal_fields_schema returns valid schema
	 */
	public function test_get_internal_fields_schema_returns_array() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'get_internal_fields_schema' );
		$method->setAccessible( true );

		$schema = $method->invoke( $this->abilities );

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'properties', $schema );
	}

	/**
	 * Test get_internal_fields_schema contains ID property
	 */
	public function test_get_internal_fields_schema_has_id_property() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'get_internal_fields_schema' );
		$method->setAccessible( true );

		$schema = $method->invoke( $this->abilities );

		$this->assertArrayHasKey( 'ID', $schema['properties'] );
	}

	/**
	 * Test get_entity_with_internal_fields_schema returns merged schema
	 */
	public function test_get_entity_with_internal_fields_schema_returns_array() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'get_entity_with_internal_fields_schema' );
		$method->setAccessible( true );

		$schema = $method->invoke( $this->abilities );

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'properties', $schema );
	}

	/**
	 * Test get_entity_with_internal_fields_schema contains both entity and internal fields
	 */
	public function test_get_entity_with_internal_fields_schema_has_merged_properties() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'get_entity_with_internal_fields_schema' );
		$method->setAccessible( true );

		$schema = $method->invoke( $this->abilities );

		// Should have entity-specific field (taxonomy has 'taxonomy' field).
		$this->assertArrayHasKey( 'taxonomy', $schema['properties'], 'Should have entity-specific taxonomy field' );
		// Should have internal field (ID).
		$this->assertArrayHasKey( 'ID', $schema['properties'], 'Should have internal ID field' );
	}

	// Private method tests.

	/**
	 * Test not_found_error returns correct error code
	 */
	public function test_not_found_error_returns_correct_code() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'not_found_error' );
		$method->setAccessible( true );

		$error = $method->invoke( $this->abilities );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertEquals( 'not_found', $error->get_error_code() );
	}

	/**
	 * Test not_found_error returns 404 status
	 */
	public function test_not_found_error_returns_404() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'not_found_error' );
		$method->setAccessible( true );

		$error      = $method->invoke( $this->abilities );
		$error_data = $error->get_error_data();

		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test entity_name returns correct value for taxonomy
	 */
	public function test_entity_name_returns_taxonomy() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'entity_name' );
		$method->setAccessible( true );

		$this->assertEquals( 'taxonomy', $method->invoke( $this->abilities ) );
	}

	/**
	 * Test entity_name_plural returns correct value for taxonomy
	 */
	public function test_entity_name_plural_returns_taxonomies() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'entity_name_plural' );
		$method->setAccessible( true );

		$this->assertEquals( 'taxonomies', $method->invoke( $this->abilities ) );
	}

	/**
	 * Test schema_name returns correct schema file name
	 */
	public function test_schema_name_returns_taxonomy() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'schema_name' );
		$method->setAccessible( true );

		$this->assertEquals( 'taxonomy', $method->invoke( $this->abilities ) );
	}

	/**
	 * Test ability_category returns correct category
	 */
	public function test_ability_category_returns_scf_taxonomies() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'ability_category' );
		$method->setAccessible( true );

		$this->assertEquals( 'scf-taxonomies', $method->invoke( $this->abilities ) );
	}

	/**
	 * Test ability_name uses plural for list action
	 */
	public function test_ability_name_uses_plural_for_list() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'ability_name' );
		$method->setAccessible( true );

		$this->assertEquals( 'scf/list-taxonomies', $method->invoke( $this->abilities, 'list' ) );
	}

	/**
	 * Test ability_name uses singular for non-list actions
	 */
	public function test_ability_name_uses_singular_for_get() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'ability_name' );
		$method->setAccessible( true );

		$this->assertEquals( 'scf/get-taxonomy', $method->invoke( $this->abilities, 'get' ) );
	}

	/**
	 * Test instance() method caches result
	 */
	public function test_instance_caches_result() {
		$reflection = new ReflectionClass( SCF_Internal_Post_Type_Abilities::class );

		// Access the private $instance property from base class.
		$property = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );

		// Reset instance to null for this test.
		$property->setValue( $this->abilities, null );

		// Initially should be null after reset.
		$this->assertNull( $property->getValue( $this->abilities ), 'Instance should be null after reset' );

		// Call instance() method.
		$method = $reflection->getMethod( 'instance' );
		$method->setAccessible( true );
		$first_call = $method->invoke( $this->abilities );

		// Should now be cached.
		$cached = $property->getValue( $this->abilities );
		$this->assertNotNull( $cached, 'Instance should be cached after first call' );
		$this->assertSame( $first_call, $cached, 'Cached value should match first call result' );

		// Second call should return same cached instance.
		$second_call = $method->invoke( $this->abilities );
		$this->assertSame( $first_call, $second_call, 'Second call should return cached instance' );
	}

	// Mocked callback tests.

	/**
	 * Test create_callback returns error for duplicate key
	 */
	public function test_create_callback_returns_error_for_duplicate_key() {
		$this->inject_mock_instance(
			array(
				'get_post' => array(
					'ID'  => 123,
					'key' => 'existing_key',
				),
			)
		);

		$result = $this->abilities->create_callback( array( 'key' => 'existing_key' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'already_exists', $result->get_error_code() );
	}

	/**
	 * Test create_callback succeeds when no duplicate
	 */
	public function test_create_callback_success() {
		$created_entity = array(
			'ID'    => 456,
			'key'   => 'new_key',
			'title' => 'New Taxonomy',
		);

		$this->inject_mock_instance(
			array(
				'get_post'    => null,
				'update_post' => $created_entity,
			)
		);

		$result = $this->abilities->create_callback(
			array(
				'key'   => 'new_key',
				'title' => 'New Taxonomy',
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 456, $result['ID'] );
		$this->assertEquals( 'new_key', $result['key'] );
	}

	/**
	 * Test update_callback succeeds with valid entity
	 */
	public function test_update_callback_success() {
		$existing_entity = array(
			'ID'    => 123,
			'key'   => 'test_key',
			'title' => 'Old Title',
		);
		$updated_entity  = array(
			'ID'    => 123,
			'key'   => 'test_key',
			'title' => 'New Title',
		);

		$this->inject_mock_instance(
			array(
				'get_post'    => $existing_entity,
				'update_post' => $updated_entity,
			)
		);

		$result = $this->abilities->update_callback(
			array(
				'ID'    => 123,
				'title' => 'New Title',
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 123, $result['ID'] );
		$this->assertEquals( 'New Title', $result['title'] );
	}

	/**
	 * Test delete_callback succeeds when entity exists
	 */
	public function test_delete_callback_success() {
		$this->inject_mock_instance(
			array(
				'get_post'    => array(
					'ID'  => 123,
					'key' => 'test_key',
				),
				'delete_post' => true,
			)
		);

		$result = $this->abilities->delete_callback( array( 'identifier' => 123 ) );

		$this->assertTrue( $result );
	}
}
