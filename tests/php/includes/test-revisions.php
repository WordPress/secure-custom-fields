<?php
/**
 * Tests for SCF revision support in includes/revisions.php and
 * includes/Datastore/Revisions.php.
 *
 * Note on the WorDBless environment: two gaps in WorDBless are shimmed so
 * the revision flow can run as a true integration test.
 *
 * 1. get_metadata() with an empty key (fetch-all) is not implemented, which
 *    acf_get_meta()/acf_copy_metadata() rely on. A `get_post_metadata`
 *    filter backfills it from the WorDBless postmeta store.
 * 2. WP_Query runs raw SQL, which WorDBless no-ops, so wp_get_post_revisions()
 *    always returns nothing. A `posts_pre_query` filter answers revision
 *    queries from the WorDBless posts store.
 *
 * wp_save_post_revision(), _wp_put_post_revision() and
 * wp_restore_post_revision() themselves work natively under WorDBless.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test SCF revision saving, restoring and revision field rendering.
 */
class Test_ACF_Revisions extends BaseTestCase {

	/**
	 * Test post ID.
	 *
	 * @var integer
	 */
	private $post_id;

	/**
	 * The registered test field array.
	 *
	 * @var array
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->install_wordbless_shims();

		acf_add_local_field_group(
			array(
				'key'      => 'group_revision_test',
				'title'    => 'Revision Test Group',
				'fields'   => array(
					array(
						'key'   => 'field_revision_text',
						'label' => 'Revision Text',
						'name'  => 'revision_text',
						'type'  => 'text',
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
		$this->field = acf_get_field( 'field_revision_text' );

		$this->post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Revision Test Post',
				'post_status'  => 'publish',
				'post_content' => 'Version 1',
			)
		);

		acf_get_store( 'values' )->reset();
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		unset( $_POST['_acf_changed'], $_POST['action'], $_GET['preview'], $_GET['preview_id'] );
		acf_get_store( 'values' )->reset();
		acf()->revisions->cache = array();
		unregister_meta_key( 'post', '_acf' );
		parent::tearDown();
	}

	/**
	 * Installs filters that backfill WorDBless gaps (fetch-all postmeta and
	 * revision WP_Query lookups). Hooks are restored by BaseTestCase teardown.
	 *
	 * @return void
	 */
	private function install_wordbless_shims() {
		add_filter(
			'get_post_metadata',
			function ( $check, $object_id, $meta_key ) {
				if ( '' !== $meta_key ) {
					return $check;
				}

				$store = \WorDBless\PostMeta::init();
				$all   = array();
				if ( isset( $store->meta[ $object_id ] ) ) {
					foreach ( $store->meta[ $object_id ] as $row ) {
						$all[ $row['meta_key'] ][] = $row['meta_value'];
					}
				}
				return $all;
			},
			20,
			3
		);

		add_filter(
			'posts_pre_query',
			function ( $posts, $query ) {
				if ( 'revision' !== $query->get( 'post_type' ) ) {
					return $posts;
				}

				$parent = (int) $query->get( 'post_parent' );
				$found  = array();
				foreach ( \WorDBless\Posts::init()->posts as $post ) {
					if ( 'revision' === $post->post_type && (int) $post->post_parent === $parent && 'inherit' === $post->post_status ) {
						$found[] = new WP_Post( $post );
					}
				}
				usort(
					$found,
					function ( $a, $b ) {
						return $b->ID <=> $a->ID;
					}
				);
				return $found;
			},
			10,
			2
		);
	}

