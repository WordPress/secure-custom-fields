<?php
/**
 * Tests for relational field AJAX post visibility.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the field types before WorDBless snapshots the hook state.
acf_include( 'includes/fields/class-acf-field-post_object.php' );
acf_include( 'includes/fields/class-acf-field-relationship.php' );
acf_include( 'includes/fields/class-acf-field-page_link.php' );

/**
 * Exercises the real relational AJAX handlers with deterministic query results.
 *
 * @group fields
 * @group ajax
 * @group security
 */
class Test_ACF_Relational_Field_Ajax_Security extends BaseTestCase {

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
	 * Queryable post IDs.
	 *
	 * @var array
	 */
	private $query_post_ids = array();

	/**
	 * Whether to serve fixture queries.
	 *
	 * @var bool
	 */
	private $serve_queries = false;

	/**
	 * Observed query variables.
	 *
	 * @var array
	 */
	private $observed_queries = array();

	/** Set up deterministic users, fields, posts, and queries. */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( 0 );

		$this->users['subscriber'] = $this->create_user( 'subscriber', 'relational_subscriber' );
		$this->users['author']     = $this->create_user( 'author', 'relational_author' );
		$this->users['other']      = $this->create_user( 'author', 'relational_other' );
		$this->users['editor']     = $this->create_user( 'editor', 'relational_editor' );

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

