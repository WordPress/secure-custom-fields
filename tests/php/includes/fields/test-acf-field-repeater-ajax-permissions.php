<?php
/**
 * Tests for the Repeater field pagination AJAX permission checks.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Exercises acf_field_repeater::ajax_get_rows() for posts and options pages.
 *
 * @group fields
 * @group ajax
 */
class Test_ACF_Field_Repeater_Ajax_Permissions extends BaseTestCase {

	/**
	 * Repeater field key used to create the typed nonce.
	 *
	 * @var string
	 */
	private const FIELD_KEY = 'field_test_repeater_ajax_permissions';

	/**
	 * Repeater field name.
	 *
	 * @var string
	 */
	private const FIELD_NAME = 'test_repeater_ajax_permissions';

	/**
	 * Text sub field key.
	 *
	 * @var string
	 */
	private const SUB_FIELD_KEY = 'field_test_repeater_ajax_permissions_text';

	/**
	 * Options page menu slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'test-repeater-ajax-options';

	/**
	 * Custom options storage used by some tests.
	 *
	 * @var string
	 */
	private const CUSTOM_STORAGE = 'test_repeater_ajax_storage';

	/**
	 * Key of a second repeater whose sub field has the same name as the main repeater's.
	 *
	 * @var string
	 */
	private const OTHER_FIELD_KEY = 'field_test_repeater_ajax_permissions_other';

	/**
	 * Name of the second repeater.
	 *
	 * @var string
	 */
	private const OTHER_FIELD_NAME = 'test_repeater_ajax_permissions_other';

	/**
	 * Key of the second repeater's sub field.
	 *
	 * @var string
	 */
	private const OTHER_SUB_FIELD_KEY = 'field_test_repeater_ajax_permissions_other_text';

	/**
	 * Snapshot of the registered options pages, restored in tear_down().
	 *
	 * @var array
	 */
	private $pages_backup = array();

	/**
	 * User IDs created by the current test.
	 *
	 * @var int[]
	 */
	private $user_ids = array();

	/**
	 * Post IDs created by the current test.
	 *
	 * @var int[]
	 */
	private $post_ids = array();

	/**
	 * Set up the field, options page and request state.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->ensure_field_types();

		$this->pages_backup       = acf_options_page()->pages;
		acf_options_page()->pages = array();

		acf_add_local_field(
			array(
				'key'           => self::FIELD_KEY,
				'name'          => self::FIELD_NAME,
				'label'         => 'Repeater AJAX Permissions',
				'type'          => 'repeater',
				'pagination'    => 1,
				'rows_per_page' => 1,
				'sub_fields'    => array(
					array(
						'key'   => self::SUB_FIELD_KEY,
						'name'  => 'text',
						'label' => 'Text',
						'type'  => 'text',
					),
				),
			)
		);

		wp_set_current_user( 0 );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * Remove fixtures and request state.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( array( 'options', self::CUSTOM_STORAGE ) as $storage ) {
			foreach ( array( self::FIELD_NAME, self::OTHER_FIELD_NAME ) as $name ) {
				foreach ( array( '', '_0_text', '_1_text' ) as $suffix ) {
					delete_option( "{$storage}_{$name}{$suffix}" );
					delete_option( "_{$storage}_{$name}{$suffix}" );
				}
			}
		}

		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}

		acf_remove_local_field( self::SUB_FIELD_KEY );
		acf_remove_local_field( self::FIELD_KEY );
		acf_remove_local_field( self::OTHER_SUB_FIELD_KEY );
		acf_remove_local_field( self::OTHER_FIELD_KEY );
		acf_remove_local_field( 'field_test_repeater_ajax_group_inner' );
		acf_remove_local_field( 'field_test_repeater_ajax_group_inner_text' );
		acf_remove_local_field( 'field_test_repeater_ajax_group' );
		acf_remove_local_field( 'field_test_repeater_ajax_clone' );

		// The handler flags the shared field type instance as rendering for the rest of the request.
		acf_get_field_type( 'repeater' )->is_rendering = false;

		acf_options_page()->pages = $this->pages_backup;

		$this->user_ids = array();
		$this->post_ids = array();

		wp_set_current_user( 0 );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Registers the field types used by the tests if their hooks are missing.
	 *
	 * A field type first loaded during an earlier test keeps its instance, but its hooks are removed when
	 * that test's hooks are restored.
	 *
	 * @return void
	 */
	private function ensure_field_types() {
		$field_types = array(
			'text'     => array( 'acf_field_text', array( 'includes/fields/class-acf-field-text.php' ) ),
			'group'    => array( 'acf_field__group', array( 'includes/fields/class-acf-field-group.php' ) ),
			'clone'    => array( 'acf_field_clone', array( 'includes/fields/class-acf-field-clone.php' ) ),
			'repeater' => array(
				'acf_field_repeater',
				array( 'includes/fields/class-acf-repeater-table.php', 'includes/fields/class-acf-field-repeater.php' ),
			),
		);

		foreach ( $field_types as $type => list( $class, $files ) ) {
			if ( ! class_exists( $class ) ) {
				foreach ( $files as $file ) {
					acf_include( $file );
				}
			}

			if ( ! has_action( "acf/render_field/type={$type}" ) ) {
				acf_register_field_type( $class );
			}
		}
	}

