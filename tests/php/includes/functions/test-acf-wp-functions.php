<?php
/**
 * Tests for WordPress wrapper functions in includes/acf-wp-functions.php
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for acf-wp-functions.php
 *
 * Tests cover:
 * - acf_get_object_type() - WordPress object type resolution
 * - acf_decode_post_id() - Post ID string decoding
 * - acf_get_object_type_rest_base() - REST base extraction
 * - acf_get_object_id() - Object ID extraction
 */
class Test_ACF_WP_Functions extends BaseTestCase {

	/**
	 * Test acf_get_object_type returns correct data for post type.
	 */
	public function test_get_object_type_post_without_subtype() {
		$result = acf_get_object_type( 'post' );

		$this->assertIsObject( $result, 'Result should be an object' );
		$this->assertSame( 'post', $result->type, 'Type should be post' );
		$this->assertSame( '', $result->subtype, 'Subtype should be empty' );
		$this->assertSame( 'post', $result->name, 'Name should be post' );
		$this->assertSame( 'dashicons-admin-post', $result->icon, 'Icon should be post icon' );
	}

	/**
	 * Test acf_get_object_type returns correct data for post with subtype.
	 */
	public function test_get_object_type_post_with_subtype() {
		$result = acf_get_object_type( 'post', 'post' );

		$this->assertIsObject( $result, 'Result should be an object' );
		$this->assertSame( 'post', $result->type, 'Type should be post' );
		$this->assertSame( 'post', $result->subtype, 'Subtype should be post' );
		$this->assertSame( 'post/post', $result->name, 'Name should be post/post' );
		$this->assertSame( 'Posts', $result->label, 'Label should be Posts' );
	}

	/**
	 * Test acf_get_object_type returns correct data for page subtype.
	 */
	public function test_get_object_type_post_page_subtype() {
		$result = acf_get_object_type( 'post', 'page' );

		$this->assertIsObject( $result, 'Result should be an object' );
		$this->assertSame( 'post/page', $result->name, 'Name should be post/page' );
		$this->assertSame( 'Pages', $result->label, 'Label should be Pages' );
	}

	/**
	 * Test acf_get_object_type returns false for invalid post subtype.
	 */
	public function test_get_object_type_invalid_post_subtype() {
		$result = acf_get_object_type( 'post', 'nonexistent_post_type' );

		$this->assertFalse( $result, 'Should return false for invalid post type' );
	}

	/**
	 * Test acf_get_object_type returns correct data for term type.
	 */
	public function test_get_object_type_term_without_subtype() {
		$result = acf_get_object_type( 'term' );

		$this->assertIsObject( $result, 'Result should be an object' );
		$this->assertSame( 'term', $result->type, 'Type should be term' );
		$this->assertSame( 'Taxonomies', $result->label, 'Label should be Taxonomies' );
		$this->assertSame( 'dashicons-tag', $result->icon, 'Icon should be tag' );
	}

	/**
	 * Test acf_get_object_type returns correct data for term with category subtype.
	 */
	public function test_get_object_type_term_with_subtype() {
		$result = acf_get_object_type( 'term', 'category' );

		$this->assertIsObject( $result, 'Result should be an object' );
		$this->assertSame( 'term/category', $result->name, 'Name should be term/category' );
		$this->assertSame( 'Categories', $result->label, 'Label should be Categories' );
	}

	/**
	 * Test acf_get_object_type returns false for invalid taxonomy.
	 */
	public function test_get_object_type_invalid_taxonomy() {
		$result = acf_get_object_type( 'term', 'nonexistent_taxonomy' );

		$this->assertFalse( $result, 'Should return false for invalid taxonomy' );
	}

	/**
	 * Data provider for object types without subtypes.
	 *
	 * @return array
	 */
	public function data_provider_object_types() {
		return array(
			'attachment' => array( 'attachment', 'Attachments', 'dashicons-admin-media' ),
			'comment'    => array( 'comment', 'Comments', 'dashicons-admin-comments' ),
			'widget'     => array( 'widget', 'Widgets', 'dashicons-screenoptions' ),
			'menu'       => array( 'menu', 'Menus', 'dashicons-admin-appearance' ),
			'menu_item'  => array( 'menu_item', 'Menu items', 'dashicons-admin-appearance' ),
			'user'       => array( 'user', 'Users', 'dashicons-admin-users' ),
			'option'     => array( 'option', 'Options', 'dashicons-admin-generic' ),
			'block'      => array( 'block', 'Blocks', 'dashicons-block-default' ),
		);
	}

