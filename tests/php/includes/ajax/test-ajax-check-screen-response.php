<?php
/**
 * Tests for the ACF_Ajax_Check_Screen response shape.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_Ajax_Check_Screen_Response
 *
 * Tests the full success-path response of the check_screen AJAX handler,
 * including field group matching, rendered HTML, hidden metabox handling
 * and the metabox sort order.
 *
 * @group ajax
 */
class Test_Ajax_Check_Screen_Response extends BaseTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Field group key used by these tests.
	 *
	 * @var string
	 */
	private $group_key = 'group_test_cs_response';

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Ensure ACF core (forms, location types, field types) is initialized.
		acf_init();

		$this->admin_user_id = wp_insert_user(
			array(
				'user_login' => 'cs_admin_user',
				'user_pass'  => 'password',
				'user_email' => 'cs_admin@example.com',
				'role'       => 'administrator',
			)
		);

		$this->post_id = wp_insert_post(
			array(
				'post_title'  => 'Check Screen Post',
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $this->admin_user_id,
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => $this->group_key,
				'title'    => 'Check Screen Group',
				'fields'   => array(
					array(
						'key'   => 'field_test_cs_response_text',
						'name'  => 'cs_response_text',
						'label' => 'CS Response Text',
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
			)
		);

		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		acf_remove_local_field_group( $this->group_key );
		wp_set_current_user( 0 );

		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();

		parent::tear_down();
	}

	/**
	 * Builds a default check_screen request array.
	 *
	 * @param array $overrides Request overrides.
	 * @return array
	 */
	private function get_request( array $overrides = array() ): array {
		return array_merge(
			array(
				'screen'    => 'post',
				'post_id'   => $this->post_id,
				'post_type' => 'post',
				'exists'    => array(),
			),
			$overrides
		);
	}

	/**
	 * Runs a check_screen request as the given user and returns the response.
	 *
	 * @param array $request The request args.
	 * @return mixed
	 */
	private function run_check_screen( array $request ) {
		$ajax          = new ACF_Ajax_Check_Screen();
		$ajax->request = $request;

		return $ajax->get_response( $ajax->request );
	}

	/**
	 * Finds the result item for this test's field group.
	 *
	 * @param array $response The check_screen response.
	 * @return array|null
	 */
	private function find_group_item( array $response ) {
		foreach ( $response['results'] as $item ) {
			if ( $this->group_key === $item['key'] ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Test that a logged-out user is rejected.
	 */
	public function test_logged_out_user_is_rejected() {
		wp_set_current_user( 0 );

		$result = $this->run_check_screen( $this->get_request() );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that the response has the expected top-level shape.
	 */
	public function test_response_shape_for_admin() {
		wp_set_current_user( $this->admin_user_id );

		$response = $this->run_check_screen( $this->get_request() );

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'results', $response );
		$this->assertArrayHasKey( 'style', $response );
		$this->assertIsArray( $response['results'] );
		$this->assertIsString( $response['style'] );
	}

	/**
	 * Test that a matching field group is returned with the expected item structure.
	 */
	public function test_matching_field_group_item_structure() {
		wp_set_current_user( $this->admin_user_id );

		$response = $this->run_check_screen( $this->get_request() );
		$item     = $this->find_group_item( $response );

		$this->assertNotNull( $item, 'The registered field group should match the post screen' );

		foreach ( array( 'id', 'key', 'title', 'position', 'classes', 'style', 'label', 'edit', 'html' ) as $key ) {
			$this->assertArrayHasKey( $key, $item, "Item should contain the '{$key}' key" );
		}

		$this->assertSame( 'acf-' . $this->group_key, $item['id'] );
		$this->assertSame( 'Check Screen Group', $item['title'] );
	}

	/**
	 * Test that the rendered HTML contains the field input.
	 */
	public function test_item_html_contains_rendered_field() {
		wp_set_current_user( $this->admin_user_id );

		$response = $this->run_check_screen( $this->get_request() );
		$item     = $this->find_group_item( $response );

		$this->assertNotNull( $item );
		$this->assertStringContainsString( 'cs_response_text', $item['html'], 'HTML should contain the rendered field' );
		$this->assertStringContainsString( 'field_test_cs_response_text', $item['html'], 'HTML should reference the field key' );
	}

	/**
	 * Test that HTML rendering is skipped for field groups already on the page.
	 */
	public function test_html_skipped_when_group_already_exists_on_page() {
		wp_set_current_user( $this->admin_user_id );

		$response = $this->run_check_screen( $this->get_request( array( 'exists' => array( $this->group_key ) ) ) );
		$item     = $this->find_group_item( $response );

		$this->assertNotNull( $item );
		$this->assertSame( '', $item['html'], 'HTML should be empty when the field group already exists on the page' );
	}

	/**
	 * Test that the 'sorted' key is added for the post screen.
	 */
	public function test_sorted_key_added_for_post_screen() {
		wp_set_current_user( $this->admin_user_id );

		$response = $this->run_check_screen( $this->get_request() );

		$this->assertArrayHasKey( 'sorted', $response, 'Post screen should include the sorted metabox order' );
	}

	/**
	 * Test that the 'sorted' key reflects the user's metabox order option.
	 */
	public function test_sorted_key_returns_user_metabox_order() {
		wp_set_current_user( $this->admin_user_id );

		$order = array( 'normal' => 'acf-' . $this->group_key . ',submitdiv' );
		update_user_option( $this->admin_user_id, 'meta-box-order_post', $order, true );

		$response = $this->run_check_screen( $this->get_request() );

		$this->assertSame( $order, $response['sorted'] );
	}

	/**
	 * Test that the 'sorted' key is not added for non-post screens.
	 */
	public function test_sorted_key_absent_for_non_post_screen() {
		wp_set_current_user( $this->admin_user_id );

		$response = $this->run_check_screen( $this->get_request( array( 'screen' => 'user' ) ) );

		$this->assertIsArray( $response );
		$this->assertArrayNotHasKey( 'sorted', $response, 'Non-post screens should not include the sorted key' );
	}

	/**
	 * Test that hidden metaboxes get the hide-if-js class.
	 */
	public function test_hidden_metabox_gets_hide_if_js_class() {
		wp_set_current_user( $this->admin_user_id );

		update_user_option( $this->admin_user_id, 'metaboxhidden_post', array( 'acf-' . $this->group_key ), true );

		$response = $this->run_check_screen( $this->get_request() );
		$item     = $this->find_group_item( $response );

		$this->assertNotNull( $item );
		$this->assertStringContainsString( 'hide-if-js', $item['classes'] );
	}

	/**
	 * Test that the response can be extended via the check_screen response filter.
	 */
	public function test_response_filter_is_applied() {
		wp_set_current_user( $this->admin_user_id );

		add_filter(
			'acf/ajax/check_screen/response',
			function ( $response ) {
				$response['custom_extension'] = 'extension_value';
				return $response;
			}
		);

		$response = $this->run_check_screen( $this->get_request() );

		$this->assertArrayHasKey( 'custom_extension', $response );
		$this->assertSame( 'extension_value', $response['custom_extension'] );
	}
}
