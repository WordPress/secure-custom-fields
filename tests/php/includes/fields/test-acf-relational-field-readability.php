<?php
/**
 * Tests for which posts relational fields show, save and render for users who can't read them.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/* Load the field types before WorDBless snapshots the hook state. */
acf_include( 'includes/fields/class-acf-field-post_object.php' );
acf_include( 'includes/fields/class-acf-field-relationship.php' );
acf_include( 'includes/fields/class-acf-field-page_link.php' );
acf_include( 'includes/fields/class-acf-field-select.php' );

/**
 * Covers relational field searches, rendering, saving and ACF Block values for users who can't read
 * every post.
 *
 * WorDBless has no database, so queries are answered by a `posts_pre_query` shim. The shim applies the
 * restriction clause that `_acf_query_restrict_unreadable_posts()` added to the query's SQL, so the
 * searches exercise the clause the plugin generates.
 *
 * @group fields
 * @group ajax
 * @group security
 */
class Test_ACF_Relational_Field_Readability extends BaseTestCase {

	/**
	 * User IDs.
	 *
	 * @var array
	 */
	private $users = array();

	/**
	 * Post IDs.
	 *
	 * @var array
	 */
	private $posts = array();

	/**
	 * Post IDs the query shim can return.
	 *
	 * @var array
	 */
	private $query_post_ids = array();

	/**
	 * Whether the query shim answers queries.
	 *
	 * @var bool
	 */
	private $serve_queries = false;

	/**
	 * SQL of the queries the shim answered.
	 *
	 * @var string[]
	 */
	private $observed_requests = array();

	/**
	 * Block type names registered during a test.
	 *
	 * @var string[]
	 */
	private $block_names = array();

	/** Set up users, fields, posts and the query shim. */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( 0 );

		$this->users['contributor'] = $this->create_user( 'contributor', 'readability_contributor' );
		$this->users['author']      = $this->create_user( 'author', 'readability_author' );
		$this->users['other']       = $this->create_user( 'author', 'readability_other' );
		$this->users['editor']      = $this->create_user( 'editor', 'readability_editor' );

		$this->register_fields();
		$this->create_base_posts();

