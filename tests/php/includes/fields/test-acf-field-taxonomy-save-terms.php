<?php
/**
 * Tests which objects the Taxonomy field's save_terms setting writes term relationships to.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

require_once dirname( __DIR__ ) . '/forms/wc-order-test-functions.php';

/**
 * Class Test_ACF_Field_Taxonomy_Save_Terms
 */
class Test_ACF_Field_Taxonomy_Save_Terms extends BaseTestCase {

	/**
	 * Term relationships written during the test, as object ID and taxonomy pairs.
	 *
	 * @var array
	 */
	private $writes = array();

	/**
	 * The Taxonomy field type.
	 *
	 * @var acf_field_taxonomy
	 */
	private $field_type;

	/**
	 * Registers the taxonomies and starts recording term relationship writes.
	 */
	public function set_up() {
		parent::set_up();

		register_taxonomy( 'scf_post_tax', array( 'post', 'page' ) );
		register_taxonomy( 'scf_user_tax', 'user' );
		register_taxonomy( 'scf_comment_tax', 'comment' );
		register_taxonomy( 'scf_order_tax', 'shop_order' );
		register_taxonomy(
			'scf_admin_tax',
			'post',
			array( 'capabilities' => array( 'assign_terms' => 'manage_options' ) )
		);

		acf_include( 'includes/fields/class-acf-field-taxonomy.php' );
		$this->field_type = acf_get_field_type( 'taxonomy' );

		$this->writes = array();
		add_action( 'set_object_terms', array( $this, 'record_write' ), 10, 4 );
	}

	/**
	 * Stops recording and removes the taxonomies.
	 */
	public function tear_down() {
		remove_action( 'set_object_terms', array( $this, 'record_write' ), 10 );
		foreach ( array( 'scf_post_tax', 'scf_user_tax', 'scf_comment_tax', 'scf_order_tax', 'scf_admin_tax' ) as $taxonomy ) {
			unregister_taxonomy( $taxonomy );
		}
		wp_set_current_user( 0 );
		unset( $GLOBALS['acf_form'] );

		parent::tear_down();
	}

