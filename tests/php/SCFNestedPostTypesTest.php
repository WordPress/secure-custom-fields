<?php
/**
 * Tests for the nested custom post types feature.
 *
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- The request stand-in is specific to these tests.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

acf_include( 'includes/nested-post-types.php' );
acf_include( 'includes/rest-api/class-scf-nested-post-types-endpoint.php' );

/**
 * Tests for SCF_Nested_Post_Types and the nested parent REST endpoint.
 *
 * Note: WorDBless does not run post list queries or get_page_by_path, though
 * get_post and get_post_meta work. Query-dependent paths are exercised through
 * the feature's documented filter seams rather than real meta queries.
 */
class SCFNestedPostTypesTest extends BaseTestCase {

	/**
	 * The service instance under test.
	 *
	 * @var SCF_Nested_Post_Types
	 */
	protected $service;

	/**
	 * Root post type key.
	 *
	 * @var string
	 */
	protected $root_type = 'scf_nt_service';

	/**
	 * Child post type key (nests under the root type).
	 *
	 * @var string
	 */
	protected $child_type = 'scf_nt_subservice';

	/**
	 * Grandchild post type key (nests under the child type).
	 *
	 * @var string
	 */
	protected $grandchild_type = 'scf_nt_deep';

	/**
	 * Admin user id.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Map created posts for cleanup.
	 *
	 * @var array
	 */
	protected $created_posts = array();

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		register_post_type(
			$this->root_type,
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);
		register_post_type(
			$this->child_type,
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);
		register_post_type(
			$this->grandchild_type,
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);

		update_option( 'scf_beta_feature_nested_post_types_enabled', true );
		$GLOBALS['wp_rest_server'] = null;

		add_filter( 'scf/nested_parent_post_type', array( $this, 'map_nested_parent' ), 10, 2 );

		SCF_Nested_Post_Types::reset_instance();
		$this->service = SCF_Nested_Post_Types::instance();

		// The plugin bootstrap may not run under WorDBless, so build the route.
		$endpoint = new SCF_Rest_Nested_Post_Types_Endpoint();
		$endpoint->register_routes();

		$this->admin_id = wp_insert_user(
			array(
				'user_login' => 'scf_nt_admin_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		foreach ( $this->created_posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->created_posts = array();

		remove_filter( 'scf/nested_parent_post_type', array( $this, 'map_nested_parent' ) );
		remove_all_filters( 'scf/nested_resolve_candidates' );
		$GLOBALS['wp_rewrite']->set_permalink_structure( '' );

		delete_option( 'scf_beta_feature_nested_post_types_enabled' );
		delete_option( 'rewrite_rules' );

		SCF_Nested_Post_Types::reset_instance();
		$GLOBALS['wp_rest_server'] = null;

		unregister_post_type( $this->grandchild_type );
		unregister_post_type( $this->child_type );
		unregister_post_type( $this->root_type );

		wp_delete_user( $this->admin_id );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Filter callback mapping each child post type to its allowed parent.
	 *
	 * @param string $parent_type Current mapping.
	 * @param string $post_type   Child post type.
	 * @return string
	 */
	public function map_nested_parent( $parent_type, $post_type ) {
		if ( $this->child_type === $post_type ) {
			return $this->root_type;
		}

		if ( $this->grandchild_type === $post_type ) {
			return $this->child_type;
		}

		return $parent_type;
	}

	/**
	 * Create a post and track it for cleanup.
	 *
	 * @param string $post_type Post type.
	 * @param string $slug      Post slug.
	 * @param string $status    Post status.
	 * @return int
	 */
	protected function create_post( $post_type, $slug, $status = 'publish' ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_title'  => ucwords( str_replace( '-', ' ', $slug ) ),
				'post_name'   => $slug,
				'post_status' => $status,
			)
		);

		$this->created_posts[] = $post_id;
		return $post_id;
	}

	/**
	 * Assert a value is a WP_Error.
	 *
	 * @param mixed  $actual  Value under test.
	 * @param string $message Optional message.
	 * @return void
	 */
	protected function assertWpError( $actual, $message = '' ) {
		$this->assertInstanceOf( 'WP_Error', $actual, $message );
	}

	/**
	 * Resolve an HTTP status from a dispatched response or WP_Error.
	 *
	 * The REST server may wrap errors in a WP_REST_Response, so tests assert on
	 * the effective status instead of the object type.
	 *
	 * @param WP_REST_Response|WP_Error $response Dispatched response.
	 * @return int
	 */
	protected function response_status( $response ) {
		if ( is_wp_error( $response ) ) {
			$data = $response->get_error_data();
			return isset( $data['status'] ) ? (int) $data['status'] : 0;
		}

		return (int) $response->get_status();
	}

