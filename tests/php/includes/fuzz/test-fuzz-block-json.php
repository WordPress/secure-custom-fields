<?php
/**
 * Property-based fuzz tests for block.json ingestion.
 *
 * Functions under test process arbitrarily-shaped block metadata:
 *   - acf_handle_json_block_registration( $settings, $metadata )
 *   - acf_block_json_process_fields( $fields, $block_slug, $block_name, ... )
 *
 * Invariants:
 *   - Neither function throws (or raises a warning) on randomized,
 *     block.json-shaped (and outright garbage) metadata that carries the
 *     minimum keys the API contract requires (a 'name', and a 'file' for
 *     registration — both are always present for a real block.json block).
 *   - acf_handle_json_block_registration() returns an array; when the
 *     metadata is an SCF block it carries the required structural keys
 *     (name/path/attributes/supports/uses_context/render_callback).
 *   - acf_block_json_process_fields() returns an array; every processed field
 *     has a non-empty 'key' and 'name'; entries that are not arrays or lack a
 *     'name' are dropped.
 *
 * Determinism: fixed seeds per test (see docblocks). Failures embed seed,
 * iteration and the failing metadata. Crank with SCF_FUZZ_ITERATIONS.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

require_once __DIR__ . '/class-scf-fuzz-generator.php';

/**
 * Fuzz tests for block.json ingestion.
 */
class Test_Fuzz_Block_JSON extends BaseTestCase {

	/**
	 * Base seed for this test class.
	 *
	 * @var integer
	 */
	const BASE_SEED = 612501;

	/**
	 * Block names registered during a test, removed in tearDown.
	 *
	 * @var array
	 */
	private $block_names = array();

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();

		acf_init();

