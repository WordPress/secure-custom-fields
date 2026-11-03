<?php
/**
 * Test Secure Custom Fields main functionality
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the ACF_Form_Post class.
acf_include( '/includes/forms/form-post.php' );

/**
 * Class Test_Form_Post
 */
class Test_Form_Post extends BaseTestCase {

	/**
	 * Post the save tests submit values for.
	 *
	 * @var integer
	 */
	private $post_id;

	/**
	 * $_POST['acf'] as seen by acf/save_post, or null when no save ran.
	 *
	 * @var array|null
	 */
	private $saved_values;

	/**
	 * Test filters that must be removed during cleanup.
	 *
	 * @var array
	 */
	private $test_filters = array();

	/**
	 * Registers field groups for posts and pages, and a post to save.
	 */
	public function set_up() {
		parent::set_up();

		acf_reset_local();
		acf_enable_local();
		$this->reset_stores();

		acf_include( 'includes/fields/class-acf-field-text.php' );
		if ( ! acf_get_field_type( 'text' ) ) {
			acf_register_field_type( 'acf_field_text' );
		}

		acf_include( 'includes/locations/class-acf-location-post-type.php' );
		if ( ! acf_get_location_type( 'post_type' ) ) {
			acf_register_location_type( 'ACF_Location_Post_Type' );
		}

		if ( ! has_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts' ) ) {
			add_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts', 20, 2 );
		}

		$this->add_group( 'group_form_post_posts', 'post', 'field_form_post_post' );
		$this->add_group( 'group_form_post_pages', 'page', 'field_form_post_page' );

		$this->post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Form post save',
				'post_status' => 'draft',
			)
		);

		$this->saved_values = null;
		$this->add_test_filter( 'acf/save_post', array( $this, 'capture_saved_values' ), 1 );

		$_POST  = array();
		$_FILES = array();
	}

	/**
	 * Removes local fields, request data and filters.
	 */
	public function tear_down() {
		foreach ( $this->test_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}
		$this->test_filters = array();

		acf_reset_local();
		acf_enable_local();
		$this->reset_stores();

		$_POST  = array();
		$_FILES = array();

		parent::tear_down();
	}

	/**
	 * Records $_POST['acf'] when a save runs.
	 */
	public function capture_saved_values() {
		/* phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Test reads the request it built. */
		$this->saved_values = isset( $_POST['acf'] ) ? $_POST['acf'] : array();
	}

	/**
	 * Test if the ACF_Form_Post class exists.
	 */
	public function test_form_post_class_exists() {
		$this->assertTrue( class_exists( 'ACF_Form_Post' ), 'ACF_Form_Post class should exist' );
	}

	/**
	 * Test if the ACF_Form_Post class is properly initialized.
	 */
	public function test_form_post_initialization() {
		$form_post = new ACF_Form_Post();

		$this->assertInstanceOf( 'ACF_Form_Post', $form_post, 'ACF_Form_Post should be properly initialized' );
	}

	/**
	 * Test the allow_save_post method under different scenarios.
	 */
	public function test_allow_save_post() {
		$form_post = new ACF_Form_Post();

		// Test auto-draft post type (should not be allowed).
		$auto_draft_post = (object) array(
			'ID'        => 1,
			'post_type' => 'auto-draft',
		);
		$this->assertFalse( $form_post->allow_save_post( $auto_draft_post ), 'Auto-draft posts should not be allowed' );

		// Test regular post (should be allowed).
		$regular_post = (object) array(
			'ID'        => 2,
			'post_type' => 'post',
		);
		$this->assertTrue( $form_post->allow_save_post( $regular_post ), 'Regular posts should be allowed' );

		// Test ACF field group (should not be allowed).
		$acf_field_group = (object) array(
			'ID'        => 3,
			'post_type' => 'acf-field-group',
		);
		$this->assertFalse( $form_post->allow_save_post( $acf_field_group ), 'ACF field groups should not be allowed' );
	}

	/**
	 * Test wp_insert_post_empty_content method.
	 */
	public function test_wp_insert_post_empty_content() {
		$form_post = new ACF_Form_Post();

		// Should return true when no ACF data is present.
		$this->assertTrue(
			$form_post->wp_insert_post_empty_content( true ),
			'Should return true when no ACF data is present'
		);

		// Simulate POST data with ACF changes.
		$_POST['_acf_changed'] = '1';
		$this->assertFalse(
			$form_post->wp_insert_post_empty_content( true ),
			'Should return false when ACF data is present'
		);
		unset( $_POST['_acf_changed'] );
	}

	/**
	 * Test revision handling in allow_save_post.
	 */
	public function test_allow_save_post_revision_handling() {
		$form_post = new ACF_Form_Post();

		// Test regular revision (should not be allowed).
		$revision = (object) array(
			'ID'          => 4,
			'post_type'   => 'revision',
			'post_parent' => 10,
		);
		$this->assertFalse(
			$form_post->allow_save_post( $revision ),
			'Regular revision should not be allowed'
		);

		// Test preview revision (should be allowed).
		$_POST['wp-preview'] = 'dopreview';
		$_POST['post_ID']    = 10; // Set parent post ID.
		$preview_revision    = (object) array(
			'ID'          => 5,
			'post_type'   => 'revision',
			'post_parent' => 10,
		);
		$this->assertTrue(
			$form_post->allow_save_post( $preview_revision ),
			'Preview revision should be allowed when parent matches'
		);

		// Cleanup
		unset( $_POST['wp-preview'] );
		unset( $_POST['post_ID'] );
	}

	/**
	 * Test post ID validation in allow_save_post.
	 */
	public function test_allow_save_post_id_validation() {
		$form_post = new ACF_Form_Post();

		// Test when POST ID doesn't match post ID
		$_POST['post_ID'] = 999;
		$post             = (object) array(
			'ID'        => 123,
			'post_type' => 'post',
		);
		$this->assertFalse(
			$form_post->allow_save_post( $post ),
			'Should not allow save when POST ID differs from post ID'
		);

		// Test when POST ID matches post ID
		$_POST['post_ID'] = 123;
		$this->assertTrue(
			$form_post->allow_save_post( $post ),
			'Should allow save when POST ID matches post ID'
		);

		// Cleanup
		unset( $_POST['post_ID'] );
	}

	/**
	 * Test is_block_field_group method with block location rules.
	 */
	public function test_is_block_field_group_with_block_location() {
		$form_post = new ACF_Form_Post();

		// Test field group with block location rule
		$field_group_with_block = array(
			'location' => array(
				array(
					array(
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'acf/testimonial',
					),
				),
			),
		);

		$this->assertTrue(
			$form_post->is_block_field_group( $field_group_with_block ),
			'Field group with block location should be identified as block field group'
		);
	}

	/**
	 * Test is_block_field_group method with non-block location rules.
	 */
	public function test_is_block_field_group_with_non_block_location() {
		$form_post = new ACF_Form_Post();

		// Test field group with post type location rule
		$field_group_with_post_type = array(
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
		);

		$this->assertFalse(
			$form_post->is_block_field_group( $field_group_with_post_type ),
			'Field group with only post_type location should not be identified as block field group'
		);
	}

	/**
	 * Test is_block_field_group method with empty location.
	 */
	public function test_is_block_field_group_with_empty_location() {
		$form_post = new ACF_Form_Post();

		// Test field group with no location
		$field_group_empty_location = array(
			'location' => array(),
		);

		$this->assertFalse(
			$form_post->is_block_field_group( $field_group_empty_location ),
			'Field group with empty location should not be identified as block field group'
		);

		// Test field group with missing location key
		$field_group_no_location = array();

		$this->assertFalse(
			$form_post->is_block_field_group( $field_group_no_location ),
			'Field group without location key should not be identified as block field group'
		);
	}

	/**
	 * Test is_block_field_group method with multiple location groups.
	 */
	public function test_is_block_field_group_with_multiple_location_groups() {
		$form_post = new ACF_Form_Post();

		// Test field group with multiple location groups, one containing block
		$field_group_multiple_groups = array(
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
				array(
					array(
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'acf/testimonial',
					),
				),
			),
		);

		$this->assertTrue(
			$form_post->is_block_field_group( $field_group_multiple_groups ),
			'Field group with block location in any group should be identified as block field group'
		);
	}

	/**
	 * Test is_block_field_group method with mixed rules in same group.
	 */
	public function test_is_block_field_group_with_mixed_rules() {
		$form_post = new ACF_Form_Post();

		// Test field group with block AND post_type in same group
		$field_group_mixed_rules = array(
			'location' => array(
				array(
					array(
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'acf/testimonial',
					),
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
		);

		$this->assertTrue(
			$form_post->is_block_field_group( $field_group_mixed_rules ),
			'Field group with block location among other rules should be identified as block field group'
		);
	}

	/**
	 * A post save keeps fields from groups located on the post type and drops the rest.
	 */
	public function test_save_post_drops_fields_not_located_on_post() {
		$this->submit(
			array(
				'field_form_post_post'    => 'kept',
				'field_form_post_page'    => 'dropped',
				'field_form_post_unknown' => 'dropped',
			)
		);

		$this->save_post( $this->post_id );

		$this->assertSame( array( 'field_form_post_post' => 'kept' ), $this->saved_values );
	}

	/**
	 * The post allowed-keys filter can allow extra keys, and receives the post ID.
	 */
	public function test_save_post_allowed_keys_filter_adds_keys() {
		$received = null;
		$this->add_test_filter(
			'acf/form/post/allowed_field_keys',
			function ( $keys, $post_id ) use ( &$received ) {
				$received = $post_id;
				$keys[]   = 'field_form_post_injected';
				return $keys;
			},
			10,
			2
		);
		$this->submit(
			array(
				'field_form_post_post'     => 'kept',
				'field_form_post_injected' => 'kept',
			)
		);

		$this->save_post( $this->post_id );

		$this->assertSame( $this->post_id, $received );
		$this->assertSame(
			array(
				'field_form_post_post'     => 'kept',
				'field_form_post_injected' => 'kept',
			),
			$this->saved_values
		);
	}

	/**
	 * A field listed in a field list signed for the post is saved even if not located on it.
	 */
	public function test_save_post_accepts_fields_listed_for_post() {
		$this->submit( array( 'field_form_post_page' => 'kept' ) );
		$_POST['_acf_fields'] = array(
			(string) $this->post_id => array( $this->get_signed_field_list( (string) $this->post_id, array( 'field_form_post_page' ) ) ),
		);

		$this->save_post( $this->post_id );

		$this->assertSame( array( 'field_form_post_page' => 'kept' ), $this->saved_values );
	}

	/**
	 * A field list signed for another post grants nothing.
	 */
	public function test_save_post_ignores_field_list_signed_for_another_post() {
		$other_id = (string) ( $this->post_id + 1 );
		$this->submit(
			array(
				'field_form_post_post' => 'kept',
				'field_form_post_page' => 'dropped',
			)
		);
		$_POST['_acf_fields'] = array(
			(string) $this->post_id => array( $this->get_signed_field_list( $other_id, array( 'field_form_post_page' ) ) ),
			$other_id               => array( $this->get_signed_field_list( $other_id, array( 'field_form_post_page' ) ) ),
		);

		$this->save_post( $this->post_id );

		$this->assertSame( array( 'field_form_post_post' => 'kept' ), $this->saved_values );
	}

	/**
	 * A preview saved to a revision accepts the fields located on, and listed for, the post it belongs to.
	 */
	public function test_save_post_preview_revision_matches_parent_post() {
		$revision_id = _wp_put_post_revision( get_post( $this->post_id ) );
		$this->submit(
			array(
				'field_form_post_post' => 'kept',
				'field_form_post_page' => 'kept',
			)
		);
		$_POST['wp-preview']  = 'dopreview';
		$_POST['post_ID']     = $this->post_id;
		$_POST['_acf_fields'] = array(
			(string) $this->post_id => array( $this->get_signed_field_list( (string) $this->post_id, array( 'field_form_post_page' ) ) ),
		);

		$this->save_post( $revision_id );

		$this->assertSame(
			array(
				'field_form_post_post' => 'kept',
				'field_form_post_page' => 'kept',
			),
			$this->saved_values
		);
	}

	/**
	 * Uploaded files for fields the post does not accept are dropped.
	 */
	public function test_save_post_drops_uploads_for_fields_not_allowed() {
		$keys          = array( 'field_form_post_post', 'field_form_post_page' );
		$branch        = array_fill_keys( $keys, '' );
		$_FILES['acf'] = array(
			'name'     => $branch,
			'type'     => $branch,
			'tmp_name' => $branch,
			'error'    => array_fill_keys( $keys, UPLOAD_ERR_NO_FILE ),
			'size'     => array_fill_keys( $keys, 0 ),
		);
		$this->submit( array( 'field_form_post_page' => 'dropped' ) );

		$this->save_post( $this->post_id );

		/* phpcs:disable WordPress.Security -- Test reads the request it built. */
		foreach ( $_FILES['acf'] as $attribute => $uploaded ) {
			$this->assertSame( array( 'field_form_post_post' ), array_keys( $uploaded ), "Unexpected keys in {$attribute}." );
		}
		/* phpcs:enable WordPress.Security */
	}

	/**
	 * Builds a nonce-verified post form submission.
	 *
	 * @param array $values The submitted acf values.
	 */
	private function submit( array $values ) {
		$_POST['_acf_screen'] = 'post';
		$_POST['_acf_nonce']  = wp_create_nonce( 'post' );
		$_POST['acf']         = $values;
	}

	/**
	 * Runs the post form save for a post.
	 *
	 * @param integer $post_id The post ID.
	 */
	private function save_post( $post_id ) {
		( new ACF_Form_Post() )->save_post( $post_id, get_post( $post_id ) );
	}

	/**
	 * Returns a field list signed for a render ID, as acf_render_fields() outputs it.
	 *
	 * @param string $render_id The render ID.
	 * @param array  $keys      The listed field keys.
	 * @return string
	 */
	private function get_signed_field_list( $render_id, array $keys ) {
		ob_start();
		_acf_render_field_list( $keys, $render_id, '_acf_fields' );
		$processor = new WP_HTML_Tag_Processor( ob_get_clean() );
		$processor->next_tag( 'input' );

		return (string) $processor->get_attribute( 'value' );
	}

	/**
	 * Registers a local field group located on a post type, with one text field.
	 *
	 * @param string $group_key The field group key.
	 * @param string $post_type The post type the group is located on.
	 * @param string $field_key The field key.
	 */
	private function add_group( $group_key, $post_type, $field_key ) {
		acf_add_local_field_group(
			array(
				'key'      => $group_key,
				'title'    => $group_key,
				'fields'   => array(
					array(
						'key'   => $field_key,
						'name'  => $field_key,
						'label' => $field_key,
						'type'  => 'text',
					),
				),
				'active'   => true,
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => $post_type,
						),
					),
				),
			)
		);
	}

	/**
	 * Adds and tracks a test-specific filter.
	 *
	 * @param string   $hook          Filter name.
	 * @param callable $callback      Filter callback.
	 * @param int      $priority      Filter priority.
	 * @param int      $accepted_args Accepted argument count.
	 */
	private function add_test_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook, $callback, $priority, $accepted_args );

		$this->test_filters[] = array(
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		);
	}

	/**
	 * Resets the stores the fixtures write to.
	 */
	private function reset_stores() {
		foreach ( array( 'local-empty', 'fields', 'field-groups', 'values', 'form' ) as $store_name ) {
			$store = acf_get_store( $store_name );
			if ( $store ) {
				$store->reset();
			}
		}
	}
}
