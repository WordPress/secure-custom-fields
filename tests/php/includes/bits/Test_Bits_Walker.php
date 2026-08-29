<?php
/**
 * Tests for the SCF inline-token (Bit) walker.
 *
 * @package wordpress/secure-custom-fields
 *
 * @covers \SCF\Bits\Walker
 */

use WorDBless\BaseTestCase;
use SCF\Bits\Walker;

/**
 * Tests for the SCF inline-token (Bit) walker.
 *
 * @covers \SCF\Bits\Walker
 */
class Test_Bits_Walker extends BaseTestCase {

	/**
	 * Post ID used as the field target in tests.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Sets up a single test post with two SCF fields.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		$this->post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Bits Walker Test',
				'post_status' => 'publish',
			)
		);

		acf_init();

		acf_add_local_field_group(
			array(
				'key'    => 'group_bits_walker',
				'title'  => 'Bits Walker Fields',
				'fields' => array(
					array(
						'key'   => 'field_bits_text',
						'name'  => 'bits_text',
						'label' => 'Bits Text',
						'type'  => 'text',
					),
					array(
						'key'          => 'field_bits_wysiwyg',
						'name'         => 'bits_wysiwyg',
						'label'        => 'Bits WYSIWYG',
						'type'         => 'wysiwyg',
						'toolbar'      => 'basic',
						'media_upload' => 0,
					),
				),
			)
		);

		update_field( 'bits_text', 'Plain Text Value', $this->post_id );
		update_field( 'bits_wysiwyg', '<strong>Bold</strong> value', $this->post_id );
	}

	/**
	 * Sanity-check the early-exit substring gate.
	 *
	 * @return void
	 */
	public function test_early_exit_when_no_class() {
		$content = '<p>no bit here</p>';
		$this->assertSame( $content, Walker::maybe_render_block( $content, array() ) );
	}

	/**
	 * Verifies text-format bits are substituted with field values.
	 *
	 * @return void
	 */
	public function test_substitutes_text_format_with_field_value() {
		$bit = '<span class="scf-field-bit" data-bit="scf/field" data-key="bits_text" data-target="post:' . $this->post_id . '" data-fallback="DEF">DEF</span>';

		$container = '<p>before ' . $bit . ' after</p>';
		$out       = Walker::maybe_render_block( $container, array() );

		$this->assertStringContainsString( 'Plain Text Value', $out );
		$this->assertStringNotContainsString( $bit, $out );
		$this->assertStringContainsString( 'before', $out );
		$this->assertStringContainsString( 'after', $out );
	}

	/**
	 * Verifies HTML-format bits run field values through `wp_kses_post()`.
	 *
	 * @return void
	 */
	public function test_substitutes_html_format_with_kses_post() {
		$bit = '<span class="scf-field-bit" data-bit="scf/field" data-key="bits_wysiwyg" data-target="post:' . $this->post_id . '" data-format="html" data-fallback="">fallback</span>';

		$container = '<p>' . $bit . '</p>';
		$out       = Walker::maybe_render_block( $container, array() );

		$this->assertStringContainsString( '<strong>Bold</strong>', $out );
	}

	/**
	 * Confirms fallback text shows when the resolved value is empty.
	 *
	 * @return void
	 */
	public function test_fallback_used_when_value_empty() {
		update_field( 'bits_text', '', $this->post_id );

		$bit = '<span class="scf-field-bit" data-bit="scf/field" data-key="bits_text" data-target="post:' . $this->post_id . '" data-fallback="EMPTY">EMPTY</span>';
		$out = Walker::maybe_render_block( '<p>' . $bit . '</p>', array() );

		$this->assertStringContainsString( 'EMPTY', $out );
		$this->assertStringNotContainsString( 'Plain Text Value', $out );
	}

	/**
	 * Confirms an unknown field key degrades safely to the fallback.
	 *
	 * @return void
	 */
	public function test_unknown_key_uses_fallback() {
		$bit = '<span class="scf-field-bit" data-bit="scf/field" data-key="nonexistent" data-target="post:' . $this->post_id . '" data-fallback="NA">NA</span>';
		$out = Walker::maybe_render_block( '<p>' . $bit . '</p>', array() );

		$this->assertStringContainsString( 'NA', $out );
	}

	/**
	 * Confirms unknown bit names fall through with fallback visible.
	 *
	 * @return void
	 */
	public function test_unknown_bit_passes_through() {
		$bit = '<span class="scf-field-bit" data-bit="scf/unknown" data-key="bits_text" data-target="post:' . $this->post_id . '" data-fallback="NOBIT">NOBIT</span>';
		$out = Walker::maybe_render_block( '<p>' . $bit . '</p>', array() );

		$this->assertStringContainsString( 'NOBIT', $out );
	}

	/**
	 * Confirms `format=raw` is rejected when the user lacks `unfiltered_html`.
	 *
	 * @return void
	 */
	public function test_raw_format_blocked_without_unfiltered_html_cap() {
		$user = wp_get_current_user();
		if ( $user && $user->exists() ) {
			$user->caps = array();
			wp_set_current_user( $user->ID );
		}

		$bit = '<span class="scf-field-bit" data-bit="scf/field" data-key="bits_text" data-target="post:' . $this->post_id . '" data-format="raw" data-fallback="RAW">RAW</span>';
		$out = Walker::maybe_render_block( '<p>' . $bit . '</p>', array() );

		$this->assertStringContainsString( 'RAW', $out );
	}

	/**
	 * Confirms HTML-format sanitization strips dangerous tags.
	 *
	 * @return void
	 */
	public function test_html_format_preserves_tag_attributes() {
		// Server should not pass through script tags even if a user
		// somehow managed to insert one into a field value.
		update_field( 'bits_wysiwyg', '<script>alert(1)</script>ok', $this->post_id );

		$bit = '<span class="scf-field-bit" data-bit="scf/field" data-key="bits_wysiwyg" data-target="post:' . $this->post_id . '" data-format="html" data-fallback="">fallback</span>';
		$out = Walker::maybe_render_block( '<p>' . $bit . '</p>', array() );

		$this->assertStringNotContainsString( '<script', $out );
		$this->assertStringContainsString( 'ok', $out );
	}
}
