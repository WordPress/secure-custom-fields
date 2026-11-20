<?php
/**
 * Tests the [acf] shortcode with prefixed post IDs.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests that the [acf] shortcode doesn't show fields from draft or private posts referenced by a
 * prefixed ID such as post_123, inside an ACF Block or outside the loop.
 */
class Test_API_Template_Shortcode_Post_ID extends BaseTestCase {

	/**
	 * A published post.
	 *
	 * @var int
	 */
	private $public_id;

	/**
	 * A draft post.
	 *
	 * @var int
	 */
	private $draft_id;

	/**
	 * Original enable_shortcode setting.
	 *
	 * @var bool
	 */
	private $original_shortcode_setting;

	/**
	 * Create posts and a field, as a logged-out visitor.
	 */
	public function set_up() {
		parent::set_up();

		$this->public_id = wp_insert_post(
			array(
				'post_title'  => 'Public post',
				'post_status' => 'publish',
			)
		);
		$this->draft_id  = wp_insert_post(
			array(
				'post_title'  => 'Draft post',
				'post_status' => 'draft',
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_shortcode_post_id',
				'title'    => 'Shortcode Post ID',
				'fields'   => array(
					array(
						'key'               => 'field_shortcode_post_id_text',
						'name'              => 'shortcode_post_id_text',
						'label'             => 'Text',
						'type'              => 'text',
						// Local fields default to no shortcode access; these tests are about post visibility.
						'allow_in_bindings' => true,
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
		update_field( 'shortcode_post_id_text', 'Draft value', $this->draft_id );
		acf_get_store( 'values' )->reset();

		$this->original_shortcode_setting = acf_get_setting( 'enable_shortcode' );
		acf_update_setting( 'enable_shortcode', true );

		wp_set_current_user( 0 );
		unset( $GLOBALS['post'] );
	}

	/**
	 * Remove test data.
	 */
	public function tear_down() {
		acf_update_setting( 'enable_shortcode', $this->original_shortcode_setting );
		acf_remove_local_field_group( 'group_shortcode_post_id' );
		acf_remove_local_field( 'field_shortcode_post_id_text' );
		acf_get_store( 'values' )->reset();
		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * Renders the shortcode for the draft post's field.
	 *
	 * @param mixed $post_id The shortcode's post_id attribute.
	 * @return mixed
	 */
	private function render( $post_id ) {
		return acf_shortcode(
			array(
				'field'   => 'shortcode_post_id_text',
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * Outside the loop, a draft post referenced as post_123 is not shown.
	 */
	public function test_prefixed_draft_post_id_outside_loop_is_hidden() {
		$this->assertEmpty( $this->render( 'post_' . $this->draft_id ) );
		$this->assertEmpty( $this->render( $this->draft_id ) );
	}

	/**
	 * Inside an ACF Block, a draft post referenced as post_123 is not shown.
	 */
	public function test_prefixed_draft_post_id_inside_block_is_hidden() {
		$GLOBALS['post'] = get_post( $this->public_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		acf_setup_meta( array(), 'block_shortcode_post_id', true );

		$output = $this->render( 'post_' . $this->draft_id );

		acf_reset_meta( 'block_shortcode_post_id' );

		$this->assertEmpty( $output );
	}

	/**
	 * The current post's own fields are still shown when referenced by a prefixed ID.
	 */
	public function test_prefixed_current_post_id_is_shown() {
		$GLOBALS['post'] = get_post( $this->draft_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( 'Draft value', $this->render( 'post_' . $this->draft_id ) );
		$this->assertSame( 'Draft value', $this->render( $this->draft_id ) );
	}

	/**
	 * When the current post resolves to a revision, as in previews, its parent counts as the current post.
	 */
	public function test_revision_parent_counts_as_current_post() {
		$revision_id = wp_insert_post(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $this->draft_id,
				'post_name'   => $this->draft_id . '-revision-v1',
			)
		);

		$to_revision = static function ( $post_id ) use ( $revision_id ) {
			return $post_id ? $post_id : $revision_id;
		};
		add_filter( 'acf/validate_post_id', $to_revision );

		$output = $this->render( $this->draft_id );

		remove_filter( 'acf/validate_post_id', $to_revision );

		$this->assertSame( 'Draft value', $output );
	}

	/**
	 * A published post referenced as post_123 is still shown outside the loop.
	 */
	public function test_prefixed_public_post_id_outside_loop_is_shown() {
		update_field( 'shortcode_post_id_text', 'Public value', $this->public_id );
		acf_get_store( 'values' )->reset();

		$this->assertSame( 'Public value', $this->render( 'post_' . $this->public_id ) );
	}
}