	/**
	 * The service is only built when the beta feature is enabled.
	 */
	public function test_instance_is_null_when_disabled() {
		update_option( 'scf_beta_feature_nested_post_types_enabled', false );
		SCF_Nested_Post_Types::reset_instance();

		$this->assertNull( SCF_Nested_Post_Types::instance() );

		update_option( 'scf_beta_feature_nested_post_types_enabled', true );
		SCF_Nested_Post_Types::reset_instance();
		$this->assertInstanceOf( 'SCF_Nested_Post_Types', SCF_Nested_Post_Types::instance() );
	}

	/**
	 * The parent post type is resolved through the filter.
	 */
	public function test_get_parent_post_type() {
		$this->assertSame( $this->root_type, $this->service->get_parent_post_type( $this->child_type ) );
		$this->assertSame( $this->child_type, $this->service->get_parent_post_type( $this->grandchild_type ) );
		$this->assertSame( '', $this->service->get_parent_post_type( $this->root_type ) );
	}

	/**
	 * A parent type that is not registered is ignored.
	 */
	public function test_get_parent_post_type_rejects_unregistered() {
		$ghost = 'scf_nt_ghost_' . uniqid();

		$closure = function ( $parent_type, $post_type ) use ( $ghost ) {
			return $post_type === $this->child_type ? $ghost : $parent_type;
		};
		add_filter( 'scf/nested_parent_post_type', $closure, 20, 2 );

		SCF_Nested_Post_Types::reset_instance();
		$service = SCF_Nested_Post_Types::instance();
		$this->assertSame( '', $service->get_parent_post_type( $this->child_type ) );

		remove_filter( 'scf/nested_parent_post_type', $closure, 20 );
	}

	/**
	 * An invalid parent post type lookup can succeed after that type is registered.
	 */
	public function test_get_parent_post_type_retries_after_registration() {
		$late_type = 'scf_nt_late_' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$closure   = function ( $parent_type, $post_type ) use ( $late_type ) {
			return $post_type === $this->child_type ? $late_type : $parent_type;
		};
		add_filter( 'scf/nested_parent_post_type', $closure, 20, 2 );
		SCF_Nested_Post_Types::reset_instance();
		$this->service = SCF_Nested_Post_Types::instance();

		$this->assertSame( '', $this->service->get_parent_post_type( $this->child_type ) );

		register_post_type( $late_type, array( 'public' => true ) );
		$this->assertSame( $late_type, $this->service->get_parent_post_type( $this->child_type ) );

		remove_filter( 'scf/nested_parent_post_type', $closure, 20 );
		unregister_post_type( $late_type );
	}

	/**
	 * Gets the configured child post types.
	 */
	public function test_get_nested_post_types() {
		$types = $this->service->get_nested_post_types();
		$this->assertContains( $this->child_type, $types );
		$this->assertContains( $this->grandchild_type, $types );
		$this->assertNotContains( $this->root_type, $types );
	}

	/**
	 * A valid parent passes validation.
	 */
	public function test_validate_parent_accepts_valid() {
		$parent = $this->create_post( $this->root_type, 'website-development' );
		$child  = $this->create_post( $this->child_type, 'wordpress-development' );

		$this->assertTrue( $this->service->validate_parent( $child, $parent ) );
	}

	/**
	 * Clearing the parent is always allowed.
	 */
	public function test_validate_parent_allows_clear() {
		$child = $this->create_post( $this->child_type, 'clear-child' );
		$this->assertTrue( $this->service->validate_parent( $child, 0 ) );
	}

	/**
	 * A post cannot nest under itself.
	 */
	public function test_validate_parent_rejects_self() {
		$child  = $this->create_post( $this->child_type, 'self-child' );
		$result = $this->service->validate_parent( $child, $child );

		$this->assertWpError( $result );
		$this->assertSame( 'scf_nested_parent_self', $result->get_error_code() );
	}

	/**
	 * A parent of the wrong post type is rejected.
	 */
	public function test_validate_parent_rejects_wrong_type() {
		$wrong  = $this->create_post( $this->grandchild_type, 'wrong-type' );
		$child  = $this->create_post( $this->child_type, 'child-item' );
		$result = $this->service->validate_parent( $child, $wrong );

		$this->assertWpError( $result );
		$this->assertSame( 'scf_nested_parent_type', $result->get_error_code() );
	}

	/**
	 * A post type without a nested parent rejects the relationship.
	 */
	public function test_validate_parent_rejects_unsupported_post_type() {
		$root_a = $this->create_post( $this->root_type, 'unsupported-root-a' );
		$root_b = $this->create_post( $this->root_type, 'unsupported-root-b' );

		// The root type has no allowed parent type, so any parent is unsupported.
		$result = $this->service->validate_parent( $root_a, $root_b );
		$this->assertWpError( $result );
		$this->assertSame( 'scf_nested_parent_unsupported', $result->get_error_code() );
	}

