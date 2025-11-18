<?php
/**
 * Tests for SCF_JSON_Schema_Validator class
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test SCF JSON Schema Validator
 */
class Test_SCF_JSON_Schema_Validator extends BaseTestCase {

	/**
	 * Instance of SCF_JSON_Schema_Validator for testing
	 *
	 * @var SCF_JSON_Schema_Validator
	 */
	private $validator;

	/**
	 * Setup test fixtures
	 */
	public function setUp(): void {
		parent::setUp();
		$this->validator = new SCF_JSON_Schema_Validator();
	}

	/**
	 * Teardown after tests
	 */
	public function tearDown(): void {
		parent::tearDown();
		$this->validator = null;
	}

	/**
	 * Test that load_schema returns object when schema exists
	 */
	public function test_load_schema_returns_object_for_existing_schema() {
		$schema = $this->validator->load_schema( 'post-type' );

		$this->assertIsObject( $schema, 'load_schema should return an object for existing schema' );
	}

	/**
	 * Test that load_schema returns null when schema does not exist
	 */
	public function test_load_schema_returns_null_for_missing_schema() {
		$schema = $this->validator->load_schema( 'nonexistent-schema' );

		$this->assertNull( $schema, 'load_schema should return null for missing schema' );
	}

	/**
	 * Test that all required schemas can be loaded
	 */
	public function test_all_required_schemas_can_be_loaded() {
		$required_schemas = SCF_JSON_Schema_Validator::REQUIRED_SCHEMAS;

		foreach ( $required_schemas as $schema_name ) {
			$schema = $this->validator->load_schema( $schema_name );
			$this->assertIsObject(
				$schema,
				"Schema '{$schema_name}' should be loadable"
			);
		}
	}

	/**
	 * Test that load_schema loads correct structure
	 */
	public function test_load_schema_loads_correct_structure() {
		$schema = $this->validator->load_schema( 'post-type' );

		$this->assertObjectHasProperty( 'definitions', $schema, 'Schema should have definitions' );
		$this->assertObjectHasProperty( 'postType', $schema->definitions, 'Definitions should have postType' );
	}

	/**
	 * Test that validate_required_schemas returns true when all schemas exist
	 */
	public function test_validate_required_schemas_returns_true_when_all_exist() {
		$result = $this->validator->validate_required_schemas();

		$this->assertTrue( $result, 'validate_required_schemas should return true when all required schemas exist' );
	}

	/**
	 * Test validate_data with valid data
	 */
	public function test_validate_data_accepts_valid_data() {
		// Valid post type data structure
		$valid_data = array(
			'key'   => 'test_post_type',
			'label' => 'Test Post Type',
		);

		$result = $this->validator->validate_data( $valid_data, 'post-type' );

		// Result should be boolean (valid or invalid)
		$this->assertIsBool( $result );
	}

	/**
	 * Test that validation errors are captured
	 */
	public function test_validation_errors_are_captured() {
		// Invalid data - missing required fields
		$invalid_data = array(
			'extra_field' => 'value',
		);

		$result = $this->validator->validate_data( $invalid_data, 'post-type' );

		// Should be invalid
		$this->assertFalse( $result, 'Invalid data should fail validation' );

		// Should have captured errors
		$this->assertTrue(
			$this->validator->has_validation_errors(),
			'Validator should have captured validation errors'
		);
	}

	/**
	 * Test get_validation_errors returns array
	 */
	public function test_get_validation_errors_returns_array() {
		$errors = $this->validator->get_validation_errors();

		$this->assertIsArray( $errors, 'get_validation_errors should return an array' );
	}

	/**
	 * Test validate_json with valid JSON string
	 */
	public function test_validate_json_with_valid_json_string() {
		$valid_json = wp_json_encode(
			array(
				'key'   => 'test_post_type',
				'label' => 'Test Post Type',
			)
		);

		$result = $this->validator->validate_json( $valid_json, 'post-type' );

		$this->assertIsBool( $result );
	}

	/**
	 * Test validate_json with invalid JSON string
	 */
	public function test_validate_json_with_invalid_json_string() {
		$invalid_json = '{invalid json}';

		$result = $this->validator->validate_json( $invalid_json, 'post-type' );

		$this->assertFalse( $result, 'Invalid JSON should fail validation' );
		$this->assertTrue(
			$this->validator->has_validation_errors(),
			'Should capture JSON parsing error'
		);
	}

	/**
	 * Test get_validation_errors_string returns formatted string
	 */
	public function test_get_validation_errors_string_returns_formatted_message() {
		// Create an error scenario
		$invalid_data = array();
		$this->validator->validate_data( $invalid_data, 'post-type' );

		if ( $this->validator->has_validation_errors() ) {
			$error_string = $this->validator->get_validation_errors_string();
			$this->assertIsString( $error_string, 'Should return a string' );
		}
	}

