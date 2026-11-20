<?php
/**
 * Tests for the meta box callback edit restriction on post types and taxonomies.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test that meta box callbacks can only be set or changed by super admins.
 *
 * WorDBless runs as a single site, where is_super_admin() falls back to the
 * delete_users capability. An administrator stands in for a super admin, and an
 * administrator without delete_users stands in for a multisite subsite admin.
 *
 * @covers acf_settings_enable_meta_box_cb_edit
 * @covers ACF_Taxonomy::update_post
 * @covers ACF_Post_Type::update_post
 */
class Test_Meta_Box_Callback_Edit extends BaseTestCase {

	/**
	 * Admin handler for taxonomies.
	 *
	 * @var ACF_Admin_Taxonomy
	 */
	private static $taxonomy_admin;

	/**
	 * Admin handler for post types.
	 *
	 * @var ACF_Admin_Post_Type
	 */
	private static $post_type_admin;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! acf_get_internal_post_type_instance( 'acf-taxonomy' ) ) {
			do_action( 'plugins_loaded' );
			do_action( 'init' );
		}

		// The admin classes only load under is_admin().
		if ( ! class_exists( 'ACF_Admin_Internal_Post_Type' ) ) {
			acf_include( 'includes/admin/admin-internal-post-type.php' );
		}
		if ( ! class_exists( 'ACF_Admin_Taxonomy' ) ) {
			acf_include( 'includes/admin/post-types/admin-taxonomy.php' );
		}
		if ( ! class_exists( 'ACF_Admin_Post_Type' ) ) {
			acf_include( 'includes/admin/post-types/admin-post-type.php' );
		}

		if ( ! self::$taxonomy_admin ) {
			self::$taxonomy_admin  = new ACF_Admin_Taxonomy();
			self::$post_type_admin = new ACF_Admin_Post_Type();
		}

		// WorDBless stores the slashed data it receives from wp_insert_post_data,
		// so remove the slashes before it does, as the database layer would.
		add_filter( 'wp_insert_post_data', 'wp_unslash', 9 );
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		remove_filter( 'wp_insert_post_data', 'wp_unslash', 9 );
		unset( $_POST['acf_taxonomy'], $_POST['acf_post_type'], $_POST['_acf_nonce'] );
		wp_set_current_user( 0 );
		acf_update_setting( 'enable_meta_box_cb_edit', true );

		foreach ( array( 'acf-taxonomy', 'acf-post-type' ) as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'   => $post_type,
					'numberposts' => -1,
					'post_status' => 'any',
				)
			);
			foreach ( $posts as $post ) {
				wp_delete_post( $post->ID, true );
			}
		}

		parent::tearDown();
	}

	/**
	 * Creates an administrator and makes it the current user.
	 *
	 * @param boolean $super_admin Whether the user should pass is_super_admin().
	 * @return integer The user ID.
	 */
	private function login_as_administrator( $super_admin = true ) {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'mbcb_admin_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);

		if ( ! $super_admin ) {
			$user = get_user_by( 'id', $user_id );
			$user->add_cap( 'delete_users', false );
		}

		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Saves a taxonomy through the admin handler as the current user.
	 *
	 * @param integer $post_id   The acf-taxonomy post ID.
	 * @param array   $submitted The submitted taxonomy settings.
	 * @return array The raw stored taxonomy.
	 */
	private function submit_taxonomy( $post_id, array $submitted ) {
		$_POST['_acf_nonce']   = wp_create_nonce( 'taxonomy' );
		$_POST['acf_taxonomy'] = $submitted;

		self::$taxonomy_admin->save_post( $post_id, get_post( $post_id ) );

		return acf_get_raw_taxonomy( $post_id );
	}

	/**
	 * Saves a post type through the admin handler as the current user.
	 *
	 * @param integer $post_id   The acf-post-type post ID.
	 * @param array   $submitted The submitted post type settings.
	 * @return array The raw stored post type.
	 */
	private function submit_post_type( $post_id, array $submitted ) {
		$_POST['_acf_nonce']    = wp_create_nonce( 'post_type' );
		$_POST['acf_post_type'] = $submitted;

		self::$post_type_admin->save_post( $post_id, get_post( $post_id ) );

		return acf_get_raw_post_type( $post_id );
	}

	/**
	 * Creates a stored taxonomy as a super admin, leaving that user logged in.
	 *
	 * @param array $overrides Settings to override.
	 * @return array The stored taxonomy.
	 */
	private function create_taxonomy( array $overrides = array() ) {
		$this->login_as_administrator();

		return acf_update_taxonomy(
			array_merge(
				array(
					'key'         => 'taxonomy_' . uniqid(),
					'title'       => 'Callback Taxonomy',
					'taxonomy'    => 'cb_tax',
					'object_type' => array( 'post' ),
					'active'      => true,
				),
				$overrides
			)
		);
	}

	/**
	 * Creates a stored post type as a super admin, leaving that user logged in.
	 *
	 * @param array $overrides Settings to override.
	 * @return array The stored post type.
	 */
	private function create_post_type( array $overrides = array() ) {
		$this->login_as_administrator();

		return acf_update_post_type(
			array_merge(
				array(
					'key'       => 'post_type_' . uniqid(),
					'title'     => 'Callback Post Type',
					'post_type' => 'cb_cpt',
					'active'    => true,
				),
				$overrides
			)
		);
	}

	// =========================================================================
	// Setting
	// =========================================================================

	/**
	 * Test the setting is enabled for a super admin.
	 */
	public function test_setting_enabled_for_super_admin() {
		$this->login_as_administrator();

		$this->assertTrue( is_super_admin() );
		$this->assertTrue( acf_get_setting( 'enable_meta_box_cb_edit' ) );
	}

	/**
	 * Test the setting is disabled for an administrator who is not a super admin.
	 */
	public function test_setting_disabled_for_non_super_admin() {
		$this->login_as_administrator( false );

		$this->assertTrue( current_user_can( 'manage_options' ) );
		$this->assertFalse( is_super_admin() );
		$this->assertFalse( acf_get_setting( 'enable_meta_box_cb_edit' ) );
	}

	/**
	 * Test the setting is disabled for logged out users.
	 */
	public function test_setting_disabled_when_logged_out() {
		wp_set_current_user( 0 );

		$this->assertFalse( acf_get_setting( 'enable_meta_box_cb_edit' ) );
	}

	/**
	 * Test a super admin cannot bypass a setting that was turned off.
	 */
	public function test_setting_respects_disabled_value() {
		$this->login_as_administrator();
		acf_update_setting( 'enable_meta_box_cb_edit', false );

		$this->assertFalse( acf_get_setting( 'enable_meta_box_cb_edit' ) );
	}

	// =========================================================================
	// Taxonomy admin save
	// =========================================================================

	/**
	 * Test a non super admin cannot set taxonomy callbacks when saving.
	 */
	public function test_taxonomy_save_ignores_callbacks_for_non_super_admin() {
		$taxonomy = $this->create_taxonomy();
		$this->login_as_administrator( false );

		$stored = $this->submit_taxonomy(
			$taxonomy['ID'],
			array(
				'key'                  => $taxonomy['key'],
				'taxonomy'             => $taxonomy['taxonomy'],
				'object_type'          => array( 'post' ),
				'meta_box'             => 'custom',
				'meta_box_cb'          => '__return_null',
				'meta_box_sanitize_cb' => 'call_user_func',
			)
		);

		$this->assertSame( 'default', $stored['meta_box'] );
		$this->assertSame( '', $stored['meta_box_cb'] );
		$this->assertSame( '', $stored['meta_box_sanitize_cb'] );
	}

	/**
	 * Test a non super admin cannot change existing taxonomy callbacks when saving.
	 */
	public function test_taxonomy_save_keeps_existing_callbacks_for_non_super_admin() {
		$taxonomy = $this->create_taxonomy(
			array(
				'meta_box'             => 'custom',
				'meta_box_cb'          => 'my_meta_box',
				'meta_box_sanitize_cb' => 'my_sanitize',
			)
		);
		$this->login_as_administrator( false );

		$stored = $this->submit_taxonomy(
			$taxonomy['ID'],
			array(
				'key'                  => $taxonomy['key'],
				'taxonomy'             => $taxonomy['taxonomy'],
				'object_type'          => array( 'post' ),
				'meta_box'             => 'custom',
				'meta_box_cb'          => '__return_null',
				'meta_box_sanitize_cb' => 'call_user_func',
			)
		);

		$this->assertSame( 'custom', $stored['meta_box'] );
		$this->assertSame( 'my_meta_box', $stored['meta_box_cb'] );
		$this->assertSame( 'my_sanitize', $stored['meta_box_sanitize_cb'] );
	}

	/**
	 * Test existing taxonomy callbacks survive a save where the fields were not submitted.
	 */
	public function test_taxonomy_save_keeps_existing_callbacks_when_fields_hidden() {
		$taxonomy = $this->create_taxonomy(
			array(
				'meta_box'             => 'custom',
				'meta_box_cb'          => 'my_meta_box',
				'meta_box_sanitize_cb' => 'my_sanitize',
			)
		);
		$this->login_as_administrator( false );

		$stored = $this->submit_taxonomy(
			$taxonomy['ID'],
			array(
				'key'         => $taxonomy['key'],
				'taxonomy'    => $taxonomy['taxonomy'],
				'object_type' => array( 'post' ),
				'meta_box'    => 'custom',
			)
		);

		$this->assertSame( 'custom', $stored['meta_box'] );
		$this->assertSame( 'my_meta_box', $stored['meta_box_cb'] );
		$this->assertSame( 'my_sanitize', $stored['meta_box_sanitize_cb'] );
	}

	/**
	 * Test a super admin can set taxonomy callbacks when saving.
	 */
	public function test_taxonomy_save_stores_callbacks_for_super_admin() {
		$taxonomy = $this->create_taxonomy();
		$this->login_as_administrator();

		$stored = $this->submit_taxonomy(
			$taxonomy['ID'],
			array(
				'key'                  => $taxonomy['key'],
				'taxonomy'             => $taxonomy['taxonomy'],
				'object_type'          => array( 'post' ),
				'meta_box'             => 'custom',
				'meta_box_cb'          => 'my_meta_box',
				'meta_box_sanitize_cb' => 'my_sanitize',
			)
		);

		$this->assertSame( 'custom', $stored['meta_box'] );
		$this->assertSame( 'my_meta_box', $stored['meta_box_cb'] );
		$this->assertSame( 'my_sanitize', $stored['meta_box_sanitize_cb'] );
	}

	// =========================================================================
	// Post type admin save
	// =========================================================================

	/**
	 * Test a non super admin cannot set the post type callback when saving.
	 */
	public function test_post_type_save_ignores_callback_for_non_super_admin() {
		$post_type = $this->create_post_type();
		$this->login_as_administrator( false );

		$stored = $this->submit_post_type(
			$post_type['ID'],
			array(
				'key'                  => $post_type['key'],
				'post_type'            => $post_type['post_type'],
				'register_meta_box_cb' => '__return_null',
			)
		);

		$this->assertSame( '', $stored['register_meta_box_cb'] );
	}

	/**
	 * Test a non super admin cannot change the existing post type callback when saving.
	 */
	public function test_post_type_save_keeps_existing_callback_for_non_super_admin() {
		$post_type = $this->create_post_type( array( 'register_meta_box_cb' => 'my_meta_boxes' ) );
		$this->login_as_administrator( false );

		$stored = $this->submit_post_type(
			$post_type['ID'],
			array(
				'key'                  => $post_type['key'],
				'post_type'            => $post_type['post_type'],
				'register_meta_box_cb' => '__return_null',
			)
		);

		$this->assertSame( 'my_meta_boxes', $stored['register_meta_box_cb'] );
	}

	/**
	 * Test a super admin can set the post type callback when saving.
	 */
	public function test_post_type_save_stores_callback_for_super_admin() {
		$post_type = $this->create_post_type();
		$this->login_as_administrator();

		$stored = $this->submit_post_type(
			$post_type['ID'],
			array(
				'key'                  => $post_type['key'],
				'post_type'            => $post_type['post_type'],
				'register_meta_box_cb' => 'my_meta_boxes',
			)
		);

		$this->assertSame( 'my_meta_boxes', $stored['register_meta_box_cb'] );
	}

	// =========================================================================
	// Import
	// =========================================================================

	/**
	 * Test a non super admin cannot import taxonomy callbacks.
	 */
	public function test_taxonomy_import_ignores_callbacks_for_non_super_admin() {
		$this->login_as_administrator( false );

		$imported = acf_import_taxonomy(
			array(
				'key'                  => 'taxonomy_' . uniqid(),
				'title'                => 'Imported Taxonomy',
				'taxonomy'             => 'imported_cb_tax',
				'object_type'          => array( 'post' ),
				'meta_box'             => 'custom',
				'meta_box_cb'          => '__return_null',
				'meta_box_sanitize_cb' => 'call_user_func',
			)
		);
		$stored   = acf_get_raw_taxonomy( $imported['ID'] );

		$this->assertSame( 'default', $stored['meta_box'] );
		$this->assertSame( '', $stored['meta_box_cb'] );
		$this->assertSame( '', $stored['meta_box_sanitize_cb'] );
	}

	/**
	 * Test a non super admin cannot change existing taxonomy callbacks through import.
	 */
	public function test_taxonomy_import_keeps_existing_callbacks_for_non_super_admin() {
		$taxonomy = $this->create_taxonomy(
			array(
				'meta_box'             => 'custom',
				'meta_box_cb'          => 'my_meta_box',
				'meta_box_sanitize_cb' => 'my_sanitize',
			)
		);
		$this->login_as_administrator( false );

		$imported = acf_import_taxonomy(
			array(
				'ID'                   => $taxonomy['ID'],
				'key'                  => $taxonomy['key'],
				'title'                => $taxonomy['title'],
				'taxonomy'             => $taxonomy['taxonomy'],
				'object_type'          => array( 'post' ),
				'meta_box'             => 'custom',
				'meta_box_cb'          => '__return_null',
				'meta_box_sanitize_cb' => 'call_user_func',
			)
		);
		$stored   = acf_get_raw_taxonomy( $imported['ID'] );

		$this->assertSame( 'custom', $stored['meta_box'] );
		$this->assertSame( 'my_meta_box', $stored['meta_box_cb'] );
		$this->assertSame( 'my_sanitize', $stored['meta_box_sanitize_cb'] );
	}

	/**
	 * Test a super admin can import taxonomy callbacks.
	 */
	public function test_taxonomy_import_stores_callbacks_for_super_admin() {
		$this->login_as_administrator();

		$imported = acf_import_taxonomy(
			array(
				'key'                  => 'taxonomy_' . uniqid(),
				'title'                => 'Imported Taxonomy',
				'taxonomy'             => 'imported_cb_tax',
				'object_type'          => array( 'post' ),
				'meta_box'             => 'custom',
				'meta_box_cb'          => 'my_meta_box',
				'meta_box_sanitize_cb' => 'my_sanitize',
			)
		);
		$stored   = acf_get_raw_taxonomy( $imported['ID'] );

		$this->assertSame( 'custom', $stored['meta_box'] );
		$this->assertSame( 'my_meta_box', $stored['meta_box_cb'] );
		$this->assertSame( 'my_sanitize', $stored['meta_box_sanitize_cb'] );
	}

	/**
	 * Test a non super admin cannot import the post type callback.
	 */
	public function test_post_type_import_ignores_callback_for_non_super_admin() {
		$this->login_as_administrator( false );

		$imported = acf_import_post_type(
			array(
				'key'                  => 'post_type_' . uniqid(),
				'title'                => 'Imported Post Type',
				'post_type'            => 'imported_cb_cpt',
				'register_meta_box_cb' => '__return_null',
			)
		);
		$stored   = acf_get_raw_post_type( $imported['ID'] );

		$this->assertSame( '', $stored['register_meta_box_cb'] );
	}

	/**
	 * Test a non super admin cannot change the existing post type callback through import.
	 */
	public function test_post_type_import_keeps_existing_callback_for_non_super_admin() {
		$post_type = $this->create_post_type( array( 'register_meta_box_cb' => 'my_meta_boxes' ) );
		$this->login_as_administrator( false );

		$imported = acf_import_post_type(
			array(
				'ID'                   => $post_type['ID'],
				'key'                  => $post_type['key'],
				'title'                => $post_type['title'],
				'post_type'            => $post_type['post_type'],
				'register_meta_box_cb' => '__return_null',
			)
		);
		$stored   = acf_get_raw_post_type( $imported['ID'] );

		$this->assertSame( 'my_meta_boxes', $stored['register_meta_box_cb'] );
	}

	/**
	 * Test a super admin can import the post type callback.
	 */
	public function test_post_type_import_stores_callback_for_super_admin() {
		$this->login_as_administrator();

		$imported = acf_import_post_type(
			array(
				'key'                  => 'post_type_' . uniqid(),
				'title'                => 'Imported Post Type',
				'post_type'            => 'imported_cb_cpt',
				'register_meta_box_cb' => 'my_meta_boxes',
			)
		);
		$stored   = acf_get_raw_post_type( $imported['ID'] );

		$this->assertSame( 'my_meta_boxes', $stored['register_meta_box_cb'] );
	}

	// =========================================================================
	// Meta box choice, direct updates, and Local JSON sync
	// =========================================================================

	/**
	 * Test a non super admin can still switch an existing taxonomy between the default and no meta box.
	 */
	public function test_taxonomy_save_lets_non_super_admin_change_meta_box_between_default_and_disabled() {
		$taxonomy = $this->create_taxonomy( array( 'meta_box' => 'default' ) );
		$this->login_as_administrator( false );

		$stored = $this->submit_taxonomy(
			$taxonomy['ID'],
			array(
				'key'         => $taxonomy['key'],
				'taxonomy'    => $taxonomy['taxonomy'],
				'object_type' => array( 'post' ),
				'meta_box'    => 'disabled',
			)
		);

		$this->assertSame( 'disabled', $stored['meta_box'] );
	}

	/**
	 * Test a non super admin can leave a custom meta box without losing its stored callbacks.
	 */
	public function test_taxonomy_save_keeps_callbacks_when_non_super_admin_leaves_custom_meta_box() {
		$taxonomy = $this->create_taxonomy(
			array(
				'meta_box'             => 'custom',
				'meta_box_cb'          => 'my_meta_box',
				'meta_box_sanitize_cb' => 'my_sanitize',
			)
		);
		$this->login_as_administrator( false );

		$stored = $this->submit_taxonomy(
			$taxonomy['ID'],
			array(
				'key'         => $taxonomy['key'],
				'taxonomy'    => $taxonomy['taxonomy'],
				'object_type' => array( 'post' ),
				'meta_box'    => 'default',
			)
		);

		$this->assertSame( 'default', $stored['meta_box'] );
		$this->assertSame( 'my_meta_box', $stored['meta_box_cb'] );
		$this->assertSame( 'my_sanitize', $stored['meta_box_sanitize_cb'] );
	}

	/**
	 * Test a direct update, as the create and update abilities do, cannot set taxonomy callbacks for a non super admin.
	 */
	public function test_taxonomy_direct_update_ignores_callbacks_for_non_super_admin() {
		$this->login_as_administrator( false );

		$taxonomy = acf_update_taxonomy(
			array(
				'key'                  => 'taxonomy_' . uniqid(),
				'title'                => 'Callback Taxonomy',
				'taxonomy'             => 'cb_tax',
				'object_type'          => array( 'post' ),
				'meta_box'             => 'custom',
				'meta_box_cb'          => '__return_null',
				'meta_box_sanitize_cb' => 'call_user_func',
			)
		);
		$stored   = acf_get_raw_taxonomy( $taxonomy['ID'] );

		$this->assertSame( 'default', $stored['meta_box'] );
		$this->assertSame( '', $stored['meta_box_cb'] );
		$this->assertSame( '', $stored['meta_box_sanitize_cb'] );
	}

	/**
	 * Test a direct update, as the create and update abilities do, cannot set the post type callback for a non super admin.
	 */
	public function test_post_type_direct_update_ignores_callback_for_non_super_admin() {
		$this->login_as_administrator( false );

		$post_type = acf_update_post_type(
			array(
				'key'                  => 'post_type_' . uniqid(),
				'title'                => 'Callback Post Type',
				'post_type'            => 'cb_cpt',
				'register_meta_box_cb' => '__return_null',
			)
		);
		$stored    = acf_get_raw_post_type( $post_type['ID'] );

		$this->assertSame( '', $stored['register_meta_box_cb'] );
	}

	/**
	 * Test Local JSON sync keeps the callbacks from the file for a non super admin.
	 */
	public function test_taxonomy_sync_keeps_file_callbacks_for_non_super_admin() {
		$this->login_as_administrator( false );

		$synced = acf_skip_internal_post_sanitization(
			'acf_import_taxonomy',
			array(
				'key'                  => 'taxonomy_' . uniqid(),
				'title'                => 'Synced Taxonomy',
				'taxonomy'             => 'synced_cb_tax',
				'object_type'          => array( 'post' ),
				'meta_box'             => 'custom',
				'meta_box_cb'          => 'my_meta_box',
				'meta_box_sanitize_cb' => 'my_sanitize',
			)
		);
		$stored = acf_get_raw_taxonomy( $synced['ID'] );

		$this->assertSame( 'custom', $stored['meta_box'] );
		$this->assertSame( 'my_meta_box', $stored['meta_box_cb'] );
		$this->assertSame( 'my_sanitize', $stored['meta_box_sanitize_cb'] );
	}

	/**
	 * Test Local JSON sync keeps the post type callback from the file for a non super admin.
	 */
	public function test_post_type_sync_keeps_file_callback_for_non_super_admin() {
		$this->login_as_administrator( false );

		$synced = acf_skip_internal_post_sanitization(
			'acf_import_post_type',
			array(
				'key'                  => 'post_type_' . uniqid(),
				'title'                => 'Synced Post Type',
				'post_type'            => 'synced_cb_cpt',
				'register_meta_box_cb' => 'my_meta_boxes',
			)
		);
		$stored = acf_get_raw_post_type( $synced['ID'] );

		$this->assertSame( 'my_meta_boxes', $stored['register_meta_box_cb'] );
	}
}
