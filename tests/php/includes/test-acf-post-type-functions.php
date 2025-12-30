<?php
/**
 * Tests for ACF post type functions.
 *
 * Tests functions in includes/acf-post-type-functions.php that provide
 * the public API for managing custom post types created through SCF.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test ACF post type functions.
 *
 * @covers acf_get_post_type
 * @covers acf_get_raw_post_type
 * @covers acf_get_post_type_post
 * @covers acf_is_post_type_key
 * @covers acf_validate_post_type
 * @covers acf_translate_post_type
 * @covers acf_get_acf_post_types
 * @covers acf_get_raw_post_types
 * @covers acf_filter_post_types
 * @covers acf_update_post_type
 * @covers acf_flush_post_type_cache
 * @covers acf_delete_post_type
 * @covers acf_trash_post_type
 * @covers acf_untrash_post_type
 * @covers acf_is_post_type
 * @covers acf_duplicate_post_type
 * @covers acf_update_post_type_active_status
 * @covers acf_get_post_type_edit_link
 * @covers acf_prepare_post_type_for_export
 * @covers acf_export_post_type_as_php
 * @covers acf_prepare_post_type_for_import
 * @covers acf_import_post_type
 * @covers acf_export_enter_title_here
 */
class Test_ACF_Post_Type_Functions extends BaseTestCase {

	/**
	 * Test post type ID.
	 *
	 * @var int
	 */
	private $post_type_id;

	/**
	 * Test post type key.
	 *
	 * @var string
	 */
	private $post_type_key;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Ensure ACF internal post type instances are initialized.
		// This is required for the public API functions to work properly.
		// WorDBless fires plugins_loaded and init BEFORE our plugin is loaded,
		// so we need to fire them again to run our plugin's callbacks.
		if ( ! acf_get_internal_post_type_instance( 'acf-post-type' ) ) {
			do_action( 'plugins_loaded' );
			do_action( 'init' );
		}

		$this->post_type_key = 'post_type_test_' . uniqid();

		// Create a test post type using ACF functions for proper setup.
		$post_type = acf_update_post_type(
			array(
				'key'       => $this->post_type_key,
				'title'     => 'Test Post Type',
				'post_type' => 'test_cpt',
				'active'    => true,
			)
		);

