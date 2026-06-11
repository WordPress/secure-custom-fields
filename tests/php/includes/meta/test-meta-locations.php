<?php
/**
 * Tests for the SCF meta location classes and the meta routing layer.
 *
 * Covers includes/Meta/MetaLocation.php, Post.php, User.php, Term.php,
 * Comment.php and Option.php, both through the class API and through the
 * acf_*_metadata() routing functions with typed post IDs.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;
use WorDBless\Metadata;
use WorDBless\PostMeta;
use WorDBless\UserMeta;

require_once __DIR__ . '/class-scf-test-custom-meta-location.php';

/**
 * Test the SCF meta location classes.
 */
class Test_Meta_Locations extends BaseTestCase {

	/**
	 * Test post ID.
	 *
	 * @var integer
	 */
	private $post_id;

	/**
	 * Test user ID.
	 *
	 * @var integer
	 */
	private $user_id;

	/**
	 * Test term ID.
	 *
	 * @var integer
	 */
	private $term_id;

	/**
	 * Test comment ID.
	 *
	 * @var integer
	 */
	private $comment_id;

	/**
	 * WorDBless meta mock for term meta.
	 *
	 * @var Metadata
	 */
	private $term_meta_mock;

	/**
	 * WorDBless meta mock for comment meta.
	 *
	 * @var Metadata
	 */
	private $comment_meta_mock;

