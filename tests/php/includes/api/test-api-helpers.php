<?php
/**
 * Tests for post ID resolution helpers in includes/api/api-helpers.php
 * and term helpers in includes/api/api-term.php.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test API helper functions.
 */
class Test_API_Helpers extends BaseTestCase {

	/**
	 * Clean up globals after each test.
	 */
	public function tear_down() {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	// =========================================================================
	// acf_get_valid_post_id()
	// =========================================================================

	/**
	 * Test a numeric post ID is returned unchanged.
	 */
	public function test_get_valid_post_id_numeric() {
		$this->assertSame( 123, acf_get_valid_post_id( 123 ) );
	}

	/**
	 * Test 'option' is normalised to 'options'.
	 */
	public function test_get_valid_post_id_option_normalised_to_options() {
		$this->assertSame( 'options', acf_get_valid_post_id( 'option' ) );
		$this->assertSame( 'options', acf_get_valid_post_id( 'options' ) );
	}

	/**
	 * Test user, term and comment style string IDs pass through unchanged.
	 */
	public function test_get_valid_post_id_context_strings_pass_through() {
		$this->assertSame( 'user_5', acf_get_valid_post_id( 'user_5' ) );
		$this->assertSame( 'term_7', acf_get_valid_post_id( 'term_7' ) );
		$this->assertSame( 'comment_9', acf_get_valid_post_id( 'comment_9' ) );
	}

	/**
	 * Test a WP_Post object resolves to its numeric ID.
	 */
	public function test_get_valid_post_id_post_object() {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Helper Test Post',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( $post_id, acf_get_valid_post_id( get_post( $post_id ) ) );
	}

	/**
	 * Test a WP_User object resolves to a 'user_{id}' string.
	 */
	public function test_get_valid_post_id_user_object() {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'helper_user_' . uniqid(),
				'user_pass'  => 'password',
				'user_email' => 'helper_' . uniqid() . '@example.com',
			)
		);

		$user = get_user_by( 'id', $user_id );

