<?php
/**
 * Tests for ACF_Rest_Request class.
 *
 * Tests REST request parsing, URL parameter extraction, and object type detection.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the REST API classes.
acf_include( 'includes/rest-api/class-acf-rest-request.php' );

/**
 * Class Test_ACF_Rest_Request
 *
 * Tests for the ACF REST Request parser class.
 *
 * @group rest-api
 * @group p0-critical
 */
class Test_ACF_Rest_Request extends BaseTestCase {

	/**
	 * The request instance being tested.
	 *
	 * @var ACF_Rest_Request
	 */
	protected $request;

	/**
	 * Reflection for accessing private methods.
	 *
	 * @var ReflectionClass
	 */
	protected $reflection;

	/**
	 * Original server values.
	 *
	 * @var array
	 */
	protected $original_server;

	/**
	 * Original GET values.
	 *
	 * @var array
	 */
	protected $original_get;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->request    = new ACF_Rest_Request();
		$this->reflection = new ReflectionClass( $this->request );

		// Store original superglobals.
		$this->original_server = $_SERVER;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test environment, restoring after test.
		$this->original_get = $_GET;
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		// Restore original superglobals.
		$_SERVER = $this->original_server;
		$_GET    = $this->original_get;

		parent::tear_down();
	}

	// =========================================================================
	// Property Access Tests
	// =========================================================================

	/**
	 * Test readonly properties are accessible via __get.
	 */
	public function test_readonly_properties_accessible() {
		// These should return null initially but should be accessible.
		$this->assertNull( $this->request->object_type );
		$this->assertNull( $this->request->object_sub_type );
		$this->assertNull( $this->request->child_object_type );
		$this->assertNull( $this->request->http_method );
	}

	/**
	 * Test non-readonly properties return null.
	 */
	public function test_non_readonly_properties_return_null() {
		$this->assertNull( $this->request->non_existent_property );
		$this->assertNull( $this->request->current_route );
		$this->assertNull( $this->request->supported_routes );
	}

	// =========================================================================
	// HTTP Method Detection Tests
	// =========================================================================

	/**
	 * Test default HTTP method is GET.
	 */
	public function test_default_http_method_is_get() {
		unset( $_SERVER['REQUEST_METHOD'] );

		$this->request->parse_request( null );

		$this->assertEquals( 'GET', $this->request->http_method );
	}

	/**
	 * Test HTTP method from REQUEST_METHOD.
	 *
	 * @dataProvider http_method_provider
	 *
	 * @param string $method   The HTTP method to test.
	 * @param string $expected The expected result.
	 */
	public function test_http_method_from_request_method( $method, $expected ) {
		$_SERVER['REQUEST_METHOD'] = $method;

		$request = new ACF_Rest_Request();
		$request->parse_request( null );

		$this->assertEquals( $expected, $request->http_method );
	}

	/**
	 * Data provider for HTTP method tests.
	 *
	 * @return array
	 */
	public function http_method_provider() {
		return array(
			'GET method'     => array( 'GET', 'GET' ),
			'POST method'    => array( 'POST', 'POST' ),
			'PUT method'     => array( 'PUT', 'PUT' ),
			'PATCH method'   => array( 'PATCH', 'PATCH' ),
			'DELETE method'  => array( 'DELETE', 'DELETE' ),
			'OPTIONS method' => array( 'OPTIONS', 'OPTIONS' ),
			'HEAD method'    => array( 'HEAD', 'HEAD' ),
			'lowercase get'  => array( 'get', 'GET' ),
			'lowercase post' => array( 'post', 'POST' ),
		);
	}

	/**
	 * Test HTTP method override via _method GET parameter.
	 */
	public function test_http_method_override_via_get_parameter() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET['_method']           = 'PUT';

		$request = new ACF_Rest_Request();
		$request->parse_request( null );

		$this->assertEquals( 'PUT', $request->http_method );
	}

	/**
	 * Test HTTP method override via X-HTTP-Method-Override header.
	 */
	public function test_http_method_override_via_header() {
		$_SERVER['REQUEST_METHOD']              = 'POST';
		$_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'DELETE';

		$request = new ACF_Rest_Request();
		$request->parse_request( null );

		$this->assertEquals( 'DELETE', $request->http_method );
	}

	/**
	 * Test _method parameter takes precedence over header.
	 */
	public function test_method_parameter_takes_precedence() {
		$_SERVER['REQUEST_METHOD']              = 'POST';
		$_GET['_method']                        = 'PATCH';
		$_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'DELETE';

		$request = new ACF_Rest_Request();
		$request->parse_request( null );

		$this->assertEquals( 'PATCH', $request->http_method );
	}

	// =========================================================================
	// Route Parsing Tests
	// =========================================================================

	/**
	 * Test parse_request with WP_REST_Request object.
	 */
	public function test_parse_request_with_wp_rest_request() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/posts/123' );

		$this->request->parse_request( $wp_request );

		// The route should be set from the request.
		$route_prop = $this->reflection->getProperty( 'current_route' );
		$route_prop->setAccessible( true );

		$this->assertEquals( '/wp/v2/posts/123', $route_prop->getValue( $this->request ) );
	}

	/**
	 * Test parse_request with null falls back to global.
	 */
	public function test_parse_request_with_null_uses_global() {
		// Save and restore the global to avoid breaking other tests.
		$original_wp = isset( $GLOBALS['wp'] ) ? $GLOBALS['wp'] : null;

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Required to test global fallback behavior.
		$GLOBALS['wp']                           = new stdClass();
		$GLOBALS['wp']->query_vars               = array();
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->request->parse_request( null );

		$route_prop = $this->reflection->getProperty( 'current_route' );
		$route_prop->setAccessible( true );

		$this->assertEquals( '/wp/v2/posts', $route_prop->getValue( $this->request ) );

		// Restore the original global.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original value.
		$GLOBALS['wp'] = $original_wp;
	}

	// =========================================================================
	// URL Parameter Tests
	// =========================================================================

	/**
	 * Test get_url_param returns null for non-existent param.
	 */
	public function test_get_url_param_returns_null_for_non_existent() {
		$result = $this->request->get_url_param( 'non_existent' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_url_param returns value when set.
	 */
	public function test_get_url_param_returns_value_when_set() {
		$url_params_prop = $this->reflection->getProperty( 'url_params' );
		$url_params_prop->setAccessible( true );
		$url_params_prop->setValue( $this->request, array( 'id' => '123' ) );

		$result = $this->request->get_url_param( 'id' );

		$this->assertEquals( '123', $result );
	}

	// =========================================================================
	// Object Type Detection Tests
	// =========================================================================

	/**
	 * Test object type detection for posts route.
	 */
	public function test_object_type_detection_for_posts() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/posts/123' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'post', $request->object_type );
		$this->assertEquals( 'post', $request->object_sub_type );
	}

	/**
	 * Test object type detection for pages route.
	 */
	public function test_object_type_detection_for_pages() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/pages/456' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'post', $request->object_type );
		$this->assertEquals( 'page', $request->object_sub_type );
	}

	/**
	 * Test object type detection for users route.
	 */
	public function test_object_type_detection_for_users() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/users/1' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'user', $request->object_type );
		$this->assertEquals( 'user', $request->object_sub_type );
	}

	/**
	 * Test object type detection for users/me route.
	 */
	public function test_object_type_detection_for_users_me() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/users/me' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'user', $request->object_type );
		$this->assertEquals( 'user', $request->object_sub_type );
	}

	/**
	 * Test object type detection for comments route.
	 */
	public function test_object_type_detection_for_comments() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/comments/789' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'comment', $request->object_type );
		$this->assertEquals( 'comment', $request->object_sub_type );
	}

	/**
	 * Test object type detection for categories route.
	 */
	public function test_object_type_detection_for_categories() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/categories/10' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'term', $request->object_type );
		$this->assertEquals( 'category', $request->object_sub_type );
	}

	/**
	 * Test object type detection for tags route.
	 */
	public function test_object_type_detection_for_tags() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/tags/20' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'term', $request->object_type );
		$this->assertEquals( 'post_tag', $request->object_sub_type );
	}

	/**
	 * Test object type detection for collection routes (no ID).
	 */
	public function test_object_type_detection_for_collection() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/posts' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'post', $request->object_type );
		$this->assertEquals( 'post', $request->object_sub_type );
	}

	// =========================================================================
	// Child Object Type Tests (Revisions/Autosaves)
	// =========================================================================

	/**
	 * Test child object type detection for revisions.
	 */
	public function test_child_object_type_for_revisions() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/posts/123/revisions/456' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'post', $request->object_type );
		$this->assertEquals( 'post', $request->object_sub_type );
		$this->assertEquals( 'post-revision', $request->child_object_type );
	}

	/**
	 * Test child object type detection for autosaves.
	 */
	public function test_child_object_type_for_autosaves() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/posts/123/autosaves/789' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'post', $request->object_type );
		$this->assertEquals( 'post', $request->object_sub_type );
		$this->assertEquals( 'post-revision', $request->child_object_type );
	}

	/**
	 * Test child object type detection for page revisions.
	 */
	public function test_child_object_type_for_page_revisions() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/pages/100/revisions' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( 'post', $request->object_type );
		$this->assertEquals( 'page', $request->object_sub_type );
		$this->assertEquals( 'page-revision', $request->child_object_type );
	}

	// =========================================================================
	// Supported Routes Building Tests
	// =========================================================================

	/**
	 * Test that supported routes are built for post types.
	 */
	public function test_supported_routes_include_post_types() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/posts' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$routes_prop = $this->reflection->getProperty( 'supported_routes' );
		$routes_prop->setAccessible( true );
		$routes = $routes_prop->getValue( $request );

		$this->assertNotEmpty( $routes );
		$this->assertIsArray( $routes );

		// Should have routes for posts.
		$has_post_route = false;
		foreach ( $routes as $route ) {
			if ( strpos( $route, 'posts' ) !== false ) {
				$has_post_route = true;
				break;
			}
		}

		$this->assertTrue( $has_post_route, 'Should have post routes' );
	}

	/**
	 * Test that supported routes include user routes.
	 */
	public function test_supported_routes_include_users() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/users' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$routes_prop = $this->reflection->getProperty( 'supported_routes' );
		$routes_prop->setAccessible( true );
		$routes = $routes_prop->getValue( $request );

		$has_user_route = false;
		foreach ( $routes as $route ) {
			if ( strpos( $route, 'users' ) !== false ) {
				$has_user_route = true;
				break;
			}
		}

		$this->assertTrue( $has_user_route, 'Should have user routes' );
	}

	/**
	 * Test that supported routes include comment routes.
	 */
	public function test_supported_routes_include_comments() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$routes_prop = $this->reflection->getProperty( 'supported_routes' );
		$routes_prop->setAccessible( true );
		$routes = $routes_prop->getValue( $request );

		$has_comment_route = false;
		foreach ( $routes as $route ) {
			if ( strpos( $route, 'comments' ) !== false ) {
				$has_comment_route = true;
				break;
			}
		}

		$this->assertTrue( $has_comment_route, 'Should have comment routes' );
	}

	// =========================================================================
	// Private Method Tests
	// =========================================================================

	/**
	 * Test get_post_type_by_rest_base finds correct post type.
	 */
	public function test_get_post_type_by_rest_base() {
		$method = $this->reflection->getMethod( 'get_post_type_by_rest_base' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->request, 'posts' );

		$this->assertInstanceOf( 'WP_Post_Type', $result );
		$this->assertEquals( 'post', $result->name );
	}

	/**
	 * Test get_post_type_by_rest_base returns null for invalid base.
	 */
	public function test_get_post_type_by_rest_base_returns_null_for_invalid() {
		$method = $this->reflection->getMethod( 'get_post_type_by_rest_base' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->request, 'invalid_rest_base' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_taxonomy_by_rest_base finds correct taxonomy.
	 */
	public function test_get_taxonomy_by_rest_base() {
		$method = $this->reflection->getMethod( 'get_taxonomy_by_rest_base' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->request, 'categories' );

		$this->assertInstanceOf( 'WP_Taxonomy', $result );
		$this->assertEquals( 'category', $result->name );
	}

	/**
	 * Test get_taxonomy_by_rest_base returns null for invalid base.
	 */
	public function test_get_taxonomy_by_rest_base_returns_null_for_invalid() {
		$method = $this->reflection->getMethod( 'get_taxonomy_by_rest_base' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->request, 'invalid_taxonomy_base' );

		$this->assertNull( $result );
	}

	// =========================================================================
	// Edge Cases
	// =========================================================================

	/**
	 * Test parse_request handles empty route gracefully.
	 */
	public function test_parse_request_handles_empty_route() {
		// Save and restore the global to avoid breaking other tests.
		$original_wp = isset( $GLOBALS['wp'] ) ? $GLOBALS['wp'] : null;

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Required to test global fallback behavior.
		$GLOBALS['wp']                           = new stdClass();
		$GLOBALS['wp']->query_vars               = array();
		$GLOBALS['wp']->query_vars['rest_route'] = '';
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$request = new ACF_Rest_Request();
		$request->parse_request( null );

		$this->assertNull( $request->object_type );
		$this->assertNull( $request->object_sub_type );

		// Restore the original global.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original value.
		$GLOBALS['wp'] = $original_wp;
	}

	/**
	 * Test parse_request handles non-REST route gracefully.
	 */
	public function test_parse_request_handles_non_rest_route() {
		$wp_request = new WP_REST_Request( 'GET', '/some/custom/endpoint' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		// Should not match any supported route.
		$this->assertNull( $request->object_type );
	}

	/**
	 * Test URL parameters are extracted correctly.
	 */
	public function test_url_parameters_extracted() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/posts/999' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( '999', $request->get_url_param( 'id' ) );
		$this->assertEquals( 'posts', $request->get_url_param( 'rest_base' ) );
	}

	/**
	 * Test child_id parameter for revisions.
	 */
	public function test_child_id_parameter_for_revisions() {
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/posts/100/revisions/200' );

		$request = new ACF_Rest_Request();
		$request->parse_request( $wp_request );

		$this->assertEquals( '100', $request->get_url_param( 'id' ) );
		$this->assertEquals( '200', $request->get_url_param( 'child_id' ) );
		$this->assertEquals( 'revisions', $request->get_url_param( 'child_rest_base' ) );
	}
}