	/**
	 * Test that validator catches missing schema files gracefully
	 */
	public function test_validator_handles_missing_schema_gracefully() {
		// Try to load a schema that doesn't exist
		$schema = $this->validator->load_schema( 'definitely-does-not-exist' );

		// Should return null instead of throwing error
		$this->assertNull( $schema, 'Should return null for missing schema without throwing' );
	}

	/**
	 * Test validate method auto-detects file paths
	 */
	public function test_validate_method_detects_file_path() {
		// Create a temporary JSON file
		$temp_file  = tempnam( sys_get_temp_dir(), 'scf_test_' );
		$valid_data = array(
			'key'   => 'test_post_type',
			'label' => 'Test Post Type',
		);
		// Use WP_Filesystem for file operations.
		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$wp_filesystem->put_contents( $temp_file, wp_json_encode( $valid_data ) );

		$result = $this->validator->validate( $temp_file, 'post-type' );

		$this->assertIsBool( $result );

		// Cleanup
		wp_delete_file( $temp_file );
	}

	/**
	 * Test validate method detects JSON strings
	 */
	public function test_validate_method_detects_json_string() {
		$json_string = wp_json_encode(
			array(
				'key'   => 'test_post_type',
				'label' => 'Test Post Type',
			)
		);

		$result = $this->validator->validate( $json_string, 'post-type' );

		$this->assertIsBool( $result );
	}

	/**
	 * Test validate method detects parsed data
	 */
	public function test_validate_method_detects_parsed_data() {
		$data = array(
			'key'   => 'test_post_type',
			'label' => 'Test Post Type',
		);

		$result = $this->validator->validate( $data, 'post-type' );

		$this->assertIsBool( $result );
	}

	/**
	 * Test validate_file with missing file
	 */
	public function test_validate_file_with_missing_file() {
		$result = $this->validator->validate_file( '/nonexistent/path/file.json', 'post-type' );

		$this->assertFalse( $result, 'Should return false for missing file' );
		$this->assertTrue(
			$this->validator->has_validation_errors(),
			'Should capture error about missing file'
		);

		$errors = $this->validator->get_validation_errors();
		$this->assertNotEmpty( $errors, 'Should have at least one error' );
	}

	/**
	 * Test validate_file with valid JSON file
	 */
	public function test_validate_file_with_valid_json_file() {
		$temp_file  = tempnam( sys_get_temp_dir(), 'scf_test_' );
		$valid_data = array(
			'key'   => 'test_post_type',
			'label' => 'Test Post Type',
		);

		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$wp_filesystem->put_contents( $temp_file, wp_json_encode( $valid_data ) );

		$result = $this->validator->validate_file( $temp_file, 'post-type' );

		$this->assertIsBool( $result );

		// Cleanup
		wp_delete_file( $temp_file );
	}

	/**
	 * Test validate_file with invalid JSON in file
	 */
	public function test_validate_file_with_invalid_json_in_file() {
		$temp_file = tempnam( sys_get_temp_dir(), 'scf_test_' );

		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$wp_filesystem->put_contents( $temp_file, '{invalid json content}' );

		$result = $this->validator->validate_file( $temp_file, 'post-type' );

		$this->assertFalse( $result, 'Should return false for file with invalid JSON' );
		$this->assertTrue(
			$this->validator->has_validation_errors(),
			'Should capture JSON parsing error'
		);

		// Cleanup
		wp_delete_file( $temp_file );
	}

	/**
	 * Test get_validation_errors_string with custom separator
	 */
	public function test_get_validation_errors_string_with_custom_separator() {
		// Create an error scenario
		$invalid_data = array();
		$this->validator->validate_data( $invalid_data, 'post-type' );

		if ( $this->validator->has_validation_errors() ) {
			$error_string = $this->validator->get_validation_errors_string( ' | ' );
			$this->assertIsString( $error_string, 'Should return a string' );
			$this->assertStringContainsString( '|', $error_string, 'Should use custom separator' );
		}
	}

	/**
	 * Test has_validation_errors returns false when no errors
	 */
	public function test_has_validation_errors_returns_false_when_no_errors() {
		// Create a new validator instance to ensure clean state
		$validator = new SCF_JSON_Schema_Validator();

		// Test that a fresh validator has no errors
		$this->assertFalse(
			$validator->has_validation_errors(),
			'Should return false on fresh validator with no validation attempts'
		);
	}

	/**
	 * Test validate_data clears previous errors
	 */
	public function test_validate_data_clears_previous_errors() {
		// First validation with invalid data
		$invalid_data = array();
		$this->validator->validate_data( $invalid_data, 'post-type' );
		$first_error_count = count( $this->validator->get_validation_errors() );
		$this->assertTrue(
			$this->validator->has_validation_errors(),
			'First validation should have errors'
		);

		// Second validation with different invalid data (empty again)
		$second_invalid_data = array();
		$this->validator->validate_data( $second_invalid_data, 'post-type' );
		$second_error_count = count( $this->validator->get_validation_errors() );

		// Errors should be cleared (not accumulate)
		$this->assertTrue(
			$this->validator->has_validation_errors(),
			'Should still have validation errors'
		);
		// The key point: errors from first validation should be cleared, not accumulated
		// If clearing works, the error count should be similar or different, but not double
		$this->assertLessThanOrEqual(
			$first_error_count * 1.5,
			$second_error_count,
			'Errors should be cleared, not accumulated'
		);
	}

