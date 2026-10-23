<?php
/**
 * Tests that front-end field output respects password-protected posts.
 *
 * @package wordpress/secure-custom-fields
 */

use SCF\AI\GEO\Outputs\Posts;
use WorDBless\BaseTestCase;

/**
 * Tests the shortcode and JSON-LD output for protected content.
 *
 * @group security
 */
class Test_Protected_Content_Output extends BaseTestCase {

	/**
	 * A published post.
	 *
	 * @var int
	 */
	private $public_id;

	/**
	 * A password-protected post.
	 *
	 * @var int
	 */
	private $protected_id;

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

		if ( ! class_exists( 'acf_field_user' ) ) {
			acf_include( 'includes/fields/class-acf-field-user.php' );
		}
		if ( ! has_filter( 'acf/format_value/type=user' ) ) {
			acf_register_field_type( 'acf_field_user' );
		}

		$this->public_id    = wp_insert_post(
			array(
				'post_title'  => 'Public post',
				'post_status' => 'publish',
			)
		);
		$this->protected_id = wp_insert_post(
			array(
				'post_title'    => 'Protected post',
				'post_status'   => 'publish',
				'post_password' => 'output-secret',
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_protected_output',
				'title'    => 'Protected Output',
				'fields'   => array(
					array(
						'key'   => 'field_protected_output_text',
						'name'  => 'protected_output_text',
						'label' => 'Text',
						'type'  => 'text',
					),
					array(
						'key'           => 'field_protected_output_user',
						'name'          => 'protected_output_user',
						'label'         => 'User',
						'type'          => 'user',
						'return_format' => 'array',
						'multiple'      => 0,
						'role'          => array(),
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
		update_field( 'protected_output_text', 'Locked value', $this->protected_id );
		acf_get_store( 'values' )->reset();

		$this->original_shortcode_setting = acf_get_setting( 'enable_shortcode' );
		acf_update_setting( 'enable_shortcode', true );

		wp_set_current_user( 0 );
	}

	/**
	 * Remove test data.
	 */
	public function tear_down() {
		acf_update_setting( 'enable_shortcode', $this->original_shortcode_setting );
		acf_remove_local_field_group( 'group_protected_output' );
		acf_remove_local_field( 'field_protected_output_text' );
		acf_remove_local_field( 'field_protected_output_user' );
		acf_get_store( 'values' )->reset();
		unset( $GLOBALS['post'] );
		$GLOBALS['wp_query'] = $GLOBALS['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		parent::tear_down();
	}

	/**
	 * The shortcode does not show fields of a password-protected post until the password is entered.
	 */
	public function test_shortcode_hides_password_protected_post_fields() {
		$GLOBALS['post'] = get_post( $this->public_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$atts            = array(
			'field'   => 'protected_output_text',
			'post_id' => $this->protected_id,
		);

		$this->assertEmpty( acf_shortcode( $atts ) );

		add_filter( 'post_password_required', '__return_false' );
		$unlocked = acf_shortcode( $atts );
		remove_filter( 'post_password_required', '__return_false' );

		$this->assertSame( 'Locked value', $unlocked );
	}

	/**
	 * The shortcode outputs User fields as IDs rather than account details.
	 */
	public function test_shortcode_reduces_user_fields_to_ids() {
		$user_login = uniqid( 'shortcode_user_', false );
		$user_email = "{$user_login}@example.invalid";
		$user_id    = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_pass'  => 'password',
				'user_email' => $user_email,
			)
		);
		update_field( 'protected_output_user', $user_id, $this->public_id );
		acf_get_store( 'values' )->reset();

		// The test datastore has no users table for WP_User_Query to read.
		$shim_user_query = static function ( $results, $query ) {
			$user_ids           = array_map( 'intval', (array) ( $query->query_vars['include'] ?? array() ) );
			$query->total_users = count( $user_ids );
			return $user_ids;
		};
		add_filter( 'users_pre_query', $shim_user_query, 10, 2 );

		$GLOBALS['post'] = get_post( $this->public_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$output          = acf_shortcode( array( 'field' => 'protected_output_user' ) );

		remove_filter( 'users_pre_query', $shim_user_query, 10 );

		$this->assertStringNotContainsString( $user_email, (string) $output );
		$this->assertStringNotContainsString( $user_login, (string) $output );
		$this->assertSame( (string) $user_id, (string) $output );
	}

	/**
	 * The shortcode does not show fields of the current post while it is locked.
	 */
	public function test_shortcode_hides_current_password_protected_post_fields() {
		$GLOBALS['post'] = get_post( $this->protected_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertEmpty( acf_shortcode( array( 'field' => 'protected_output_text' ) ) );
	}

	/**
	 * JSON-LD for a password-protected post does not include its field values.
	 */
	public function test_post_jsonld_hides_password_protected_post_fields() {
		$this->assertStringNotContainsString( 'Locked value', $this->render_post_jsonld( $this->protected_id ) );

		add_filter( 'post_password_required', '__return_false' );
		$unlocked = $this->render_post_jsonld( $this->protected_id );
		remove_filter( 'post_password_required', '__return_false' );

		$this->assertStringContainsString( 'Locked value', $unlocked );
	}

	/**
	 * Renders the JSON-LD a singular post view would print.
	 *
	 * @param int $post_id The post ID.
	 * @return string
	 */
	private function render_post_jsonld( $post_id ) {
		$GLOBALS['post']                  = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_query']              = new WP_Query(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_query']->is_singular = true;
		$GLOBALS['wp_query']->is_single   = true;

		$enable = static function () {
			return array( 'post' );
		};
		$fields = static function () {
			return array(
				'protected_output_text' => array(
					'type'            => 'text',
					'name'            => 'protected_output_text',
					'value'           => get_field( 'protected_output_text', false, false ),
					'schema_property' => 'description',
				),
			);
		};
		add_filter( 'acf/schema/enabled_post_types', $enable );
		add_filter( 'acf/schema/post_field_objects', $fields );

		ob_start();
		( new Posts() )->output_jsonld_data();
		$output = ob_get_clean();

		remove_filter( 'acf/schema/enabled_post_types', $enable );
		remove_filter( 'acf/schema/post_field_objects', $fields );

		return $output;
	}
}
