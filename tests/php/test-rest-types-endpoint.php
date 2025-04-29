<?php
/**
 * Tests for the SCF_Rest_Types_Endpoint class.
 *
 * @package secure-custom-fields
 */

/**
 * Class SCF_Rest_Types_Endpoint_Test
 *
 * Tests for the SCF_Rest_Types_Endpoint class which extends the /wp/v2/types endpoint.
 *
 * @package secure-custom-fields
 */
class SCF_Rest_Types_Endpoint_Test extends BaseTestCase {

	/**
	 * The SCF_Rest_Types_Endpoint instance being tested.
	 *
	 * @var SCF_Rest_Types_Endpoint
	 */
	private $endpoint;

	/**
	 * Set up test environment and create test users.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create test user with administrator role
		$this->admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );

		// Create test user with editor role
		$this->editor_user = $this->factory->user->create( array( 'role' => 'editor' ) );

		// Create test user with subscriber role
		$this->subscriber_user = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		// Initialize the endpoint
		$this->endpoint = new SCF_Rest_Types_Endpoint();
	}

	/**
	 * Test endpoint initialization
	 */
	public function test_endpoint_initialization() {
		// Check if the filter is added
		$this->assertNotFalse( has_filter( 'rest_pre_dispatch', array( $this->endpoint, 'initialize' ) ) );
	}

	/**
	 * Test field registration
	 */
	public function test_field_registration() {
		// Call the register_field method directly
		$this->endpoint->register_field();

		// Get the registered fields for 'type'
		$registered_fields = rest_get_field_registry()->get_all_fields( 'type' );

		// Check if our field is registered
		$this->assertArrayHasKey( 'scf_fields', $registered_fields );
	}

	/**
	 * Test get_scf_fields method
	 */
	public function test_get_scf_fields() {
		// Create a test field group
		$field_group = array(
			'key'      => 'group_test',
			'title'    => 'Test Field Group',
			'fields'   => array(
				array(
					'key'   => 'field_test',
					'label' => 'Test Field',
					'name'  => 'test_field',
					'type'  => 'text',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
		);

		acf_add_local_field_group( $field_group );

		// Create a mock post type object
		$post_type_object = array(
			'slug' => 'post',
		);

		// Call the get_scf_fields method
		$fields = $this->endpoint->get_scf_fields( $post_type_object );

		// Check if fields are returned correctly
		$this->assertIsArray( $fields );
		$this->assertCount( 1, $fields );
		$this->assertEquals( 'Test Field', $fields[0]['label'] );
		$this->assertEquals( 'text', $fields[0]['type'] );
	}

	/**
	 * Test get_field_schema method
	 */
	public function test_get_field_schema() {
		// Use reflection to access the private method
		$reflection = new ReflectionClass( $this->endpoint );
		$method     = $reflection->getMethod( 'get_field_schema' );
		$method->setAccessible( true );

		// Call the method
		$schema = $method->invoke( $this->endpoint );

		// Check if schema is returned correctly
		$this->assertIsArray( $schema );
		$this->assertEquals( 'Fields attached to this post type.', $schema['description'] );
		$this->assertEquals( 'array', $schema['type'] );
		$this->assertArrayHasKey( 'items', $schema );
		$this->assertArrayHasKey( 'properties', $schema['items'] );
		$this->assertArrayHasKey( 'label', $schema['items']['properties'] );
		$this->assertArrayHasKey( 'type', $schema['items']['properties'] );
	}

	/**
	 * Test initialize method with REST API disabled
	 */
	public function test_initialize_with_rest_api_disabled() {
		// Mock acf_get_setting to return false
		global $acf;
		$original_setting                    = isset( $acf['settings']['rest_api_enabled'] ) ? $acf['settings']['rest_api_enabled'] : null;
		$acf['settings']['rest_api_enabled'] = false;

		// Create a mock request and response
		$request  = new WP_REST_Request( 'GET', '/wp/v2/types' );
		$response = new WP_REST_Response();
		$handler  = new WP_REST_Server();

		// Call the initialize method
		$result = $this->endpoint->initialize( $response, $handler, $request );

		// Check if the original response is returned without modification
		$this->assertSame( $response, $result );

		// Restore the original setting
		$acf['settings']['rest_api_enabled'] = $original_setting;
	}

	/**
	 * Test initialize method with REST API enabled
	 */
	public function test_initialize_with_rest_api_enabled() {
		// Mock acf_get_setting to return true
		global $acf;
		$original_setting                    = isset( $acf['settings']['rest_api_enabled'] ) ? $acf['settings']['rest_api_enabled'] : null;
		$acf['settings']['rest_api_enabled'] = true;

		// Create a mock request and response
		$request  = new WP_REST_Request( 'GET', '/wp/v2/types' );
		$response = new WP_REST_Response();
		$handler  = new WP_REST_Server();

		// Call the initialize method
		$result = $this->endpoint->initialize( $response, $handler, $request );

		// Check if the original response is returned
		$this->assertSame( $response, $result );

		// Restore the original setting
		$acf['settings']['rest_api_enabled'] = $original_setting;
	}
}
