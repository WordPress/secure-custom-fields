<?php
/**
 * Tests for the SCF block rendering pipeline in includes/blocks.php.
 *
 * Covers acf_rendered_block()/acf_render_block() template resolution and
 * output, render_callback argument propagation ($is_preview, $post_id,
 * $context), v3 rendering and InnerBlocks handling, the block-cache
 * preloading store, block validation state, the empty block form and the
 * inline editing attribute helpers.
 *
 * @package wordpress/secure-custom-fields
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions -- direct filesystem calls manage throwaway temp template fixtures.
 */

use WorDBless\BaseTestCase;

/**
 * Test the block render pipeline.
 */
class Test_Blocks_Render extends BaseTestCase {

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
	 * Temp files created during a test.
	 *
	 * @var array
	 */
	private $temp_files = array();

	/**
	 * Block IDs that had local meta set up during a test.
	 *
	 * @var array
	 */
	private $meta_block_ids = array();

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		acf_init();

		$this->ensure_local_meta_filters();

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

		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
		$this->temp_files = array();

		// NOTE: documents current behavior — possible bug: the preview render
		// paths in acf_rendered_block()/acf_rendered_block_v3() call
		// acf_setup_meta() for validation after rendering without a matching
		// acf_reset_meta(), leaving block-scoped local meta active after the
		// function returns. Reset it to avoid leaking into other tests.
		foreach ( $this->meta_block_ids as $block_id ) {
			acf_reset_meta( $block_id );
		}
		$this->meta_block_ids = array();

		acf_set_data( 'acf_current_block_version', null );
		acf_set_data( 'acf_doing_block_preview', false );

		acf_get_store( 'block-cache' )->reset();
		acf_get_store( 'values' )->reset();

		unset( $GLOBALS['post'] );

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
	 * Creates a temporary render template file.
	 *
	 * @param string $contents The PHP template contents.
	 * @return string The template path.
	 */
	private function make_template( $contents ) {
		$path = sys_get_temp_dir() . '/scf-block-template-' . uniqid() . '.php';
		file_put_contents( $path, $contents );
		$this->temp_files[] = $path;
		return $path;
	}

