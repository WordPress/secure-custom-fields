<?php
/**
 * Schema Builder for SCF
 *
 * Handles JSON Schema operations like $ref resolution and schema composition.
 *
 * @package SCF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_Schema_Builder' ) ) :

	/**
	 * SCF Schema Builder
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
	 * - schemas/field-fragments/field-base.schema.json: Base properties shared by all types
	 * - schemas/field-fragments/{category}/{type}.schema.json: Type-specific properties
	 *
	 * @since 6.8.0
	 */
	class SCF_Schema_Builder {

		/**
		 * Cached composed field schema.
		 *
		 * @var array|null
		 */
		private ?array $composed_field_schema = null;

		/**
		 * Cached base field schema.
		 *
		 * @var array|null
		 */
		private ?array $base_schema = null;

		/**
		 * Recursively resolves $ref references in a JSON schema.
		 *
		 * WordPress internal validation doesn't understand JSON Schema $ref,
		 * so we inline referenced definitions before passing schemas to WP.
		 *
		 * @since 6.8.0
		 *
		 * @param array      $schema      The schema to resolve.
		 * @param array|null $root_schema The root schema containing definitions. If null, uses $schema.
		 * @return array The resolved schema.
		 */
		public function resolve_refs( array $schema, ?array $root_schema = null ): array {
			$root_schema = $root_schema ?? $schema;
			$definitions = $root_schema['definitions'] ?? array();

			if ( isset( $schema['$ref'] ) && preg_match( '~^#/definitions/(.+)$~', $schema['$ref'], $matches ) ) {
				$resolved = $definitions;
				foreach ( explode( '/', $matches[1] ) as $part ) {
					$resolved = $resolved[ $part ] ?? null;
				}

				if ( is_array( $resolved ) ) {
					unset( $schema['$ref'] );
					return array_merge( $this->resolve_refs( $resolved, $root_schema ), $schema );
				}
			}

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
			if ( null !== $this->composed_field_schema ) {
				return $this->composed_field_schema;
			}

			// Load and resolve base field schema.
			$base_schema = $this->load_base_field_schema();
			$base_def    = $base_schema['definitions']['field'] ?? array();
			$base_props  = $base_def['properties'] ?? array();

			// Build oneOf variants for each field type with a schema.
			$variants     = array();
			$type_schemas = $this->load_type_schemas();

			foreach ( $type_schemas as $type_schema ) {
				$type_props = $type_schema['properties'] ?? array();

				$variants[] = array(
					'type'                 => 'object',
					'required'             => array( 'key', 'label', 'name', 'type', 'parent' ),
					'properties'           => array_merge( $base_props, $type_props ),
					'additionalProperties' => $type_schema['additionalProperties'] ?? false,
				);
			}

			// Temporary fallback for field types without specific schemas.
			// This will be removed once all 35 field types have dedicated schema files.
			// Exclude types that have specific schemas by modifying the type enum.
			$known_types    = array_keys( $type_schemas );
			$all_types      = $base_props['type']['enum'] ?? array();
			$fallback_types = array_values( array_diff( $all_types, $known_types ) );

			$fallback_props         = $base_props;
			$fallback_props['type'] = array(
				'type' => 'string',
				'enum' => $fallback_types,
			);

			$variants[] = array(
				'type'                 => 'object',
				'required'             => array( 'key', 'label', 'name', 'type', 'parent' ),
				'properties'           => $fallback_props,
				'additionalProperties' => true,
			);

			$this->composed_field_schema = array(
				'oneOf' => $variants,
			);

			return $this->composed_field_schema;
		}

		/**
		 * Loads the base field schema without resolving refs.
		 *
		 * Refs are kept intact so the generated field.schema.json stays compact.
		 * Consumers (like Field Abilities) resolve refs at runtime when needed.
		 *
		 * @since 6.8.0
		 *
		 * @return array The base field schema with refs intact.
		 */
		private function load_base_field_schema(): array {
			if ( null === $this->base_schema ) {
				$schema_path       = ACF_PATH . 'schemas/field-fragments/field-base.schema.json';
				$this->base_schema = json_decode( file_get_contents( $schema_path ), true );
			}

			return $this->base_schema;
		}

		/**
		 * Loads all type-specific field schemas from category directories.
		 *
		 * Scans schemas/field-fragments/{category}/ directories for type schema files.
		 *
		 * @since 6.8.0
		 *
		 * @return array Associative array of type => schema data.
		 */
		private function load_type_schemas(): array {
			$schemas     = array();
			$fields_path = ACF_PATH . 'schemas/field-fragments/';

			if ( ! is_dir( $fields_path ) || ! is_readable( $fields_path ) ) {
				return $schemas;
			}

			// Get category directories using glob (safer than scandir).
			$category_dirs = glob( $fields_path . '*', GLOB_ONLYDIR );
			if ( ! is_array( $category_dirs ) ) {
				return $schemas;
			}

			foreach ( $category_dirs as $category_path ) {
				// Scan schema files in this category.
				$files = glob( $category_path . '/*.schema.json' );
				if ( ! is_array( $files ) ) {
					continue;
				}

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

	// Initialize only in WordPress context, not in the CLI.
	if ( function_exists( 'acf_new_instance' ) ) {
		acf_new_instance( 'SCF_Schema_Builder' );
	}

endif;