	/**
	 * Test acf_get_object_type for various object types.
	 *
	 * @dataProvider data_provider_object_types
	 * @param string $type           The object type.
	 * @param string $expected_label The expected label.
	 * @param string $expected_icon  The expected icon.
	 */
	public function test_get_object_type_various_types( $type, $expected_label, $expected_icon ) {
		$result = acf_get_object_type( $type );

		$this->assertIsObject( $result, "Result for '$type' should be an object" );
		$this->assertSame( $type, $result->type, "Type should be '$type'" );
		$this->assertSame( $expected_label, $result->label, "Label should be '$expected_label'" );
		$this->assertSame( $expected_icon, $result->icon, "Icon should be '$expected_icon'" );
	}

	/**
	 * Test acf_get_object_type returns false for unknown type.
	 */
	public function test_get_object_type_unknown_type() {
		$result = acf_get_object_type( 'unknown_type' );

		$this->assertFalse( $result, 'Should return false for unknown object type' );
	}

	/**
	 * Test acf_get_object_type applies filter.
	 */
	public function test_get_object_type_applies_filter() {
		add_filter(
			'acf/get_object_type',
			function ( $object_type_result ) {
				$object_type_result->custom = 'filtered';
				return $object_type_result;
			}
		);

		$result = acf_get_object_type( 'post' );

		$this->assertSame( 'filtered', $result->custom, 'Filter should modify the object' );

		remove_all_filters( 'acf/get_object_type' );
	}

	/**
	 * Test acf_decode_post_id with numeric ID.
	 */
	public function test_decode_post_id_numeric() {
		$result = acf_decode_post_id( 123 );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertSame( 'post', $result['type'], 'Type should be post' );
		$this->assertSame( 123, $result['id'], 'ID should be 123' );
	}

	/**
	 * Test acf_decode_post_id with string numeric ID.
	 */
	public function test_decode_post_id_string_numeric() {
		$result = acf_decode_post_id( '456' );

		$this->assertSame( 'post', $result['type'], 'Type should be post' );
		$this->assertSame( 456, $result['id'], 'ID should be 456' );
	}

	/**
	 * Test acf_decode_post_id with user ID format.
	 */
	public function test_decode_post_id_user() {
		$result = acf_decode_post_id( 'user_42' );

		$this->assertSame( 'user', $result['type'], 'Type should be user' );
		$this->assertSame( 42, $result['id'], 'ID should be 42' );
	}

	/**
	 * Test acf_decode_post_id with term ID format.
	 */
	public function test_decode_post_id_term() {
		$result = acf_decode_post_id( 'term_99' );

		$this->assertSame( 'term', $result['type'], 'Type should be term' );
		$this->assertSame( 99, $result['id'], 'ID should be 99' );
	}

	/**
	 * Test acf_decode_post_id with comment ID format.
	 */
	public function test_decode_post_id_comment() {
		$result = acf_decode_post_id( 'comment_15' );

		$this->assertSame( 'comment', $result['type'], 'Type should be comment' );
		$this->assertSame( 15, $result['id'], 'ID should be 15' );
	}

	/**
	 * Test acf_decode_post_id with attachment format (maps to post).
	 */
	public function test_decode_post_id_attachment() {
		$result = acf_decode_post_id( 'attachment_200' );

		$this->assertSame( 'post', $result['type'], 'Attachment should map to post type' );
		$this->assertSame( 200, $result['id'], 'ID should be 200' );
	}

	/**
	 * Test acf_decode_post_id with menu format (maps to term).
	 */
	public function test_decode_post_id_menu() {
		$result = acf_decode_post_id( 'menu_50' );

		$this->assertSame( 'term', $result['type'], 'Menu should map to term type' );
		$this->assertSame( 50, $result['id'], 'ID should be 50' );
	}

	/**
	 * Test acf_decode_post_id with menu_item format (maps to post).
	 */
	public function test_decode_post_id_menu_item() {
		$result = acf_decode_post_id( 'menu_item_75' );

		$this->assertSame( 'post', $result['type'], 'Menu item should map to post type' );
		$this->assertSame( 75, $result['id'], 'ID should be 75' );
	}

	/**
	 * Test acf_decode_post_id with widget format (maps to option).
	 */
	public function test_decode_post_id_widget_string() {
		$result = acf_decode_post_id( 'widget_text_2' );

		$this->assertSame( 'option', $result['type'], 'Widget should map to option type' );
		$this->assertSame( 'widget_text_2', $result['id'], 'ID should be the full widget string' );
	}

