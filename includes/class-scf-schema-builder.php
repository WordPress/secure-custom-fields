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
		 * Max recursion depth for $ref resolution. Guards against circular refs.
		 */
		private const MAX_REF_DEPTH = 32;

		/**
		 * Recursively resolves $ref references in a JSON schema.
		 *
		 * WordPress internal validation doesn't understand JSON Schema $ref,
		 * so we inline referenced definitions before passing schemas to WP.
		 *
		 * Supports two ref formats:
		 * - Internal refs: #/definitions/foo (resolved from root_schema)
		 * - Relative file refs: file.schema.json#/definitions/foo (loaded from base_path)
		 *
		 * Cycle detection uses two guards:
		 * - $visited tracks external-file ref strings on the current resolution chain
		 *   so cycles (A -> B -> A) abort cleanly without exhausting the stack.
		 * - $depth is a belt-and-suspenders upper bound, only incremented when
		 *   recursing into a resolved external-file $ref (not on plain structural
		 *   descent into properties, items, etc.), so deeply nested schemas don't
		 *   trip the cycle guard prematurely.
		 *
		 * @since 6.8.0
		 *
		 * @param array       $schema      The schema to resolve.
		 * @param array|null  $root_schema The root schema containing definitions. If null, uses $schema.
		 * @param string|null $base_path   Base path for loading external schema files. Defaults to schemas/.
		 * @param int         $depth       Internal external-ref recursion depth counter.
		 * @param array       $visited     External-file ref strings seen on the current resolution chain.
		 * @return array The resolved schema.
		 */
		public function resolve_refs( array $schema, ?array $root_schema = null, ?string $base_path = null, int $depth = 0, array $visited = array() ): array {
			if ( $depth >= self::MAX_REF_DEPTH ) {
				return $schema;
			}

			$root_schema = $root_schema ?? $schema;
			$definitions = $root_schema['definitions'] ?? array();
			$base_path   = $base_path ?? acf_get_path( 'schemas/' );
			$base_path   = rtrim( $base_path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

			if ( isset( $schema['$ref'] ) ) {
				$ref           = $schema['$ref'];
				$resolved      = null;
				$ref_signature = null;

				// Internal ref: #/definitions/path/to/def
				if ( preg_match( '~^#/definitions/(.+)$~', $ref, $matches ) ) {
					$resolved = $definitions;
					foreach ( explode( '/', $matches[1] ) as $part ) {
						$resolved = $resolved[ $part ] ?? null;
					}
				} elseif ( preg_match( '~^([^#]+)#/definitions/(.+)$~', $ref, $matches ) && false === strpos( $matches[1], "\0" ) ) {
					// Relative file ref: file.schema.json#/definitions/path/to/def.
					// Contain the resolved path to $base_path to block traversal via ../ or absolute paths.
					$candidate = $base_path . $matches[1];
					$real_base = realpath( $base_path );
					$real_file = realpath( $candidate );
					$def_path  = $matches[2];

					$within_base = false !== $real_base
						&& false !== $real_file
						&& 0 === strpos( $real_file, rtrim( $real_base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR );

					if ( $within_base && is_file( $real_file ) ) {
						// Signature uses the resolved real file path so equivalent refs collapse to the same key.
						$ref_signature = $real_file . '#/definitions/' . $def_path;

						// Abort unresolved if this external ref is already on the current resolution chain.
						if ( in_array( $ref_signature, $visited, true ) ) {
							return $schema;
						}

						$external_content = file_get_contents( $real_file );
						$external_schema  = json_decode( $external_content, true );

						if ( is_array( $external_schema ) ) {
							$resolved = $external_schema['definitions'] ?? array();
							foreach ( explode( '/', $def_path ) as $part ) {
								$resolved = $resolved[ $part ] ?? null;
							}
						}
					}
				}

				if ( is_array( $resolved ) ) {
					unset( $schema['$ref'] );

					// Only bump depth and extend visited on external-file refs — internal refs
					// resolve against already-loaded definitions and don't open new files.
					if ( null !== $ref_signature ) {
						$next_visited   = $visited;
						$next_visited[] = $ref_signature;
						return array_merge( $this->resolve_refs( $resolved, $root_schema, $base_path, $depth + 1, $next_visited ), $schema );
					}

					return array_merge( $this->resolve_refs( $resolved, $root_schema, $base_path, $depth, $visited ), $schema );
				}
			}

			foreach ( $schema as $key => $value ) {
				if ( is_array( $value ) ) {
					// Structural descent: don't bump $depth, just thread the visited set through.
					$schema[ $key ] = $this->resolve_refs( $value, $root_schema, $base_path, $depth, $visited );
				}
			}

			return $schema;
		}

		/**
		 * Merges base and type-specific schema properties.
		 *
		 * Uses array_merge for nested arrays: fragment values overwrite base values,
		 * but base-only keys are preserved (e.g., base's "type": "string" is kept
		 * when fragment only provides "enum").
		 *
		 * @since 6.8.0
		 *
		 * @param array $base_props Base properties from field-base.schema.json.
		 * @param array $type_props Type-specific properties from fragment files.
		 * @return array Merged properties.
		 */
		private function merge_schema_properties( array $base_props, array $type_props ): array {
			$merged = $base_props;

			foreach ( $type_props as $prop_name => $prop_value ) {
				if ( isset( $merged[ $prop_name ] ) && is_array( $merged[ $prop_name ] ) && is_array( $prop_value ) ) {
					// Merge arrays: fragment values overwrite base, but base-only keys preserved.
					$merged[ $prop_name ] = array_merge( $merged[ $prop_name ], $prop_value );
				} else {
					$merged[ $prop_name ] = $prop_value;
				}
			}

			return $merged;
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
					'properties'           => $this->merge_schema_properties( $base_props, $type_props ),
					'additionalProperties' => $type_schema['additionalProperties'] ?? false,
				);
			}

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
			$schemas       = array();
			$fields_path   = ACF_PATH . 'schemas/field-fragments/';
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
