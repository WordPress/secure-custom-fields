<?php
/**
 * Tests for SCF_Schema_Builder class.
 *
 * Fixture setup for containment/cycle tests needs direct filesystem calls
 * (symlink, temp-dir cycles) that WP_Filesystem cannot express.
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
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
	 * Temporary directory used by containment tests.
	 *
	 * Populated in tests that need a scratch $base_path and cleaned in tearDown().
	 *
	 * @var string|null
	 */
	private $temp_dir = null;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->builder = acf_get_instance( 'SCF_Schema_Builder' );
	}

	/**
	 * Clean up any temp directories/symlinks created by a test.
	 */
	public function tearDown(): void {
		if ( null !== $this->temp_dir && is_dir( $this->temp_dir ) ) {
			$this->remove_tree( $this->temp_dir );
			$this->temp_dir = null;
		}
		parent::tearDown();
	}

	/**
	 * Recursively remove a directory, including symlinks.
	 *
	 * @param string $dir Directory to remove.
	 */
	private function remove_tree( string $dir ): void {
		if ( is_link( $dir ) ) {
			unlink( $dir );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			if ( file_exists( $dir ) ) {
				unlink( $dir );
			}
			return;
		}
		$entries = scandir( $dir );
		if ( false === $entries ) {
			return;
		}
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $entry;
			if ( is_link( $path ) ) {
				unlink( $path );
			} elseif ( is_dir( $path ) ) {
				$this->remove_tree( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}

	/**
	 * Create a unique temp directory under the system tmp and remember it.
	 *
	 * @return string Absolute path to the created temp dir.
	 */
	private function make_temp_dir(): string {
		$base = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR
			. 'scf-schema-builder-test-' . uniqid( '', true );
		mkdir( $base, 0700, true );
		$this->temp_dir = $base;
		return $base;
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
	 * Test compose_field_schema adds a fallback for extension field types.
	 *
	 * The fallback accepts field types without dedicated schemas while excluding
	 * known types so each known field continues to match exactly one variant.
	 */
	public function test_compose_field_schema_adds_fallback_for_unknown_types() {
		$result = $this->builder->compose_field_schema();

		$fallback = end( $result['oneOf'] );

		$this->assertTrue( $fallback['additionalProperties'] );
		$this->assertArrayHasKey( 'pattern', $fallback['properties']['type'] );
		$this->assertSame( 0, preg_match( '/' . $fallback['properties']['type']['pattern'] . '/', 'text' ) );
		$this->assertSame( 1, preg_match( '/' . $fallback['properties']['type']['pattern'] . '/', 'phpunit_unknown_type' ) );
		$this->assertTrue(
			rest_validate_value_from_schema( 'phpunit_unknown_type', $fallback['properties']['type'], 'type' )
		);
		$this->assertTrue(
			is_wp_error( rest_validate_value_from_schema( 'text', $fallback['properties']['type'], 'type' ) )
		);
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

	/**
	 * Parent-traversal refs like "../evil.schema.json#/..." must be rejected.
	 *
	 * Containment is enforced by realpath() prefix check; the ref must stay
	 * unresolved and no fatal error should be raised.
	 */
	public function test_resolve_refs_rejects_parent_traversal() {
		$base_path = $this->make_temp_dir() . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR;
		mkdir( $base_path, 0700, true );

		// Place a real file outside $base_path that the traversal would reach, to prove
		// the rejection comes from containment and not from the file being missing.
		$outside_path = dirname( rtrim( $base_path, DIRECTORY_SEPARATOR ) ) . DIRECTORY_SEPARATOR . 'evil.schema.json';
		file_put_contents(
			$outside_path,
			wp_json_encode(
				array(
					'definitions' => array(
						'x' => array( 'type' => 'string' ),
					),
				)
			)
		);

		$schema = array(
			'$ref' => '../evil.schema.json#/definitions/x',
		);

		$result = $this->builder->resolve_refs( $schema, $schema, $base_path );

		// Ref must come back unchanged (unresolved).
		$this->assertSame( $schema, $result );
	}

	/**
	 * Absolute-path refs like "/etc/passwd#/..." must be rejected.
	 *
	 * Concatenating $base_path with an absolute ref produces a non-existent
	 * nested path, and even if it did exist realpath() would fall outside
	 * $base_path and be rejected.
	 */
	public function test_resolve_refs_rejects_absolute_path() {
		$base_path = $this->make_temp_dir() . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR;
		mkdir( $base_path, 0700, true );

		$schema = array(
			'$ref' => '/etc/passwd#/definitions/x',
		);

		$result = $this->builder->resolve_refs( $schema, $schema, $base_path );

		$this->assertSame( $schema, $result );
	}

	/**
	 * Symlinks that resolve outside $base_path must be rejected.
	 *
	 * The real path containment check should reject a ref whose target, after
	 * realpath(), lives outside the configured base directory even though the
	 * lexical path (base + filename) looked safe.
	 */
	public function test_resolve_refs_rejects_symlink_escape() {
		if ( DIRECTORY_SEPARATOR === '\\' ) {
			$this->markTestSkipped( 'Symlink semantics differ on Windows.' );
		}

		$root      = $this->make_temp_dir();
		$base_path = $root . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR;
		mkdir( $base_path, 0700, true );

		// Valid in-base schema to prove the builder works in this temp layout.
		file_put_contents(
			$base_path . 'inner.schema.json',
			wp_json_encode(
				array(
					'definitions' => array(
						'x' => array( 'type' => 'string' ),
					),
				)
			)
		);

		// Real schema file OUTSIDE $base_path (sibling to schemas/ under $root).
		$outside_file = $root . DIRECTORY_SEPARATOR . 'outside.schema.json';
		file_put_contents(
			$outside_file,
			wp_json_encode(
				array(
					'definitions' => array(
						'x' => array( 'type' => 'string' ),
					),
				)
			)
		);

		// Symlink inside $base_path pointing at the outside real file.
		$link = $base_path . 'escape.schema.json';
		if ( ! symlink( $outside_file, $link ) ) {
			$this->markTestSkipped( 'Unable to create symlink in this environment.' );
		}

		$schema = array(
			'$ref' => 'escape.schema.json#/definitions/x',
		);

		$result = $this->builder->resolve_refs( $schema, $schema, $base_path );

		// Symlink resolution escapes $base_path -> containment check must reject.
		$this->assertSame( $schema, $result );
	}

	/**
	 * A cycle between two external-file refs must halt via the visited-set guard.
	 *
	 * The two files reference each other; resolve_refs() must return in bounded
	 * time and leave the cycle entry unresolved, without tripping the
	 * MAX_REF_DEPTH structural cap or exhausting the PHP call stack.
	 */
	public function test_resolve_refs_halts_on_circular_ref() {
		$base_path = $this->make_temp_dir() . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR;
		mkdir( $base_path, 0700, true );

		file_put_contents(
			$base_path . 'a.schema.json',
			wp_json_encode(
				array(
					'definitions' => array(
						'x' => array( '$ref' => 'b.schema.json#/definitions/y' ),
					),
				)
			)
		);
		file_put_contents(
			$base_path . 'b.schema.json',
			wp_json_encode(
				array(
					'definitions' => array(
						'y' => array( '$ref' => 'a.schema.json#/definitions/x' ),
					),
				)
			)
		);

		$schema = array(
			'$ref' => 'a.schema.json#/definitions/x',
		);

		$start   = microtime( true );
		$result  = $this->builder->resolve_refs( $schema, $schema, $base_path );
		$elapsed = microtime( true ) - $start;

		// Bounded time sanity fence — cycle must be cut short, not wait for depth cap.
		$this->assertLessThan( 1.0, $elapsed, 'Cycle should halt quickly via visited-set guard.' );

		// The returned schema must still carry an unresolved $ref somewhere on the
		// chain (either the top-level entry or one of its descendants kept intact
		// when the cycle was detected).
		$this->assertTrue(
			$this->contains_ref( $result ),
			'Circular ref chain should leave a $ref in place rather than fully inlining.'
		);
	}

	/**
	 * Recursively check whether a schema array contains any $ref key.
	 *
	 * @param mixed $node Schema node to inspect.
	 * @return bool True when a $ref key is present anywhere in the structure.
	 */
	private function contains_ref( $node ): bool {
		if ( ! is_array( $node ) ) {
			return false;
		}
		if ( isset( $node['$ref'] ) ) {
			return true;
		}
		foreach ( $node as $value ) {
			if ( $this->contains_ref( $value ) ) {
				return true;
			}
		}
		return false;
	}
}
