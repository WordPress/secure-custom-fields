<?php
/**
 * Tests for the public template API in includes/api/api-template.php.
 *
 * Covers get_field(), the_field(), get_field_object(), get_fields(),
 * get_field_objects(), update_field(), delete_field() and the escaped
 * HTML log helpers across post, option and user contexts.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test the template API functions.
 */
class Test_API_Template extends BaseTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->ensure_field_type_filters();

		$this->post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Template API Test Post',
				'post_status' => 'publish',
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_tmpl_api',
				'title'    => 'Template API Test Fields',
				'fields'   => array(
					array(
						'key'           => 'field_tmpl_text',
						'name'          => 'tmpl_text',
						'label'         => 'Text',
						'type'          => 'text',
						'default_value' => '',
					),
					array(
						'key'           => 'field_tmpl_text_default',
						'name'          => 'tmpl_text_default',
						'label'         => 'Text With Default',
						'type'          => 'text',
						'default_value' => 'fallback',
					),
					array(
						'key'       => 'field_tmpl_textarea',
						'name'      => 'tmpl_textarea',
						'label'     => 'Textarea',
						'type'      => 'textarea',
						'new_lines' => 'br',
					),
					array(
						'key'      => 'field_tmpl_select',
						'name'     => 'tmpl_select',
						'label'    => 'Select',
						'type'     => 'select',
						'multiple' => 1,
						'choices'  => array(
							'a' => 'Alpha',
							'b' => 'Beta',
						),
					),
					array(
						'key'        => 'field_tmpl_group',
						'name'       => 'tmpl_group',
						'label'      => 'Group',
						'type'       => 'group',
						'sub_fields' => array(
							array(
								'key'   => 'field_tmpl_group_inner',
								'name'  => 'inner',
								'label' => 'Inner',
								'type'  => 'text',
							),
						),
					),
				),
				'location' => array(),
			)
		);

		acf_get_store( 'values' )->reset();
		acf()->loop->loops = array();
	}

	/**
	 * Clean up test data.
	 */
	public function tear_down() {
		acf_get_store( 'values' )->reset();
		acf()->loop->loops = array();
		parent::tear_down();
	}

	/**
	 * SCF registers field type hooks lazily via acf_init(), but the WorDBless
	 * base test case restores all hooks after each test to a snapshot taken
	 * before acf_init() ever ran. Since acf_init() only runs once per process,
	 * the field type filters (e.g. acf/format_value/type=textarea) would be
	 * missing for every test after the first. Re-instantiate the field types
	 * used by these tests to restore their hooks.
	 */
	private function ensure_field_type_filters() {
		acf_init();

		if ( has_filter( 'acf/format_value/type=textarea' ) ) {
			return;
		}

		foreach ( array( 'textarea', 'select', 'group', 'repeater', 'flexible_content' ) as $type ) {
			$instance = acf_get_field_type( $type );
			if ( $instance instanceof acf_field ) {
				acf_register_field_type( get_class( $instance ) );
			}
		}
	}

	/**
	 * WorDBless does not implement the "fetch all meta" form of get_metadata(),
	 * which acf_get_meta() relies on. This shim serves all-key requests from the
	 * real WorDBless post meta store so get_fields()/get_field_objects() can be
	 * integration tested against genuinely saved meta.
	 */
	private function enable_all_meta_lookups() {
		add_filter(
			'get_post_metadata',
			function ( $check, $object_id, $meta_key ) {
				if ( '' !== $meta_key ) {
					return $check;
				}

				$store = \WorDBless\PostMeta::init();
				$all   = array();

				if ( isset( $store->meta[ $object_id ] ) ) {
					foreach ( $store->meta[ $object_id ] as $meta ) {
						$all[ $meta['meta_key'] ][] = $meta['meta_value'];
					}
				}

				return $all;
			},
			20,
			3
		);
	}

	// =========================================================================
	// get_field()
	// =========================================================================

	/**
	 * Test get_field returns a saved value when queried by field key.
	 */
	public function test_get_field_returns_value_by_field_key() {
		update_field( 'field_tmpl_text', 'hello world', $this->post_id );
		acf_get_store( 'values' )->reset();

		$this->assertSame( 'hello world', get_field( 'field_tmpl_text', $this->post_id ) );
	}

	/**
	 * Test get_field returns a saved value when queried by field name.
	 */
	public function test_get_field_returns_value_by_field_name() {
		update_field( 'field_tmpl_text', 'hello world', $this->post_id );
		acf_get_store( 'values' )->reset();

		$this->assertSame( 'hello world', get_field( 'tmpl_text', $this->post_id ) );
	}

	/**
	 * Test get_field returns null for a non-existent field with no meta.
	 */
	public function test_get_field_returns_null_for_nonexistent_field() {
		$this->assertNull( get_field( 'completely_unknown_field', $this->post_id ) );
	}

	/**
	 * Test get_field returns the registered default value when no value is saved.
	 */
	public function test_get_field_returns_default_when_unset() {
		$this->assertSame( 'fallback', get_field( 'field_tmpl_text_default', $this->post_id ) );
		$this->assertSame( 'fallback', get_field( 'field_tmpl_text_default', $this->post_id, false ) );
	}

	/**
	 * Test get_field applies field type formatting when format_value is true.
	 */
	public function test_get_field_format_value_true_applies_formatting() {
		update_field( 'field_tmpl_textarea', "line1\nline2", $this->post_id );
		acf_get_store( 'values' )->reset();

		$this->assertSame( nl2br( "line1\nline2" ), get_field( 'tmpl_textarea', $this->post_id, true ) );
	}

	/**
	 * Test get_field returns the raw database value when format_value is false.
	 */
	public function test_get_field_format_value_false_returns_raw_value() {
		update_field( 'field_tmpl_textarea', "line1\nline2", $this->post_id );
		acf_get_store( 'values' )->reset();

		$this->assertSame( "line1\nline2", get_field( 'tmpl_textarea', $this->post_id, false ) );
	}

	/**
	 * Test get_field returns false when escape_html is requested without format_value.
	 */
	public function test_get_field_escape_html_without_format_value_returns_false() {
		update_field( 'field_tmpl_text', 'value', $this->post_id );
		acf_get_store( 'values' )->reset();

		$this->assertFalse( get_field( 'tmpl_text', $this->post_id, false, true ) );
	}

	/**
	 * Test get_field escapes unsafe HTML when escape_html is true.
	 */
	public function test_get_field_escape_html_escapes_unsafe_html() {
		update_field( 'field_tmpl_text', '<script>alert(1)</script>safe', $this->post_id );
		acf_get_store( 'values' )->reset();

		$value = get_field( 'tmpl_text', $this->post_id, true, true );

		$this->assertStringNotContainsString( '<script>', $value );
		$this->assertStringContainsString( 'safe', $value );
	}

	/**
	 * Test get_field returns raw meta for an unregistered field name saved via update_field.
	 */
	public function test_get_field_returns_raw_meta_for_unregistered_field() {
		update_field( 'free_form_meta', 'raw-stored', $this->post_id );
		acf_get_store( 'values' )->reset();

		// A dummy field is built internally; the raw value is returned unformatted.
		$this->assertSame( 'raw-stored', get_field( 'free_form_meta', $this->post_id ) );
	}

	/**
	 * Test get_field formatted multiple select returns an array of values.
	 */
	public function test_get_field_select_multiple_returns_array() {
		update_field( 'field_tmpl_select', array( 'a', 'b' ), $this->post_id );
		acf_get_store( 'values' )->reset();

		$this->assertSame( array( 'a', 'b' ), get_field( 'tmpl_select', $this->post_id ) );
	}

	// =========================================================================
	// the_field()
	// =========================================================================

	/**
	 * Test the_field echoes the saved value.
	 */
	public function test_the_field_echoes_value() {
		update_field( 'field_tmpl_text', 'echoed value', $this->post_id );
		acf_get_store( 'values' )->reset();

		ob_start();
		the_field( 'tmpl_text', $this->post_id );
		$output = ob_get_clean();

		$this->assertSame( 'echoed value', $output );
	}

	/**
	 * Test the_field strips unsafe HTML from output.
	 */
	public function test_the_field_escapes_unsafe_html() {
		update_field( 'field_tmpl_text', '<script>bad()</script>good', $this->post_id );
		acf_get_store( 'values' )->reset();

		ob_start();
		the_field( 'tmpl_text', $this->post_id );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( 'good', $output );
	}

	/**
	 * Test the_field fires the removed_unsafe_html action when HTML is stripped.
	 */
	public function test_the_field_fires_removed_unsafe_html_action() {
		update_field( 'field_tmpl_text', '<script>bad()</script>good', $this->post_id );
		acf_get_store( 'values' )->reset();

		$fired = 0;
		add_action(
			'acf/removed_unsafe_html',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		ob_start();
		the_field( 'tmpl_text', $this->post_id );
		ob_get_clean();

		$this->assertGreaterThan( 0, $fired );
	}

	/**
	 * Test the_field implodes array values into a comma separated string.
	 */
	public function test_the_field_implodes_array_values() {
		update_field( 'field_tmpl_select', array( 'a', 'b' ), $this->post_id );
		acf_get_store( 'values' )->reset();

		ob_start();
		the_field( 'tmpl_select', $this->post_id );
		$output = ob_get_clean();

		$this->assertSame( 'a, b', $output );
	}

	/**
	 * Test the_field outputs nothing for a non-existent field.
	 */
	public function test_the_field_outputs_nothing_for_nonexistent_field() {
		ob_start();
		the_field( 'completely_unknown_field', $this->post_id );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Test the_field allows unsafe HTML when the allow_unsafe_html filter is used.
	 */
	public function test_the_field_allow_unsafe_html_filter() {
		update_field( 'field_tmpl_text', '<em>emphasis</em>', $this->post_id );
		acf_get_store( 'values' )->reset();

		add_filter( 'acf/the_field/allow_unsafe_html', '__return_true' );

		ob_start();
		the_field( 'tmpl_text', $this->post_id );
		$output = ob_get_clean();

		$this->assertSame( '<em>emphasis</em>', $output );
	}

	// =========================================================================
	// get_field_object()
	// =========================================================================

	/**
	 * Test get_field_object returns the field array including the loaded value.
	 */
	public function test_get_field_object_returns_field_with_value() {
		update_field( 'field_tmpl_text', 'object value', $this->post_id );
		acf_get_store( 'values' )->reset();

		$field = get_field_object( 'tmpl_text', $this->post_id );

		$this->assertIsArray( $field );
		$this->assertSame( 'field_tmpl_text', $field['key'] );
		$this->assertSame( 'tmpl_text', $field['name'] );
		$this->assertSame( 'text', $field['type'] );
		$this->assertSame( 'object value', $field['value'] );
	}

	/**
	 * Test get_field_object works with a field key even when no value is saved.
	 */
	public function test_get_field_object_by_key_without_saved_value() {
		$field = get_field_object( 'field_tmpl_text_default', $this->post_id );

		$this->assertIsArray( $field );
		$this->assertSame( 'tmpl_text_default', $field['name'] );
		$this->assertSame( 'fallback', $field['value'] );
	}

	/**
	 * Test get_field_object returns false for an unknown selector.
	 */
	public function test_get_field_object_returns_false_for_unknown_field() {
		$this->assertFalse( get_field_object( 'completely_unknown_field', $this->post_id ) );
	}

	/**
	 * Test get_field_object does not load the saved value when load_value is false.
	 */
	public function test_get_field_object_load_value_false_does_not_load_value() {
		update_field( 'field_tmpl_text', 'should not load', $this->post_id );
		acf_get_store( 'values' )->reset();

		$field = get_field_object( 'field_tmpl_text', $this->post_id, true, false );

		$this->assertIsArray( $field );
		// The 'value' key exists with its null default from acf_validate_field,
		// but the saved value is not loaded.
		$this->assertNull( $field['value'] );
	}

	/**
	 * Test get_field_object returns the raw value when format_value is false.
	 */
	public function test_get_field_object_format_value_false_returns_raw_value() {
		update_field( 'field_tmpl_textarea', "a\nb", $this->post_id );
		acf_get_store( 'values' )->reset();

		$field = get_field_object( 'tmpl_textarea', $this->post_id, false );

		$this->assertSame( "a\nb", $field['value'] );
	}

	/**
	 * Test get_field_object supports the legacy array form of the format_value argument.
	 */
	public function test_get_field_object_legacy_array_format_value() {
		update_field( 'field_tmpl_textarea', "a\nb", $this->post_id );
		acf_get_store( 'values' )->reset();

		$field = get_field_object( 'tmpl_textarea', $this->post_id, array( 'format_value' => false ) );

		$this->assertSame( "a\nb", $field['value'] );
	}

	/**
	 * Test get_field_object blanks the value when escape_html is used without format_value.
	 */
	public function test_get_field_object_escape_html_without_format_value_blanks_value() {
		update_field( 'field_tmpl_text', 'secret', $this->post_id );
		acf_get_store( 'values' )->reset();

		$field = get_field_object( 'tmpl_text', $this->post_id, false, true, true );

		$this->assertIsArray( $field );
		$this->assertFalse( $field['value'] );
	}

	// =========================================================================
	// get_fields() / get_field_objects()
	// =========================================================================

	/**
	 * Test get_fields returns a name => value map of all saved fields.
	 */
	public function test_get_fields_returns_name_value_map() {
		$this->enable_all_meta_lookups();

		update_field( 'field_tmpl_text', 'first', $this->post_id );
		update_field( 'field_tmpl_textarea', 'second', $this->post_id );
		acf_get_store( 'values' )->reset();

		$fields = get_fields( $this->post_id );

		$this->assertIsArray( $fields );
		$this->assertSame( 'first', $fields['tmpl_text'] );
		$this->assertSame( 'second', $fields['tmpl_textarea'] );
	}

	/**
	 * Test get_fields returns false when the post has no field values.
	 */
	public function test_get_fields_returns_false_when_no_meta() {
		$this->enable_all_meta_lookups();

		$this->assertFalse( get_fields( $this->post_id ) );
	}

	/**
	 * Test get_fields returns false when escape_html is used without format_value.
	 */
	public function test_get_fields_escape_html_without_format_value_returns_false() {
		$this->enable_all_meta_lookups();

		update_field( 'field_tmpl_text', 'value', $this->post_id );
		acf_get_store( 'values' )->reset();

		$this->assertFalse( get_fields( $this->post_id, false, true ) );
	}

	/**
	 * Test get_field_objects returns full field arrays keyed by field name.
	 */
	public function test_get_field_objects_returns_field_arrays() {
		$this->enable_all_meta_lookups();

		update_field( 'field_tmpl_text', 'object value', $this->post_id );
		acf_get_store( 'values' )->reset();

		$fields = get_field_objects( $this->post_id );

		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'tmpl_text', $fields );
		$this->assertSame( 'field_tmpl_text', $fields['tmpl_text']['key'] );
		$this->assertSame( 'object value', $fields['tmpl_text']['value'] );
	}

	/**
	 * Test get_field_objects excludes sub field meta rows from the result.
	 */
	public function test_get_field_objects_excludes_sub_fields() {
		$this->enable_all_meta_lookups();

		update_field( 'field_tmpl_group', array( 'inner' => 'nested' ), $this->post_id );
		acf_get_store( 'values' )->reset();

		$fields = get_field_objects( $this->post_id );

		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'tmpl_group', $fields );
		// The group sub field is stored as "tmpl_group_inner" meta but must not
		// be returned as a top-level field object.
		$this->assertArrayNotHasKey( 'tmpl_group_inner', $fields );
		$this->assertArrayNotHasKey( 'inner', $fields );
	}

	/**
	 * Test get_field_objects returns false for a post with no meta.
	 */
	public function test_get_field_objects_returns_false_when_no_meta() {
		$this->enable_all_meta_lookups();

		$this->assertFalse( get_field_objects( $this->post_id ) );
	}

	// =========================================================================
	// update_field() / delete_field()
	// =========================================================================

	/**
	 * Test update_field round-trips a value and creates a field reference.
	 */
	public function test_update_field_round_trip_creates_reference() {
		$result = update_field( 'field_tmpl_text', 'round trip', $this->post_id );

		$this->assertNotFalse( $result );
		$this->assertSame( 'field_tmpl_text', acf_get_reference( 'tmpl_text', $this->post_id ) );

		acf_get_store( 'values' )->reset();
		$this->assertSame( 'round trip', get_field( 'tmpl_text', $this->post_id ) );
	}

	/**
	 * Test update_field works with the field name once a reference exists.
	 */
	public function test_update_field_by_name() {
		update_field( 'field_tmpl_text', 'initial', $this->post_id );

		update_field( 'tmpl_text', 'updated by name', $this->post_id );
		acf_get_store( 'values' )->reset();

		$this->assertSame( 'updated by name', get_field( 'tmpl_text', $this->post_id ) );
	}

	/**
	 * Test update_field stores arbitrary meta for unregistered field names.
	 */
	public function test_update_field_with_unregistered_name_stores_meta() {
		update_field( 'totally_custom_key', 'custom value', $this->post_id );

		$this->assertSame( 'custom value', get_post_meta( $this->post_id, 'totally_custom_key', true ) );
	}

	/**
	 * Test delete_field removes the saved value.
	 */
	public function test_delete_field_removes_value() {
		update_field( 'field_tmpl_text', 'to be deleted', $this->post_id );

		$this->assertTrue( delete_field( 'tmpl_text', $this->post_id ) );

		acf_get_store( 'values' )->reset();
		$this->assertSame( '', get_post_meta( $this->post_id, 'tmpl_text', true ) );
	}

	/**
	 * Test deleting a field with a default value falls back to the default on read.
	 */
	public function test_delete_field_restores_default_value_on_read() {
		update_field( 'field_tmpl_text_default', 'explicit', $this->post_id );
		acf_get_store( 'values' )->reset();
		$this->assertSame( 'explicit', get_field( 'tmpl_text_default', $this->post_id ) );

		delete_field( 'tmpl_text_default', $this->post_id );
		acf_get_store( 'values' )->reset();

		// Reading by key still resolves the field, so the default is returned.
		$this->assertSame( 'fallback', get_field( 'field_tmpl_text_default', $this->post_id ) );

		// Reading by name no longer resolves the field because delete_field also
		// removed the reference meta, so null is returned instead of the default.
		acf_get_store( 'values' )->reset();
		$this->assertNull( get_field( 'tmpl_text_default', $this->post_id ) );
	}

	/**
	 * Test delete_field returns false for an unknown field.
	 */
	public function test_delete_field_returns_false_for_unknown_field() {
		$this->assertFalse( delete_field( 'completely_unknown_field', $this->post_id ) );
	}

	// =========================================================================
	// Contexts: option / options / user_{id}
	// =========================================================================

	/**
	 * Test field round trip against the options context.
	 */
	public function test_field_round_trip_options_context() {
		update_field( 'field_tmpl_text', 'option value', 'options' );
		acf_get_store( 'values' )->reset();

		$this->assertSame( 'option value', get_field( 'tmpl_text', 'options' ) );
		// 'option' is normalised to 'options'.
		$this->assertSame( 'option value', get_field( 'tmpl_text', 'option' ) );
	}

	/**
	 * Test saving via 'option' singular is readable via 'options'.
	 */
	public function test_field_saved_via_option_singular_readable_via_options() {
		update_field( 'field_tmpl_text', 'singular save', 'option' );
		acf_get_store( 'values' )->reset();

		$this->assertSame( 'singular save', get_field( 'tmpl_text', 'options' ) );
	}

	/**
	 * Test field round trip against a user_{id} context.
	 */
	public function test_field_round_trip_user_context() {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'tmpl_user_' . uniqid(),
				'user_pass'  => 'password',
				'user_email' => 'tmpl_' . uniqid() . '@example.com',
			)
		);

		update_field( 'field_tmpl_text', 'user value', "user_{$user_id}" );
		acf_get_store( 'values' )->reset();

		$this->assertSame( 'user value', get_field( 'tmpl_text', "user_{$user_id}" ) );
		$this->assertSame( 'user value', get_user_meta( $user_id, 'tmpl_text', true ) );

		$this->assertTrue( delete_field( 'tmpl_text', "user_{$user_id}" ) );
		acf_get_store( 'values' )->reset();
		$this->assertSame( '', get_user_meta( $user_id, 'tmpl_text', true ) );
	}

	/**
	 * Test the same field name holds independent values per context.
	 */
	public function test_field_values_are_isolated_per_context() {
		update_field( 'field_tmpl_text', 'post value', $this->post_id );
		update_field( 'field_tmpl_text', 'option value', 'options' );
		acf_get_store( 'values' )->reset();

		$this->assertSame( 'post value', get_field( 'tmpl_text', $this->post_id ) );
		$this->assertSame( 'option value', get_field( 'tmpl_text', 'options' ) );
	}

	// =========================================================================
	// Escaped HTML log helpers
	// =========================================================================

	/**
	 * Test the escaped HTML log update, read and delete round trip.
	 */
	public function test_escaped_html_log_round_trip() {
		$this->assertSame( array(), _acf_get_escaped_html_log() );

		$log = array(
			'field_tmpl_text' => array(
				'selector' => 'tmpl_text',
				'function' => 'the_field',
				'field'    => 'Text',
				'post_id'  => $this->post_id,
			),
		);

		$this->assertTrue( _acf_update_escaped_html_log( $log ) );
		$this->assertSame( $log, _acf_get_escaped_html_log() );

		$this->assertTrue( _acf_delete_escaped_html_log() );
		$this->assertSame( array(), _acf_get_escaped_html_log() );
	}

	/**
	 * Test the escaped HTML log returns an array when the option is corrupted.
	 */
	public function test_escaped_html_log_handles_non_array_option() {
		update_option( 'acf_escaped_html_log', 'corrupted-string', false );

		$this->assertSame( array(), _acf_get_escaped_html_log() );
	}
}
