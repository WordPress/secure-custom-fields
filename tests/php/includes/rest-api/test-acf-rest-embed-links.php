<?php
/**
 * Tests for ACF_Rest_Embed_Links class.
 *
 * Tests embed link preparation and loading for REST API responses.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the REST API classes.
acf_include( 'includes/rest-api/class-acf-rest-embed-links.php' );
acf_include( 'includes/rest-api/acf-rest-api-functions.php' );

/**
 * Class Test_ACF_Rest_Embed_Links
 *
 * Tests for the ACF REST Embed Links handler class.
 *
 * @group rest-api
 * @group p0-critical
 */
class Test_ACF_Rest_Embed_Links extends BaseTestCase {

	/**
	 * The embed links instance being tested.
	 *
	 * @var ACF_Rest_Embed_Links
	 */
	protected $embed_links;

	/**
	 * Reflection for accessing private properties and methods.
	 *
	 * @var ReflectionClass
	 */
	protected $reflection;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected $test_post_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->embed_links = new ACF_Rest_Embed_Links();
		$this->reflection  = new ReflectionClass( $this->embed_links );

		// Create a test post.
		$this->test_post_id = wp_insert_post(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		// Clean up test post.
		if ( $this->test_post_id ) {
			wp_delete_post( $this->test_post_id, true );
		}

		parent::tear_down();
	}

	// =========================================================================
	// Class Existence and Structure Tests
	// =========================================================================

