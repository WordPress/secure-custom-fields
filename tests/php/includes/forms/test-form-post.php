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
	 * Test should_validate_post returns true for statuses that publish the post
	 * or hand it off for review.
	 */
	public function test_should_validate_post_for_publishing_statuses() {
		$form_post = new ACF_Form_Post();

		foreach ( array( 'publish', 'pending', 'future', 'private' ) as $status ) {
			$post = (object) array(
				'ID'          => 1,
				'post_type'   => 'post',
				'post_status' => $status,
			);

			$this->assertTrue(
				$form_post->should_validate_post( $post ),
				sprintf( 'Status "%s" should trigger required-field validation', $status )
			);
		}
	}

	/**
	 * Test should_validate_post returns false for intermediate statuses so
	 * drafts and auto-drafts keep saving without enforcement.
	 */
	public function test_should_validate_post_for_drafting_statuses() {
		$form_post = new ACF_Form_Post();

		foreach ( array( 'draft', 'auto-draft', 'inherit', 'trash' ) as $status ) {
			$post = (object) array(
				'ID'          => 1,
				'post_type'   => 'post',
				'post_status' => $status,
			);

			$this->assertFalse(
				$form_post->should_validate_post( $post ),
				sprintf( 'Status "%s" should bypass required-field validation', $status )
			);
		}
	}
}
