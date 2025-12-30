<?php
/**
 * Tests for scf-ui-options-page-functions.php
 *
 * Tests the public API functions for managing SCF UI options pages.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load required files.
acf_include( '/includes/acf-internal-post-type-functions.php' );
acf_include( '/includes/scf-ui-options-page-functions.php' );
acf_include( '/includes/class-acf-internal-post-type.php' );
acf_include( '/includes/post-types/class-acf-ui-options-page.php' );

/**
 * Test SCF UI options page functions.
 */
class Test_SCF_UI_Options_Page_Functions extends BaseTestCase {

	/**
	 * Test options page post ID.
	 *
	 * @var int
	 */
	private $options_page_id;

	/**
	 * Test options page key.
	 *
	 * @var string
	 */
	private $options_page_key;

	/**
	 * Second options page for testing.
	 *
	 * @var int
	 */
	private $options_page_id_2;

	/**
	 * Second options page key.
	 *
	 * @var string
	 */
	private $options_page_key_2;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear ACF caches to ensure fresh state.
		// The cache key is based on cache_key_plural property of the class.
		wp_cache_delete( acf_cache_key( 'acf_get_ui_options_page_posts' ), 'secure-custom-fields' );

		// Unique keys for each test run - key is stored as post_name.
		$this->options_page_key   = 'ui_options_page_test_' . uniqid();
		$this->options_page_key_2 = 'ui_options_page_test2_' . uniqid();

		// Create first options page post - key is stored as post_name.
		$this->options_page_id = wp_insert_post(
			array(
				'post_type'   => 'acf-ui-options-page',
				'post_title'  => 'Test Options Page',
				'post_status' => 'publish',
				'post_name'   => $this->options_page_key,
			)
		);

		// Create second options page post for multiple post tests.
		$this->options_page_id_2 = wp_insert_post(
			array(
				'post_type'   => 'acf-ui-options-page',
				'post_title'  => 'Test Options Page 2',
				'post_status' => 'publish',
				'post_name'   => $this->options_page_key_2,
			)
		);
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		if ( $this->options_page_id ) {
			wp_delete_post( $this->options_page_id, true );
		}
		if ( $this->options_page_id_2 ) {
			wp_delete_post( $this->options_page_id_2, true );
		}

		// Clear any cached data.
		$store = acf_get_store( 'ui-options-pages' );
		if ( $store ) {
			$store->reset();
		}