	/**
	 * Test initial state of links property.
	 */
	public function test_initial_links_property_is_empty() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );

		$links = $links_prop->getValue( $this->embed_links );

		$this->assertIsArray( $links );
		$this->assertEmpty( $links );
	}

	// =========================================================================
	// Initialize Tests
	// =========================================================================

	/**
	 * Test initialize hooks link handlers.
	 */
	public function test_initialize_hooks_link_handlers() {
		$this->embed_links->initialize();

		// Check that the post filter is registered.
		$this->assertNotFalse(
			has_filter( 'rest_prepare_post', array( $this->embed_links, 'load_item_links' ) ),
			'rest_prepare_post filter should be registered'
		);

		// Check that the page filter is registered.
		$this->assertNotFalse(
			has_filter( 'rest_prepare_page', array( $this->embed_links, 'load_item_links' ) ),
			'rest_prepare_page filter should be registered'
		);

		// Check that the user filter is registered.
		$this->assertNotFalse(
			has_filter( 'rest_prepare_user', array( $this->embed_links, 'load_item_links' ) ),
			'rest_prepare_user filter should be registered'
		);
	}

	/**
	 * Test initialize hooks taxonomy filters.
	 */
	public function test_initialize_hooks_taxonomy_filters() {
		$this->embed_links->initialize();

		// Check category filter.
		$this->assertNotFalse(
			has_filter( 'rest_prepare_category', array( $this->embed_links, 'load_item_links' ) ),
			'rest_prepare_category filter should be registered'
		);

		// Check tag filter.
		$this->assertNotFalse(
			has_filter( 'rest_prepare_post_tag', array( $this->embed_links, 'load_item_links' ) ),
			'rest_prepare_post_tag filter should be registered'
		);
	}

	// =========================================================================
	// Links Property Direct Manipulation Tests
	// =========================================================================

	/**
	 * Test links property can be set.
	 */
	public function test_links_property_can_be_set() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );

		$test_links = array(
			'relation:https://example.com/1' => array(
				'rel'  => 'relation',
				'href' => 'https://example.com/1',
			),
		);

		$links_prop->setValue( $this->embed_links, $test_links );
		$links = $links_prop->getValue( $this->embed_links );

		$this->assertEquals( $test_links, $links );
	}

	/**
	 * Test links key format uses rel and href.
	 */
	public function test_links_key_format() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );

		$test_links = array(
			'my_rel:https://example.com/my-href' => array(
				'rel'  => 'my_rel',
				'href' => 'https://example.com/my-href',
			),
		);

		$links_prop->setValue( $this->embed_links, $test_links );
		$links = $links_prop->getValue( $this->embed_links );

		$this->assertArrayHasKey( 'my_rel:https://example.com/my-href', $links );
	}

	// =========================================================================
	// Load Item Links Tests
	// =========================================================================

	/**
	 * Test load_item_links returns response when no links.
	 */
	public function test_load_item_links_returns_response_when_no_links() {
		$response = new WP_REST_Response( array( 'id' => 123 ) );
		$item     = get_post( $this->test_post_id );
		$request  = new WP_REST_Request();

		$result = $this->embed_links->load_item_links( $response, $item, $request );

		$this->assertSame( $response, $result );
	}

	/**
	 * Test load_item_links adds links to response.
	 */
	public function test_load_item_links_adds_links_to_response() {
		// First, add some links to the internal array.
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );
		$links_prop->setValue(
			$this->embed_links,
			array(
				'relation:https://example.com/1' => array(
					'rel'        => 'relation',
					'href'       => 'https://example.com/1',
					'embeddable' => true,
				),
			)
		);

		$response = new WP_REST_Response( array( 'id' => 123 ) );
		$item     = get_post( $this->test_post_id );
		$request  = new WP_REST_Request();

		$result = $this->embed_links->load_item_links( $response, $item, $request );

		$response_links = $result->get_links();

		$this->assertArrayHasKey( 'relation', $response_links );
	}

	/**
	 * Test load_item_links clears internal links after use.
	 */
	public function test_load_item_links_clears_internal_links() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );
		$links_prop->setValue(
			$this->embed_links,
			array(
				'test:https://example.com' => array(
					'rel'  => 'test',
					'href' => 'https://example.com',
				),
			)
		);

		$response = new WP_REST_Response( array( 'id' => 123 ) );
		$item     = get_post( $this->test_post_id );
		$request  = new WP_REST_Request();

		$this->embed_links->load_item_links( $response, $item, $request );

		$links = $links_prop->getValue( $this->embed_links );

		$this->assertEmpty( $links );
	}

	/**
	 * Test load_item_links handles multiple links.
	 */
	public function test_load_item_links_handles_multiple_links() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );
		$links_prop->setValue(
			$this->embed_links,
			array(
				'relation1:https://example.com/1' => array(
					'rel'  => 'relation1',
					'href' => 'https://example.com/1',
				),
				'relation2:https://example.com/2' => array(
					'rel'  => 'relation2',
					'href' => 'https://example.com/2',
				),
				'relation3:https://example.com/3' => array(
					'rel'  => 'relation3',
					'href' => 'https://example.com/3',
				),
			)
		);

		$response = new WP_REST_Response( array( 'id' => 123 ) );
		$item     = get_post( $this->test_post_id );
		$request  = new WP_REST_Request();

		$result = $this->embed_links->load_item_links( $response, $item, $request );

		$response_links = $result->get_links();

		$this->assertArrayHasKey( 'relation1', $response_links );
		$this->assertArrayHasKey( 'relation2', $response_links );
		$this->assertArrayHasKey( 'relation3', $response_links );
	}

	/**
	 * Test load_item_links preserves additional link attributes.
	 */
	public function test_load_item_links_preserves_link_attributes() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );
		$links_prop->setValue(
			$this->embed_links,
			array(
				'custom:https://example.com' => array(
					'rel'        => 'custom',
					'href'       => 'https://example.com',
					'embeddable' => true,
					'title'      => 'Custom Link Title',
				),
			)
		);

		$response = new WP_REST_Response( array( 'id' => 123 ) );
		$item     = get_post( $this->test_post_id );
		$request  = new WP_REST_Request();

		$result = $this->embed_links->load_item_links( $response, $item, $request );

		$response_links = $result->get_links();

		$this->assertArrayHasKey( 'custom', $response_links );

		$custom_link = $response_links['custom'][0];
		$this->assertEquals( 'https://example.com', $custom_link['href'] );
		$this->assertTrue( $custom_link['attributes']['embeddable'] );
		$this->assertEquals( 'Custom Link Title', $custom_link['attributes']['title'] );
	}

	// =========================================================================
	// Integration with WP_REST_Response Tests
	// =========================================================================

	/**
	 * Test that WP_REST_Response can accept links.
	 */
	public function test_wp_rest_response_accepts_links() {
		$response = new WP_REST_Response( array( 'data' => 'test' ) );

		$response->add_link( 'test_rel', 'https://example.com/test' );

		$links = $response->get_links();

		$this->assertArrayHasKey( 'test_rel', $links );
		$this->assertEquals( 'https://example.com/test', $links['test_rel'][0]['href'] );
	}

	/**
	 * Test that embeddable flag works correctly.
	 */
	public function test_embeddable_flag_works() {
		$response = new WP_REST_Response( array( 'data' => 'test' ) );

		$response->add_link(
			'embeddable_rel',
			'https://example.com/embed',
			array( 'embeddable' => true )
		);

		$links = $response->get_links();

		$this->assertTrue( $links['embeddable_rel'][0]['attributes']['embeddable'] );
	}

	// =========================================================================
	// Link Deduplication Tests
	// =========================================================================

	/**
	 * Test that duplicate links with same rel and href are deduplicated.
	 */
	public function test_duplicate_links_are_deduplicated() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );

		// Set the same link twice with same key.
		$links_prop->setValue(
			$this->embed_links,
			array(
				'rel:https://example.com' => array(
					'rel'  => 'rel',
					'href' => 'https://example.com',
				),
			)
		);

		// Try to add same key again - should overwrite.
		$current                            = $links_prop->getValue( $this->embed_links );
		$current['rel:https://example.com'] = array(
			'rel'  => 'rel',
			'href' => 'https://example.com',
		);
		$links_prop->setValue( $this->embed_links, $current );

		$links = $links_prop->getValue( $this->embed_links );

		$this->assertCount( 1, $links );
	}

	/**
	 * Test that different links with same rel but different href are kept.
	 */
	public function test_different_hrefs_same_rel_are_kept() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );

		$links_prop->setValue(
			$this->embed_links,
			array(
				'rel:https://example.com/1' => array(
					'rel'  => 'rel',
					'href' => 'https://example.com/1',
				),
				'rel:https://example.com/2' => array(
					'rel'  => 'rel',
					'href' => 'https://example.com/2',
				),
			)
		);

		$links = $links_prop->getValue( $this->embed_links );

		$this->assertCount( 2, $links );
	}

	// =========================================================================
	// Edge Cases Tests
	// =========================================================================

	/**
	 * Test load_item_links with null item.
	 */
	public function test_load_item_links_with_null_item() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );
		$links_prop->setValue(
			$this->embed_links,
			array(
				'test:https://example.com' => array(
					'rel'  => 'test',
					'href' => 'https://example.com',
				),
			)
		);

		$response = new WP_REST_Response( array( 'id' => 123 ) );
		$request  = new WP_REST_Request();

		// Passing null as item should still work since method doesn't use item.
		$result = $this->embed_links->load_item_links( $response, null, $request );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
	}

	/**
	 * Test load_item_links returns correct type.
	 */
	public function test_load_item_links_returns_wp_rest_response() {
		$response = new WP_REST_Response( array( 'id' => 123 ) );
		$item     = get_post( $this->test_post_id );
		$request  = new WP_REST_Request();

		$result = $this->embed_links->load_item_links( $response, $item, $request );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
	}

	/**
	 * Test consecutive calls to load_item_links work correctly.
	 */
	public function test_consecutive_load_item_links_calls() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );

		// First call.
		$links_prop->setValue(
			$this->embed_links,
			array(
				'first:https://example.com/1' => array(
					'rel'  => 'first',
					'href' => 'https://example.com/1',
				),
			)
		);

		$response1 = new WP_REST_Response( array( 'id' => 1 ) );
		$this->embed_links->load_item_links( $response1, null, new WP_REST_Request() );

		$response1_links = $response1->get_links();
		$this->assertArrayHasKey( 'first', $response1_links );

		// Second call with different links.
		$links_prop->setValue(
			$this->embed_links,
			array(
				'second:https://example.com/2' => array(
					'rel'  => 'second',
					'href' => 'https://example.com/2',
				),
			)
		);

		$response2 = new WP_REST_Response( array( 'id' => 2 ) );
		$this->embed_links->load_item_links( $response2, null, new WP_REST_Request() );

		$response2_links = $response2->get_links();
		$this->assertArrayHasKey( 'second', $response2_links );
		$this->assertArrayNotHasKey( 'first', $response2_links );
	}

	/**
	 * Test that link with empty rel is handled.
	 */
	public function test_link_with_empty_rel_handling() {
		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );

		// Simulate what would happen if a link had empty rel somehow got into the array.
		$links_prop->setValue(
			$this->embed_links,
			array(
				':https://example.com' => array(
					'rel'  => '',
					'href' => 'https://example.com',
				),
			)
		);

		$response = new WP_REST_Response( array( 'id' => 123 ) );
		$result   = $this->embed_links->load_item_links( $response, null, new WP_REST_Request() );

		// Should process without error (though link may not be valid).
		$this->assertInstanceOf( WP_REST_Response::class, $result );
	}

	/**
	 * Test that complex URL in href is preserved.
	 */
	public function test_complex_url_in_href_preserved() {
		$complex_url = 'https://example.com/api/v1/resource?param=value&other=123#anchor';

		$links_prop = $this->reflection->getProperty( 'links' );
		$links_prop->setAccessible( true );
		$links_prop->setValue(
			$this->embed_links,
			array(
				'complex:' . $complex_url => array(
					'rel'  => 'complex',
					'href' => $complex_url,
				),
			)
		);

		$response = new WP_REST_Response( array( 'id' => 123 ) );
		$result   = $this->embed_links->load_item_links( $response, null, new WP_REST_Request() );

		$links = $result->get_links();
		$this->assertEquals( $complex_url, $links['complex'][0]['href'] );
	}
}
