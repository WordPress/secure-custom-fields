<?php
/**
 * Tests for block type registration and helpers in includes/blocks.php.
 *
 * Covers acf_register_block_type() validation/normalization, the block-types
 * store helpers, default attributes, block.json registration handling
 * (acf_handle_json_block_registration / acf_add_block_namespace), inline
 * field definitions, block ID generation, attribute serialization and the
 * save-time data-to-meta conversion.
 *
 * @package wordpress/secure-custom-fields
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions -- direct filesystem calls manage throwaway temp block.json fixtures.
 */

use WorDBless\BaseTestCase;

/**
 * Test block type registration and helpers.
 */
class Test_Blocks extends BaseTestCase {

	/**
	 * Block type names registered during a test.
	 *
	 * @var array
	 */
	private $block_names = array();

	/**
	 * Local field group keys registered during a test.
	 *
	 * @var array
	 */
	private $group_keys = array();

	/**
	 * Local field keys registered during a test.
	 *
	 * @var array
	 */
	private $field_keys = array();

	/**
	 * Temp paths (files or directories) created during a test.
	 *
	 * @var array
	 */
	private $temp_paths = array();

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		acf_init();

		$this->ensure_local_meta_filters();

		// _doing_it_wrong() calls would otherwise be converted into test errors.
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		acf_get_store( 'block-cache' )->reset();
		acf_get_store( 'values' )->reset();
	}

	/**
	 * The ACF_Local_Meta instance is created lazily, so its constructor
	 * filters may have been added after the WorDBless hooks snapshot was
	 * taken and stripped after a previous test. Without them, get_field()
	 * cannot read block data out of local meta.
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
	 * Clean up everything registered by the test.
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( $this->block_names as $name ) {
			acf_remove_block_type( $name );
			if ( $registry->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		$this->block_names = array();

		foreach ( $this->group_keys as $key ) {
			acf_remove_local_field_group( $key );
		}
		$this->group_keys = array();

		foreach ( $this->field_keys as $key ) {
			acf_remove_local_field( $key );
		}
		$this->field_keys = array();

		foreach ( $this->temp_paths as $path ) {
			if ( is_dir( $path ) ) {
				array_map( 'unlink', glob( rtrim( $path, '/' ) . '/*' ) );
				rmdir( $path );
			} elseif ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
		$this->temp_paths = array();

		acf_get_store( 'block-cache' )->reset();
		acf_get_store( 'block-meta-values' )->reset();
		acf_get_store( 'values' )->reset();

		parent::tear_down();
	}

	/**
	 * Registers an SCF block type and tracks it for clean-up.
	 *
	 * @param array $args The block settings.
	 * @return array|false
	 */
	private function register_block( $args ) {
		$block = acf_register_block_type( $args );
		if ( is_array( $block ) && ! empty( $block['name'] ) ) {
			$this->block_names[] = $block['name'];
		}
		return $block;
	}

	/**
	 * Tracks a local field group (and optionally its field keys) for clean-up.
	 *
	 * @param string $group_key  The field group key.
	 * @param array  $field_keys The field keys belonging to the group.
	 */
	private function track_group( $group_key, $field_keys = array() ) {
		$this->group_keys = array_merge( $this->group_keys, array( $group_key ) );
		$this->field_keys = array_merge( $this->field_keys, $field_keys );
	}

	/**
	 * Test that acf_validate_block_type() fills defaults and prefixes the name.
	 */
	public function test_validate_block_type_defaults_and_name_prefix() {
		$block = acf_validate_block_type(
			array(
				'name'  => 'My Test_Block',
				'title' => 'My Test Block',
			)
		);

		$this->assertSame( 'acf/my-test-block', $block['name'] );
		$this->assertSame( 'common', $block['category'] );
		$this->assertSame( 'preview', $block['mode'] );
		$this->assertFalse( $block['render_template'] );
		$this->assertFalse( $block['render_callback'] );
		$this->assertSame(
			array(
				'align' => true,
				'html'  => false,
				'mode'  => true,
			),
			$block['supports']
		);
		$this->assertContains( 'postId', $block['uses_context'] );
		$this->assertContains( 'postType', $block['uses_context'] );
	}

	/**
	 * Test parent setting normalization in acf_validate_block_type().
	 */
	public function test_validate_block_type_parent_normalization() {
		$null_parent = acf_validate_block_type(
			array(
				'name'   => 'parent-null',
				'parent' => null,
			)
		);
		$this->assertArrayNotHasKey( 'parent', $null_parent );

		$string_parent = acf_validate_block_type(
			array(
				'name'   => 'parent-string',
				'parent' => 'core/group',
			)
		);
		$this->assertSame( array( 'core/group' ), $string_parent['parent'] );

		$array_parent = acf_validate_block_type(
			array(
				'name'   => 'parent-array',
				'parent' => array( 'core/group', 'core/cover' ),
			)
		);
		$this->assertSame( array( 'core/group', 'core/cover' ), $array_parent['parent'] );
	}

	/**
	 * Test that the deprecated __experimental_jsx support flag maps to jsx.
	 */
	public function test_validate_block_type_experimental_jsx() {
		$block = acf_validate_block_type(
			array(
				'name'     => 'jsx-block',
				'supports' => array( '__experimental_jsx' => true ),
			)
		);

		$this->assertTrue( $block['supports']['jsx'] );
	}

	/**
	 * Test that registering a block without a name fails.
	 */
	public function test_register_block_type_requires_name() {
		$this->assertFalse( acf_register_block_type( array( 'title' => 'No Name' ) ) );
	}

	/**
	 * Test a full block type registration.
	 */
	public function test_register_block_type_registers_block() {
		$block = $this->register_block(
			array(
				'name'  => 'basic-block',
				'title' => 'Basic Block',
			)
		);

		$this->assertIsArray( $block );
		$this->assertSame( 'acf/basic-block', $block['name'] );

		// Defaults for blocks registered via PHP.
		$this->assertSame( 1, $block['acf_block_version'] );
		$this->assertSame( 2, $block['api_version'] );

		// ACF's required attributes are added.
		foreach ( array( 'name', 'data', 'align', 'mode' ) as $attribute ) {
			$this->assertArrayHasKey( $attribute, $block['attributes'] );
		}

		// The block is stored in the SCF block-types store.
		$this->assertTrue( acf_has_block_type( 'acf/basic-block' ) );
		$stored = acf_get_block_type( 'acf/basic-block' );
		$this->assertSame( 'Basic Block', $stored['title'] );
		$this->assertArrayHasKey( 'acf/basic-block', acf_get_block_types() );

		// The block is registered with WordPress using the SCF render callback.
		$wp_block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'acf/basic-block' );
		$this->assertInstanceOf( WP_Block_Type::class, $wp_block_type );
		$this->assertSame( 'acf_render_block_callback', $wp_block_type->render_callback );
		$this->assertSame( 'acf_render_block_callback', $block['render_callback'] );
	}

	/**
	 * Test that registering a duplicate block type name fails.
	 */
	public function test_register_block_type_duplicate_returns_false() {
		$this->register_block(
			array(
				'name'  => 'duped-block',
				'title' => 'Duped Block',
			)
		);

		$this->assertFalse(
			acf_register_block_type(
				array(
					'name'  => 'duped-block',
					'title' => 'Duped Block Again',
				)
			)
		);
	}

	/**
	 * Test v3 block registration defaults.
	 */
	public function test_register_block_type_v3_defaults() {
		$block = $this->register_block(
			array(
				'name'              => 'v3-block',
				'title'             => 'V3 Block',
				'acf_block_version' => 3,
			)
		);

		$this->assertSame( 3, $block['api_version'] );
		$this->assertTrue( $block['expanded_editor_buttons'] );

		// The acf_block_version is exposed on the WP block type object,
		// which is how acf_rendered_block() dispatches to the v3 renderer.
		$wp_block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'acf/v3-block' );
		$this->assertSame( 3, $wp_block_type->acf_block_version );
	}

	/**
	 * Test that inline fields on acf_register_block_type() register a local field group.
	 */
	public function test_register_block_type_inline_fields_registers_field_group() {
		$this->register_block(
			array(
				'name'   => 'inline-fields-block',
				'title'  => 'Inline Fields Block',
				'fields' => array(
					array(
						'name'  => 'inline_text',
						'label' => 'Inline Text',
						'type'  => 'text',
					),
				),
			)
		);
		$this->track_group( 'group_acf_inline_fields_block', array( 'field_acf_inline_fields_block_inline_text' ) );

		$group = acf_get_field_group( 'group_acf_inline_fields_block' );
		$this->assertIsArray( $group );
		$this->assertSame( 'Block: Inline Fields Block', $group['title'] );
		$this->assertSame( 'block', $group['location'][0][0]['param'] );
		$this->assertSame( 'acf/inline-fields-block', $group['location'][0][0]['value'] );

		$field = acf_get_field( 'field_acf_inline_fields_block_inline_text' );
		$this->assertIsArray( $field );
		$this->assertSame( 'inline_text', $field['name'] );
	}

	/**
	 * Test the block-types store helpers.
	 */
	public function test_block_type_store_helpers() {
		$this->assertFalse( acf_has_block_type( 'acf/store-block' ) );
		$this->assertNull( acf_get_block_type( 'acf/store-block' ) );

		$this->register_block(
			array(
				'name'  => 'store-block',
				'title' => 'Store Block',
			)
		);

		$this->assertTrue( acf_has_block_type( 'acf/store-block' ) );

		acf_remove_block_type( 'acf/store-block' );
		$this->assertFalse( acf_has_block_type( 'acf/store-block' ) );
	}

	/**
	 * Test default attributes, including extra supports-driven attributes.
	 */
	public function test_get_block_type_default_attributes() {
		$attributes = acf_get_block_type_default_attributes( array() );
		$this->assertSame( array( 'name', 'data', 'align', 'mode' ), array_keys( $attributes ) );
		$this->assertSame( 'object', $attributes['data']['type'] );

		$attributes = acf_get_block_type_default_attributes(
			array(
				'supports' => array(
					'alignText'    => true,
					'alignContent' => true,
					'fullHeight'   => true,
				),
			)
		);
		$this->assertArrayHasKey( 'alignText', $attributes );
		$this->assertArrayHasKey( 'alignContent', $attributes );
		$this->assertArrayHasKey( 'fullHeight', $attributes );

		// Old snake_case support keys are migrated.
		$attributes = acf_get_block_type_default_attributes(
			array(
				'supports' => array( 'align_text' => true ),
			)
		);
		$this->assertArrayHasKey( 'alignText', $attributes );

		// User-defined defaults for ACF attributes are respected.
		$attributes = acf_get_block_type_default_attributes(
			array(
				'attributes' => array(
					'mode' => array( 'default' => 'edit' ),
				),
			)
		);
		$this->assertSame( 'edit', $attributes['mode']['default'] );
	}

	/**
	 * Test backwards compatible attribute mapping.
	 */
	public function test_add_back_compat_attributes() {
		$block = acf_add_back_compat_attributes(
			array(
				'fullHeight'   => true,
				'alignText'    => 'center',
				'alignContent' => 'top',
			)
		);

		$this->assertTrue( $block['full_height'] );
		$this->assertSame( 'center', $block['align_text'] );
		$this->assertSame( 'top', $block['align_content'] );

		$this->assertSame(
			array(
				'fullHeight'   => 'full_height',
				'alignText'    => 'align_text',
				'alignContent' => 'align_content',
			),
			acf_get_block_back_compat_attribute_key_array()
		);
	}

	/**
	 * Test acf_prepare_block() behavior.
	 */
	public function test_prepare_block() {
		// No name.
		$this->assertFalse( acf_prepare_block( array( 'id' => 'abc123' ) ) );

		// Unknown block type.
		$this->assertFalse(
			acf_prepare_block(
				array(
					'name' => 'acf/unknown-block-type',
					'id'   => 'abc123',
				)
			)
		);

		$this->register_block(
			array(
				'name'            => 'prepare-block',
				'title'           => 'Prepare Block',
				'render_template' => 'real-template.php',
			)
		);

		$prepared = acf_prepare_block(
			array(
				'name'            => 'acf/prepare-block',
				'id'              => 'abc123',
				'render_template' => 'evil-template.php',
				'align'           => 'wide',
			)
		);

		// Block ID is prefixed.
		$this->assertSame( 'block_abc123', $prepared['id'] );

		// Protected attributes cannot be overridden by block attributes.
		$this->assertSame( 'real-template.php', $prepared['render_template'] );

		// Attribute defaults are merged in, with provided values winning.
		$this->assertSame( 'wide', $prepared['align'] );
		$this->assertSame( array(), $prepared['data'] );
		$this->assertSame( 'Prepare Block', $prepared['title'] );
	}

	/**
	 * Test block ID prefixing.
	 */
	public function test_ensure_block_id_prefix() {
		$this->assertSame( 'block_abc', acf_ensure_block_id_prefix( 'abc' ) );
		$this->assertSame( 'block_abc', acf_ensure_block_id_prefix( 'block_abc' ) );
	}

	/**
	 * Test block ID generation.
	 */
	public function test_get_block_id() {
		// An existing ID is returned untouched.
		$this->assertSame( 'existing', acf_get_block_id( array( 'id' => 'existing' ) ) );

		$attributes = array(
			'name' => 'acf/hash-block',
			'data' => array( 'field_x' => 'y' ),
		);

		// Generation is deterministic.
		$first  = acf_get_block_id( $attributes );
		$second = acf_get_block_id( $attributes );
		$this->assertSame( $first, $second );
		$this->assertSame( 32, strlen( $first ) );

		// Different data produces a different ID.
		$different = acf_get_block_id(
			array(
				'name' => 'acf/hash-block',
				'data' => array( 'field_x' => 'z' ),
			)
		);
		$this->assertNotSame( $first, $different );

		// Context changes the ID.
		$with_context = acf_get_block_id( $attributes, array( 'postId' => 5 ) );
		$this->assertNotSame( $first, $with_context );

		// Empty-string attributes are ignored, matching the JS hash building.
		$with_empty = acf_get_block_id( array_merge( $attributes, array( 'align' => '' ) ) );
		$this->assertSame( $first, $with_empty );

		// Empty data is ignored too.
		$no_data         = acf_get_block_id( array( 'name' => 'acf/hash-block' ) );
		$with_empty_data = acf_get_block_id(
			array(
				'name' => 'acf/hash-block',
				'data' => array(),
			)
		);
		$this->assertSame( $no_data, $with_empty_data );

		// A forced regeneration ignores the existing ID.
		$forced = acf_get_block_id( array_merge( $attributes, array( 'id' => 'existing' ) ), array(), true );
		$this->assertSame( $first, $forced );
	}

	/**
	 * Test Gutenberg-compatible attribute serialization.
	 */
	public function test_serialize_block_attributes() {
		$serialized = acf_serialize_block_attributes(
			array(
				'text'  => 'a -- b < c > d & e',
				'quote' => 'say "hi"',
			)
		);

		$this->assertStringNotContainsString( '--', $serialized );
		$this->assertStringNotContainsString( '<', $serialized );
		$this->assertStringNotContainsString( '>', $serialized );
		$this->assertStringNotContainsString( '&', $serialized );
		$this->assertStringContainsString( '\\u002d\\u002d', $serialized );
		$this->assertStringContainsString( '\\u003c', $serialized );
		$this->assertStringContainsString( '\\u003e', $serialized );
		$this->assertStringContainsString( '\\u0026', $serialized );
		$this->assertStringContainsString( '\\u0022', $serialized );
	}

	/**
	 * Test detection of SCF blocks in block.json metadata.
	 */
	public function test_is_acf_block_json() {
		$this->assertTrue( acf_is_acf_block_json( array( 'acf' => array( 'mode' => 'preview' ) ) ) );
		$this->assertFalse( acf_is_acf_block_json( array( 'name' => 'core/paragraph' ) ) );
		$this->assertFalse( acf_is_acf_block_json( array( 'acf' => false ) ) );
	}

	/**
	 * Test block.json name prefixing.
	 */
	public function test_add_block_namespace() {
		// SCF blocks without a namespace get the acf/ prefix and a slugified name.
		$metadata = acf_add_block_namespace(
			array(
				'name' => 'My Block_name',
				'acf'  => array( 'mode' => 'preview' ),
			)
		);
		$this->assertSame( 'acf/my-block-name', $metadata['name'] );

		// Existing namespaces are kept.
		$metadata = acf_add_block_namespace(
			array(
				'name' => 'custom/my-block',
				'acf'  => array( 'mode' => 'preview' ),
			)
		);
		$this->assertSame( 'custom/my-block', $metadata['name'] );

		// Non-SCF blocks are untouched.
		$metadata = acf_add_block_namespace( array( 'name' => 'my-block' ) );
		$this->assertSame( 'my-block', $metadata['name'] );
	}

	/**
	 * Test block.json registration handling of SCF settings.
	 */
	public function test_handle_json_block_registration_defaults_and_mappings() {
		$this->block_names[] = 'acf/json-mapped';

		$settings = acf_handle_json_block_registration(
			array(),
			array(
				'name'  => 'acf/json-mapped',
				'title' => 'JSON Mapped',
				'file'  => '/tmp/scf-test-json/block.json',
				'acf'   => array(
					'mode'           => 'edit',
					'renderTemplate' => 'template.php',
					'blockVersion'   => 3,
					'postTypes'      => array( 'post' ),
				),
			)
		);

		// Non-SCF metadata passes through untouched.
		$this->assertSame(
			array( 'foo' => 'bar' ),
			acf_handle_json_block_registration( array( 'foo' => 'bar' ), array( 'name' => 'core/group' ) )
		);

		// camelCase acf settings are mapped to snake_case.
		$this->assertSame( 'edit', $settings['mode'] );
		$this->assertSame( 'template.php', $settings['render_template'] );
		$this->assertSame( 3, $settings['acf_block_version'] );
		$this->assertSame( array( 'post' ), $settings['post_types'] );

		// Block version 3 implies api_version 3 on WP 6.3+.
		$this->assertSame( 3, $settings['api_version'] );

		// Defaults.
		$this->assertTrue( $settings['validate'] );
		$this->assertTrue( $settings['validate_on_load'] );
		$this->assertFalse( $settings['use_post_meta'] );
		$this->assertTrue( $settings['expanded_editor_buttons'] );
		$this->assertTrue( $settings['supports']['jsx'] );
		$this->assertContains( 'postId', $settings['uses_context'] );
		$this->assertContains( 'postType', $settings['uses_context'] );
		$this->assertArrayHasKey( 'data', $settings['attributes'] );

		// Name and path are derived from the metadata.
		$this->assertSame( 'acf/json-mapped', $settings['name'] );
		$this->assertSame( '/tmp/scf-test-json', $settings['path'] );

		// SCF takes over the render callback and stores the block type.
		$this->assertSame( 'acf_render_block_callback', $settings['render_callback'] );
		$this->assertTrue( acf_has_block_type( 'acf/json-mapped' ) );
	}

	/**
	 * Test block.json registration of blocks saving to post meta.
	 */
	public function test_handle_json_block_registration_use_post_meta() {
		$this->block_names[] = 'acf/json-postmeta';

		$settings = acf_handle_json_block_registration(
			array(),
			array(
				'name'  => 'acf/json-postmeta',
				'title' => 'JSON Post Meta',
				'file'  => '/tmp/scf-test-json/block.json',
				'acf'   => array(
					'mode'        => 'preview',
					'usePostMeta' => true,
				),
			)
		);

		$this->assertTrue( $settings['use_post_meta'] );
		$this->assertSame( array( 'core/post-content' ), $settings['parent'] );
		$this->assertFalse( $settings['supports']['multiple'] );
	}

	/**
	 * Test key generation for fields defined in block.json.
	 */
	public function test_block_json_process_fields() {
		$fields = acf_block_json_process_fields(
			array(
				array(
					'name' => 'plain',
					'type' => 'text',
				),
				array(
					'name' => 'keyed',
					'key'  => 'field_custom_key',
					'type' => 'text',
				),
				'not-an-array',
				array(
					'name'       => 'parent',
					'type'       => 'repeater',
					'sub_fields' => array(
						array(
							'name' => 'child',
							'type' => 'text',
						),
					),
				),
				array(
					'name'    => 'flex',
					'type'    => 'flexible_content',
					'layouts' => array(
						array(
							'name'       => 'layout_one',
							'sub_fields' => array(
								array(
									'name' => 'deep',
									'type' => 'text',
								),
							),
						),
					),
				),
			),
			'acf_my_block',
			'acf/my-block'
		);

		$this->assertCount( 4, $fields );
		$this->assertSame( 'field_acf_my_block_plain', $fields[0]['key'] );
		$this->assertSame( 'field_custom_key', $fields[1]['key'] );
		$this->assertSame( 'field_acf_my_block_parent', $fields[2]['key'] );
		$this->assertSame( 'field_acf_my_block_parent_child', $fields[2]['sub_fields'][0]['key'] );
		$this->assertSame( 'field_acf_my_block_flex_layout_one_deep', $fields[3]['layouts'][0]['sub_fields'][0]['key'] );
	}

	/**
	 * Test that fields without a name are skipped with a _doing_it_wrong().
	 */
	public function test_block_json_process_fields_skips_missing_name() {
		$wrong_calls = array();
		add_action(
			'doing_it_wrong_run',
			function ( $function_name ) use ( &$wrong_calls ) {
				$wrong_calls[] = $function_name;
			}
		);

		$fields = acf_block_json_process_fields(
			array(
				array( 'type' => 'text' ),
				array(
					'name' => 'valid',
					'type' => 'text',
				),
			),
			'acf_my_block',
			'acf/my-block'
		);

		$this->assertCount( 1, $fields );
		$this->assertSame( 'valid', $fields[0]['name'] );
		$this->assertContains( 'acf_block_json_process_fields', $wrong_calls );
	}

	/**
	 * Test registering a field group from inline block fields.
	 */
	public function test_register_block_field_group_from_fields() {
		// Invalid input.
		$this->assertFalse( acf_register_block_field_group_from_fields( '', 'Title', array( array( 'name' => 'a' ) ) ) );
		$this->assertFalse( acf_register_block_field_group_from_fields( 'acf/some-block', 'Title', array() ) );

		$result = acf_register_block_field_group_from_fields(
			'acf/group-from-fields',
			'Group From Fields',
			array(
				array(
					'name'  => 'gff_text',
					'label' => 'Text',
					'type'  => 'text',
				),
			),
			'Custom Group Title'
		);
		$this->track_group( 'group_acf_group_from_fields', array( 'field_acf_group_from_fields_gff_text' ) );

		$this->assertTrue( $result );

		$group = acf_get_field_group( 'group_acf_group_from_fields' );
		$this->assertSame( 'Custom Group Title', $group['title'] );
		$this->assertSame(
			array(
				'param'    => 'block',
				'operator' => '==',
				'value'    => 'acf/group-from-fields',
			),
			$group['location'][0][0]
		);
	}

	/**
	 * Test that acf_get_block_fields() returns fields located to the block.
	 */
	public function test_get_block_fields() {
		$this->assertSame( array(), acf_get_block_fields( array() ) );

		acf_add_local_field_group(
			array(
				'key'      => 'group_blocks_fields_test',
				'title'    => 'Block Fields Test',
				'fields'   => array(
					array(
						'key'   => 'field_blocks_fields_test_text',
						'name'  => 'bft_text',
						'label' => 'Text',
						'type'  => 'text',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/fields-test-block',
						),
					),
				),
			)
		);
		$this->track_group( 'group_blocks_fields_test', array( 'field_blocks_fields_test_text' ) );

		$fields = acf_get_block_fields( array( 'name' => 'acf/fields-test-block' ) );
		$this->assertCount( 1, $fields );
		$this->assertSame( 'bft_text', $fields[0]['name'] );

		// A different block gets no fields.
		$this->assertSame( array(), acf_get_block_fields( array( 'name' => 'acf/other-block' ) ) );
	}

	/**
	 * Test full block.json file registration through register_block_type().
	 */
	public function test_block_json_file_registration_and_render() {
		$dir = sys_get_temp_dir() . '/scf-block-json-' . uniqid();
		mkdir( $dir );
		$this->temp_paths[] = $dir;

		file_put_contents(
			$dir . '/block.json',
			wp_json_encode(
				array(
					'name'  => 'jsonfile-block',
					'title' => 'JSON File Block',
					'acf'   => array(
						'mode'           => 'preview',
						'renderTemplate' => 'render.php',
						'fields'         => array(
							array(
								'name'  => 'jsonfile_text',
								'label' => 'Text',
								'type'  => 'text',
							),
						),
					),
				)
			)
		);
		file_put_contents( $dir . '/render.php', '<?php echo "VAL[" . get_field( "jsonfile_text" ) . "]"; ?>' );

		$wp_block_type       = register_block_type( $dir );
		$this->block_names[] = 'acf/jsonfile-block';
		$this->track_group( 'group_acf_jsonfile_block', array( 'field_acf_jsonfile_block_jsonfile_text' ) );

		// The name was namespaced and SCF took over rendering.
		$this->assertSame( 'acf/jsonfile-block', $wp_block_type->name );
		$this->assertSame( 'acf_render_block_callback', $wp_block_type->render_callback );

		// Stored in the SCF block-types store with the block.json path.
		$stored = acf_get_block_type( 'acf/jsonfile-block' );
		$this->assertSame( 'render.php', $stored['render_template'] );
		// WordPress resolves the block.json path via realpath(), so on macOS
		// /var/... becomes /private/var/... for temp directories.
		$this->assertSame( realpath( $dir ), realpath( $stored['path'] ) );

		// The inline fields were registered as a local field group.
		$this->assertTrue( acf_is_local_field_group( 'group_acf_jsonfile_block' ) );

		// Render the block through the real WordPress pipeline.
		$html = do_blocks( '<!-- wp:acf/jsonfile-block {"name":"acf/jsonfile-block","data":{"jsonfile_text":"FromJson","_jsonfile_text":"field_acf_jsonfile_block_jsonfile_text"}} /-->' );
		$this->assertStringContainsString( 'VAL[FromJson]', $html );
	}

	/**
	 * Test that saving content converts block data to the meta format.
	 */
	public function test_parse_save_blocks_converts_data_to_meta_format() {
		$this->register_block(
			array(
				'name'  => 'save-block',
				'title' => 'Save Block',
			)
		);
		acf_add_local_field_group(
			array(
				'key'      => 'group_blocks_save_test',
				'title'    => 'Save Test',
				'fields'   => array(
					array(
						'key'   => 'field_blocks_save_text',
						'name'  => 'save_text',
						'label' => 'Text',
						'type'  => 'text',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/save-block',
						),
					),
				),
			)
		);
		$this->track_group( 'group_blocks_save_test', array( 'field_blocks_save_text' ) );

		$content = '<!-- wp:acf/save-block {"id":"block_savetest","name":"acf/save-block","data":{"field_blocks_save_text":"Saved"}} /-->';
		$parsed  = stripslashes( acf_parse_save_blocks( addslashes( $content ) ) );
		acf_reset_meta( 'block_savetest' );

		// Field-key format is converted to name/_name meta format.
		$this->assertStringContainsString( '"save_text":"Saved"', $parsed );
		$this->assertStringContainsString( '"_save_text":"field_blocks_save_text"', $parsed );
		$this->assertStringNotContainsString( '"field_blocks_save_text":"Saved"', $parsed );
		$this->assertStringStartsWith( '<!-- wp:acf/save-block ', $parsed );
		$this->assertStringEndsWith( ' /-->', $parsed );

		// Non-SCF blocks are untouched.
		$core = '<!-- wp:core/paragraph {"align":"wide"} -->';
		$this->assertSame( $core, stripslashes( acf_parse_save_blocks( addslashes( $core ) ) ) );
	}

	/**
	 * Test save handling and meta value collection for use_post_meta blocks.
	 */
	public function test_parse_save_blocks_use_post_meta_collects_values() {
		$this->register_block(
			array(
				'name'          => 'meta-block',
				'title'         => 'Meta Block',
				'use_post_meta' => true,
			)
		);

		$this->assertTrue( acf_block_uses_post_meta( array( 'name' => 'acf/meta-block' ) ) );
		$this->assertFalse( acf_block_uses_post_meta( array( 'name' => 'acf/unknown' ) ) );

		$content = '<!-- wp:acf/meta-block {"id":"block_metatest","name":"acf/meta-block","data":{"field_meta_text":"MetaVal"}} /-->';
		$parsed  = stripslashes( acf_parse_save_blocks( addslashes( $content ) ) );

		// Data is stripped from post content and cached for the save_post hook.
		$this->assertStringNotContainsString( 'MetaVal', $parsed );
		$this->assertStringContainsString( '"id":"block_metatest"', $parsed );
		$this->assertTrue( acf_get_store( 'block-meta-values' )->has( 'block_metatest' ) );

		// The values are collected for saving and removed from the cache.
		$values = acf_get_block_meta_values_to_save( $parsed );
		$this->assertSame( array( 'field_meta_text' => 'MetaVal' ), $values );
		$this->assertFalse( acf_get_store( 'block-meta-values' )->has( 'block_metatest' ) );

		// Empty/non-block content short-circuits.
		$this->assertSame( array(), acf_get_block_meta_values_to_save( '' ) );
		$this->assertSame( array(), acf_get_block_meta_values_to_save( 'plain text' ) );
	}

	/**
	 * Test that use_post_meta blocks load their data from post meta.
	 */
	public function test_add_block_meta_values_loads_from_post_meta() {
		$this->register_block(
			array(
				'name'          => 'meta-load-block',
				'title'         => 'Meta Load Block',
				'use_post_meta' => true,
			)
		);
		acf_add_local_field_group(
			array(
				'key'      => 'group_blocks_meta_load',
				'title'    => 'Meta Load',
				'fields'   => array(
					array(
						'key'   => 'field_blocks_meta_load_text',
						'name'  => 'meta_load_text',
						'label' => 'Text',
						'type'  => 'text',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/meta-load-block',
						),
					),
				),
			)
		);
		$this->track_group( 'group_blocks_meta_load', array( 'field_blocks_meta_load_text' ) );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Meta Load Test',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'meta_load_text', 'Stored Value' );
		update_post_meta( $post_id, '_meta_load_text', 'field_blocks_meta_load_text' );

		$block = array(
			'id'            => 'block_metaload',
			'name'          => 'acf/meta-load-block',
			'data'          => array(),
			'use_post_meta' => true,
		);

		$block = acf_add_block_meta_values( $block, $post_id );

		$this->assertSame( 'Stored Value', $block['data']['meta_load_text'] );
		$this->assertSame( 'field_blocks_meta_load_text', $block['data']['_meta_load_text'] );
		$this->assertTrue( (bool) acf_get_data( 'block_metaload_loaded_meta_values' ) );

		// A block that already has data is returned untouched.
		$existing = array(
			'id'            => 'block_other',
			'name'          => 'acf/meta-load-block',
			'data'          => array( 'meta_load_text' => 'Existing' ),
			'use_post_meta' => true,
		);
		$this->assertSame( $existing, acf_add_block_meta_values( $existing, $post_id ) );
	}
}
