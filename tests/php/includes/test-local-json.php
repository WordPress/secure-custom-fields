<?php
/**
 * Tests for local-json.php
 *
 * Integration tests for the ACF_Local_JSON class: save/load paths, writing
 * field groups to .json files, deleting files, loading field groups, post
 * types and taxonomies from JSON files, and malformed file handling.
 *
 * Uses a real temporary directory registered via the acf/settings/save_json
 * and acf/settings/load_json filters.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test ACF_Local_JSON functionality.
 */
class Test_Local_JSON extends BaseTestCase {

	/**
	 * The temporary JSON directory for this test.
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * The ACF_Local_JSON instance.
	 *
	 * @var ACF_Local_JSON
	 */
	private $json;

	/**
	 * Filter callback returning the temp save path.
	 *
	 * @var callable
	 */
	private $save_filter;

	/**
	 * Filter callback returning the temp load paths.
	 *
	 * @var callable
	 */
	private $load_filter;

	/**
	 * Post IDs created during a test, deleted in tearDown.
	 *
	 * @var array
	 */
	private $post_ids = array();

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->ensure_field_types();

		// Start from a clean slate.
		acf_reset_local();
		acf_get_store( 'local-empty' )->reset();
		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'values' )->reset();

		// Create a real temporary directory for JSON files.
		$this->temp_dir = sys_get_temp_dir() . '/scf-local-json-' . uniqid();
		mkdir( $this->temp_dir, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture outside of WP_Filesystem context.

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

		// Reset the instance's cached file list to our (empty) temp directory.
		$this->json->scan_files();
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->post_ids = array();

		remove_filter( 'acf/settings/save_json', $this->save_filter, 100 );
		remove_filter( 'acf/settings/load_json', $this->load_filter, 100 );

		// Delete the temp directory and its contents (including hidden files).
		$this->delete_dir( $this->temp_dir );

		// Reset the instance's internal file cache now that the filters are gone.
		$this->json->scan_files();

		// Reset all local stores so we do not poison other tests.
		acf_reset_local();
		acf_get_store( 'local-empty' )->reset();
		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'values' )->reset();

		parent::tearDown();
	}

	/**
	 * Recursively deletes a directory, including hidden files.
	 *
	 * @param string $dir The directory path.
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

			if ( is_dir( $path ) ) {
				$this->delete_dir( $path );
			} else {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture cleanup.
			}
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture cleanup.
	}

	/**
	 * Ensures the field types used by these tests are registered with their hooks.
	 *
	 * Field types normally register on 'init', which never fires in the WorDBless
	 * environment. The per-test hook restore also wipes their filters, so we
	 * re-register them for every test.
	 *
	 * @return void
	 */
	private function ensure_field_types() {
		acf_include( 'includes/fields/class-acf-field-text.php' );

		if ( ! has_filter( 'acf/prepare_field_for_import/type=text' ) ) {
			acf_register_field_type( 'acf_field_text' );
		}
	}

	/**
	 * Writes a JSON file into the temp directory.
	 *
	 * @param string $filename The filename.
	 * @param mixed  $data     Data to encode, or a raw string to write as-is.
	 * @return string The full file path.
	 */
	private function write_json_file( $filename, $data ) {
		$file     = $this->temp_dir . '/' . $filename;
		$contents = is_string( $data ) ? $data : acf_json_encode( $data );
		file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		return $file;
	}

	// =========================================================================
	// is_enabled()
	// =========================================================================

	/**
	 * Test is_enabled reflects the json setting and can be toggled via filter.
	 */
	public function test_is_enabled_toggling_via_filter() {
		$this->assertTrue( $this->json->is_enabled(), 'Local JSON should be enabled by default' );

		add_filter( 'acf/settings/json', '__return_false' );
		$this->assertFalse( $this->json->is_enabled(), 'Local JSON should be disabled via the settings filter' );

		remove_filter( 'acf/settings/json', '__return_false' );
		$this->assertTrue( $this->json->is_enabled(), 'Local JSON should be enabled again after removing the filter' );
	}

	// =========================================================================
	// Saving field groups
	// =========================================================================

	/**
	 * Test saving a field group through acf_update_field_group writes a JSON file.
	 */
	public function test_update_field_group_writes_json_file() {
		$key = 'group_json_save_' . uniqid();

		// The acf/update_field_group action fires inside acf_update_field_group.
		$field_group      = acf_update_field_group(
			array(
				'key'    => $key,
				'title'  => 'JSON Save Group',
				'fields' => array(),
				'active' => true,
			)
		);
		$this->post_ids[] = $field_group['ID'];

		$file = $this->temp_dir . '/' . $key . '.json';
		$this->assertFileExists( $file, 'A JSON file should be written when a field group is updated' );

		$data = json_decode( file_get_contents( $file ), true );
		$this->assertIsArray( $data, 'File should contain valid JSON' );
		$this->assertSame( $key, $data['key'], 'JSON should contain the field group key' );
		$this->assertSame( 'JSON Save Group', $data['title'], 'JSON should contain the field group title' );
		$this->assertSame( array(), $data['fields'], 'JSON should contain the (empty) fields array' );
		$this->assertIsInt( $data['modified'], 'JSON should contain a modified timestamp' );
		$this->assertGreaterThan( 0, $data['modified'], 'Modified timestamp should be positive' );
		$this->assertArrayNotHasKey( 'ID', $data, 'JSON should not contain the database ID' );
	}

	/**
	 * Test the saved JSON includes the field group's fields.
	 *
	 * Uses local fields since WorDBless does not replicate parent/child
	 * post queries needed for DB field lookups.
	 */
	public function test_update_field_group_writes_fields_to_json() {
		$key       = 'group_json_fields_' . uniqid();
		$field_key = 'field_json_' . uniqid();

		acf_add_local_field_group(
			array(
				'key'    => $key,
				'title'  => 'JSON Fields Group',
				'fields' => array(
					array(
						'key'   => $field_key,
						'label' => 'JSON Field',
						'name'  => 'json_field',
						'type'  => 'text',
					),
				),
			)
		);

		// Save the group; update_field_group appends fields via acf_get_fields().
		$this->json->update_field_group(
			array(
				'ID'    => 0,
				'key'   => $key,
				'title' => 'JSON Fields Group',
			)
		);

		$data = json_decode( file_get_contents( $this->temp_dir . '/' . $key . '.json' ), true );

		$this->assertCount( 1, $data['fields'], 'JSON should contain the field' );
		$this->assertSame( $field_key, $data['fields'][0]['key'], 'Field key should match' );
		$this->assertSame( 'json_field', $data['fields'][0]['name'], 'Field name should match' );
		$this->assertSame( 'text', $data['fields'][0]['type'], 'Field type should match' );
	}

	/**
	 * Test update_field_group returns false and writes nothing when disabled.
	 */
	public function test_update_field_group_does_nothing_when_disabled() {
		add_filter( 'acf/settings/json', '__return_false' );

		$result = $this->json->update_field_group(
			array(
				'ID'     => 0,
				'key'    => 'group_json_disabled',
				'title'  => 'Disabled',
				'fields' => array(),
			)
		);

		remove_filter( 'acf/settings/json', '__return_false' );

		$this->assertFalse( $result, 'Should return false when disabled' );
		$this->assertFileDoesNotExist( $this->temp_dir . '/group_json_disabled.json', 'No file should be written when disabled' );
	}

	/**
	 * Test save_file writes a JSON file for a local-only (no DB) field group.
	 */
	public function test_save_file_writes_local_only_group() {
		$key = 'group_json_local_only';

		$result = $this->json->save_file(
			$key,
			array(
				'ID'     => 0,
				'key'    => $key,
				'title'  => 'Local Only',
				'fields' => array(),
			)
		);

		$this->assertTrue( $result, 'save_file should return true on success' );
		$this->assertFileExists( $this->temp_dir . '/' . $key . '.json', 'File should be written' );

		$data = json_decode( file_get_contents( $this->temp_dir . '/' . $key . '.json' ), true );
		$this->assertIsInt( $data['modified'], 'Modified timestamp should be set even without a DB post' );
	}

	/**
	 * Test save_file returns false for a key that is not a recognizable ACF post type.
	 */
	public function test_save_file_rejects_unknown_key_prefix() {
		$result = $this->json->save_file(
			'bogus_key',
			array(
				'ID'    => 0,
				'key'   => 'bogus_key',
				'title' => 'Bogus',
			)
		);

		$this->assertFalse( $result, 'Should return false for an unknown key prefix' );
		$this->assertFileDoesNotExist( $this->temp_dir . '/bogus_key.json', 'No file should be written' );
	}

	/**
	 * Test save_file returns false when no save path is writable.
	 */
	public function test_save_file_returns_false_without_writable_path() {
		$bad_path = $this->temp_dir . '/does-not-exist';
		$override = function () use ( $bad_path ) {
			return $bad_path;
		};
		add_filter( 'acf/settings/save_json', $override, 200 );

		$result = $this->json->save_file(
			'group_unwritable',
			array(
				'ID'    => 0,
				'key'   => 'group_unwritable',
				'title' => 'Unwritable',
			)
		);

		remove_filter( 'acf/settings/save_json', $override, 200 );

		$this->assertFalse( $result, 'Should return false when no path is writable' );
	}

	/**
	 * Test the acf/json/save_file_name filter changes the written filename.
	 */
	public function test_save_file_name_filter() {
		$rename = function () {
			return 'custom-name.json';
		};
		add_filter( 'acf/json/save_file_name', $rename );

		$result = $this->json->save_file(
			'group_renamed',
			array(
				'ID'     => 0,
				'key'    => 'group_renamed',
				'title'  => 'Renamed',
				'fields' => array(),
			)
		);

		remove_filter( 'acf/json/save_file_name', $rename );

		$this->assertTrue( $result, 'Save should succeed' );
		$this->assertFileExists( $this->temp_dir . '/custom-name.json', 'Custom filename should be used' );
		$this->assertFileDoesNotExist( $this->temp_dir . '/group_renamed.json', 'Default filename should not be used' );
	}

	/**
	 * Test save_file fails when the filename filter returns a non-string.
	 */
	public function test_save_file_rejects_non_string_filename() {
		$break = function () {
			return false;
		};
		add_filter( 'acf/json/save_file_name', $break );

		$result = $this->json->save_file(
			'group_no_filename',
			array(
				'ID'    => 0,
				'key'   => 'group_no_filename',
				'title' => 'No Filename',
			)
		);

		remove_filter( 'acf/json/save_file_name', $break );

		$this->assertFalse( $result, 'Should return false when the filename filter returns a non-string' );
	}

	/**
	 * Test acf_write_json_field_group wrapper writes a file.
	 */
	public function test_acf_write_json_field_group_wrapper() {
		$key = 'group_wrapper_write';

		$result = acf_write_json_field_group(
			array(
				'ID'     => 0,
				'key'    => $key,
				'title'  => 'Wrapper Write',
				'fields' => array(),
			)
		);

		$this->assertTrue( $result, 'Wrapper should return true' );
		$this->assertFileExists( $this->temp_dir . '/' . $key . '.json', 'Wrapper should write the file' );
	}

	// =========================================================================
	// Deleting field groups
	// =========================================================================

	/**
	 * Test delete_field_group removes the JSON file.
	 */
	public function test_delete_field_group_removes_file() {
		$key = 'group_json_delete';
		$this->write_json_file(
			$key . '.json',
			array(
				'key'    => $key,
				'title'  => 'Delete Me',
				'fields' => array(),
			)
		);

		$result = $this->json->delete_field_group( array( 'key' => $key ) );

		$this->assertTrue( $result, 'delete_field_group should return true' );
		$this->assertFileDoesNotExist( $this->temp_dir . '/' . $key . '.json', 'File should be deleted' );
	}

	/**
	 * Test delete_field_group strips the WP __trashed suffix from the key.
	 */
	public function test_delete_field_group_strips_trashed_suffix() {
		$key = 'group_json_trashed';
		$this->write_json_file(
			$key . '.json',
			array(
				'key'    => $key,
				'title'  => 'Trash Me',
				'fields' => array(),
			)
		);

		$result = $this->json->delete_field_group( array( 'key' => $key . '__trashed' ) );

		$this->assertTrue( $result, 'delete_field_group should return true' );
		$this->assertFileDoesNotExist( $this->temp_dir . '/' . $key . '.json', 'File should be deleted using the un-trashed key' );
	}

	/**
	 * Test delete_internal_post_type returns false when disabled and keeps the file.
	 */
	public function test_delete_does_nothing_when_disabled() {
		$key = 'group_json_keep';
		$this->write_json_file(
			$key . '.json',
			array(
				'key'    => $key,
				'title'  => 'Keep Me',
				'fields' => array(),
			)
		);

		add_filter( 'acf/settings/json', '__return_false' );
		$result = $this->json->delete_field_group( array( 'key' => $key ) );
		remove_filter( 'acf/settings/json', '__return_false' );

		$this->assertFalse( $result, 'Should return false when disabled' );
		$this->assertFileExists( $this->temp_dir . '/' . $key . '.json', 'File should not be deleted when disabled' );
	}

	/**
	 * Test acf_delete_json_field_group wrapper deletes the file.
	 */
	public function test_acf_delete_json_field_group_wrapper() {
		$key = 'group_wrapper_delete';
		$this->write_json_file(
			$key . '.json',
			array(
				'key'    => $key,
				'title'  => 'Wrapper Delete',
				'fields' => array(),
			)
		);

		$result = acf_delete_json_field_group( $key );

		$this->assertTrue( $result, 'Wrapper should return true' );
		$this->assertFileDoesNotExist( $this->temp_dir . '/' . $key . '.json', 'Wrapper should delete the file' );
	}

	// =========================================================================
	// Loading from JSON files
	// =========================================================================

	/**
	 * Test include_fields loads field groups from JSON files into the local store.
	 */
	public function test_include_fields_registers_local_field_groups() {
		$key       = 'group_json_load';
		$field_key = 'field_json_load';
		$file      = $this->write_json_file(
			$key . '.json',
			array(
				'key'    => $key,
				'title'  => 'Loaded From JSON',
				'fields' => array(
					array(
						'key'   => $field_key,
						'label' => 'Loaded Field',
						'name'  => 'loaded_field',
						'type'  => 'text',
					),
				),
			)
		);

		$this->json->include_fields();

		$this->assertTrue( acf_is_local_field_group( $key ), 'Field group should be registered locally' );

		$group = acf_get_local_field_group( $key );
		$this->assertSame( 'json', $group['local'], 'Local source should be json' );
		$this->assertSame( $file, $group['local_file'], 'Local file path should be recorded' );
		$this->assertSame( 'Loaded From JSON', $group['title'], 'Title should be loaded from the file' );

		// Its fields should be registered too.
		$this->assertTrue( acf_is_local_field( $field_key ), 'Field should be registered locally' );
		$field = acf_get_local_field( $field_key );
		$this->assertSame( $key, $field['parent'], 'Field parent should be the group key' );

		// And resolvable through the public fields API.
		$fields = acf_get_fields( $key );
		$this->assertCount( 1, $fields, 'acf_get_fields should resolve the JSON-loaded field' );
		$this->assertSame( 'loaded_field', $fields[0]['name'], 'Field name should match' );
	}

	/**
	 * Test include_fields does nothing when disabled.
	 */
	public function test_include_fields_does_nothing_when_disabled() {
		$key = 'group_json_disabled_load';
		$this->write_json_file(
			$key . '.json',
			array(
				'key'    => $key,
				'title'  => 'Should Not Load',
				'fields' => array(),
			)
		);

		add_filter( 'acf/settings/json', '__return_false' );
		$this->json->include_fields();
		remove_filter( 'acf/settings/json', '__return_false' );

		$this->assertFalse( acf_is_local_field_group( $key ), 'Field group should not be registered when disabled' );
	}

	/**
	 * Test include_post_types loads post types from JSON files.
	 */
	public function test_include_post_types_registers_local_post_types() {
		$key = 'post_type_json_load';
		$this->write_json_file(
			$key . '.json',
			array(
				'key'       => $key,
				'title'     => 'JSON Post Type',
				'post_type' => 'json_pt',
			)
		);

		$this->json->include_post_types();

		$this->assertTrue(
			acf_is_local_internal_post_type( $key, 'acf-post-type' ),
			'Post type should be registered locally'
		);

		$post_type = acf_get_local_internal_post_type( $key, 'acf-post-type' );
		$this->assertSame( 'json', $post_type['local'], 'Local source should be json' );
	}

	/**
	 * Test include_taxonomies loads taxonomies from JSON files.
	 */
	public function test_include_taxonomies_registers_local_taxonomies() {
		$key = 'taxonomy_json_load';
		$this->write_json_file(
			$key . '.json',
			array(
				'key'      => $key,
				'title'    => 'JSON Taxonomy',
				'taxonomy' => 'json_tax',
			)
		);

		$this->json->include_taxonomies();

		$this->assertTrue(
			acf_is_local_internal_post_type( $key, 'acf-taxonomy' ),
			'Taxonomy should be registered locally'
		);
	}

	// =========================================================================
	// Scanning and file listing
	// =========================================================================

	/**
	 * Test acf_get_local_json_files returns scanned files keyed by post key.
	 */
	public function test_acf_get_local_json_files_returns_scanned_files() {
		$file_a = $this->write_json_file(
			'group_scan_a.json',
			array(
				'key'    => 'group_scan_a',
				'title'  => 'Scan A',
				'fields' => array(),
			)
		);
		$file_b = $this->write_json_file(
			'group_scan_b.json',
			array(
				'key'    => 'group_scan_b',
				'title'  => 'Scan B',
				'fields' => array(),
			)
		);

		$this->json->scan_files();
		$files = acf_get_local_json_files();

		$this->assertSame( $file_a, $files['group_scan_a'], 'File A should be mapped to its key' );
		$this->assertSame( $file_b, $files['group_scan_b'], 'File B should be mapped to its key' );
	}

	/**
	 * Test files are filtered by ACF post type.
	 */
	public function test_acf_get_local_json_files_filters_by_post_type() {
		$this->write_json_file(
			'group_scan_fg.json',
			array(
				'key'    => 'group_scan_fg',
				'title'  => 'Scan Group',
				'fields' => array(),
			)
		);
		$pt_file = $this->write_json_file(
			'post_type_scan.json',
			array(
				'key'       => 'post_type_scan',
				'title'     => 'Scan Post Type',
				'post_type' => 'scan_pt',
			)
		);

		$this->json->scan_files();

		$group_files = acf_get_local_json_files( 'acf-field-group' );
		$this->assertArrayHasKey( 'group_scan_fg', $group_files, 'Field group file should be listed for acf-field-group' );
		$this->assertArrayNotHasKey( 'post_type_scan', $group_files, 'Post type file should not be listed for acf-field-group' );

		$pt_files = acf_get_local_json_files( 'acf-post-type' );
		$this->assertSame( array( 'post_type_scan' => $pt_file ), $pt_files, 'Post type file should be listed for acf-post-type' );
	}

	/**
	 * Test malformed and irrelevant files are ignored by the scanner.
	 */
	public function test_scan_files_ignores_malformed_and_irrelevant_files() {
		// Valid file.
		$this->write_json_file(
			'group_valid.json',
			array(
				'key'    => 'group_valid',
				'title'  => 'Valid',
				'fields' => array(),
			)
		);

		// Malformed JSON.
		$this->write_json_file( 'group_malformed.json', '{ this is not valid JSON !!!' );

		// Valid JSON but no key.
		$this->write_json_file( 'group_keyless.json', array( 'title' => 'No Key' ) );

		// Valid JSON but not an array/object.
		$this->write_json_file( 'group_scalar.json', '"just a string"' );

		// Non-JSON extension.
		$this->write_json_file( 'group_not_json.txt', array( 'key' => 'group_not_json' ) );

		// Hidden file.
		$this->write_json_file( '.group_hidden.json', array( 'key' => 'group_hidden' ) );

		// Sub directory.
		mkdir( $this->temp_dir . '/subdir' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture.

		$files = $this->json->scan_files();

		$this->assertSame( array( 'group_valid' ), array_keys( $files ), 'Only the valid JSON field group file should be found' );

		// include_fields should also load only the valid one, without erroring.
		$this->json->include_fields();
		$this->assertTrue( acf_is_local_field_group( 'group_valid' ), 'Valid group should be loaded' );
		$this->assertSame( 1, acf_count_local_field_groups(), 'Only one local group should be registered' );
	}

	/**
	 * Test the modified timestamp in the JSON matches the post's modified time.
	 *
	 * The 'modified' value is what the acf/json sync logic compares against the
	 * database to detect when a JSON file is newer than the saved field group.
	 */
	public function test_saved_json_modified_matches_post_modified_time() {
		$key              = 'group_json_modified_' . uniqid();
		$field_group      = acf_update_field_group(
			array(
				'key'    => $key,
				'title'  => 'Modified Time Group',
				'fields' => array(),
				'active' => true,
			)
		);
		$this->post_ids[] = $field_group['ID'];

		$data = json_decode( file_get_contents( $this->temp_dir . '/' . $key . '.json' ), true );

		$expected = (int) get_post_modified_time( 'U', true, $field_group['ID'] );
		$this->assertSame( $expected, $data['modified'], 'JSON modified timestamp should match the post modified time' );
	}

	/**
	 * Test the acf/json/save_paths filter can redirect where files are written.
	 */
	public function test_save_paths_filter_redirects_save_location() {
		$alt_dir = $this->temp_dir . '/alt';
		mkdir( $alt_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture.

		$redirect = function () use ( $alt_dir ) {
			return array( $alt_dir );
		};
		add_filter( 'acf/json/save_paths', $redirect, 100 );

		$result = $this->json->save_file(
			'group_alt_path',
			array(
				'ID'     => 0,
				'key'    => 'group_alt_path',
				'title'  => 'Alt Path',
				'fields' => array(),
			)
		);

		remove_filter( 'acf/json/save_paths', $redirect, 100 );

		$this->assertTrue( $result, 'Save should succeed' );
		$this->assertFileExists( $alt_dir . '/group_alt_path.json', 'File should be written to the filtered path' );
		$this->assertFileDoesNotExist( $this->temp_dir . '/group_alt_path.json', 'File should not be written to the default path' );
	}
}
