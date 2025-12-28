<?php
/**
 * Tests for SCF_Schema_Builder class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for SCF_Schema_Builder.
 *
 * @group schema
 */
class SCFSchemaBuilderTest extends BaseTestCase {

	/**
	 * The builder instance.
	 *
	 * @var SCF_Schema_Builder
	 */
	private $builder;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->builder = acf_get_instance( 'SCF_Schema_Builder' );
	}

	/**
	 * Test resolve_refs resolves simple $ref.
	 */
	public function test_resolve_refs_resolves_simple_ref() {
		$schema = array(
			'$ref' => '#/definitions/testDef',
		);

		$root_schema = array(
			'definitions' => array(
				'testDef' => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array( 'type' => 'string' ),
					),
				),
			),
		);

		$result = $this->builder->resolve_refs( $schema, $root_schema );

		$this->assertEquals( 'object', $result['type'] );
		$this->assertArrayHasKey( 'properties', $result );
		$this->assertArrayHasKey( 'name', $result['properties'] );
	}

	/**
	 * Test resolve_refs resolves nested $ref (ref pointing to ref).
	 */
	public function test_resolve_refs_resolves_nested_refs() {
		$schema = array(
			'type'  => 'array',
			'items' => array(
				'$ref' => '#/definitions/outerDef',
			),
		);

		$root_schema = array(
			'definitions' => array(
				'outerDef' => array(
					'type'  => 'array',
					'items' => array(
						'$ref' => '#/definitions/innerDef',
					),
				),
				'innerDef' => array(
					'type'       => 'object',
					'required'   => array( 'param', 'operator', 'value' ),
					'properties' => array(
						'param'    => array( 'type' => 'string' ),
						'operator' => array( 'type' => 'string' ),
						'value'    => array( 'type' => 'string' ),
					),
				),
			),
		);

		$result = $this->builder->resolve_refs( $schema, $root_schema );

		// Outer array should be preserved.
		$this->assertEquals( 'array', $result['type'] );

		// Items should be resolved (outerDef -> array with items).
		$this->assertEquals( 'array', $result['items']['type'] );

		// Nested items should be fully resolved (innerDef -> object).
		$this->assertEquals( 'object', $result['items']['items']['type'] );
		$this->assertContains( 'param', $result['items']['items']['required'] );
		$this->assertArrayHasKey( 'properties', $result['items']['items'] );
	}

	/**
	 * Test resolve_refs passes through non-ref schemas unchanged.
	 */
	public function test_resolve_refs_passthrough_non_ref() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string' ),
				'age'  => array( 'type' => 'integer' ),
			),
		);

		$root_schema = array(
			'definitions' => array(),
		);

		$result = $this->builder->resolve_refs( $schema, $root_schema );

		$this->assertEquals( $schema, $result );
	}

	/**
	 * Test resolve_refs returns original schema for unresolvable $ref.
	 */
	public function test_resolve_refs_returns_original_for_missing_definition() {
		$schema = array(
			'$ref' => '#/definitions/nonExistentDef',
		);

		$root_schema = array(
			'definitions' => array(
				'existingDef' => array( 'type' => 'string' ),
			),
		);

		$result = $this->builder->resolve_refs( $schema, $root_schema );

		// Should return original schema when definition not found.
		$this->assertEquals( $schema, $result );
	}

	/**
	 * Test compose_field_schema returns oneOf structure.
	 */
	public function test_compose_field_schema_returns_oneof_structure() {
		$result = $this->builder->compose_field_schema();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'oneOf', $result );
		$this->assertIsArray( $result['oneOf'] );
		$this->assertNotEmpty( $result['oneOf'] );
	}

	/**
	 * Test compose_field_schema only adds fallback when types are missing schemas.
	 *
	 * With all 39 field types having dedicated schemas, no fallback variant
	 * should be present. The fallback (with additionalProperties: true) is
	 * only added when there are field types without schema files.
	 */
	public function test_compose_field_schema_no_fallback_when_all_types_have_schemas() {
		$result = $this->builder->compose_field_schema();

		// All dedicated schemas have additionalProperties: false.
		// If a fallback exists, it would have additionalProperties: true.
		$last_variant = end( $result['oneOf'] );

		$this->assertFalse( $last_variant['additionalProperties'] );
	}

	/**
	 * Test compose_field_schema variants have required fields.
	 */
	public function test_compose_field_schema_variants_have_required_fields() {
		$result = $this->builder->compose_field_schema();

		foreach ( $result['oneOf'] as $variant ) {
			$this->assertArrayHasKey( 'required', $variant );
			$this->assertContains( 'key', $variant['required'] );
			$this->assertContains( 'label', $variant['required'] );
			$this->assertContains( 'name', $variant['required'] );
			$this->assertContains( 'type', $variant['required'] );
			$this->assertContains( 'parent', $variant['required'] );
		}
	}

	/**
	 * Test compose_field_schema merges base and type properties.
	 */
	public function test_compose_field_schema_merges_properties() {
		$result = $this->builder->compose_field_schema();

		// Find a type-specific variant (not the fallback which has additionalProperties: true).
		$type_variant = null;
		foreach ( $result['oneOf'] as $variant ) {
			if ( false === $variant['additionalProperties'] ) {
				$type_variant = $variant;
				break;
			}
		}

		if ( $type_variant ) {
			// Should have base properties like 'key', 'label', etc.
			$this->assertArrayHasKey( 'key', $type_variant['properties'] );
			$this->assertArrayHasKey( 'label', $type_variant['properties'] );
			$this->assertArrayHasKey( 'type', $type_variant['properties'] );
		}
	}

	/**
	 * Test resolve_refs handles nested definition paths.
	 *
	 * Refs like "#/definitions/shared/default_value" should resolve nested paths.
	 */
	public function test_resolve_refs_resolves_nested_definition_paths() {
		$schema = array(
			'$ref' => '#/definitions/shared/nested',
		);

		$root_schema = array(
			'definitions' => array(
				'shared' => array(
					'nested' => array(
						'type'        => 'string',
						'description' => 'A nested definition',
					),
				),
			),
		);

		$result = $this->builder->resolve_refs( $schema, $root_schema );

		$this->assertEquals( 'string', $result['type'] );
		$this->assertEquals( 'A nested definition', $result['description'] );
	}

	/**
	 * Test resolve_refs resolves relative file refs.
	 *
	 * Refs like "common.schema.json#/definitions/title" should load and resolve
	 * definitions from external schema files.
	 */
	public function test_resolve_refs_resolves_relative_file_refs() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'title' => array(
					'$ref' => 'common.schema.json#/definitions/title',
				),
			),
		);

		$result = $this->builder->resolve_refs( $schema, $schema );

		// The title property should be resolved from common.schema.json.
		$this->assertArrayHasKey( 'title', $result['properties'] );
		$this->assertEquals( 'string', $result['properties']['title']['type'] );
		$this->assertArrayNotHasKey( '$ref', $result['properties']['title'] );
	}

	/**
	 * Test resolve_refs resolves wordpressReservedTerms from common.schema.json.
	 *
	 * This validates the specific use case where post-type.schema.json uses:
	 * "not": { "$ref": "common.schema.json#/definitions/wordpressReservedTerms" }
	 */
	public function test_resolve_refs_resolves_wordpress_reserved_terms() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_type' => array(
					'type' => 'string',
					'not'  => array(
						'$ref' => 'common.schema.json#/definitions/wordpressReservedTerms',
					),
				),
			),
		);

		$result = $this->builder->resolve_refs( $schema, $schema );

		// The "not" constraint should be resolved with the enum of reserved terms.
		$this->assertArrayHasKey( 'not', $result['properties']['post_type'] );
		$this->assertArrayHasKey( 'enum', $result['properties']['post_type']['not'] );
		$this->assertContains( 'post', $result['properties']['post_type']['not']['enum'] );
		$this->assertContains( 'page', $result['properties']['post_type']['not']['enum'] );
		$this->assertContains( 'attachment', $result['properties']['post_type']['not']['enum'] );
	}

	/**
	 * Test resolve_refs returns original schema for non-existent external file.
	 */
	public function test_resolve_refs_returns_original_for_missing_external_file() {
		$schema = array(
			'$ref' => 'nonexistent.schema.json#/definitions/foo',
		);

		$result = $this->builder->resolve_refs( $schema, $schema );

		// Should return original schema when external file not found.
		$this->assertEquals( $schema, $result );
	}

	/**
	 * Test resolve_refs with custom base_path parameter.
	 */
	public function test_resolve_refs_with_custom_base_path() {
		$schema = array(
			'$ref' => 'common.schema.json#/definitions/active',
		);

		// Use explicit base path.
		$base_path = ACF_PATH . 'schemas/';
		$result    = $this->builder->resolve_refs( $schema, $schema, $base_path );

		// Should resolve the active definition.
		$this->assertEquals( 'boolean', $result['type'] );
		$this->assertArrayNotHasKey( '$ref', $result );
	}
}
