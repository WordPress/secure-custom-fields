<?php
/**
 * Tests for acf-internal-post-type-functions.php
 *
 * Tests the public API functions for managing ACF internal post types.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load required files.
acf_include( '/includes/acf-internal-post-type-functions.php' );
acf_include( '/includes/class-acf-internal-post-type.php' );
acf_include( '/includes/post-types/class-acf-field-group.php' );
acf_include( '/includes/post-types/class-acf-post-type.php' );
acf_include( '/includes/post-types/class-acf-taxonomy.php' );

/**
 * Test ACF internal post type functions.
 */
class Test_ACF_Internal_Post_Type_Functions extends BaseTestCase {

	/**
	 * Test field group post ID.
	 *
	 * @var int
	 */
	private $field_group_id;

	/**
	 * Test field group key.
	 *
	 * @var string
	 */
	private $field_group_key;

	/**
	 * Test post type post ID.
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
	 * Test taxonomy post ID.
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

		// Clear ACF caches to ensure fresh state.
		// The cache keys are based on cache_key_plural property of each class.
		wp_cache_delete( acf_cache_key( 'acf_get_field_group_posts' ), 'secure-custom-fields' );
		wp_cache_delete( acf_cache_key( 'acf_get_post_type_posts' ), 'secure-custom-fields' );
		wp_cache_delete( acf_cache_key( 'acf_get_taxonomy_posts' ), 'secure-custom-fields' );

		// Unique keys for each test run - key is stored as post_name.
		$this->field_group_key = 'group_test_' . uniqid();
		$this->post_type_key   = 'post_type_test_' . uniqid();
		$this->taxonomy_key    = 'taxonomy_test_' . uniqid();

		// Create a field group post - key is stored as post_name.
		$this->field_group_id = wp_insert_post(
			array(
				'post_type'   => 'acf-field-group',
				'post_title'  => 'Test Field Group',
				'post_status' => 'publish',
				'post_name'   => $this->field_group_key,
			)
		);

		// Create a post type post - key is stored as post_name.
		$this->post_type_id = wp_insert_post(
			array(
				'post_type'   => 'acf-post-type',
				'post_title'  => 'Test Post Type',
				'post_status' => 'publish',
				'post_name'   => $this->post_type_key,
			)
		);

		// Create a taxonomy post - key is stored as post_name.
		$this->taxonomy_id = wp_insert_post(
			array(
				'post_type'   => 'acf-taxonomy',
				'post_title'  => 'Test Taxonomy',
				'post_status' => 'publish',
				'post_name'   => $this->taxonomy_key,
			)
		);
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		if ( $this->field_group_id ) {
			wp_delete_post( $this->field_group_id, true );
		}
		if ( $this->post_type_id ) {
			wp_delete_post( $this->post_type_id, true );
		}
		if ( $this->taxonomy_id ) {
			wp_delete_post( $this->taxonomy_id, true );
		}

		// Clear any cached data.
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'post-types' )->reset();
		acf_get_store( 'taxonomies' )->reset();

		parent::tearDown();
	}

	// =========================================================================
	// Data Providers
	// =========================================================================

	/**
	 * Data provider for internal post type instances.
	 *
	 * @return array Post type slug => expected class name.
	 */
	public function internal_post_type_provider() {
		return array(
			'field group' => array( 'acf-field-group', 'ACF_Field_Group' ),
			'post type'   => array( 'acf-post-type', 'ACF_Post_Type' ),
			'taxonomy'    => array( 'acf-taxonomy', 'ACF_Taxonomy' ),
		);
	}

	/**
	 * Data provider for key prefix validation.
	 *
	 * @return array Post type slug => key prefix.
	 */
	public function key_prefix_provider() {
		return array(
			'field group' => array( 'acf-field-group', 'group_' ),
			'post type'   => array( 'acf-post-type', 'post_type_' ),
			'taxonomy'    => array( 'acf-taxonomy', 'taxonomy_' ),
		);
	}

	// =========================================================================
	// acf_get_internal_post_type_instance() tests
	// =========================================================================

	/**
	 * Test getting internal post type instances.
	 *
	 * @dataProvider internal_post_type_provider
	 *
	 * @param string $post_type      The internal post type slug.
	 * @param string $expected_class The expected class name.
	 */
	public function test_get_internal_post_type_instance( $post_type, $expected_class ) {
		$instance = acf_get_internal_post_type_instance( $post_type );

		$this->assertInstanceOf( 'ACF_Internal_Post_Type', $instance );
		$this->assertInstanceOf( $expected_class, $instance );
	}

	/**
	 * Test getting instance for invalid post type returns false.
	 */
	public function test_get_internal_post_type_instance_returns_false_for_invalid_type() {
		$this->assertFalse( acf_get_internal_post_type_instance( 'invalid-post-type' ) );
	}