	/**
	 * Pretends to be on the revisions admin screen so that
	 * wp_post_revision_fields() does not bail early.
	 *
	 * @return void
	 */
	private function fake_revision_screen() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_POST['action'] = 'get-revision-diffs';
	}

	// =========================================================================
	// acf_get_post_latest_revision / acf_save_post_revision
	// =========================================================================

	/**
	 * Test that acf_get_post_latest_revision() returns null without revisions.
	 */
	public function test_get_post_latest_revision_none() {
		$this->assertNull( acf_get_post_latest_revision( $this->post_id ) );
	}

	/**
	 * Test that acf_get_post_latest_revision() returns the newest revision.
	 */
	public function test_get_post_latest_revision_returns_newest() {
		$rev1 = wp_save_post_revision( $this->post_id );
		$this->assertIsInt( $rev1 );

		// Change the post so a second revision is allowed.
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => 'Version 2',
			)
		);

		$latest = acf_get_post_latest_revision( $this->post_id );
		$this->assertInstanceOf( 'WP_Post', $latest );
		$this->assertGreaterThan( $rev1, $latest->ID );
		$this->assertEquals( $this->post_id, $latest->post_parent );
	}

	/**
	 * Test that acf_save_post_revision() copies SCF meta (values and field
	 * key references) from the post to its latest revision.
	 */
	public function test_save_post_revision_copies_meta() {
		acf_update_value( 'first value', $this->post_id, $this->field );

		$rev_id = wp_save_post_revision( $this->post_id );
		$this->assertIsInt( $rev_id );

		// The freshly created revision has no SCF meta yet.
		$this->assertSame( '', get_post_meta( $rev_id, 'revision_text', true ) );

		acf_save_post_revision( $this->post_id );

		$this->assertSame( 'first value', get_post_meta( $rev_id, 'revision_text', true ) );
		$this->assertSame( 'field_revision_text', get_post_meta( $rev_id, '_revision_text', true ) );
	}

	/**
	 * Test that acf_save_post_revision() is a no-op when no revision exists.
	 */
	public function test_save_post_revision_without_revision() {
		acf_update_value( 'orphan value', $this->post_id, $this->field );

		// Should not error.
		acf_save_post_revision( $this->post_id );

		$this->assertNull( acf_get_post_latest_revision( $this->post_id ) );
	}

	// =========================================================================
	// Restoring a revision
	// =========================================================================

	/**
	 * Test that restoring a revision restores SCF field values and their
	 * hidden field key references on the parent post.
	 */
	public function test_restore_revision_restores_values_and_references() {
		// Save the original value and snapshot it into a revision.
		acf_update_value( 'first value', $this->post_id, $this->field );
		$rev_id = wp_save_post_revision( $this->post_id );
		acf_save_post_revision( $this->post_id );

		// Now change the value and corrupt the reference on the parent post.
		acf_get_store( 'values' )->reset();
		acf_update_value( 'second value', $this->post_id, $this->field );
		update_post_meta( $this->post_id, '_revision_text', 'field_something_else' );

		// Restore the original revision through the real WP API. This fires
		// the wp_restore_post_revision action handled by acf_revisions.
		$restored = wp_restore_post_revision( $rev_id );
		$this->assertEquals( $this->post_id, $restored );

		// Raw meta restored.
		$this->assertSame( 'first value', get_post_meta( $this->post_id, 'revision_text', true ) );
		$this->assertSame( 'field_revision_text', get_post_meta( $this->post_id, '_revision_text', true ) );

		// And the value loads through the SCF API.
		acf_get_store( 'values' )->reset();
		$this->assertSame( 'first value', acf_get_value( $this->post_id, $this->field ) );
	}

	/**
	 * Test that restoring also syncs the latest revision with the restored
	 * meta, so the restore itself is revisioned consistently.
	 */
	public function test_restore_revision_updates_latest_revision() {
		acf_update_value( 'first value', $this->post_id, $this->field );
		$rev1 = wp_save_post_revision( $this->post_id );
		acf_save_post_revision( $this->post_id );

		// Create a newer revision with a different value.
		acf_get_store( 'values' )->reset();
		acf_update_value( 'second value', $this->post_id, $this->field );
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => 'Version 2',
			)
		);
		acf_save_post_revision( $this->post_id );

		$latest = acf_get_post_latest_revision( $this->post_id );
		$this->assertNotEquals( $rev1, $latest->ID );
		$this->assertSame( 'second value', get_post_meta( $latest->ID, 'revision_text', true ) );

		// Restore revision 1 directly via the SCF handler.
		acf()->revisions->wp_restore_post_revision( $this->post_id, $rev1 );

		// Parent post and the latest revision both carry the restored value.
		$this->assertSame( 'first value', get_post_meta( $this->post_id, 'revision_text', true ) );
		$latest = acf_get_post_latest_revision( $this->post_id );
		$this->assertSame( 'first value', get_post_meta( $latest->ID, 'revision_text', true ) );
		$this->assertSame( 'field_revision_text', get_post_meta( $latest->ID, '_revision_text', true ) );
	}

	// =========================================================================
	// Revision creation / change detection
	// =========================================================================

	/**
	 * Test that an unchanged post does not create a new revision, but a
	 * save flagged with _acf_changed does.
	 */
	public function test_acf_changed_flag_forces_new_revision() {
		wp_save_post_revision( $this->post_id );
		$count = count( wp_get_post_revisions( $this->post_id ) );

		// Identical post: WP detects no change, no new revision.
		$this->assertNull( wp_save_post_revision( $this->post_id ) );
		$this->assertCount( $count, wp_get_post_revisions( $this->post_id ) );

		// Classic editor save with changed SCF fields: revision forced via
		// the wp_save_post_revision_post_has_changed filter.
		$_POST['_acf_changed'] = '1';
		$new_rev               = wp_save_post_revision( $this->post_id );
		$this->assertIsInt( $new_rev );
		$this->assertCount( $count + 1, wp_get_post_revisions( $this->post_id ) );
	}

	/**
	 * Test check_acf_fields_have_changed() returns the original value when
	 * nothing indicates an SCF change.
	 */
	public function test_check_acf_fields_have_changed_passthrough() {
		$revision = get_post( _wp_put_post_revision( get_post( $this->post_id ) ) );
		$post     = get_post( $this->post_id );

		$this->assertFalse( acf()->revisions->check_acf_fields_have_changed( false, $revision, $post ) );
		$this->assertTrue( acf()->revisions->check_acf_fields_have_changed( true, $revision, $post ) );
	}

	/**
	 * Test maybe_save_revision() deletes the _acf_changed marker when no SCF
	 * save has occurred during the request.
	 */
	public function test_maybe_save_revision_clears_acf_changed_without_save() {
		if ( did_action( 'acf/save_post' ) ) {
			$this->markTestSkipped( 'acf/save_post already fired in this process; cannot test the not-saved branch.' );
		}

		$rev_id = _wp_put_post_revision( get_post( $this->post_id ) );
		update_post_meta( $this->post_id, '_acf_changed', '1' );
		update_post_meta( $rev_id, '_acf_changed', '1' );

		acf()->revisions->maybe_save_revision( $rev_id, $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, '_acf_changed', true ) );
		$this->assertSame( '', get_post_meta( $rev_id, '_acf_changed', true ) );
	}

	/**
	 * Test maybe_save_revision() copies SCF meta to the latest revision after
	 * an SCF save has occurred.
	 */
	public function test_maybe_save_revision_copies_meta_after_save() {
		acf_update_value( 'saved value', $this->post_id, $this->field );
		$rev_id = _wp_put_post_revision( get_post( $this->post_id ) );

		// Simulate that SCF saved fields during this request.
		do_action( 'acf/save_post', $this->post_id );

		acf()->revisions->maybe_save_revision( $rev_id, $this->post_id );

		$this->assertSame( 'saved value', get_post_meta( $rev_id, 'revision_text', true ) );
		$this->assertSame( 'field_revision_text', get_post_meta( $rev_id, '_revision_text', true ) );
	}

	// =========================================================================
	// wp_post_revision_fields / wp_post_revision_field
	// =========================================================================

	/**
	 * Test that SCF fields are appended to the revision fields array on the
	 * revision screen, and the per-field render filter is registered.
	 */
	public function test_wp_post_revision_fields_appends_acf_fields() {
		acf_update_value( 'first value', $this->post_id, $this->field );
		$this->fake_revision_screen();

		$fields = acf()->revisions->wp_post_revision_fields(
			array( 'post_title' => 'Title' ),
			array( 'ID' => $this->post_id )
		);

		$this->assertArrayHasKey( 'post_title', $fields );
		$this->assertArrayHasKey( 'revision_text', $fields );
		$this->assertSame( 'Revision Text (revision_text)', $fields['revision_text'] );

		// A render filter is attached for the field.
		$this->assertNotFalse( has_filter( '_wp_post_revision_field_revision_text' ) );
	}

	/**
	 * Test that meta without a field key reference is not added to the
	 * revision fields array.
	 */
	public function test_wp_post_revision_fields_skips_meta_without_reference() {
		update_post_meta( $this->post_id, 'plain_meta', 'no reference' );
		$this->fake_revision_screen();

		$fields = acf()->revisions->wp_post_revision_fields( array(), array( 'ID' => $this->post_id ) );

		$this->assertArrayNotHasKey( 'plain_meta', $fields );
	}

	/**
	 * Test that the fields array is untouched outside the revision screen.
	 */
	public function test_wp_post_revision_fields_bails_outside_revision_screen() {
		acf_update_value( 'first value', $this->post_id, $this->field );

		$fields = acf()->revisions->wp_post_revision_fields(
			array( 'post_title' => 'Title' ),
			array( 'ID' => $this->post_id )
		);

		$this->assertSame( array( 'post_title' => 'Title' ), $fields );
	}

	/**
	 * Test wp_post_revision_field() value rendering.
	 */
	public function test_wp_post_revision_field_rendering() {
		$post = get_post( $this->post_id );

		// Empty values render as an empty string.
		$this->assertSame( '', acf()->revisions->wp_post_revision_field( '', 'revision_text', $post ) );

		// Plain strings pass through.
		$this->assertSame( 'hello', acf()->revisions->wp_post_revision_field( 'hello', 'revision_text', $post ) );

		// Serialized arrays are unserialized and imploded for display.
		$serialized = serialize( array( 'one', 'two' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- raw meta is stored serialized.
		$this->assertSame( 'one, two', acf()->revisions->wp_post_revision_field( $serialized, 'revision_text', $post ) );
	}

	// =========================================================================
	// Legacy acf_revisions meta key registration
	// =========================================================================

	/**
	 * Test that _acf_changed is added to the revisioned meta keys.
	 */
	public function test_wp_post_revision_meta_keys_includes_acf_changed() {
		$keys = acf()->revisions->wp_post_revision_meta_keys( array( 'existing' ) );

		$this->assertSame( array( 'existing', '_acf_changed' ), $keys );
	}

	// =========================================================================
	// acf_validate_post_id (preview support)
	// =========================================================================

	/**
	 * Test that acf_validate_post_id() swaps in the latest revision ID when
	 * previewing a post.
	 */
	public function test_validate_post_id_returns_revision_for_preview() {
		$rev_id = wp_save_post_revision( $this->post_id );

		// Not previewing: post ID is returned untouched.
		$this->assertEquals( $this->post_id, acf()->revisions->acf_validate_post_id( $this->post_id, $this->post_id ) );

		// Previewing: the latest revision ID is returned instead.
		$_GET['preview']    = 'true';
		$_GET['preview_id'] = (string) $this->post_id;

		$this->assertEquals( $rev_id, acf()->revisions->acf_validate_post_id( $this->post_id, $this->post_id ) );
	}

	// =========================================================================
	// SCF\Datastore\Revisions
	// =========================================================================

	/**
	 * Test that the datastore revision integration is inert when the
	 * datastore is disabled (the default).
	 */
	public function test_datastore_inert_when_disabled() {
		$datastore = new \SCF\Datastore\Revisions();

		$this->assertSame( array( 'a' ), $datastore->add_acf_to_revision_meta_keys( array( 'a' ) ) );
		$this->assertFalse( $datastore->skip_during_rest( false ) );
		$this->assertTrue( $datastore->skip_during_rest( true ) );
	}

	/**
	 * Test that _acf is not revisioned outside REST requests even with the
	 * datastore enabled.
	 *
	 * The REST_REQUEST=true branches cannot be exercised here: defining the
	 * REST_REQUEST constant would leak into every other test in the process.
	 */
	public function test_datastore_does_not_revision_acf_meta_outside_rest() {
		add_filter( 'acf/settings/enable_datastore', '__return_true' );

		$datastore = new \SCF\Datastore\Revisions();

		$this->assertSame( array(), $datastore->add_acf_to_revision_meta_keys( array() ) );
		$this->assertFalse( $datastore->skip_during_rest( false ) );
	}

	/**
	 * Test that the registered _acf sanitize callback canonicalizes JSON so
	 * the stored bytes are stable across key reordering.
	 */
	public function test_datastore_acf_meta_sanitization_canonicalizes_json() {
		add_filter( 'acf/settings/enable_datastore', '__return_true' );

		$datastore = new \SCF\Datastore\Revisions();
		$datastore->register_meta();

		// Associative keys are sorted; sequential arrays keep their order.
		$value = wp_json_encode(
			array(
				'field_b' => 'two',
				'field_a' => array( 'z', 'a' ),
			)
		);
		$this->assertSame(
			'{"field_a":["z","a"],"field_b":"two"}',
			sanitize_meta( '_acf', $value, 'post' )
		);

		// Reordered input produces identical bytes.
		$reordered = wp_json_encode(
			array(
				'field_a' => array( 'z', 'a' ),
				'field_b' => 'two',
			)
		);
		$this->assertSame(
			sanitize_meta( '_acf', $value, 'post' ),
			sanitize_meta( '_acf', $reordered, 'post' )
		);

		// Invalid JSON and non-object payloads are rejected.
		$this->assertSame( '', sanitize_meta( '_acf', 'not json', 'post' ) );
		$this->assertSame( '', sanitize_meta( '_acf', '"just a string"', 'post' ) );
		$this->assertSame( '', sanitize_meta( '_acf', 123, 'post' ) );
	}
}
