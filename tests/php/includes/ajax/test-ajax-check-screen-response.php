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
		unregister_taxonomy( 'scf_cs_restricted' );
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
	 * Test that the requested screen is ignored, as the response is always for the post being edited.
	 */
	public function test_requested_screen_is_ignored() {
		wp_set_current_user( $this->admin_user_id );

		$response = $this->run_check_screen( $this->get_request( array( 'screen' => 'user' ) ) );

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'sorted', $response, 'The response is for the post screen' );
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

	/**
	 * Runs a check_screen request and returns the location args the field groups were matched with.
	 *
	 * @param array $request The request args.
	 * @return array
	 */
	private function get_screen_args_for( array $request ): array {
		$screen_args = array();
		$capture     = function ( $response, $field_groups, $args ) use ( &$screen_args ) {
			$screen_args = $args;
			return $response;
		};

		add_filter( 'acf/ajax/check_screen/response', $capture, 10, 3 );
		$response = $this->run_check_screen( $request );
		remove_filter( 'acf/ajax/check_screen/response', $capture, 10 );

		$this->assertIsArray( $response );

		return $screen_args;
	}

	/**
	 * Returns the location args matched for a post, with the given request args.
	 *
	 * @param int   $post_id   The post ID.
	 * @param array $overrides Request overrides.
	 * @return array
	 */
	private function get_screen_args_for_post( int $post_id, array $overrides ): array {
		return $this->get_screen_args_for( $this->get_request( array_merge( array( 'post_id' => $post_id ), $overrides ) ) );
	}

	/**
	 * Creates a user with the given role.
	 *
	 * @param string $role The role.
	 * @return int
	 */
	private function create_user( string $role ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'cs_' . $role . '_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user_id );

		return $user_id;
	}

	/**
	 * Creates a page.
	 *
	 * @param array $args Post args.
	 * @return int
	 */
	private function create_page( array $args = array() ): int {
		return wp_insert_post(
			array_merge(
				array(
					'post_title'  => 'Check Screen Page',
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_author' => $this->admin_user_id,
				),
				$args
			)
		);
	}

	/**
	 * Test that the post ID, post type and screen come from the post, not the request.
	 */
	public function test_post_type_and_screen_come_from_the_post() {
		wp_set_current_user( $this->admin_user_id );

		$args = $this->get_screen_args_for(
			$this->get_request(
				array(
					'post_id'   => (string) $this->post_id,
					'post_type' => 'page',
					'screen'    => 'user',
				)
			)
		);

		$this->assertSame( $this->post_id, $args['post_id'] );
		$this->assertSame( 'post', $args['post_type'] );
		$this->assertSame( 'post', $args['screen'] );
	}

	/**
	 * Test that location args for other objects are not taken from the request.
	 */
	public function test_other_object_args_are_ignored() {
		wp_set_current_user( $this->admin_user_id );

		$args = $this->get_screen_args_for(
			$this->get_request(
				array(
					'user_id'      => $this->admin_user_id,
					'user_role'    => 'administrator',
					'taxonomy'     => 'category',
					'options_page' => 'acf-options',
					'comment'      => 'post',
				)
			)
		);

		foreach ( array( 'user_id', 'user_role', 'taxonomy', 'options_page', 'comment' ) as $name ) {
			$this->assertArrayNotHasKey( $name, $args, "The '{$name}' arg must not come from the request." );
		}
	}

	/**
	 * Test that a page template the page can't use is ignored, and the default template is kept.
	 */
	public function test_unavailable_page_template_is_ignored() {
		wp_set_current_user( $this->admin_user_id );
		$page_id = $this->create_page();

		$unavailable = $this->get_screen_args_for_post( $page_id, array( 'page_template' => 'templates/not-in-theme.php' ) );
		$default     = $this->get_screen_args_for_post( $page_id, array( 'page_template' => 'default' ) );

		$this->assertArrayNotHasKey( 'page_template', $unavailable );
		$this->assertSame( 'default', $default['page_template'] );
	}

	/**
	 * Test that a page parent must be no parent or another post of the same type.
	 */
	public function test_page_parent_must_be_another_post_of_the_same_type() {
		wp_set_current_user( $this->admin_user_id );
		$page_id   = $this->create_page();
		$parent_id = $this->create_page( array( 'post_title' => 'Parent Page' ) );

		$other_type = $this->get_screen_args_for_post( $page_id, array( 'page_parent' => $this->post_id ) );
		$itself     = $this->get_screen_args_for_post( $page_id, array( 'page_parent' => $page_id ) );
		$not_number = $this->get_screen_args_for_post( $page_id, array( 'page_parent' => array( $parent_id ) ) );
		$no_parent  = $this->get_screen_args_for_post( $page_id, array( 'page_parent' => '' ) );
		$valid      = $this->get_screen_args_for_post( $page_id, array( 'page_parent' => (string) $parent_id ) );

		$this->assertArrayNotHasKey( 'page_parent', $other_type, 'A post of another type is ignored.' );
		$this->assertArrayNotHasKey( 'page_parent', $itself, 'The page itself is ignored.' );
		$this->assertArrayNotHasKey( 'page_parent', $not_number, 'A non-scalar parent is ignored.' );
		$this->assertSame( 0, $no_parent['page_parent'] );
		$this->assertSame( $parent_id, $valid['page_parent'] );
	}

	/**
	 * Test that only the known page types are accepted.
	 */
	public function test_unknown_page_type_is_ignored() {
		wp_set_current_user( $this->admin_user_id );

		$unknown = $this->get_screen_args_for( $this->get_request( array( 'page_type' => 'front_page' ) ) );
		$child   = $this->get_screen_args_for( $this->get_request( array( 'page_type' => 'child' ) ) );

		$this->assertArrayNotHasKey( 'page_type', $unknown );
		$this->assertSame( 'child', $child['page_type'] );
	}

	/**
	 * Test that a post format is only accepted if it exists and the post type supports formats.
	 */
	public function test_post_format_must_be_supported() {
		wp_set_current_user( $this->admin_user_id );
		$page_id = $this->create_page();

		$supported   = $this->get_screen_args_for( $this->get_request( array( 'post_format' => 'aside' ) ) );
		$unknown     = $this->get_screen_args_for( $this->get_request( array( 'post_format' => 'not-a-format' ) ) );
		$unsupported = $this->get_screen_args_for_post( $page_id, array( 'post_format' => 'aside' ) );

		$this->assertSame( 'aside', $supported['post_format'] );
		$this->assertArrayNotHasKey( 'post_format', $unknown );
		$this->assertArrayNotHasKey( 'post_format', $unsupported, 'Pages do not support post formats.' );
	}

	/**
	 * Test that a user who can't publish can't match field groups with a published status.
	 */
	public function test_publishing_status_is_ignored_for_user_who_cannot_publish() {
		$contributor_id = $this->create_user( 'contributor' );
		$draft_id       = wp_insert_post(
			array(
				'post_title'  => 'Contributor Draft',
				'post_status' => 'draft',
				'post_author' => $contributor_id,
			)
		);
		wp_set_current_user( $contributor_id );

		foreach ( array( 'publish', 'future', 'private' ) as $status ) {
			$args = $this->get_screen_args_for_post( $draft_id, array( 'post_status' => $status ) );
			$this->assertArrayNotHasKey( 'post_status', $args, "The '{$status}' status needs the publish capability." );
		}

		$pending = $this->get_screen_args_for_post( $draft_id, array( 'post_status' => 'pending' ) );
		$this->assertSame( 'pending', $pending['post_status'] );
	}

	/**
	 * Test that internal and unregistered statuses are ignored, and a user who can publish can set publish.
	 */
	public function test_internal_and_unknown_statuses_are_ignored() {
		wp_set_current_user( $this->admin_user_id );
		$draft_id = wp_insert_post(
			array(
				'post_title'  => 'Admin Draft',
				'post_status' => 'draft',
				'post_author' => $this->admin_user_id,
			)
		);

		$trash   = $this->get_screen_args_for_post( $draft_id, array( 'post_status' => 'trash' ) );
		$unknown = $this->get_screen_args_for_post( $draft_id, array( 'post_status' => 'not-a-status' ) );
		$publish = $this->get_screen_args_for_post( $draft_id, array( 'post_status' => 'publish' ) );

		$this->assertArrayNotHasKey( 'post_status', $trash );
		$this->assertArrayNotHasKey( 'post_status', $unknown );
		$this->assertSame( 'publish', $publish['post_status'] );
	}

	/**
	 * Test that terms in a taxonomy the user can't assign terms in are replaced with the post's own terms.
	 */
	public function test_terms_the_user_cannot_assign_are_ignored() {
		register_taxonomy(
			'scf_cs_restricted',
			'post',
			array(
				'capabilities' => array( 'assign_terms' => 'manage_options' ),
			)
		);

		$contributor_id = $this->create_user( 'contributor' );
		$draft_id       = wp_insert_post(
			array(
				'post_title'  => 'Contributor Draft',
				'post_status' => 'draft',
				'post_author' => $contributor_id,
			)
		);
		wp_set_current_user( $contributor_id );

		$submitted = $this->get_screen_args_for(
			$this->get_request(
				array(
					'post_id'    => $draft_id,
					'post_terms' => array(
						'category'          => array( '7' ),
						'scf_cs_restricted' => array( '99' ),
					),
				)
			)
		);
		$omitted   = $this->get_screen_args_for(
			$this->get_request(
				array(
					'post_id'    => $draft_id,
					'post_terms' => array( 'category' => array( '7' ) ),
				)
			)
		);

		$this->assertSame( array( '7' ), $submitted['post_terms']['category'] );
		$this->assertSame( array(), $submitted['post_terms']['scf_cs_restricted'], 'Submitted terms the user cannot assign are replaced.' );
		$this->assertSame( array(), $omitted['post_terms']['scf_cs_restricted'], 'Leaving the taxonomy out keeps the post\'s own terms.' );
	}

	/**
	 * Test that a logged-in user who can't edit the post is rejected.
	 */
	public function test_user_who_cannot_edit_the_post_is_rejected() {
		wp_set_current_user( $this->create_user( 'contributor' ) );

		$result = $this->run_check_screen( $this->get_request() );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that a request for a post that doesn't exist is rejected.
	 */
	public function test_missing_post_is_rejected() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->run_check_screen( $this->get_request( array( 'post_id' => 0 ) ) );

		$this->assertInstanceOf( 'WP_Error', $result );
	}
}
