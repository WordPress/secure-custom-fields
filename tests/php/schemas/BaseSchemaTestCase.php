<?php
/**
 * Abstract base class for SCF JSON Schema validation tests.
 *
 * @package wordpress/secure-custom-fields
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 3 ) . '/includes/class-scf-json-schema-validator.php';

/**
 * Abstract Class BaseSchemaTestCase
 *
 * Provides common functionality for testing SCF JSON schemas.
 * Extend this class and implement the abstract methods to test a specific schema.
 */
abstract class BaseSchemaTestCase extends TestCase {

	/**
	 * The schema validator instance.
	 *
	 * @var SCF_JSON_Schema_Validator
	 */
	protected $validator;

	/**
	 * Path to test fixtures.
	 *
	 * @var string
	 */
	protected $fixtures_path;

	/**
	 * Get the schema type to test (e.g., 'post-type', 'taxonomy').
	 *
	 * @return string
	 */
	abstract protected function get_schema_type(): string;

	/**
	 * Get the path to the fixtures directory for this schema.
	 *
	 * @return string
	 */
	abstract protected function get_fixtures_path(): string;

	/**
	 * Get the definition name in the schema (e.g., 'postType', 'taxonomy').
	 *
	 * @return string
	 */
	abstract protected function get_definition_name(): string;

	/**
	 * Get the required fields for this schema.
	 *
	 * @return array
	 */
	abstract protected function get_required_fields(): array;

	/**
	 * Data provider for valid entities.
	 *
	 * @return array
	 */
	abstract public function validEntitiesProvider(): array;

	/**
	 * Data provider for invalid entities.
	 *
	 * @return array
	 */
	abstract public function invalidEntitiesProvider(): array;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->validator     = new SCF_JSON_Schema_Validator();
		$this->fixtures_path = $this->get_fixtures_path();
	}

	/**
	 * Test that the schema loads correctly.
	 */
	public function test_schema_loads() {
		$schema_type     = $this->get_schema_type();
		$definition_name = $this->get_definition_name();
		$required_fields = $this->get_required_fields();

		$schema = $this->validator->load_schema( $schema_type );

		$this->assertNotNull( $schema, ucfirst( $schema_type ) . ' schema should load successfully' );
		$this->assertObjectHasProperty( 'oneOf', $schema, 'Schema should use oneOf for flexibility' );
		$this->assertObjectHasProperty( 'definitions', $schema, 'Schema should have definitions' );
		$this->assertObjectHasProperty( $definition_name, $schema->definitions, "Schema should define {$definition_name}" );

		// Check that the definition has the correct required fields.
		$definition = $schema->definitions->$definition_name;
		foreach ( $required_fields as $field ) {
			$this->assertContains( $field, $definition->required, "{$field} should be required" );
		}
	}

	/**
	 * Test validation of valid entities using data provider.
	 *
	 * @dataProvider validEntitiesProvider
	 * @param mixed  $data        The entity data to validate.
	 * @param string $description Test description.
	 */
	public function test_valid_entities_from_data_provider( $data, $description ) {
		$result = $this->validator->validate( $data, $this->get_schema_type() );
		$this->assertTrue( $result, $description );
		$this->assertFalse( $this->validator->has_validation_errors(), 'Should have no validation errors' );
	}

	/**
	 * Test validation of invalid entities using data provider.
	 *
	 * @dataProvider invalidEntitiesProvider
	 * @param mixed  $data        The entity data to validate.
	 * @param string $description Test description.
	 */
	public function test_invalid_entities_from_data_provider( $data, $description ) {
		$result = $this->validator->validate( $data, $this->get_schema_type() );
		$this->assertFalse( $result, $description );
		$this->assertTrue( $this->validator->has_validation_errors(), 'Should have validation errors' );
	}

	/**
	 * Test validation with valid fixture files (JSON file import scenarios).
	 */
	public function test_valid_entities_from_fixture_files() {
		if ( empty( $this->fixtures_path ) ) {
			$this->assertTrue( true, 'No fixture files - using data providers only' );
			return;
		}

		$valid_files = glob( $this->fixtures_path . 'valid/*.json' );
		$this->assertNotEmpty( $valid_files, 'Should have valid fixture files' );

		foreach ( $valid_files as $file_path ) {
			$filename = basename( $file_path );
			$result   = $this->validator->validate( $file_path, $this->get_schema_type() );

			if ( ! $result ) {
				$errors = $this->validator->get_validation_errors_string();
				$this->fail( "Valid fixture {$filename} should pass validation. Errors: {$errors}" );
			}

			$this->assertTrue( $result, "Valid fixture {$filename} should pass validation" );
		}
	}

	/**
	 * Test validation with invalid fixture files (JSON file import scenarios).
	 */
	public function test_invalid_entities_from_fixture_files() {
		if ( empty( $this->fixtures_path ) ) {
			$this->assertTrue( true, 'No fixture files - using data providers only' );
			return;
		}

		$invalid_files = glob( $this->fixtures_path . 'invalid/*.json' );
		$this->assertNotEmpty( $invalid_files, 'Should have invalid fixture files' );

		foreach ( $invalid_files as $file_path ) {
			$filename = basename( $file_path );
			$result   = $this->validator->validate( $file_path, $this->get_schema_type() );
			$this->assertFalse( $result, "Invalid fixture {$filename} should fail validation" );
		}
	}
}