	/**
	 * A user with the options page capability can load the next page of rows.
	 *
	 * @return void
	 */
	public function test_options_page_user_can_load_next_page() {
		$this->register_options_page( 'edit_posts' );
		$this->save_rows( 'options' );
		wp_set_current_user( $this->create_user( 'editor' ) );

		$response = $this->invoke_ajax_get_rows( 'options', self::PAGE_SLUG );

		$this->assertTrue( $response['success'], 'Options page pagination should succeed.' );
		$this->assertIsArray( $response['data']['rows'] );
		$this->assertStringContainsString( 'row two', implode( '', $response['data']['rows'] ) );
		$this->assertStringNotContainsString( 'row one', implode( '', $response['data']['rows'] ) );
	}

	/**
	 * A user without the options page capability is still rejected.
	 *
	 * @return void
	 */
	public function test_options_page_user_without_capability_is_rejected() {
		$this->register_options_page( 'manage_options' );
		$this->save_rows( 'options' );
		wp_set_current_user( $this->create_user( 'editor' ) );

		$response = $this->invoke_ajax_get_rows( 'options', self::PAGE_SLUG );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Sorry, you do not have permission to do that.', $response['data']['error'] );
		$this->assertStringNotContainsString( 'row two', wp_json_encode( $response ) );
	}

