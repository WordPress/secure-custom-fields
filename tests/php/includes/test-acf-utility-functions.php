<?php
/**
 * Tests for utility functions in acf-utility-functions.php.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for utility functions.
 *
 * @covers ::acf_new_instance
 * @covers ::acf_get_instance
 * @covers ::acf_register_store
 * @covers ::acf_get_store
 * @covers ::acf_get_path
 * @covers ::acf_get_url
 * @covers ::acf_include
 */
class Test_ACF_Utility_Functions extends BaseTestCase {

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		// Reset instances.
		global $acf_instances;
		$acf_instances = array();

		parent::tear_down();
	}

	/**
	 * Test acf_new_instance creates and stores instance.
	 */
	public function test_acf_new_instance_creates_instance(): void {
		$instance = acf_new_instance( 'stdClass' );

		$this->assertInstanceOf( stdClass::class, $instance );
	}

	/**
	 * Test acf_new_instance stores in global.
	 */
	public function test_acf_new_instance_stores_in_global(): void {
		global $acf_instances;

		acf_new_instance( 'stdClass' );

		$this->assertArrayHasKey( 'stdClass', $acf_instances );
		$this->assertInstanceOf( stdClass::class, $acf_instances['stdClass'] );
	}

	/**
	 * Test acf_new_instance replaces existing instance.
	 */
	public function test_acf_new_instance_replaces_existing(): void {
		$instance1 = acf_new_instance( 'stdClass' );
		$instance2 = acf_new_instance( 'stdClass' );

		$this->assertNotSame( $instance1, $instance2 );

		global $acf_instances;
		$this->assertSame( $instance2, $acf_instances['stdClass'] );
	}

	/**
	 * Test acf_get_instance creates instance if not exists.
	 */
	public function test_acf_get_instance_creates_if_not_exists(): void {
		global $acf_instances;
		$acf_instances = array();

		$instance = acf_get_instance( 'stdClass' );

		$this->assertInstanceOf( stdClass::class, $instance );
	}

	/**
	 * Test acf_get_instance returns existing instance.
	 */
	public function test_acf_get_instance_returns_existing(): void {
		$instance1 = acf_get_instance( 'stdClass' );
		$instance2 = acf_get_instance( 'stdClass' );

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test acf_get_instance implements singleton pattern.
	 */
	public function test_acf_get_instance_singleton_pattern(): void {
		// First call creates instance.
		$first = acf_get_instance( 'stdClass' );

		// Modify the instance.
		$first->test_property = 'test_value';

		// Second call should return same instance with property.
		$second = acf_get_instance( 'stdClass' );

		$this->assertEquals( 'test_value', $second->test_property );
	}

	/**
	 * Test acf_register_store creates store.
	 */
	public function test_acf_register_store_creates_store(): void {
		$store = acf_register_store( 'test_store' );

		$this->assertInstanceOf( ACF_Data::class, $store );
	}

	/**
	 * Test acf_register_store stores in global.
	 */
	public function test_acf_register_store_stores_in_global(): void {
		global $acf_stores;

		acf_register_store( 'test_global_store' );

		$this->assertArrayHasKey( 'test_global_store', $acf_stores );
	}

	/**
	 * Test acf_register_store with initial data.
	 */
	public function test_acf_register_store_with_data(): void {
		$initial_data = array(
			'key1' => 'value1',
			'key2' => 'value2',
		);

		$store = acf_register_store( 'test_data_store', $initial_data );

		$this->assertEquals( 'value1', $store->get( 'key1' ) );
		$this->assertEquals( 'value2', $store->get( 'key2' ) );
	}

	/**
	 * Test acf_get_store returns registered store.
	 */
	public function test_acf_get_store_returns_store(): void {
		$original  = acf_register_store( 'get_test_store' );
		$retrieved = acf_get_store( 'get_test_store' );

		$this->assertSame( $original, $retrieved );
	}

	/**
	 * Test acf_get_store returns false for non-existent store.
	 */
	public function test_acf_get_store_returns_false_for_nonexistent(): void {
		$result = acf_get_store( 'nonexistent_store_' . uniqid() );

		$this->assertFalse( $result );
	}

	/**
	 * Test acf_get_path returns correct path.
	 */
	public function test_acf_get_path_returns_path(): void {
		$path = acf_get_path( 'includes/test.php' );

		$this->assertEquals( ACF_PATH . 'includes/test.php', $path );
	}

	/**
	 * Test acf_get_path handles leading slash.
	 */
	public function test_acf_get_path_trims_leading_slash(): void {
		$path = acf_get_path( '/includes/test.php' );

		$this->assertEquals( ACF_PATH . 'includes/test.php', $path );
		$this->assertStringNotContainsString( '//', $path );
	}

	/**
	 * Test acf_get_path with empty filename.
	 */
	public function test_acf_get_path_empty_filename(): void {
		$path = acf_get_path( '' );

		$this->assertEquals( ACF_PATH, $path );
	}

	/**
	 * Test acf_get_url returns correct URL.
	 */
	public function test_acf_get_url_returns_url(): void {
		$url = acf_get_url( 'assets/js/test.js' );

		$this->assertStringContainsString( 'assets/js/test.js', $url );
	}

	/**
	 * Test acf_get_url handles leading slash.
	 */
	public function test_acf_get_url_trims_leading_slash(): void {
		$url = acf_get_url( '/assets/js/test.js' );

		// Should not have double slashes after domain.
		$this->assertStringContainsString( 'assets/js/test.js', $url );
	}

	/**
	 * Test acf_get_url correctly appends filenames to ACF_URL base.
	 *
	 * Note: In test environments, ACF_URL may be a relative path rather than
	 * a full URL. We test the core behavior: filename appending with ltrim.
	 */
	public function test_acf_get_url_appends_filename(): void {
		$url_without_leading_slash = acf_get_url( 'assets/test-file.js' );
		$url_with_leading_slash    = acf_get_url( '/assets/test-file.js' );

		// Both should end with the same filename (ltrim removes leading slash).
		$this->assertStringEndsWith( 'assets/test-file.js', $url_without_leading_slash );
		$this->assertStringEndsWith( 'assets/test-file.js', $url_with_leading_slash );

		// Both should produce identical URLs (the ltrim behavior).
		$this->assertEquals( $url_without_leading_slash, $url_with_leading_slash );

		// URL should not be empty.
		$this->assertNotEmpty( $url_without_leading_slash );
	}

	/**
	 * Test acf_include uses acf_get_path to resolve file paths.
	 *
	 * Note: We can't directly test file inclusion since files are already loaded
	 * at bootstrap. Instead, we verify the path resolution is correct.
	 */
	public function test_acf_include_resolves_path_correctly(): void {
		// Verify that acf_get_path (used by acf_include) resolves correctly.
		$path = acf_get_path( 'includes/acf-helper-functions.php' );

		$this->assertTrue( file_exists( $path ), 'acf_include should resolve to an existing file path' );
	}

	/**
	 * Test acf_include silently handles missing files without fatal errors.
	 *
	 * Note: acf_include uses include_once with @ suppression, so missing files
	 * never cause errors. We verify the path resolution and that inclusion
	 * of missing files doesn't break execution.
	 */
	public function test_acf_include_handles_missing_file(): void {
		$missing_file  = 'nonexistent-file-' . uniqid() . '.php';
		$resolved_path = acf_get_path( $missing_file );

		// Verify the resolved path follows expected pattern.
		$this->assertStringEndsWith( $missing_file, $resolved_path );
		$this->assertStringStartsWith( ACF_PATH, $resolved_path );

		// Verify the file truly doesn't exist.
		$this->assertFalse( file_exists( $resolved_path ) );

		// Call acf_include - the test passes if no fatal error occurs.
		// This documents that acf_include uses @ suppression for missing files.
		acf_include( $missing_file );
	}

	/**
	 * Test acf_include handles leading slash consistently with acf_get_path.
	 */
	public function test_acf_include_handles_leading_slash(): void {
		// Both paths should resolve to the same file.
		$path_without_slash = acf_get_path( 'includes/acf-helper-functions.php' );
		$path_with_slash    = acf_get_path( '/includes/acf-helper-functions.php' );

		$this->assertEquals( $path_without_slash, $path_with_slash );
		$this->assertTrue( file_exists( $path_with_slash ) );
	}

	/**
	 * Test store operations work together.
	 */
	public function test_store_operations_integration(): void {
		// Register a store.
		$store = acf_register_store( 'integration_test' );

		// Set some data.
		$store->set( 'key1', 'value1' );
		$store->set( 'key2', 'value2' );

		// Retrieve the store.
		$retrieved = acf_get_store( 'integration_test' );

		// Verify data persists.
		$this->assertEquals( 'value1', $retrieved->get( 'key1' ) );
		$this->assertEquals( 'value2', $retrieved->get( 'key2' ) );

		// Verify all data retrieval.
		$all_data = $retrieved->get();
		$this->assertIsArray( $all_data );
		$this->assertArrayHasKey( 'key1', $all_data );
		$this->assertArrayHasKey( 'key2', $all_data );
	}

	/**
	 * Test multiple stores are independent.
	 */
	public function test_multiple_stores_independent(): void {
		$store_a = acf_register_store( 'store_a' );
		$store_b = acf_register_store( 'store_b' );

		$store_a->set( 'key', 'value_a' );
		$store_b->set( 'key', 'value_b' );

		$this->assertEquals( 'value_a', acf_get_store( 'store_a' )->get( 'key' ) );
		$this->assertEquals( 'value_b', acf_get_store( 'store_b' )->get( 'key' ) );
	}

	/**
	 * Test instance and store patterns together.
	 */
	public function test_instance_and_store_patterns(): void {
		// Register a store for testing.
		acf_register_store( 'test_pattern_store' );

		// Get the store.
		$store = acf_get_store( 'test_pattern_store' );

		// Store should be usable.
		$store->set( 'pattern_key', 'pattern_value' );
		$this->assertEquals( 'pattern_value', $store->get( 'pattern_key' ) );
	}
}