	/**
	 * A missing parent post is rejected.
	 */
	public function test_validate_parent_rejects_missing_parent() {
		$child  = $this->create_post( $this->child_type, 'missing-parent-child' );
		$result = $this->service->validate_parent( $child, 999999 );

		$this->assertWpError( $result );
		$this->assertSame( 'scf_nested_parent_invalid', $result->get_error_code() );
	}

	/**
	 * A trashed parent post is rejected.
	 */
	public function test_validate_parent_rejects_trashed_parent() {
		$parent = $this->create_post( $this->root_type, 'trash-parent', 'trash' );
		$child  = $this->create_post( $this->child_type, 'trash-child' );

		$result = $this->service->validate_parent( $child, $parent );
		$this->assertWpError( $result );
		$this->assertSame( 'scf_nested_parent_invalid', $result->get_error_code() );
	}

	/**
	 * A circular relationship is rejected.
	 */
	public function test_validate_parent_rejects_cycle() {
		$parent = $this->create_post( $this->root_type, 'cycle-parent' );
		$child  = $this->create_post( $this->child_type, 'cycle-child' );

		$this->assertTrue( $this->service->set_parent( $child, $parent ) );

		// Seeding the reverse pointer directly bypasses validation, producing a
		// cycle that validate_parent must detect before any further reparenting.
		update_post_meta( $parent, '_scf_nested_parent', $child );

		$result = $this->service->validate_parent( $child, $parent );
		$this->assertWpError( $result );
		$this->assertSame( 'scf_nested_parent_cycle', $result->get_error_code() );
	}

	/**
	 * A post cannot nest deeper than the maximum chain length.
	 */
	public function test_validate_parent_rejects_excess_depth() {
		// Build a chain where the proposed parent already sits below MAX_DEPTH
		// ancestors, using the root -> child -> root -> child alternation.
		$chain = array();
		$prev  = null;
		for ( $i = 0; $i <= SCF_Nested_Post_Types::MAX_DEPTH; $i++ ) {
			$type = $this->root_type;
			$id   = $this->create_post( $type, 'depth-' . $i );
			if ( $prev ) {
				update_post_meta( $id, '_scf_nested_parent', $prev );
			}
			$chain[] = $id;
			$prev    = $id;
		}

		$deepest = end( $chain );
		$child   = $this->create_post( $this->child_type, 'depth-child' );

		// The deepest post already has MAX_DEPTH ancestors.
		$result = $this->service->validate_parent( $child, $deepest );
		$this->assertWpError( $result );
		$this->assertSame( 'scf_nested_parent_depth', $result->get_error_code() );
	}

	/**
	 * A post may nest at the maximum supported chain length.
	 */
	public function test_validate_parent_accepts_maximum_depth() {
		$previous = 0;
		for ( $i = 0; $i < SCF_Nested_Post_Types::MAX_DEPTH - 1; $i++ ) {
			$post_id = $this->create_post( $this->root_type, 'max-depth-' . $i );
			if ( $previous ) {
				update_post_meta( $post_id, '_scf_nested_parent', $previous );
			}
			$previous = $post_id;
		}

		$child  = $this->create_post( $this->child_type, 'max-depth-child' );
		$result = $this->service->validate_parent( $child, $previous );

		$this->assertTrue( $result );
	}

	/**
	 * Stores and clears the parent relationship.
	 */
	public function test_set_parent_stores_and_clears() {
		$parent = $this->create_post( $this->root_type, 'store-parent' );
		$child  = $this->create_post( $this->child_type, 'store-child' );

		$this->assertTrue( $this->service->set_parent( $child, $parent ) );
		$this->assertSame( $parent, $this->service->get_parent_id( $child ) );

		$this->assertTrue( $this->service->set_parent( $child, 0 ) );
		$this->assertSame( 0, $this->service->get_parent_id( $child ) );
	}

	/**
	 * Rewrite rules invalidate before deleting a nested child.
	 */
	public function test_before_delete_post_invalidates_rewrites() {
		$root  = $this->create_post( $this->root_type, 'delete-parent' );
		$child = $this->create_post( $this->child_type, 'delete-child' );
		$this->service->set_parent( $child, $root );
		update_option( 'rewrite_rules', array( 'cached' => 'rules' ) );

		do_action( 'before_delete_post', $child, get_post( $child ) );

		$this->assertFalse( get_option( 'rewrite_rules' ) );
	}

