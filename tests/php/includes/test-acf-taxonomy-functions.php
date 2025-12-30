<?php
/**
 * Tests for ACF taxonomy functions.
 *
 * Tests functions in includes/acf-taxonomy-functions.php that provide
 * the public API for managing custom taxonomies created through SCF.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test ACF taxonomy functions.
 *
 * @covers acf_get_taxonomy
 * @covers acf_get_raw_taxonomy
 * @covers acf_get_taxonomy_post
 * @covers acf_is_taxonomy_key
 * @covers acf_validate_taxonomy
 * @covers acf_translate_taxonomy
 * @covers acf_get_acf_taxonomies
 * @covers acf_get_raw_taxonomies
 * @covers acf_filter_taxonomies
 * @covers acf_update_taxonomy
 * @covers acf_flush_taxonomy_cache
 * @covers acf_delete_taxonomy
 * @covers acf_trash_taxonomy
 * @covers acf_untrash_taxonomy
 * @covers acf_is_taxonomy
 * @covers acf_duplicate_taxonomy
 * @covers acf_update_taxonomy_active_status
 * @covers acf_get_taxonomy_edit_link
 * @covers acf_prepare_taxonomy_for_export
 * @covers acf_export_taxonomy_as_php
 * @covers acf_prepare_taxonomy_for_import
 * @covers acf_import_taxonomy
 */
class Test_ACF_Taxonomy_Functions extends BaseTestCase {

	/**
	 * Test taxonomy ID.
	 *
	 * @var int
	 */
	private $taxonomy_id;

	/**
	 * Test taxonomy key.
	 *
	 * @var string
	 */
	private $taxonomy_key;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Ensure ACF internal post type instances are initialized.
		// This is required for the public API functions to work properly.
		// WorDBless fires plugins_loaded and init BEFORE our plugin is loaded,
		// so we need to fire them again to run our plugin's callbacks.
		if ( ! acf_get_internal_post_type_instance( 'acf-taxonomy' ) ) {
			do_action( 'plugins_loaded' );
			do_action( 'init' );
		}

		$this->taxonomy_key = 'taxonomy_test_' . uniqid();

		// Create a test taxonomy using ACF functions for proper setup.
		$taxonomy = acf_update_taxonomy(
			array(
				'key'         => $this->taxonomy_key,
				'title'       => 'Test Taxonomy',
				'taxonomy'    => 'test_tax',
				'object_type' => array( 'post' ),
				'active'      => true,
			)
		);