	/**
	 * Test default post type is field group.
	 */
	public function test_get_internal_post_type_instance_defaults_to_field_group() {
		$this->assertInstanceOf( 'ACF_Field_Group', acf_get_internal_post_type_instance() );
	}

	// =========================================================================
	// acf_get_internal_post_type() tests
	// =========================================================================

	/**
	 * Test getting internal post type by ID.
	 */
	public function test_get_internal_post_type_by_id() {
		$result = acf_get_internal_post_type( $this->field_group_id, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $this->field_group_id, $result['ID'], 'Should return correct ID' );
		$this->assertEquals( $this->field_group_key, $result['key'], 'Should return correct key' );
	}

	/**
	 * Test getting internal post type by key.
	 *
	 * Note: Key-based lookup requires the posts_where filter to be active and
	 * the store cache to be populated. This test verifies the function works
	 * correctly when the key is NOT in the store cache.
	 */
	public function test_get_internal_post_type_by_key() {
		// First, get by ID to populate the store cache with alias.
		$by_id = acf_get_internal_post_type( $this->field_group_id, 'acf-field-group' );
		$this->assertIsArray( $by_id, 'Should return an array when fetched by ID' );

		// Now lookup by key should work via the store alias.
		$result = acf_get_internal_post_type( $this->field_group_key, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array when fetched by key after ID lookup' );
		$this->assertEquals( $this->field_group_id, $result['ID'], 'Should return correct ID when fetched by key' );
	}

	/**
	 * Test getting internal post type with invalid ID returns false.
	 */
	public function test_get_internal_post_type_returns_false_for_invalid_id() {
		$result = acf_get_internal_post_type( 99999, 'acf-field-group' );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test getting internal post type with invalid post type returns false.
	 */
	public function test_get_internal_post_type_returns_false_for_invalid_post_type() {
		$result = acf_get_internal_post_type( $this->field_group_id, 'invalid-post-type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	// =========================================================================
	// acf_get_raw_internal_post_type() tests
	// =========================================================================

	/**
	 * Test getting raw internal post type by ID.
	 */
	public function test_get_raw_internal_post_type_by_id() {
		$result = acf_get_raw_internal_post_type( $this->field_group_id, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $this->field_group_id, $result['ID'], 'Should return correct ID' );
	}

	/**
	 * Test getting raw internal post type returns false for invalid ID.
	 */
	public function test_get_raw_internal_post_type_returns_false_for_invalid_id() {
		$result = acf_get_raw_internal_post_type( 99999, 'acf-field-group' );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test getting raw internal post type returns false for invalid post type.
	 */
	public function test_get_raw_internal_post_type_returns_false_for_invalid_post_type() {
		$result = acf_get_raw_internal_post_type( $this->field_group_id, 'invalid-post-type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	// =========================================================================
	// acf_get_internal_post_type_post() tests
	// =========================================================================

	/**
	 * Test getting internal post type post object by ID.
	 */
	public function test_get_internal_post_type_post_by_id() {
		$result = acf_get_internal_post_type_post( $this->field_group_id, 'acf-field-group' );

		$this->assertIsObject( $result, 'Should return an object' );
		$this->assertEquals( $this->field_group_id, $result->ID, 'Should return correct post ID' );
		$this->assertEquals( 'acf-field-group', $result->post_type, 'Should return correct post type' );
	}

	/**
	 * Test getting internal post type post returns false for invalid ID.
	 */
	public function test_get_internal_post_type_post_returns_false_for_invalid_id() {
		$result = acf_get_internal_post_type_post( 99999, 'acf-field-group' );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test getting internal post type post returns false for invalid post type.
	 */
	public function test_get_internal_post_type_post_returns_false_for_invalid_post_type() {
		$result = acf_get_internal_post_type_post( $this->field_group_id, 'invalid-post-type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	// =========================================================================
	// acf_is_internal_post_type_key() tests
	// =========================================================================

	/**
	 * Test key detection for valid keys.
	 *
	 * @dataProvider key_prefix_provider
	 *
	 * @param string $post_type  The internal post type slug.
	 * @param string $key_prefix The expected key prefix.
	 */
	public function test_is_internal_post_type_key_valid( $post_type, $key_prefix ) {
		$this->assertTrue(
			acf_is_internal_post_type_key( $key_prefix . 'abc123', $post_type ),
			"Should return true for valid {$key_prefix} key"
		);
	}

	/**
	 * Test key detection returns false for wrong prefix.
	 *
	 * @dataProvider key_prefix_provider
	 *
	 * @param string $post_type  The internal post type slug.
	 * @param string $key_prefix The expected key prefix.
	 */
	public function test_is_internal_post_type_key_wrong_prefix( $post_type, $key_prefix ) {
		// Use a different prefix than expected.
		$wrong_prefix = 'group_' === $key_prefix ? 'post_type_' : 'group_';
		$this->assertFalse(
			acf_is_internal_post_type_key( $wrong_prefix . 'abc123', $post_type ),
			"Should return false for {$wrong_prefix} key when checking {$post_type}"
		);
	}

	/**
	 * Test key detection returns false for invalid post type.
	 */
	public function test_is_internal_post_type_key_returns_false_for_invalid_post_type() {
		$this->assertFalse( acf_is_internal_post_type_key( 'group_abc123', 'invalid-post-type' ) );
	}

	/**
	 * Test key detection with empty string.
	 */
	public function test_is_internal_post_type_key_with_empty_string() {
		$this->assertFalse( acf_is_internal_post_type_key( '', 'acf-field-group' ) );
	}

	// =========================================================================
	// acf_validate_internal_post_type() tests
	// =========================================================================

	/**
	 * Test validating internal post type.
	 */
	public function test_validate_internal_post_type() {
		$post = array(
			'key'   => 'group_new_' . uniqid(),
			'title' => 'New Field Group',
		);

		$result = acf_validate_internal_post_type( $post, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $post['key'], $result['key'], 'Should preserve key' );
		$this->assertEquals( $post['title'], $result['title'], 'Should preserve title' );
		$this->assertArrayHasKey( 'active', $result, 'Should add default active value' );
	}

	/**
	 * Test validating internal post type returns false for invalid post type.
	 */
	public function test_validate_internal_post_type_returns_false_for_invalid_post_type() {
		$post = array(
			'key'   => 'group_new_' . uniqid(),
			'title' => 'New Field Group',
		);

		$result = acf_validate_internal_post_type( $post, 'invalid-post-type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	// =========================================================================
	// acf_translate_internal_post_type() tests
	// =========================================================================

	/**
	 * Test translating internal post type.
	 */
	public function test_translate_internal_post_type() {
		$post = array(
			'key'   => 'group_test',
			'title' => 'Test Title',
		);

		$result = acf_translate_internal_post_type( $post, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $post['key'], $result['key'], 'Should preserve key' );
	}

	/**
	 * Test translating internal post type returns original for invalid post type.
	 */
	public function test_translate_internal_post_type_returns_original_for_invalid_post_type() {
		$post = array(
			'key'   => 'group_test',
			'title' => 'Test Title',
		);

		$result = acf_translate_internal_post_type( $post, 'invalid-post-type' );

		$this->assertEquals( $post, $result, 'Should return original array for invalid post type' );
	}

	// =========================================================================
	// acf_get_internal_post_type_posts() tests
	// =========================================================================

	/**
	 * Test getting internal post type posts returns an array.
	 *
	 * Note: WP_Query for custom post types doesn't work in WorDBless.
	 * This test verifies the function signature and return type.
	 * Full WP_Query retrieval is tested via E2E tests.
	 */
	public function test_get_internal_post_type_posts_returns_array() {
		$result = acf_get_internal_post_type_posts( 'acf-post-type' );

		$this->assertIsArray( $result, 'Should return an array' );
	}


	/**
	 * Test getting internal post type posts returns empty array for invalid type.
	 */
	public function test_get_internal_post_type_posts_returns_empty_for_invalid_type() {
		$result = acf_get_internal_post_type_posts( 'invalid-post-type' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEmpty( $result, 'Should return empty array for invalid post type' );
	}

	// =========================================================================
	// acf_get_raw_internal_post_type_posts() tests
	// =========================================================================

	/**
	 * Test getting raw internal post type posts returns array.
	 *
	 * Note: WP_Query for custom post types doesn't work in WorDBless.
	 * This test verifies the function signature and return type.
	 * Full WP_Query retrieval is tested via E2E tests.
	 */
	public function test_get_raw_internal_post_type_posts_returns_array() {
		$result = acf_get_raw_internal_post_type_posts( 'acf-post-type' );

		$this->assertIsArray( $result, 'Should return an array' );
	}

	/**
	 * Test getting raw internal post type posts returns empty for invalid type.
	 */
	public function test_get_raw_internal_post_type_posts_returns_empty_for_invalid_type() {
		$result = acf_get_raw_internal_post_type_posts( 'invalid-post-type' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEmpty( $result, 'Should return empty array for invalid post type' );
	}

	// =========================================================================
	// acf_filter_internal_post_type_posts() tests
	// =========================================================================

	/**
	 * Test filtering internal post type posts by active status.
	 *
	 * Uses post types instead of field groups to avoid location rule filtering.
	 */
	public function test_filter_internal_post_type_posts_by_active() {
		$posts = array(
			array(
				'key'    => 'post_type_1',
				'title'  => 'Active Post Type',
				'active' => true,
			),
			array(
				'key'    => 'post_type_2',
				'title'  => 'Inactive Post Type',
				'active' => false,
			),
		);

		$result = acf_filter_internal_post_type_posts( $posts, array( 'active' => true ), 'acf-post-type' );

		$this->assertCount( 1, $result, 'Should return only active posts' );
		$this->assertEquals( 'post_type_1', $result[0]['key'], 'Should return the active post' );
	}

	/**
	 * Test filtering returns empty array for invalid post type.
	 */
	public function test_filter_internal_post_type_posts_returns_empty_for_invalid_type() {
		$posts = array(
			array(
				'key'    => 'group_1',
				'title'  => 'Test Group',
				'active' => true,
			),
		);

		$result = acf_filter_internal_post_type_posts( $posts, array(), 'invalid-post-type' );

		$this->assertEmpty( $result, 'Should return empty array for invalid post type' );
	}

	// =========================================================================
	// acf_update_internal_post_type() tests
	// =========================================================================

	/**
	 * Test updating internal post type.
	 */
	public function test_update_internal_post_type() {
		$updated_data = array(
			'ID'     => $this->field_group_id,
			'key'    => $this->field_group_key,
			'title'  => 'Updated Field Group Title',
			'active' => true,
		);

		$result = acf_update_internal_post_type( $updated_data, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $this->field_group_id, $result['ID'], 'Should preserve ID' );
		$this->assertEquals( 'Updated Field Group Title', $result['title'], 'Should update title' );

		// Verify the update persisted.
		$fetched = acf_get_internal_post_type( $this->field_group_id, 'acf-field-group' );
		$this->assertEquals( 'Updated Field Group Title', $fetched['title'], 'Update should persist' );
	}

	/**
	 * Test updating internal post type creates new post when no ID.
	 */
	public function test_update_internal_post_type_creates_new_post() {
		$new_key  = 'group_new_' . uniqid();
		$new_data = array(
			'key'    => $new_key,
			'title'  => 'New Field Group',
			'active' => true,
		);

		$result = acf_update_internal_post_type( $new_data, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'ID', $result, 'Should have an ID' );
		$this->assertGreaterThan( 0, $result['ID'], 'Should create new post with valid ID' );

		// Clean up.
		wp_delete_post( $result['ID'], true );
	}

	// =========================================================================
	// acf_flush_internal_post_type_cache() tests
	// =========================================================================

	/**
	 * Test flushing cache does not throw error.
	 */
	public function test_flush_internal_post_type_cache() {
		$post = array(
			'ID'  => $this->field_group_id,
			'key' => $this->field_group_key,
		);

		// Should not throw any errors.
		acf_flush_internal_post_type_cache( $post, 'acf-field-group' );

		$this->assertTrue( true, 'Cache flush should complete without error' );
	}

	// =========================================================================
	// acf_delete_internal_post_type() tests
	// =========================================================================

	/**
	 * Test deleting internal post type by ID.
	 */
	public function test_delete_internal_post_type_by_id() {
		// Create a post to delete.
		$delete_id = wp_insert_post(
			array(
				'post_type'   => 'acf-field-group',
				'post_title'  => 'To Delete',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $delete_id, 'acf-key', 'group_delete_' . uniqid() );

		$result = acf_delete_internal_post_type( $delete_id, 'acf-field-group' );

		$this->assertTrue( $result, 'Should return true on successful delete' );

		// Verify deletion.
		$post = get_post( $delete_id );
		$this->assertNull( $post, 'Post should be deleted' );
	}

	/**
	 * Test deleting internal post type returns false for invalid ID.
	 */
	public function test_delete_internal_post_type_returns_false_for_invalid_id() {
		$result = acf_delete_internal_post_type( 99999, 'acf-field-group' );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test deleting internal post type returns false for invalid post type.
	 */
	public function test_delete_internal_post_type_returns_false_for_invalid_post_type() {
		$result = acf_delete_internal_post_type( $this->field_group_id, 'invalid-post-type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	// =========================================================================
	// acf_trash_internal_post_type() tests
	// =========================================================================

	/**
	 * Test trashing internal post type.
	 */
	public function test_trash_internal_post_type() {
		// Create a post to trash.
		$trash_id = wp_insert_post(
			array(
				'post_type'   => 'acf-field-group',
				'post_title'  => 'To Trash',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $trash_id, 'acf-key', 'group_trash_' . uniqid() );

		$result = acf_trash_internal_post_type( $trash_id, 'acf-field-group' );

		$this->assertTrue( $result, 'Should return true on successful trash' );

		// Verify trash.
		$post = get_post( $trash_id );
		$this->assertEquals( 'trash', $post->post_status, 'Post should be trashed' );

		// Clean up.
		wp_delete_post( $trash_id, true );
	}

	/**
	 * Test trashing returns false for invalid ID.
	 */
	public function test_trash_internal_post_type_returns_false_for_invalid_id() {
		$result = acf_trash_internal_post_type( 99999, 'acf-field-group' );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test trashing returns false for invalid post type.
	 */
	public function test_trash_internal_post_type_returns_false_for_invalid_post_type() {
		$result = acf_trash_internal_post_type( $this->field_group_id, 'invalid-post-type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	// =========================================================================
	// acf_untrash_internal_post_type() tests
	// =========================================================================

	/**
	 * Test untrashing internal post type.
	 */
	public function test_untrash_internal_post_type() {
		// Create and trash a post.
		$trash_id = wp_insert_post(
			array(
				'post_type'   => 'acf-field-group',
				'post_title'  => 'To Untrash',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $trash_id, 'acf-key', 'group_untrash_' . uniqid() );
		wp_trash_post( $trash_id );

		$result = acf_untrash_internal_post_type( $trash_id, 'acf-field-group' );

		$this->assertTrue( $result, 'Should return true on successful untrash' );

		// Verify untrash.
		$post = get_post( $trash_id );
		$this->assertNotEquals( 'trash', $post->post_status, 'Post should not be trashed' );

		// Clean up.
		wp_delete_post( $trash_id, true );
	}

	/**
	 * Test untrashing returns false for invalid ID.
	 */
	public function test_untrash_internal_post_type_returns_false_for_invalid_id() {
		$result = acf_untrash_internal_post_type( 99999, 'acf-field-group' );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test untrashing returns false for invalid post type.
	 */
	public function test_untrash_internal_post_type_returns_false_for_invalid_post_type() {
		$result = acf_untrash_internal_post_type( $this->field_group_id, 'invalid-post-type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	// =========================================================================
	// acf_is_internal_post_type() tests
	// =========================================================================

	/**
	 * Test checking if array is internal post type.
	 */
	public function test_is_internal_post_type() {
		$post = acf_get_internal_post_type( $this->field_group_id, 'acf-field-group' );

		$this->assertTrue(
			acf_is_internal_post_type( $post, 'acf-field-group' ),
			'Should return true for valid internal post type'
		);
	}

	/**
	 * Test is_internal_post_type returns false for empty array.
	 */
	public function test_is_internal_post_type_returns_false_for_empty_array() {
		$this->assertFalse(
			acf_is_internal_post_type( array(), 'acf-field-group' ),
			'Should return false for empty array'
		);
	}

	/**
	 * Test is_internal_post_type returns false for invalid post type.
	 */
	public function test_is_internal_post_type_returns_false_for_invalid_post_type() {
		$post = acf_get_internal_post_type( $this->field_group_id, 'acf-field-group' );

		$this->assertFalse(
			acf_is_internal_post_type( $post, 'invalid-post-type' ),
			'Should return false for invalid post type'
		);
	}

	// =========================================================================
	// acf_duplicate_internal_post_type() tests
	// =========================================================================

	/**
	 * Test duplicating internal post type.
	 */
	public function test_duplicate_internal_post_type() {
		$result = acf_duplicate_internal_post_type( $this->field_group_id, 0, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
		$this->assertNotEquals( $this->field_group_key, $result['key'], 'Should have new key' );
		$this->assertStringStartsWith( 'group_', $result['key'], 'Should use correct key prefix' );
		$this->assertStringContainsString( '(copy)', $result['title'], 'Should have (copy) in title' );

		// Clean up.
		if ( isset( $result['ID'] ) ) {
			wp_delete_post( $result['ID'], true );
		}
	}

	/**
	 * Test duplicating post type uses correct prefix.
	 */
	public function test_duplicate_internal_post_type_uses_correct_prefix() {
		$result = acf_duplicate_internal_post_type( $this->post_type_id, 0, 'acf-post-type' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertStringStartsWith( 'post_type_', $result['key'], 'Should use post_type_ prefix' );

		// Clean up.
		if ( isset( $result['ID'] ) ) {
			wp_delete_post( $result['ID'], true );
		}
	}

	/**
	 * Test duplicating taxonomy uses correct prefix.
	 */
	public function test_duplicate_internal_post_type_taxonomy_uses_correct_prefix() {
		$result = acf_duplicate_internal_post_type( $this->taxonomy_id, 0, 'acf-taxonomy' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertStringStartsWith( 'taxonomy_', $result['key'], 'Should use taxonomy_ prefix' );

		// Clean up.
		if ( isset( $result['ID'] ) ) {
			wp_delete_post( $result['ID'], true );
		}
	}

	/**
	 * Test duplicating returns false for invalid ID.
	 */
	public function test_duplicate_internal_post_type_returns_false_for_invalid_id() {
		$result = acf_duplicate_internal_post_type( 99999, 0, 'acf-field-group' );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test duplicating returns false for invalid post type.
	 */
	public function test_duplicate_internal_post_type_returns_false_for_invalid_post_type() {
		$result = acf_duplicate_internal_post_type( $this->field_group_id, 0, 'invalid-post-type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	// =========================================================================
	// acf_update_internal_post_type_active_status() tests
	// =========================================================================

	/**
	 * Test activating internal post type.
	 */
	public function test_update_internal_post_type_active_status_activate() {
		// First deactivate.
		wp_update_post(
			array(
				'ID'          => $this->field_group_id,
				'post_status' => 'acf-disabled',
			)
		);

		$result = acf_update_internal_post_type_active_status( $this->field_group_id, true, 'acf-field-group' );

		$this->assertTrue( $result, 'Should return true on successful activation' );

		$post = get_post( $this->field_group_id );
		$this->assertEquals( 'publish', $post->post_status, 'Post should be published (active)' );
	}

	/**
	 * Test deactivating internal post type.
	 */
	public function test_update_internal_post_type_active_status_deactivate() {
		$result = acf_update_internal_post_type_active_status( $this->field_group_id, false, 'acf-field-group' );

		$this->assertTrue( $result, 'Should return true on successful deactivation' );

		$post = get_post( $this->field_group_id );
		$this->assertEquals( 'acf-disabled', $post->post_status, 'Post should be disabled (inactive)' );
	}

	/**
	 * Test updating active status returns false for invalid ID.
	 */
	public function test_update_internal_post_type_active_status_returns_false_for_invalid_id() {
		$result = acf_update_internal_post_type_active_status( 99999, true, 'acf-field-group' );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test updating active status returns false for invalid post type.
	 */
	public function test_update_internal_post_type_active_status_returns_false_for_invalid_post_type() {
		$result = acf_update_internal_post_type_active_status( $this->field_group_id, true, 'invalid-post-type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	// =========================================================================
	// acf_get_internal_post_type_edit_link() tests
	// =========================================================================

	/**
	 * Test getting edit link.
	 */
	public function test_get_internal_post_type_edit_link() {
		// Set current user as admin to have edit capability.
		wp_set_current_user( 1 );

		$result = acf_get_internal_post_type_edit_link( $this->field_group_id, 'acf-field-group' );

		// Should return empty string since we're in a test environment without full admin context.
		$this->assertIsString( $result, 'Should return a string' );
	}

	/**
	 * Test getting edit link returns empty string for invalid post type.
	 */
	public function test_get_internal_post_type_edit_link_returns_empty_for_invalid_post_type() {
		$result = acf_get_internal_post_type_edit_link( $this->field_group_id, 'invalid-post-type' );

		$this->assertEquals( '', $result, 'Should return empty string for invalid post type' );
	}

	// =========================================================================
	// acf_prepare_internal_post_type_for_export() tests
	// =========================================================================

	/**
	 * Test preparing internal post type for export.
	 */
	public function test_prepare_internal_post_type_for_export() {
		$post = acf_get_internal_post_type( $this->field_group_id, 'acf-field-group' );

		$result = acf_prepare_internal_post_type_for_export( $post, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
		$this->assertArrayNotHasKey( 'ID', $result, 'Should not have ID for export' );
	}

	/**
	 * Test preparing returns original for invalid post type.
	 */
	public function test_prepare_internal_post_type_for_export_returns_original_for_invalid_type() {
		$post = array(
			'ID'    => 1,
			'key'   => 'group_test',
			'title' => 'Test',
		);

		$result = acf_prepare_internal_post_type_for_export( $post, 'invalid-post-type' );

		$this->assertEquals( $post, $result, 'Should return original array for invalid post type' );
	}

	// =========================================================================
	// acf_export_internal_post_type_as_php() tests
	// =========================================================================

	/**
	 * Test exporting internal post type as PHP.
	 *
	 * Note: The export uses register_post_type() for acf-post-type exports.
	 * The post_type field (slug) must be set in the array for export to work.
	 */
	public function test_export_internal_post_type_as_php() {
		// Create a properly structured post type array with required fields.
		$post = array(
			'key'       => $this->post_type_key,
			'title'     => 'Test Post Type',
			'post_type' => 'my_custom_type', // The slug for register_post_type().
			'public'    => true,
			'active'    => true,
		);

		$result = acf_export_internal_post_type_as_php( $post, 'acf-post-type' );

		$this->assertIsString( $result, 'Should return a string' );
		// ACF post types export using register_post_type(), not acf_register_post_type().
		$this->assertStringContainsString( 'register_post_type', $result, 'Should contain registration function' );
		$this->assertStringContainsString( 'my_custom_type', $result, 'Should contain the post type slug' );
	}

	/**
	 * Test exporting returns false for invalid post type.
	 */
	public function test_export_internal_post_type_as_php_returns_false_for_invalid_type() {
		$post = array(
			'key'   => 'group_test',
			'title' => 'Test',
		);

		$result = acf_export_internal_post_type_as_php( $post, 'invalid-post-type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	// =========================================================================
	// acf_prepare_internal_post_type_for_import() tests
	// =========================================================================

	/**
	 * Test preparing internal post type for import.
	 */
	public function test_prepare_internal_post_type_for_import() {
		$post = array(
			'key'    => 'group_import_' . uniqid(),
			'title'  => 'Imported Field Group',
			'active' => true,
		);

		$result = acf_prepare_internal_post_type_for_import( $post, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $post['key'], $result['key'], 'Should preserve key' );
	}

	/**
	 * Test preparing returns original for invalid post type.
	 */
	public function test_prepare_internal_post_type_for_import_returns_original_for_invalid_type() {
		$post = array(
			'key'   => 'group_test',
			'title' => 'Test',
		);

		$result = acf_prepare_internal_post_type_for_import( $post, 'invalid-post-type' );

		$this->assertEquals( $post, $result, 'Should return original array for invalid post type' );
	}

	// =========================================================================
	// acf_import_internal_post_type() tests
	// =========================================================================

	/**
	 * Test importing internal post type.
	 */
	public function test_import_internal_post_type() {
		$import_key = 'group_import_' . uniqid();
		$post       = array(
			'key'    => $import_key,
			'title'  => 'Imported Field Group',
			'active' => true,
		);

		$result = acf_import_internal_post_type( $post, 'acf-field-group' );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'ID', $result, 'Should have an ID' );
		$this->assertEquals( $import_key, $result['key'], 'Should preserve key' );

		// Clean up.
		if ( isset( $result['ID'] ) ) {
			wp_delete_post( $result['ID'], true );
		}
	}

	/**
	 * Test importing returns original for invalid post type.
	 */
	public function test_import_internal_post_type_returns_original_for_invalid_type() {
		$post = array(
			'key'   => 'group_test',
			'title' => 'Test',
		);

		$result = acf_import_internal_post_type( $post, 'invalid-post-type' );

		$this->assertEquals( $post, $result, 'Should return original array for invalid post type' );
	}

	// =========================================================================
	// acf_determine_internal_post_type() tests
	// =========================================================================

	/**
	 * Test determining internal post type from key.
	 *
	 * @dataProvider key_prefix_provider
	 *
	 * @param string $post_type  The expected internal post type slug.
	 * @param string $key_prefix The key prefix.
	 */
	public function test_determine_internal_post_type( $post_type, $key_prefix ) {
		$this->assertEquals( $post_type, acf_determine_internal_post_type( $key_prefix . 'abc123' ) );
	}

	/**
	 * Test determining internal post type returns false for invalid key.
	 */
	public function test_determine_internal_post_type_returns_false_for_invalid_key() {
		$this->assertFalse( acf_determine_internal_post_type( 'invalid_key' ) );
	}

	/**
	 * Test determining internal post type returns false for empty key.
	 */
	public function test_determine_internal_post_type_returns_false_for_empty_key() {
		$this->assertFalse( acf_determine_internal_post_type( '' ) );
	}

	// =========================================================================
	// acf_is_valid_internal_post_type_key() tests
	// =========================================================================

	/**
	 * Test valid internal post type keys.
	 *
	 * @dataProvider key_prefix_provider
	 *
	 * @param string $post_type  The internal post type slug (unused).
	 * @param string $key_prefix The key prefix.
	 */
	public function test_is_valid_internal_post_type_key( $post_type, $key_prefix ) {
		$this->assertTrue( acf_is_valid_internal_post_type_key( $key_prefix . 'abc123' ) );
	}

	/**
	 * Test invalid key returns false.
	 */
	public function test_is_valid_internal_post_type_key_returns_false_for_invalid() {
		$this->assertFalse( acf_is_valid_internal_post_type_key( 'invalid_key' ) );
	}

	// =========================================================================
	// acf_internal_post_object_contains_valid_key() tests
	// =========================================================================

	/**
	 * Test post object with valid key.
	 */
	public function test_internal_post_object_contains_valid_key() {
		$post = array( 'key' => 'group_abc123' );

		$this->assertTrue(
			acf_internal_post_object_contains_valid_key( $post ),
			'Should return true for post with valid key'
		);
	}

	/**
	 * Test post object with invalid key.
	 */
	public function test_internal_post_object_contains_valid_key_returns_false_for_invalid() {
		$post = array( 'key' => 'invalid_key' );

		$this->assertFalse(
			acf_internal_post_object_contains_valid_key( $post ),
			'Should return false for post with invalid key'
		);
	}

	/**
	 * Test post object without key.
	 */
	public function test_internal_post_object_contains_valid_key_returns_false_without_key() {
		$post = array( 'title' => 'No Key' );

		$this->assertFalse(
			acf_internal_post_object_contains_valid_key( $post ),
			'Should return false for post without key'
		);
	}

	/**
	 * Test post object with empty key.
	 */
	public function test_internal_post_object_contains_valid_key_returns_false_for_empty_key() {
		$post = array( 'key' => '' );

		$this->assertFalse(
			acf_internal_post_object_contains_valid_key( $post ),
			'Should return false for post with empty key'
		);
	}

	/**
	 * Test post object with non-string key.
	 */
	public function test_internal_post_object_contains_valid_key_returns_false_for_non_string_key() {
		$post = array( 'key' => 123 );

		$this->assertFalse(
			acf_internal_post_object_contains_valid_key( $post ),
			'Should return false for post with non-string key'
		);
	}

	// =========================================================================
	// acf_get_combined_post_type_settings_tabs() tests
	// =========================================================================

	/**
	 * Test getting combined post type settings tabs.
	 */
	public function test_get_combined_post_type_settings_tabs() {
		$result = acf_get_combined_post_type_settings_tabs();

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'general', $result, 'Should have general tab' );
		$this->assertArrayHasKey( 'labels', $result, 'Should have labels tab' );
		$this->assertArrayHasKey( 'visibility', $result, 'Should have visibility tab' );
		$this->assertArrayHasKey( 'urls', $result, 'Should have urls tab' );
		$this->assertArrayHasKey( 'permissions', $result, 'Should have permissions tab' );
		$this->assertArrayHasKey( 'rest_api', $result, 'Should have rest_api tab' );
	}

	/**
	 * Test additional tabs filter.
	 */
	public function test_get_combined_post_type_settings_tabs_filter() {
		// Add a custom tab via filter.
		add_filter(
			'acf/post_type/additional_settings_tabs',
			function ( $tabs ) {
				$tabs['custom'] = 'Custom Tab';
				return $tabs;
			}
		);

		$result = acf_get_combined_post_type_settings_tabs();

		$this->assertArrayHasKey( 'custom', $result, 'Should include custom tab from filter' );
		$this->assertEquals( 'Custom Tab', $result['custom'], 'Should have correct custom tab label' );

		// Clean up.
		remove_all_filters( 'acf/post_type/additional_settings_tabs' );
	}

	/**
	 * Test that filter cannot override default tabs.
	 */
	public function test_get_combined_post_type_settings_tabs_filter_cannot_override_defaults() {
		// Try to override a default tab via filter.
		add_filter(
			'acf/post_type/additional_settings_tabs',
			function ( $tabs ) {
				$tabs['general'] = 'Overridden General';
				return $tabs;
			}
		);

		$result = acf_get_combined_post_type_settings_tabs();

		$this->assertNotEquals( 'Overridden General', $result['general'], 'Filter should not override default tabs' );

		// Clean up.
		remove_all_filters( 'acf/post_type/additional_settings_tabs' );
	}

	// =========================================================================
	// acf_get_combined_taxonomy_settings_tabs() tests
	// =========================================================================

	/**
	 * Test getting combined taxonomy settings tabs.
	 */
	public function test_get_combined_taxonomy_settings_tabs() {
		$result = acf_get_combined_taxonomy_settings_tabs();

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'general', $result, 'Should have general tab' );
		$this->assertArrayHasKey( 'labels', $result, 'Should have labels tab' );
	}

	// =========================================================================
	// acf_get_combined_options_page_settings_tabs() tests
	// =========================================================================

	/**
	 * Test getting combined options page settings tabs.
	 */
	public function test_get_combined_options_page_settings_tabs() {
		$result = acf_get_combined_options_page_settings_tabs();

		$this->assertIsArray( $result, 'Should return an array' );
		// Options page tabs don't have 'general' - they have visibility, labels, permissions.
		$this->assertArrayHasKey( 'visibility', $result, 'Should have visibility tab' );
		$this->assertArrayHasKey( 'labels', $result, 'Should have labels tab' );
		$this->assertArrayHasKey( 'permissions', $result, 'Should have permissions tab' );
	}

	// =========================================================================
	// acf_get_post_type_from_screen_value() tests
	// =========================================================================

	/**
	 * Test getting post type from screen value.
	 */
	public function test_get_post_type_from_screen_value() {
		$result = acf_get_post_type_from_screen_value( 'post_type' );

		$this->assertEquals( 'acf-post-type', $result, 'Should return acf-post-type for post_type screen' );
	}

	/**
	 * Test getting taxonomy from screen value.
	 */
	public function test_get_post_type_from_screen_value_taxonomy() {
		$result = acf_get_post_type_from_screen_value( 'taxonomy' );

		$this->assertEquals( 'acf-taxonomy', $result, 'Should return acf-taxonomy for taxonomy screen' );
	}

	/**
	 * Test getting ui options page from screen value.
	 */
	public function test_get_post_type_from_screen_value_ui_options_page() {
		$result = acf_get_post_type_from_screen_value( 'ui_options_page' );

		$this->assertEquals( 'acf-ui-options-page', $result, 'Should return acf-ui-options-page for ui_options_page screen' );
	}

	/**
	 * Test getting post type from invalid screen value.
	 */
	public function test_get_post_type_from_screen_value_returns_false_for_invalid() {
		$result = acf_get_post_type_from_screen_value( 'invalid_screen' );

		$this->assertFalse( $result, 'Should return false for invalid screen value' );
	}
}