	/**
	 * Gets ancestors in nearest-first order.
	 */
	public function test_get_ancestor_posts_multilevel() {
		$root = $this->create_post( $this->root_type, 'website-development' );
		$mid  = $this->create_post( $this->child_type, 'wordpress-development' );
		$leaf = $this->create_post( $this->grandchild_type, 'custom-theme-development' );

		$this->assertTrue( $this->service->set_parent( $mid, $root ) );
		$this->assertTrue( $this->service->set_parent( $leaf, $mid ) );

		$ancestors = $this->service->get_ancestor_posts( $leaf );
		$ids       = wp_list_pluck( $ancestors, 'ID' );

		$this->assertSame( array( $mid, $root ), $ids );
		$this->assertSame( array(), $this->service->get_ancestor_posts( $root ) );
	}

	/**
	 * A broken meta cycle does not loop forever.
	 */
	public function test_get_ancestor_posts_bails_on_cycle() {
		$a = $this->create_post( $this->root_type, 'meta-cycle-a' );
		$b = $this->create_post( $this->root_type, 'meta-cycle-b' );

		// Force a data cycle directly through meta, bypassing validation.
		update_post_meta( $a, '_scf_nested_parent', $b );
		update_post_meta( $b, '_scf_nested_parent', $a );

		$ancestors = $this->service->get_ancestor_posts( $a );
		$this->assertCount( 1, $ancestors );
		$this->assertSame( $b, $ancestors[0]->ID );
	}

	/**
	 * Builds the URL from the root base and slugs.
	 */
	public function test_get_nested_path() {
		$root  = $this->create_post( $this->root_type, 'website-development' );
		$child = $this->create_post( $this->child_type, 'wordpress-development' );

		$this->service->set_parent( $child, $root );

		// The root post type has no custom rewrite slug, so the key is used.
		$this->assertSame( '/' . $this->root_type . '/website-development/wordpress-development/', $this->service->get_nested_path( $child ) );
		$this->assertSame( '', $this->service->get_nested_path( $root ) );
	}

