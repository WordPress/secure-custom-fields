<?php
/**
 * Tests for SCF_Post_Type_Abilities class
 *
 * Note: Full integration tests including create/get/update/delete flows are covered
 * by e2e tests in tests/e2e/abilities-post-types.spec.ts. These PHPUnit tests focus
 * on callback structure and error handling that can be tested in WorDBless.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load ACF internal post type class to register the post-type instance.
require_once dirname( __DIR__, 4 ) . '/includes/post-types/class-acf-post-type.php';

// Load the abilities classes after ACF classes are loaded.
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-internal-post-type-abilities.php';
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-post-type-abilities.php';

/**
 * Test SCF Post Type Abilities callbacks
 */
class Test_SCF_Post_Type_Abilities extends BaseTestCase {

	/**
	 * Instance of SCF_Post_Type_Abilities for testing
	 *
	 * @var SCF_Post_Type_Abilities
	 */
	private $abilities;

	/**
	 * Test post type data
	 *
	 * @var array
	 */
	private $test_post_type = array(
		'key'       => 'post_type_phpunit_test',
		'title'     => 'PHPUnit Test Type',
		'post_type' => 'phpunit_test',
	);

	/**
	 * Setup test fixtures
	 */
	public function setUp(): void {
		parent::setUp();
		$this->abilities = acf_get_instance( 'SCF_Post_Type_Abilities' );
	}

	/**
	 * Test list_callback returns array
	 */
	public function test_list_post_types_returns_array() {
		$result = $this->abilities->list_callback( array() );

		$this->assertIsArray( $result );
	}

	/**
	 * Test list_callback with filter parameter
	 */
	public function test_list_post_types_with_filter() {
		$result = $this->abilities->list_callback(
			array( 'filter' => array( 'active' => true ) )
		);

		$this->assertIsArray( $result );
	}

	/**
	 * Test create_callback returns array with key
	 */
	public function test_create_post_type_returns_array_with_key() {
		$result = $this->abilities->create_callback( $this->test_post_type );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'key', $result );
		$this->assertEquals( $this->test_post_type['key'], $result['key'] );
	}

	/**
	 * Test create_callback returns array with title
	 */
	public function test_create_post_type_returns_array_with_title() {
		$result = $this->abilities->create_callback( $this->test_post_type );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertEquals( $this->test_post_type['title'], $result['title'] );
	}

	/**
	 * Test get_callback returns WP_Error for non-existent ID
	 */
	public function test_get_post_type_not_found_returns_error() {
		$result = $this->abilities->get_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'post_type_not_found', $result->get_error_code() );
	}

	/**
	 * Test get_callback returns 404 status for non-existent key
	 */
	public function test_get_post_type_not_found_returns_404_status() {
		$result = $this->abilities->get_callback(
			array( 'identifier' => 'nonexistent_post_type' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$error_data = $result->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test update_callback returns WP_Error for non-existent ID
	 */
	public function test_update_post_type_not_found_returns_error() {
		$result = $this->abilities->update_callback(
			array(
				'ID'    => 999999,
				'title' => 'Should Fail',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'post_type_not_found', $result->get_error_code() );
	}

	/**
	 * Test delete_callback returns WP_Error for non-existent ID
	 */
	public function test_delete_post_type_not_found_returns_error() {
		$result = $this->abilities->delete_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'post_type_not_found', $result->get_error_code() );
	}

	/**
	 * Test delete_callback returns 404 status for non-existent key
	 */
	public function test_delete_post_type_not_found_returns_404_status() {
		$result = $this->abilities->delete_callback(
			array( 'identifier' => 'nonexistent_delete_target' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$error_data = $result->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test duplicate_callback returns WP_Error for non-existent ID
	 */
	public function test_duplicate_post_type_not_found_returns_error() {
		$result = $this->abilities->duplicate_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'post_type_not_found', $result->get_error_code() );
	}

	/**
	 * Test export_callback returns WP_Error for non-existent ID
	 */
	public function test_export_post_type_not_found_returns_error() {
		$result = $this->abilities->export_callback(
			array( 'identifier' => 999999 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'post_type_not_found', $result->get_error_code() );
	}

	/**
	 * Test import_callback returns array
	 */
	public function test_import_post_type_returns_array() {
		$result = $this->abilities->import_callback( $this->test_post_type );

		$this->assertIsArray( $result );
	}

	/**
	 * Test import_callback returns correct post_type
	 */
	public function test_import_post_type_returns_correct_post_type() {
		$result = $this->abilities->import_callback( $this->test_post_type );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->test_post_type['post_type'], $result['post_type'] );
	}

	/**
	 * Test import_callback returns correct title
	 */
	public function test_import_post_type_returns_correct_title() {
		$result = $this->abilities->import_callback( $this->test_post_type );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->test_post_type['title'], $result['title'] );
	}
}
