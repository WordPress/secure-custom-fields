<?php
/**
 * Tests for the create taxonomy REST endpoint.
 *
 * Covers the permission gate, duplicate handling, validation and the shape of
 * a successful response for the block editor sidebar route.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the REST endpoint class under test.
acf_include( 'includes/rest-api/class-scf-rest-create-taxonomy.php' );

/**
 * Test SCF_Rest_Create_Taxonomy_Endpoint.
 *
 * @covers SCF_Rest_Create_Taxonomy_Endpoint
 * @group rest-api
 */
class SCFRestCreateTaxonomyTest extends BaseTestCase {

	/**
	 * The endpoint instance.
	 *
	 * @var SCF_Rest_Create_Taxonomy_Endpoint
	 */
	private $endpoint;

	/**
	 * The admin user ID used for authorized requests.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// The internal post type instances are needed for persistence.
		if ( ! acf_get_internal_post_type_instance( 'acf-taxonomy' ) ) {
			do_action( 'plugins_loaded' );
			do_action( 'init' );
		}

		$this->endpoint = new SCF_Rest_Create_Taxonomy_Endpoint();

		$this->admin_id = wp_insert_user(
			array(
				'user_login' => 'create_taxonomy_admin_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
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

		delete_option( 'scf_beta_feature_create_taxonomies_enabled' );

		wp_set_current_user( 0 );

		if ( $this->admin_id ) {
			wp_delete_user( $this->admin_id );
		}

		parent::tearDown();
	}

	/**
	 * Builds a request for the create route.
	 *
	 * @param array $params Request parameters.
	 * @return WP_REST_Request
	 */
	private function make_request( array $params = array() ) {
		$request = new WP_REST_Request( 'POST', '/scf/v1/taxonomies' );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}

	/**
	 * Default valid parameters.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	private function valid_params( array $overrides = array() ) {
		return array_merge(
			array(
				'singular_label' => 'Genre',
				'plural_label'   => 'Genres',
				'taxonomy'       => 'test_' . uniqid(),
				'object_type'    => array( 'post' ),
				'hierarchical'   => true,
			),
			$overrides
		);
	}

	/**
	 * Test the route is registered while the beta feature is enabled.
	 */
	public function test_route_is_registered() {
		update_option( 'scf_beta_feature_create_taxonomies_enabled', true );

		$this->endpoint->register_routes();

		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/scf/v1/taxonomies', $routes );
	}

	/**
	 * Test the route is absent while the beta feature is disabled.
	 */
	public function test_route_not_registered_when_beta_disabled() {
		delete_option( 'scf_beta_feature_create_taxonomies_enabled' );

		// rest_get_server() reuses its server for the whole process, so a fresh
		// one is needed to see what this call alone would register.
		$GLOBALS['wp_rest_server'] = new WP_REST_Server();

		$this->endpoint->register_routes();

		$routes = rest_get_server()->get_routes();
		$this->assertArrayNotHasKey( '/scf/v1/taxonomies', $routes );
	}

	/**
	 * Reads a created taxonomy from its stored post.
	 *
	 * WorDBless keeps the slashed serialized content, so it is unslashed here
	 * to match how WordPress stores and reads the data in a real install.
	 *
	 * @param int $post_id The taxonomy post ID.
	 * @return array
	 */
	private function read_taxonomy( $post_id ) {
		$post = get_post( $post_id );

		return (array) maybe_unserialize( wp_unslash( $post->post_content ) );
	}

	/**
	 * Test a valid request creates a taxonomy.
	 */
	public function test_creates_taxonomy() {
		$params = $this->valid_params();
		$result = $this->endpoint->create_taxonomy( $this->make_request( $params ) );

		$this->assertInstanceOf( 'WP_REST_Response', $result );
		$this->assertEquals( 201, $result->get_status() );

		$data = $result->get_data();
		$this->assertNotEmpty( $data['id'] );
		$this->assertEquals( $params['taxonomy'], $data['taxonomy'] );

		$created = $this->read_taxonomy( $data['id'] );
		$this->assertEquals( $params['taxonomy'], $created['taxonomy'] );
		$this->assertEquals( 'Genres', $created['labels']['name'] );
		$this->assertEquals( 'Genre', $created['labels']['singular_name'] );
		$this->assertTrue( (bool) $created['hierarchical'] );
	}

	/**
	 * Test duplicate taxonomy keys are rejected.
	 */
	public function test_rejects_duplicate_key() {
		$params = $this->valid_params();

		$first = $this->endpoint->create_taxonomy( $this->make_request( $params ) );
		$this->assertInstanceOf( 'WP_REST_Response', $first );

		$second = $this->endpoint->create_taxonomy(
			$this->make_request(
				array_merge( $params, array( 'taxonomy' => $params['taxonomy'] ) )
			)
		);

		$this->assertInstanceOf( 'WP_Error', $second );
		$this->assertEquals( 'scf_taxonomy_exists', $second->get_error_code() );
	}

	/**
	 * Test reserved and invalid keys are rejected by schema validation.
	 */
	public function test_rejects_invalid_key() {
		$result = $this->endpoint->create_taxonomy(
			$this->make_request( $this->valid_params( array( 'taxonomy' => 'category' ) ) )
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'scf_invalid_taxonomy', $result->get_error_code() );
	}

	/**
	 * Test uppercase keys are rejected.
	 */
	public function test_rejects_uppercase_key() {
		$result = $this->endpoint->create_taxonomy(
			$this->make_request( $this->valid_params( array( 'taxonomy' => 'Genre' ) ) )
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'scf_invalid_taxonomy', $result->get_error_code() );
	}

	/**
	 * Test post types that do not exist are filtered out.
	 */
	public function test_filters_unknown_post_types() {
		$params = $this->valid_params(
			array( 'object_type' => array( 'post', 'not_a_real_post_type' ) )
		);
		$result = $this->endpoint->create_taxonomy( $this->make_request( $params ) );

		$this->assertInstanceOf( 'WP_REST_Response', $result );

		$created = $this->read_taxonomy( $result->get_data()['id'] );
		$this->assertEquals( array( 'post' ), $created['object_type'] );
	}

	/**
	 * Test the permission callback requires the SCF capability.
	 */
	public function test_permission_requires_capability() {
		wp_set_current_user( 0 );

		$this->assertFalse( scf_current_user_has_capability() );

		$result = $this->endpoint->create_taxonomy(
			$this->make_request( $this->valid_params() )
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'scf_rest_cannot_create', $result->get_error_code() );
	}
}
