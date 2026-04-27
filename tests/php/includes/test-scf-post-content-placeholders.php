<?php
/**
 * Tests for SCF post-content placeholders.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests SCF_Post_Content_Placeholders.
 */
class Test_SCF_Post_Content_Placeholders extends BaseTestCase {

	/**
	 * The placeholder service instance under test.
	 *
	 * @var SCF_Post_Content_Placeholders
	 */
	private $service;

	/**
	 * The test post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Field keys used by the test field group.
	 *
	 * @var array<string, string>
	 */
	private $field_keys;

	/**
	 * Sets up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		acf_reset_local();
		acf_get_store( 'values' )->reset();

		$this->service = new SCF_Post_Content_Placeholders();
		$this->post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Placeholder Test Post',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);

		$this->field_keys = array(
			'movie_title'  => 'field_movie_title',
			'release_year' => 'field_release_year',
			'secret_note'  => 'field_secret_note',
			'legacy_title' => 'field_legacy_title',
			'body_html'    => 'field_body_html',
			'multi_choice' => 'field_multi_choice',
			'hero_image'   => 'field_hero_image',
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_post_content_placeholders',
				'title'    => 'Post Content Placeholders',
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
				'fields'   => array(
					array(
						'key'               => $this->field_keys['movie_title'],
						'label'             => 'Movie Title',
						'name'              => 'movie_title',
						'type'              => 'text',
						'allow_in_bindings' => 1,
					),
					array(
						'key'               => $this->field_keys['release_year'],
						'label'             => 'Release Year',
						'name'              => 'release_year',
						'type'              => 'number',
						'allow_in_bindings' => 1,
					),
					array(
						'key'               => $this->field_keys['secret_note'],
						'label'             => 'Secret Note',
						'name'              => 'secret_note',
						'type'              => 'text',
						'allow_in_bindings' => 0,
					),
					array(
						'key'   => $this->field_keys['legacy_title'],
						'label' => 'Legacy Title',
						'name'  => 'legacy_title',
						'type'  => 'text',
					),
					array(
						'key'               => $this->field_keys['body_html'],
						'label'             => 'Body HTML',
						'name'              => 'body_html',
						'type'              => 'wysiwyg',
						'allow_in_bindings' => 1,
					),
					array(
						'key'               => $this->field_keys['multi_choice'],
						'label'             => 'Multi Choice',
						'name'              => 'multi_choice',
						'type'              => 'select',
						'multiple'          => 1,
						'choices'           => array(
							'one' => 'One',
							'two' => 'Two',
						),
						'allow_in_bindings' => 1,
					),
					array(
						'key'               => $this->field_keys['hero_image'],
						'label'             => 'Hero Image',
						'name'              => 'hero_image',
						'type'              => 'image',
						'allow_in_bindings' => 1,
					),
				),
			)
		);
	}

	/**
	 * Tears down test fixtures.
	 */
	public function tearDown(): void {
		remove_filter( 'render_block', array( $this->service, 'filter_render_block' ), 10 );
		acf_reset_local();
		acf_get_store( 'values' )->reset();

		if ( $this->post_id ) {
			wp_delete_post( $this->post_id, true );
		}

		parent::tearDown();
	}

	/**
	 * Tests placeholder replacement in paragraph and heading blocks.
	 */
	public function test_replaces_supported_placeholders_in_paragraph_and_heading_blocks() {
		update_field( $this->field_keys['movie_title'], 'The Matrix', $this->post_id );
		update_field( $this->field_keys['release_year'], 1999, $this->post_id );

		$rendered = $this->render_post_content(
			implode(
				"\n\n",
				array(
					'<!-- wp:heading -->',
					'<h2>[[movie_title]]</h2>',
					'<!-- /wp:heading -->',
					'<!-- wp:paragraph -->',
					'<p>[[movie_title]] ([[release_year]]) [[movie_title]]</p>',
					'<!-- /wp:paragraph -->',
				)
			)
		);

		$this->assertStringContainsString( '<h2 class="wp-block-heading">The Matrix</h2>', $rendered );
		$this->assertStringContainsString( '<p>The Matrix (1999) The Matrix</p>', $rendered );
	}

	/**
	 * Tests malformed placeholders remain unchanged.
	 */
	public function test_malformed_placeholders_remain_unchanged() {
		$rendered = $this->render_post_content(
			implode(
				"\n\n",
				array(
					'<!-- wp:paragraph -->',
					'<p>[[movie title]] [[movie_title] [movie_title] [[movie_title|upper]]</p>',
					'<!-- /wp:paragraph -->',
				)
			)
		);

		$this->assertStringContainsString( '[[movie title]] [[movie_title] [movie_title] [[movie_title|upper]]', $rendered );
	}