		add_filter( 'posts_pre_query', array( $this, 'filter_fixture_posts' ), 10, 2 );

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
	}

	/** Remove fixtures and request state. */
	public function tear_down(): void {
		remove_filter( 'posts_pre_query', array( $this, 'filter_fixture_posts' ), 10 );

		foreach ( $this->field_types() as $field_type ) {
			acf_remove_local_field( $this->field_key( $field_type ) );
		}

		foreach ( $this->block_names as $block_name ) {
			acf_remove_block_type( $block_name );

			if ( WP_Block_Type_Registry::get_instance()->is_registered( $block_name ) ) {
				unregister_block_type( $block_name );
			}
		}

		acf_remove_local_field_group( 'group_readability_block' );
		acf_remove_local_field( 'field_readability_block_posts' );

		foreach ( $this->query_post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		acf_set_data( 'acf_stored_post_ids_snapshots', array() );
		acf_set_data( 'acf_block_reader', null );
		acf_get_store( 'block-cache' )->reset();
		acf_get_store( 'values' )->reset();

		unset( $GLOBALS['post'] );
		wp_set_current_user( 0 );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/** Contributors and authors find their own drafts and public posts, but not other users' unpublished posts. */
	public function test_search_hides_other_users_unpublished_posts() {
		$public = array( $this->posts['public_post'], $this->posts['public_page'] );
		$cases  = array(
			'contributor' => array_merge( $public, array( $this->posts['contributor_draft'] ) ),
			'author'      => array_merge( $public, array( $this->posts['author_draft'] ) ),
		);

		foreach ( $this->field_types() as $field_type ) {
			foreach ( $cases as $role => $expected ) {
				wp_set_current_user( $this->users[ $role ] );

				$payload = $this->invoke_ajax_query( $field_type, array( 's' => 'Beta' ) );

				$this->assert_result_ids( $expected, $payload, $field_type . ' as ' . $role );
				$this->assertStringContainsString( "post_status NOT IN ('", $this->last_request(), $field_type );
			}
		}
	}

	/** Editors still find other users' drafts, so the restriction only applies to users who can't edit them. */
	public function test_search_shows_other_users_drafts_to_editors() {
		wp_set_current_user( $this->users['editor'] );

		foreach ( $this->field_types() as $field_type ) {
			$payload = $this->invoke_ajax_query( $field_type, array( 's' => 'Beta' ) );

			$this->assertContains( $this->posts['other_draft'], $this->result_ids( $payload ), $field_type );
			$this->assertContains( $this->posts['other_private'], $this->result_ids( $payload ), $field_type );
		}
	}

	/** The unreadable statuses filter can re-include a status for posts the user is granted access to. */
	public function test_unreadable_post_statuses_filter_reincludes_status() {
		wp_set_current_user( $this->users['author'] );

		$granted_draft   = $this->posts['other_draft'];
		$grant_draft     = static function ( $can_read, $post ) use ( $granted_draft ) {
			return $post && $granted_draft === $post->ID ? true : $can_read;
		};
		$filtered_types  = array();
		$include_drafts  = static function ( $statuses, $post_type ) use ( &$filtered_types ) {
			$filtered_types[] = $post_type;
			return array_values( array_diff( $statuses, array( 'draft' ) ) );
		};
		$granted_results = array();
		$included        = array();

		add_filter( 'acf/user_can_read_post', $grant_draft, 10, 2 );

		try {
			foreach ( $this->field_types() as $field_type ) {
				$granted_results[ $field_type ] = $this->result_ids( $this->invoke_ajax_query( $field_type, array( 's' => 'Beta' ) ) );
			}

			add_filter( 'acf/fields/relational/unreadable_post_statuses', $include_drafts, 10, 2 );

			foreach ( $this->field_types() as $field_type ) {
				$included[ $field_type ] = $this->result_ids( $this->invoke_ajax_query( $field_type, array( 's' => 'Beta' ) ) );
			}
		} finally {
			remove_filter( 'acf/fields/relational/unreadable_post_statuses', $include_drafts, 10 );
			remove_filter( 'acf/user_can_read_post', $grant_draft, 10 );
		}

		foreach ( $this->field_types() as $field_type ) {
			$this->assertNotContains( $granted_draft, $granted_results[ $field_type ], $field_type );
			$this->assertContains( $granted_draft, $included[ $field_type ], $field_type );
			$this->assertNotContains( $this->posts['other_pending'], $included[ $field_type ], $field_type );
		}

		$this->assertContains( 'post', $filtered_types );
		$this->assertContains( 'page', $filtered_types );
	}

	/** A page whose posts were all removed returns no results but reports that more pages exist. */
	public function test_page_of_removed_posts_keeps_paging() {
		wp_set_current_user( $this->users['author'] );

		$this->create_post( 'Paging A Other Draft', 'draft', $this->users['other'] );
		$this->create_post( 'Paging B Other Draft', 'draft', $this->users['other'] );
		$readable_id = $this->create_post( 'Paging C Public', 'publish', $this->users['other'] );

		$hide_no_statuses = static function () {
			return array();
		};
		$two_per_page     = static function ( $args ) {
			$args['posts_per_page'] = 2;
			return $args;
		};
		$pages            = array();

		add_filter( 'acf/fields/relational/unreadable_post_statuses', $hide_no_statuses );

		try {
			foreach ( $this->field_types() as $field_type ) {
				add_filter( 'acf/fields/' . $field_type . '/query', $two_per_page, 100 );

				$pages[ $field_type ] = array(
					$this->invoke_ajax_query( $field_type, array( 's' => 'Paging' ) ),
					$this->invoke_ajax_query(
						$field_type,
						array(
							's'     => 'Paging',
							'paged' => 2,
						)
					),
				);

				remove_filter( 'acf/fields/' . $field_type . '/query', $two_per_page, 100 );
			}
		} finally {
			remove_filter( 'acf/fields/relational/unreadable_post_statuses', $hide_no_statuses );
		}

		foreach ( $pages as $field_type => $payloads ) {
			$this->assertSame( array(), $payloads[0]['results'], $field_type );
			$this->assertTrue( $payloads[0]['more'], $field_type );
			$this->assert_result_ids( array( $readable_id ), $payloads[1], $field_type );
		}
	}

	/** A stored selection the user can't read renders as the restricted title instead of its title. */
	public function test_unreadable_selection_renders_restricted_title() {
		wp_set_current_user( $this->users['author'] );

		foreach ( $this->field_types() as $field_type ) {
			$field          = acf_get_field( $this->field_key( $field_type ) );
			$field['value'] = array( $this->posts['other_draft'], $this->posts['public_post'] );
			$field['name']  = 'acf[' . $field['key'] . ']';

			$html                = '';
			$this->serve_queries = true;
			ob_start();

			try {
				acf_get_field_type( $field_type )->render_field( $field );
			} finally {
				$html                = ob_get_clean();
				$this->serve_queries = false;
			}

			$this->assertStringContainsString( acf_get_restricted_post_title(), $html, $field_type );
			$this->assertStringContainsString( 'Beta Public Post', $html, $field_type );
			$this->assertStringNotContainsString( 'Beta Other Draft', $html, $field_type );
		}
	}

	/** Saving keeps a stored selection the user can't read and drops a newly submitted one. */
	public function test_save_keeps_stored_unreadable_selection_and_drops_new_one() {
		wp_set_current_user( $this->users['author'] );

		$host_id   = $this->create_post( 'Readability Host', 'draft', $this->users['author'] );
		$submitted = array( $this->posts['other_draft'], $this->posts['other_private'], $this->posts['public_post'] );

		foreach ( $this->field_types() as $field_type ) {
			$field = acf_get_field( $this->field_key( $field_type ) );

			acf_update_value( array( $this->posts['other_draft'] ), $host_id, $field );

			_acf_save_submitted_values(
				static function () use ( $submitted, $host_id, $field ) {
					acf_update_value( $submitted, $host_id, $field );
				}
			);
			_acf_clear_stored_post_ids_snapshot( $host_id );

			$stored = array_map( 'intval', acf_get_array( get_post_meta( $host_id, $field['name'], true ) ) );

			$this->assertSame( array( $this->posts['other_draft'], $this->posts['public_post'] ), $stored, $field_type );
		}
	}

	/** An ACF Block leaves out posts the post's author can't read, even when the viewer can read them. */
	public function test_block_render_hides_posts_the_post_author_cannot_read() {
		wp_set_current_user( $this->users['editor'] );

		$host_id = $this->create_post( 'Readability Block Host', 'publish', $this->users['author'] );

		$this->assertSame( array( $this->posts['public_post'] ), $this->render_block_value( $host_id, 'block_readability_hidden' ) );
	}

	/** The hide unreadable posts filter returning false keeps unreadable posts in ACF Block values. */
	public function test_hide_unreadable_posts_filter_keeps_posts_in_blocks() {
		wp_set_current_user( $this->users['editor'] );

		$host_id         = $this->create_post( 'Readability Block Host', 'publish', $this->users['author'] );
		$filtered_values = array();
		$keep_posts      = static function ( $hide, $post_id ) use ( &$filtered_values ) {
			$filtered_values[] = array( $hide, $post_id );
			return false;
		};

		add_filter( 'acf/fields/relational/hide_unreadable_posts', $keep_posts, 10, 2 );

		try {
			$value = $this->render_block_value( $host_id, 'block_readability_shown' );
		} finally {
			remove_filter( 'acf/fields/relational/hide_unreadable_posts', $keep_posts, 10 );
		}

		$this->assertSame( array( $this->posts['other_draft'], $this->posts['public_post'] ), $value );
		$this->assertSame( array( array( true, 'block_readability_shown' ) ), $filtered_values );
	}

	/**
	 * Render an ACF Block saved in a post, as `the_content` does, and return the value its relationship
	 * field formats.
	 *
	 * @param int    $host_id  The post the block is saved in.
	 * @param string $block_id The block ID.
	 * @return mixed
	 */
	private function render_block_value( $host_id, $block_id ) {
		$captured = null;

		$this->register_block_with_relationship_field(
			static function () use ( &$captured ) {
				$captured = get_field( 'readability_block_posts' );
			}
		);

		$attributes = array(
			'id'   => $block_id,
			'name' => 'acf/readability-block',
			'data' => array(
				'readability_block_posts'  => array( (string) $this->posts['other_draft'], (string) $this->posts['public_post'] ),
				'_readability_block_posts' => 'field_readability_block_posts',
			),
			'mode' => 'preview',
		);
		$render     = static function ( $content ) use ( $attributes, $host_id ) {
			return $content . acf_rendered_block( $attributes, '', false, $host_id );
		};

		$GLOBALS['post'] = get_post( $host_id ); /* phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The block renders as part of this post's content. */
		add_filter( 'the_content', $render, 1 );

		try {
			apply_filters( 'the_content', '' );
		} finally {
			remove_filter( 'the_content', $render, 1 );
		}

		return $captured;
	}

	/**
	 * Register an ACF Block with a relationship field that returns post IDs.
	 *
	 * @param callable $render_callback The block's render callback.
	 * @return void
	 */
	private function register_block_with_relationship_field( $render_callback ) {
		$local_meta = acf_get_instance( 'ACF_Local_Meta' );

		if ( ! has_filter( 'acf/pre_load_meta', array( $local_meta, 'pre_load_meta' ) ) ) {
			add_filter( 'acf/pre_load_post_id', array( $local_meta, 'pre_load_post_id' ), 1, 2 );
			add_filter( 'acf/pre_load_meta', array( $local_meta, 'pre_load_meta' ), 1, 2 );
			add_filter( 'acf/pre_load_metadata', array( $local_meta, 'pre_load_metadata' ), 1, 4 );
		}

		acf_register_block_type(
			array(
				'name'            => 'readability-block',
				'title'           => 'Readability Block',
				'render_callback' => $render_callback,
			)
		);
		$this->block_names[] = 'acf/readability-block';

		acf_add_local_field_group(
			array(
				'key'      => 'group_readability_block',
				'title'    => 'Readability Block Fields',
				'fields'   => array(
					array(
						'key'           => 'field_readability_block_posts',
						'name'          => 'readability_block_posts',
						'label'         => 'Posts',
						'type'          => 'relationship',
						'post_type'     => array( 'post', 'page' ),
						'return_format' => 'id',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/readability-block',
						),
					),
				),
			)
		);
	}

	/**
	 * Create a test user.
	 *
	 * @param string $role  WordPress role.
	 * @param string $login Login and email prefix.
	 * @return int
	 */
	private function create_user( $role, $login ) {
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => $role,
			)
		);

		$this->assertIsInt( $user_id );
		return $user_id;
	}

	/** Register the relational fields searched by the tests. */
	private function register_fields() {
		foreach ( $this->field_types() as $field_type ) {
			$field = array(
				'key'       => $this->field_key( $field_type ),
				'name'      => 'readability_' . $field_type,
				'type'      => $field_type,
				'post_type' => array( 'post', 'page' ),
				'taxonomy'  => array(),
			);

			if ( 'relationship' === $field_type ) {
				$field['elements'] = array();
				$field['filters']  = array( 'search' );
			} else {
				$field['multiple']       = 1;
				$field['allow_archives'] = 0;
			}

			acf_add_local_field( $field );
		}
	}

	/** Create posts shared by the scenarios. */
	private function create_base_posts() {
		$fixtures = array(
			'public_post'       => array( 'Beta Public Post', 'publish', 'other', 'post' ),
			'public_page'       => array( 'Beta Public Page', 'publish', 'other', 'page' ),
			'other_draft'       => array( 'Beta Other Draft', 'draft', 'other', 'post' ),
			'other_pending'     => array( 'Beta Other Pending', 'pending', 'other', 'post' ),
			'other_private'     => array( 'Beta Other Private', 'private', 'other', 'post' ),
			'other_page_draft'  => array( 'Beta Other Page Draft', 'draft', 'other', 'page' ),
			'contributor_draft' => array( 'Beta Contributor Draft', 'draft', 'contributor', 'post' ),
			'author_draft'      => array( 'Beta Author Draft', 'draft', 'author', 'post' ),
		);

		foreach ( $fixtures as $key => $fixture ) {
			$this->posts[ $key ] = $this->create_post(
				$fixture[0],
				$fixture[1],
				$this->users[ $fixture[2] ],
				$fixture[3]
			);
		}

		$this->posts['other_future'] = $this->create_post( 'Beta Other Future', 'future', $this->users['other'], 'post', '2099-01-01 00:00:00' );
	}

	/**
	 * Create a post and expose it to the query shim.
	 *
	 * @param string $title     Post title.
	 * @param string $status    Post status.
	 * @param int    $author    Post author ID.
	 * @param string $type      Post type.
	 * @param string $post_date Post date, needed for scheduled posts.
	 * @return int
	 */
	private function create_post( $title, $status, $author, $type = 'post', $post_date = '' ) {
		$postarr = array(
			'post_title'  => $title,
			'post_status' => $status,
			'post_type'   => $type,
			'post_author' => $author,
		);

		if ( '' !== $post_date ) {
			$postarr['post_date'] = $post_date;
		}

		$post_id = wp_insert_post( $postarr );

		$this->assertIsInt( $post_id );
		$this->assertSame( $status, get_post_status( $post_id ) );
		$this->query_post_ids[] = $post_id;
		return $post_id;
	}

	/**
	 * Invoke a real relational AJAX handler and decode its JSON.
	 *
	 * @param string $field_type   Relational field type.
	 * @param array  $request_args Request overrides.
	 * @return array
	 * @throws RuntimeException When the handler throws unexpectedly.
	 */
	private function invoke_ajax_query( $field_type, $request_args = array() ) {
		$field_key = $this->field_key( $field_type );

		$_POST    = array_merge(
			array(
				'nonce'     => wp_create_nonce( 'acf_field_' . $field_type . '_' . $field_key ),
				'field_key' => $field_key,
				'post_id'   => 0,
				's'         => '',
				'paged'     => 1,
				'include'   => '',
			),
			$request_args
		);
		$_REQUEST = $_POST; /* phpcs:ignore WordPress.Security.NonceVerification.Missing -- Mirrors acf_request_arg(); the handler verifies the nonce. */

		$halt_marker  = 'acf_relational_readability_halt';
		$force_ajax   = static function () {
			return true;
		};
		$halt_handler = static function () use ( $halt_marker ) {
			return static function () use ( $halt_marker ) {
				throw new RuntimeException( esc_html( $halt_marker ) );
			};
		};

		add_filter( 'wp_doing_ajax', $force_ajax );
		add_filter( 'wp_die_ajax_handler', $halt_handler, 100 );
		add_filter( 'wp_die_json_handler', $halt_handler, 100 );

		$output                  = '';
		$unrelated_exception     = null;
		$this->observed_requests = array();
		$this->serve_queries     = true;

		ob_start();
		try {
			acf_get_field_type( $field_type )->ajax_query();
		} catch ( RuntimeException $exception ) {
			if ( $halt_marker !== $exception->getMessage() ) {
				$unrelated_exception = $exception;
			}
		} finally {
			$output              = ob_get_clean();
			$this->serve_queries = false;

			remove_filter( 'wp_die_json_handler', $halt_handler, 100 );
			remove_filter( 'wp_die_ajax_handler', $halt_handler, 100 );
			remove_filter( 'wp_doing_ajax', $force_ajax );
		}

		if ( $unrelated_exception ) {
			throw $unrelated_exception;
		}

		$payload = json_decode( $output, true );
		$this->assertIsArray( $payload );
		return $payload;
	}

	/**
	 * Answer relational queries with the seeded posts that match them.
	 *
	 * @param array|null $preempt Existing short-circuit value.
	 * @param WP_Query   $query   Query being executed.
	 * @return array|null
	 */
	public function filter_fixture_posts( $preempt, $query ) {
		if ( ! $this->serve_queries ) {
			return $preempt;
		}

		$post_types = (array) $query->get( 'post_type' );
		if ( ! array_intersect( array( 'post', 'page', 'any' ), $post_types ) ) {
			return $preempt;
		}

		$this->observed_requests[] = $query->request;

		$statuses = (array) $query->get( 'post_status' );
		$included = array_map( 'intval', (array) $query->get( 'post__in' ) );
		$search   = (string) $query->get( 's' );
		$posts    = array();

		foreach ( $this->query_post_ids as $post_id ) {
			$post = get_post( $post_id );

			if (
				! $post instanceof WP_Post
				|| ( ! in_array( 'any', $post_types, true ) && ! in_array( $post->post_type, $post_types, true ) )
				|| ( $statuses && ! in_array( 'any', $statuses, true ) && ! in_array( $post->post_status, $statuses, true ) )
				|| ( $included && ! in_array( $post->ID, $included, true ) )
				|| ( '' !== $search && false === stripos( $post->post_title, $search ) )
				|| ! $this->passes_readability_clause( $post, $query->request )
			) {
				continue;
			}

			$posts[] = $post;
		}

		usort(
			$posts,
			static function ( $left, $right ) use ( $post_types ) {
				$left_type  = array_search( $left->post_type, $post_types, true );
				$right_type = array_search( $right->post_type, $post_types, true );

				if ( $left_type !== $right_type ) {
					return $left_type <=> $right_type;
				}

				return strnatcasecmp( $left->post_title, $right->post_title );
			}
		);

		$per_page = (int) $query->get( 'posts_per_page' );
		if ( -1 !== $per_page ) {
			$page  = max( 1, (int) $query->get( 'paged' ) );
			$posts = array_slice( $posts, ( $page - 1 ) * $per_page, $per_page );
		}

		return array_values( $posts );
	}

	/**
	 * Evaluate, for one post, the clause `_acf_query_restrict_unreadable_posts()` appended to a query.
	 *
	 * The clause allows the current user's own posts, posts of post types listed as readable, and posts
	 * of each restricted post type whose status isn't in that post type's `NOT IN` list.
	 *
	 * @param WP_Post $post    The post.
	 * @param string  $request The query's SQL.
	 * @return bool
	 */
	private function passes_readability_clause( $post, $request ) {
		global $wpdb;

		$marker = " AND ({$wpdb->posts}.post_author = ";
		$start  = strrpos( $request, $marker );

		if ( false === $start ) {
			return true;
		}

		$clause = substr( $request, $start + strlen( $marker ) );

		if ( (int) $clause === (int) $post->post_author ) {
			return true;
		}

		if ( in_array( $post->post_type, $this->sql_list_after( $clause, "{$wpdb->posts}.post_type IN ('" ), true ) ) {
			return true;
		}

		$type_marker = "{$wpdb->posts}.post_type = '{$post->post_type}' AND {$wpdb->posts}.post_status NOT IN ('";

		if ( false === strpos( $clause, $type_marker ) ) {
			return false;
		}

		return ! in_array( $post->post_status, $this->sql_list_after( $clause, $type_marker ), true );
	}

	/**
	 * Return the quoted values of the SQL list that follows a marker.
	 *
	 * @param string $sql    The SQL.
	 * @param string $marker The text right before the list's first value.
	 * @return string[]
	 */
	private function sql_list_after( $sql, $marker ) {
		$start = strpos( $sql, $marker );

		if ( false === $start ) {
			return array();
		}

		$list = substr( $sql, $start + strlen( $marker ) );

		return explode( "','", substr( $list, 0, strpos( $list, "')" ) ) );
	}

	/**
	 * Return the SQL of the last query the shim answered.
	 *
	 * @return string
	 */
	private function last_request() {
		$this->assertNotEmpty( $this->observed_requests );
		return (string) end( $this->observed_requests );
	}

	/**
	 * Return the post IDs in an AJAX payload.
	 *
	 * @param array $payload AJAX payload.
	 * @return int[]
	 */
	private function result_ids( $payload ) {
		$post_ids = array();

		foreach ( $payload['results'] as $result ) {
			$items = isset( $result['children'] ) ? $result['children'] : array( $result );

			foreach ( $items as $item ) {
				if ( isset( $item['id'] ) && is_numeric( $item['id'] ) ) {
					$post_ids[] = (int) $item['id'];
				}
			}
		}

		return $post_ids;
	}

	/**
	 * Assert that a payload contains exactly the expected IDs.
	 *
	 * @param array  $expected Expected post IDs.
	 * @param array  $payload  AJAX payload.
	 * @param string $message  Optional failure context.
	 * @return void
	 */
	private function assert_result_ids( $expected, $payload, $message = '' ) {
		$this->assertEqualsCanonicalizing( $expected, $this->result_ids( $payload ), $message );
	}

	/** Return relational field types. */
	private function field_types() {
		return array( 'post_object', 'relationship', 'page_link' );
	}

	/**
	 * Return a local field key.
	 *
	 * @param string $field_type Relational field type.
	 * @return string
	 */
	private function field_key( $field_type ) {
		return 'field_readability_' . $field_type;
	}
}
