<?php
/**
 * Schema Composer for SCF
 *
 * Handles JSON Schema operations like $ref resolution and schema composition.
 *
 * @package SCF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_Schema_Composer' ) ) :

	/**
	 * SCF Schema Composer
	 *
	 * Builds composed field schemas and resolves $ref for WordPress.
	 *
	 * Why $ref resolution:
	 * - WordPress internal validation doesn't understand JSON Schema $ref
	 * - We inline referenced definitions before passing schemas to WP
	 *
	 * Why oneOf composition:
	 * - Field validation requires type-specific rules (text has maxlength, number has min/max)
	 * - Base properties (key, label, name, type, parent) are shared across all types
	 * - oneOf validates "valid text field OR valid number field OR ..."
	 * - Each variant merges base + type-specific properties with additionalProperties: false
	 * - Fallback variant allows unknown types until all 35 field types have schemas
	 *
	 * Schema structure:
	 * - schemas/field.schema.json: Base properties shared by all types
	 * - schemas/fields/{category}/{type}.schema.json: Type-specific properties
	 *
	 * @since 6.8.0
	 */
	class SCF_Schema_Composer {

		/**
		 * Recursively resolves $ref references in a JSON schema.
		 *
		 * WordPress internal validation doesn't understand JSON Schema $ref,
		 * so we need to inline referenced definitions.
		 *
		 * @since 6.8.0
		 *
		 * @param array      $schema      The schema to resolve.
		 * @param array|null $root_schema The root schema containing definitions. If null, uses $schema.
		 * @return array The resolved schema.
		 */
		public function resolve_refs( array $schema, ?array $root_schema = null ): array {
			// Use the schema itself as root if not provided (first call).
			if ( null === $root_schema ) {
				$root_schema = $schema;
			}

			$definitions = $root_schema['definitions'] ?? array();

			// If this is a $ref, resolve it.
			if ( isset( $schema['$ref'] ) ) {
				$ref = $schema['$ref'];
				// Extract definition name from "#/definitions/name".
				if ( preg_match( '~^#/definitions/(.+)$~', $ref, $matches ) ) {
					$def_name = $matches[1];
					if ( isset( $definitions[ $def_name ] ) ) {
						// Recursively resolve refs in the referenced definition.
						$resolved = $this->resolve_refs( $definitions[ $def_name ], $root_schema );
						// Merge any additional properties from the original schema.
						unset( $schema['$ref'] );
						return array_merge( $resolved, $schema );
					}
				}

				// Log warning for unresolvable $ref.
				_doing_it_wrong(
					__METHOD__,
					esc_html(
						sprintf(
							/* translators: %s: The unresolvable JSON Schema $ref value */
							__( 'Could not resolve schema $ref: %s', 'secure-custom-fields' ),
							$ref
						)
					),
					'6.8.0'
				);
				return $schema;
			}

			// Recursively process all array elements.
			foreach ( $schema as $key => $value ) {
				if ( is_array( $value ) ) {
					$schema[ $key ] = $this->resolve_refs( $value, $root_schema );
				}
			}

			return $schema;
		}

		/**
		 * Composes a field schema with oneOf containing all type variants.
		 *
		 * Each variant merges base field properties with type-specific properties,
		 * enabling complete validation without schema duplication in source files.
		 *
		 * @since 6.8.0
		 *
		 * @return array The composed schema with oneOf variants.
		 */
		public function compose_field_schema(): array {
			static $composed = null;

			if ( null !== $composed ) {
				return $composed;
			}

			// Load and resolve base field schema.
			$base_schema = $this->load_base_field_schema();
			$base_def    = $base_schema['definitions']['field'] ?? array();
			$base_props  = $base_def['properties'] ?? array();

			// Build oneOf variants for each field type with a schema.
			$variants     = array();
			$type_schemas = $this->load_type_schemas();

			foreach ( $type_schemas as $type => $type_schema ) {
				$type_props = $type_schema['properties'] ?? array();

				$variants[] = array(
					'title'                => ucfirst( str_replace( '_', ' ', $type ) ) . ' field',
					'type'                 => 'object',
					'required'             => array( 'key', 'label', 'name', 'type', 'parent' ),
					'properties'           => array_merge( $base_props, $type_props ),
					'additionalProperties' => $type_schema['additionalProperties'] ?? false,
				);
			}

			// Add fallback variant for field types without specific schemas.
			$variants[] = array(
				'title'                => 'Other field types',
				'type'                 => 'object',
				'required'             => array( 'key', 'label', 'name', 'type', 'parent' ),
				'properties'           => $base_props,
				'additionalProperties' => true,
			);

			$composed = array(
				'oneOf' => $variants,
			);

			return $composed;
		}

		/**
		 * Loads and resolves the base field schema.
		 *
		 * @since 6.8.0
		 *
		 * @return array The base field schema with refs resolved.
		 */
		private function load_base_field_schema(): array {
			static $base_schema = null;

			if ( null === $base_schema ) {
				$schema_path = ACF_PATH . 'schemas/field.schema.json';
				$base_schema = json_decode( file_get_contents( $schema_path ), true );
				$base_schema = $this->resolve_refs( $base_schema );
			}

			return $base_schema;
		}

		/**
		 * Loads all type-specific field schemas from category directories.
		 *
		 * Scans schemas/fields/{category}/ directories for type schema files.
		 *
		 * @since 6.8.0
		 *
		 * @return array Associative array of type => schema data.
		 */
		private function load_type_schemas(): array {
			$schemas     = array();
			$fields_path = ACF_PATH . 'schemas/fields/';

			if ( ! is_dir( $fields_path ) ) {
				return $schemas;
			}

			// Scan category directories.
			$categories = scandir( $fields_path );
			foreach ( $categories as $category ) {
				if ( '.' === $category || '..' === $category ) {
					continue;
				}

				$category_path = $fields_path . $category . '/';
				if ( ! is_dir( $category_path ) ) {
					continue;
				}

				// Scan schema files in this category.
				$files = glob( $category_path . '*.schema.json' );
				foreach ( $files as $file ) {
					$content = file_get_contents( $file );
					if ( false === $content ) {
						continue;
					}

					$schema = json_decode( $content, true );
					if ( ! $schema ) {
						continue;
					}

					// Extract field type from schema or filename.
					$type = $schema['properties']['type']['enum'][0]
						?? basename( $file, '.schema.json' );

					$schemas[ $type ] = $schema;
				}
			}

			return $schemas;
		}
	}

	// Initialize composer instance.
	acf_new_instance( 'SCF_Schema_Composer' );

endif;
