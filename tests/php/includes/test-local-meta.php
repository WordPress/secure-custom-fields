<?php
/**
 * Tests for local-meta.php
 *
 * Integration tests for the local preview-meta layer: acf_setup_meta()
 * injecting values into get_field() without touching the database, and
 * acf_reset_meta() restoring normal behavior. Covers raw and request
 * (field-key) formats, the "main" post id shortcut, and nested repeater
 * values.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test local meta functionality.
 */
class Test_Local_Meta extends BaseTestCase {

	/**
	 * Key of the registered text field.
	 *
	 * @var string
	 */
	private $text_key = 'field_local_meta_bio';

	/**
	 * Key of the registered repeater field.
	 *
	 * @var string
	 */
	private $repeater_key = 'field_local_meta_items';

	/**
	 * Key of the registered repeater sub field.
	 *
	 * @var string
	 */
	private $sub_key = 'field_local_meta_detail';

	/**
	 * Post IDs used with acf_setup_meta during a test, reset in tearDown.
	 *
	 * @var array
	 */
	private $meta_ids = array();

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->ensure_field_types();
		$this->ensure_local_meta_filters();

		// Start from a clean slate.
		acf_reset_local();
		acf_get_store( 'local-empty' )->reset();
		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'values' )->reset();

		// Register the fields the local meta will reference.
		acf_add_local_field_group(
			array(
				'key'    => 'group_local_meta_test',
				'title'  => 'Local Meta Test Group',
				'fields' => array(
					array(
						'key'   => $this->text_key,
						'label' => 'Bio',
						'name'  => 'bio',
						'type'  => 'text',
					),
					array(
						'key'        => $this->repeater_key,
						'label'      => 'Items',
						'name'       => 'items',
						'type'       => 'repeater',
						'sub_fields' => array(
							array(
								'key'   => $this->sub_key,
								'label' => 'Detail',
								'name'  => 'detail',
								'type'  => 'text',
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		// Remove any local meta registered during the test.
		foreach ( $this->meta_ids as $post_id ) {
			acf_reset_meta( $post_id );
		}
		$this->meta_ids = array();

		// Reset all stores so we do not poison other tests.
		acf_reset_local();
		acf_get_store( 'local-empty' )->reset();
		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'values' )->reset();

		parent::tearDown();
	}

	/**
	 * Ensures the field types used by these tests are registered with their hooks.
	 *
	 * Field types normally register on 'init', which never fires in the WorDBless
	 * environment. The per-test hook restore also wipes their filters, so we
	 * re-register them for every test.
	 *
	 * @return void
	 */
	private function ensure_field_types() {
		acf_include( 'includes/fields/class-acf-field-text.php' );
		acf_include( 'includes/fields/class-acf-repeater-table.php' );
		acf_include( 'includes/fields/class-acf-field-repeater.php' );

		foreach ( array(
			'text'     => 'acf_field_text',
			'repeater' => 'acf_field_repeater',
		) as $name => $class ) {
			if ( ! has_filter( "acf/prepare_field_for_import/type={$name}" ) ) {
				acf_register_field_type( $class );
			}
		}
	}

	/**
	 * Ensures the ACF_Local_Meta filters are registered.
	 *
	 * The singleton is instantiated lazily and registers its filters in the
	 * constructor; the per-test hook restore wipes them while the instance
	 * survives, so they need to be re-added for every test.
	 *
	 * @return void
	 */
	private function ensure_local_meta_filters() {
		$local_meta = acf_get_instance( 'ACF_Local_Meta' );

		if ( ! has_filter( 'acf/pre_load_meta', array( $local_meta, 'pre_load_meta' ) ) ) {
			add_filter( 'acf/pre_load_post_id', array( $local_meta, 'pre_load_post_id' ), 1, 2 );
			add_filter( 'acf/pre_load_meta', array( $local_meta, 'pre_load_meta' ), 1, 2 );
			add_filter( 'acf/pre_load_metadata', array( $local_meta, 'pre_load_metadata' ), 1, 4 );
		}
	}

	/**
	 * Registers local meta and tracks the post id for cleanup.
	 *
	 * @param array   $meta    The meta array.
	 * @param mixed   $post_id The post id.
	 * @param boolean $is_main Whether this is the main meta.
	 * @return array
	 */
	private function setup_meta( $meta, $post_id, $is_main = false ) {
		$this->meta_ids[] = $post_id;
		return acf_setup_meta( $meta, $post_id, $is_main );
	}

	// =========================================================================
	// Raw meta format
	// =========================================================================

	/**
	 * Test get_field returns values from raw local meta without touching the DB.
	 */
	public function test_get_field_returns_raw_local_meta() {
		$this->setup_meta(
			array(
				'bio'  => 'Hello from local meta',
				'_bio' => $this->text_key,
			),
			'local_meta_test'
		);

		$this->assertSame(
			'Hello from local meta',
			get_field( 'bio', 'local_meta_test' ),
			'get_field should return the local value'
		);
	}

	/**
	 * Test acf_get_metadata is short-circuited by local meta.
	 */
	public function test_acf_get_metadata_uses_local_meta() {
		$this->setup_meta(
			array(
				'bio'  => 'Raw value',
				'_bio' => $this->text_key,
			),
			'local_meta_raw'
		);

		$this->assertSame( 'Raw value', acf_get_metadata( 'local_meta_raw', 'bio' ), 'Value should come from local meta' );
		$this->assertSame( $this->text_key, acf_get_metadata( 'local_meta_raw', 'bio', true ), 'Hidden reference should come from local meta' );
	}

	/**
	 * Test names missing from local meta resolve to null even if stored elsewhere.
	 */
	public function test_local_meta_shadows_missing_names() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Shadow Test',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'other_meta', 'db value' );

		$this->setup_meta(
			array(
				'bio'  => 'Local only',
				'_bio' => $this->text_key,
			),
			$post_id
		);

		// NOTE: documents current behavior — while local meta is active for a
		// post id, any meta name not present in the local set returns null,
		// shadowing real database values. Harmful interplay with leaked
		// setup_meta state is tracked in #455.
		$this->assertNull( acf_get_metadata( $post_id, 'other_meta' ), 'Names missing from local meta should resolve to null' );

		acf_reset_meta( $post_id );

		$this->assertSame( 'db value', acf_get_metadata( $post_id, 'other_meta' ), 'DB value should be visible again after reset' );

		wp_delete_post( $post_id, true );
	}

	// =========================================================================
	// Request (field-key) format and capture
	// =========================================================================

	/**
	 * Test field-key formatted meta is captured into flat meta without DB writes.
	 */
	public function test_capture_from_request_format_without_db_writes() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Capture Test',
				'post_status' => 'publish',
			)
		);

		$captured = $this->setup_meta(
			array(
				$this->text_key => 'Captured value',
			),
			$post_id
		);

		// The returned meta is flattened into name/value pairs plus references.
		$this->assertSame( 'Captured value', $captured['bio'], 'Captured meta should contain the value by name' );
		$this->assertSame( $this->text_key, $captured['_bio'], 'Captured meta should contain the field reference' );

		// get_field resolves the local value.
		$this->assertSame( 'Captured value', get_field( 'bio', $post_id ), 'get_field should return the captured value' );

		// The database was never written to.
		$this->assertSame( '', get_post_meta( $post_id, 'bio', true ), 'No postmeta should be written during capture' );
		$this->assertSame( '', get_post_meta( $post_id, '_bio', true ), 'No reference meta should be written during capture' );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test acf_reset_meta restores the database values.
	 */
	public function test_reset_meta_restores_db_values() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Reset Test',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'bio', 'database value' );
		update_post_meta( $post_id, '_bio', $this->text_key );

		// Local meta overrides the DB value.
		$this->setup_meta(
			array(
				$this->text_key => 'preview value',
			),
			$post_id
		);

		$this->assertSame( 'preview value', get_field( 'bio', $post_id ), 'Local meta should override the DB value' );

		// Reset restores the database value.
		acf_reset_meta( $post_id );
		acf_get_store( 'values' )->reset(); // Flush the cached value.

		$this->assertSame( 'database value', get_field( 'bio', $post_id ), 'DB value should be returned after reset' );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test get_field falls back to empty after resetting local-only meta.
	 */
	public function test_reset_meta_removes_local_only_values() {
		$this->setup_meta(
			array(
				'bio'  => 'Ephemeral',
				'_bio' => $this->text_key,
			),
			'local_meta_ephemeral'
		);

		$this->assertSame( 'Ephemeral', get_field( 'bio', 'local_meta_ephemeral' ), 'Value should be available while set up' );

		acf_reset_meta( 'local_meta_ephemeral' );
		acf_get_store( 'values' )->reset(); // Flush the cached value.

		$this->assertEmpty( get_field( 'bio', 'local_meta_ephemeral' ), 'Value should be gone after reset' );
	}

	// =========================================================================
	// Main post id
	// =========================================================================

	/**
	 * Test is_main makes the meta visible to get_field() without a post id.
	 */
	public function test_main_meta_resolves_without_post_id() {
		$this->setup_meta(
			array(
				'bio'  => 'Main value',
				'_bio' => $this->text_key,
			),
			'local_meta_main',
			true
		);

		$this->assertSame( 'Main value', get_field( 'bio' ), 'get_field without a post id should use the main local meta' );
		$this->assertSame( 'local_meta_main', acf_get_valid_post_id(), 'The main post id should be injected' );

		acf_reset_meta( 'local_meta_main' );

		$this->assertNotSame(
			'local_meta_main',
			acf_get_valid_post_id(),
			'The main post id should no longer be injected after reset'
		);
	}

	/**
	 * Test non-main meta does not hijack the global post id.
	 */
	public function test_non_main_meta_does_not_set_post_id() {
		$this->setup_meta(
			array(
				'bio'  => 'Not main',
				'_bio' => $this->text_key,
			),
			'local_meta_not_main'
		);

		$this->assertNotSame(
			'local_meta_not_main',
			acf_get_valid_post_id(),
			'A non-main local meta set should not change the resolved post id'
		);
	}

	// =========================================================================
	// Nested / repeater values
	// =========================================================================

	/**
	 * Test repeater values provided in request format resolve through get_field.
	 */
	public function test_repeater_values_resolve_from_local_meta() {
		$post_id = 'local_meta_repeater';

		$captured = $this->setup_meta(
			array(
				$this->repeater_key => array(
					array( $this->sub_key => 'Row one' ),
					array( $this->sub_key => 'Row two' ),
				),
			),
			$post_id
		);

		// Capture flattens the repeater into count + per-row meta.
		$this->assertSame( 2, $captured['items'], 'Captured repeater meta should store the row count' );
		$this->assertSame( 'Row one', $captured['items_0_detail'], 'Row 0 sub value should be flattened' );
		$this->assertSame( 'Row two', $captured['items_1_detail'], 'Row 1 sub value should be flattened' );

		$rows = get_field( 'items', $post_id );

		$this->assertIsArray( $rows, 'Repeater should return an array of rows' );
		$this->assertCount( 2, $rows, 'Repeater should have two rows' );
		$this->assertSame( 'Row one', $rows[0]['detail'], 'Row 0 value should match' );
		$this->assertSame( 'Row two', $rows[1]['detail'], 'Row 1 value should match' );
	}

	/**
	 * Test have_rows loops over local repeater meta.
	 */
	public function test_have_rows_uses_local_meta() {
		$post_id = 'local_meta_have_rows';

		$this->setup_meta(
			array(
				$this->repeater_key => array(
					array( $this->sub_key => 'Looped row' ),
				),
			),
			$post_id
		);

		$values = array();
		while ( have_rows( 'items', $post_id ) ) {
			the_row();
			$values[] = get_sub_field( 'detail' );
		}

		$this->assertSame( array( 'Looped row' ), $values, 'have_rows should loop over the local repeater rows' );
	}

	/**
	 * Test independent local meta for multiple post ids.
	 */
	public function test_multiple_post_ids_are_independent() {
		$this->setup_meta(
			array(
				'bio'  => 'First',
				'_bio' => $this->text_key,
			),
			'local_meta_a'
		);
		$this->setup_meta(
			array(
				'bio'  => 'Second',
				'_bio' => $this->text_key,
			),
			'local_meta_b'
		);

		$this->assertSame( 'First', get_field( 'bio', 'local_meta_a' ), 'First post id should keep its value' );
		$this->assertSame( 'Second', get_field( 'bio', 'local_meta_b' ), 'Second post id should keep its value' );

		// Removing one leaves the other intact.
		acf_reset_meta( 'local_meta_a' );
		acf_get_store( 'values' )->reset(); // Flush the cached values.

		$this->assertEmpty( get_field( 'bio', 'local_meta_a' ), 'First post id should be cleared' );
		$this->assertSame( 'Second', get_field( 'bio', 'local_meta_b' ), 'Second post id should be unaffected' );
	}
}
