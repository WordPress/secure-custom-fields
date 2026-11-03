<?php
/**
 * Tests that WordPress's own custom field editing can't change ACF field values.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-includes/class-IXR.php';
require_once ABSPATH . 'wp-includes/class-wp-xmlrpc-server.php';

/**
 * Tests the post meta capabilities mapped by acf_map_post_meta_caps().
 *
 * @group meta
 * @group security
 *
 * @covers ::acf_map_post_meta_caps
 * @covers ::_acf_is_field_meta_key
 */
class Test_Protected_Field_Meta extends BaseTestCase {

	private const GROUP_KEY = 'group_scf_protected_meta';

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Registers a field group with top-level, group and repeater fields, and a post to edit.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->ensure_field_types();

		acf_add_local_field_group(
			array(
				'key'      => self::GROUP_KEY,
				'title'    => 'Protected Meta',
				'fields'   => array(
					array(
						'key'  => 'field_scf_pm_text',
						'name' => 'scf_pm_text',
						'type' => 'text',
					),
					array(
						'key'        => 'field_scf_pm_group',
						'name'       => 'scf_pm_group',
						'type'       => 'group',
						'sub_fields' => array(
							array(
								'key'  => 'field_scf_pm_group_inner',
								'name' => 'inner',
								'type' => 'text',
							),
						),
					),
					array(
						'key'        => 'field_scf_pm_rows',
						'name'       => 'scf_pm_rows',
						'type'       => 'repeater',
						'sub_fields' => array(
							array(
								'key'  => 'field_scf_pm_rows_cell',
								'name' => 'cell',
								'type' => 'text',
							),
						),
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

		$this->admin_id = wp_insert_user(
			array(
				'user_login' => 'scf_pm_admin',
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);

		$this->post_id = wp_insert_post(
			array(
				'post_title'  => 'Protected Meta Post',
				'post_status' => 'publish',
				'post_author' => $this->admin_id,
			)
		);

		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Removes the field group, registered meta and request state.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		acf_remove_local_field_group( self::GROUP_KEY );
		unregister_post_meta( 'post', 'scf_pm_text' );
		wp_set_current_user( 0 );
		$_POST = array();

		parent::tear_down();
	}

	/**
	 * Registers the field types used by these tests with their hooks.
	 *
	 * Field types normally register on 'init', which never fires in WorDBless, and the per-test
	 * hook restore wipes their filters, so they are registered again for every test.
	 *
	 * @return void
	 */
	private function ensure_field_types(): void {
		acf_include( 'includes/fields/class-acf-field-text.php' );
		acf_include( 'includes/fields/class-acf-field-group.php' );
		acf_include( 'includes/fields/class-acf-repeater-table.php' );
		acf_include( 'includes/fields/class-acf-field-repeater.php' );

		$types = array(
			'text'     => 'acf_field_text',
			'group'    => 'acf_field__group',
			'repeater' => 'acf_field_repeater',
		);

		foreach ( $types as $name => $class ) {
			if ( ! has_filter( "acf/prepare_field_for_import/type={$name}" ) ) {
				acf_register_field_type( $class );
			}
		}
	}

	/**
	 * Returns whether the current user can add, edit and delete a meta key on the test post.
	 *
	 * @param string $meta_key The meta key.
	 * @return array
	 */
	private function meta_caps( string $meta_key ): array {
		return array(
			'add'    => current_user_can( 'add_post_meta', $this->post_id, $meta_key ),
			'edit'   => current_user_can( 'edit_post_meta', $this->post_id, $meta_key ),
			'delete' => current_user_can( 'delete_post_meta', $this->post_id, $meta_key ),
		);
	}

	/**
	 * A top-level field's meta key can't be added, edited or deleted through post meta capabilities.
	 *
	 * @return void
	 */
	public function test_field_meta_key_is_protected(): void {
		$this->assertSame(
			array(
				'add'    => false,
				'edit'   => false,
				'delete' => false,
			),
			$this->meta_caps( 'scf_pm_text' )
		);
	}

	/**
	 * The `_`-prefixed field key reference of a field can't be changed either.
	 *
	 * @return void
	 */
	public function test_field_reference_key_is_protected(): void {
		$this->assertSame(
			array(
				'add'    => false,
				'edit'   => false,
				'delete' => false,
			),
			$this->meta_caps( '_scf_pm_text' )
		);
	}

	/**
	 * Group and repeater row sub field meta keys are protected.
	 *
	 * @return void
	 */
	public function test_sub_field_meta_keys_are_protected(): void {
		$this->assertFalse( current_user_can( 'edit_post_meta', $this->post_id, 'scf_pm_group_inner' ), 'Group sub field.' );
		$this->assertFalse( current_user_can( 'edit_post_meta', $this->post_id, 'scf_pm_rows_0_cell' ), 'First repeater row.' );
		$this->assertFalse( current_user_can( 'edit_post_meta', $this->post_id, 'scf_pm_rows_12_cell' ), 'Later repeater row.' );
	}

	/**
	 * A meta key the post references a field key for is protected even if no field is named after it.
	 *
	 * @return void
	 */
	public function test_meta_key_with_field_key_reference_is_protected(): void {
		update_post_meta( $this->post_id, '_scf_pm_renamed', 'field_scf_pm_old_name' );

		$this->assertFalse( current_user_can( 'edit_post_meta', $this->post_id, 'scf_pm_renamed' ) );
	}

	/**
	 * Meta keys that don't belong to a field stay editable.
	 *
	 * @return void
	 */
	public function test_unrelated_meta_key_is_not_protected(): void {
		$this->assertSame(
			array(
				'add'    => true,
				'edit'   => true,
				'delete' => true,
			),
			$this->meta_caps( 'scf_pm_unrelated' )
		);
	}

	/**
	 * Keys that only look like repeater rows are not protected.
	 *
	 * @return void
	 */
	public function test_non_numeric_row_meta_key_is_not_protected(): void {
		$this->assertTrue( current_user_can( 'edit_post_meta', $this->post_id, 'scf_pm_rows_x_cell' ) );
	}

	/**
	 * Meta keys registered with register_meta() are left to WordPress.
	 *
	 * @return void
	 */
	public function test_registered_meta_key_is_left_to_wordpress(): void {
		register_post_meta(
			'post',
			'scf_pm_text',
			array(
				'auth_callback' => '__return_true',
			)
		);

		$this->assertTrue( current_user_can( 'edit_post_meta', $this->post_id, 'scf_pm_text' ) );
	}

	/**
	 * The Custom Fields meta box can't add a value for a field.
	 *
	 * @return void
	 */
	public function test_custom_fields_meta_box_cannot_add_field_meta(): void {
		$_POST = array(
			'metakeyinput' => 'scf_pm_text',
			'metavalue'    => 'injected',
		);

		$this->assertFalse( add_meta( $this->post_id ) );
		$this->assertSame( '', get_post_meta( $this->post_id, 'scf_pm_text', true ) );
	}

	/**
	 * The Custom Fields meta box still adds unrelated meta.
	 *
	 * @return void
	 */
	public function test_custom_fields_meta_box_adds_unrelated_meta(): void {
		$_POST = array(
			'metakeyinput' => 'scf_pm_unrelated',
			'metavalue'    => 'allowed',
		);

		$this->assertIsInt( add_meta( $this->post_id ) );
		$this->assertSame( 'allowed', get_post_meta( $this->post_id, 'scf_pm_unrelated', true ) );
	}

	/**
	 * XML-RPC custom fields can't add field values or references, but can add unrelated meta.
	 *
	 * @return void
	 */
	public function test_xmlrpc_custom_fields_cannot_add_field_meta(): void {
		$server = new wp_xmlrpc_server();
		$server->set_custom_fields(
			$this->post_id,
			array(
				array(
					'key'   => 'scf_pm_text',
					'value' => 'injected',
				),
				array(
					'key'   => '_scf_pm_text',
					'value' => 'field_scf_pm_text',
				),
				array(
					'key'   => 'scf_pm_rows_0_cell',
					'value' => 'injected',
				),
				array(
					'key'   => 'scf_pm_unrelated',
					'value' => 'allowed',
				),
			)
		);

		$this->assertSame( '', get_post_meta( $this->post_id, 'scf_pm_text', true ) );
		$this->assertSame( '', get_post_meta( $this->post_id, '_scf_pm_text', true ) );
		$this->assertSame( '', get_post_meta( $this->post_id, 'scf_pm_rows_0_cell', true ) );
		$this->assertSame( 'allowed', get_post_meta( $this->post_id, 'scf_pm_unrelated', true ) );
	}

	/**
	 * ACF's own field API still saves field values.
	 *
	 * @return void
	 */
	public function test_acf_api_still_saves_field_meta(): void {
		update_field( 'field_scf_pm_text', 'saved', $this->post_id );

		$this->assertSame( 'saved', get_post_meta( $this->post_id, 'scf_pm_text', true ) );
	}
}