		// _doing_it_wrong() notices (raised for nameless fields) would
		// otherwise be converted into test errors.
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		acf_get_store( 'block-types' )->reset();
	}

	/**
	 * Clean up registered blocks and groups.
	 */
	public function tearDown(): void {
		foreach ( $this->block_names as $name ) {
			acf_remove_block_type( $name );
			$slug = sanitize_key( str_replace( array( '/', '-' ), '_', $name ) );
			acf_remove_local_field_group( 'group_' . $slug );
		}
		$this->block_names = array();

		acf_get_store( 'block-types' )->reset();

		parent::tearDown();
	}

	/**
	 * Builds randomized but block.json-shaped metadata. Always carries a
	 * unique 'name' and a 'file' (both guaranteed for a real registered
	 * block.json), plus a fuzzed 'acf' payload so the SCF path is exercised.
	 *
	 * @param SCF_Fuzz_Generator $generator Generator.
	 * @param integer            $n         Uniquifier.
	 * @return array
	 */
	private function block_metadata( $generator, $n ) {
		$metadata = array(
			'name' => 'acf/fuzz-block-' . $n,
			'file' => '/tmp/scf-fuzz-block-' . $n . '/block.json',
			'acf'  => true,
		);

		if ( $generator->chance( 0.8 ) ) {
			$acf = array();

			if ( $generator->chance( 0.6 ) ) {
				$acf['mode'] = $generator->pick( array( 'edit', 'preview', 'auto', $generator->unicode_string( 1 ) ) );
			}
			if ( $generator->chance( 0.5 ) ) {
				$acf['renderTemplate'] = $generator->unicode_string( 1 );
			}
			if ( $generator->chance( 0.5 ) ) {
				$acf['blockVersion'] = $generator->pick( array( 1, 2, 3, '2', 0, -1, $generator->unicode_string( 1 ) ) );
			}
			if ( $generator->chance( 0.4 ) ) {
				$acf['postTypes'] = $generator->nested_array( 2, 3 );
			}
			if ( $generator->chance( 0.4 ) ) {
				$acf['usePostMeta'] = $generator->scalar();
			}
			if ( $generator->chance( 0.5 ) ) {
				$acf['fields'] = $this->random_fields( $generator, $n );
			}

			$metadata['acf'] = empty( $acf ) ? true : $acf;
		}

		// Sprinkle random top-level keys.
		if ( $generator->chance( 0.5 ) ) {
			$metadata['title'] = $generator->unicode_string( 2 );
		}
		if ( $generator->chance( 0.3 ) ) {
			$metadata['apiVersion'] = $generator->pick( array( 1, 2, 3, '3', $generator->unicode_string( 1 ) ) );
		}
		if ( $generator->chance( 0.3 ) ) {
			$metadata['supports'] = $generator->nested_array( 2, 3 );
		}
		if ( $generator->chance( 0.3 ) ) {
			$metadata['textdomain'] = $generator->unicode_string( 1 );
		}

		return $metadata;
	}

	/**
	 * Builds a randomized list of (possibly malformed) field definitions.
	 *
	 * @param SCF_Fuzz_Generator $generator Generator.
	 * @param integer            $n         Uniquifier.
	 * @return array
	 */
	private function random_fields( $generator, $n ) {
		$fields = array();
		$count  = $generator->int_between( 0, 5 );

		for ( $f = 0; $f < $count; $f++ ) {
			switch ( $generator->int_between( 0, 4 ) ) {
				case 0:
					// Well-formed field.
					$fields[] = array(
						'name'  => 'field_' . $n . '_' . $f,
						'label' => $generator->unicode_string( 1 ),
						'type'  => $generator->field_type(),
					);
					break;
				case 1:
					// Field with a name but garbage settings.
					$field         = $generator->nested_array( 2, 3 );
					$field['name'] = 'field_' . $n . '_' . $f;
					$fields[]      = $field;
					break;
				case 2:
					// Field missing a name (must be dropped).
					$fields[] = array(
						'label' => $generator->unicode_string( 1 ),
						'type'  => 'text',
					);
					break;
				case 3:
					// Nested sub_fields / layouts.
					$fields[] = array(
						'name'       => 'parent_' . $n . '_' . $f,
						'type'       => $generator->pick( array( 'repeater', 'flexible_content', 'group' ) ),
						'sub_fields' => array(
							array(
								'name' => 'sub_' . $f,
								'type' => 'text',
							),
							array( 'label' => 'no name' ),
						),
						'layouts'    => array(
							array(
								'name'       => 'layout_' . $f,
								'sub_fields' => array(
									array(
										'name' => 'lsub_' . $f,
										'type' => 'text',
									),
								),
							),
						),
					);
					break;
				default:
					// Non-array garbage entry (must be skipped).
					$fields[] = $generator->scalar();
			}
		}

		return $fields;
	}

	/**
	 * Property: acf_handle_json_block_registration() must never throw on randomized
	 * block metadata, must return an array, and for SCF blocks must carry the
	 * required structural keys.
	 *
	 * Seed: 612501. Default iterations: 120.
	 */
	public function test_handle_json_block_registration_never_fatals() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED );
		$iterations = SCF_Fuzz_Generator::iterations( 120 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$metadata            = $this->block_metadata( $generator, $i );
			$this->block_names[] = $metadata['name'];

			$settings = $generator->chance( 0.5 ) ? $generator->nested_array( 2, 3 ) : array();

			try {
				$result = acf_handle_json_block_registration( $settings, $metadata );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $metadata, 'acf_handle_json_block_registration threw: ' . $e->getMessage() ) );
			}

			$this->assertIsArray( $result, $generator->failure_message( $i, $metadata ) );

			// Metadata is always an SCF block here (acf key is truthy), so
			// the required structural keys must be present.
			foreach ( array( 'name', 'path', 'attributes', 'supports', 'uses_context', 'render_callback' ) as $required ) {
				$this->assertArrayHasKey(
					$required,
					$result,
					$generator->failure_message( $i, $metadata, "missing required key '{$required}'" )
				);
			}

			$this->assertSame( $metadata['name'], $result['name'], $generator->failure_message( $i, $metadata ) );
			$this->assertSame( 'acf_render_block_callback', $result['render_callback'], $generator->failure_message( $i, $metadata ) );
		}
	}

	/**
	 * Non-SCF metadata (no truthy 'acf' key) must be returned untouched.
	 *
	 * Seed: 612502. Default iterations: 80.
	 */
	public function test_non_scf_metadata_passes_through_untouched() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 1 );
		$iterations = SCF_Fuzz_Generator::iterations( 80 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$settings = $generator->nested_array( 2, 4 );
			$metadata = array(
				'name' => 'core/fuzz-' . $i,
				'file' => '/tmp/x/block.json',
			);

			// Either no 'acf' key, or a falsy one.
			if ( $generator->chance( 0.5 ) ) {
				$metadata['acf'] = $generator->pick( array( false, 0, '', null, array() ) );
			}

			try {
				$result = acf_handle_json_block_registration( $settings, $metadata );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $metadata, 'threw: ' . $e->getMessage() ) );
			}

			$this->assertSame( $settings, $result, $generator->failure_message( $i, $metadata, 'non-SCF metadata was modified' ) );
		}
	}

	/**
	 * Property: acf_block_json_process_fields() must never throw, must return an array,
	 * and every processed field must have a non-empty key and name. Entries
	 * that are not arrays or that lack a name must be dropped.
	 *
	 * Seed: 612503. Default iterations: 200.
	 */
	public function test_process_fields_invariants() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 2 );
		$iterations = SCF_Fuzz_Generator::iterations( 200 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$fields = $this->random_fields( $generator, $i );
			$slug   = 'fuzz_block_' . $i;

			try {
				$processed = acf_block_json_process_fields( $fields, $slug, 'acf/fuzz-' . $i );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $fields, 'acf_block_json_process_fields threw: ' . $e->getMessage() ) );
			}

			$this->assertIsArray( $processed, $generator->failure_message( $i, $fields ) );

			foreach ( $processed as $field ) {
				$this->assertIsArray( $field, $generator->failure_message( $i, $fields, 'a processed entry is not an array' ) );
				$this->assertArrayHasKey( 'name', $field, $generator->failure_message( $i, $fields, 'processed field has no name' ) );
				$this->assertNotEmpty( $field['name'], $generator->failure_message( $i, $fields, 'processed field has empty name' ) );
				$this->assertArrayHasKey( 'key', $field, $generator->failure_message( $i, $fields, 'processed field has no key' ) );
				$this->assertNotEmpty( $field['key'], $generator->failure_message( $i, $fields, 'processed field has empty key' ) );
			}
		}
	}
}