		$this->post_type_id = $post_type['ID'];
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		// Clean up all test posts.
		$posts = get_posts(
			array(
				'post_type'   => 'acf-post-type',
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
	 * Helper to create a post type array.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array Post type data.
	 */
	private function make_post_type_array( array $overrides = array() ) {
		$defaults = array(
			'key'          => 'post_type_' . uniqid(),
			'title'        => 'Test Post Type',
			'post_type'    => 'test_cpt',
			'active'       => true,
			'labels'       => array(
				'name'          => 'Test CPTs',
				'singular_name' => 'Test CPT',
			),
			'public'       => true,
			'show_in_rest' => true,
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Test acf_get_post_type returns post type data by ID.
	 */
	public function test_acf_get_post_type_by_id() {
		$result = acf_get_post_type( $this->post_type_id );

		$this->assertIsArray( $result, 'Should return array for valid ID' );
		$this->assertEquals( $this->post_type_key, $result['key'], 'Should return correct key' );
	}

	/**
	 * Test acf_get_post_type returns post type data by key.
	 *
	 * Note: We first call acf_get_post_type by ID to populate the store cache,
	 * which creates a key alias. This allows key-based lookup to work via cache
	 * instead of get_posts() which WorDBless doesn't fully support.
	 */
	public function test_acf_get_post_type_by_key() {
		// Populate store cache by fetching via ID first (creates key alias).
		acf_get_post_type( $this->post_type_id );

		$result = acf_get_post_type( $this->post_type_key );

		$this->assertIsArray( $result, 'Should return array for valid key' );
		$this->assertEquals( $this->post_type_key, $result['key'], 'Should return correct key' );
	}

	/**
	 * Test acf_get_post_type returns false for invalid ID.
	 */
	public function test_acf_get_post_type_returns_false_for_invalid_id() {
		$result = acf_get_post_type( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test acf_get_raw_post_type returns raw post type data.
	 */
	public function test_acf_get_raw_post_type() {
		$result = acf_get_raw_post_type( $this->post_type_id );

		$this->assertIsArray( $result, 'Should return array for valid ID' );
		$this->assertEquals( $this->post_type_key, $result['key'], 'Should return correct key' );
	}

	/**
	 * Test acf_get_raw_post_type returns false for invalid ID.
	 */
	public function test_acf_get_raw_post_type_returns_false_for_invalid_id() {
		$result = acf_get_raw_post_type( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test acf_get_post_type_post returns WP_Post object.
	 */
	public function test_acf_get_post_type_post_returns_post_object() {
		$result = acf_get_post_type_post( $this->post_type_id );

		$this->assertInstanceOf( WP_Post::class, $result, 'Should return WP_Post object' );
		$this->assertEquals( $this->post_type_id, $result->ID, 'Should return correct post ID' );
	}

	/**
	 * Test acf_get_post_type_post returns false for invalid ID.
	 */
	public function test_acf_get_post_type_post_returns_false_for_invalid_id() {
		$result = acf_get_post_type_post( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test acf_is_post_type_key returns true for valid key.
	 */
	public function test_acf_is_post_type_key_returns_true_for_valid_key() {
		$result = acf_is_post_type_key( 'post_type_abc123' );

		$this->assertTrue( $result, 'Should return true for valid post_type_ prefix key' );
	}

	/**
	 * Test acf_is_post_type_key returns false for invalid key.
	 */
	public function test_acf_is_post_type_key_returns_false_for_invalid_key() {
		$result = acf_is_post_type_key( 'group_abc123' );

		$this->assertFalse( $result, 'Should return false for non-post_type key' );
	}

	/**
	 * Test acf_is_post_type_key returns false for non-string.
	 */
	public function test_acf_is_post_type_key_returns_false_for_non_string() {
		$result = acf_is_post_type_key( 12345 );

		$this->assertFalse( $result, 'Should return false for non-string' );
	}

	/**
	 * Test acf_validate_post_type validates a post type array.
	 */
	public function test_acf_validate_post_type_with_valid_array() {
		$post_type = $this->make_post_type_array();
		$result    = acf_validate_post_type( $post_type );

		$this->assertIsArray( $result, 'Should return validated array' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
		$this->assertArrayHasKey( 'title', $result, 'Should have title' );
	}

	/**
	 * Test acf_validate_post_type with empty array.
	 */
	public function test_acf_validate_post_type_with_empty_array() {
		$result = acf_validate_post_type( array() );

		$this->assertIsArray( $result, 'Should return array even for empty input' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key with default value' );
	}

	/**
	 * Test acf_translate_post_type translates labels.
	 */
	public function test_acf_translate_post_type_translates_labels() {
		$post_type = $this->make_post_type_array(
			array(
				'labels' => array(
					'name'          => 'Books',
					'singular_name' => 'Book',
				),
			)
		);

		$result = acf_translate_post_type( $post_type );

		$this->assertIsArray( $result, 'Should return translated array' );
		$this->assertArrayHasKey( 'labels', $result, 'Should have labels' );
	}

	/**
	 * Test acf_get_acf_post_types returns all post types.
	 */
	public function test_acf_get_acf_post_types_returns_array() {
		$result = acf_get_acf_post_types();

		$this->assertIsArray( $result, 'Should return array' );
	}

	/**
	 * Test acf_get_acf_post_types with active filter.
	 */
	public function test_acf_get_acf_post_types_with_active_filter() {
		// Create an inactive post type.
		acf_update_post_type(
			array(
				'key'       => 'post_type_inactive_' . uniqid(),
				'title'     => 'Inactive Post Type',
				'post_type' => 'inactive_cpt',
				'active'    => false,
			)
		);

		$active_result   = acf_get_acf_post_types( array( 'active' => true ) );
		$inactive_result = acf_get_acf_post_types( array( 'active' => false ) );

		$this->assertIsArray( $active_result, 'Active filter should return array' );
		$this->assertIsArray( $inactive_result, 'Inactive filter should return array' );
	}

	/**
	 * Test acf_get_raw_post_types returns raw post types.
	 */
	public function test_acf_get_raw_post_types_returns_array() {
		$result = acf_get_raw_post_types();

		$this->assertIsArray( $result, 'Should return array' );
	}

	/**
	 * Test acf_filter_post_types filters by active status.
	 */
	public function test_acf_filter_post_types_by_active_status() {
		$post_types = array(
			$this->make_post_type_array(
				array(
					'key'    => 'post_type_active_1',
					'active' => true,
				)
			),
			$this->make_post_type_array(
				array(
					'key'    => 'post_type_inactive_1',
					'active' => false,
				)
			),
			$this->make_post_type_array(
				array(
					'key'    => 'post_type_active_2',
					'active' => true,
				)
			),
		);

		$active_result = acf_filter_post_types( $post_types, array( 'active' => true ) );

		$this->assertCount( 2, $active_result, 'Should return only active post types' );
		foreach ( $active_result as $pt ) {
			$this->assertTrue( $pt['active'], 'All filtered post types should be active' );
		}
	}

	/**
	 * Test acf_filter_post_types returns all when no filter.
	 */
	public function test_acf_filter_post_types_returns_all_without_filter() {
		$post_types = array(
			$this->make_post_type_array(
				array(
					'key'    => 'post_type_1',
					'active' => true,
				)
			),
			$this->make_post_type_array(
				array(
					'key'    => 'post_type_2',
					'active' => false,
				)
			),
		);

		$result = acf_filter_post_types( $post_types, array() );

		$this->assertCount( 2, $result, 'Should return all post types without filter' );
	}

	/**
	 * Test acf_update_post_type creates a new post type.
	 */
	public function test_acf_update_post_type_creates_new() {
		$post_type = $this->make_post_type_array(
			array(
				'key'       => 'post_type_new_' . uniqid(),
				'title'     => 'New Test Post Type',
				'post_type' => 'new_cpt',
			)
		);

		$result = acf_update_post_type( $post_type );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'ID', $result, 'Should have ID' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
	}

	/**
	 * Test acf_update_post_type updates existing post type.
	 */
	public function test_acf_update_post_type_updates_existing() {
		$post_type          = acf_get_post_type( $this->post_type_id );
		$post_type['title'] = 'Updated Title';

		$result = acf_update_post_type( $post_type );

		$this->assertEquals( 'Updated Title', $result['title'], 'Title should be updated' );
		$this->assertEquals( $this->post_type_id, $result['ID'], 'Should update existing post' );
	}

	/**
	 * Test acf_delete_post_type deletes by ID.
	 */
	public function test_acf_delete_post_type_by_id() {
		$created = acf_update_post_type(
			array(
				'key'       => 'post_type_delete_' . uniqid(),
				'title'     => 'To Delete',
				'post_type' => 'delete_cpt',
				'active'    => true,
			)
		);

		$result = acf_delete_post_type( $created['ID'] );

		$this->assertTrue( $result, 'Should return true on successful delete' );
		$this->assertNull( get_post( $created['ID'] ), 'Post should be deleted' );
	}

	/**
	 * Test acf_delete_post_type returns false for invalid ID.
	 */
	public function test_acf_delete_post_type_returns_false_for_invalid_id() {
		$result = acf_delete_post_type( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test acf_trash_post_type trashes a post type.
	 */
	public function test_acf_trash_post_type() {
		$created = acf_update_post_type(
			array(
				'key'       => 'post_type_trash_' . uniqid(),
				'title'     => 'To Trash',
				'post_type' => 'trash_cpt',
				'active'    => true,
			)
		);

		$result = acf_trash_post_type( $created['ID'] );

		$this->assertTrue( $result, 'Should return true on successful trash' );
		$post = get_post( $created['ID'] );
		$this->assertEquals( 'trash', $post->post_status, 'Post should be trashed' );
	}

	/**
	 * Test acf_untrash_post_type restores a trashed post type.
	 */
	public function test_acf_untrash_post_type() {
		$created = acf_update_post_type(
			array(
				'key'       => 'post_type_untrash_' . uniqid(),
				'title'     => 'To Untrash',
				'post_type' => 'untrash_cpt',
				'active'    => true,
			)
		);

		// Trash it first.
		acf_trash_post_type( $created['ID'] );

		$result = acf_untrash_post_type( $created['ID'] );

		$this->assertTrue( $result, 'Should return true on successful untrash' );
		$post = get_post( $created['ID'] );
		// Note: WordPress may restore to 'draft' or 'publish' depending on stored status.
		$this->assertContains( $post->post_status, array( 'publish', 'draft' ), 'Post should be restored from trash' );
	}

	/**
	 * Test acf_is_post_type returns true for valid post type array.
	 */
	public function test_acf_is_post_type_returns_true_for_valid_array() {
		$post_type = $this->make_post_type_array();
		$result    = acf_is_post_type( $post_type );

		$this->assertTrue( $result, 'Should return true for valid post type array' );
	}

	/**
	 * Test acf_is_post_type returns false for invalid array.
	 */
	public function test_acf_is_post_type_returns_false_for_invalid_array() {
		$result = acf_is_post_type( array( 'invalid' => 'data' ) );

		$this->assertFalse( $result, 'Should return false for invalid array' );
	}

	/**
	 * Test acf_duplicate_post_type creates a copy.
	 */
	public function test_acf_duplicate_post_type() {
		$result = acf_duplicate_post_type( $this->post_type_id );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
		$this->assertNotEquals( $this->post_type_key, $result['key'], 'Should have different key' );
		$this->assertStringStartsWith( 'post_type_', $result['key'], 'Key should start with post_type_' );
	}

	/**
	 * Test acf_duplicate_post_type includes (copy) in title.
	 */
	public function test_acf_duplicate_post_type_appends_copy_to_title() {
		$result = acf_duplicate_post_type( $this->post_type_id );

		$this->assertStringContainsString( '(copy)', $result['title'], 'Title should contain (copy)' );
	}

	/**
	 * Test acf_duplicate_post_type returns false for invalid ID.
	 */
	public function test_acf_duplicate_post_type_returns_false_for_invalid_id() {
		$result = acf_duplicate_post_type( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test acf_update_post_type_active_status activates.
	 */
	public function test_acf_update_post_type_active_status_activates() {
		// Create an inactive post type first.
		$created = acf_update_post_type(
			array(
				'key'       => 'post_type_activate_' . uniqid(),
				'title'     => 'To Activate',
				'post_type' => 'activate_cpt',
				'active'    => false,
			)
		);

		$result = acf_update_post_type_active_status( $created['ID'], true );

		$this->assertTrue( $result, 'Should return true on success' );
		// Use raw function to bypass store cache which may have stale data.
		$post_type = acf_get_raw_post_type( $created['ID'] );
		$this->assertTrue( $post_type['active'], 'Post type should be active' );
	}

	/**
	 * Test acf_update_post_type_active_status deactivates.
	 */
	public function test_acf_update_post_type_active_status_deactivates() {
		// Create an active post type first.
		$created = acf_update_post_type(
			array(
				'key'       => 'post_type_deactivate_' . uniqid(),
				'title'     => 'To Deactivate',
				'post_type' => 'deactivate_cpt',
				'active'    => true,
			)
		);

		$result = acf_update_post_type_active_status( $created['ID'], false );

		$this->assertTrue( $result, 'Should return true on success' );
		// Use raw function to bypass store cache which may have stale data.
		$post_type = acf_get_raw_post_type( $created['ID'] );
		$this->assertFalse( $post_type['active'], 'Post type should be inactive' );
	}

	/**
	 * Test acf_get_post_type_edit_link returns string.
	 */
	public function test_acf_get_post_type_edit_link_returns_string() {
		// Set up admin user for permissions.
		$admin_id = wp_insert_user(
			array(
				'user_login' => 'admin_test_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );

		$result = acf_get_post_type_edit_link( $this->post_type_id );

		$this->assertIsString( $result, 'Should return string' );

		// Clean up user.
		wp_delete_user( $admin_id );
	}

	/**
	 * Test acf_prepare_post_type_for_export removes internal keys.
	 */
	public function test_acf_prepare_post_type_for_export() {
		$post_type = acf_get_post_type( $this->post_type_id );
		$result    = acf_prepare_post_type_for_export( $post_type );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayNotHasKey( 'ID', $result, 'Should not have ID' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
	}

	/**
	 * Test acf_export_post_type_as_php returns PHP code.
	 */
	public function test_acf_export_post_type_as_php() {
		$post_type = $this->make_post_type_array();
		$result    = acf_export_post_type_as_php( $post_type );

		$this->assertIsString( $result, 'Should return string' );
		// The export uses register_post_type, not acf_register_post_type.
		$this->assertStringContainsString( 'register_post_type', $result, 'Should contain registration function' );
	}

	/**
	 * Test acf_prepare_post_type_for_import validates array.
	 */
	public function test_acf_prepare_post_type_for_import() {
		$post_type = $this->make_post_type_array();
		$result    = acf_prepare_post_type_for_import( $post_type );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
	}

	/**
	 * Test acf_import_post_type creates a new post type.
	 */
	public function test_acf_import_post_type() {
		$post_type = $this->make_post_type_array(
			array(
				'key'       => 'post_type_import_' . uniqid(),
				'title'     => 'Imported Post Type',
				'post_type' => 'imported_cpt',
			)
		);

		$result = acf_import_post_type( $post_type );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'ID', $result, 'Should have ID' );
	}

	/**
	 * Test acf_export_enter_title_here generates filter code.
	 */
	public function test_acf_export_enter_title_here_generates_code() {
		$post_types = array(
			$this->make_post_type_array(
				array(
					'post_type'        => 'book',
					'enter_title_here' => 'Enter book title',
				)
			),
		);

		$result = acf_export_enter_title_here( $post_types );

		$this->assertIsString( $result, 'Should return string' );
		$this->assertStringContainsString( 'add_filter', $result, 'Should contain add_filter' );
		$this->assertStringContainsString( 'enter_title_here', $result, 'Should contain filter name' );
		$this->assertStringContainsString( 'book', $result, 'Should contain post type' );
		$this->assertStringContainsString( 'Enter book title', $result, 'Should contain title text' );
	}

	/**
	 * Test acf_export_enter_title_here with multiple post types.
	 */
	public function test_acf_export_enter_title_here_with_multiple_post_types() {
		$post_types = array(
			$this->make_post_type_array(
				array(
					'post_type'        => 'book',
					'enter_title_here' => 'Enter book title',
				)
			),
			$this->make_post_type_array(
				array(
					'post_type'        => 'movie',
					'enter_title_here' => 'Enter movie title',
				)
			),
		);

		$result = acf_export_enter_title_here( $post_types );

		$this->assertStringContainsString( 'book', $result, 'Should contain book post type' );
		$this->assertStringContainsString( 'movie', $result, 'Should contain movie post type' );
		$this->assertStringContainsString( 'switch', $result, 'Should contain switch statement' );
	}

	/**
	 * Test acf_export_enter_title_here returns empty for no enter_title_here.
	 */
	public function test_acf_export_enter_title_here_returns_empty_without_titles() {
		$post_types = array(
			$this->make_post_type_array(
				array(
					'post_type'        => 'book',
					'enter_title_here' => '',
				)
			),
		);

		$result = acf_export_enter_title_here( $post_types );

		$this->assertEmpty( $result, 'Should return empty string when no enter_title_here' );
	}

	/**
	 * Test acf_export_enter_title_here with empty array.
	 */
	public function test_acf_export_enter_title_here_with_empty_array() {
		$result = acf_export_enter_title_here( array() );

		$this->assertEmpty( $result, 'Should return empty string for empty array' );
	}

	/**
	 * Test acf_flush_post_type_cache runs without error.
	 */
	public function test_acf_flush_post_type_cache() {
		$post_type = acf_get_post_type( $this->post_type_id );

		// Should not throw any errors.
		acf_flush_post_type_cache( $post_type );

		// Just verify the function runs without error.
		$this->assertTrue( true, 'Cache flush should complete without error' );
	}

	/**
	 * Test post type CRUD operations in sequence.
	 */
	public function test_post_type_crud_sequence() {
		// Create.
		$key       = 'post_type_crud_' . uniqid();
		$post_type = $this->make_post_type_array(
			array(
				'key'       => $key,
				'title'     => 'CRUD Test Post Type',
				'post_type' => 'crud_cpt',
			)
		);

		$created = acf_update_post_type( $post_type );
		$this->assertIsArray( $created, 'Create should return array' );
		$this->assertArrayHasKey( 'ID', $created, 'Created should have ID' );

		$post_id = $created['ID'];

		// Read.
		$read = acf_get_post_type( $post_id );
		$this->assertEquals( $key, $read['key'], 'Read should return same key' );

		// Update.
		$read['title'] = 'Updated CRUD Test';
		$updated       = acf_update_post_type( $read );
		$this->assertEquals( 'Updated CRUD Test', $updated['title'], 'Update should change title' );

		// Delete.
		$deleted = acf_delete_post_type( $post_id );
		$this->assertTrue( $deleted, 'Delete should return true' );

		// Verify deleted - check the actual WP post is gone.
		$verify = get_post( $post_id );
		$this->assertNull( $verify, 'WP Post should not exist after delete' );
	}
}