		foreach ( $this->query_post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		wp_set_current_user( 0 );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/** Ensure every handler retains its hooks and enforces post visibility. */
	public function test_handlers_enforce_post_visibility() {
		foreach ( $this->field_types() as $field_type ) {
			$action = 'acf/fields/' . $field_type . '/query';

			$this->assertNotFalse( has_action( 'wp_ajax_' . $action ) );
			$this->assertNotFalse( has_action( 'wp_ajax_nopriv_' . $action ) );

			wp_set_current_user( 0 );
			$search = $this->invoke_ajax_query( $field_type, array( 's' => 'Alpha' ) );
			$this->assert_result_ids( array( $this->posts['public_post'], $this->posts['public_page'] ), $search );

			$anonymous = $this->invoke_ajax_query( $field_type, array( 'include' => $this->posts['other_private'] ) );
			$this->assert_result_ids( array(), $anonymous );

			wp_set_current_user( $this->users['editor'] );
			$editor = $this->invoke_ajax_query( $field_type, array( 'include' => $this->posts['other_private'] ) );
			$this->assert_result_ids( array( $this->posts['other_private'] ), $editor );
		}
	}

	/** Ensure shared authorization honors the relevant WordPress roles. */
	public function test_post_object_query_honors_role_permissions() {
		$public = array( $this->posts['public_post'], $this->posts['public_page'] );
		$author = array_merge(
			$public,
			array( $this->posts['own_draft'], $this->posts['own_pending'], $this->posts['own_private'] )
		);
		$editor = array_merge( $author, array( $this->posts['other_private'] ) );
		$cases  = array(
			'anonymous'  => array( 0, $public ),
			'subscriber' => array( $this->users['subscriber'], $public ),
			'author'     => array( $this->users['author'], $author ),
			'editor'     => array( $this->users['editor'], $editor ),
		);

		foreach ( $cases as $case => $values ) {
			wp_set_current_user( $values[0] );
			$payload = $this->invoke_ajax_query( 'post_object', array( 's' => 'Alpha' ) );
			$this->assert_result_ids( $values[1], $payload, $case );
		}
	}

	/** Ensure anonymous restrictions apply before pagination. */
	public function test_anonymous_status_restrictions_apply_before_pagination() {
		wp_set_current_user( 0 );

		$this->create_post( 'Pagination A Hidden', 'private', $this->users['other'] );
		$this->create_post( 'Pagination Z Public 01', 'publish', $this->users['other'] );
		$expected_id = $this->create_post( 'Pagination Z Public 02', 'publish', $this->users['other'] );

		$query_filter = static function ( $args ) {
			$args['post_status']    = 'any';
			$args['posts_per_page'] = 1;
			return $args;
		};
		add_filter( 'acf/fields/post_object/query', $query_filter, 100 );

		try {
			$payload = $this->invoke_ajax_query(
				'post_object',
				array(
					's'     => 'Pagination',
					'paged' => 2,
				)
			);
		} finally {
			remove_filter( 'acf/fields/post_object/query', $query_filter, 100 );
		}
		$this->assert_result_ids( array( $expected_id ), $payload );

		$queries = array_filter(
			$this->observed_queries,
			static function ( $query ) {
				return 1 === $query['posts_per_page'] && 2 === $query['paged'];
			}
		);
		$this->assertNotEmpty( $queries );

		foreach ( $queries as $query ) {
			$this->assertEqualsCanonicalizing( array( 'publish' ), (array) $query['post_status'] );
		}
	}

	/** Ensure denied groups disappear before result filters run. */
	public function test_denied_groups_are_removed_before_result_filters() {
		wp_set_current_user( $this->users['subscriber'] );

		$filtered_ids  = array();
		$result_filter = static function ( $title, $post ) use ( &$filtered_ids ) {
			$filtered_ids[] = $post->ID;
			return $title;
		};
		add_filter( 'acf/fields/relationship/result', $result_filter, 10, 2 );

		try {
			$payload = $this->invoke_ajax_query(
				'relationship',
				array(
					's' => 'Group Sentinel',
				)
			);
		} finally {
			remove_filter( 'acf/fields/relationship/result', $result_filter, 10 );
		}

		$this->assert_result_ids( array( $this->posts['sentinel_public'] ), $payload );
		$this->assertSame( array( $this->posts['sentinel_public'] ), $filtered_ids );
		$this->assertNotContains(
			get_post_type_object( 'page' )->labels->name,
			wp_list_pluck( $payload['results'], 'text' )
		);
	}

	/** Ensure public inherited attachments remain available. */
	public function test_anonymous_public_attachment_remains_available() {
		wp_set_current_user( 0 );

		$public_attachment_id = $this->create_post(
			'Public Inherited Attachment',
			'inherit',
			$this->users['other'],
			'attachment',
			$this->posts['public_post']
		);
		$this->create_post(
			'Private Inherited Attachment',
			'inherit',
			$this->users['other'],
			'attachment',
			$this->posts['other_private']
		);
		$query_filter = static function ( $args ) {
			$args['post_type']   = array( 'attachment' );
			$args['post_status'] = 'any';
			return $args;
		};
		add_filter( 'acf/fields/page_link/query', $query_filter, 100 );

		try {
			$payload = $this->invoke_ajax_query( 'page_link' );
		} finally {
			remove_filter( 'acf/fields/page_link/query', $query_filter, 100 );
		}

		$this->assert_result_ids( array( $public_attachment_id ), $payload );
		$this->assertContains( 'inherit', (array) $this->observed_queries[0]['post_status'] );
	}

	/** Ensure anonymous queries discard non-viewable post types before querying. */
	public function test_anonymous_query_discards_non_viewable_post_types() {
		register_post_type( 'relational_hidden', array( 'public' => false ) );
		$this->create_post( 'Hidden Type Post', 'publish', $this->users['other'], 'relational_hidden' );
		$this->serve_queries = true;

		try {
			$groups = acf_get_grouped_posts(
				array(
					'post_type'      => array( 'relational_hidden' ),
					'post_status'    => 'any',
					'posts_per_page' => -1,
				),
				true
			);
		} finally {
			$this->serve_queries = false;
			unregister_post_type( 'relational_hidden' );
		}

		$this->assertSame( array(), $groups );
		$this->assertSame( array(), $this->observed_queries );
	}

	/** Ensure helper consumers remain unfiltered unless they opt in. */
	public function test_grouped_posts_preserve_existing_behavior_by_default() {
		wp_set_current_user( 0 );
		$this->serve_queries = true;

		try {
			$groups = acf_get_grouped_posts(
				array(
					'post_type'      => array( 'post', 'page' ),
					'post_status'    => 'any',
					'posts_per_page' => -1,
					's'              => 'Alpha',
				)
			);
		} finally {
			$this->serve_queries = false;
		}

		$post_ids = array();
		foreach ( $groups as $group ) {
			$post_ids = array_merge( $post_ids, array_keys( $group ) );
		}

		$this->assertEqualsCanonicalizing(
			array(
				$this->posts['public_post'],
				$this->posts['public_page'],
				$this->posts['own_draft'],
				$this->posts['own_pending'],
				$this->posts['own_private'],
				$this->posts['other_private'],
			),
			$post_ids
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

	/** Register fields used to resolve typed nonces. */
	private function register_fields() {
		$common = array(
			'post_type'   => array( 'post', 'page' ),
			'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			'taxonomy'    => array(),
		);

		foreach ( $this->field_types() as $field_type ) {
			$field = array_merge(
				$common,
				array(
					'key'  => $this->field_key( $field_type ),
					'name' => 'test_relational_' . $field_type,
					'type' => $field_type,
				)
			);

			if ( 'relationship' === $field_type ) {
				$field['elements'] = array();
				$field['filters']  = array( 'search' );
			} elseif ( 'page_link' === $field_type ) {
				$field['allow_archives'] = 0;
			}

			acf_add_local_field( $field );
		}
	}

	/** Create posts shared by the scenarios. */
	private function create_base_posts() {
		$fixtures = array(
			'public_post'     => array( 'Alpha Public Post', 'publish', 'other', 'post' ),
			'public_page'     => array( 'Alpha Public Page', 'publish', 'other', 'page' ),
			'own_draft'       => array( 'Alpha Own Draft', 'draft', 'author', 'post' ),
			'own_pending'     => array( 'Alpha Own Pending', 'pending', 'author', 'post' ),
			'own_private'     => array( 'Alpha Own Private', 'private', 'author', 'post' ),
			'other_private'   => array( 'Alpha Other Private', 'private', 'other', 'post' ),
			'sentinel_public' => array( 'Group Sentinel Public', 'publish', 'other', 'post' ),
			'sentinel_hidden' => array( 'Group Sentinel Hidden', 'private', 'other', 'page' ),
		);

		foreach ( $fixtures as $key => $fixture ) {
			$this->posts[ $key ] = $this->create_post(
				$fixture[0],
				$fixture[1],
				$this->users[ $fixture[2] ],
				$fixture[3]
			);
		}
	}

	/**
	 * Create a post and expose it to the query shim.
	 *
	 * @param string $title     Post title.
	 * @param string $status    Post status.
	 * @param int    $author    Post author ID.
	 * @param string $type      Post type.
	 * @param int    $parent_id Parent post ID.
	 * @return int
	 */
	private function create_post( $title, $status, $author, $type = 'post', $parent_id = 0 ) {
		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => $status,
				'post_type'   => $type,
				'post_author' => $author,
				'post_parent' => $parent_id,
			)
		);

		$this->assertIsInt( $post_id );
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Real handler test request.
		$_POST = array_merge(
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Mirrors acf_request_arg().
		$_REQUEST = $_POST;

		$halt_marker  = 'acf_relational_ajax_halt';
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

		$output                 = '';
		$unrelated_exception    = null;
		$this->observed_queries = array();
		$this->serve_queries    = true;

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
	 * Preempt relational queries with seeded posts.
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
		if ( ! array_intersect( array( 'post', 'page', 'attachment', 'relational_hidden' ), $post_types ) ) {
			return $preempt;
		}

		$this->observed_queries[] = array(
			'post_status'    => $query->get( 'post_status' ),
			'posts_per_page' => (int) $query->get( 'posts_per_page' ),
			'paged'          => (int) $query->get( 'paged' ),
		);

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
	 * Assert that a payload contains exactly the expected IDs.
	 *
	 * @param array  $expected Expected post IDs.
	 * @param array  $payload  AJAX payload.
	 * @param string $message  Optional failure context.
	 * @return void
	 */
	private function assert_result_ids( $expected, $payload, $message = '' ) {
		$post_ids = array();

		foreach ( $payload['results'] as $result ) {
			$items = isset( $result['children'] ) ? $result['children'] : array( $result );

			foreach ( $items as $item ) {
				if ( isset( $item['id'] ) && is_numeric( $item['id'] ) ) {
					$post_ids[] = (int) $item['id'];
				}
			}
		}

		$this->assertEqualsCanonicalizing( $expected, $post_ids, $message );
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
		return 'field_test_relational_' . $field_type;
	}
}
