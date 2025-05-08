<?php
/**
 * Test Secure Custom Fields REST API Types Endpoint functionality.
 *
 * @package wordpress/secure-custom-fields
 * @group rest-api
 */

use WorDBless\BaseTestCase;

acf_include( 'includes/rest-api/class-acf-rest-types-endpoint.php' );

/**
 * Tests for the SCF_Rest_Types_Endpoint class.
 */
class Test_REST_Types_Endpoint extends BaseTestCase {

	/**
	 * The endpoint instance being tested.
	 *
	 * @var SCF_Rest_Types_Endpoint
	 */
	protected $endpoint;

	/**
	 * Reflection for accessing private methods.
	 *
	 * @var ReflectionClass
	 */
	protected $reflection;

	/**
	 * The get_source_post_types method.
	 *
	 * @var ReflectionMethod
	 */
	protected $source_method;

	/**
	 * The test post type name.
	 *
	 * @var string
	 */
	protected $test_post_type = 'test-post-type';

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		// Create the endpoint instance.
		$this->endpoint = new SCF_Rest_Types_Endpoint();

		// Set up reflection for accessing private methods.
		$this->reflection = new ReflectionClass( $this->endpoint );

		// Access the get_source_post_types method.
		$this->source_method = $this->reflection->getMethod( 'get_source_post_types' );
		$this->source_method->setAccessible( true );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Check if our test post type needs to be unregistered.
		if ( post_type_exists( $this->test_post_type ) ) {
			unregister_post_type( $this->test_post_type );
		}

		parent::tear_down();
	}

	/**
	 * Test that the SCF_Rest_Types_Endpoint class exists.
	 */
	public function test_endpoint_class_exists() {
		$this->assertTrue( class_exists( 'SCF_Rest_Types_Endpoint' ) );
	}

	/**
	 * Test that the source parameter is properly added to collection params.
	 */
	public function test_add_collection_params() {
		// Test with empty parameters.
		$empty_params          = array();
		$modified_empty_params = $this->endpoint->add_collection_params( $empty_params );

		$this->assertArrayHasKey( 'source', $modified_empty_params );
		$this->assertCount( 1, $modified_empty_params );

		// Test with existing parameters.
		$existing_params          = array(
			'context' => array(
				'default' => 'view',
				'enum'    => array( 'view', 'embed', 'edit' ),
			),
		);
		$modified_existing_params = $this->endpoint->add_collection_params( $existing_params );

		$this->assertArrayHasKey( 'source', $modified_existing_params );
		$this->assertArrayHasKey( 'context', $modified_existing_params );
		$this->assertCount( 2, $modified_existing_params );
		$this->assertEquals( 'view', $modified_existing_params['context']['default'] );

		// Check parameter properties.
		$source_param = $modified_existing_params['source'];
		$this->assertEquals( 'string', $source_param['type'] );
		$this->assertFalse( $source_param['required'] );
		$this->assertContains( 'core', $source_param['enum'] );
		$this->assertContains( 'scf', $source_param['enum'] );
		$this->assertContains( 'other', $source_param['enum'] );
		$this->assertArrayHasKey( 'validate_callback', $source_param );
		$this->assertArrayHasKey( 'sanitize_callback', $source_param );
	}

	/**
	 * Test the get_source_post_types method for SCF post types
	 */
	public function test_get_source_post_types_scf() {
		$scf_types = $this->source_method->invoke( $this->endpoint, 'scf' );

		$this->assertIsArray( $scf_types, 'SCF types should be an array' );

		// Check for SCF post types.
		$this->assertContains( 'acf-field-group', $scf_types );
		$this->assertContains( 'acf-post-type', $scf_types );
		$this->assertContains( 'acf-taxonomy', $scf_types );
		$this->assertContains( 'acf-ui-options-page', $scf_types );

		// Should not include core types.
		$this->assertNotContains( 'post', $scf_types );
		$this->assertNotContains( 'page', $scf_types );
	}

	/**
	 * Test the get_source_post_types method for core post types
	 */
	public function test_get_source_post_types_core() {
		$core_types = $this->source_method->invoke( $this->endpoint, 'core' );

		$this->assertIsArray( $core_types, 'Core types should be an array' );

		// Check for core post types.
		$this->assertContains( 'post', $core_types );
		$this->assertContains( 'page', $core_types );

		// Should not include SCF types.
		$this->assertNotContains( 'acf-field-group', $core_types );
		$this->assertNotContains( 'acf-post-type', $core_types );
	}

	/**
	 * Test the get_source_post_types method for other post types
	 */
	public function test_get_source_post_types_other() {
		// Register a test post type.
		register_post_type(
			$this->test_post_type,
			array(
				'labels' => array( 'name' => 'Test Post Type' ),
				'public' => true,
			)
		);

		$other_types = $this->source_method->invoke( $this->endpoint, 'other' );

		$this->assertIsArray( $other_types, 'Other types should be an array' );

		// Should include our test post type.
		$this->assertContains( $this->test_post_type, $other_types );

		// Should not include core or SCF types.
		$this->assertNotContains( 'post', $other_types );
		$this->assertNotContains( 'page', $other_types );
		$this->assertNotContains( 'acf-field-group', $other_types );
	}

	/**
	 * Test the get_source_post_types method with an invalid source parameter
	 */
	public function test_get_source_post_types_invalid() {
		$invalid_types = $this->source_method->invoke( $this->endpoint, 'invalid' );

		$this->assertIsArray( $invalid_types );
		$this->assertEmpty( $invalid_types, 'Invalid source should return empty array' );
	}

	/**
	 * Test the source parameter definition.
	 */
	public function test_source_parameter_definition() {
		$param_method = $this->reflection->getMethod( 'get_source_param_definition' );
		$param_method->setAccessible( true );

		// Test without validation callbacks.
		$param_def = $param_method->invoke( $this->endpoint, false );
		$this->assertEquals( 'string', $param_def['type'] );
		$this->assertFalse( $param_def['required'] );
		$this->assertContains( 'core', $param_def['enum'] );
		$this->assertContains( 'scf', $param_def['enum'] );
		$this->assertContains( 'other', $param_def['enum'] );
		$this->assertCount( 3, $param_def['enum'] );

		// Test with validation callbacks.
		$param_def_with_validation = $param_method->invoke( $this->endpoint, true );
		$this->assertEquals( 'string', $param_def_with_validation['type'] );
		$this->assertFalse( $param_def_with_validation['required'] );
		$this->assertContains( 'core', $param_def_with_validation['enum'] );
		$this->assertContains( 'scf', $param_def_with_validation['enum'] );
		$this->assertContains( 'other', $param_def_with_validation['enum'] );
		$this->assertArrayHasKey( 'validate_callback', $param_def_with_validation );
		$this->assertArrayHasKey( 'sanitize_callback', $param_def_with_validation );
		$this->assertEquals( 'rest_validate_request_arg', $param_def_with_validation['validate_callback'] );
		$this->assertEquals( 'sanitize_text_field', $param_def_with_validation['sanitize_callback'] );
	}
}
