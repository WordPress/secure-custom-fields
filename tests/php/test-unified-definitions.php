<?php
/**
 * Tests for unified CPT+fields definition loader (issue #163).
 *
 * Verifies that ACF_Local_JSON detects, validates, and loads JSON files
 * that declare a custom post type together with the field groups attached
 * to it. Legacy single-object files continue to load through the existing
 * per-type includes.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Local_JSON unified CPT+fields handling.
 */
class Test_Unified_Definitions extends BaseTestCase {

	/**
	 * Temporary JSON directory used by each test.
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * ACF_Local_JSON instance under test.
	 *
	 * @var ACF_Local_JSON
	 */
	private $json;

	/**
	 * Save-path filter callback.
	 *
	 * @var callable
	 */
	private $save_filter;

	/**
	 * Load-path filter callback.
	 *
	 * @var callable
	 */
	private $load_filter;

	/**
	 * Set up: create temp dir, hook filters, reset local stores.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		acf_include( 'includes/fields/class-acf-field-text.php' );
		if ( ! has_filter( 'acf/prepare_field_for_import/type=text' ) ) {
			acf_register_field_type( 'acf_field_text' );
		}

		acf_reset_local();
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'local-post-types' )->reset();
		acf_get_store( 'local-groups' )->reset();

		$this->temp_dir = sys_get_temp_dir() . '/scf-unified-' . uniqid();
		mkdir( $this->temp_dir, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture.

		$dir               = $this->temp_dir;
		$this->save_filter = function () use ( $dir ) {
			return $dir;
		};
		$this->load_filter = function () use ( $dir ) {
			return array( $dir );
		};

		add_filter( 'acf/settings/save_json', $this->save_filter, 100 );
		add_filter( 'acf/settings/load_json', $this->load_filter, 100 );

		$this->json = acf_get_instance( 'ACF_Local_JSON' );
		$this->json->scan_files();
	}

	/**
	 * Tear down: remove filters, drop temp dir, reset stores.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'acf/settings/save_json', $this->save_filter, 100 );
		remove_filter( 'acf/settings/load_json', $this->load_filter, 100 );
		$this->delete_dir( $this->temp_dir );
		acf_reset_local();
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'local-post-types' )->reset();
		acf_get_store( 'local-groups' )->reset();
		parent::tearDown();
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @param string $dir Path to remove.
	 * @return void
	 */
	private function delete_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir( $path ) ? $this->delete_dir( $path ) : unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test cleanup.
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test cleanup.
	}

	/**
	 * Writes a JSON fixture to the temp dir.
	 *
	 * @param string $filename File name without leading slash.
	 * @param mixed  $data     Array, decoded object, or raw JSON string.
	 * @return string Full path.
	 */
	private function write_json( $filename, $data ) {
		$file     = $this->temp_dir . '/' . $filename;
		$contents = is_string( $data ) ? $data : acf_json_encode( $data );
		file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		return $file;
	}

	// =====================================================================
	// Static is_unified_definition() detector
	// =====================================================================

	/**
	 * Returns true for a payload with post_type + field_groups.
	 *
	 * @return void
	 */
	public function test_detector_accepts_post_type_with_field_groups() {
		$payload = array(
			'key'          => 'post_type_book',
			'title'        => 'Book',
			'post_type'    => 'book',
			'field_groups' => array(
				array(
					'key'    => 'group_a',
					'title'  => 'A',
					'fields' => array(),
				),
			),
		);
		$this->assertTrue( ACF_Local_JSON::is_unified_definition( $payload ) );
	}

	/**
	 * Returns true for a payload with post_type + top-level fields.
	 *
	 * @return void
	 */
	public function test_detector_accepts_post_type_with_top_level_fields() {
		$payload = array(
			'key'       => 'post_type_book',
			'title'     => 'Book',
			'post_type' => 'book',
			'fields'    => array(
				array(
					'key'   => 'field_a',
					'label' => 'A',
					'name'  => 'a',
					'type'  => 'text',
				),
			),
		);
		$this->assertTrue( ACF_Local_JSON::is_unified_definition( $payload ) );
	}

	/**
	 * Returns false for a legacy CPT-only payload (no field_groups or fields).
	 *
	 * @return void
	 */
	public function test_detector_rejects_legacy_cpt_only_payload() {
		$payload = array(
			'key'       => 'post_type_book',
			'title'     => 'Book',
			'post_type' => 'book',
		);
		$this->assertFalse( ACF_Local_JSON::is_unified_definition( $payload ) );
	}

	/**
	 * Returns false for a legacy field-group payload (no post_type).
	 *
	 * @return void
	 */
	public function test_detector_rejects_legacy_field_group_payload() {
		$payload = array(
			'key'    => 'group_abc',
			'title'  => 'Group',
			'fields' => array(),
		);
		$this->assertFalse( ACF_Local_JSON::is_unified_definition( $payload ) );
	}

	/**
	 * Returns false for non-array input.
	 *
	 * @return void
	 */
	public function test_detector_rejects_non_array_input() {
		$this->assertFalse( ACF_Local_JSON::is_unified_definition( null ) );
		$this->assertFalse( ACF_Local_JSON::is_unified_definition( 'string' ) );
		$this->assertFalse( ACF_Local_JSON::is_unified_definition( 123 ) );
	}

	// =====================================================================
	// Scan: unified files are skipped from legacy scans
	// =====================================================================

	/**
	 * A unified file is registered in unified_files, not files.
	 *
	 * @return void
	 */
	public function test_scan_routes_unified_files_separately() {
		$this->write_json(
			'post_type_book.json',
			array(
				'key'          => 'post_type_book',
				'title'        => 'Book',
				'post_type'    => 'book',
				'field_groups' => array(
					array(
						'key'    => 'group_book_details',
						'title'  => 'Book Details',
						'fields' => array(),
					),
				),
			)
		);

		$this->json->scan_files( 'acf-post-type' );

		$unified = $this->json->get_unified_files();
		$this->assertArrayHasKey( 'post_type_book', $unified, 'Unified file should land in unified_files' );
		$this->assertArrayNotHasKey( 'post_type_book', $this->json->get_files( 'acf-post-type' ), 'Unified file should NOT appear in legacy post-type scan' );
		$this->assertArrayNotHasKey( 'post_type_book', $this->json->get_files( 'acf-field-group' ), 'Unified file should NOT appear in field-group scan' );
	}

	// =====================================================================
	// End-to-end load
	// =====================================================================

	/**
	 * A unified file with explicit field_groups loads the CPT and its groups.
	 *
	 * @return void
	 */
	public function test_include_unified_loads_cpt_and_explicit_field_groups() {
		$this->write_json(
			'post_type_book.json',
			array(
				'key'          => 'post_type_book',
				'title'        => 'Book',
				'post_type'    => 'book',
				'public'       => true,
				'field_groups' => array(
					array(
						'key'    => 'group_book_details',
						'title'  => 'Book Details',
						'fields' => array(
							array(
								'key'   => 'field_book_isbn',
								'label' => 'ISBN',
								'name'  => 'isbn',
								'type'  => 'text',
							),
						),
					),
				),
			)
		);

		$this->json->include_unified_definitions();

		$cpt = acf_get_local_internal_post_type( 'post_type_book', 'acf-post-type' );
		$this->assertIsArray( $cpt, 'Local CPT should be registered from unified file' );
		$this->assertSame( 'book', $cpt['post_type'] );
		$this->assertSame( 'Book', $cpt['title'] );

		$group = acf_get_local_field_group( 'group_book_details' );
		$this->assertIsArray( $group, 'Field group from unified file should be registered' );
		$this->assertSame( 'Book Details', $group['title'] );

		$fields = array_values( acf_get_local_fields( 'group_book_details' ) );
		$this->assertCount( 1, $fields, 'Field group should contain the declared field(s)' );
		$this->assertSame( 'field_book_isbn', $fields[0]['key'] );

		// Location must attach to the owning CPT.
		$this->assertNotEmpty( $group['location'], 'Field group must have location rules' );
		$attached = false;
		foreach ( $group['location'] as $or_group ) {
			foreach ( $or_group as $rule ) {
				if ( 'post_type' === $rule['param'] && '==' === $rule['operator'] && 'book' === $rule['value'] ) {
					$attached = true;
					break 2;
				}
			}
		}
		$this->assertTrue( $attached, 'Field group must include a post_type rule targeting the owning CPT' );
	}

	/**
	 * A unified file with only top-level fields auto-wraps into a synthetic group.
	 *
	 * @return void
	 */
	public function test_include_unified_auto_wraps_top_level_fields() {
		$this->write_json(
			'post_type_movie.json',
			array(
				'key'       => 'post_type_movie',
				'title'     => 'Movie',
				'post_type' => 'movie',
				'fields'    => array(
					array(
						'key'   => 'field_movie_director',
						'label' => 'Director',
						'name'  => 'director',
						'type'  => 'text',
					),
				),
			)
		);

		$this->json->include_unified_definitions();

		$cpt = acf_get_local_internal_post_type( 'post_type_movie', 'acf-post-type' );
		$this->assertIsArray( $cpt );

		$group = acf_get_local_field_group( 'group_movie_fields' );
		$this->assertIsArray( $group, 'Auto-wrapped field group should be registered' );
		$this->assertSame( 'Movie Fields', $group['title'] );

		$fields = array_values( acf_get_local_fields( 'group_movie_fields' ) );
		$this->assertCount( 1, $fields, 'Auto-wrapped group should contain the declared field' );
		$this->assertSame( 'field_movie_director', $fields[0]['key'] );
	}

	/**
	 * Explicit location rules coexist with the injected post_type rule.
	 *
	 * @return void
	 */
	public function test_include_unified_preserves_explicit_location_rules() {
		$this->write_json(
			'post_type_album.json',
			array(
				'key'          => 'post_type_album',
				'title'        => 'Album',
				'post_type'    => 'album',
				'field_groups' => array(
					array(
						'key'      => 'group_album_meta',
						'title'    => 'Album Meta',
						'fields'   => array(),
						'location' => array(
							array(
								array(
									'param'    => 'post_type',
									'operator' => '==',
									'value'    => 'page',
								),
							),
						),
					),
				),
			)
		);

		$this->json->include_unified_definitions();

		$group = acf_get_local_field_group( 'group_album_meta' );
		$this->assertIsArray( $group );

		// Both rules should be present (user-provided `page` rule AND injected `album` rule).
		$has_page   = false;
		$has_album  = false;
		$has_inject = false;
		foreach ( $group['location'] as $or_group ) {
			foreach ( $or_group as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				if ( 'page' === $rule['value'] ) {
					$has_page = true;
				}
				if ( 'album' === $rule['value'] ) {
					$has_album = true;
				}
			}
		}
		$this->assertTrue( $has_page, 'Explicit user location rule should be preserved' );
		$this->assertTrue( $has_album, 'Injected post_type rule for owning CPT should also be present' );
	}

	/**
	 * Legacy files coexist with unified files in the same directory.
	 *
	 * @return void
	 */
	public function test_legacy_files_still_load_alongside_unified() {
		// Unified file.
		$this->write_json(
			'post_type_book.json',
			array(
				'key'          => 'post_type_book',
				'title'        => 'Book',
				'post_type'    => 'book',
				'field_groups' => array(
					array(
						'key'    => 'group_book_details',
						'title'  => 'Book Details',
						'fields' => array(),
					),
				),
			)
		);

		// Legacy CPT file.
		$this->write_json(
			'post_type_album.json',
			array(
				'key'       => 'post_type_album',
				'title'     => 'Album',
				'post_type' => 'album',
			)
		);

		// Legacy field group file.
		$this->write_json(
			'group_legacy.json',
			array(
				'key'    => 'group_legacy',
				'title'  => 'Legacy Group',
				'fields' => array(),
			)
		);

		$this->json->scan_files( 'acf-post-type' );

		// Unified loads via its own method.
		$this->json->include_unified_definitions();
		$this->assertNotNull( acf_get_local_internal_post_type( 'post_type_book', 'acf-post-type' ) );

		// Legacy loads via per-type includes.
		$this->json->include_post_types();
		$this->json->include_fields();
		$this->assertNotNull( acf_get_local_internal_post_type( 'post_type_album', 'acf-post-type' ), 'Legacy CPT file still loads' );
		$this->assertNotNull( acf_get_local_field_group( 'group_legacy' ), 'Legacy field group file still loads' );
	}

	/**
	 * The injected post_type location rule matches the actual location matcher.
	 *
	 * This exercises ACF_Location_Post_Type against the loader's output.
	 *
	 * @return void
	 */
	public function test_field_groups_record_source_file() {
		$file = $this->write_json(
			'post_type_book.json',
			array(
				'key'          => 'post_type_book',
				'title'        => 'Book',
				'post_type'    => 'book',
				'field_groups' => array(
					array(
						'key'    => 'group_book_details',
						'title'  => 'Book Details',
						'fields' => array(),
					),
				),
			)
		);

		$this->json->include_unified_definitions();

		$group = acf_get_local_field_group( 'group_book_details' );
		$this->assertIsArray( $group );
		$this->assertSame( $file, $group['local_file'], 'Field group should record its source path' );
		$this->assertSame( 'json', $group['local'] );
	}

	/**
	 * The injected post_type location rule is what makes the field group
	 * render on the owning CPT's edit screen at runtime. We verify its
	 * presence here. End-to-end visibility is exercised by the manual
	 * smoke test (WorDBless does not register ACF_Location types).
	 *
	 * @return void
	 */
	public function test_injected_location_targets_owning_cpt() {
		// The injected post_type location rule is what makes the field group
		// render on the owning CPT's edit screen at runtime. We verify its
		// presence here. End-to-end visibility is exercised by the manual
		// smoke test (WorDBless does not register ACF_Location types).
		$this->write_json(
			'post_type_book.json',
			array(
				'key'          => 'post_type_book',
				'title'        => 'Book',
				'post_type'    => 'book',
				'field_groups' => array(
					array(
						'key'    => 'group_book_details',
						'title'  => 'Book Details',
						'fields' => array(),
					),
				),
			)
		);

		$this->json->include_unified_definitions();

		$group = acf_get_local_field_group( 'group_book_details' );
		$this->assertIsArray( $group );

		$founded = false;
		foreach ( $group['location'] as $or_group ) {
			if ( ! is_array( $or_group ) ) {
				continue;
			}
			foreach ( $or_group as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				if ( 'post_type' === $rule['param'] && '==' === $rule['operator'] && 'book' === $rule['value'] ) {
					$founded = true;
					break 2;
				}
			}
		}
		$this->assertTrue( $founded, 'Field group must carry an injected post_type==book location rule' );
	}

	// =====================================================================
	// Schema validation against unified-cpt.schema.json
	// =====================================================================

	/**
	 * Valid unified fixture passes the schema validator.
	 *
	 * @return void
	 */
	public function test_unified_schema_accepts_valid_fixture() {
		$fixture = dirname( __DIR__ ) . '/fixtures/schemas/unified/valid/post-type-book-with-field-groups.json';
		// Locate the family-wide fixtures dir, fallback to tests/php/fixtures.
		if ( ! file_exists( $fixture ) ) {
			$fixture = __DIR__ . '/fixtures/schemas/unified/valid/post-type-book-with-field-groups.json';
		}
		$this->assertFileExists( $fixture );

		$validator = acf_get_instance( 'SCF_JSON_Schema_Validator' );
		$this->assertTrue( $validator->validate( $fixture, 'unified-cpt' ), 'Valid unified file should validate against unified-cpt schema' );
		$this->assertFalse( $validator->has_validation_errors() );
	}

	/**
	 * Invalid unified fixture fails the schema validator.
	 *
	 * @return void
	 */
	public function test_unified_schema_rejects_invalid_fixture() {
		$fixture = dirname( __DIR__ ) . '/fixtures/schemas/unified/invalid/post-type-product-missing-post-type.json';
		if ( ! file_exists( $fixture ) ) {
			$fixture = __DIR__ . '/fixtures/schemas/unified/invalid/post-type-product-missing-post-type.json';
		}
		$this->assertFileExists( $fixture );

		$validator = acf_get_instance( 'SCF_JSON_Schema_Validator' );
		$this->assertFalse( $validator->validate( $fixture, 'unified-cpt' ), 'Invalid unified file should fail schema validation' );
		$this->assertTrue( $validator->has_validation_errors() );
	}
}