	/**
	 * Registers a text field group located to the given block name.
	 *
	 * @param string $block_name The block name (e.g. acf/render-block).
	 * @param string $suffix     Unique suffix for the group/field keys.
	 * @param string $field_name The field name.
	 */
	private function register_block_field_group( $block_name, $suffix, $field_name ) {
		acf_add_local_field_group(
			array(
				'key'      => "group_blocks_render_{$suffix}",
				'title'    => 'Render Fields',
				'fields'   => array(
					array(
						'key'   => "field_blocks_render_{$suffix}",
						'name'  => $field_name,
						'label' => 'Text',
						'type'  => 'text',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => $block_name,
						),
					),
				),
			)
		);
		$this->group_keys[] = "group_blocks_render_{$suffix}";
		$this->field_keys[] = "field_blocks_render_{$suffix}";
	}

	/**
	 * Test a front-end render through a render template.
	 */
	public function test_render_template_front_end() {
		$template = $this->make_template(
			'<?php
			echo "VAL[" . get_field( "render_text" ) . "]";
			echo "PREVIEW[" . ( $is_preview ? 1 : 0 ) . "]";
			echo "POST[" . (int) $post_id . "]";
			echo "NAME[" . $block["name"] . "]";
			'
		);

		$this->register_block(
			array(
				'name'            => 'render-tpl-block',
				'title'           => 'Render Template Block',
				'render_template' => $template,
			)
		);
		$this->register_block_field_group( 'acf/render-tpl-block', 'tpl', 'render_text' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Render Test',
				'post_status' => 'publish',
			)
		);

		$attributes = array(
			'id'   => 'block_rendertpl',
			'name' => 'acf/render-tpl-block',
			'data' => array(
				'render_text'  => 'Hello Render',
				'_render_text' => 'field_blocks_render_tpl',
			),
			'mode' => 'preview',
		);

		$html = acf_rendered_block( $attributes, '', false, $post_id );

		$this->assertStringContainsString( 'VAL[Hello Render]', $html );
		$this->assertStringContainsString( 'PREVIEW[0]', $html );
		$this->assertStringContainsString( "POST[{$post_id}]", $html );
		$this->assertStringContainsString( 'NAME[acf/render-tpl-block]', $html );
	}

	/**
	 * Test that a missing render template outputs nothing on the front end
	 * but a message in preview.
	 */
	public function test_render_template_not_found() {
		$this->register_block(
			array(
				'name'            => 'missing-tpl-block',
				'title'           => 'Missing Template Block',
				'render_template' => 'this-template-does-not-exist.php',
			)
		);

		$attributes = array(
			'id'   => 'block_missingtpl',
			'name' => 'acf/missing-tpl-block',
			'data' => array(),
			'mode' => 'preview',
		);

		$html = acf_rendered_block( $attributes, '', false, 0 );
		$this->assertStringNotContainsString( 'render template', $html );

		// Clear the block cache so the preview render is not served from
		// the front-end render that was just cached for the same block ID.
		acf_get_store( 'block-cache' )->reset();

		$preview_html           = acf_rendered_block( $attributes, '', true, 0 );
		$this->meta_block_ids[] = 'block_missingtpl';
		$this->assertStringContainsString( 'The render template for this ACF Block was not found', $preview_html );
	}

	/**
	 * Test render_callback argument propagation, including $context.
	 */
	public function test_render_callback_args_propagation() {
		$captured = array();

		$this->register_block(
			array(
				'name'            => 'callback-block',
				'title'           => 'Callback Block',
				'render_callback' => function ( $block, $content, $is_preview, $post_id, $wp_block, $context ) use ( &$captured ) {
					$captured = array(
						'block'      => $block,
						'content'    => $content,
						'is_preview' => $is_preview,
						'post_id'    => $post_id,
						'wp_block'   => $wp_block,
						'context'    => $context,
					);
					echo 'CALLBACK-RAN';
				},
			)
		);

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Callback Test',
				'post_status' => 'publish',
			)
		);

		$attributes = array(
			'id'   => 'block_callback',
			'name' => 'acf/callback-block',
			'data' => array(),
			'mode' => 'preview',
		);
		$context    = array(
			'postId'   => $post_id,
			'postType' => 'post',
		);

		$html = acf_rendered_block( $attributes, '', false, $post_id, null, $context );

		$this->assertStringContainsString( 'CALLBACK-RAN', $html );
		$this->assertSame( 'acf/callback-block', $captured['block']['name'] );
		$this->assertSame( 'block_callback', $captured['block']['id'] );
		$this->assertFalse( $captured['is_preview'] );
		$this->assertSame( $post_id, $captured['post_id'] );
		$this->assertSame( $context, $captured['context'] );
	}

	/**
	 * Test that $is_preview is passed through and the preview is cached.
	 */
	public function test_preview_render_populates_block_cache() {
		$captured_preview = null;

		$this->register_block(
			array(
				'name'            => 'preview-cache-block',
				'title'           => 'Preview Cache Block',
				'render_callback' => function ( $block, $content, $is_preview ) use ( &$captured_preview ) {
					$captured_preview = $is_preview;
					echo 'PREVIEW-HTML';
				},
			)
		);

		$attributes = array(
			'id'   => 'block_previewcache',
			'name' => 'acf/preview-cache-block',
			'data' => array(),
			'mode' => 'preview',
		);

		$html                   = acf_rendered_block( $attributes, '', true, 0 );
		$this->meta_block_ids[] = 'block_previewcache';

		$this->assertTrue( $captured_preview );
		$this->assertStringContainsString( 'PREVIEW-HTML', $html );

		// The render is cached for editor preloading.
		$cached = acf_get_store( 'block-cache' )->get( 'block_previewcache' );
		$this->assertIsArray( $cached );
		$this->assertFalse( $cached['form'] );
		$this->assertSame( $html, $cached['html'] );

		// A second preview render is served from the cache, not re-rendered.
		$captured_preview = null;
		$second           = acf_rendered_block( $attributes, '', true, 0 );
		$this->assertSame( $html, $second );
		$this->assertNull( $captured_preview );
	}

	/**
	 * Test that v3 blocks wrap inner blocks content on the front end.
	 */
	public function test_v3_render_wraps_inner_blocks() {
		$template = $this->make_template( '<div class="my-v3-block"><InnerBlocks /></div>' );

		$this->register_block(
			array(
				'name'              => 'v3-inner-block',
				'title'             => 'V3 Inner Block',
				'acf_block_version' => 3,
				'render_template'   => $template,
			)
		);

		$attributes = array(
			'id'   => 'block_v3inner',
			'name' => 'acf/v3-inner-block',
			'data' => array(),
			'mode' => 'preview',
		);

		$html = acf_rendered_block( $attributes, '<p>Inner content</p>', false, 0 );

		$this->assertStringContainsString( '<div class="acf-innerblocks-container"><p>Inner content</p></div>', $html );
		$this->assertStringNotContainsString( '<InnerBlocks', $html );
	}

	/**
	 * Test the InnerBlocks placeholder replacement helper.
	 */
	public function test_replace_inner_blocks_in_block_content() {
		$this->assertSame(
			'<div><p>inner</p></div>',
			acf_replace_inner_blocks_in_block_content( '<p>inner</p>', '<div><InnerBlocks /></div>' )
		);

		$this->assertSame(
			'<div><p>inner</p></div>',
			acf_replace_inner_blocks_in_block_content( '<p>inner</p>', '<div><InnerBlocks className="custom" /></div>' )
		);

		// No placeholder, no change.
		$this->assertSame(
			'<div>static</div>',
			acf_replace_inner_blocks_in_block_content( '<p>inner</p>', '<div>static</div>' )
		);
	}

	/**
	 * Test a full front-end render through do_blocks(), including the
	 * fallback that fills the name attribute from the WP_Block instance.
	 */
	public function test_do_blocks_renders_block() {
		$template = $this->make_template( '<?php echo "DB[" . get_field( "do_blocks_text" ) . "]PREVIEW[" . ( $is_preview ? 1 : 0 ) . "]";' );

		$this->register_block(
			array(
				'name'            => 'do-blocks-block',
				'title'           => 'Do Blocks Block',
				'render_template' => $template,
			)
		);
		$this->register_block_field_group( 'acf/do-blocks-block', 'do_blocks', 'do_blocks_text' );

		// Note the missing "name" attribute: acf_render_block_callback() falls
		// back to the WP_Block instance name.
		$content = '<!-- wp:acf/do-blocks-block {"id":"block_doblocks","data":{"do_blocks_text":"Front Value","_do_blocks_text":"field_blocks_render_do_blocks"}} /-->';

		$html = do_blocks( $content );

		$this->assertStringContainsString( 'DB[Front Value]', $html );
		$this->assertStringContainsString( 'PREVIEW[0]', $html );
	}

	/**
	 * Test the empty block form HTML and its filters.
	 */
	public function test_get_empty_block_form_html() {
		$html = acf_get_empty_block_form_html( 'acf/some-block' );
		$this->assertStringContainsString( 'This block contains no editable fields.', $html );
		$this->assertStringContainsString( 'acf-empty-block-fields', $html );

		// The message can be filtered.
		add_filter(
			'acf/blocks/no_fields_assigned_message',
			function () {
				return 'Custom empty message';
			}
		);
		$this->assertStringContainsString( 'Custom empty message', acf_get_empty_block_form_html( 'acf/some-block' ) );

		// A non-string message results in an empty container.
		add_filter(
			'acf/blocks/no_fields_assigned_message',
			function () {
				return false;
			},
			20
		);
		$this->assertSame( acf_esc_html( '<div class="acf-empty-block-fields"></div>' ), acf_get_empty_block_form_html( 'acf/some-block' ) );
	}

	/**
	 * Test block validation state handling.
	 */
	public function test_get_block_validation_state() {
		acf_add_local_field_group(
			array(
				'key'      => 'group_blocks_validation',
				'title'    => 'Validation Fields',
				'fields'   => array(
					array(
						'key'      => 'field_blocks_validation_req',
						'name'     => 'validation_req',
						'label'    => 'Required Text',
						'type'     => 'text',
						'required' => 1,
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/validation-block',
						),
					),
				),
			)
		);
		$this->group_keys[] = 'group_blocks_validation';
		$this->field_keys[] = 'field_blocks_validation_req';

		$block = array(
			'id'               => 'block_validation',
			'name'             => 'acf/validation-block',
			'data'             => array( 'field_blocks_validation_req' => '' ),
			'validate'         => true,
			'validate_on_load' => false,
		);

		// Validation is skipped on load when validate_on_load is false.
		$state = acf_get_block_validation_state( $block, false, false, true );
		$this->assertTrue( $state['valid'] );
		$this->assertFalse( $state['errors'] );

		// POSTed data with an empty required field produces an error.
		$state = acf_get_block_validation_state( $block, false, true );
		$this->assertFalse( $state['valid'] );
		$this->assertIsArray( $state['errors'] );
		$this->assertSame( 'acf-block_validation[field_blocks_validation_req]', $state['errors'][0]['input'] );

		// POSTed data with a value passes.
		$block['data'] = array( 'field_blocks_validation_req' => 'Filled' );
		$state         = acf_get_block_validation_state( $block, false, true );
		$this->assertTrue( $state['valid'] );
	}

	/**
	 * Test the inline editing empty-field helper.
	 */
	public function test_inline_editing_field_is_empty() {
		acf_add_local_field_group(
			array(
				'key'      => 'group_blocks_inline_empty',
				'title'    => 'Inline Empty Fields',
				'fields'   => array(
					array(
						'key'   => 'field_blocks_inline_empty',
						'name'  => 'inline_empty_text',
						'label' => 'Text',
						'type'  => 'text',
					),
				),
				'location' => array(),
			)
		);
		$this->group_keys[] = 'group_blocks_inline_empty';
		$this->field_keys[] = 'field_blocks_inline_empty';

		acf_setup_meta(
			array(
				'inline_empty_text'  => 'acf_auto_inline_editing_field_name_inline_empty_text',
				'_inline_empty_text' => 'field_blocks_inline_empty',
			),
			'block_inlineempty',
			true
		);
		$this->meta_block_ids[] = 'block_inlineempty';

		// The auto inline editing placeholder counts as empty.
		$this->assertTrue( acf_inline_editing_field_is_empty( 'inline_empty_text' ) );

		acf_setup_meta(
			array(
				'inline_empty_text'  => 'A real value',
				'_inline_empty_text' => 'field_blocks_inline_empty',
			),
			'block_inlineempty2',
			true
		);
		$this->meta_block_ids[] = 'block_inlineempty2';
		acf_get_store( 'values' )->reset();

		$this->assertFalse( acf_inline_editing_field_is_empty( 'inline_empty_text' ) );

		// A missing field value is empty.
		$this->assertTrue( acf_inline_editing_field_is_empty( 'totally_missing_field' ) );
	}

	/**
	 * Test the inline text editing attributes helper.
	 */
	public function test_inline_text_editing_attrs() {
		// No field name.
		$this->assertSame( '', acf_inline_text_editing_attrs( '' ) );

		// No current block version tracked.
		acf_set_data( 'acf_current_block_version', null );
		$this->assertSame( '', acf_inline_text_editing_attrs( 'my_field' ) );

		// V2 and below do not support inline editing.
		acf_set_data( 'acf_current_block_version', 2 );
		$this->assertSame( '', acf_inline_text_editing_attrs( 'my_field' ) );

		// V3 outside a block preview renders nothing.
		acf_set_data( 'acf_current_block_version', 3 );
		acf_set_data( 'acf_doing_block_preview', false );
		$this->assertSame( '', acf_inline_text_editing_attrs( 'my_field' ) );

		// V3 in a block preview renders contenteditable attributes.
		acf_set_data( 'acf_doing_block_preview', true );
		$attrs = acf_inline_text_editing_attrs( 'my_field' );
		$this->assertStringContainsString( 'data-acf-inline-contenteditable="1"', $attrs );
		$this->assertStringContainsString( 'data-acf-inline-contenteditable-field-slug="my_field"', $attrs );
		$this->assertStringContainsString( 'data-acf-placeholder="Type to edit..."', $attrs );

		// Custom args are reflected.
		$attrs = acf_inline_text_editing_attrs(
			'my_field',
			array(
				'toolbar_icon'  => '<svg></svg>',
				'toolbar_title' => 'My Toolbar',
				'placeholder'   => 'Custom placeholder',
			)
		);
		$this->assertStringContainsString( 'data-acf-toolbar-icon=', $attrs );
		$this->assertStringContainsString( 'data-acf-toolbar-title="My Toolbar"', $attrs );
		$this->assertStringContainsString( 'data-acf-placeholder="Custom placeholder"', $attrs );
	}

	/**
	 * Test the inline toolbar editing attributes helper.
	 */
	public function test_inline_toolbar_editing_attrs() {
		acf_add_local_field_group(
			array(
				'key'      => 'group_blocks_toolbar',
				'title'    => 'Toolbar Fields',
				'fields'   => array(
					array(
						'key'   => 'field_blocks_toolbar_text',
						'name'  => 'toolbar_text',
						'label' => 'Toolbar Text',
						'type'  => 'text',
					),
				),
				'location' => array(),
			)
		);
		$this->group_keys[] = 'group_blocks_toolbar';
		$this->field_keys[] = 'field_blocks_toolbar_text';

		// Empty fields short-circuit, respecting the return format.
		$this->assertSame( '', acf_inline_toolbar_editing_attrs( array() ) );
		$this->assertSame( array(), acf_inline_toolbar_editing_attrs( array(), array( 'return_array' => true ) ) );

		// V2 and below do not support toolbar editing.
		acf_set_data( 'acf_current_block_version', 2 );
		$this->assertSame( '', acf_inline_toolbar_editing_attrs( array( 'toolbar_text' ) ) );

		// V3 outside a block preview renders nothing.
		acf_set_data( 'acf_current_block_version', 3 );
		acf_set_data( 'acf_doing_block_preview', false );
		$this->assertSame( '', acf_inline_toolbar_editing_attrs( array( 'toolbar_text' ) ) );

		// V3 in a block preview with block-scoped local meta renders attributes.
		acf_set_data( 'acf_doing_block_preview', true );
		acf_setup_meta( array(), 'block_toolbar1', true );
		$this->meta_block_ids[] = 'block_toolbar1';

		$attrs = acf_inline_toolbar_editing_attrs( array( 'toolbar_text' ) );
		$this->assertStringContainsString( 'data-acf-inline-fields-uid="block_toolbar1toolbar_text"', $attrs );
		$this->assertStringContainsString( 'role="button"', $attrs );
		$this->assertStringContainsString( 'tabindex="0"', $attrs );
		$this->assertStringContainsString( 'toolbar_text', $attrs );

		// Array form with custom label, expanded editor and array return.
		$attrs = acf_inline_toolbar_editing_attrs(
			array(
				array(
					'field_name'          => 'toolbar_text',
					'field_label'         => 'Custom Label',
					'use_expanded_editor' => true,
				),
			),
			array(
				'toolbar_title' => 'My Toolbar',
				'return_array'  => true,
			)
		);

		$this->assertIsArray( $attrs );
		$this->assertArrayHasKey( 'data-acf-inline-fields-uid', $attrs );
		$this->assertArrayHasKey( 'data-acf-inline-fields', $attrs );
		$this->assertSame( 'My Toolbar', $attrs['data-acf-toolbar-title'] );

		$fields_json = html_entity_decode( $attrs['data-acf-inline-fields'], ENT_QUOTES );
		$decoded     = json_decode( $fields_json, true );
		$this->assertSame( 'toolbar_text', $decoded[0]['fieldName'] );
		$this->assertSame( 'Custom Label', $decoded[0]['fieldLabel'] );
		$this->assertTrue( $decoded[0]['useExpandedEditor'] );

		// Unknown fields are skipped entirely.
		$attrs = acf_inline_toolbar_editing_attrs( array( 'this_field_does_not_exist' ) );
		$this->assertStringContainsString( 'data-acf-inline-fields="[]"', $attrs );
	}
}