		$this->assertSame( "user_{$user_id}", acf_get_valid_post_id( $user ) );
	}

	/**
	 * Test a WP_Term object resolves to a 'term_{id}' string.
	 */
	public function test_get_valid_post_id_term_object() {
		$term = new WP_Term(
			(object) array(
				'term_id'  => 7,
				'taxonomy' => 'category',
			)
		);

		$this->assertSame( 'term_7', acf_get_valid_post_id( $term ) );
	}

	/**
	 * Test a WP_Comment object resolves to a 'comment_{id}' string.
	 */
	public function test_get_valid_post_id_comment_object() {
		$comment = new WP_Comment( (object) array( 'comment_ID' => '9' ) );

		$this->assertSame( 'comment_9', acf_get_valid_post_id( $comment ) );
	}

	/**
	 * Test the global post is used when no post ID is provided.
	 */
	public function test_get_valid_post_id_falls_back_to_global_post() {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Global Post',
				'post_status' => 'publish',
			)
		);

		$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test intentionally sets the global post to verify fallback behavior; reset in tear_down.

		$this->assertSame( $post_id, acf_get_valid_post_id( false ) );
	}

	/**
	 * Test the acf/pre_load_post_id filter can short-circuit resolution.
	 */
	public function test_get_valid_post_id_pre_load_filter_short_circuits() {
		add_filter(
			'acf/pre_load_post_id',
			function () {
				return 'intercepted_99';
			}
		);

		$this->assertSame( 'intercepted_99', acf_get_valid_post_id( 123 ) );
	}

	/**
	 * Test the acf/validate_post_id filter can modify the result.
	 */
	public function test_get_valid_post_id_validate_filter_modifies_result() {
		add_filter(
			'acf/validate_post_id',
			function ( $post_id ) {
				return is_numeric( $post_id ) ? $post_id + 1000 : $post_id;
			}
		);

		$this->assertSame( 1123, acf_get_valid_post_id( 123 ) );
	}

	/**
	 * Test the deprecated acf_filter_post_id alias delegates to acf_get_valid_post_id.
	 */
	public function test_acf_filter_post_id_alias() {
		$this->assertSame( 'options', acf_filter_post_id( 'option' ) );
		$this->assertSame( 42, acf_filter_post_id( 42 ) );
	}

	// =========================================================================
	// acf_get_post_id_info()
	// =========================================================================

	/**
	 * Test post ID info decoding for each supported context.
	 */
	public function test_get_post_id_info_decodes_contexts() {
		$this->assertSame(
			array(
				'type' => 'post',
				'id'   => 12,
			),
			acf_get_post_id_info( 12 )
		);
		$this->assertSame(
			array(
				'type' => 'user',
				'id'   => 2,
			),
			acf_get_post_id_info( 'user_2' )
		);
		$this->assertSame(
			array(
				'type' => 'term',
				'id'   => 3,
			),
			acf_get_post_id_info( 'term_3' )
		);
		$this->assertSame(
			array(
				'type' => 'comment',
				'id'   => 4,
			),
			acf_get_post_id_info( 'comment_4' )
		);
		$this->assertSame(
			array(
				'type' => 'option',
				'id'   => 'options',
			),
			acf_get_post_id_info( 'options' )
		);
		$this->assertSame(
			array(
				'type' => 'option',
				'id'   => 'my_custom_location',
			),
			acf_get_post_id_info( 'my_custom_location' )
		);
	}

	/**
	 * Test post ID info returns the default for an empty value.
	 */
	public function test_get_post_id_info_empty_value() {
		$this->assertSame(
			array(
				'type' => 'post',
				'id'   => 0,
			),
			acf_get_post_id_info( 0 )
		);
	}

	// =========================================================================
	// api-term.php helpers
	// =========================================================================

	/**
	 * Test acf_get_taxonomies returns public builtin taxonomies.
	 */
	public function test_get_taxonomies_returns_public_taxonomies() {
		$taxonomies = acf_get_taxonomies();

		$this->assertContains( 'category', $taxonomies );
		$this->assertContains( 'post_tag', $taxonomies );
		// Private builtin taxonomies are excluded.
		$this->assertNotContains( 'nav_menu', $taxonomies );
	}

	/**
	 * Test acf_get_taxonomies with a post_type argument.
	 */
	public function test_get_taxonomies_for_post_type() {
		$taxonomies = acf_get_taxonomies( array( 'post_type' => 'post' ) );

		$this->assertContains( 'category', $taxonomies );
		$this->assertContains( 'post_tag', $taxonomies );

		$page_taxonomies = acf_get_taxonomies_for_post_type( 'page' );
		$this->assertNotContains( 'category', $page_taxonomies );
	}

	/**
	 * Test acf_get_taxonomy_labels maps taxonomy names to singular labels.
	 */
	public function test_get_taxonomy_labels() {
		$labels = acf_get_taxonomy_labels( array( 'category', 'post_tag' ) );

		$this->assertSame( 'Category', $labels['category'] );
		$this->assertSame( 'Tag', $labels['post_tag'] );
	}

	/**
	 * Test acf_encode_term builds a "taxonomy:slug" string.
	 */
	public function test_encode_term() {
		$term = new WP_Term(
			(object) array(
				'term_id'  => 3,
				'taxonomy' => 'post_tag',
				'name'     => 'Tagged',
				'slug'     => 'tagged',
			)
		);

		$this->assertSame( 'post_tag:tagged', acf_encode_term( $term ) );
	}

	/**
	 * Test acf_decode_term parses a "taxonomy:slug" string.
	 */
	public function test_decode_term() {
		$this->assertSame(
			array(
				'taxonomy' => 'category',
				'slug'     => 'news',
			),
			acf_decode_term( 'category:news' )
		);
	}

	/**
	 * Test acf_decode_term returns false for strings without a separator.
	 */
	public function test_decode_term_returns_false_for_invalid_string() {
		$this->assertFalse( acf_decode_term( 'no-separator' ) );
		$this->assertFalse( acf_decode_term( 123 ) );
	}

	/**
	 * Test acf_get_term_title returns the term name.
	 */
	public function test_get_term_title_returns_name() {
		$term = new WP_Term(
			(object) array(
				'term_id'  => 3,
				'taxonomy' => 'post_tag',
				'name'     => 'My Tag',
				'slug'     => 'my-tag',
			)
		);

		$this->assertSame( 'My Tag', acf_get_term_title( $term ) );
	}

	/**
	 * Test acf_get_term_title falls back for an empty term name.
	 */
	public function test_get_term_title_empty_name_falls_back() {
		$term = new WP_Term(
			(object) array(
				'term_id'  => 4,
				'taxonomy' => 'post_tag',
				'name'     => '',
				'slug'     => 'empty',
			)
		);

		$this->assertSame( '(no title)', acf_get_term_title( $term ) );
	}
}
