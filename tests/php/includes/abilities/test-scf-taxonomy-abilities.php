<?php
/**
 * Tests for SCF_Taxonomy_Abilities class
 *
 * Note: Full integration tests including create/get/update/delete flows are covered
 * by e2e tests in tests/e2e/abilities-taxonomies.spec.ts. These PHPUnit tests focus
 * on callback structure and error handling that can be tested in WorDBless.
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
 * Test SCF Taxonomy Abilities callbacks
 */
class Test_SCF_Taxonomy_Abilities extends BaseTestCase {

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
		$this->assertEquals( 'taxonomy_not_found', $result->get_error_code() );
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
		$this->assertEquals( 'taxonomy_not_found', $result->get_error_code() );
	}

	/**
	 * Test delete_callback returns WP_Error for non-existent ID
	 */
	public function test_delete_taxonomy_callback_not_found_returns_error() {
		$result = $this->abilities->delete_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'taxonomy_not_found', $result->get_error_code() );
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
		$this->assertEquals( 'taxonomy_not_found', $result->get_error_code() );
	}

	/**
	 * Test export_callback returns WP_Error for non-existent ID
	 */
	public function test_export_taxonomy_callback_not_found_returns_error() {
		$result = $this->abilities->export_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'taxonomy_not_found', $result->get_error_code() );
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

	// Schema tests.

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

	// Helper method tests.

	/**
	 * Test not_found_error returns correct error code
	 */
	public function test_not_found_error_returns_correct_code() {
		$reflection = new ReflectionClass( $this->abilities );
		$method     = $reflection->getMethod( 'not_found_error' );
		$method->setAccessible( true );

		$error = $method->invoke( $this->abilities );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertEquals( 'taxonomy_not_found', $error->get_error_code() );
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
}