	/**
	 * Test acf_decode_post_id with block format.
	 */
	public function test_decode_post_id_block() {
		$result = acf_decode_post_id( 'block_abc123' );

		$this->assertSame( 'block', $result['type'], 'Type should be block' );
		$this->assertSame( 'block_abc123', $result['id'], 'ID should be the full block string' );
	}

	/**
	 * Test acf_decode_post_id with option format.
	 */
	public function test_decode_post_id_option() {
		$result = acf_decode_post_id( 'option_my_settings' );

		$this->assertSame( 'option', $result['type'], 'Type should be option' );
		$this->assertSame( 'option_my_settings', $result['id'], 'ID should be the full option string' );
	}

	/**
	 * Test acf_decode_post_id with simple option string.
	 */
	public function test_decode_post_id_options() {
		$result = acf_decode_post_id( 'options' );

		$this->assertSame( 'option', $result['type'], 'Type should be option' );
		$this->assertSame( 'options', $result['id'], 'ID should be options' );
	}

	/**
	 * Test acf_decode_post_id with woo_order format.
	 */
	public function test_decode_post_id_woo_order() {
		$result = acf_decode_post_id( 'woo_order_1001' );

		$this->assertSame( 'woo_order', $result['type'], 'Type should be woo_order' );
		$this->assertSame( 1001, $result['id'], 'ID should be 1001' );
	}

	/**
	 * Test acf_decode_post_id with taxonomy term format.
	 */
	public function test_decode_post_id_category_taxonomy() {
		$result = acf_decode_post_id( 'category_5' );

		$this->assertSame( 'term', $result['type'], 'Category should map to term type' );
		$this->assertSame( 5, $result['id'], 'ID should be 5' );
	}

	/**
	 * Test acf_decode_post_id with invalid param type.
	 */
	public function test_decode_post_id_invalid_type() {
		$result = acf_decode_post_id( array( 'invalid' ) );

		$this->assertSame( '', $result['type'], 'Type should be empty' );
		$this->assertSame( 0, $result['id'], 'ID should be 0' );
	}

	/**
	 * Test acf_decode_post_id with null.
	 */
	public function test_decode_post_id_null() {
		$result = acf_decode_post_id( null );

		$this->assertSame( '', $result['type'], 'Type should be empty' );
		$this->assertSame( 0, $result['id'], 'ID should be 0' );
	}

	/**
	 * Test acf_decode_post_id with zero.
	 */
	public function test_decode_post_id_zero() {
		$result = acf_decode_post_id( 0 );

		$this->assertSame( 'post', $result['type'], 'Type should be post' );
		$this->assertSame( 0, $result['id'], 'ID should be 0' );
	}

	/**
	 * Test acf_decode_post_id applies filter.
	 */
	public function test_decode_post_id_applies_filter() {
		add_filter(
			'acf/decode_post_id',
			function ( $props ) {
				$props['custom'] = 'filtered';
				return $props;
			}
		);

		$result = acf_decode_post_id( 123 );

		$this->assertSame( 'filtered', $result['custom'], 'Filter should add custom property' );

		remove_all_filters( 'acf/decode_post_id' );
	}

	/**
	 * Test acf_get_object_type_rest_base with WP_Post_Type.
	 */
	public function test_get_object_type_rest_base_post_type() {
		$post_type = get_post_type_object( 'post' );

		$result = acf_get_object_type_rest_base( $post_type );

		$this->assertSame( 'posts', $result, 'REST base for post type should be posts' );
	}

	/**
	 * Test acf_get_object_type_rest_base with WP_Post_Type with custom rest_base.
	 */
	public function test_get_object_type_rest_base_custom() {
		$post_type = get_post_type_object( 'page' );

		$result = acf_get_object_type_rest_base( $post_type );

		$this->assertSame( 'pages', $result, 'REST base for page type should be pages' );
	}

	/**
	 * Test acf_get_object_type_rest_base with WP_Taxonomy.
	 */
	public function test_get_object_type_rest_base_taxonomy() {
		$taxonomy = get_taxonomy( 'category' );

		$result = acf_get_object_type_rest_base( $taxonomy );

		$this->assertSame( 'categories', $result, 'REST base for category should be categories' );
	}

