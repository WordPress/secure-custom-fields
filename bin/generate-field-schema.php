#!/usr/bin/env php
<?php
// phpcs:ignoreFile -- CLI script runs outside WordPress, can't use WP functions.
/**
 * Generates the composed field.schema.json from fragments.
 *
 * This script reuses SCF_Schema_Builder::compose_field_schema() to ensure
 * the generated schema matches runtime behavior exactly.
 *
 * Usage:
 *   php bin/generate-field-schema.php          # Generate the schema
 *   php bin/generate-field-schema.php --check  # Verify schema is in sync
 *
 * @package SCF
 */

$check_mode = in_array( '--check', $argv, true );

// Bootstrap minimal SCF environment.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'ACF_PATH', dirname( __DIR__ ) . '/' );

// Load the Schema Builder (auto-initialization is skipped outside WordPress).
require_once ACF_PATH . 'includes/class-scf-schema-builder.php';

// Load base schema for structure and shared definitions.
$base_path = ACF_PATH . 'schemas/field-fragments/field-base.schema.json';
$base      = json_decode( file_get_contents( $base_path ), true );

if ( ! $base ) {
	fwrite( STDERR, "Error: Could not load base schema from {$base_path}\n" );
	exit( 1 );
}

// Get composed field definition from builder.
$builder            = new SCF_Schema_Builder();
$composed_field_def = $builder->compose_field_schema();

// Remove 'parent' from each variant's required array.
// - Standalone fields (validated via top-level oneOf) require parent because
// the UI always creates fields with a parent - you can't create orphan fields.
// - Fields inside field groups (validated via #/definitions/field) don't need
// parent in the JSON because it's implicit from the field group context.
foreach ( $composed_field_def['oneOf'] as &$variant ) {
	if ( isset( $variant['required'] ) ) {
		$variant['required'] = array_values(
			array_diff( $variant['required'], array( 'parent' ) )
		);
	}
}
unset( $variant );

// Use base schema as wrapper, replacing definitions/field with composed version.
$schema                         = $base;
$schema['$id']                  = 'https://raw.githubusercontent.com/WordPress/secure-custom-fields/trunk/schemas/field.schema.json';
$schema['title']                = 'SCF Field';
$schema['description']          = 'Schema for Secure Custom Fields field definitions. Auto-generated from field-fragments/.';
$schema['definitions']['field'] = $composed_field_def;

// Remove the $comment from generated file (it's only relevant for the source).
unset( $schema['$comment'] );

$output      = json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
$output_path = ACF_PATH . 'schemas/field.schema.json';

if ( $check_mode ) {
	$current = file_exists( $output_path ) ? file_get_contents( $output_path ) : '';
	if ( $current !== $output ) {
		fwrite( STDERR, "Error: field.schema.json is out of sync with fragments!\n" );
		fwrite( STDERR, "Run: php bin/generate-field-schema.php\n" );
		exit( 1 );
	}
	echo "field.schema.json is in sync.\n";
	exit( 0 );
}

file_put_contents( $output_path, $output );
echo "Generated: schemas/field.schema.json\n";
