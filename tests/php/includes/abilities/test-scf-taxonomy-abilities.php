<?php
/**
 * Tests for SCF_Taxonomy_Abilities class
 *
 * Note: Full integration tests including create/get/update/delete flows are covered
 * by e2e tests in tests/e2e/abilities-taxonomies.spec.ts. These PHPUnit tests focus
 * on callback structure and error handling that can be tested in WorDBless.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the abilities class directly since wp_register_ability doesn't exist in WorDBless.
require_once dirname( __DIR__, 3 ) . '/../includes/abilities/class-scf-taxonomy-abilities.php';

/**
 * Test SCF Taxonomy Abilities callbacks
 */
class Test_SCF_Taxonomy_Abilities extends BaseTestCase {

	/**
	 * Instance of SCF_Taxonomy_Abilities for testing
	 *
	 * @var SCF_Taxonomy_Abilities
	 */
	private $abilities;

	/**
	 * Test taxonomy data
	 *
	 * @var array
	 */
	private $test_taxonomy = array(
		'key'      => 'taxonomy_phpunit_test',
		'title'    => 'PHPUnit Test Taxonomy',
		'taxonomy' => 'phpunit_test',
	);

	/**
	 * Setup test fixtures
	 */
	public function setUp(): void {
		parent::setUp();
		$this->abilities = acf_get_instance( 'SCF_Taxonomy_Abilities' );
	}

	/**
	 * Test list_taxonomies_callback returns array
	 */
	public function test_list_taxonomies_returns_array() {
		$result = $this->abilities->list_taxonomies_callback( array() );

		$this->assertIsArray( $result );
	}

	/**
	 * Test list_taxonomies_callback with filter parameter
	 */
	public function test_list_taxonomies_with_filter() {
		$result = $this->abilities->list_taxonomies_callback(
			array( 'filter' => array( 'active' => true ) )
		);

		$this->assertIsArray( $result );
	}

	/**
	 * Test create_taxonomy_callback returns array with key
	 */
	public function test_create_taxonomy_returns_array_with_key() {
		$result = $this->abilities->create_taxonomy_callback( $this->test_taxonomy );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'key', $result );
		$this->assertEquals( $this->test_taxonomy['key'], $result['key'] );
	}

	/**
	 * Test create_taxonomy_callback returns array with title
	 */
	public function test_create_taxonomy_returns_array_with_title() {
		$result = $this->abilities->create_taxonomy_callback( $this->test_taxonomy );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertEquals( $this->test_taxonomy['title'], $result['title'] );
	}

	/**
	 * Test get_taxonomy_callback returns WP_Error for non-existent ID
	 */
	public function test_get_taxonomy_not_found_returns_error() {
		$result = $this->abilities->get_taxonomy_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'taxonomy_not_found', $result->get_error_code() );
	}

	/**
	 * Test get_taxonomy_callback returns 404 status for non-existent key
	 */
	public function test_get_taxonomy_not_found_returns_404_status() {
		$result = $this->abilities->get_taxonomy_callback(
			array( 'identifier' => 'nonexistent_taxonomy' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$error_data = $result->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test update_taxonomy_callback returns WP_Error for non-existent ID
	 */
	public function test_update_taxonomy_not_found_returns_error() {
		$result = $this->abilities->update_taxonomy_callback(
			array(
				'ID'    => 999999,
				'title' => 'Should Fail',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'taxonomy_not_found', $result->get_error_code() );
	}

	/**
	 * Test delete_taxonomy_callback returns WP_Error for non-existent ID
	 */
	public function test_delete_taxonomy_not_found_returns_error() {
		$result = $this->abilities->delete_taxonomy_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'taxonomy_not_found', $result->get_error_code() );
	}

	/**
	 * Test delete_taxonomy_callback returns 404 status for non-existent key
	 */
	public function test_delete_taxonomy_not_found_returns_404_status() {
		$result = $this->abilities->delete_taxonomy_callback(
			array( 'identifier' => 'nonexistent_delete_target' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$error_data = $result->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test duplicate_taxonomy_callback returns WP_Error for non-existent ID
	 */
	public function test_duplicate_taxonomy_not_found_returns_error() {
		$result = $this->abilities->duplicate_taxonomy_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'taxonomy_not_found', $result->get_error_code() );
	}

	/**
	 * Test export_taxonomy_callback returns WP_Error for non-existent ID
	 */
	public function test_export_taxonomy_not_found_returns_error() {
		$result = $this->abilities->export_taxonomy_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'taxonomy_not_found', $result->get_error_code() );
	}

	/**
	 * Test import_taxonomy_callback returns array
	 */
	public function test_import_taxonomy_returns_array() {
		$result = $this->abilities->import_taxonomy_callback( $this->test_taxonomy );

		$this->assertIsArray( $result );
	}

	/**
	 * Test import_taxonomy_callback returns correct taxonomy
	 */
	public function test_import_taxonomy_returns_correct_taxonomy() {
		$result = $this->abilities->import_taxonomy_callback( $this->test_taxonomy );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->test_taxonomy['taxonomy'], $result['taxonomy'] );
	}

	/**
	 * Test import_taxonomy_callback returns correct title
	 */
	public function test_import_taxonomy_returns_correct_title() {
		$result = $this->abilities->import_taxonomy_callback( $this->test_taxonomy );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->test_taxonomy['title'], $result['title'] );
	}
}