	/**
	 * Test acf_get_object_type_rest_base with invalid object.
	 */
	public function test_get_object_type_rest_base_invalid() {
		$result = acf_get_object_type_rest_base( new stdClass() );

		$this->assertNull( $result, 'Should return null for invalid object type' );
	}

	/**
	 * Test acf_get_object_type_rest_base with null.
	 */
	public function test_get_object_type_rest_base_null() {
		$result = acf_get_object_type_rest_base( null );

		$this->assertNull( $result, 'Should return null for null input' );
	}

	/**
	 * Test acf_get_object_type_rest_base with string.
	 */
	public function test_get_object_type_rest_base_string() {
		$result = acf_get_object_type_rest_base( 'not_an_object' );

		$this->assertNull( $result, 'Should return null for string input' );
	}

	/**
	 * Test acf_get_object_id with WP_Post.
	 */
	public function test_get_object_id_wp_post() {
		$post     = new WP_Post( (object) array( 'ID' => 42 ) );
		$post->ID = 42;

		$result = acf_get_object_id( $post );

		$this->assertSame( 42, $result, 'Should extract ID from WP_Post' );
	}

	/**
	 * Test acf_get_object_id with WP_User.
	 */
	public function test_get_object_id_wp_user() {
		$user     = new WP_User();
		$user->ID = 99;

		$result = acf_get_object_id( $user );

		$this->assertSame( 99, $result, 'Should extract ID from WP_User' );
	}

	/**
	 * Test acf_get_object_id with WP_Term.
	 */
	public function test_get_object_id_wp_term() {
		$term          = new WP_Term( (object) array( 'term_id' => 15 ) );
		$term->term_id = 15;

		$result = acf_get_object_id( $term );

		$this->assertSame( 15, $result, 'Should extract term_id from WP_Term' );
	}

	/**
	 * Test acf_get_object_id with WP_Comment.
	 */
	public function test_get_object_id_wp_comment() {
		$comment             = new WP_Comment( (object) array( 'comment_ID' => 77 ) );
		$comment->comment_ID = '77';

		$result = acf_get_object_id( $comment );

		$this->assertSame( 77, $result, 'Should extract comment_ID from WP_Comment' );
	}

	/**
	 * Test acf_get_object_id with array containing lowercase 'id'.
	 */
	public function test_get_object_id_array_lowercase_id() {
		$object = array( 'id' => 123 );

		$result = acf_get_object_id( $object );

		$this->assertSame( 123, $result, 'Should extract id from array' );
	}

	/**
	 * Test acf_get_object_id with array containing uppercase 'ID'.
	 */
	public function test_get_object_id_array_uppercase_id() {
		$object = array( 'ID' => 456 );

		$result = acf_get_object_id( $object );

		$this->assertSame( 456, $result, 'Should extract ID from array' );
	}

	/**
	 * Test acf_get_object_id with array containing both id and ID (id takes precedence).
	 */
	public function test_get_object_id_array_both_ids() {
		$object = array(
			'id' => 111,
			'ID' => 222,
		);

		$result = acf_get_object_id( $object );

		$this->assertSame( 111, $result, 'Lowercase id should take precedence' );
	}

	/**
	 * Test acf_get_object_id with unknown object type.
	 */
	public function test_get_object_id_unknown_object() {
		$object = new stdClass();

		$result = acf_get_object_id( $object );

		$this->assertNull( $result, 'Should return null for unknown object type' );
	}

	/**
	 * Test acf_get_object_id with array without id.
	 */
	public function test_get_object_id_array_no_id() {
		$object = array( 'name' => 'test' );

		$result = acf_get_object_id( $object );

		$this->assertNull( $result, 'Should return null for array without id' );
	}

	/**
	 * Test acf_get_object_id casts string ID to integer.
	 */
	public function test_get_object_id_casts_to_int() {
		$object = array( 'id' => '789' );

		$result = acf_get_object_id( $object );

		$this->assertSame( 789, $result, 'String ID should be cast to integer' );
		$this->assertIsInt( $result, 'Result should be integer type' );
	}

	/**
	 * Test acf_get_object_id with empty array.
	 */
	public function test_get_object_id_empty_array() {
		$result = acf_get_object_id( array() );

		$this->assertNull( $result, 'Should return null for empty array' );
	}

	/**
	 * Test acf_get_object_id with null.
	 */
	public function test_get_object_id_null() {
		$result = acf_get_object_id( null );

		$this->assertNull( $result, 'Should return null for null input' );
	}
}
