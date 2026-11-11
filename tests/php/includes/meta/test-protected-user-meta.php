<?php
/**
 * Tests that SCF never reads or writes protected user meta keys.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;
use WorDBless\UserMeta;

/**
 * Class Test_Protected_User_Meta
 *
 * @group meta
 * @group security
 *
 * @covers ::scf_is_protected_user_meta_key
 * @covers \SCF\Meta\User
 */
class Test_Protected_User_Meta extends BaseTestCase {

	/**
	 * Test user ID.
	 *
	 * @var integer
	 */
	private $user_id;

	/**
	 * Sets up a user and the user meta backend.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->user_id = wp_insert_user(
			array(
				'user_login' => 'protected_meta_user_' . uniqid(),
				'user_pass'  => 'password',
				'user_email' => 'protected_meta_' . uniqid() . '@example.com',
			)
		);
		$this->assertIsInt( $this->user_id );

		UserMeta::init();
	}

	/**
	 * Returns a text field array stored under the given name.
	 *
	 * @param string $name The meta/field name.
	 * @return array
	 */
	private function field( string $name ): array {
		return array(
			'key'  => 'field_' . md5( $name ),
			'name' => $name,
			'type' => 'text',
		);
	}

	/**
	 * Keys WordPress uses for authorization or sign-in.
	 *
	 * @return array<int, array{0:string}>
	 */
	public function protected_key_provider(): array {
		return array(
			array( 'wp_capabilities' ),
			array( 'wp_user_level' ),
			array( 'wp_2_capabilities' ),
			array( 'wp_12_user_level' ),
			array( 'session_tokens' ),
			array( '_application_passwords' ),
			array( 'wp_capabilit\\ies' ),
			array( 'session\\_tokens' ),
			array( 'wp_wp_3_capabilities' ),
			array( 'wp_3e0_capabilities' ),
			array( 'wp_ 3_capabilities' ),
		);
	}

	/**
	 * Ordinary field names SCF must keep handling.
	 *
	 * Names spelled differently from a protected key are different keys when read. Writes that the
	 * database would match to a protected row are refused separately.
	 *
	 * @return array<int, array{0:string}>
	 */
	public function allowed_key_provider(): array {
		return array(
			array( 'capabilities' ),
			array( 'application_passwords' ),
			array( '_wp_capabilities' ),
			array( 'my_capabilities' ),
			array( 'wp_capabilities_custom' ),
			array( 'wp_x_capabilities' ),
			array( 'wp__capabilities' ),
			array( 'user_level_note' ),
			array( 'level_2' ),
			array( 'team_capabilities' ),
			array( 'course_user_level' ),
			array( 'map_location' ),
			array( 'first_name' ),
			array( 'WP_Capabilities' ),
			array( 'wp_capabilities ' ),
			array( 'tel' . "\u{00E9}" . 'fono' ),
			array( "\u{96FB}\u{8A71}\u{756A}\u{53F7}" ),
		);
	}

	/**
	 * Keys WordPress uses for authorization or sign-in are flagged as protected.
	 *
	 * @dataProvider protected_key_provider
	 *
	 * @param string $key Protected meta key.
	 */
	public function test_matcher_flags_protected_keys( string $key ): void {
		$this->assertTrue( scf_is_protected_user_meta_key( $key ), "{$key} should be protected." );
	}

	/**
	 * Ordinary field names are not treated as protected.
	 *
	 * @dataProvider allowed_key_provider
	 *
	 * @param string $key Ordinary field name.
	 */
	public function test_matcher_allows_ordinary_keys( string $key ): void {
		$this->assertFalse( scf_is_protected_user_meta_key( $key ), "{$key} should not be protected." );
	}

	/**
	 * The user meta writer refuses to store a protected key, but writes an ordinary one.
	 */
	public function test_update_value_refuses_protected_key(): void {
		update_user_meta( $this->user_id, 'wp_capabilities', array( 'subscriber' => true ) );

		$instance = acf_get_meta_instance( 'user' );
		$this->assertInstanceOf( 'SCF\Meta\User', $instance );

		$result = $instance->update_value( $this->user_id, $this->field( 'wp_capabilities' ), 'changed' );

		$this->assertFalse( $result, 'The protected write must be refused.' );
		$this->assertSame(
			array( 'subscriber' => true ),
			get_user_meta( $this->user_id, 'wp_capabilities', true ),
			'The role metadata must be untouched.'
		);

		$allowed = $instance->update_value( $this->user_id, $this->field( 'map_location' ), 'Berlin' );
		$this->assertNotFalse( $allowed );
		$this->assertSame( 'Berlin', get_user_meta( $this->user_id, 'map_location', true ) );
	}