	/**
	 * Records a term relationship write.
	 *
	 * @param integer $object_id The object the terms were written to.
	 * @param array   $terms     The terms.
	 * @param array   $tt_ids    The term taxonomy IDs.
	 * @param string  $taxonomy  The taxonomy.
	 */
	public function record_write( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'scf_post_tax', 'scf_user_tax', 'scf_comment_tax', 'scf_order_tax', 'scf_admin_tax' ), true ) ) {
			$this->writes[] = array( (int) $object_id, $taxonomy );
		}
	}

	/**
	 * Saves a term to an ACF post ID through a Taxonomy field with save_terms enabled.
	 *
	 * @param string         $taxonomy The field's taxonomy.
	 * @param integer|string $post_id  The ACF post ID being saved.
	 * @return mixed The value the field saves.
	 */
	private function save_terms( $taxonomy, $post_id ) {
		$value = $this->field_type->update_value( array( 3 ), $post_id, $this->get_field( $taxonomy ) );
		$this->field_type->save_post( $post_id );

		return $value;
	}

	/**
	 * Saves a term as a value submitted through a form or the REST API.
	 *
	 * @param string         $taxonomy The field's taxonomy.
	 * @param integer|string $post_id  The ACF post ID being saved.
	 * @return mixed The value the field saves.
	 */
	private function save_submitted_terms( $taxonomy, $post_id ) {
		return _acf_save_submitted_values(
			function () use ( $taxonomy, $post_id ) {
				return $this->save_terms( $taxonomy, $post_id );
			}
		);
	}

	/**
	 * Returns a Taxonomy field with save_terms enabled.
	 *
	 * @param string $taxonomy The field's taxonomy.
	 * @return array
	 */
	private function get_field( $taxonomy ) {
		return array(
			'key'                  => 'field_save_terms_' . $taxonomy,
			'name'                 => 'save_terms_' . $taxonomy,
			'type'                 => 'taxonomy',
			'taxonomy'             => $taxonomy,
			'save_terms'           => 1,
			'bidirectional'        => 0,
			'bidirectional_target' => array(),
		);
	}

	/**
	 * Inserts a user with the given role and makes them the current user.
	 *
	 * @param string $role The role.
	 */
	private function log_in_as( $role ) {
		wp_set_current_user(
			wp_insert_user(
				array(
					'user_login' => $role . '_' . uniqid(),
					'user_pass'  => 'password',
					'role'       => $role,
				)
			)
		);
	}

	/**
	 * Saving a post writes the terms to that post.
	 */
	public function test_post_save_writes_terms_to_the_post() {
		$post_id = wp_insert_post( array( 'post_title' => 'Target' ) );

		$this->save_terms( 'scf_post_tax', $post_id );

		$this->assertSame( array( array( $post_id, 'scf_post_tax' ) ), $this->writes );
	}

	/**
	 * Saving a user doesn't write a post taxonomy's terms to the post with the same ID.
	 */
	public function test_user_save_does_not_write_terms_to_a_post_with_the_same_id() {
		$post_id = wp_insert_post( array( 'post_title' => 'Target' ) );

		$this->save_terms( 'scf_post_tax', "user_{$post_id}" );

		$this->assertSame( array(), $this->writes );
	}

	/**
	 * Saving a comment doesn't write a post taxonomy's terms to the post with the same ID.
	 */
	public function test_comment_save_does_not_write_terms_to_a_post_with_the_same_id() {
		$post_id = wp_insert_post( array( 'post_title' => 'Target' ) );

		$this->save_terms( 'scf_post_tax', "comment_{$post_id}" );

		$this->assertSame( array(), $this->writes );
	}

	/**
	 * Saving a term doesn't write terms to the post with the same ID.
	 */
	public function test_term_save_does_not_write_terms_to_a_post_with_the_same_id() {
		$post_id = wp_insert_post( array( 'post_title' => 'Target' ) );

		$this->save_terms( 'scf_post_tax', "term_{$post_id}" );

		$this->assertSame( array(), $this->writes );
	}

	/**
	 * Taxonomies registered for users or comments still save terms for them.
	 */
	public function test_user_and_comment_taxonomies_still_save_terms() {
		$this->save_terms( 'scf_user_tax', 'user_7' );
		$this->save_terms( 'scf_comment_tax', 'comment_8' );

		$this->assertSame(
			array(
				array( 7, 'scf_user_tax' ),
				array( 8, 'scf_comment_tax' ),
			),
			$this->writes
		);
	}

	/**
	 * WooCommerce orders only keep terms in taxonomies registered for their order type.
	 */
	public function test_orders_only_save_terms_in_taxonomies_for_their_type() {
		$this->save_terms( 'scf_order_tax', 'woo_order_9' );
		$this->save_terms( 'scf_post_tax', 'woo_order_9' );

		$this->assertSame( array( array( 9, 'scf_order_tax' ) ), $this->writes );
	}

	/**
	 * Submitted terms aren't assigned when the user can't assign terms in the taxonomy.
	 */
	public function test_submitted_terms_need_permission_to_assign_terms() {
		$post_id = wp_insert_post( array( 'post_title' => 'Target' ) );
		$this->log_in_as( 'author' );

		$this->save_submitted_terms( 'scf_admin_tax', $post_id );

		$this->assertSame( array(), $this->writes );
	}

	/**
	 * Submitted terms keep the stored value when the user can't assign terms in the taxonomy.
	 */
	public function test_submitted_terms_keep_the_stored_value_without_permission() {
		$post_id = wp_insert_post( array( 'post_title' => 'Target' ) );
		update_post_meta( $post_id, 'save_terms_scf_admin_tax', array( '5' ) );
		$this->log_in_as( 'author' );

		$value = $this->save_submitted_terms( 'scf_admin_tax', $post_id );

		$this->assertSame( array( '5' ), $value );
	}

	/**
	 * Submitted terms are assigned when the user can assign terms in the taxonomy.
	 */
	public function test_submitted_terms_save_for_users_who_can_assign_terms() {
		$post_id = wp_insert_post( array( 'post_title' => 'Target' ) );
		$this->log_in_as( 'administrator' );

		$value = $this->save_submitted_terms( 'scf_admin_tax', $post_id );

		$this->assertSame( array( 3 ), $value );
		$this->assertSame( array( array( $post_id, 'scf_admin_tax' ) ), $this->writes );
	}

	/**
	 * Terms saved by code aren't checked against the current user.
	 */
	public function test_terms_saved_by_code_are_not_checked() {
		$post_id = wp_insert_post( array( 'post_title' => 'Target' ) );
		$this->log_in_as( 'author' );

		$this->save_terms( 'scf_admin_tax', $post_id );

		$this->assertSame( array( array( $post_id, 'scf_admin_tax' ) ), $this->writes );
	}

	/**
	 * Terms submitted through a front-end form are saved for visitors who aren't logged in.
	 */
	public function test_front_end_form_terms_are_not_checked() {
		$post_id             = wp_insert_post( array( 'post_title' => 'Target' ) );
		$GLOBALS['acf_form'] = array( 'post_id' => $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates a front-end form submission.

		$this->save_submitted_terms( 'scf_admin_tax', $post_id );

		$this->assertSame( array( array( $post_id, 'scf_admin_tax' ) ), $this->writes );
	}
}
