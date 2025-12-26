<?php
/**
 * JSON Schema Validator for SCF entities
 *
 * @package SCF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_JSON_Schema_Validator' ) ) :

	/**
	 * SCF JSON Schema Validator
	 *
	 * Validates JSON data against schemas for SCF entities. Currently supports post types.
	 * Uses the justinrainbow/json-schema library for validation.
	 *
	 * @since SCF 6.x
	 */
	class SCF_JSON_Schema_Validator {

		/**
		 * Required schema files for SCF abilities.
		 *
		 * @var array
		 */
		public const REQUIRED_SCHEMAS = array( 'post-type', 'taxonomy', 'ui-options-page', 'field-group', 'internal-properties', 'scf-identifier' );

		/**
		 * The last validation errors.
		 *
		 * @var array
		 */
		private $validation_errors = array();

		/**
		 * Base path for schema files.
		 *
		 * @var string
		 */
		private $schema_path;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->schema_path = acf_get_path( 'schemas/' );
		}



		/**
		 * Smart validation method that auto-detects input type.
		 *
		 * @param mixed  $input File path, JSON string, or parsed data to validate.
		 * @param string $schema_name The name of the schema file (without .schema.json extension).
		 * @return bool True if valid, false otherwise.
		 */
		public function validate( $input, $schema_name ) {
			// Auto-detect input type and handle appropriately
			if ( is_string( $input ) ) {
				if ( file_exists( $input ) ) {
					// It's a file path
					return $this->validate_file( $input, $schema_name );
				} else {
					// It's a JSON string
					return $this->validate_json( $input, $schema_name );
				}
			}
			// It's already parsed data
			return $this->validate_data( $input, $schema_name );
		}

		/**
		 * Validates parsed data against a schema.
		 *
		 * @param array|object $data The data to validate (arrays are converted to objects).
		 * @param string       $schema_name The name of the schema file (without .schema.json extension).
		 * @return bool True if valid, false otherwise.
		 */
		public function validate_data( $data, $schema_name ) {
			$this->clear_validation_errors();

			$schema = $this->load_schema( $schema_name );
			if ( ! $schema ) {
				$this->add_validation_error( 'system', 'Failed to load schema: ' . $schema_name );
				return false;
			}

			// Convert arrays to objects recursively for JsonSchema validation (library expects objects)
			if ( is_array( $data ) ) {
				$data = json_decode( wp_json_encode( $data ) );
			}

			// Create schema storage and register schemas for $ref support.
			// Use full file:// URIs so relative refs resolve correctly within the schemas directory.
			$schema_storage = new JsonSchema\SchemaStorage();

			// Build base URI for the schemas directory.
			$schemas_base_uri = 'file://' . realpath( $this->schema_path ) . '/';

			// Register common schema (referenced by post-type, taxonomy, ui-options-page, field-group).
			$common_schema_path    = $this->schema_path . 'common.schema.json';
			$common_schema_content = wp_json_file_decode( $common_schema_path );
			$schema_storage->addSchema( $schemas_base_uri . 'common.schema.json', $common_schema_content );

			// Register field schema (referenced by field-group).
			$field_schema_path    = $this->schema_path . 'field.schema.json';
			$field_schema_content = wp_json_file_decode( $field_schema_path );
			if ( $field_schema_content ) {
				$schema_storage->addSchema( $schemas_base_uri . 'field.schema.json', $field_schema_content );
			}

			// Register main schema with full path so relative refs resolve to sibling schemas.
			$main_schema_uri = $schemas_base_uri . $schema_name . '.schema.json';
			$schema_storage->addSchema( $main_schema_uri, $schema );

			$validator = new JsonSchema\Validator( new JsonSchema\Constraints\Factory( $schema_storage ) );
			$validator->validate( $data, $schema );

			foreach ( $validator->getErrors() as $error ) {
				$this->add_validation_error( $error['property'], $error['message'] );
			}

			return $validator->isValid();
		}

		/**
		 * Loads a schema file.
		 *
		 * @param string $schema_name The name of the schema file (without .schema.json extension).
		 * @return object|null The loaded schema object, or null on failure.
		 */
		public function load_schema( $schema_name ) {
			$schema_file = $this->schema_path . $schema_name . '.schema.json';

			if ( ! file_exists( $schema_file ) || ! is_readable( $schema_file ) ) {
				return null;
			}

			$schema_content = file_get_contents( $schema_file );
			if ( false === $schema_content ) {
				return null;
			}

			try {
				return json_decode( $schema_content, false, 512, JSON_THROW_ON_ERROR );
			} catch ( JsonException $e ) {
				return null;
			}
		}


		/**
		 * Validates that all required schemas are available.
		 *
		 * @since 6.6.0
		 * @return bool True if all required schemas load successfully, false otherwise.
		 */
		public function validate_required_schemas() {
			foreach ( self::REQUIRED_SCHEMAS as $schema_name ) {
				if ( ! $this->load_schema( $schema_name ) ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * Gets the validation errors from the last validation attempt.
		 *
		 * @return array Array of validation errors with 'field' and 'message' keys.
		 */
		public function get_validation_errors() {
			return $this->validation_errors;
		}

		/**
		 * Checks if there are any validation errors.
		 *
		 * @return bool True if there are validation errors, false otherwise.
		 */
		public function has_validation_errors() {
			return ! empty( $this->validation_errors );
		}

		/**
		 * Gets validation errors formatted as a string.
		 *
		 * @param string $separator The separator between error messages.
		 * @return string The formatted error message.
		 */
		public function get_validation_errors_string( $separator = '; ' ) {
			$messages = array();
			foreach ( $this->validation_errors as $error ) {
				$field_info = ! empty( $error['field'] ) ? '[' . $error['field'] . '] ' : '';
				$messages[] = $field_info . $error['message'];
			}
			return implode( $separator, $messages );
		}

		/**
		 * Adds a validation error.
		 *
		 * @param string $field The field that has the error.
		 * @param string $message The error message.
		 */
		private function add_validation_error( $field, $message ) {
			$this->validation_errors[] = array(
				'field'   => $field,
				'message' => $message,
			);
		}

		/**
		 * Clears all validation errors.
		 */
		private function clear_validation_errors() {
			$this->validation_errors = array();
		}



		/**
		 * Validates JSON string data.
		 *
		 * @param string $json_string The JSON string to validate.
		 * @param string $schema_name The name of the schema to validate against.
		 * @return bool True if valid, false otherwise.
		 */
		public function validate_json( $json_string, $schema_name ) {
			$this->clear_validation_errors();

			try {
				$data = json_decode( $json_string, false, 512, JSON_THROW_ON_ERROR );
			} catch ( JsonException $e ) {
				$this->add_validation_error( 'json', 'Invalid JSON: ' . $e->getMessage() );
				return false;
			}

			return $this->validate_data( $data, $schema_name );
		}

		/**
		 * Validates a JSON file.
		 *
		 * @param string $file_path Path to the JSON file.
		 * @param string $schema_name The name of the schema to validate against.
		 * @return bool True if valid, false otherwise.
		 */
		public function validate_file( $file_path, $schema_name ) {
			$this->clear_validation_errors();

			if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
				$this->add_validation_error( 'file', 'File does not exist: ' . $file_path );
				return false;
			}

			$json_content = file_get_contents( $file_path );

			if ( false === $json_content ) {
				$this->add_validation_error( 'file', 'Could not read file: ' . $file_path );
				return false;
			}

			return $this->validate_json( $json_content, $schema_name );
		}
	}

	// Initialize validator instance.
	acf_new_instance( 'SCF_JSON_Schema_Validator' );

endif; // class_exists check
