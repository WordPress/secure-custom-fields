<?php
/**
 * Tests for ACF_Ajax_Query_Users query results.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_Ajax_Query_Users_Results
 *
 * Tests the paginated response shape of the query_users AJAX handler.
 * WP_User_Query does not run real SQL under WorDBless, so results are
 * shimmed via the 'users_pre_query' filter.
 *
 * @group ajax
 */
class Test_Ajax_Query_Users_Results extends BaseTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private $editor_user_id;

	/**
	 * Field group key used by these tests.
	 *
	 * @var string
	 */
	private $group_key = 'group_test_qu_results';

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		acf_init();

		$this->admin_user_id = wp_insert_user(
			array(
				'user_login' => 'qu_admin_user',
				'user_pass'  => 'password',
				'user_email' => 'qu_admin@example.com',
				'role'       => 'administrator',
			)
		);

		$this->subscriber_user_id = wp_insert_user(
			array(
				'user_login' => 'qu_subscriber_user',
				'user_pass'  => 'password',
				'user_email' => 'qu_subscriber@example.com',
				'role'       => 'subscriber',
			)
		);

		$this->editor_user_id = wp_insert_user(
			array(
				'user_login' => 'qu_editor_user',
				'user_pass'  => 'password',
				'user_email' => 'qu_editor@example.com',
				'role'       => 'editor',
				'first_name' => 'Edna',
				'last_name'  => 'Editor',
			)
		);

		$_REQUEST = array();
		$_POST    = array();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		acf_remove_local_field_group( $this->group_key );
		wp_set_current_user( 0 );

		$_REQUEST = array();
		$_POST    = array();

		parent::tear_down();
	}

	/**
	 * Shims WP_User_Query results via the users_pre_query filter.
	 *
	 * @param array    $user_ids User IDs to return.
	 * @param int      $total    The total found users to report.
	 * @param callable $matcher  Optional callback receiving the WP_User_Query, return false to skip the shim.
	 */
	private function shim_user_query( array $user_ids, int $total, $matcher = null ) {
		add_filter(
			'users_pre_query',
			function ( $results, $query ) use ( $user_ids, $total, $matcher ) {
				if ( $matcher && ! call_user_func( $matcher, $query ) ) {
					$query->total_users = 0;
					return array();
				}

				$query->total_users = $total;
				return $user_ids;
			},
			10,
			2
		);
	}

	/**
	 * Test that searching returns a flat array of id/text results.
	 */
	public function test_search_returns_flat_results() {
		$this->shim_user_query( array( $this->editor_user_id ), 1 );

		$ajax     = new ACF_Ajax_Query_Users();
		$response = $ajax->get_response( array( 'search' => 'edna' ) );

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'results', $response );
		$this->assertArrayHasKey( 'more', $response );
		$this->assertFalse( $response['more'] );

		$this->assertCount( 1, $response['results'] );
		$this->assertSame(
			array(
				'id'   => $this->editor_user_id,
				'text' => 'qu_editor_user (Edna Editor)',
			),
			$response['results'][0]
		);
	}

	/**
	 * Test that the more flag is set when more results exist.
	 */
	public function test_more_flag_set_when_more_results_exist() {
		// Report 50 total users while only returning one.
		$this->shim_user_query( array( $this->editor_user_id ), 50 );

		$ajax     = new ACF_Ajax_Query_Users();
		$response = $ajax->get_response( array( 'search' => 'edna' ) );

		$this->assertTrue( $response['more'], 'more should be true when total exceeds returned results' );
	}

	/**
	 * Test that non-search queries return results grouped by role.
	 */
	public function test_results_grouped_by_role_when_not_searching() {
		// Only return results for the editor role query.
		$this->shim_user_query(
			array( $this->editor_user_id ),
			1,
			function ( $query ) {
				return 'editor' === $query->query_vars['role'];
			}
		);

		$ajax     = new ACF_Ajax_Query_Users();
		$response = $ajax->get_response( array() );

		$this->assertIsArray( $response );
		$this->assertCount( 1, $response['results'], 'Only roles with users should produce an optgroup' );

		$group = $response['results'][0];
		$this->assertSame( 'Editor', $group['text'] );
		$this->assertCount( 1, $group['children'] );
		$this->assertSame( $this->editor_user_id, $group['children'][0]['id'] );
		$this->assertSame( 'qu_editor_user (Edna Editor)', $group['children'][0]['text'] );
	}

	/**
	 * Test that a user field's role setting restricts the query via role__in.
	 */
	public function test_field_role_setting_restricts_query() {
		acf_add_local_field_group(
			array(
				'key'      => $this->group_key,
				'title'    => 'QU Results Group',
				'fields'   => array(
					array(
						'key'   => 'field_test_qu_results_user',
						'name'  => 'qu_results_user',
						'label' => 'QU Results User',
						'type'  => 'user',
						'role'  => array( 'editor' ),
					),
				),
				'location' => array(),
			)
		);

		$captured_role_in = null;
		add_filter(
			'users_pre_query',
			function ( $results, $query ) use ( &$captured_role_in ) {
				$captured_role_in   = $query->query_vars['role__in'];
				$query->total_users = 1;
				return array( $this->editor_user_id );
			},
			10,
			2
		);

		$ajax     = new ACF_Ajax_Query_Users();
		$response = $ajax->get_response(
			array(
				'field_key' => 'field_test_qu_results_user',
				'search'    => 'edna',
			)
		);

		$this->assertSame( array( 'editor' ), $captured_role_in, 'The field role setting should be passed as role__in' );
		$this->assertCount( 1, $response['results'] );
	}

	/**
	 * Test that a single role restriction returns flat results without searching.
	 */
	public function test_single_role_restriction_returns_flat_results() {
		acf_add_local_field_group(
			array(
				'key'      => $this->group_key,
				'title'    => 'QU Results Group',
				'fields'   => array(
					array(
						'key'   => 'field_test_qu_results_user',
						'name'  => 'qu_results_user',
						'label' => 'QU Results User',
						'type'  => 'user',
						'role'  => array( 'editor' ),
					),
				),
				'location' => array(),
			)
		);

		$this->shim_user_query( array( $this->editor_user_id ), 1 );

		$ajax     = new ACF_Ajax_Query_Users();
		$response = $ajax->get_response( array( 'field_key' => 'field_test_qu_results_user' ) );

		// A single role means a flat (ungrouped) result list even without a search term.
		$this->assertCount( 1, $response['results'] );
		$this->assertArrayHasKey( 'id', $response['results'][0], 'Results should be flat, not grouped' );
		$this->assertSame( $this->editor_user_id, $response['results'][0]['id'] );
	}

	/**
	 * Test that individual results can be modified via the result filter.
	 */
	public function test_result_filter_is_applied() {
		$this->shim_user_query( array( $this->editor_user_id ), 1 );

		add_filter(
			'acf/ajax/query_users/result',
			function ( $item ) {
				$item['text'] .= ' [filtered]';
				return $item;
			}
		);

		$ajax     = new ACF_Ajax_Query_Users();
		$response = $ajax->get_response( array( 'search' => 'edna' ) );

		$this->assertSame( 'qu_editor_user (Edna Editor) [filtered]', $response['results'][0]['text'] );
	}

	/**
	 * Test that pagination args are passed through to the user query.
	 */
	public function test_pagination_args_passed_to_user_query() {
		$captured_vars = null;
		add_filter(
			'users_pre_query',
			function ( $results, $query ) use ( &$captured_vars ) {
				$captured_vars      = $query->query_vars;
				$query->total_users = 0;
				return array();
			},
			10,
			2
		);

		$ajax = new ACF_Ajax_Query_Users();
		$ajax->get_response(
			array(
				'search'   => 'edna',
				'page'     => 3,
				'per_page' => 5,
			)
		);

		$this->assertSame( 5, $captured_vars['number'] );
		$this->assertSame( 10, $captured_vars['offset'], 'Offset should be per_page * (page - 1)' );
		$this->assertSame( '*edna*', $captured_vars['search'] );
	}

	/**
	 * Test that search columns are restricted for users without edit_users.
	 */
	public function test_search_columns_restricted_for_low_privilege_users() {
		wp_set_current_user( $this->subscriber_user_id );

		$ajax    = new ACF_Ajax_Query_Users();
		$columns = $ajax->filter_search_columns(
			array( 'user_login', 'user_email', 'user_url', 'user_nicename', 'display_name' ),
			'search-term',
			new WP_User_Query()
		);

		$this->assertSame( array( 'user_login', 'user_nicename', 'display_name' ), $columns );
		$this->assertNotContains( 'user_email', $columns, 'Low privilege users should not search by email' );
	}

	/**
	 * Test that search columns are unchanged for users with edit_users.
	 */
	public function test_search_columns_unchanged_for_admins() {
		wp_set_current_user( $this->admin_user_id );

		$input = array( 'user_login', 'user_email', 'user_url', 'user_nicename', 'display_name' );

		$ajax    = new ACF_Ajax_Query_Users();
		$columns = $ajax->filter_search_columns( $input, 'search-term', new WP_User_Query() );

		$this->assertSame( $input, $columns );
	}
}