		$this->taxonomy_id = $taxonomy['ID'];
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		// Clean up all test posts.
		$posts = get_posts(
			array(
				'post_type'   => 'acf-taxonomy',
				'numberposts' => -1,
				'post_status' => 'any',
			)
		);
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		parent::tearDown();
	}

	/**
	 * Helper to create a taxonomy array.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array Taxonomy data.
	 */
	private function make_taxonomy_array( array $overrides = array() ) {
		$defaults = array(
			'key'          => 'taxonomy_' . uniqid(),
			'title'        => 'Test Taxonomy',
			'taxonomy'     => 'test_tax',
			'object_type'  => array( 'post' ),
			'active'       => true,
			'labels'       => array(
				'name'          => 'Test Taxonomies',
				'singular_name' => 'Test Taxonomy',
			),
			'public'       => true,
			'show_in_rest' => true,
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Test acf_get_taxonomy returns taxonomy data by ID.
	 */
	public function test_acf_get_taxonomy_by_id() {
		$result = acf_get_taxonomy( $this->taxonomy_id );

		$this->assertIsArray( $result, 'Should return array for valid ID' );
		$this->assertEquals( $this->taxonomy_key, $result['key'], 'Should return correct key' );
	}

	/**
	 * Test acf_get_taxonomy returns taxonomy data by key.
	 *
	 * Note: We first call acf_get_taxonomy by ID to populate the store cache,
	 * which creates a key alias. This allows key-based lookup to work via cache
	 * instead of get_posts() which WorDBless doesn't fully support.
	 */
	public function test_acf_get_taxonomy_by_key() {
		// Populate store cache by fetching via ID first (creates key alias).
		acf_get_taxonomy( $this->taxonomy_id );

		$result = acf_get_taxonomy( $this->taxonomy_key );

		$this->assertIsArray( $result, 'Should return array for valid key' );
		$this->assertEquals( $this->taxonomy_key, $result['key'], 'Should return correct key' );
	}

	/**
	 * Test acf_get_taxonomy returns false for invalid ID.
	 */
	public function test_acf_get_taxonomy_returns_false_for_invalid_id() {
		$result = acf_get_taxonomy( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test acf_get_raw_taxonomy returns raw taxonomy data.
	 */
	public function test_acf_get_raw_taxonomy() {
		$result = acf_get_raw_taxonomy( $this->taxonomy_id );

		$this->assertIsArray( $result, 'Should return array for valid ID' );
		$this->assertEquals( $this->taxonomy_key, $result['key'], 'Should return correct key' );
	}

	/**
	 * Test acf_get_raw_taxonomy returns false for invalid ID.
	 */
	public function test_acf_get_raw_taxonomy_returns_false_for_invalid_id() {
		$result = acf_get_raw_taxonomy( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test acf_get_taxonomy_post returns WP_Post object.
	 */
	public function test_acf_get_taxonomy_post_returns_post_object() {
		$result = acf_get_taxonomy_post( $this->taxonomy_id );

		$this->assertInstanceOf( WP_Post::class, $result, 'Should return WP_Post object' );
		$this->assertEquals( $this->taxonomy_id, $result->ID, 'Should return correct post ID' );
	}

	/**
	 * Test acf_get_taxonomy_post returns false for invalid ID.
	 */
	public function test_acf_get_taxonomy_post_returns_false_for_invalid_id() {
		$result = acf_get_taxonomy_post( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test acf_is_taxonomy_key returns true for valid key.
	 */
	public function test_acf_is_taxonomy_key_returns_true_for_valid_key() {
		$result = acf_is_taxonomy_key( 'taxonomy_abc123' );

		$this->assertTrue( $result, 'Should return true for valid taxonomy_ prefix key' );
	}

	/**
	 * Test acf_is_taxonomy_key returns false for invalid key.
	 */
	public function test_acf_is_taxonomy_key_returns_false_for_invalid_key() {
		$result = acf_is_taxonomy_key( 'group_abc123' );

		$this->assertFalse( $result, 'Should return false for non-taxonomy key' );
	}

	/**
	 * Test acf_is_taxonomy_key returns false for post_type key.
	 */
	public function test_acf_is_taxonomy_key_returns_false_for_post_type_key() {
		$result = acf_is_taxonomy_key( 'post_type_abc123' );

		$this->assertFalse( $result, 'Should return false for post_type key' );
	}

	/**
	 * Test acf_is_taxonomy_key returns false for non-string.
	 */
	public function test_acf_is_taxonomy_key_returns_false_for_non_string() {
		$result = acf_is_taxonomy_key( 12345 );

		$this->assertFalse( $result, 'Should return false for non-string' );
	}

	/**
	 * Test acf_validate_taxonomy validates a taxonomy array.
	 */
	public function test_acf_validate_taxonomy_with_valid_array() {
		$taxonomy = $this->make_taxonomy_array();
		$result   = acf_validate_taxonomy( $taxonomy );

		$this->assertIsArray( $result, 'Should return validated array' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
		$this->assertArrayHasKey( 'title', $result, 'Should have title' );
	}

	/**
	 * Test acf_validate_taxonomy with empty array.
	 */
	public function test_acf_validate_taxonomy_with_empty_array() {
		$result = acf_validate_taxonomy( array() );

		$this->assertIsArray( $result, 'Should return array even for empty input' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key with default value' );
	}

	/**
	 * Test acf_translate_taxonomy translates labels.
	 */
	public function test_acf_translate_taxonomy_translates_labels() {
		$taxonomy = $this->make_taxonomy_array(
			array(
				'labels' => array(
					'name'          => 'Genres',
					'singular_name' => 'Genre',
				),
			)
		);

		$result = acf_translate_taxonomy( $taxonomy );

		$this->assertIsArray( $result, 'Should return translated array' );
		$this->assertArrayHasKey( 'labels', $result, 'Should have labels' );
	}

	/**
	 * Test acf_get_acf_taxonomies returns all taxonomies.
	 */
	public function test_acf_get_acf_taxonomies_returns_array() {
		$result = acf_get_acf_taxonomies();

		$this->assertIsArray( $result, 'Should return array' );
	}

	/**
	 * Test acf_get_acf_taxonomies with active filter.
	 */
	public function test_acf_get_acf_taxonomies_with_active_filter() {
		// Create an inactive taxonomy.
		acf_update_taxonomy(
			array(
				'key'         => 'taxonomy_inactive_' . uniqid(),
				'title'       => 'Inactive Taxonomy',
				'taxonomy'    => 'inactive_tax',
				'object_type' => array( 'post' ),
				'active'      => false,
			)
		);

		$active_result   = acf_get_acf_taxonomies( array( 'active' => true ) );
		$inactive_result = acf_get_acf_taxonomies( array( 'active' => false ) );

		$this->assertIsArray( $active_result, 'Active filter should return array' );
		$this->assertIsArray( $inactive_result, 'Inactive filter should return array' );
	}

	/**
	 * Test acf_get_raw_taxonomies returns raw taxonomies.
	 */
	public function test_acf_get_raw_taxonomies_returns_array() {
		$result = acf_get_raw_taxonomies();

		$this->assertIsArray( $result, 'Should return array' );
	}

	/**
	 * Test acf_filter_taxonomies filters by active status.
	 */
	public function test_acf_filter_taxonomies_by_active_status() {
		$taxonomies = array(
			$this->make_taxonomy_array(
				array(
					'key'    => 'taxonomy_active_1',
					'active' => true,
				)
			),
			$this->make_taxonomy_array(
				array(
					'key'    => 'taxonomy_inactive_1',
					'active' => false,
				)
			),
			$this->make_taxonomy_array(
				array(
					'key'    => 'taxonomy_active_2',
					'active' => true,
				)
			),
		);

		$active_result = acf_filter_taxonomies( $taxonomies, array( 'active' => true ) );

		$this->assertCount( 2, $active_result, 'Should return only active taxonomies' );
		foreach ( $active_result as $tax ) {
			$this->assertTrue( $tax['active'], 'All filtered taxonomies should be active' );
		}
	}

	/**
	 * Test acf_filter_taxonomies filters inactive.
	 */
	public function test_acf_filter_taxonomies_filters_inactive() {
		$taxonomies = array(
			$this->make_taxonomy_array(
				array(
					'key'    => 'taxonomy_active_1',
					'active' => true,
				)
			),
			$this->make_taxonomy_array(
				array(
					'key'    => 'taxonomy_inactive_1',
					'active' => false,
				)
			),
			$this->make_taxonomy_array(
				array(
					'key'    => 'taxonomy_inactive_2',
					'active' => false,
				)
			),
		);

		$inactive_result = acf_filter_taxonomies( $taxonomies, array( 'active' => false ) );

		$this->assertCount( 2, $inactive_result, 'Should return only inactive taxonomies' );
		foreach ( $inactive_result as $tax ) {
			$this->assertFalse( $tax['active'], 'All filtered taxonomies should be inactive' );
		}
	}

	/**
	 * Test acf_filter_taxonomies returns all when no filter.
	 */
	public function test_acf_filter_taxonomies_returns_all_without_filter() {
		$taxonomies = array(
			$this->make_taxonomy_array(
				array(
					'key'    => 'taxonomy_1',
					'active' => true,
				)
			),
			$this->make_taxonomy_array(
				array(
					'key'    => 'taxonomy_2',
					'active' => false,
				)
			),
		);

		$result = acf_filter_taxonomies( $taxonomies, array() );

		$this->assertCount( 2, $result, 'Should return all taxonomies without filter' );
	}

	/**
	 * Test acf_update_taxonomy creates a new taxonomy.
	 */
	public function test_acf_update_taxonomy_creates_new() {
		$taxonomy = $this->make_taxonomy_array(
			array(
				'key'      => 'taxonomy_new_' . uniqid(),
				'title'    => 'New Test Taxonomy',
				'taxonomy' => 'new_tax',
			)
		);

		$result = acf_update_taxonomy( $taxonomy );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'ID', $result, 'Should have ID' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
	}

	/**
	 * Test acf_update_taxonomy updates existing taxonomy.
	 */
	public function test_acf_update_taxonomy_updates_existing() {
		$taxonomy          = acf_get_taxonomy( $this->taxonomy_id );
		$taxonomy['title'] = 'Updated Title';

		$result = acf_update_taxonomy( $taxonomy );

		$this->assertEquals( 'Updated Title', $result['title'], 'Title should be updated' );
		$this->assertEquals( $this->taxonomy_id, $result['ID'], 'Should update existing post' );
	}

	/**
	 * Test acf_delete_taxonomy deletes by ID.
	 */
	public function test_acf_delete_taxonomy_by_id() {
		$created = acf_update_taxonomy(
			array(
				'key'         => 'taxonomy_delete_' . uniqid(),
				'title'       => 'To Delete',
				'taxonomy'    => 'delete_tax',
				'object_type' => array( 'post' ),
				'active'      => true,
			)
		);

		$result = acf_delete_taxonomy( $created['ID'] );

		$this->assertTrue( $result, 'Should return true on successful delete' );
		$this->assertNull( get_post( $created['ID'] ), 'Post should be deleted' );
	}

	/**
	 * Test acf_delete_taxonomy returns false for invalid ID.
	 */
	public function test_acf_delete_taxonomy_returns_false_for_invalid_id() {
		$result = acf_delete_taxonomy( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test acf_trash_taxonomy trashes a taxonomy.
	 */
	public function test_acf_trash_taxonomy() {
		$created = acf_update_taxonomy(
			array(
				'key'         => 'taxonomy_trash_' . uniqid(),
				'title'       => 'To Trash',
				'taxonomy'    => 'trash_tax',
				'object_type' => array( 'post' ),
				'active'      => true,
			)
		);

		$result = acf_trash_taxonomy( $created['ID'] );

		$this->assertTrue( $result, 'Should return true on successful trash' );
		$post = get_post( $created['ID'] );
		$this->assertEquals( 'trash', $post->post_status, 'Post should be trashed' );
	}

	/**
	 * Test acf_untrash_taxonomy restores a trashed taxonomy.
	 */
	public function test_acf_untrash_taxonomy() {
		$created = acf_update_taxonomy(
			array(
				'key'         => 'taxonomy_untrash_' . uniqid(),
				'title'       => 'To Untrash',
				'taxonomy'    => 'untrash_tax',
				'object_type' => array( 'post' ),
				'active'      => true,
			)
		);

		// Trash it first.
		acf_trash_taxonomy( $created['ID'] );

		$result = acf_untrash_taxonomy( $created['ID'] );

		$this->assertTrue( $result, 'Should return true on successful untrash' );
		$post = get_post( $created['ID'] );
		// Note: WordPress may restore to 'draft' or 'publish' depending on stored status.
		$this->assertContains( $post->post_status, array( 'publish', 'draft' ), 'Post should be restored from trash' );
	}

	/**
	 * Test acf_is_taxonomy returns true for valid taxonomy array.
	 */
	public function test_acf_is_taxonomy_returns_true_for_valid_array() {
		$taxonomy = $this->make_taxonomy_array();
		$result   = acf_is_taxonomy( $taxonomy );

		$this->assertTrue( $result, 'Should return true for valid taxonomy array' );
	}

	/**
	 * Test acf_is_taxonomy returns false for invalid array.
	 */
	public function test_acf_is_taxonomy_returns_false_for_invalid_array() {
		$result = acf_is_taxonomy( array( 'invalid' => 'data' ) );

		$this->assertFalse( $result, 'Should return false for invalid array' );
	}

	/**
	 * Test acf_duplicate_taxonomy creates a copy.
	 */
	public function test_acf_duplicate_taxonomy() {
		$result = acf_duplicate_taxonomy( $this->taxonomy_id );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
		$this->assertNotEquals( $this->taxonomy_key, $result['key'], 'Should have different key' );
		$this->assertStringStartsWith( 'taxonomy_', $result['key'], 'Key should start with taxonomy_' );
	}

	/**
	 * Test acf_duplicate_taxonomy includes (copy) in title.
	 */
	public function test_acf_duplicate_taxonomy_appends_copy_to_title() {
		$result = acf_duplicate_taxonomy( $this->taxonomy_id );

		$this->assertStringContainsString( '(copy)', $result['title'], 'Title should contain (copy)' );
	}

	/**
	 * Test acf_duplicate_taxonomy returns false for invalid ID.
	 */
	public function test_acf_duplicate_taxonomy_returns_false_for_invalid_id() {
		$result = acf_duplicate_taxonomy( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test acf_update_taxonomy_active_status activates.
	 */
	public function test_acf_update_taxonomy_active_status_activates() {
		// Create an inactive taxonomy first.
		$created = acf_update_taxonomy(
			array(
				'key'         => 'taxonomy_activate_' . uniqid(),
				'title'       => 'To Activate',
				'taxonomy'    => 'activate_tax',
				'object_type' => array( 'post' ),
				'active'      => false,
			)
		);

		$result = acf_update_taxonomy_active_status( $created['ID'], true );

		$this->assertTrue( $result, 'Should return true on success' );
		// Use raw function to bypass store cache which may have stale data.
		$taxonomy = acf_get_raw_taxonomy( $created['ID'] );
		$this->assertTrue( $taxonomy['active'], 'Taxonomy should be active' );
	}

	/**
	 * Test acf_update_taxonomy_active_status deactivates.
	 */
	public function test_acf_update_taxonomy_active_status_deactivates() {
		// Create an active taxonomy first.
		$created = acf_update_taxonomy(
			array(
				'key'         => 'taxonomy_deactivate_' . uniqid(),
				'title'       => 'To Deactivate',
				'taxonomy'    => 'deactivate_tax',
				'object_type' => array( 'post' ),
				'active'      => true,
			)
		);

		$result = acf_update_taxonomy_active_status( $created['ID'], false );

		$this->assertTrue( $result, 'Should return true on success' );
		// Use raw function to bypass store cache which may have stale data.
		$taxonomy = acf_get_raw_taxonomy( $created['ID'] );
		$this->assertFalse( $taxonomy['active'], 'Taxonomy should be inactive' );
	}

	/**
	 * Test acf_get_taxonomy_edit_link returns string.
	 */
	public function test_acf_get_taxonomy_edit_link_returns_string() {
		// Set up admin user for permissions.
		$admin_id = wp_insert_user(
			array(
				'user_login' => 'admin_test_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );

		$result = acf_get_taxonomy_edit_link( $this->taxonomy_id );

		$this->assertIsString( $result, 'Should return string' );

		// Clean up user.
		wp_delete_user( $admin_id );
	}

	/**
	 * Test acf_prepare_taxonomy_for_export removes internal keys.
	 */
	public function test_acf_prepare_taxonomy_for_export() {
		$taxonomy = acf_get_taxonomy( $this->taxonomy_id );
		$result   = acf_prepare_taxonomy_for_export( $taxonomy );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayNotHasKey( 'ID', $result, 'Should not have ID' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
	}

	/**
	 * Test acf_export_taxonomy_as_php returns PHP code.
	 */
	public function test_acf_export_taxonomy_as_php() {
		$taxonomy = $this->make_taxonomy_array();
		$result   = acf_export_taxonomy_as_php( $taxonomy );

		$this->assertIsString( $result, 'Should return string' );
		// The export uses register_taxonomy, not acf_register_taxonomy.
		$this->assertStringContainsString( 'register_taxonomy', $result, 'Should contain registration function' );
	}

	/**
	 * Test acf_prepare_taxonomy_for_import validates array.
	 */
	public function test_acf_prepare_taxonomy_for_import() {
		$taxonomy = $this->make_taxonomy_array();
		$result   = acf_prepare_taxonomy_for_import( $taxonomy );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
	}

	/**
	 * Test acf_import_taxonomy creates a new taxonomy.
	 */
	public function test_acf_import_taxonomy() {
		$taxonomy = $this->make_taxonomy_array(
			array(
				'key'      => 'taxonomy_import_' . uniqid(),
				'title'    => 'Imported Taxonomy',
				'taxonomy' => 'imported_tax',
			)
		);

		$result = acf_import_taxonomy( $taxonomy );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'ID', $result, 'Should have ID' );
	}

	/**
	 * Test acf_flush_taxonomy_cache runs without error.
	 */
	public function test_acf_flush_taxonomy_cache() {
		$taxonomy = acf_get_taxonomy( $this->taxonomy_id );

		// Should not throw any errors.
		acf_flush_taxonomy_cache( $taxonomy );

		// Just verify the function runs without error.
		$this->assertTrue( true, 'Cache flush should complete without error' );
	}

	/**
	 * Test taxonomy CRUD operations in sequence.
	 */
	public function test_taxonomy_crud_sequence() {
		// Create.
		$key      = 'taxonomy_crud_' . uniqid();
		$taxonomy = $this->make_taxonomy_array(
			array(
				'key'      => $key,
				'title'    => 'CRUD Test Taxonomy',
				'taxonomy' => 'crud_tax',
			)
		);

		$created = acf_update_taxonomy( $taxonomy );
		$this->assertIsArray( $created, 'Create should return array' );
		$this->assertArrayHasKey( 'ID', $created, 'Created should have ID' );

		$post_id = $created['ID'];

		// Read.
		$read = acf_get_taxonomy( $post_id );
		$this->assertEquals( $key, $read['key'], 'Read should return same key' );

		// Update.
		$read['title'] = 'Updated CRUD Test';
		$updated       = acf_update_taxonomy( $read );
		$this->assertEquals( 'Updated CRUD Test', $updated['title'], 'Update should change title' );

		// Delete.
		$deleted = acf_delete_taxonomy( $post_id );
		$this->assertTrue( $deleted, 'Delete should return true' );

		// Verify deleted - check the actual WP post is gone.
		$verify = get_post( $post_id );
		$this->assertNull( $verify, 'WP Post should not exist after delete' );
	}

	/**
	 * Test taxonomy with object_type array.
	 */
	public function test_taxonomy_with_object_type() {
		$taxonomy = $this->make_taxonomy_array(
			array(
				'key'         => 'taxonomy_objects_' . uniqid(),
				'title'       => 'Multi Object Taxonomy',
				'taxonomy'    => 'multi_tax',
				'object_type' => array( 'post', 'page', 'custom_cpt' ),
			)
		);

		$result = acf_update_taxonomy( $taxonomy );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'object_type', $result, 'Should have object_type' );
		$this->assertIsArray( $result['object_type'], 'object_type should be array' );
	}

	/**
	 * Test taxonomy hierarchical setting.
	 */
	public function test_taxonomy_hierarchical_setting() {
		$taxonomy = $this->make_taxonomy_array(
			array(
				'key'          => 'taxonomy_hier_' . uniqid(),
				'title'        => 'Hierarchical Taxonomy',
				'taxonomy'     => 'hier_tax',
				'hierarchical' => true,
			)
		);

		$result = acf_update_taxonomy( $taxonomy );

		$this->assertIsArray( $result, 'Should return array' );
	}

	/**
	 * Test taxonomy REST API settings.
	 */
	public function test_taxonomy_rest_api_settings() {
		$taxonomy = $this->make_taxonomy_array(
			array(
				'key'            => 'taxonomy_rest_' . uniqid(),
				'title'          => 'REST Enabled Taxonomy',
				'taxonomy'       => 'rest_tax',
				'show_in_rest'   => true,
				'rest_base'      => 'custom-rest-base',
				'rest_namespace' => 'wp/v2',
			)
		);

		$result = acf_update_taxonomy( $taxonomy );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertTrue( $result['show_in_rest'], 'Should have REST enabled' );
	}

	/**
	 * Test taxonomy capabilities setting.
	 */
	public function test_taxonomy_capabilities() {
		$taxonomy = $this->make_taxonomy_array(
			array(
				'key'          => 'taxonomy_caps_' . uniqid(),
				'title'        => 'Custom Caps Taxonomy',
				'taxonomy'     => 'caps_tax',
				'capabilities' => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);

		$result = acf_update_taxonomy( $taxonomy );

		$this->assertIsArray( $result, 'Should return array' );
	}

	/**
	 * Test taxonomy labels are preserved.
	 */
	public function test_taxonomy_labels_preserved() {
		$taxonomy = $this->make_taxonomy_array(
			array(
				'key'      => 'taxonomy_labels_' . uniqid(),
				'title'    => 'Labeled Taxonomy',
				'taxonomy' => 'labeled_tax',
				'labels'   => array(
					'name'          => 'Genres',
					'singular_name' => 'Genre',
					'menu_name'     => 'Genres Menu',
					'all_items'     => 'All Genres',
					'edit_item'     => 'Edit Genre',
					'view_item'     => 'View Genre',
					'add_new_item'  => 'Add New Genre',
					'search_items'  => 'Search Genres',
					'not_found'     => 'No genres found',
				),
			)
		);

		$result = acf_update_taxonomy( $taxonomy );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'labels', $result, 'Should have labels' );
		$this->assertEquals( 'Genres', $result['labels']['name'], 'Label name should be preserved' );
		$this->assertEquals( 'Genre', $result['labels']['singular_name'], 'Singular name should be preserved' );
	}
}
