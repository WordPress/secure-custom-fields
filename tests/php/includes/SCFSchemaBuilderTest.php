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
	 * Test resolve_refs triggers _doing_it_wrong for unresolvable $ref.
	 *
	 * @expectedIncorrectUsage SCF_Schema_Builder::resolve_refs
	 */
	public function test_resolve_refs_triggers_doing_it_wrong_for_missing_definition() {
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
	 * Test compose_field_schema includes fallback variant.
	 */
	public function test_compose_field_schema_includes_fallback_variant() {
		$result = $this->builder->compose_field_schema();

		// Last variant should be the fallback.
		$last_variant = end( $result['oneOf'] );

		$this->assertEquals( 'Other field types', $last_variant['title'] );
		$this->assertTrue( $last_variant['additionalProperties'] );
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

		// Find a type-specific variant (not the fallback).
		$type_variant = null;
		foreach ( $result['oneOf'] as $variant ) {
			if ( 'Other field types' !== $variant['title'] ) {
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
}