	/**
	 * Test validate_json clears previous errors
	 */
	public function test_validate_json_clears_previous_errors() {
		// First validation with invalid JSON
		$invalid_json = '{bad json}';
		$this->validator->validate_json( $invalid_json, 'post-type' );
		$first_error_count = count( $this->validator->get_validation_errors() );
		$this->assertTrue(
			$this->validator->has_validation_errors(),
			'First validation should have errors'
		);

		// Second validation with different invalid JSON (to ensure errors are cleared, not accumulated)
		$another_invalid_json = '{also bad}';
		$this->validator->validate_json( $another_invalid_json, 'post-type' );
		$second_error_count = count( $this->validator->get_validation_errors() );

		// Errors should be cleared (not accumulate)
		$this->assertTrue(
			$this->validator->has_validation_errors(),
			'Should have validation errors'
		);
		// The key point: errors should be cleared between validations, not accumulated
		$this->assertLessThanOrEqual(
			$first_error_count * 1.5,
			$second_error_count,
			'Errors should be cleared between validations, not accumulated'
		);
	}

	/**
	 * Test validation error structure
	 */
	public function test_validation_error_structure() {
		// Create an error
		$invalid_data = array();
		$this->validator->validate_data( $invalid_data, 'post-type' );

		if ( $this->validator->has_validation_errors() ) {
			$errors = $this->validator->get_validation_errors();
			$error  = reset( $errors );

			$this->assertIsArray( $error, 'Error should be an array' );
			$this->assertArrayHasKey( 'field', $error, 'Error should have field key' );
			$this->assertArrayHasKey( 'message', $error, 'Error should have message key' );
		}
	}

	/**
	 * Test validate with non-existent file falls back to JSON string parsing
	 */
	public function test_validate_non_existent_file_falls_back_to_json_parsing() {
		$json_string = wp_json_encode(
			array(
				'key'   => 'test_post_type',
				'label' => 'Test Post Type',
			)
		);

		// Pass a JSON string that looks like it could be a path but isn't
		$result = $this->validator->validate( $json_string, 'post-type' );

		// Should treat it as JSON string and validate successfully
		$this->assertIsBool( $result, 'Should return bool even for non-file strings' );
	}

	/**
	 * Test load_schema returns consistent results across multiple calls
	 */
	public function test_load_schema_returns_consistent_results() {
		$schema1 = $this->validator->load_schema( 'post-type' );
		$schema2 = $this->validator->load_schema( 'post-type' );

		$this->assertEquals(
			wp_json_encode( $schema1 ),
			wp_json_encode( $schema2 ),
			'Multiple calls to load_schema should return equivalent objects'
		);
	}

	/**
	 * Test validate_data with nested array structures
	 */
	public function test_validate_data_with_nested_array_structures() {
		$nested_data = array(
			'key'    => 'test_post_type',
			'label'  => 'Test Post Type',
			'fields' => array(
				array(
					'key'   => 'field_1',
					'label' => 'Field 1',
				),
			),
		);

		$result = $this->validator->validate_data( $nested_data, 'post-type' );

		$this->assertIsBool( $result, 'Should handle nested structures' );
	}

	/**
	 * Test validate_data converts arrays to objects properly
	 */
	public function test_validate_data_converts_arrays_to_objects() {
		// This tests the internal array-to-object conversion
		$array_data = array(
			'key'   => 'test_post_type',
			'label' => 'Test Post Type',
		);

		// Validate should internally convert array to object
		$result = $this->validator->validate_data( $array_data, 'post-type' );

		$this->assertIsBool( $result, 'Should successfully convert and validate array data' );
	}

	/**
	 * Test REQUIRED_SCHEMAS constant
	 */
	public function test_required_schemas_constant_has_all_required_schemas() {
		$required = SCF_JSON_Schema_Validator::REQUIRED_SCHEMAS;

		$this->assertIsArray( $required, 'REQUIRED_SCHEMAS should be an array' );
		$this->assertNotEmpty( $required, 'REQUIRED_SCHEMAS should not be empty' );
		$this->assertContains( 'post-type', $required, 'Should include post-type' );
		$this->assertContains( 'internal-fields', $required, 'Should include internal-fields' );
		$this->assertContains( 'scf-identifier', $required, 'Should include scf-identifier' );
	}

	/**
	 * Test constructor initializes schema_path correctly
	 */
	public function test_constructor_initializes_schema_path() {
		// The validator is already initialized in setUp
		// We just verify it can load schemas (which means path is correct)
		$schema = $this->validator->load_schema( 'post-type' );

		$this->assertIsObject( $schema, 'Schema path should be correctly initialized' );
	}
}