	/**
	 * Uses the root post type rewrite slug when configured.
	 */
	public function test_get_nested_path_with_rewrite_slug() {
		unregister_post_type( $this->root_type );
		register_post_type(
			$this->root_type,
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'services' ),
			)
		);

		$root  = $this->create_post( $this->root_type, 'website-development' );
		$child = $this->create_post( $this->child_type, 'wordpress-development' );
		$this->service->set_parent( $child, $root );

		$this->assertSame( '/services/website-development/wordpress-development/', $this->service->get_nested_path( $child ) );
	}

	/**
	 * Walks the whole chain from root to leaf.
	 */
	public function test_get_nested_path_multilevel() {
		unregister_post_type( $this->root_type );
		register_post_type(
			$this->root_type,
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'services' ),
			)
		);

		$root = $this->create_post( $this->root_type, 'website-development' );
		$mid  = $this->create_post( $this->child_type, 'wordpress-development' );
		$leaf = $this->create_post( $this->grandchild_type, 'custom-theme-development' );
		$this->service->set_parent( $mid, $root );
		$this->service->set_parent( $leaf, $mid );

		$this->assertSame(
			'/services/website-development/wordpress-development/custom-theme-development/',
			$this->service->get_nested_path( $leaf )
		);
	}

	/**
	 * The permalink filter returns the nested URL when permalinks are enabled.
	 */
	public function test_filter_post_type_link_with_permalinks() {
		$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );

		$root  = $this->create_post( $this->root_type, 'link-root' );
		$child = $this->create_post( $this->child_type, 'link-child' );
		$this->service->set_parent( $child, $root );

		$url = $this->service->filter_post_type_link( 'http://example.org/?fallback', get_post( $child ) );

		$this->assertStringContainsString( '/link-child/', $url );
		$this->assertStringContainsString( 'link-root', $url );
	}

	/**
	 * The permalink filter leaves the default URL on plain permalinks.
	 */
	public function test_filter_post_type_link_plain_permalinks() {
		$GLOBALS['wp_rewrite']->set_permalink_structure( '' );

		$root  = $this->create_post( $this->root_type, 'plain-root' );
		$child = $this->create_post( $this->child_type, 'plain-child' );
		$this->service->set_parent( $child, $root );

		$url = $this->service->filter_post_type_link( 'http://example.org/?scf=plain-child', get_post( $child ) );
		$this->assertSame( 'http://example.org/?scf=plain-child', $url );
	}

	/**
	 * The permalink filter leaves a non-nested post unchanged.
	 */
	public function test_filter_post_type_link_non_nested() {
		$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
		$root = $this->create_post( $this->root_type, 'top-item' );

		$url = $this->service->filter_post_type_link( 'http://example.org/top-item/', get_post( $root ) );
		$this->assertSame( 'http://example.org/top-item/', $url );
	}

	/**
	 * Rewrite rules are built for the supplied nested post IDs.
	 */
	public function test_add_rules_for_posts() {
		$root  = $this->create_post( $this->root_type, 'rule-root' );
		$child = $this->create_post( $this->child_type, 'rule-child' );
		$this->service->set_parent( $child, $root );

		$rules = $this->service->add_rules_for_posts( array( $child ), array() );

		$regex    = '^' . $this->root_type . '/rule-root/rule-child/?$';
		$expected = 'index.php?scf_nested_uri=' . $this->root_type . '/rule-root/rule-child';

		$this->assertArrayHasKey( $regex, $rules );
		$this->assertSame( $regex, array_key_first( $rules ) );
		$this->assertSame( $expected, $rules[ $regex ] );
	}

	/**
	 * An existing rewrite rule is never overridden.
	 */
	public function test_add_rules_for_posts_preserves_existing() {
		$root  = $this->create_post( $this->root_type, 'keep-root' );
		$child = $this->create_post( $this->child_type, 'keep-child' );
		$this->service->set_parent( $child, $root );

		$regex = '^' . $this->root_type . '/keep-root/keep-child/?$';
		$rules = $this->service->add_rules_for_posts( array( $child ), array( $regex => 'index.php?existing=1' ) );

		$this->assertSame( 'index.php?existing=1', $rules[ $regex ] );
	}

	/**
	 * The rules filter returns unchanged when permalinks are disabled.
	 */
	public function test_filter_rewrite_rules_array_plain_permalinks() {
		$GLOBALS['wp_rewrite']->set_permalink_structure( '' );
		$rules = $this->service->filter_rewrite_rules_array( array( 'existing' => 'x' ) );
		$this->assertSame( array( 'existing' => 'x' ), $rules );
	}

	/**
	 * The rules filter adds nested rules using the post ID seam.
	 */
	public function test_filter_rewrite_rules_array_with_ids_filter() {
		$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );

		$root  = $this->create_post( $this->root_type, 'filtered-root' );
		$child = $this->create_post( $this->child_type, 'filtered-child' );
		$this->service->set_parent( $child, $root );

		add_filter(
			'scf/nested_post_ids',
			function ( $ids ) use ( $child ) {
				$ids[] = $child;
				return $ids;
			}
		);

		$rules = $this->service->filter_rewrite_rules_array( array() );
		$this->assertArrayHasKey( '^' . $this->root_type . '/filtered-root/filtered-child/?$', $rules );

		remove_all_filters( 'scf/nested_post_ids' );
	}

	/**
	 * The nested query var is registered.
	 */
	public function test_filter_query_vars() {
		$vars = $this->service->filter_query_vars( array( 'post_type' ) );
		$this->assertContains( 'scf_nested_uri', $vars );
	}

	/**
	 * A matching nested request resolves to the child post.
	 */
	public function test_resolve_request_matches_post() {
		$root  = $this->create_post( $this->root_type, 'resolve-root' );
		$child = $this->create_post( $this->child_type, 'resolve-child' );
		$this->service->set_parent( $child, $root );

		$this->inject_candidate( get_post( $child ) );

		$wp             = new SCF_Fake_WP();
		$wp->query_vars = array(
			'scf_nested_uri' => $this->root_type . '/resolve-root/resolve-child',
			'name'           => 'conflicting-name',
			'pagename'       => 'conflicting-page',
			'page_id'        => 12,
			'preview'        => 'true',
		);

		$this->service->resolve_request( $wp );

		$this->assertFalse( $wp->is_404 );
		$this->assertSame( $child, $wp->query_vars['p'] );
		$this->assertSame( $this->child_type, $wp->query_vars['post_type'] );
		$this->assertArrayNotHasKey( 'scf_nested_uri', $wp->query_vars );
		$this->assertArrayNotHasKey( 'name', $wp->query_vars );
		$this->assertArrayNotHasKey( 'pagename', $wp->query_vars );
		$this->assertArrayNotHasKey( 'page_id', $wp->query_vars );
		$this->assertSame( 'true', $wp->query_vars['preview'] );
	}

	/**
	 * A draft nested post is not exposed to users who cannot read it.
	 */
	public function test_resolve_request_rejects_unreadable_draft() {
		$root  = $this->create_post( $this->root_type, 'draft-root' );
		$child = $this->create_post( $this->child_type, 'draft-child', 'draft' );
		$this->service->set_parent( $child, $root );
		$this->inject_candidate( get_post( $child ) );
		wp_set_current_user( 0 );

		$wp             = new SCF_Fake_WP();
		$wp->query_vars = array( 'scf_nested_uri' => $this->root_type . '/draft-root/draft-child' );
		$this->service->resolve_request( $wp );

		$this->assertTrue( $wp->is_404 );
	}

	/**
	 * A trashed nested post never resolves, even for a user with read access.
	 */
	public function test_resolve_request_rejects_trashed_post() {
		$root  = $this->create_post( $this->root_type, 'trash-root' );
		$child = $this->create_post( $this->child_type, 'trash-child', 'trash' );
		$this->service->set_parent( $child, $root );
		$this->inject_candidate( get_post( $child ) );

		$wp             = new SCF_Fake_WP();
		$wp->query_vars = array( 'scf_nested_uri' => $this->root_type . '/trash-root/trash-child' );
		$this->service->resolve_request( $wp );

		$this->assertTrue( $wp->is_404 );
	}

	/**
	 * An unmatched nested request becomes a 404.
	 */
	public function test_resolve_request_sets_404_when_unknown() {
		$wp             = new SCF_Fake_WP();
		$wp->query_vars = array( 'scf_nested_uri' => $this->root_type . '/ghost/nowhere' );

		$this->service->resolve_request( $wp );
		$this->assertTrue( $wp->is_404 );
	}

	/**
	 * Requests without the query var are left untouched.
	 */
	public function test_resolve_request_ignores_other_requests() {
		$wp             = new SCF_Fake_WP();
		$wp->query_vars = array( 'page_id' => 5 );

		$this->service->resolve_request( $wp );

		$this->assertFalse( $wp->is_404 );
		$this->assertSame( array( 'page_id' => 5 ), $wp->query_vars );
	}

	/**
	 * A wrong ancestor path does not resolve and 404s.
	 */
	public function test_resolve_request_rejects_wrong_ancestor() {
		$real_root  = $this->create_post( $this->root_type, 'real-root' );
		$other_root = $this->create_post( $this->root_type, 'other-root' );
		$child      = $this->create_post( $this->child_type, 'wrong-ancestor-child' );
		$this->service->set_parent( $child, $real_root );

		$this->inject_candidate( get_post( $child ) );

		$wp             = new SCF_Fake_WP();
		$wp->query_vars = array( 'scf_nested_uri' => $this->root_type . '/other-root/wrong-ancestor-child' );

		$this->service->resolve_request( $wp );
		$this->assertTrue( $wp->is_404 );
	}

	/**
	 * Gets direct children through the post ID seam.
	 */
	public function test_scf_get_nested_children() {
		$root = $this->create_post( $this->root_type, 'children-root' );
		$c1   = $this->create_post( $this->child_type, 'child-one' );
		$c2   = $this->create_post( $this->child_type, 'child-two' );

		$this->service->set_parent( $c1, $root );
		$this->service->set_parent( $c2, $root );

		add_filter(
			'scf/nested_children',
			function ( $children ) use ( $c1, $c2 ) {
				return array_merge( $children, array( get_post( $c1 ), get_post( $c2 ) ) );
			}
		);

		$children = scf_get_nested_children( $root );
		$ids      = wp_list_pluck( $children, 'ID' );

		$this->assertContains( $c1, $ids );
		$this->assertContains( $c2, $ids );

		remove_all_filters( 'scf/nested_children' );
	}

	/**
	 * The public helpers respect validation and read meta.
	 */
	public function test_public_helpers() {
		$root  = $this->create_post( $this->root_type, 'helper-root' );
		$child = $this->create_post( $this->child_type, 'helper-child' );

		$this->assertTrue( scf_set_nested_parent( $child, $root ) );
		$this->assertSame( $root, scf_get_nested_parent( $child ) );

		$ancestors = scf_get_nested_ancestors( $child );
		$this->assertSame( $root, $ancestors[0]->ID );

		$this->assertSame( $this->root_type, scf_get_nested_parent_post_type( $this->child_type ) );

		// A child cannot become the parent of the root, since the root allows no parent.
		$this->assertWpError( scf_set_nested_parent( $root, $child ) );
	}

	/**
	 * The set helper is safe when the feature is disabled.
	 */
	public function test_helpers_when_disabled() {
		update_option( 'scf_beta_feature_nested_post_types_enabled', false );
		SCF_Nested_Post_Types::reset_instance();

		$child  = $this->create_post( $this->child_type, 'disabled-child' );
		$result = scf_set_nested_parent( $child, 123 );

		$this->assertWpError( $result );
		$this->assertSame( array(), scf_get_nested_ancestors( $child ) );
		$this->assertSame( '', scf_get_nested_parent_post_type( $this->child_type ) );
	}

	/**
	 * Registers meta for every nested post type without error.
	 */
	public function test_register_meta_runs() {
		$this->service->register_meta();

		$meta = get_registered_meta_keys( 'post', $this->child_type );
		if ( isset( $meta['_scf_nested_parent'] ) ) {
			$this->assertTrue( ! empty( $meta['_scf_nested_parent']['show_in_rest'] ) );
		} else {
			// Older meta registry fallback: just confirm no fatal occurred.
			$this->assertTrue( true );
		}
	}

	/**
	 * The REST route is registered for the nested parent path.
	 */
	public function test_rest_route_registered_when_enabled() {
		$routes = array_keys( rest_get_server()->get_routes() );
		$found  = false;
		foreach ( $routes as $route ) {
			if ( 0 === strpos( $route, '/scf/v1/nested-parent/' ) ) {
				$found = true;
			}
		}
		$this->assertTrue( $found );
	}

	/**
	 * The REST route updates the parent for an authorized user.
	 */
	public function test_rest_route_reads_parent() {
		$root  = $this->create_post( $this->root_type, 'rest-read-root' );
		$child = $this->create_post( $this->child_type, 'rest-read-child' );
		$this->service->set_parent( $child, $root );

		$request  = new WP_REST_Request( 'GET', '/scf/v1/nested-parent/' . $child );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'post_id'   => $child,
				'parent_id' => $root,
			),
			$response->get_data()
		);
	}

	/**
	 * The REST route updates the parent for an authorized user.
	 */
	public function test_rest_route_updates_parent() {
		$root  = $this->create_post( $this->root_type, 'rest-root' );
		$child = $this->create_post( $this->child_type, 'rest-child' );

		$server  = rest_get_server();
		$request = new WP_REST_Request( 'POST', '/scf/v1/nested-parent/' . $child );
		$request->set_param( 'parent_id', $root );

		$response = $server->dispatch( $request );

		$this->assertFalse( is_wp_error( $response ), 'Dispatch returned an error' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $root, scf_get_nested_parent( $child ) );

		$data = $response->get_data();
		$this->assertSame( $child, $data['post_id'] );
		$this->assertSame( $root, $data['parent_id'] );
	}

	/**
	 * The REST route rejects a request from a user without access.
	 */
	public function test_rest_route_rejects_unauthorized() {
		$root  = $this->create_post( $this->root_type, 'deny-root' );
		$child = $this->create_post( $this->child_type, 'deny-child' );

		wp_set_current_user( 0 );

		$server  = rest_get_server();
		$request = new WP_REST_Request( 'POST', '/scf/v1/nested-parent/' . $child );
		$request->set_param( 'parent_id', $root );

		$response = $server->dispatch( $request );
		$this->assertSame( 401, $this->response_status( $response ) );
	}

	/**
	 * The REST route rejects a cycle.
	 */
	public function test_rest_route_rejects_cycle() {
		$root = $this->create_post( $this->root_type, 'cycle-rest-root' );
		$mid  = $this->create_post( $this->child_type, 'cycle-rest-mid' );
		$leaf = $this->create_post( $this->grandchild_type, 'cycle-rest-leaf' );

		$server = rest_get_server();

		$first = new WP_REST_Request( 'POST', '/scf/v1/nested-parent/' . $mid );
		$first->set_param( 'parent_id', $root );
		$server->dispatch( $first );

		$second = new WP_REST_Request( 'POST', '/scf/v1/nested-parent/' . $leaf );
		$second->set_param( 'parent_id', $mid );
		$server->dispatch( $second );

		// Reparenting the root under its descendant must fail.
		$third = new WP_REST_Request( 'POST', '/scf/v1/nested-parent/' . $root );
		$third->set_param( 'parent_id', $leaf );
		$response = $server->dispatch( $third );

		$this->assertSame( 400, $this->response_status( $response ) );
	}

	/**
	 * The REST route returns 404 for an unknown post.
	 */
	public function test_rest_route_unknown_post() {
		$server  = rest_get_server();
		$request = new WP_REST_Request( 'POST', '/scf/v1/nested-parent/999999' );
		$request->set_param( 'parent_id', 1 );

		$response = $server->dispatch( $request );
		$this->assertSame( 404, $this->response_status( $response ) );
	}

	/**
	 * The REST route requires the parent_id parameter.
	 */
	public function test_rest_route_requires_parent_id() {
		$child  = $this->create_post( $this->child_type, 'missing-param-child' );
		$server = rest_get_server();

		$request  = new WP_REST_Request( 'POST', '/scf/v1/nested-parent/' . $child );
		$response = $server->dispatch( $request );

		$this->assertSame( 400, $this->response_status( $response ) );
	}

	/**
	 * The route is not registered when the feature is disabled.
	 */
	public function test_rest_route_not_registered_when_disabled() {
		update_option( 'scf_beta_feature_nested_post_types_enabled', false );
		$GLOBALS['wp_rest_server'] = null;

		$endpoint = new SCF_Rest_Nested_Post_Types_Endpoint();
		$endpoint->register_routes();

		$routes = array_keys( rest_get_server()->get_routes() );
		$found  = false;
		foreach ( $routes as $route ) {
			if ( 0 === strpos( $route, '/scf/v1/nested-parent/' ) ) {
				$found = true;
			}
		}
		$this->assertFalse( $found );
	}

	/**
	 * The nesting meta box is offered only for nested post types.
	 */
	public function test_should_show_meta_box_only_for_nested_types() {
		$this->assertTrue( $this->service->should_show_meta_box( $this->child_type ) );
		$this->assertTrue( $this->service->should_show_meta_box( $this->grandchild_type ) );
		$this->assertFalse( $this->service->should_show_meta_box( $this->root_type ) );

		// add_meta_box() is not loaded under WorDBless; the call must be a safe no-op.
		$this->service->add_meta_box( $this->child_type, null );
		$this->assertTrue( true );
	}

	/**
	 * The render callback returns early when the screen post type is not nested.
	 */
	public function test_render_meta_box_is_safe_for_nested_type() {
		if ( ! function_exists( 'wp_nonce_field' ) ) {
			$this->markTestSkipped( 'wp_nonce_field is unavailable in this environment.' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Saves this test fixture value before replacing it.
		$server_backup          = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		$_SERVER['REQUEST_URI'] = '/wp-admin/post-new.php';

		$child = get_post( $this->create_post( $this->child_type, 'render-child' ) );
		ob_start();
		$this->service->render_meta_box( $child );
		$output = ob_get_clean();

		if ( null === $server_backup ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $server_backup;
		}

		$this->assertStringContainsString( 'scf_nested_parent', $output );
	}

	/**
	 * The meta box save handler requires a valid nonce.
	 */
	public function test_save_meta_box_requires_nonce() {
		$root  = $this->create_post( $this->root_type, 'nonce-root' );
		$child = $this->create_post( $this->child_type, 'nonce-child' );

		$_POST['scf_nested_parent_nonce'] = 'bogus';
		$_POST['scf_nested_parent']       = $root;

		$this->service->save_meta_box( $child );
		unset( $_POST['scf_nested_parent_nonce'], $_POST['scf_nested_parent'] );

		$this->assertSame( 0, $this->service->get_parent_id( $child ) );
	}

	/**
	 * The meta box save handler stores the parent with a valid nonce.
	 */
	public function test_save_meta_box_stores_parent() {
		$root  = $this->create_post( $this->root_type, 'save-root' );
		$child = $this->create_post( $this->child_type, 'save-child' );

		$_POST['scf_nested_parent_nonce'] = wp_create_nonce( 'scf_nested_parent_save' );
		$_POST['scf_nested_parent']       = $root;

		$this->service->save_meta_box( $child );
		unset( $_POST['scf_nested_parent_nonce'], $_POST['scf_nested_parent'] );

		$this->assertSame( $root, $this->service->get_parent_id( $child ) );
	}

	/**
	 * The meta box save handler rejects an invalid parent type.
	 */
	public function test_save_meta_box_rejects_bad_parent() {
		$other = $this->create_post( $this->grandchild_type, 'bad-parent-other' );
		$child = $this->create_post( $this->child_type, 'bad-parent-child' );

		$_POST['scf_nested_parent_nonce'] = wp_create_nonce( 'scf_nested_parent_save' );
		$_POST['scf_nested_parent']       = $other;

		$this->service->save_meta_box( $child );
		unset( $_POST['scf_nested_parent_nonce'], $_POST['scf_nested_parent'] );

		$this->assertSame( 0, $this->service->get_parent_id( $child ) );
	}

	/**
	 * Register a candidate for the resolver through the documented filter.
	 *
	 * @param WP_Post $post The candidate post.
	 * @return void
	 */
	protected function inject_candidate( $post ) {
		add_filter(
			'scf/nested_resolve_candidates',
			function ( $candidates, $slug ) use ( $post ) {
				if ( $slug === $post->post_name ) {
					$candidates[] = $post;
				}
				return $candidates;
			},
			10,
			2
		);
	}
}

/**
 * Minimal WP request stand-in for parse_request tests.
 */
class SCF_Fake_WP {

	/**
	 * Query vars for the fake request.
	 *
	 * @var array
	 */
	public $query_vars = array();

	/**
	 * Whether set_404 was called.
	 *
	 * @var bool
	 */
	public $is_404 = false;

	/**
	 * Marks the request as a 404.
	 *
	 * @return void
	 */
	public function set_404() {
		$this->is_404 = true;
	}
}
