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
	 * Provides utilities for JSON Schema operations including:
	 * - Resolving $ref references within schemas
	 * - Composing schemas with oneOf variants
	 *
	 * @since 6.8.0
	 */
	class SCF_Schema_Composer {

		/**
		 * Recursively resolves $ref references in a JSON schema.
		 *
		 * WordPress Abilities API doesn't understand JSON Schema $ref,
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
	}

	// Initialize composer instance.
	acf_new_instance( 'SCF_Schema_Composer' );

endif;