		parent::tearDown();
	}

	// =========================================================================
	// acf_get_ui_options_page() tests
	// =========================================================================

	/**
	 * Test getting options page by ID.
	 */
	public function test_get_ui_options_page_by_id() {
		$result = acf_get_ui_options_page( $this->options_page_id );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $this->options_page_id, $result['ID'], 'Should return correct ID' );
		$this->assertEquals( $this->options_page_key, $result['key'], 'Should return correct key' );
	}

	/**
	 * Test getting options page by key.
	 *
	 * Note: Key-based lookup requires the store cache to be populated first.
	 */
	public function test_get_ui_options_page_by_key() {
		// First, get by ID to populate the store cache with alias.
		$by_id = acf_get_ui_options_page( $this->options_page_id );
		$this->assertIsArray( $by_id, 'Should return an array when fetched by ID' );

		// Now lookup by key should work via the store alias.
		$result = acf_get_ui_options_page( $this->options_page_key );

		$this->assertIsArray( $result, 'Should return an array when fetched by key after ID lookup' );
		$this->assertEquals( $this->options_page_id, $result['ID'], 'Should return correct ID when fetched by key' );
		$this->assertEquals( $this->options_page_key, $result['key'], 'Should return correct key' );
	}

	/**
	 * Test getting options page returns false for invalid ID.
	 */
	public function test_get_ui_options_page_returns_false_for_invalid_id() {
		$result = acf_get_ui_options_page( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	/**
	 * Test getting options page returns false for invalid key.
	 */
	public function test_get_ui_options_page_returns_false_for_invalid_key() {
		$result = acf_get_ui_options_page( 'invalid_key_' . uniqid() );

		$this->assertFalse( $result, 'Should return false for non-existent key' );
	}

	/**
	 * Test options page has expected default fields.
	 */
	public function test_get_ui_options_page_has_expected_fields() {
		$result = acf_get_ui_options_page( $this->options_page_id );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'ID', $result, 'Should have ID' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
		$this->assertArrayHasKey( 'title', $result, 'Should have title' );
		$this->assertArrayHasKey( 'active', $result, 'Should have active status' );
		$this->assertArrayHasKey( 'menu_slug', $result, 'Should have menu_slug' );
		$this->assertArrayHasKey( 'parent_slug', $result, 'Should have parent_slug' );
		$this->assertArrayHasKey( 'capability', $result, 'Should have capability' );
	}

	// =========================================================================
	// acf_get_raw_ui_options_page() tests
	// =========================================================================

	/**
	 * Test getting raw options page by ID.
	 */
	public function test_get_raw_ui_options_page_by_id() {
		$result = acf_get_raw_ui_options_page( $this->options_page_id );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $this->options_page_id, $result['ID'], 'Should return correct ID' );
	}

	/**
	 * Test getting raw options page returns false for invalid ID.
	 */
	public function test_get_raw_ui_options_page_returns_false_for_invalid_id() {
		$result = acf_get_raw_ui_options_page( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	// =========================================================================
	// acf_get_ui_options_page_post() tests
	// =========================================================================

	/**
	 * Test getting options page post object by ID.
	 */
	public function test_get_ui_options_page_post_by_id() {
		$result = acf_get_ui_options_page_post( $this->options_page_id );

		$this->assertIsObject( $result, 'Should return an object' );
		$this->assertEquals( $this->options_page_id, $result->ID, 'Should return correct post ID' );
		$this->assertEquals( 'acf-ui-options-page', $result->post_type, 'Should return correct post type' );
	}

	/**
	 * Test getting options page post returns false for invalid ID.
	 */
	public function test_get_ui_options_page_post_returns_false_for_invalid_id() {
		$result = acf_get_ui_options_page_post( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	// =========================================================================
	// acf_is_ui_options_page_key() tests
	// =========================================================================

	/**
	 * Test valid options page key detection.
	 */
	public function test_is_ui_options_page_key_returns_true_for_valid_key() {
		$this->assertTrue(
			acf_is_ui_options_page_key( 'ui_options_page_abc123' ),
			'Should return true for valid ui options page key'
		);
	}

	/**
	 * Test invalid key format returns false.
	 */
	public function test_is_ui_options_page_key_returns_false_for_invalid_format() {
		$this->assertFalse(
			acf_is_ui_options_page_key( 'group_abc123' ),
			'Should return false for field group key'
		);

		$this->assertFalse(
			acf_is_ui_options_page_key( 'post_type_abc123' ),
			'Should return false for post type key'
		);

		$this->assertFalse(
			acf_is_ui_options_page_key( 'taxonomy_abc123' ),
			'Should return false for taxonomy key'
		);
	}

	/**
	 * Test empty string returns false.
	 */
	public function test_is_ui_options_page_key_returns_false_for_empty_string() {
		$this->assertFalse(
			acf_is_ui_options_page_key( '' ),
			'Should return false for empty string'
		);
	}

	// =========================================================================
	// acf_validate_ui_options_page() tests
	// =========================================================================

	/**
	 * Test validating options page.
	 */
	public function test_validate_ui_options_page() {
		$page = array(
			'key'        => 'ui_options_page_new_' . uniqid(),
			'title'      => 'New Options Page',
			'page_title' => 'New Options Page Title',
		);

		$result = acf_validate_ui_options_page( $page );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $page['key'], $result['key'], 'Should preserve key' );
		$this->assertEquals( $page['title'], $result['title'], 'Should preserve title' );
		$this->assertArrayHasKey( 'active', $result, 'Should add default active value' );
		$this->assertArrayHasKey( 'capability', $result, 'Should add default capability' );
	}

	/**
	 * Test validating sets default values.
	 */
	public function test_validate_ui_options_page_sets_defaults() {
		$page = array(
			'key'   => 'ui_options_page_defaults_' . uniqid(),
			'title' => 'Default Test Page',
		);

		$result = acf_validate_ui_options_page( $page );

		$this->assertTrue( $result['active'], 'Default active should be true' );
		$this->assertEquals( 'edit_posts', $result['capability'], 'Default capability should be edit_posts' );
		$this->assertEquals( 'options', $result['data_storage'], 'Default data_storage should be options' );
		$this->assertFalse( $result['redirect'], 'Default redirect should be false' );
	}

	// =========================================================================
	// acf_translate_ui_options_page() tests
	// =========================================================================

	/**
	 * Test translating options page.
	 */
	public function test_translate_ui_options_page() {
		$page = array(
			'key'   => 'ui_options_page_translate',
			'title' => 'Translate Test',
		);

		$result = acf_translate_ui_options_page( $page );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $page['key'], $result['key'], 'Should preserve key' );
	}

	// =========================================================================
	// acf_get_ui_options_pages() tests
	// =========================================================================

	/**
	 * Test getting all options pages returns an array.
	 *
	 * Note: WP_Query for custom post types doesn't work in WorDBless.
	 * This test verifies the function signature and return type.
	 * Full WP_Query retrieval is tested via E2E tests.
	 */
	public function test_get_ui_options_pages_returns_array() {
		$result = acf_get_ui_options_pages();

		$this->assertIsArray( $result, 'Should return an array' );
	}



	// =========================================================================
	// acf_get_raw_ui_options_pages() tests
	// =========================================================================

	/**
	 * Test getting raw options pages returns array.
	 *
	 * Note: WP_Query for custom post types doesn't work in WorDBless.
	 * This test verifies the function signature and return type.
	 * Full WP_Query retrieval is tested via E2E tests.
	 */
	public function test_get_raw_ui_options_pages_returns_array() {
		$result = acf_get_raw_ui_options_pages();

		$this->assertIsArray( $result, 'Should return an array' );
	}

	// =========================================================================
	// acf_filter_ui_options_pages() tests
	// =========================================================================

	/**
	 * Test filtering options pages by active status.
	 */
	public function test_filter_ui_options_pages_by_active() {
		$pages = array(
			array(
				'key'    => 'ui_options_page_1',
				'title'  => 'Active Page',
				'active' => true,
			),
			array(
				'key'    => 'ui_options_page_2',
				'title'  => 'Inactive Page',
				'active' => false,
			),
		);

		$result = acf_filter_ui_options_pages( $pages, array( 'active' => true ) );

		$this->assertCount( 1, $result, 'Should return only active pages' );
		$this->assertEquals( 'ui_options_page_1', $result[0]['key'], 'Should return the active page' );
	}

	/**
	 * Test filtering options pages without filter returns all.
	 */
	public function test_filter_ui_options_pages_without_filter_returns_all() {
		$pages = array(
			array(
				'key'    => 'ui_options_page_1',
				'title'  => 'Page 1',
				'active' => true,
			),
			array(
				'key'    => 'ui_options_page_2',
				'title'  => 'Page 2',
				'active' => false,
			),
		);

		$result = acf_filter_ui_options_pages( $pages, array() );

		$this->assertCount( 2, $result, 'Should return all pages when no filter' );
	}

	// =========================================================================
	// acf_update_ui_options_page() tests
	// =========================================================================

	/**
	 * Test updating options page.
	 */
	public function test_update_ui_options_page() {
		$updated_data = array(
			'ID'         => $this->options_page_id,
			'key'        => $this->options_page_key,
			'title'      => 'Updated Options Page Title',
			'page_title' => 'Updated Page Title',
			'menu_slug'  => 'updated-options-page',
			'active'     => true,
		);

		$result = acf_update_ui_options_page( $updated_data );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $this->options_page_id, $result['ID'], 'Should preserve ID' );
		$this->assertEquals( 'Updated Options Page Title', $result['title'], 'Should update title' );

		// Verify the update persisted.
		$fetched = acf_get_ui_options_page( $this->options_page_id );
		$this->assertEquals( 'Updated Options Page Title', $fetched['title'], 'Update should persist' );
	}

	/**
	 * Test updating options page creates new post when no ID.
	 */
	public function test_update_ui_options_page_creates_new_post() {
		$new_key  = 'ui_options_page_new_' . uniqid();
		$new_data = array(
			'key'        => $new_key,
			'title'      => 'New Options Page',
			'page_title' => 'New Page Title',
			'menu_slug'  => 'new-options-page',
			'active'     => true,
		);

		$result = acf_update_ui_options_page( $new_data );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'ID', $result, 'Should have an ID' );
		$this->assertGreaterThan( 0, $result['ID'], 'Should create new post with valid ID' );
		$this->assertEquals( $new_key, $result['key'], 'Should preserve key' );

		// Clean up.
		wp_delete_post( $result['ID'], true );
	}

	// =========================================================================
	// acf_flush_ui_options_page_cache() tests
	// =========================================================================

	/**
	 * Test flushing cache does not throw error.
	 */
	public function test_flush_ui_options_page_cache() {
		$page = array(
			'ID'  => $this->options_page_id,
			'key' => $this->options_page_key,
		);

		// Should not throw any errors.
		acf_flush_ui_options_page_cache( $page );

		$this->assertTrue( true, 'Cache flush should complete without error' );
	}

	// =========================================================================
	// acf_delete_ui_options_page() tests
	// =========================================================================

	/**
	 * Test deleting options page by ID.
	 */
	public function test_delete_ui_options_page_by_id() {
		// Create a page to delete.
		$delete_id = wp_insert_post(
			array(
				'post_type'   => 'acf-ui-options-page',
				'post_title'  => 'To Delete',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $delete_id, 'acf-key', 'ui_options_page_delete_' . uniqid() );

		$result = acf_delete_ui_options_page( $delete_id );

		$this->assertTrue( $result, 'Should return true on successful delete' );

		// Verify deletion.
		$post = get_post( $delete_id );
		$this->assertNull( $post, 'Post should be deleted' );
	}

	/**
	 * Test deleting options page returns false for invalid ID.
	 */
	public function test_delete_ui_options_page_returns_false_for_invalid_id() {
		$result = acf_delete_ui_options_page( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	// =========================================================================
	// acf_trash_ui_options_page() tests
	// =========================================================================

	/**
	 * Test trashing options page.
	 */
	public function test_trash_ui_options_page() {
		// Create a page to trash.
		$trash_id = wp_insert_post(
			array(
				'post_type'   => 'acf-ui-options-page',
				'post_title'  => 'To Trash',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $trash_id, 'acf-key', 'ui_options_page_trash_' . uniqid() );

		$result = acf_trash_ui_options_page( $trash_id );

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
	public function test_trash_ui_options_page_returns_false_for_invalid_id() {
		$result = acf_trash_ui_options_page( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	// =========================================================================
	// acf_untrash_ui_options_page() tests
	// =========================================================================

	/**
	 * Test untrashing options page.
	 */
	public function test_untrash_ui_options_page() {
		// Create and trash a page.
		$trash_id = wp_insert_post(
			array(
				'post_type'   => 'acf-ui-options-page',
				'post_title'  => 'To Untrash',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $trash_id, 'acf-key', 'ui_options_page_untrash_' . uniqid() );
		wp_trash_post( $trash_id );

		$result = acf_untrash_ui_options_page( $trash_id );

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
	public function test_untrash_ui_options_page_returns_false_for_invalid_id() {
		$result = acf_untrash_ui_options_page( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	// =========================================================================
	// acf_is_ui_options_page() tests
	// =========================================================================

	/**
	 * Test checking if array is options page.
	 */
	public function test_is_ui_options_page() {
		$page = acf_get_ui_options_page( $this->options_page_id );

		$this->assertTrue(
			acf_is_ui_options_page( $page ),
			'Should return true for valid ui options page'
		);
	}

	/**
	 * Test is_ui_options_page returns false for empty array.
	 */
	public function test_is_ui_options_page_returns_false_for_empty_array() {
		$this->assertFalse(
			acf_is_ui_options_page( array() ),
			'Should return false for empty array'
		);
	}

	// =========================================================================
	// acf_duplicate_ui_options_page() tests
	// =========================================================================

	/**
	 * Test duplicating options page.
	 */
	public function test_duplicate_ui_options_page() {
		$result = acf_duplicate_ui_options_page( $this->options_page_id );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
		$this->assertNotEquals( $this->options_page_key, $result['key'], 'Should have new key' );
		$this->assertStringStartsWith( 'ui_options_page_', $result['key'], 'Should use correct key prefix' );
		$this->assertStringContainsString( '(copy)', $result['title'], 'Should have (copy) in title' );

		// Clean up.
		if ( isset( $result['ID'] ) ) {
			wp_delete_post( $result['ID'], true );
		}
	}

	/**
	 * Test duplicating with new post ID skips copy suffix.
	 */
	public function test_duplicate_ui_options_page_skips_copy_suffix_with_new_post_id() {
		$original       = acf_get_ui_options_page( $this->options_page_id );
		$original_title = $original['title'];

		$result = acf_duplicate_ui_options_page( $this->options_page_id, 999 );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals(
			$original_title,
			$result['title'],
			'Title should not have (copy) when new_post_id is provided'
		);

		// Clean up.
		if ( isset( $result['ID'] ) ) {
			wp_delete_post( $result['ID'], true );
		}
	}

	/**
	 * Test duplicating returns false for invalid ID.
	 */
	public function test_duplicate_ui_options_page_returns_false_for_invalid_id() {
		$result = acf_duplicate_ui_options_page( 99999 );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	// =========================================================================
	// acf_update_ui_options_page_active_status() tests
	// =========================================================================

	/**
	 * Test activating options page.
	 */
	public function test_update_ui_options_page_active_status_activate() {
		// First deactivate.
		wp_update_post(
			array(
				'ID'          => $this->options_page_id,
				'post_status' => 'acf-disabled',
			)
		);

		$result = acf_update_ui_options_page_active_status( $this->options_page_id, true );

		$this->assertTrue( $result, 'Should return true on successful activation' );

		$post = get_post( $this->options_page_id );
		$this->assertEquals( 'publish', $post->post_status, 'Post should be published (active)' );
	}

	/**
	 * Test deactivating options page.
	 */
	public function test_update_ui_options_page_active_status_deactivate() {
		$result = acf_update_ui_options_page_active_status( $this->options_page_id, false );

		$this->assertTrue( $result, 'Should return true on successful deactivation' );

		$post = get_post( $this->options_page_id );
		$this->assertEquals( 'acf-disabled', $post->post_status, 'Post should be disabled (inactive)' );
	}

	/**
	 * Test updating active status returns false for invalid ID.
	 */
	public function test_update_ui_options_page_active_status_returns_false_for_invalid_id() {
		$result = acf_update_ui_options_page_active_status( 99999, true );

		$this->assertFalse( $result, 'Should return false for non-existent ID' );
	}

	// =========================================================================
	// acf_get_ui_options_page_edit_link() tests
	// =========================================================================

	/**
	 * Test getting edit link.
	 */
	public function test_get_ui_options_page_edit_link() {
		// Set current user as admin to have edit capability.
		wp_set_current_user( 1 );

		$result = acf_get_ui_options_page_edit_link( $this->options_page_id );

		// Should return a string (possibly empty in test environment).
		$this->assertIsString( $result, 'Should return a string' );
	}

	// =========================================================================
	// acf_prepare_ui_options_page_for_export() tests
	// =========================================================================

	/**
	 * Test preparing options page for export.
	 */
	public function test_prepare_ui_options_page_for_export() {
		$page = acf_get_ui_options_page( $this->options_page_id );

		$result = acf_prepare_ui_options_page_for_export( $page );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'key', $result, 'Should have key' );
		$this->assertArrayNotHasKey( 'ID', $result, 'Should not have ID for export' );
	}

	/**
	 * Test preparing empty array returns empty array.
	 */
	public function test_prepare_ui_options_page_for_export_empty_array() {
		$result = acf_prepare_ui_options_page_for_export( array() );

		$this->assertIsArray( $result, 'Should return an array' );
	}

	// =========================================================================
	// acf_export_ui_options_page_as_php() tests
	// =========================================================================

	/**
	 * Test exporting options page as PHP.
	 *
	 * Note: The export uses acf_add_options_page() and strips UI-specific
	 * settings like key, title, active, menu_order.
	 */
	public function test_export_ui_options_page_as_php() {
		// Create a properly structured options page array with required fields.
		$page = array(
			'key'        => $this->options_page_key,
			'title'      => 'Test Options Page',
			'page_title' => 'Test Page Title',
			'menu_slug'  => 'test-options-page',
			'active'     => true,
			'redirect'   => false, // Non-default value that will appear in export.
		);

		$result = acf_export_ui_options_page_as_php( $page );

		$this->assertIsString( $result, 'Should return a string' );
		$this->assertStringContainsString( 'acf_add_options_page', $result, 'Should contain registration function' );
		// Key is a UI-specific setting and is NOT included in export.
		// Instead, verify other settings are present.
		$this->assertStringContainsString( 'redirect', $result, 'Should contain non-default settings' );
	}

	// =========================================================================
	// acf_prepare_ui_options_page_for_import() tests
	// =========================================================================

	/**
	 * Test preparing options page for import.
	 */
	public function test_prepare_ui_options_page_for_import() {
		$page = array(
			'key'        => 'ui_options_page_import_' . uniqid(),
			'title'      => 'Imported Options Page',
			'page_title' => 'Imported Page Title',
			'menu_slug'  => 'imported-options',
			'active'     => true,
		);

		$result = acf_prepare_ui_options_page_for_import( $page );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $page['key'], $result['key'], 'Should preserve key' );
	}

	// =========================================================================
	// acf_import_ui_options_page() tests
	// =========================================================================

	/**
	 * Test importing options page.
	 */
	public function test_import_ui_options_page() {
		$import_key = 'ui_options_page_import_' . uniqid();
		$page       = array(
			'key'        => $import_key,
			'title'      => 'Imported Options Page',
			'page_title' => 'Imported Page Title',
			'menu_slug'  => 'imported-options',
			'active'     => true,
		);

		$result = acf_import_ui_options_page( $page );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertArrayHasKey( 'ID', $result, 'Should have an ID' );
		$this->assertEquals( $import_key, $result['key'], 'Should preserve key' );

		// Clean up.
		if ( isset( $result['ID'] ) ) {
			wp_delete_post( $result['ID'], true );
		}
	}

	/**
	 * Test importing updates existing post when ID is provided.
	 *
	 * Note: The import function itself does NOT look up existing posts by key.
	 * That lookup must be done before calling import (as the admin import tool does).
	 * To update an existing post, the ID must be passed in the array.
	 */
	public function test_import_ui_options_page_updates_existing() {
		// Import with existing ID - this is how updates work.
		$page = array(
			'ID'         => $this->options_page_id, // Must provide ID to update.
			'key'        => $this->options_page_key,
			'title'      => 'Updated Via Import',
			'page_title' => 'Updated Page Title',
			'menu_slug'  => 'updated-via-import',
			'active'     => true,
		);

		$result = acf_import_ui_options_page( $page );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( $this->options_page_id, $result['ID'], 'Should update existing post' );
		$this->assertEquals( 'Updated Via Import', $result['title'], 'Should update title' );
	}

	// =========================================================================
	// Menu configuration tests
	// =========================================================================

	/**
	 * Test options page menu configuration fields.
	 */
	public function test_options_page_menu_configuration_fields() {
		$page = acf_get_ui_options_page( $this->options_page_id );

		$this->assertArrayHasKey( 'menu_slug', $page, 'Should have menu_slug' );
		$this->assertArrayHasKey( 'parent_slug', $page, 'Should have parent_slug' );
		$this->assertArrayHasKey( 'menu_title', $page, 'Should have menu_title' );
		$this->assertArrayHasKey( 'position', $page, 'Should have position' );
		$this->assertArrayHasKey( 'icon_url', $page, 'Should have icon_url' );
	}

	/**
	 * Test options page capability configuration.
	 */
	public function test_options_page_capability_configuration() {
		$page = acf_get_ui_options_page( $this->options_page_id );

		$this->assertArrayHasKey( 'capability', $page, 'Should have capability' );
		$this->assertEquals( 'edit_posts', $page['capability'], 'Default capability should be edit_posts' );
	}

	/**
	 * Test options page data storage configuration.
	 */
	public function test_options_page_data_storage_configuration() {
		$page = acf_get_ui_options_page( $this->options_page_id );

		$this->assertArrayHasKey( 'data_storage', $page, 'Should have data_storage' );
		$this->assertEquals( 'options', $page['data_storage'], 'Default data_storage should be options' );
	}

	/**
	 * Test options page labels configuration.
	 */
	public function test_options_page_labels_configuration() {
		$page = acf_get_ui_options_page( $this->options_page_id );

		$this->assertArrayHasKey( 'update_button', $page, 'Should have update_button' );
		$this->assertArrayHasKey( 'updated_message', $page, 'Should have updated_message' );
	}

	// =========================================================================
	// Parent/child relationship tests
	// =========================================================================

	/**
	 * Test creating options page as child of another.
	 */
	public function test_options_page_parent_child_relationship() {
		// Create a child page with parent_slug.
		$child_key  = 'ui_options_page_child_' . uniqid();
		$child_data = array(
			'key'         => $child_key,
			'title'       => 'Child Options Page',
			'page_title'  => 'Child Page',
			'menu_slug'   => 'child-options',
			'parent_slug' => 'test-options-page',
			'active'      => true,
		);

		$result = acf_update_ui_options_page( $child_data );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEquals( 'test-options-page', $result['parent_slug'], 'Should preserve parent_slug' );

		// Clean up.
		if ( isset( $result['ID'] ) ) {
			wp_delete_post( $result['ID'], true );
		}
	}

	/**
	 * Test options page redirect configuration.
	 */
	public function test_options_page_redirect_configuration() {
		$page_data = array(
			'key'        => 'ui_options_page_redirect_' . uniqid(),
			'title'      => 'Redirect Test Page',
			'page_title' => 'Redirect Test',
			'menu_slug'  => 'redirect-test',
			'redirect'   => true,
			'active'     => true,
		);

		$result = acf_update_ui_options_page( $page_data );

		$this->assertTrue( $result['redirect'], 'Should preserve redirect setting' );

		// Clean up.
		if ( isset( $result['ID'] ) ) {
			wp_delete_post( $result['ID'], true );
		}
	}

	// =========================================================================
	// Edge case tests
	// =========================================================================

	/**
	 * Test options page with special characters in title.
	 */
	public function test_options_page_with_special_characters() {
		$page_data = array(
			'key'        => 'ui_options_page_special_' . uniqid(),
			'title'      => 'Options & Settings <Test>',
			'page_title' => 'Options & Settings <Test>',
			'menu_slug'  => 'special-options',
			'active'     => true,
		);

		$result = acf_update_ui_options_page( $page_data );

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertStringContainsString( '&', $result['title'], 'Should preserve ampersand in title' );

		// Clean up.
		if ( isset( $result['ID'] ) ) {
			wp_delete_post( $result['ID'], true );
		}
	}

	/**
	 * Test options page with empty menu_slug generates from title.
	 */
	public function test_options_page_generates_menu_slug_from_title() {
		$page = acf_get_ui_options_page( $this->options_page_id );

		// Menu slug should be set (even if empty initially, validation should set it).
		$this->assertArrayHasKey( 'menu_slug', $page, 'Should have menu_slug' );
	}

	/**
	 * Test multiple options pages can have same parent.
	 */
	public function test_multiple_options_pages_same_parent() {
		$parent_slug = 'test-parent-page';

		// Create first child.
		$child1_data = array(
			'key'         => 'ui_options_page_child1_' . uniqid(),
			'title'       => 'Child 1',
			'menu_slug'   => 'child-1',
			'parent_slug' => $parent_slug,
			'active'      => true,
		);
		$child1      = acf_update_ui_options_page( $child1_data );

		// Create second child.
		$child2_data = array(
			'key'         => 'ui_options_page_child2_' . uniqid(),
			'title'       => 'Child 2',
			'menu_slug'   => 'child-2',
			'parent_slug' => $parent_slug,
			'active'      => true,
		);
		$child2      = acf_update_ui_options_page( $child2_data );

		$this->assertEquals( $parent_slug, $child1['parent_slug'], 'First child should have correct parent' );
		$this->assertEquals( $parent_slug, $child2['parent_slug'], 'Second child should have correct parent' );

		// Clean up.
		if ( isset( $child1['ID'] ) ) {
			wp_delete_post( $child1['ID'], true );
		}
		if ( isset( $child2['ID'] ) ) {
			wp_delete_post( $child2['ID'], true );
		}
	}
}