	/**
	 * An options request without an options page slug requires manage_options.
	 *
	 * @return void
	 */
	public function test_options_request_without_page_slug_requires_manage_options() {
		$this->register_options_page( 'edit_posts' );
		$this->save_rows( 'options' );
		wp_set_current_user( $this->create_user( 'editor' ) );

		$response = $this->invoke_ajax_get_rows( 'options' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Sorry, you do not have permission to do that.', $response['data']['error'] );
		$this->assertStringNotContainsString( 'row two', wp_json_encode( $response ) );
	}

	/**
	 * A user can load the next page of rows on their own profile.
	 *
	 * @return void
	 */
	public function test_user_can_load_next_page_on_own_profile() {
		$user_id = $this->create_user( 'subscriber' );
		$this->save_rows( 'user_' . $user_id );
		wp_set_current_user( $user_id );

		$response = $this->invoke_ajax_get_rows( 'user_' . $user_id );

		$this->assertTrue( $response['success'], 'Profile pagination should succeed for the profile owner.' );
		$this->assertStringContainsString( 'row two', implode( '', $response['data']['rows'] ) );
		$this->assertStringNotContainsString( 'row one', implode( '', $response['data']['rows'] ) );
	}

	/**
	 * A user who cannot edit another user's profile is rejected.
	 *
	 * @return void
	 */
	public function test_user_cannot_load_rows_on_another_profile() {
		$owner_id = $this->create_user( 'subscriber' );
		$this->save_rows( 'user_' . $owner_id );
		wp_set_current_user( $this->create_user( 'author' ) );

		$response = $this->invoke_ajax_get_rows( 'user_' . $owner_id );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Sorry, you do not have permission to do that.', $response['data']['error'] );
		$this->assertStringNotContainsString( 'row two', wp_json_encode( $response ) );
	}

	/**
	 * A page number below 1 loads the first page instead of an invalid row.
	 *
	 * @return void
	 */
	public function test_page_below_one_loads_first_page() {
		$user_id = $this->create_user( 'author' );
		$post_id = $this->create_post( $user_id );
		$this->save_rows( $post_id );
		wp_set_current_user( $user_id );

		$response = $this->invoke_ajax_get_rows( $post_id, '', array( 'paged' => 0 ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( array( 0 ), array_keys( $response['data']['rows'] ) );
		$this->assertStringContainsString( 'row one', implode( '', $response['data']['rows'] ) );
	}

	/**
	 * The request's field name must point to values saved by the requested field.
	 *
	 * Another repeater with a matching sub field name stores its rows in the same options storage, for
	 * example from an admin-only options page.
	 *
	 * @return void
	 */
	public function test_field_name_of_another_field_is_rejected() {
		acf_add_local_field(
			array(
				'key'        => self::OTHER_FIELD_KEY,
				'name'       => self::OTHER_FIELD_NAME,
				'label'      => 'Other Repeater',
				'type'       => 'repeater',
				'sub_fields' => array(
					array(
						'key'   => self::OTHER_SUB_FIELD_KEY,
						'name'  => 'text',
						'label' => 'Text',
						'type'  => 'text',
					),
				),
			)
		);
		update_field(
			self::OTHER_FIELD_KEY,
			array(
				array( self::OTHER_SUB_FIELD_KEY => 'other one' ),
				array( self::OTHER_SUB_FIELD_KEY => 'other two' ),
			),
			'options'
		);

		$this->register_options_page( 'edit_posts' );
		$this->save_rows( 'options' );
		wp_set_current_user( $this->create_user( 'editor' ) );

		$response = $this->invoke_ajax_get_rows( 'options', self::PAGE_SLUG, array( 'field_name' => self::OTHER_FIELD_NAME ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Sorry, you do not have permission to do that.', $response['data']['error'] );
		$this->assertStringNotContainsString( 'other two', wp_json_encode( $response ) );
	}

	/**
	 * Rows saved without a reference key, such as imported data, still load.
	 *
	 * @return void
	 */
	public function test_rows_without_reference_key_still_load() {
		$user_id = $this->create_user( 'author' );
		$post_id = $this->create_post( $user_id );
		update_post_meta( $post_id, self::FIELD_NAME, 2 );
		update_post_meta( $post_id, self::FIELD_NAME . '_0_text', 'row one' );
		update_post_meta( $post_id, self::FIELD_NAME . '_1_text', 'row two' );
		wp_set_current_user( $user_id );

		$response = $this->invoke_ajax_get_rows( $post_id );

		$this->assertTrue( $response['success'], 'Rows without a reference key should still load.' );
		$this->assertStringContainsString( 'row two', implode( '', $response['data']['rows'] ) );
	}

	/**
	 * An options page with custom storage can load the next page of rows.
	 *
	 * @return void
	 */
	public function test_options_page_with_custom_storage_can_load_next_page() {
		$this->register_options_page( 'edit_posts', self::CUSTOM_STORAGE );
		$this->save_rows( self::CUSTOM_STORAGE );
		wp_set_current_user( $this->create_user( 'editor' ) );

		$response = $this->invoke_ajax_get_rows( self::CUSTOM_STORAGE, self::PAGE_SLUG );

		$this->assertTrue( $response['success'], 'Custom storage pagination should succeed.' );
		$this->assertStringContainsString( 'row two', implode( '', $response['data']['rows'] ) );
	}

	/**
	 * An options page slug cannot unlock storage that belongs to another page.
	 *
	 * @return void
	 */
	public function test_options_page_slug_for_other_storage_is_rejected() {
		$this->register_options_page( 'edit_posts', self::CUSTOM_STORAGE );
		$this->save_rows( 'options' );
		wp_set_current_user( $this->create_user( 'editor' ) );

		$response = $this->invoke_ajax_get_rows( 'options', self::PAGE_SLUG );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Sorry, you do not have permission to do that.', $response['data']['error'] );
		$this->assertStringNotContainsString( 'row two', wp_json_encode( $response ) );
	}

	/**
	 * A subscriber cannot load rows from an options page that requires edit_posts.
	 *
	 * @return void
	 */
	public function test_subscriber_cannot_load_options_page_rows() {
		$this->register_options_page( 'edit_posts' );
		$this->save_rows( 'options' );
		wp_set_current_user( $this->create_user( 'subscriber' ) );

		$response = $this->invoke_ajax_get_rows( 'options', self::PAGE_SLUG );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Sorry, you do not have permission to do that.', $response['data']['error'] );
		$this->assertStringNotContainsString( 'row two', wp_json_encode( $response ) );
	}

	/**
	 * A request with an invalid nonce is rejected.
	 *
	 * @return void
	 */
	public function test_invalid_nonce_is_rejected() {
		$this->register_options_page( 'edit_posts' );
		$this->save_rows( 'options' );
		wp_set_current_user( $this->create_user( 'editor' ) );

		$response = $this->invoke_ajax_get_rows( 'options', self::PAGE_SLUG, array( 'nonce' => 'invalid' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid nonce.', $response['data']['error'] );
		$this->assertStringNotContainsString( 'row two', wp_json_encode( $response ) );
	}

	/**
	 * A repeater inside a group loads rows under its full meta name.
	 *
	 * @return void
	 */
	public function test_repeater_inside_group_can_load_next_page() {
		$inner_key     = 'field_test_repeater_ajax_group_inner';
		$inner_sub_key = 'field_test_repeater_ajax_group_inner_text';

		acf_add_local_field(
			array(
				'key'        => 'field_test_repeater_ajax_group',
				'name'       => 'test_repeater_ajax_group',
				'label'      => 'Group',
				'type'       => 'group',
				'sub_fields' => array(
					array(
						'key'           => $inner_key,
						'name'          => 'inner',
						'label'         => 'Inner',
						'type'          => 'repeater',
						'pagination'    => 1,
						'rows_per_page' => 1,
						'sub_fields'    => array(
							array(
								'key'   => $inner_sub_key,
								'name'  => 'text',
								'label' => 'Text',
								'type'  => 'text',
							),
						),
					),
				),
			)
		);

		$user_id = $this->create_user( 'author' );
		$post_id = $this->create_post( $user_id );
		update_field(
			'field_test_repeater_ajax_group',
			array(
				$inner_key => array(
					array( $inner_sub_key => 'row one' ),
					array( $inner_sub_key => 'row two' ),
				),
			),
			$post_id
		);
		wp_set_current_user( $user_id );

		$response = $this->invoke_ajax_get_rows(
			$post_id,
			'',
			array(
				'field_key'  => $inner_key,
				'field_name' => 'test_repeater_ajax_group_inner',
				'nonce'      => wp_create_nonce( 'acf_field_repeater_' . $inner_key ),
			)
		);

		$this->assertTrue( $response['success'], 'Nested repeater pagination should succeed.' );
		$this->assertStringContainsString( 'row two', implode( '', $response['data']['rows'] ) );
	}

	/**
	 * A cloned repeater loads rows saved through the clone field.
	 *
	 * @return void
	 */
	public function test_cloned_repeater_can_load_next_page() {
		acf_add_local_field(
			array(
				'key'          => 'field_test_repeater_ajax_clone',
				'name'         => 'test_repeater_ajax_clone',
				'label'        => 'Clone',
				'type'         => 'clone',
				'clone'        => array( self::FIELD_KEY ),
				'display'      => 'seamless',
				'prefix_name'  => 1,
				'prefix_label' => 0,
			)
		);

		$user_id = $this->create_user( 'author' );
		$post_id = $this->create_post( $user_id );
		update_field(
			'field_test_repeater_ajax_clone',
			array(
				'test_repeater_ajax_clone_' . self::FIELD_NAME => array(
					array( self::SUB_FIELD_KEY => 'row one' ),
					array( self::SUB_FIELD_KEY => 'row two' ),
				),
			),
			$post_id
		);
		wp_set_current_user( $user_id );

		$response = $this->invoke_ajax_get_rows(
			$post_id,
			'',
			array( 'field_name' => 'test_repeater_ajax_clone_' . self::FIELD_NAME )
		);

		$this->assertTrue( $response['success'], 'Cloned repeater pagination should succeed.' );
		$this->assertStringContainsString( 'row two', implode( '', $response['data']['rows'] ) );
	}

	/**
	 * A user who can edit the post can load the next page of rows.
	 *
	 * @return void
	 */
	public function test_post_editor_can_load_next_page() {
		$user_id = $this->create_user( 'author' );
		$post_id = $this->create_post( $user_id );
		$this->save_rows( $post_id );
		wp_set_current_user( $user_id );

		$response = $this->invoke_ajax_get_rows( $post_id );

		$this->assertTrue( $response['success'], 'Post pagination should succeed for the post author.' );
		$this->assertStringContainsString( 'row two', implode( '', $response['data']['rows'] ) );
		$this->assertStringNotContainsString( 'row one', implode( '', $response['data']['rows'] ) );
	}

	/**
	 * A user who cannot edit the post is rejected.
	 *
	 * @return void
	 */
	public function test_user_who_cannot_edit_post_is_rejected() {
		$post_id = $this->create_post( $this->create_user( 'administrator' ) );
		$this->save_rows( $post_id );
		wp_set_current_user( $this->create_user( 'author' ) );

		$response = $this->invoke_ajax_get_rows( $post_id );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Sorry, you do not have permission to do that.', $response['data']['error'] );
		$this->assertStringNotContainsString( 'row two', wp_json_encode( $response ) );
	}

	/**
	 * Registers the test options page with the default options storage.
	 *
	 * @param string $capability The capability required to edit the page.
	 * @param string $post_id    Optional. The page's storage. Default 'options'.
	 * @return void
	 */
	private function register_options_page( $capability, $post_id = 'options' ) {
		acf_add_options_page(
			array(
				'page_title' => 'Repeater AJAX Options',
				'menu_slug'  => self::PAGE_SLUG,
				'capability' => $capability,
				'post_id'    => $post_id,
			)
		);
	}

	/**
	 * Saves two repeater rows for the given ACF post ID.
	 *
	 * @param int|string $post_id The ACF post ID.
	 * @return void
	 */
	private function save_rows( $post_id ) {
		update_field(
			self::FIELD_KEY,
			array(
				array( self::SUB_FIELD_KEY => 'row one' ),
				array( self::SUB_FIELD_KEY => 'row two' ),
			),
			$post_id
		);
	}

	/**
	 * Creates a user with the given role.
	 *
	 * @param string $role The role.
	 * @return int
	 */
	private function create_user( $role ) {
		$login   = 'repeater_ajax_' . $role . '_' . count( $this->user_ids );
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user_id );

		$this->user_ids[] = $user_id;

		return $user_id;
	}

	/**
	 * Creates a published post owned by the given user.
	 *
	 * @param int $author_id The post author.
	 * @return int
	 */
	private function create_post( $author_id ) {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Repeater AJAX Post',
				'post_status' => 'publish',
				'post_author' => $author_id,
			)
		);
		$this->assertIsInt( $post_id );

		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * Calls the handler and returns the decoded JSON response.
	 *
	 * @param int|string $post_id           The ACF post ID.
	 * @param string     $options_page_slug Optional. The options page slug.
	 * @param array      $overrides         Optional. Request values to override.
	 * @return array
	 *
	 * @throws \RuntimeException Re-thrown when an unrelated runtime exception
	 *                           bubbles out of the handler.
	 */
	private function invoke_ajax_get_rows( $post_id, $options_page_slug = '', $overrides = array() ) {
		$request = array(
			'action'            => 'acf/ajax/query_repeater',
			'paged'             => 2,
			'field_key'         => self::FIELD_KEY,
			'field_name'        => self::FIELD_NAME,
			'rows_per_page'     => 1,
			'refresh'           => false,
			'nonce'             => wp_create_nonce( 'acf_field_repeater_' . self::FIELD_KEY ),
			'post_id'           => $post_id,
			'options_page_slug' => $options_page_slug,
		);
		$request = array_merge( $request, $overrides );

		$_POST    = $request;
		$_REQUEST = $request;

		$halt_marker  = 'acf_repeater_ajax_halt';
		$force_ajax   = static function () {
			return true;
		};
		$halt_handler = static function () use ( $halt_marker ) {
			return static function () use ( $halt_marker ) {
				throw new \RuntimeException( esc_html( $halt_marker ) );
			};
		};

		add_filter( 'wp_doing_ajax', $force_ajax );
		add_filter( 'wp_die_ajax_handler', $halt_handler, 100 );
		add_filter( 'wp_die_json_handler', $halt_handler, 100 );

		$output    = '';
		$unrelated = null;

		/*
		 * The repeater only pages rows while doing_action() reports the AJAX hook. Mark it as running
		 * instead of calling do_action(), because the halt exception would leave that hook mid-run and
		 * break later tests.
		 */
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_ajax_acf/ajax/query_repeater'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Popped in the finally block.

		ob_start();
		try {
			acf_get_field_type( 'repeater' )->ajax_get_rows();
		} catch ( \RuntimeException $halt ) {
			if ( $halt->getMessage() !== $halt_marker ) {
				$unrelated = $halt;
			}
		} finally {
			array_pop( $wp_current_filter );
			$output = ob_get_clean();
			remove_filter( 'wp_die_json_handler', $halt_handler, 100 );
			remove_filter( 'wp_die_ajax_handler', $halt_handler, 100 );
			remove_filter( 'wp_doing_ajax', $force_ajax );
		}

		if ( null !== $unrelated ) {
			throw $unrelated;
		}

		$response = json_decode( $output, true );
		$this->assertIsArray( $response, 'The handler should return JSON. Output: ' . $output );

		return $response;
	}
}
