<?php
/**
 * Tests for the SCF block bindings source in includes/Blocks/Bindings.php.
 *
 * Covers registration of the acf/field binding source, value resolution for
 * text-ish fields bound to post meta, attribute-specific handling (url, rel,
 * id/alt/title), JSON encoding of array values, unsupported field types,
 * the allow_in_bindings field setting and the binding value filter,
 * including a full do_blocks() render of a bound paragraph block.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;
use SCF\Blocks\Bindings;

/**
 * Test the block bindings source.
 */
class Test_Blocks_Bindings extends BaseTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Whether this test registered the acf/field binding source.
	 *
	 * @var bool
	 */
	private $registered_source = false;

	/**
	 * Original enable_block_bindings setting.
	 *
	 * @var bool
	 */
	private $original_setting;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		acf_init();
		$this->ensure_field_type_filters();

		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		$this->original_setting = acf_get_setting( 'enable_block_bindings' );

		$this->post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Bindings Test Post',
				'post_status' => 'publish',
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_blocks_bindings',
				'title'    => 'Bindings Test Fields',
				'fields'   => array(
					array(
						'key'   => 'field_bindings_text',
						'name'  => 'bindings_text',
						'label' => 'Text',
						'type'  => 'text',
					),
					array(
						'key'      => 'field_bindings_select',
						'name'     => 'bindings_select',
						'label'    => 'Select',
						'type'     => 'select',
						'multiple' => 1,
						'choices'  => array(
							'a' => 'Alpha',
							'b' => 'Beta',
						),
					),
					array(
						'key'               => 'field_bindings_private',
						'name'              => 'bindings_private',
						'label'             => 'Private Text',
						'type'              => 'text',
						'allow_in_bindings' => false,
					),
					array(
						'key'           => 'field_bindings_relationship',
						'name'          => 'bindings_relationship',
						'label'         => 'Relationship',
						'type'          => 'relationship',
						'post_type'     => array( 'post' ),
						'return_format' => 'object',
					),
					array(
						'key'        => 'field_bindings_group',
						'name'       => 'bindings_group',
						'label'      => 'Group',
						'type'       => 'group',
						'sub_fields' => array(
							array(
								'key'   => 'field_bindings_group_inner',
								'name'  => 'inner',
								'label' => 'Inner',
								'type'  => 'text',
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

		// Make the global post available so get_field_object() can resolve
		// the post ID the same way it would inside a rendered post.
		$GLOBALS['post'] = get_post( $this->post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		acf_get_store( 'values' )->reset();
	}

	/**
	 * Clean up test data.
	 */
	public function tear_down() {
		acf_update_setting( 'enable_block_bindings', $this->original_setting );

		if ( $this->registered_source ) {
			$this->unregister_source();
			$this->registered_source = false;
		}

		acf_remove_local_field_group( 'group_blocks_bindings' );
		foreach ( array( 'field_bindings_text', 'field_bindings_select', 'field_bindings_private', 'field_bindings_relationship', 'field_bindings_group', 'field_bindings_group_inner' ) as $key ) {
			acf_remove_local_field( $key );
		}

		acf_get_store( 'values' )->reset();
		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * SCF registers field type hooks lazily via acf_init(), but the WorDBless
	 * base test case restores all hooks after each test to a snapshot taken
	 * before acf_init() ever ran. Re-register the field types these tests
	 * format values with so their filters exist.
	 */
	private function ensure_field_type_filters() {
		if ( has_filter( 'acf/format_value/type=select' ) && has_filter( 'acf/format_value/type=relationship' ) ) {
			return;
		}

		foreach ( array( 'select', 'group', 'relationship' ) as $type ) {
			$instance = acf_get_field_type( $type );
			if ( $instance instanceof acf_field ) {
				acf_register_field_type( get_class( $instance ) );
			}
		}
	}

	/**
	 * Expands the Relationship field to post objects. Its own query needs a database,
	 * which WorDBless does not provide.
	 */
	private function expand_relationship_without_query() {
		add_filter(
			'acf/format_value/key=field_bindings_relationship',
			static function ( $value, $post_id ) {
				return array_map( 'get_post', (array) get_post_meta( $post_id, 'bindings_relationship', true ) );
			},
			20,
			2
		);
	}

	/**
	 * Registers the acf/field binding source like acf/init would.
	 *
	 * @return Bindings The bindings instance.
	 */
	private function register_source() {
		$bindings = new Bindings();
		$bindings->register_binding_sources();
		$this->registered_source = true;
		return $bindings;
	}

	/**
	 * Removes the acf/field binding source from the WP registry.
	 */
	private function unregister_source() {
		$registry = WP_Block_Bindings_Registry::get_instance();
		if ( $registry->get_registered( 'acf/field' ) ) {
			$registry->unregister( 'acf/field' );
		}
	}

	/**
	 * Builds a WP_Block instance carrying post context.
	 *
	 * @return WP_Block
	 */
	private function get_block_instance() {
		return new WP_Block(
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'postId'   => $this->post_id,
				'postType' => 'post',
			)
		);
	}

	/**
	 * Test that the acf/field source is registered when the setting is on.
	 */
	public function test_register_binding_sources() {
		$this->unregister_source();

		$this->register_source();

		$source = WP_Block_Bindings_Registry::get_instance()->get_registered( 'acf/field' );
		$this->assertNotNull( $source );
		$this->assertSame( 'Custom Fields', $source->label );
		$this->assertSame( array( 'postId', 'postType' ), $source->uses_context );
	}

	/**
	 * Test that the source is not registered when the setting is disabled.
	 */
	public function test_register_binding_sources_respects_setting() {
		$this->unregister_source();

		acf_update_setting( 'enable_block_bindings', false );
		$bindings = new Bindings();
		$bindings->register_binding_sources();

		$this->assertNull( WP_Block_Bindings_Registry::get_instance()->get_registered( 'acf/field' ) );
	}

	/**
	 * Test resolving a text field value from post meta.
	 */
	public function test_get_value_resolves_text_field() {
		update_field( 'bindings_text', 'Bound value', $this->post_id );
		acf_get_store( 'values' )->reset();

		$bindings = $this->register_source();
		$block    = $this->get_block_instance();

		// By field key.
		$this->assertSame(
			'Bound value',
			$bindings->get_value( array( 'key' => 'field_bindings_text' ), $block, 'content' )
		);

		// By field name.
		$this->assertSame(
			'Bound value',
			$bindings->get_value( array( 'key' => 'bindings_text' ), $block, 'content' )
		);
	}

	/**
	 * A bound reference to content the viewer cannot read is reduced to IDs.
	 */
	public function test_get_value_reduces_references_the_viewer_cannot_read() {
		$protected = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_title'    => 'Protected target',
				'post_content'  => 'Protected body',
				'post_status'   => 'publish',
				'post_password' => 'bindings-secret',
			)
		);
		$private   = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Private target',
				'post_content' => 'Private body',
				'post_status'  => 'private',
			)
		);
		update_field( 'bindings_relationship', array( $protected, $private ), $this->post_id );
		acf_get_store( 'values' )->reset();
		$this->expand_relationship_without_query();
		wp_set_current_user( 0 );

		$bindings = $this->register_source();
		$value    = $bindings->get_value( array( 'key' => 'field_bindings_relationship' ), $this->get_block_instance(), 'content' );

		$this->assertStringNotContainsString( 'bindings-secret', $value );
		$this->assertStringNotContainsString( 'Protected body', $value );
		$this->assertStringNotContainsString( 'Private body', $value );
		$this->assertSame( array( $protected, $private ), json_decode( $value, true ) );
	}

	/**
	 * A bound reference to a password-protected post is expanded once the password is entered.
	 */
	public function test_get_value_expands_unlocked_password_protected_references() {
		$protected = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_title'    => 'Protected target',
				'post_content'  => 'Protected body',
				'post_status'   => 'publish',
				'post_password' => 'bindings-secret',
			)
		);
		update_field( 'bindings_relationship', array( $protected ), $this->post_id );
		acf_get_store( 'values' )->reset();
		$this->expand_relationship_without_query();
		wp_set_current_user( 0 );

		$bindings = $this->register_source();
		$block    = $this->get_block_instance();

		add_filter( 'post_password_required', '__return_false' );
		$value = $bindings->get_value( array( 'key' => 'field_bindings_relationship' ), $block, 'content' );
		remove_filter( 'post_password_required', '__return_false' );

		$this->assertStringContainsString( 'Protected body', $value );
		$this->assertStringNotContainsString( 'bindings-secret', $value );
	}

	/**
	 * Bindings expose referenced content by front-end readability, not REST visibility.
	 */
	public function test_expose_reference_target_uses_front_end_readability() {
		wp_set_current_user( 0 );
		$bindings = $this->register_source();

		$draft      = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Draft parent',
				'post_status' => 'draft',
			)
		);
		$attachment = wp_insert_post(
			array(
				'post_type'      => 'attachment',
				'post_title'     => 'Image of a draft',
				'post_status'    => 'inherit',
				'post_parent'    => $draft,
				'post_mime_type' => 'image/png',
			)
		);
		$private    = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Private post',
				'post_status' => 'private',
			)
		);

		$this->assertTrue( $bindings->expose_reference_target( false, $attachment ), 'Attachments are public files' );
		$this->assertTrue( $bindings->expose_reference_target( false, $this->post_id ), 'Published posts' );
		$this->assertFalse( $bindings->expose_reference_target( true, $draft ), 'Drafts' );
		$this->assertFalse( $bindings->expose_reference_target( true, $private ), 'Private posts' );
		$this->assertFalse( $bindings->expose_reference_target( true, 999999 ), 'Missing posts' );

		$editor = wp_insert_user(
			array(
				'user_login' => 'bindings-editor',
				'user_pass'  => 'password',
				'role'       => 'editor',
			)
		);
		wp_set_current_user( $editor );
		$this->assertTrue( $bindings->expose_reference_target( false, $private ), 'Editors read private posts' );
		wp_set_current_user( 0 );
	}

	/**
	 * A bound reference to published content is still expanded.
	 */
	public function test_get_value_keeps_readable_references() {
		$public = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Public target',
				'post_content' => 'Public body',
				'post_status'  => 'publish',
			)
		);
		update_field( 'bindings_relationship', array( $public ), $this->post_id );
		acf_get_store( 'values' )->reset();
		$this->expand_relationship_without_query();
		wp_set_current_user( 0 );

		$bindings = $this->register_source();
		$value    = $bindings->get_value( array( 'key' => 'field_bindings_relationship' ), $this->get_block_instance(), 'content' );

		$this->assertStringContainsString( 'Public target', $value );

		// Callbacks written for REST output do not decide what a rendered block shows.
		add_filter( 'acf/rest/expose_reference_target', '__return_false' );
		acf_get_store( 'values' )->reset();
		$value = $bindings->get_value( array( 'key' => 'field_bindings_relationship' ), $this->get_block_instance(), 'content' );
		remove_filter( 'acf/rest/expose_reference_target', '__return_false' );

		$this->assertStringContainsString( 'Public target', $value );
	}

	/**
	 * Field values of a password-protected post stay hidden until the password is entered.
	 */
	public function test_get_value_hides_values_of_password_protected_posts() {
		update_field( 'bindings_text', 'Locked value', $this->post_id );
		wp_update_post(
			array(
				'ID'            => $this->post_id,
				'post_password' => 'bindings-secret',
			)
		);
		$GLOBALS['post'] = get_post( $this->post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		acf_get_store( 'values' )->reset();

		$bindings = $this->register_source();
		$block    = $this->get_block_instance();

		$this->assertSame( '', $bindings->get_value( array( 'key' => 'field_bindings_text' ), $block, 'content' ) );

		add_filter( 'post_password_required', '__return_false' );
		$unlocked = $bindings->get_value( array( 'key' => 'field_bindings_text' ), $block, 'content' );
		remove_filter( 'post_password_required', '__return_false' );

		$this->assertSame( 'Locked value', $unlocked );
	}

	/**
	 * Test missing or invalid source attributes.
	 */
	public function test_get_value_handles_missing_or_unknown_keys() {
		$bindings = $this->register_source();
		$block    = $this->get_block_instance();

		// No key at all.
		$this->assertSame( '', $bindings->get_value( array(), $block, 'content' ) );

		// Non-string key.
		$this->assertSame( '', $bindings->get_value( array( 'key' => array( 'nope' ) ), $block, 'content' ) );

		// Unknown field.
		$this->assertSame( '', $bindings->get_value( array( 'key' => 'field_does_not_exist' ), $block, 'content' ) );
	}

	/**
	 * Test array values are JSON encoded for generic attributes and joined for rel.
	 */
	public function test_get_value_array_handling() {
		update_field( 'bindings_select', array( 'a', 'b' ), $this->post_id );
		acf_get_store( 'values' )->reset();

		$bindings = $this->register_source();
		$block    = $this->get_block_instance();

		// Generic attributes receive a JSON encoded value.
		$this->assertSame(
			'["a","b"]',
			$bindings->get_value( array( 'key' => 'field_bindings_select' ), $block, 'content' )
		);

		// The rel attribute joins array values with spaces.
		$this->assertSame(
			'a b',
			$bindings->get_value( array( 'key' => 'field_bindings_select' ), $block, 'rel' )
		);
	}

	/**
	 * Test attribute-specific handling for scalar values.
	 */
	public function test_get_value_attribute_specific_handling() {
		update_field( 'bindings_text', 'https://example.com/image.png', $this->post_id );
		acf_get_store( 'values' )->reset();

		$bindings = $this->register_source();
		$block    = $this->get_block_instance();

		// The url attribute falls back to the raw value for scalar fields.
		$this->assertSame(
			'https://example.com/image.png',
			$bindings->get_value( array( 'key' => 'field_bindings_text' ), $block, 'url' )
		);

		// id/alt/title come from same-named keys, so scalars resolve to ''.
		foreach ( array( 'id', 'alt', 'title' ) as $attribute ) {
			$this->assertSame(
				'',
				$bindings->get_value( array( 'key' => 'field_bindings_text' ), $block, $attribute )
			);
		}
	}

	/**
	 * Test that fields with allow_in_bindings disabled return nothing on the front end.
	 */
	public function test_get_value_respects_allow_in_bindings() {
		update_field( 'bindings_private', 'Secret', $this->post_id );
		acf_get_store( 'values' )->reset();

		$bindings = $this->register_source();
		$block    = $this->get_block_instance();

		$this->assertSame(
			'',
			$bindings->get_value( array( 'key' => 'field_bindings_private' ), $block, 'content' )
		);
	}

	/**
	 * Test that field types without bindings support return nothing on the front end.
	 */
	public function test_get_value_unsupported_field_type() {
		$bindings = $this->register_source();
		$block    = $this->get_block_instance();

		// The group field type declares supports[bindings] = false.
		$this->assertFalse( acf_field_type_supports( 'group', 'bindings', true ) );
		$this->assertSame(
			'',
			$bindings->get_value( array( 'key' => 'field_bindings_group' ), $block, 'content' )
		);
	}

	/**
	 * Test that the binding value can be filtered.
	 */
	public function test_get_value_applies_binding_value_filter() {
		update_field( 'bindings_text', 'Original', $this->post_id );
		acf_get_store( 'values' )->reset();

		$bindings = $this->register_source();
		$block    = $this->get_block_instance();

		add_filter(
			'acf/blocks/binding_value',
			function ( $value, $source_attrs, $block_instance, $attribute_name ) {
				return $value . '|' . $source_attrs['key'] . '|' . $attribute_name;
			},
			10,
			4
		);

		$this->assertSame(
			'Original|field_bindings_text|content',
			$bindings->get_value( array( 'key' => 'field_bindings_text' ), $block, 'content' )
		);
	}

	/**
	 * Test a full render of a bound paragraph block through do_blocks().
	 */
	public function test_do_blocks_renders_bound_paragraph() {
		update_field( 'bindings_text', 'Rendered via binding', $this->post_id );
		acf_get_store( 'values' )->reset();

		$this->register_source();

		$content = '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"acf/field","args":{"key":"bindings_text"}}}}} --><p>placeholder</p><!-- /wp:paragraph -->';

		$html = do_blocks( $content );

		$this->assertStringContainsString( 'Rendered via binding', $html );
		$this->assertStringNotContainsString( 'placeholder', $html );
	}
}