	/**
	 * SQL queries captured from the WorDBless wpdb mock.
	 *
	 * @var array
	 */
	private $captured_queries = array();

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Meta Location Test Post',
				'post_status' => 'publish',
			)
		);

		$this->user_id = wp_insert_user(
			array(
				'user_login' => 'meta_test_user_' . uniqid(),
				'user_pass'  => 'password',
				'user_email' => 'meta_test_' . uniqid() . '@example.com',
			)
		);

		$term = wp_insert_term( 'Meta Test Term ' . uniqid(), 'category' );
		$this->assertIsArray( $term );
		$this->term_id = $term['term_id'];

		$this->comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_content' => 'Meta test comment',
			)
		);

		// WorDBless only mocks post and user meta out of the box. Register
		// in-memory mocks for term and comment meta so those backends work.
		// The filters they add are removed automatically by BaseTestCase.
		$this->term_meta_mock    = new Metadata( 'term' );
		$this->comment_meta_mock = new Metadata( 'comment' );

		// WorDBless does not support fetching all meta for an object
		// (i.e. get_metadata( $type, $id ) with no key), which the
		// MetaLocation::get_meta() method relies on. Add shims for that.
		$this->register_all_meta_shim( 'post', PostMeta::init() );
		$this->register_all_meta_shim( 'user', UserMeta::init() );
		$this->register_all_meta_shim( 'term', $this->term_meta_mock );
		$this->register_all_meta_shim( 'comment', $this->comment_meta_mock );

		acf_get_store( 'values' )->reset();
		$this->captured_queries = array();
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		// Restore the autoload setting in case a test changed it.
		acf_update_setting( 'autoload', false );

		// Remove the custom test location registration if a test added it.
		$store = acf_get_store( 'acf-meta-locations' );
		if ( $store && $store->has( 'scf_test_location' ) ) {
			$store->remove( 'scf_test_location' );
		}
		unset( $GLOBALS['acf_instances']['SCF_Test_Custom_Meta_Location'] );

		acf_get_store( 'values' )->reset();
		parent::tearDown();
	}

	/**
	 * Registers a filter that emulates get_metadata( $type, $id ) with no
	 * meta key, returning all meta for the object from the WorDBless mock.
	 *
	 * @param string   $type The meta type.
	 * @param Metadata $mock The WorDBless metadata mock holding the values.
	 */
	private function register_all_meta_shim( string $type, Metadata $mock ) {
		add_filter(
			"get_{$type}_metadata",
			function ( $check, $object_id, $meta_key ) use ( $mock ) {
				if ( '' !== $meta_key ) {
					return $check;
				}

				$all = array();
				if ( isset( $mock->meta[ $object_id ] ) ) {
					foreach ( $mock->meta[ $object_id ] as $row ) {
						$all[ $row['meta_key'] ][] = $row['meta_value'];
					}
				}
				return $all;
			},
			20,
			3
		);
	}

	/**
	 * Pre-creates meta keys with placeholder values.
	 *
	 * WorDBless routes update_metadata() for a nonexistent key through
	 * add_metadata(), which unslashes the (already unslashed) value a second
	 * time. Seeding the keys first exercises the genuine update path, which
	 * matches real database slashing semantics.
	 *
	 * @param string         $type      The meta type.
	 * @param integer|string $object_id The object ID.
	 * @param array          $keys      The meta keys to seed.
	 */
	private function seed_meta_keys( string $type, $object_id, array $keys ) {
		foreach ( $keys as $key ) {
			add_metadata( $type, $object_id, $key, 'placeholder' );
		}
	}

	/**
	 * Builds a simple text field array.
	 *
	 * @param string $name The field name.
	 * @return array
	 */
	private function get_field( string $name ): array {
		return array(
			'key'  => 'field_' . $name,
			'name' => $name,
			'type' => 'text',
		);
	}

	// =========================================================================
	// Registration / instance retrieval.
	// =========================================================================

	/**
	 * Test that all core meta locations are registered in the store.
	 */
	public function test_meta_locations_registered_in_store() {
		$store = acf_get_store( 'acf-meta-locations' );
		$this->assertInstanceOf( 'ACF_Data', $store );

		$expected = array(
			'post'    => 'SCF\Meta\Post',
			'user'    => 'SCF\Meta\User',
			'term'    => 'SCF\Meta\Term',
			'comment' => 'SCF\Meta\Comment',
			'option'  => 'SCF\Meta\Option',
		);

		foreach ( $expected as $type => $class_name ) {
			$this->assertSame( $class_name, $store->get( $type ), "Location type {$type} should map to {$class_name}" );
			$this->assertInstanceOf( $class_name, acf_get_meta_instance( $type ) );
		}
	}

	/**
	 * Test that acf_get_meta_instance() returns the same instance on repeated calls.
	 */
	public function test_meta_instance_is_reused() {
		$first  = acf_get_meta_instance( 'post' );
		$second = acf_get_meta_instance( 'post' );

		$this->assertSame( $first, $second );
	}

	/**
	 * Test that acf_get_meta_instance() returns null for unknown types.
	 */
	public function test_meta_instance_unknown_type_returns_null() {
		$this->assertNull( acf_get_meta_instance( 'nonexistent_type' ) );
	}

	/**
	 * Test that the base MetaLocation class does not register without a type.
	 */
	public function test_base_location_without_type_does_not_register() {
		new \SCF\Meta\MetaLocation();

		$store = acf_get_store( 'acf-meta-locations' );
		$this->assertFalse( $store->has( '' ) );
	}

	/**
	 * Test that a custom meta location registers itself on construction.
	 */
	public function test_custom_location_registers_itself() {
		new SCF_Test_Custom_Meta_Location();

		$store = acf_get_store( 'acf-meta-locations' );
		$this->assertTrue( $store->has( 'scf_test_location' ) );
		$this->assertSame( 'SCF_Test_Custom_Meta_Location', $store->get( 'scf_test_location' ) );

		$instance = acf_get_meta_instance( 'scf_test_location' );
		$this->assertInstanceOf( 'SCF_Test_Custom_Meta_Location', $instance );
		$this->assertSame( '_ref_', $instance->reference_prefix );
	}

	// =========================================================================
	// Post location (class API).
	// =========================================================================

	/**
	 * Test value CRUD through the Post location class.
	 */
	public function test_post_location_value_crud() {
		$instance = acf_get_meta_instance( 'post' );
		$field    = $this->get_field( 'post_text' );

		// Create.
		$result = $instance->update_value( $this->post_id, $field, 'hello' );
		$this->assertNotFalse( $result );
		$this->assertSame( 'hello', $instance->get_value( $this->post_id, $field ) );
		$this->assertSame( 'hello', get_post_meta( $this->post_id, 'post_text', true ) );

		// Update.
		$instance->update_value( $this->post_id, $field, 'updated' );
		$this->assertSame( 'updated', $instance->get_value( $this->post_id, $field ) );

		// Delete.
		$this->assertTrue( $instance->delete_value( $this->post_id, $field ) );
		$this->assertNull( $instance->get_value( $this->post_id, $field ) );
	}

	/**
	 * Test reference key CRUD through the Post location class.
	 */
	public function test_post_location_reference_crud() {
		$instance = acf_get_meta_instance( 'post' );

		$result = $instance->update_reference( $this->post_id, 'post_text', 'field_post_text' );
		$this->assertNotFalse( $result );

		// The reference is stored as hidden meta with an underscore prefix.
		$this->assertSame( 'field_post_text', get_post_meta( $this->post_id, '_post_text', true ) );
		$this->assertSame( 'field_post_text', $instance->get_reference( $this->post_id, 'post_text' ) );

		$this->assertTrue( $instance->delete_reference( $this->post_id, 'post_text' ) );
		$this->assertNull( $instance->get_reference( $this->post_id, 'post_text' ) );
	}

	/**
	 * Test that get_meta() only returns values paired with a reference key.
	 */
	public function test_post_location_get_meta_returns_paired_values() {
		$instance = acf_get_meta_instance( 'post' );

		// Field with a reference.
		$instance->update_value( $this->post_id, $this->get_field( 'paired' ), 'paired_value' );
		$instance->update_reference( $this->post_id, 'paired', 'field_paired' );

		// Serialized array value with a reference.
		$array_value = array( 'one', 'two' );
		$instance->update_value( $this->post_id, $this->get_field( 'paired_array' ), $array_value );
		$instance->update_reference( $this->post_id, 'paired_array', 'field_paired_array' );

		// Meta without a reference must be excluded.
		update_post_meta( $this->post_id, 'orphan_meta', 'orphan_value' );

		$meta = $instance->get_meta( $this->post_id );

		$this->assertSame( 'paired_value', $meta['paired'] );
		$this->assertSame( 'field_paired', $meta['_paired'] );
		$this->assertArrayNotHasKey( 'orphan_meta', $meta );

		// Serialized values are unserialized on read.
		$this->assertSame( $array_value, $meta['paired_array'] );
		$this->assertSame( 'field_paired_array', $meta['_paired_array'] );
	}

	/**
	 * Test that update_meta() slashes data so quotes and backslashes survive.
	 */
	public function test_post_location_update_meta_preserves_slashes() {
		$instance = acf_get_meta_instance( 'post' );

		$meta = array(
			'quoted'     => 'He said "hi" and it\'s fine',
			'_quoted'    => 'field_quoted',
			'backslash'  => 'C:\\Users\\test',
			'_backslash' => 'field_backslash',
		);

		// Pre-seed the keys: WorDBless' update_metadata() handler falls back
		// to add_metadata() for new keys, which unslashes a second time
		// (a known WorDBless limitation), unlike a real database.
		$this->seed_meta_keys( 'post', $this->post_id, array_keys( $meta ) );

		$instance->update_meta( $this->post_id, $meta );

		$this->assertSame( 'He said "hi" and it\'s fine', $instance->get_value( $this->post_id, $this->get_field( 'quoted' ) ) );
		$this->assertSame( 'C:\\Users\\test', $instance->get_value( $this->post_id, $this->get_field( 'backslash' ) ) );

		$read_back = $instance->get_meta( $this->post_id );
		$this->assertSame( $meta, $read_back );
	}

	// =========================================================================
	// User location (typed post ID routing).
	// =========================================================================

	/**
	 * Test that 'user_X' post IDs route to user meta.
	 */
	public function test_user_location_routing() {
		$typed_id = 'user_' . $this->user_id;

		// Update routes to user meta.
		acf_update_metadata( $typed_id, 'user_field', 'user_value' );
		$this->assertSame( 'user_value', acf_get_metadata( $typed_id, 'user_field' ) );
		$this->assertSame( 'user_value', get_user_meta( $this->user_id, 'user_field', true ) );

		// Hidden meta uses an underscore prefix.
		acf_update_metadata( $typed_id, 'user_field', 'field_user_field', true );
		$this->assertSame( 'field_user_field', get_user_meta( $this->user_id, '_user_field', true ) );

		// acf_get_meta() returns the paired values.
		$meta = acf_get_meta( $typed_id );
		$this->assertSame(
			array(
				'user_field'  => 'user_value',
				'_user_field' => 'field_user_field',
			),
			$meta
		);

		// Delete.
		$this->assertTrue( acf_delete_metadata( $typed_id, 'user_field' ) );
		$this->assertNull( acf_get_metadata( $typed_id, 'user_field' ) );
	}

	/**
	 * Test the User location class API directly.
	 */
	public function test_user_location_class_api() {
		$instance = acf_get_meta_instance( 'user' );
		$field    = $this->get_field( 'user_class_field' );

		$instance->update_value( $this->user_id, $field, array( 'a' => 1 ) );
		$this->assertSame( array( 'a' => 1 ), $instance->get_value( $this->user_id, $field ) );

		$instance->update_reference( $this->user_id, 'user_class_field', 'field_user_class_field' );
		$this->assertSame( 'field_user_class_field', $instance->get_reference( $this->user_id, 'user_class_field' ) );

		$this->assertTrue( $instance->delete_value( $this->user_id, $field ) );
		$this->assertTrue( $instance->delete_reference( $this->user_id, 'user_class_field' ) );
		$this->assertNull( $instance->get_value( $this->user_id, $field ) );
		$this->assertNull( $instance->get_reference( $this->user_id, 'user_class_field' ) );
	}

	// =========================================================================
	// Term location (typed post ID routing).
	// =========================================================================

	/**
	 * Test that 'term_X' post IDs route to term meta.
	 */
	public function test_term_location_routing() {
		$typed_id = 'term_' . $this->term_id;

		acf_update_metadata( $typed_id, 'term_field', 'term_value' );
		$this->assertSame( 'term_value', acf_get_metadata( $typed_id, 'term_field' ) );
		$this->assertSame( 'term_value', get_metadata( 'term', $this->term_id, 'term_field', true ) );

		acf_update_metadata( $typed_id, 'term_field', 'field_term_field', true );
		$this->assertSame( 'field_term_field', acf_get_metadata( $typed_id, 'term_field', true ) );

		$meta = acf_get_meta( $typed_id );
		$this->assertSame(
			array(
				'term_field'  => 'term_value',
				'_term_field' => 'field_term_field',
			),
			$meta
		);

		$this->assertTrue( acf_delete_metadata( $typed_id, 'term_field' ) );
		$this->assertNull( acf_get_metadata( $typed_id, 'term_field' ) );
	}

	/**
	 * Test that taxonomy-prefixed post IDs (e.g. 'category_X') decode to terms.
	 */
	public function test_taxonomy_prefixed_post_id_routes_to_term() {
		// Write via the taxonomy-prefixed format.
		acf_update_metadata( 'category_' . $this->term_id, 'tax_field', 'tax_value' );

		// Read back via the canonical 'term_X' format - same storage.
		$this->assertSame( 'tax_value', acf_get_metadata( 'term_' . $this->term_id, 'tax_field' ) );
		$this->assertSame( 'tax_value', get_metadata( 'term', $this->term_id, 'tax_field', true ) );
	}

	// =========================================================================
	// Comment location (typed post ID routing).
	// =========================================================================

	/**
	 * Test that 'comment_X' post IDs route to comment meta.
	 */
	public function test_comment_location_routing() {
		$typed_id = 'comment_' . $this->comment_id;

		acf_update_metadata( $typed_id, 'comment_field', 'comment_value' );
		$this->assertSame( 'comment_value', acf_get_metadata( $typed_id, 'comment_field' ) );
		$this->assertSame( 'comment_value', get_metadata( 'comment', $this->comment_id, 'comment_field', true ) );

		acf_update_metadata( $typed_id, 'comment_field', 'field_comment_field', true );

		$meta = acf_get_meta( $typed_id );
		$this->assertSame(
			array(
				'comment_field'  => 'comment_value',
				'_comment_field' => 'field_comment_field',
			),
			$meta
		);

		$this->assertTrue( acf_delete_metadata( $typed_id, 'comment_field' ) );
		$this->assertNull( acf_get_metadata( $typed_id, 'comment_field' ) );
	}

	/**
	 * Test the Comment location class API with serialized values.
	 */
	public function test_comment_location_class_api_serialized_value() {
		$instance = acf_get_meta_instance( 'comment' );
		$field    = $this->get_field( 'comment_array' );
		$value    = array(
			'nested' => array( 'deep' => 'value' ),
		);

		$instance->update_value( $this->comment_id, $field, $value );
		$this->assertSame( $value, $instance->get_value( $this->comment_id, $field ) );

		$this->assertTrue( $instance->delete_value( $this->comment_id, $field ) );
		$this->assertNull( $instance->get_value( $this->comment_id, $field ) );
	}

	// =========================================================================
	// acf_*_metadata_by_field() routing.
	// =========================================================================

	/**
	 * Test the by-field metadata functions against the post location.
	 */
	public function test_metadata_by_field_routing() {
		$field = $this->get_field( 'by_field' );

		// Update and get the value.
		$result = acf_update_metadata_by_field( $this->post_id, $field, 'by_field_value' );
		$this->assertNotFalse( $result );
		$this->assertSame( 'by_field_value', acf_get_metadata_by_field( $this->post_id, $field ) );

		// Hidden updates write the field *key* as a reference. The $value
		// argument is intentionally ignored when $hidden is true.
		acf_update_metadata_by_field( $this->post_id, $field, 'ignored_value', true );
		$this->assertSame( 'field_by_field', acf_get_metadata_by_field( $this->post_id, $field, true ) );
		$this->assertSame( 'field_by_field', get_post_meta( $this->post_id, '_by_field', true ) );

		// Delete value and reference.
		$this->assertTrue( acf_delete_metadata_by_field( $this->post_id, $field ) );
		$this->assertTrue( acf_delete_metadata_by_field( $this->post_id, $field, true ) );
		$this->assertNull( acf_get_metadata_by_field( $this->post_id, $field ) );
		$this->assertNull( acf_get_metadata_by_field( $this->post_id, $field, true ) );
	}

	/**
	 * Test the by-field metadata functions with a typed user post ID.
	 */
	public function test_metadata_by_field_routing_typed_user_id() {
		$typed_id = 'user_' . $this->user_id;
		$field    = $this->get_field( 'by_field_user' );

		acf_update_metadata_by_field( $typed_id, $field, 'user_by_field' );
		$this->assertSame( 'user_by_field', acf_get_metadata_by_field( $typed_id, $field ) );
		$this->assertSame( 'user_by_field', get_user_meta( $this->user_id, 'by_field_user', true ) );

		$this->assertTrue( acf_delete_metadata_by_field( $typed_id, $field ) );
	}

	/**
	 * Test that the by-field functions bail on an empty field name.
	 */
	public function test_metadata_by_field_empty_field_name() {
		$field = array( 'key' => 'field_nameless' );

		$this->assertNull( acf_get_metadata_by_field( $this->post_id, $field ) );
		$this->assertNull( acf_update_metadata_by_field( $this->post_id, $field, 'value' ) );
		$this->assertNull( acf_delete_metadata_by_field( $this->post_id, $field ) );
	}

	// =========================================================================
	// acf_copy_metadata() between locations.
	// =========================================================================

	/**
	 * Test copying meta from a post to a user.
	 */
	public function test_copy_metadata_post_to_user() {
		$instance    = acf_get_meta_instance( 'post' );
		$array_value = array(
			'x' => 1,
			'y' => array( 'z' => 2 ),
		);

		$instance->update_value( $this->post_id, $this->get_field( 'copy_text' ), 'copied' );
		$instance->update_reference( $this->post_id, 'copy_text', 'field_copy_text' );
		$instance->update_value( $this->post_id, $this->get_field( 'copy_array' ), $array_value );
		$instance->update_reference( $this->post_id, 'copy_array', 'field_copy_array' );

		// Orphan meta without a reference must not be copied.
		update_post_meta( $this->post_id, 'copy_orphan', 'not_copied' );

		acf_copy_metadata( $this->post_id, 'user_' . $this->user_id );

		$this->assertSame( 'copied', get_user_meta( $this->user_id, 'copy_text', true ) );
		$this->assertSame( 'field_copy_text', get_user_meta( $this->user_id, '_copy_text', true ) );
		$this->assertSame( $array_value, get_user_meta( $this->user_id, 'copy_array', true ) );
		$this->assertSame( 'field_copy_array', get_user_meta( $this->user_id, '_copy_array', true ) );
		$this->assertSame( '', get_user_meta( $this->user_id, 'copy_orphan', true ) );
	}

	/**
	 * Test that copying meta between posts preserves backslashes.
	 */
	public function test_copy_metadata_post_to_post_preserves_slashes() {
		$instance = acf_get_meta_instance( 'post' );

		// Pre-seed the source keys to avoid the WorDBless double-unslash
		// quirk on the add_metadata() fallback path for new keys.
		$this->seed_meta_keys( 'post', $this->post_id, array( 'slashy', '_slashy' ) );

		$instance->update_meta(
			$this->post_id,
			array(
				'slashy'  => 'A \\ B "quoted"',
				'_slashy' => 'field_slashy',
			)
		);

		$dest_post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Copy Destination',
				'post_status' => 'publish',
			)
		);

		// Pre-seed the destination keys to avoid the WorDBless double-unslash
		// quirk on the add_metadata() fallback path for new keys.
		$this->seed_meta_keys( 'post', $dest_post_id, array( 'slashy', '_slashy' ) );

		acf_copy_metadata( $this->post_id, $dest_post_id );

		$this->assertSame( 'A \\ B "quoted"', get_post_meta( $dest_post_id, 'slashy', true ) );
		$this->assertSame( 'field_slashy', get_post_meta( $dest_post_id, '_slashy', true ) );
	}

	// =========================================================================
	// Option location.
	// =========================================================================

	/**
	 * Test that the Option location stores values in name-prefixed options.
	 */
	public function test_option_location_value_and_reference_keys() {
		$instance = acf_get_meta_instance( 'option' );
		$field    = $this->get_field( 'color' );

		// Values are stored as '{object_id}_{field_name}' options.
		$instance->update_value( 'options', $field, 'blue' );
		$this->assertSame( 'blue', get_option( 'options_color' ) );
		$this->assertSame( 'blue', $instance->get_value( 'options', $field ) );

		// References are stored as '_{object_id}_{field_name}' options.
		$instance->update_reference( 'options', 'color', 'field_color' );
		$this->assertSame( 'field_color', get_option( '_options_color' ) );
		$this->assertSame( 'field_color', $instance->get_reference( 'options', 'color' ) );

		// Delete both.
		$this->assertTrue( $instance->delete_value( 'options', $field ) );
		$this->assertTrue( $instance->delete_reference( 'options', 'color' ) );
		$this->assertNull( $instance->get_value( 'options', $field ) );
		$this->assertNull( $instance->get_reference( 'options', 'color' ) );
	}

	/**
	 * Test that unknown post ID formats are routed to the option location.
	 */
	public function test_option_routing_with_arbitrary_post_id() {
		// 'options' is the canonical options page ID.
		acf_update_metadata( 'options', 'setting', 'v1' );
		$this->assertSame( 'v1', get_option( 'options_setting' ) );
		$this->assertSame( 'v1', acf_get_metadata( 'options', 'setting' ) );

		// Unknown string formats are treated as option IDs verbatim.
		acf_update_metadata( 'my_custom_page', 'setting', 'v2' );
		$this->assertSame( 'v2', get_option( 'my_custom_page_setting' ) );
		$this->assertSame( 'v2', acf_get_metadata( 'my_custom_page', 'setting' ) );

		// Hidden meta gets the underscore prefix on the option name.
		acf_update_metadata( 'my_custom_page', 'setting', 'field_setting', true );
		$this->assertSame( 'field_setting', get_option( '_my_custom_page_setting' ) );

		// Deletes.
		$this->assertTrue( acf_delete_metadata( 'options', 'setting' ) );
		$this->assertTrue( acf_delete_metadata( 'my_custom_page', 'setting' ) );
		$this->assertTrue( acf_delete_metadata( 'my_custom_page', 'setting', true ) );
		$this->assertNull( acf_get_metadata( 'my_custom_page', 'setting' ) );
	}

	/**
	 * Test that the Option location honours the 'autoload' SCF setting.
	 */
	public function test_option_location_autoload_setting() {
		$instance = acf_get_meta_instance( 'option' );

		// Capture INSERT statements sent to the (mocked) database so we can
		// inspect the autoload value used when creating the option row.
		add_filter(
			'wordbless_wpdb_query_results',
			function ( $results, $query ) {
				if ( false !== strpos( $query, 'INSERT INTO' ) ) {
					$this->captured_queries[] = $query;
				}
				return $results;
			},
			10,
			2
		);

		// Default: autoload setting is false, so options are not autoloaded.
		$this->assertFalse( acf_get_setting( 'autoload' ) );
		$instance->update_value( 'options', $this->get_field( 'autoload_off_test' ), 'av0' );

		$insert_off = $this->find_captured_insert( 'options_autoload_off_test' );
		$this->assertNotNull( $insert_off, 'Expected an INSERT for the autoload-off option.' );
		$this->assertStringContainsString( "'off'", $insert_off );

		// With the setting enabled, new options are autoloaded.
		acf_update_setting( 'autoload', true );
		$instance->update_value( 'options', $this->get_field( 'autoload_on_test' ), 'av1' );

		$insert_on = $this->find_captured_insert( 'options_autoload_on_test' );
		$this->assertNotNull( $insert_on, 'Expected an INSERT for the autoload-on option.' );
		$this->assertStringContainsString( "'on'", $insert_on );
	}

	/**
	 * Finds a captured INSERT query containing the given option name.
	 *
	 * @param string $option_name The option name to search for.
	 * @return string|null
	 */
	private function find_captured_insert( string $option_name ): ?string {
		foreach ( $this->captured_queries as $query ) {
			if ( false !== strpos( $query, $option_name ) ) {
				return $query;
			}
		}
		return null;
	}

	/**
	 * Test acf_get_option_meta() row parsing and Option::get_meta() pairing.
	 */
	public function test_option_get_meta_pairs_values_with_references() {
		$serialized_array = serialize( array( 'a', 'b' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Emulating raw database rows.

		// Simulate the wp_options rows that the direct SQL query in
		// acf_get_option_meta() would return, since WorDBless has no real DB.
		add_filter(
			'wordbless_wpdb_query_results',
			function ( $results, $query ) use ( $serialized_array ) {
				if ( false === strpos( $query, 'option_name LIKE' ) ) {
					return $results;
				}
				return array(
					(object) array(
						'option_name'  => 'options_color',
						'option_value' => 'red',
					),
					(object) array(
						'option_name'  => '_options_color',
						'option_value' => 'field_color',
					),
					(object) array(
						'option_name'  => 'options_list',
						'option_value' => $serialized_array,
					),
					(object) array(
						'option_name'  => '_options_list',
						'option_value' => 'field_list',
					),
					(object) array(
						'option_name'  => 'options_orphan',
						'option_value' => 'no_reference',
					),
				);
			},
			10,
			2
		);

		// acf_get_option_meta() strips the prefix and groups values.
		$option_meta = acf_get_option_meta( 'options' );
		$this->assertSame( array( 'red' ), $option_meta['color'] );
		$this->assertSame( array( 'field_color' ), $option_meta['_color'] );
		$this->assertSame( array( 'no_reference' ), $option_meta['orphan'] );

		// Option::get_meta() only returns values paired with a reference.
		$instance = acf_get_meta_instance( 'option' );
		$meta     = $instance->get_meta( 'options' );

		$this->assertSame( 'red', $meta['color'] );
		$this->assertSame( 'field_color', $meta['_color'] );
		$this->assertArrayNotHasKey( 'orphan', $meta );

		// NOTE: documents current behavior — possible bug: unlike
		// MetaLocation::get_meta(), Option::get_meta() does not run values
		// through acf_maybe_unserialize(), so serialized arrays are returned
		// as raw serialized strings.
		$this->assertSame( $serialized_array, $meta['list'] );

		// acf_get_meta( 'options' ) routes to the same backend.
		$this->assertSame( $meta, acf_get_meta( 'options' ) );
	}

	/**
	 * Test Option::update_meta() unslashing behavior.
	 */
	public function test_option_update_meta_unslashes_values() {
		$instance = acf_get_meta_instance( 'option' );

		$instance->update_meta(
			'options',
			array(
				'plain'  => 'value1',
				'slashy' => 'C:\\temp\\new',
			)
		);

		$this->assertSame( 'value1', get_option( 'options_plain' ) );

		// NOTE: documents current behavior — possible bug: Option::update_meta()
		// calls wp_unslash() on the raw values while MetaLocation::update_meta()
		// wp_slash()es them before update_metadata(), so values containing
		// backslashes lose them when copied to an option location
		// (e.g. via acf_copy_metadata() to an options page).
		$this->assertSame( 'C:tempnew', get_option( 'options_slashy' ) );
	}

	// =========================================================================
	// Slashing through the routing layer.
	// =========================================================================

	/**
	 * Test that slashed input round-trips identically for post and option types.
	 */
	public function test_metadata_routing_slash_contract() {
		$value = 'Quote " and backslash \\ and it\'s';

		// Pre-seed the key to avoid the WorDBless double-unslash quirk on
		// the add_metadata() fallback path for new keys.
		$this->seed_meta_keys( 'post', $this->post_id, array( 'slash_contract' ) );

		// Both backends unslash internally, so callers pass slashed data.
		acf_update_metadata( $this->post_id, 'slash_contract', wp_slash( $value ) );
		$this->assertSame( $value, acf_get_metadata( $this->post_id, 'slash_contract' ) );

		acf_update_metadata( 'options', 'slash_contract', wp_slash( $value ) );
		$this->assertSame( $value, acf_get_metadata( 'options', 'slash_contract' ) );
	}

	// =========================================================================
	// acf_get_value() integration with locations.
	// =========================================================================

	/**
	 * Test that acf_get_value() reads through the location backends.
	 */
	public function test_acf_get_value_uses_location_backends() {
		$field = $this->get_field( 'integration_field' );

		// Post location.
		acf_update_value( 'post_value', $this->post_id, $field );
		acf_get_store( 'values' )->reset();
		$this->assertSame( 'post_value', acf_get_value( $this->post_id, $field ) );

		// User location via typed ID.
		$typed_id = 'user_' . $this->user_id;
		acf_update_value( 'user_value', $typed_id, $field );
		acf_get_store( 'values' )->reset();
		$this->assertSame( 'user_value', acf_get_value( $typed_id, $field ) );

		// Option location: acf_update_value() also writes the reference key,
		// which acf_get_value() requires for non 'options' option IDs.
		acf_update_value( 'option_value', 'my_opts_page', $field );
		$this->assertSame( 'field_integration_field', get_option( '_my_opts_page_integration_field' ) );
		acf_get_store( 'values' )->reset();
		$this->assertSame( 'option_value', acf_get_value( 'my_opts_page', $field ) );
	}
}