	/**
	 * The user meta writer refuses a reference stored under a protected key.
	 */
	public function test_update_reference_refuses_protected_key(): void {
		global $wpdb;
		$base_prefix       = $wpdb->base_prefix;
		$wpdb->base_prefix = '_wp_';

		$instance = acf_get_meta_instance( 'user' );
		$this->assertFalse( $instance->update_reference( $this->user_id, 'application_passwords', 'field_x' ) );
		$this->assertFalse( $instance->update_reference( $this->user_id, 'wp_user_level', 'field_x' ) );
		$this->assertSame( '', get_user_meta( $this->user_id, '_wp_user_level', true ) );

		$wpdb->base_prefix = $base_prefix;
	}

	/**
	 * Reading a protected key through the user meta instance returns null.
	 */
	public function test_get_value_hides_protected_key(): void {
		update_user_meta( $this->user_id, 'wp_capabilities', array( 'subscriber' => true ) );

		$instance = acf_get_meta_instance( 'user' );

		$this->assertNull( $instance->get_value( $this->user_id, $this->field( 'wp_capabilities' ) ) );
	}

	/**
	 * The legacy metadata helpers refuse protected user keys and keep ordinary ones.
	 */
	public function test_legacy_metadata_helpers_refuse_protected_keys(): void {
		update_user_meta( $this->user_id, 'wp_capabilities', array( 'subscriber' => true ) );
		$post_id = 'user_' . $this->user_id;

		$this->assertNull( acf_get_metadata( $post_id, 'wp_capabilities' ) );
		$this->assertFalse( acf_update_metadata( $post_id, 'wp_capabilities', 'changed' ) );
		$this->assertFalse( acf_delete_metadata( $post_id, 'wp_capabilities' ) );
		$this->assertSame( array( 'subscriber' => true ), get_user_meta( $this->user_id, 'wp_capabilities', true ) );

		$this->assertNotFalse( acf_update_metadata( $post_id, 'map_location', 'Berlin' ) );
		$this->assertSame( 'Berlin', acf_get_metadata( $post_id, 'map_location' ) );
	}

	/**
	 * Writes and deletes refuse keys the database matches to a protected row, however they are spelled.
	 */
	public function test_writes_check_the_rows_they_would_change(): void {
		global $wpdb;
		$original = $wpdb;
		$wpdb     = new class( $original ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub.
			/**
			 * Keys the row lookup returns.
			 *
			 * @var array
			 */
			public $rows = array();

			/**
			 * The wrapped database.
			 *
			 * @var object
			 */
			private $db;

			/**
			 * Wraps the test database.
			 *
			 * @param object $db The test database.
			 */
			public function __construct( $db ) {
				$this->db = $db;
			}

			/**
			 * Reads properties of the wrapped database.
			 *
			 * @param string $name Property name.
			 * @return mixed
			 */
			public function __get( $name ) {
				return $this->db->$name;
			}

			/**
			 * Checks properties of the wrapped database.
			 *
			 * @param string $name Property name.
			 * @return boolean
			 */
			public function __isset( $name ) {
				return isset( $this->db->$name );
			}

			/**
			 * Keeps the query as is.
			 *
			 * @param string $query Query.
			 * @return string
			 */
			public function prepare( $query ) {
				return $query;
			}

			/**
			 * Returns the row lookup result.
			 *
			 * @return array
			 */
			public function get_col() {
				return $this->rows;
			}
		};

		try {
			$wpdb->rows = array( 'wp_capabilities' );
			$this->assertTrue( scf_is_protected_user_meta_key( 'WP_Capabilities', 7 ) );
			$this->assertFalse( scf_is_protected_user_meta_key( 'WP_Capabilities' ), 'Reads compare keys exactly.' );

			$wpdb->rows = array( 'session_tokens' );
			$this->assertTrue( scf_is_protected_user_meta_key( 'se' . "\u{00DF}" . 'ion_tokens', 7 ) );

			$wpdb->rows = array( 'tel' . "\u{00E9}" . 'fono' );
			$this->assertFalse( scf_is_protected_user_meta_key( 'tel' . "\u{00E9}" . 'fono', 7 ) );

			$wpdb->rows = array();
			$this->assertFalse( scf_is_protected_user_meta_key( 'Team_Capabilities', 7 ) );
		} finally {
			$wpdb = $original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub.
		}
	}

	/**
	 * Protected keys are only guarded for users, not for posts.
	 */
	public function test_post_meta_named_like_capabilities_is_not_blocked(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Protected meta post',
				'post_status' => 'publish',
			)
		);

		$this->assertNotFalse( acf_update_metadata( $post_id, 'wp_capabilities', 'ordinary post value' ) );
		$this->assertSame( 'ordinary post value', get_post_meta( $post_id, 'wp_capabilities', true ) );
	}
}
