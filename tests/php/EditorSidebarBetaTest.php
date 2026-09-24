<?php
/**
 * Tests for the "Move Elements to Editor Sidebar" beta feature.
 *
 * Covers the shared position rule, the meta box order correction that keeps
 * field groups in the sidebar, and the classic editor fallback.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Editor_Sidebar_Beta_Test
 */
class Editor_Sidebar_Beta_Test extends BaseTestCase {

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();

		acf_include( 'includes/admin/beta-features.php' );

		// Location rules register when their class file is included, which only
		// happens on "init" and never fires here.
		acf_include( 'includes/locations/class-acf-location-post-type.php' );

		delete_option( 'scf_beta_feature_editor_sidebar_enabled' );
	}

	/**
	 * Clean up.
	 */
	public function tearDown(): void {
		delete_option( 'scf_beta_feature_editor_sidebar_enabled' );
		acf_reset_local();

		parent::tearDown();
	}

	/**
	 * Enables the beta feature.
	 *
	 * @return void
	 */
	private function enable_beta() {
		update_option( 'scf_beta_feature_editor_sidebar_enabled', true );
	}

	/**
	 * The editor sidebar feature is registered without a manual call.
	 *
	 * @return void
	 */
	public function test_editor_sidebar_is_registered_by_default() {
		$beta_features = acf()->admin_beta_features->get_beta_features();

		$this->assertArrayHasKey( 'editor_sidebar', $beta_features );
		$this->assertInstanceOf( 'SCF_Admin_Beta_Feature_Editor_Sidebar', $beta_features['editor_sidebar'] );
	}

	/**
	 * Reading the features twice does not register them twice.
	 *
	 * @return void
	 */
	public function test_editor_sidebar_is_registered_once() {
		$beta_features = acf()->admin_beta_features;

		$beta_features->get_beta_features();
		$beta_features->get_beta_features();
		$beta_features->get_beta_feature( 'editor_sidebar' );

		$this->assertCount( 1, $beta_features->get_beta_features() );
	}

	/**
	 * A disabled feature leaves the saved position alone.
	 *
	 * @return void
	 */
	public function test_position_is_unchanged_when_the_beta_is_disabled() {
		$this->assertSame( 'normal', scf_get_field_group_meta_box_position( 'post', 'normal' ) );
		$this->assertSame( 'acf_after_title', scf_get_field_group_meta_box_position( 'post', 'acf_after_title' ) );
		$this->assertFalse( scf_field_groups_use_editor_sidebar( 'post' ) );
	}

	/**
	 * An enabled feature moves content positions into the sidebar.
	 *
	 * @return void
	 */
	public function test_position_moves_to_side_when_the_beta_is_enabled() {
		$this->enable_beta();

		$this->assertTrue( scf_field_groups_use_editor_sidebar( 'post' ) );
		$this->assertSame( 'side', scf_get_field_group_meta_box_position( 'post', 'normal' ) );
		$this->assertSame( 'side', scf_get_field_group_meta_box_position( 'post', 'acf_after_title' ) );
		$this->assertSame( 'side', scf_get_field_group_meta_box_position( 'post', 'side' ) );
	}

	/**
	 * Post types using the classic editor keep their position.
	 *
	 * @return void
	 */
	public function test_position_is_unchanged_for_classic_editor_post_types() {
		$this->enable_beta();
		add_filter( 'use_block_editor_for_post_type', '__return_false' );

		$this->assertFalse( scf_field_groups_use_editor_sidebar( 'post' ) );
		$this->assertSame( 'normal', scf_get_field_group_meta_box_position( 'post', 'normal' ) );

		remove_filter( 'use_block_editor_for_post_type', '__return_false' );
	}

	/**
	 * An unknown post type never uses the sidebar.
	 *
	 * @return void
	 */
	public function test_position_is_unchanged_without_a_post_type() {
		$this->enable_beta();

		$this->assertFalse( scf_field_groups_use_editor_sidebar( '' ) );
		$this->assertFalse( scf_field_groups_use_editor_sidebar( null ) );
		$this->assertFalse( scf_field_groups_use_editor_sidebar( 'no_such_post_type' ) );
		$this->assertSame( 'normal', scf_get_field_group_meta_box_position( '', 'normal' ) );
	}

	/**
	 * Saved order entries for moved groups end up in the sidebar.
	 *
	 * @return void
	 */
	public function test_saved_order_moves_moved_groups_to_the_sidebar() {
		$order = array(
			'acf_after_title' => 'acf-group_one',
			'normal'          => 'acf-group_two,submitdiv',
			'side'            => 'postcustom',
		);

		$result = scf_move_meta_box_ids_to_sidebar( $order, array( 'acf-group_one', 'acf-group_two' ) );

		$this->assertSame( '', $result['acf_after_title'] );
		$this->assertSame( 'submitdiv', $result['normal'] );
		$this->assertSame( 'postcustom,acf-group_one,acf-group_two', $result['side'] );
	}

	/**
	 * Groups already listed in the sidebar are not listed twice.
	 *
	 * @return void
	 */
	public function test_saved_order_does_not_duplicate_sidebar_entries() {
		$order = array(
			'normal' => 'acf-group_one',
			'side'   => 'acf-group_one,postcustom',
		);

		$result = scf_move_meta_box_ids_to_sidebar( $order, array( 'acf-group_one' ) );

		$this->assertSame( 'acf-group_one,postcustom', $result['side'] );
		$this->assertSame( '', $result['normal'] );
	}

	/**
	 * Unrelated meta boxes and unusable input are left alone.
	 *
	 * @return void
	 */
	public function test_saved_order_keeps_unrelated_meta_boxes() {
		$order = array(
			'normal' => 'submitdiv,acf-group_one',
			'side'   => '',
		);

		$result = scf_move_meta_box_ids_to_sidebar( $order, array( 'acf-group_other' ) );

		$this->assertSame( 'submitdiv,acf-group_one', $result['normal'] );
		$this->assertSame( '', $result['side'] );

		$this->assertFalse( scf_move_meta_box_ids_to_sidebar( false, array( 'acf-group_one' ) ) );
		$this->assertSame( $order, scf_move_meta_box_ids_to_sidebar( $order, array() ) );
	}

	/**
	 * Registers a field group and returns the location it was registered with.
	 *
	 * @param string $key      The field group key.
	 * @param string $position The position saved on the field group.
	 * @return string
	 */
	private function get_registered_meta_box_location( $key, $position ) {
		global $wp_meta_boxes;

		$original_meta_boxes = isset( $wp_meta_boxes ) ? $wp_meta_boxes : array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Started from a clean registry and restored below.
		$wp_meta_boxes = array();

		acf_add_local_field_group(
			array(
				'key'      => $key,
				'title'    => ucwords( str_replace( '_', ' ', $key ) ),
				'position' => $position,
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

		$post_id   = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'Sidebar test',
			)
		);
		$form_post = acf_get_instance( 'ACF_Form_Post' );

		$form_post->add_meta_boxes( 'post', get_post( $post_id ) );

		$location = '';

		foreach ( (array) $wp_meta_boxes as $locations ) {
			foreach ( (array) $locations as $name => $priorities ) {
				foreach ( (array) $priorities as $boxes ) {
					if ( array_key_exists( 'acf-' . $key, (array) $boxes ) ) {
						$location = $name;
					}
				}
			}
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the value captured above.
		$wp_meta_boxes = $original_meta_boxes;

		return $location;
	}

	/**
	 * Field groups are registered as sidebar meta boxes when the beta is on.
	 *
	 * @return void
	 */
	public function test_meta_boxes_are_registered_in_the_sidebar() {
		$this->enable_beta();
		acf_reset_local();

		$this->assertSame( 'side', $this->get_registered_meta_box_location( 'group_editor_sidebar_on', 'normal' ) );
	}

	/**
	 * Field groups keep their saved location when the beta is off.
	 *
	 * @return void
	 */
	public function test_meta_boxes_keep_their_location_when_the_beta_is_disabled() {
		acf_reset_local();

		$this->assertSame( 'normal', $this->get_registered_meta_box_location( 'group_editor_sidebar_off', 'normal' ) );
	}
}