	/**
	 * Tests missing placeholders render empty output.
	 */
	public function test_missing_placeholder_renders_empty_output() {
		$rendered = $this->render_post_content(
			implode(
				"\n\n",
				array(
					'<!-- wp:paragraph -->',
					'<p>Before [[missing_key]] after</p>',
					'<!-- /wp:paragraph -->',
				)
			)
		);

		$this->assertStringContainsString( '<p>Before  after</p>', $rendered );
	}

	/**
	 * Tests placeholders with denied or unsupported fields render empty output.
	 */
	public function test_denied_or_unsupported_placeholders_render_empty_output() {
		update_field( $this->field_keys['secret_note'], 'Classified', $this->post_id );
		update_field( $this->field_keys['legacy_title'], 'Legacy Value', $this->post_id );
		update_field( $this->field_keys['multi_choice'], array( 'one', 'two' ), $this->post_id );
		update_field( $this->field_keys['hero_image'], 123, $this->post_id );

		$rendered = $this->render_post_content(
			implode(
				"\n\n",
				array(
					'<!-- wp:paragraph -->',
					'<p>[[secret_note]][[legacy_title]][[multi_choice]][[hero_image]]</p>',
					'<!-- /wp:paragraph -->',
				)
			)
		);

		$this->assertStringContainsString( '<p></p>', $rendered );
		$this->assertStringNotContainsString( 'Classified', $rendered );
		$this->assertStringNotContainsString( 'Legacy Value', $rendered );
		$this->assertStringNotContainsString( 'Array', $rendered );
	}

	/**
	 * Tests allowed inline HTML is preserved and disallowed HTML is stripped.
	 */
	public function test_sanitizes_wysiwyg_output_to_inline_allowed_html() {
		update_field( $this->field_keys['body_html'], '<p><strong>Bold</strong> <span class="bad">Span</span></p>', $this->post_id );

		$rendered = $this->render_post_content(
			implode(
				"\n\n",
				array(
					'<!-- wp:heading -->',
					'<h2>[[body_html]]</h2>',
					'<!-- /wp:heading -->',
				)
			)
		);

		$this->assertStringContainsString( '<h2 class="wp-block-heading"><strong>Bold</strong> Span</h2>', $rendered );
		$this->assertStringNotContainsString( '<span', $rendered );
		$this->assertStringNotContainsString( 'class="bad"', $rendered );
	}

	/**
	 * Tests unsupported blocks are left untouched.
	 */
	public function test_unsupported_blocks_are_left_untouched() {
		update_field( $this->field_keys['movie_title'], 'The Matrix', $this->post_id );

		$rendered = $this->render_post_content(
			implode(
				"\n\n",
				array(
					'<!-- wp:list -->',
					'<ul><li>[[movie_title]]</li></ul>',
					'<!-- /wp:list -->',
				)
			)
		);

		$this->assertStringContainsString( '[[movie_title]]', $rendered );
	}

	/**
	 * Tests unsupported runtime contexts are left untouched.
	 */
	public function test_unsupported_runtime_context_is_left_untouched() {
		$block_content = '<p>[[movie_title]]</p>';
		$block         = array(
			'blockName' => 'core/paragraph',
		);

		$this->assertSame( $block_content, $this->service->filter_render_block( $block_content, $block ) );
	}

	/**
	 * Tests saved post content remains unchanged after rendering.
	 */
	public function test_rendering_does_not_mutate_saved_post_content() {
		update_field( $this->field_keys['movie_title'], 'The Matrix', $this->post_id );

		$content = implode(
			"\n\n",
			array(
				'<!-- wp:paragraph -->',
				'<p>[[movie_title]]</p>',
				'<!-- /wp:paragraph -->',
			)
		);

		$this->render_post_content( $content );

		$this->assertSame( $content, get_post_field( 'post_content', $this->post_id ) );
	}

	/**
	 * Renders post content through the supported frontend pipeline.
	 *
	 * @param string $content The content to render.
	 * @return string
	 */
	private function render_post_content( $content ) {
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => $content,
			)
		);

		$this->go_to( get_permalink( $this->post_id ) );

		$GLOBALS['post']           = get_post( $this->post_id );
		$GLOBALS['wp_query']->post = $GLOBALS['post'];
		$GLOBALS['wp_query']->in_the_loop = true;

		setup_postdata( $GLOBALS['post'] );
		$rendered = apply_filters( 'the_content', get_post_field( 'post_content', $this->post_id ) );
		wp_reset_postdata();

		$GLOBALS['wp_query']->in_the_loop = false;

		return $rendered;
	}
}
