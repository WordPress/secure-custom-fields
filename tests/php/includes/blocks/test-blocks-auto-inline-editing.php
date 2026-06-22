<?php
/**
 * Tests for includes/blocks-auto-inline-editing.php.
 *
 * Covers the field type allow-lists, the acf/format_value capture filter
 * that records fields used in a block render template, the DOM rewriting
 * that applies inline editing attributes to rendered HTML, and a full
 * preview render of a v3 block with autoInlineEditing enabled.
 *
 * @package wordpress/secure-custom-fields
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions -- direct filesystem calls manage throwaway temp template fixtures.
 */

use WorDBless\BaseTestCase;

use function SCF\Blocks\AutoInlineEditing\get_allowed_contenteditable_fields;
use function SCF\Blocks\AutoInlineEditing\get_non_auto_inline_editing_fields;
use function SCF\Blocks\AutoInlineEditing\populate_auto_inline_editing_values;
use function SCF\Blocks\AutoInlineEditing\apply_inline_editing_attributes_to_html_string;

/**
 * Test the auto inline editing layer.
 */
class Test_Blocks_Auto_Inline_Editing extends BaseTestCase {

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

		$GLOBALS['acf_fields_used_in_block_render_template'] = array();
		$GLOBALS['acf_blocks_doing_auto_inline_editing']     = false;
	}

	/**
	 * Clean up test state.
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

		foreach ( $this->meta_block_ids as $block_id ) {
			acf_reset_meta( $block_id );
		}
		$this->meta_block_ids = array();

		acf_set_data( 'acf_current_block_version', null );
		acf_set_data( 'acf_doing_block_preview', false );

		acf_get_store( 'block-cache' )->reset();
		acf_get_store( 'values' )->reset();

		unset( $GLOBALS['acf_fields_used_in_block_render_template'] );
		unset( $GLOBALS['acf_blocks_doing_auto_inline_editing'] );

		parent::tear_down();
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
	 * Test the field type allow-lists.
	 */
	public function test_field_type_lists() {
		$this->assertSame( array( 'text', 'textarea' ), get_allowed_contenteditable_fields() );
		$this->assertSame( array( 'repeater', 'flexible-content' ), get_non_auto_inline_editing_fields() );
	}

	/**
	 * Test that the capture filter is a no-op outside of auto inline editing renders.
	 */
	public function test_populate_values_passthrough_when_not_rendering() {
		$GLOBALS['acf_blocks_doing_auto_inline_editing'] = false;

		$value = populate_auto_inline_editing_values(
			'',
			0,
			array(
				'name' => 'some_field',
				'type' => 'text',
			)
		);

		$this->assertSame( '', $value );
		$this->assertSame( array(), $GLOBALS['acf_fields_used_in_block_render_template'] );
	}

	/**
	 * Test capturing field values during an auto inline editing render.
	 */
	public function test_populate_values_captures_fields() {
		$GLOBALS['acf_blocks_doing_auto_inline_editing'] = true;

		// A non-empty scalar value is captured as-is.
		$value = populate_auto_inline_editing_values(
			'Hello',
			0,
			array(
				'name' => 'cap_text',
				'type' => 'text',
			)
		);
		$this->assertSame( 'Hello', $value );

		// An empty value is replaced with the placeholder marker.
		$value = populate_auto_inline_editing_values(
			'',
			0,
			array(
				'name' => 'cap_empty',
				'type' => 'text',
			)
		);
		$this->assertSame( 'acf_auto_inline_editing_field_name_cap_empty', $value );

		// Array values pass through and are not captured.
		$value = populate_auto_inline_editing_values(
			array( 'a' ),
			0,
			array(
				'name' => 'cap_array',
				'type' => 'select',
			)
		);
		$this->assertSame( array( 'a' ), $value );

		// Repeater sub fields are skipped.
		$value = populate_auto_inline_editing_values(
			'Sub',
			0,
			array(
				'name'            => 'cap_sub',
				'type'            => 'text',
				'parent_repeater' => 'field_parent',
			)
		);
		$this->assertSame( 'Sub', $value );

		$captured_names = wp_list_pluck( $GLOBALS['acf_fields_used_in_block_render_template'], 'name' );
		$this->assertSame( array( 'cap_text', 'cap_empty' ), $captured_names );
	}

	/**
	 * Test that contenteditable attributes are applied to matching text values.
	 */
	public function test_apply_attributes_contenteditable_field() {
		$GLOBALS['acf_fields_used_in_block_render_template'] = array(
			array(
				'name'        => 'headline',
				'value'       => 'Hello World',
				'type'        => 'text',
				'placeholder' => 'Add a headline',
			),
		);

		$html = apply_inline_editing_attributes_to_html_string(
			'<div><h2>Hello World</h2></div>',
			array( 'id' => 'block_aie1' )
		);

		$this->assertStringContainsString( 'data-acf-inline-contenteditable', $html );
		$this->assertStringContainsString( 'data-acf-inline-contenteditable-field-slug="headline"', $html );
		$this->assertStringContainsString( 'data-acf-placeholder="Add a headline"', $html );
		$this->assertStringContainsString( 'role="button"', $html );
		$this->assertStringContainsString( 'Hello World', $html );
	}

	/**
	 * Test that empty-value placeholders are made editable and emptied.
	 */
	public function test_apply_attributes_empty_placeholder() {
		$GLOBALS['acf_fields_used_in_block_render_template'] = array(
			array(
				'name'  => 'headline',
				'value' => 'acf_auto_inline_editing_field_name_headline',
				'type'  => 'text',
			),
		);

		$html = apply_inline_editing_attributes_to_html_string(
			'<div><h2>acf_auto_inline_editing_field_name_headline</h2></div>',
			array( 'id' => 'block_aie2' )
		);

		$this->assertStringContainsString( 'data-acf-inline-contenteditable-field-slug="headline"', $html );
		// The placeholder marker is removed from the output.
		$this->assertStringNotContainsString( 'acf_auto_inline_editing_field_name_headline', $html );
	}

	/**
	 * Test that non-contenteditable field types get popover attributes instead.
	 */
	public function test_apply_attributes_popover_field() {
		$GLOBALS['acf_fields_used_in_block_render_template'] = array(
			array(
				'name'  => 'choice',
				'value' => 'Beta',
				'type'  => 'select',
			),
		);

		$html = apply_inline_editing_attributes_to_html_string(
			'<div><span>Beta</span></div>',
			array( 'id' => 'block_aie3' )
		);

		$this->assertStringNotContainsString( 'data-acf-inline-contenteditable', $html );
		$this->assertStringContainsString( 'data-acf-inline-fields-uid="choice__block_aie3"', $html );
		$this->assertStringContainsString( 'choice', $html );
	}

	/**
	 * Test that attribute values matching field values get popover attributes.
	 */
	public function test_apply_attributes_attribute_value_match() {
		$GLOBALS['acf_fields_used_in_block_render_template'] = array(
			array(
				'name'  => 'image_url',
				'value' => 'https://example.com/pic.png',
				'type'  => 'url',
			),
		);

		$html = apply_inline_editing_attributes_to_html_string(
			'<div><img src="https://example.com/pic.png" alt="pic"/></div>',
			array( 'id' => 'block_aie4' )
		);

		$this->assertStringContainsString( 'data-acf-inline-fields-uid="image_url__block_aie4"', $html );
		$this->assertStringContainsString( 'data-acf-inline-fields=', $html );
	}

	/**
	 * Test that placeholder markers inside attributes are stripped.
	 */
	public function test_apply_attributes_strips_placeholder_from_attributes() {
		$GLOBALS['acf_fields_used_in_block_render_template'] = array(
			array(
				'name'  => 'image_url',
				'value' => 'acf_auto_inline_editing_field_name_image_url',
				'type'  => 'url',
			),
		);

		$html = apply_inline_editing_attributes_to_html_string(
			'<div><img src="acf_auto_inline_editing_field_name_image_url" alt="pic"/></div>',
			array( 'id' => 'block_aie5' )
		);

		$this->assertStringNotContainsString( 'src="acf_auto_inline_editing_field_name_image_url"', $html );
		$this->assertStringContainsString( 'src=""', $html );
	}

	/**
	 * Test that HTML without DOM markup for any field is left functional.
	 */
	public function test_apply_attributes_no_matches() {
		$GLOBALS['acf_fields_used_in_block_render_template'] = array();

		$html = apply_inline_editing_attributes_to_html_string(
			'<div><p>Static content</p></div>',
			array( 'id' => 'block_aie6' )
		);

		$this->assertStringContainsString( '<p>Static content</p>', $html );
		$this->assertStringNotContainsString( 'data-acf-inline', $html );
	}

	/**
	 * Test a full preview render of a v3 block with autoInlineEditing enabled.
	 */
	public function test_v3_preview_render_applies_inline_editing() {
		$template = sys_get_temp_dir() . '/scf-aie-template-' . uniqid() . '.php';
		file_put_contents( $template, '<div class="aie-block"><h2><?php the_field( "auto_inline_text" ); ?></h2></div>' );
		$this->temp_files[] = $template;

		$block               = acf_register_block_type(
			array(
				'name'                => 'auto-inline-block',
				'title'               => 'Auto Inline Block',
				'acf_block_version'   => 3,
				'auto_inline_editing' => true,
				'render_template'     => $template,
			)
		);
		$this->block_names[] = $block['name'];

		acf_add_local_field_group(
			array(
				'key'      => 'group_blocks_auto_inline',
				'title'    => 'Auto Inline Fields',
				'fields'   => array(
					array(
						'key'   => 'field_blocks_auto_inline_text',
						'name'  => 'auto_inline_text',
						'label' => 'Text',
						'type'  => 'text',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/auto-inline-block',
						),
					),
				),
			)
		);
		$this->group_keys[] = 'group_blocks_auto_inline';
		$this->field_keys[] = 'field_blocks_auto_inline_text';

		$attributes = array(
			'id'   => 'block_aiepreview',
			'name' => 'acf/auto-inline-block',
			'data' => array(
				'auto_inline_text'  => 'Editable Headline',
				'_auto_inline_text' => 'field_blocks_auto_inline_text',
			),
			'mode' => 'preview',
		);

		$html                   = acf_rendered_block( $attributes, '', true, 0 );
		$this->meta_block_ids[] = 'block_aiepreview';

		$this->assertStringContainsString( 'Editable Headline', $html );
		$this->assertStringContainsString( 'data-acf-inline-contenteditable-field-slug="auto_inline_text"', $html );
		$this->assertStringContainsString( 'data-acf-inline-contenteditable', $html );

		// A front-end render of the same block applies no editing attributes.
		acf_get_store( 'block-cache' )->reset();
		acf_get_store( 'values' )->reset();
		$front = acf_rendered_block( $attributes, '', false, 0 );
		$this->assertStringContainsString( 'Editable Headline', $front );
		$this->assertStringNotContainsString( 'data-acf-inline-contenteditable', $front );
	}
}
