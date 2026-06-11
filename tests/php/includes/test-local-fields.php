<?php
/**
 * Tests for local-fields.php
 *
 * Integration tests for the local (PHP-registered) field and field group API:
 * registration, deduplication, removal, parent/child resolution, key vs name
 * lookups, and enabling/disabling the local layer.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test local fields functionality.
 */
class Test_Local_Fields extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->ensure_field_types();

		// Start from a clean slate.
		acf_reset_local();
		acf_get_store( 'local-empty' )->reset();
		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'values' )->reset();

		// Make sure the local layer is enabled (it is by default).
		acf_enable_local();
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		// Reset all local stores so we do not poison other tests.
		acf_reset_local();
		acf_get_store( 'local-empty' )->reset();
		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'values' )->reset();

		// Re-enable local in case a test disabled it.
		acf_enable_local();
		remove_all_filters( 'acf/settings/local' );

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
	 * Helper to build a simple local field group definition.
	 *
	 * @param string $key    The group key.
	 * @param array  $fields The fields to attach.
	 * @return array
	 */
	private function make_group( $key, $fields = array() ) {
		return array(
			'key'    => $key,
			'title'  => 'Local Group ' . $key,
			'fields' => $fields,
		);
	}

	// =========================================================================
	// Registration
	// =========================================================================

	/**
	 * Test acf_add_local_field_group registers the group in the local store.
	 */
	public function test_add_local_field_group_registers_group() {
		$key    = 'group_local_register';
		$result = acf_add_local_field_group( $this->make_group( $key ) );

		$this->assertTrue( $result, 'Should return true on success' );
		$this->assertTrue( acf_is_local_field_group( $key ), 'Group should exist in the local store' );
		$this->assertTrue( acf_is_local_field_group_key( $key ), 'Key should be recognized as a local group key' );

		$group = acf_get_local_field_group( $key );
		$this->assertIsArray( $group, 'Should return the stored group' );
		$this->assertSame( $key, $group['key'], 'Stored group should keep its key' );
		$this->assertSame( 'php', $group['local'], 'Local source should default to php' );
		$this->assertArrayNotHasKey( 'fields', $group, 'Fields should be extracted from the stored group' );
	}

	/**
	 * Test acf_add_local_field_group generates a key from the title when missing.
	 */
	public function test_add_local_field_group_generates_key_from_title() {
		$result = acf_add_local_field_group(
			array(
				'title'  => 'My Local Group',
				'fields' => array(),
			)
		);

		$this->assertTrue( $result, 'Should return true on success' );
		$this->assertTrue( acf_is_local_field_group( 'group_my_local_group' ), 'Key should be slugified from the title' );
	}

	/**
	 * Test acf_add_local_field_group falls back to an md5 key for non-sluggable titles.
	 */
	public function test_add_local_field_group_generates_md5_key_for_unsluggable_title() {
		$title  = '???';
		$result = acf_add_local_field_group(
			array(
				'title'  => $title,
				'fields' => array(),
			)
		);

		$this->assertTrue( $result, 'Should return true on success' );
		$this->assertTrue(
			acf_is_local_field_group( 'group_' . md5( $title ) ),
			'Key should fall back to md5 of the title when slugify produces nothing'
		);
	}

	/**
	 * Test register_field_group is an alias for acf_add_local_field_group.
	 */
	public function test_register_field_group_alias() {
		register_field_group( $this->make_group( 'group_legacy_alias' ) );

		$this->assertTrue( acf_is_local_field_group( 'group_legacy_alias' ), 'Alias should register the group' );
	}

	/**
	 * Test adding a duplicate field group key is rejected.
	 */
	public function test_add_local_field_group_deduplicates() {
		$key = 'group_local_dupe';

		$this->assertTrue( acf_add_local_field_group( $this->make_group( $key ) ), 'First registration should succeed' );
		$this->assertFalse( acf_add_local_field_group( $this->make_group( $key ) ), 'Second registration with same key should fail' );
		$this->assertSame( 1, acf_count_local_field_groups(), 'Only one group should be stored' );
	}

	/**
	 * Test group counting helpers.
	 */
	public function test_count_and_have_local_field_groups() {
		$this->assertFalse( acf_have_local_field_groups(), 'Should have no groups initially' );
		$this->assertSame( 0, acf_count_local_field_groups(), 'Count should be zero initially' );

		acf_add_local_field_group( $this->make_group( 'group_count_a' ) );
		acf_add_local_field_group( $this->make_group( 'group_count_b' ) );

		$this->assertTrue( acf_have_local_field_groups(), 'Should have groups after registration' );
		$this->assertSame( 2, acf_count_local_field_groups(), 'Count should match registrations' );

		$groups = acf_get_local_field_groups();
		$this->assertCount( 2, $groups, 'Should return both groups' );
	}

	// =========================================================================
	// Fields attached to groups
	// =========================================================================

	/**
	 * Test fields supplied with a group are registered as local fields with the group as parent.
	 */
	public function test_group_fields_are_registered_with_parent() {
		$group_key = 'group_with_fields';
		$field_key = 'field_local_text_a';

		acf_add_local_field_group(
			$this->make_group(
				$group_key,
				array(
					array(
						'key'   => $field_key,
						'label' => 'Local Text',
						'name'  => 'local_text_a',
						'type'  => 'text',
					),
				)
			)
		);

		$this->assertTrue( acf_is_local_field( $field_key ), 'Field should exist by key' );
		$this->assertTrue( acf_is_local_field_key( $field_key ), 'Key should be recognized as a local field key' );

		$field = acf_get_local_field( $field_key );
		$this->assertIsArray( $field, 'Should return the field' );
		$this->assertSame( $group_key, $field['parent'], 'Field parent should be the group key' );

		// Parent query.
		$children = acf_get_local_fields( $group_key );
		$this->assertCount( 1, $children, 'Group should have one child field' );
		$this->assertTrue( acf_have_local_fields( $group_key ), 'Group should report having fields' );
		$this->assertSame( 1, acf_count_local_fields( $group_key ), 'Count should be one' );
	}

	/**
	 * Test local fields can be looked up by name via the store alias.
	 */
	public function test_local_field_lookup_by_name() {
		acf_add_local_field_group(
			$this->make_group(
				'group_name_lookup',
				array(
					array(
						'key'   => 'field_name_lookup',
						'label' => 'Named Field',
						'name'  => 'named_field',
						'type'  => 'text',
					),
				)
			)
		);

		$this->assertTrue( acf_is_local_field( 'named_field' ), 'Field should be found by name (alias)' );
		$this->assertFalse( acf_is_local_field_key( 'named_field' ), 'Name should not be treated as a key' );

		$field = acf_get_local_field( 'named_field' );
		$this->assertIsArray( $field, 'Should return the field by name' );
		$this->assertSame( 'field_name_lookup', $field['key'], 'Name lookup should resolve to the same field' );
	}

	/**
	 * Test acf_add_local_field generates a key from the name when missing.
	 */
	public function test_add_local_field_generates_key_from_name() {
		acf_add_local_field(
			array(
				'name'   => 'keyless_field',
				'type'   => 'text',
				'parent' => 'group_keyless',
			)
		);

		$this->assertTrue( acf_is_local_field( 'field_keyless_field' ), 'Key should be generated as field_{name}' );
	}

	/**
	 * Test acf_get_fields resolves local fields for a local group.
	 */
	public function test_acf_get_fields_returns_local_fields() {
		$group_key = 'group_get_fields';

		acf_add_local_field_group(
			$this->make_group(
				$group_key,
				array(
					array(
						'key'   => 'field_get_fields_a',
						'label' => 'A',
						'name'  => 'get_fields_a',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_get_fields_b',
						'label' => 'B',
						'name'  => 'get_fields_b',
						'type'  => 'text',
					),
				)
			)
		);

		$fields = acf_get_fields( $group_key );

		$this->assertCount( 2, $fields, 'Should return both fields' );
		$this->assertSame( 'get_fields_a', $fields[0]['name'], 'First field should match' );
		$this->assertSame( 'get_fields_b', $fields[1]['name'], 'Second field should match' );
	}

	/**
	 * Test acf_get_field_groups includes local groups via the load filter.
	 */
	public function test_acf_get_field_groups_includes_local_groups() {
		$group_key = 'group_load_filter';
		acf_add_local_field_group( $this->make_group( $group_key ) );

		$groups = acf_get_field_groups();
		$keys   = wp_list_pluck( $groups, 'key' );

		$this->assertContains( $group_key, $keys, 'Local group should be appended to acf_get_field_groups()' );
	}

	// =========================================================================
	// Parent/child resolution (sub fields)
	// =========================================================================

	/**
	 * Test sub fields of a repeater are extracted as children of the parent field.
	 */
	public function test_sub_fields_attach_to_parent_field() {
		$group_key    = 'group_parent_child';
		$repeater_key = 'field_local_repeater';
		$sub_key      = 'field_local_sub_text';

		acf_add_local_field_group(
			$this->make_group(
				$group_key,
				array(
					array(
						'key'        => $repeater_key,
						'label'      => 'Items',
						'name'       => 'items',
						'type'       => 'repeater',
						'sub_fields' => array(
							array(
								'key'   => $sub_key,
								'label' => 'Detail',
								'name'  => 'detail',
								'type'  => 'text',
							),
						),
					),
				)
			)
		);

		// Both the parent and child should be individually registered.
		$this->assertTrue( acf_is_local_field( $repeater_key ), 'Repeater should be registered' );
		$this->assertTrue( acf_is_local_field( $sub_key ), 'Sub field should be registered' );

		// Child resolution.
		$children = acf_get_local_fields( $repeater_key );
		$this->assertCount( 1, $children, 'Repeater should have one child' );
		$children = array_values( $children );
		$this->assertSame( $sub_key, $children[0]['key'], 'Child key should match' );
		$this->assertSame( $repeater_key, $children[0]['parent'], 'Child parent should be the repeater key' );

		// acf_get_fields() should re-attach sub fields to the parent.
		$fields = acf_get_fields( $group_key );
		$this->assertCount( 1, $fields, 'Group should expose only the top-level repeater' );
		$this->assertArrayHasKey( 'sub_fields', $fields[0], 'Repeater should have sub_fields re-attached' );
		$this->assertCount( 1, $fields[0]['sub_fields'], 'Repeater should have one sub field' );
		$this->assertSame( 'detail', $fields[0]['sub_fields'][0]['name'], 'Sub field name should match' );
	}

	/**
	 * Test the same field key can be reused under a different parent (clone/key collision).
	 */
	public function test_same_field_key_under_different_parents() {
		$shared_key = 'field_shared_key';

		acf_add_local_field(
			array(
				'key'    => $shared_key,
				'name'   => 'shared_a',
				'type'   => 'text',
				'parent' => 'group_parent_a',
			)
		);

		// Same key, different parent: should be stored under a composite key.
		acf_add_local_field(
			array(
				'key'    => $shared_key,
				'name'   => 'shared_b',
				'type'   => 'text',
				'parent' => 'group_parent_b',
			)
		);

		// Original registration must not be overwritten.
		$original = acf_get_local_field( $shared_key );
		$this->assertSame( 'group_parent_a', $original['parent'], 'Original parent should be preserved' );

		// The second copy is addressable via the composite "key:parent" identifier.
		$composite = acf_get_local_field( $shared_key . ':group_parent_b' );
		$this->assertIsArray( $composite, 'Composite-keyed copy should exist' );
		$this->assertSame( 'group_parent_b', $composite['parent'], 'Composite copy should keep the second parent' );

		// Each parent resolves its own child.
		$this->assertSame( 1, acf_count_local_fields( 'group_parent_a' ), 'Parent A should have one child' );
		$this->assertSame( 1, acf_count_local_fields( 'group_parent_b' ), 'Parent B should have one child' );
	}

	/**
	 * Test re-adding the identical field (same key and parent) overwrites rather than duplicates.
	 */
	public function test_same_field_key_same_parent_overwrites() {
		$field = array(
			'key'    => 'field_overwrite_me',
			'name'   => 'overwrite_me',
			'type'   => 'text',
			'parent' => 'group_overwrite',
		);

		acf_add_local_field( $field );

		$field['label'] = 'Updated Label';
		acf_add_local_field( $field );

		$stored = acf_get_local_field( 'field_overwrite_me' );
		$this->assertSame( 'Updated Label', $stored['label'], 'Second registration should overwrite the first' );
		$this->assertSame( 1, acf_count_local_fields( 'group_overwrite' ), 'Parent should still have a single child' );
	}

	// =========================================================================
	// Removal
	// =========================================================================

	/**
	 * Test acf_remove_local_field removes the field and its alias.
	 */
	public function test_remove_local_field() {
		acf_add_local_field(
			array(
				'key'    => 'field_to_remove',
				'name'   => 'to_remove',
				'type'   => 'text',
				'parent' => 'group_removal',
			)
		);

		$this->assertTrue( acf_is_local_field( 'field_to_remove' ), 'Field should exist before removal' );

		acf_remove_local_field( 'field_to_remove' );

		$this->assertFalse( acf_is_local_field( 'field_to_remove' ), 'Field should be gone by key' );
		$this->assertFalse( acf_is_local_field( 'to_remove' ), 'Field should be gone by name' );
		$this->assertNull( acf_get_local_field( 'field_to_remove' ), 'Lookup should return null' );
	}

	/**
	 * Test acf_remove_local_field_group removes the group.
	 */
	public function test_remove_local_field_group() {
		$key = 'group_to_remove';
		acf_add_local_field_group( $this->make_group( $key ) );

		$this->assertTrue( acf_is_local_field_group( $key ), 'Group should exist before removal' );

		acf_remove_local_field_group( $key );

		$this->assertFalse( acf_is_local_field_group( $key ), 'Group should be removed' );
		$this->assertNull( acf_get_local_field_group( $key ), 'Lookup should return null' );
	}

	/**
	 * Test removing a field group does not remove its registered fields.
	 *
	 * NOTE: documents current behavior — possible bug: acf_remove_local_field_group()
	 * only removes the group from the local store, leaving its (now orphaned) fields
	 * registered in the local-fields store.
	 */
	public function test_remove_local_field_group_leaves_fields_registered() {
		acf_add_local_field_group(
			$this->make_group(
				'group_orphan_test',
				array(
					array(
						'key'   => 'field_orphan',
						'label' => 'Orphan',
						'name'  => 'orphan',
						'type'  => 'text',
					),
				)
			)
		);

		acf_remove_local_field_group( 'group_orphan_test' );

		$this->assertTrue( acf_is_local_field( 'field_orphan' ), 'Fields remain after their group is removed' );
	}

	// =========================================================================
	// Enable/disable
	// =========================================================================

	/**
	 * Test acf_is_local_enabled default state and toggling via acf_disable_local/acf_enable_local.
	 */
	public function test_local_enable_disable_toggling() {
		$this->assertTrue( acf_is_local_enabled(), 'Local should be enabled by default' );

		acf_disable_local();
		$this->assertFalse( acf_is_local_enabled(), 'Local should be disabled after acf_disable_local()' );

		acf_enable_local();
		$this->assertTrue( acf_is_local_enabled(), 'Local should be enabled after acf_enable_local()' );
	}

	/**
	 * Test the acf/settings/local filter disables the local layer.
	 */
	public function test_local_setting_filter_disables_local() {
		add_filter( 'acf/settings/local', '__return_false' );

		$this->assertFalse( acf_is_local_enabled(), 'Local should be disabled via the settings filter' );

		// While disabled, registrations are routed to the dummy store.
		acf_add_local_field_group( $this->make_group( 'group_while_disabled' ) );

		remove_filter( 'acf/settings/local', '__return_false' );

		$this->assertFalse(
			acf_is_local_field_group( 'group_while_disabled' ),
			'Groups registered while disabled should not appear in the real local store'
		);

		// Clean the dummy store which received the write.
		acf_get_store( 'local-empty' )->reset();
	}

	/**
	 * Test acf_get_local_store returns the dummy store when disabled or unnamed.
	 */
	public function test_get_local_store_returns_dummy_store() {
		$empty = acf_get_store( 'local-empty' );

		// No name provided.
		$this->assertSame( $empty, acf_get_local_store(), 'No name should return the dummy store' );

		// Disabled.
		acf_disable_local();
		$this->assertSame( $empty, acf_get_local_store( 'fields' ), 'Disabled local should return the dummy store' );
		acf_enable_local();

		// Enabled and named.
		$this->assertSame(
			acf_get_store( 'local-fields' ),
			acf_get_local_store( 'fields' ),
			'Enabled local should return the real store'
		);

		// Post type routing.
		$this->assertSame(
			acf_get_store( 'local-groups' ),
			acf_get_local_store( '', 'acf-field-group' ),
			'acf-field-group post type should route to the groups store'
		);
	}

	// =========================================================================
	// Internal post types (post types / taxonomies)
	// =========================================================================

	/**
	 * Test acf_add_local_internal_post_type registers and deduplicates.
	 */
	public function test_add_local_internal_post_type() {
		$key  = 'post_type_local_test';
		$post = array(
			'key'                    => $key,
			'title'                  => 'Local Post Type',
			'post_type'              => 'local_test',
			'advanced_configuration' => false,
		);

		$this->assertTrue( acf_add_local_internal_post_type( $post, 'acf-post-type' ), 'First registration should succeed' );
		$this->assertFalse( acf_add_local_internal_post_type( $post, 'acf-post-type' ), 'Duplicate registration should fail' );

		$this->assertTrue( acf_is_local_internal_post_type( $key, 'acf-post-type' ), 'Post type should exist' );
		$this->assertTrue( acf_is_local_internal_post_type_key( $key, 'acf-post-type' ), 'Key should be recognized' );

		$stored = acf_get_local_internal_post_type( $key, 'acf-post-type' );
		$this->assertIsArray( $stored, 'Should return the stored post type' );
		$this->assertSame( 'json', $stored['local'], 'Local source should default to json' );

		$all = acf_get_local_internal_posts( 'acf-post-type' );
		$this->assertCount( 1, $all, 'Should list the registered post type' );

		acf_remove_local_internal_post_type( $key, 'acf-post-type' );
		$this->assertFalse( acf_is_local_internal_post_type( $key, 'acf-post-type' ), 'Post type should be removed' );
	}
}
