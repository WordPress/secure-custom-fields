<?php
/**
 * Tests for functions in acf-post-functions.php and post title helpers.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_ACF_Post_Functions
 *
 * Tests post template discovery and the acf_get_post_title() helper used
 * to format post results (status suffix, hierarchy prefix, empty titles).
 *
 * @covers ::acf_get_post_templates
 * @covers ::acf_get_post_title
 */
class Test_ACF_Post_Functions extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Reset the post_templates cache between tests.
		acf_set_data( 'post_templates', null );
		$this->reset_current_screen();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		acf_set_data( 'post_templates', null );
		$this->reset_current_screen();

		parent::tear_down();
	}

	/**
	 * Reset WordPress' current screen so title formatting is tested outside
	 * admin context unless a test explicitly sets a screen.
	 */
	private function reset_current_screen() {
		global $current_screen, $typenow, $taxnow;

		$current_screen = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting globals in tests.
		$typenow        = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting globals in tests.
		$taxnow         = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting globals in tests.
	}

	// =========================================================================
	// acf_get_post_templates()
	// =========================================================================

	/**
	 * Test that templates include a default empty entry for pages.
	 */
	public function test_acf_get_post_templates_includes_page_placeholder() {
		$templates = acf_get_post_templates();

		$this->assertIsArray( $templates );
		$this->assertArrayHasKey( 'page', $templates );
		$this->assertIsArray( $templates['page'] );
	}

	/**
	 * Test that results are cached in the ACF data store.
	 */
	public function test_acf_get_post_templates_caches_result() {
		acf_get_post_templates();

		$this->assertNotNull( acf_get_data( 'post_templates' ), 'Templates should be stored after the first call' );
	}

	/**
	 * Test that a cached value short-circuits template discovery.
	 */
	public function test_acf_get_post_templates_returns_cached_value() {
		$cached = array(
			'page' => array( 'custom-template.php' => 'Custom Template' ),
		);

		acf_set_data( 'post_templates', $cached );

		$this->assertSame( $cached, acf_get_post_templates() );
	}

	// =========================================================================
	// acf_get_post_title()
	// =========================================================================

	/**
	 * Test that a missing post returns an empty string.
	 */
	public function test_acf_get_post_title_returns_empty_for_missing_post() {
		$this->assertSame( '', acf_get_post_title( 999999 ) );
	}

	/**
	 * Test that a published post title has no suffix.
	 */
	public function test_acf_get_post_title_published_post() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Published Post',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'Published Post', acf_get_post_title( $post_id ) );
	}

	/**
	 * Test that non-publish statuses are appended to the title.
	 *
	 * @dataProvider data_provider_post_statuses
	 *
	 * @param string $status   The post status.
	 * @param string $expected The expected title.
	 */
	public function test_acf_get_post_title_appends_status( $status, $expected ) {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Status Post',
				'post_type'   => 'post',
				'post_status' => $status,
			)
		);

		$this->assertSame( $expected, acf_get_post_title( $post_id ) );
	}

	/**
	 * Data provider for post status suffixes.
	 *
	 * @return array
	 */
	public function data_provider_post_statuses() {
		return array(
			'draft'   => array( 'draft', 'Status Post (draft)' ),
			'pending' => array( 'pending', 'Status Post (pending)' ),
			'private' => array( 'private', 'Private: Status Post (private)' ),
		);
	}

	/**
	 * Test that an empty title falls back to a placeholder.
	 */
	public function test_acf_get_post_title_empty_title_placeholder() {
		$post_id = wp_insert_post(
			array(
				'post_title'   => '',
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => 'Content only.',
			)
		);

		$this->assertSame( '(no title)', acf_get_post_title( $post_id ) );
	}

	/**
	 * Test that hierarchical posts are prefixed per ancestor level.
	 */
	public function test_acf_get_post_title_prefixes_ancestors() {
		$parent_id = wp_insert_post(
			array(
				'post_title'  => 'Parent Page',
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$child_id = wp_insert_post(
			array(
				'post_title'  => 'Child Page',
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => $parent_id,
			)
		);

		$grandchild_id = wp_insert_post(
			array(
				'post_title'  => 'Grandchild Page',
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => $child_id,
			)
		);

		$this->assertSame( 'Parent Page', acf_get_post_title( $parent_id ) );
		$this->assertSame( '- Child Page', acf_get_post_title( $child_id ) );
		$this->assertSame( '- - Grandchild Page', acf_get_post_title( $grandchild_id ) );
	}

	/**
	 * Test that attachments skip the ancestor prefix.
	 */
	public function test_acf_get_post_title_attachment_skips_ancestors() {
		$parent_id = wp_insert_post(
			array(
				'post_title'  => 'Attachment Parent',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$attachment_id = wp_insert_post(
			array(
				'post_title'  => 'My Attachment',
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_parent' => $parent_id,
			)
		);

		$title = acf_get_post_title( $attachment_id );

		// Attachments inherit the parent status (publish), so no status suffix
		// is added, and the ancestor prefix is skipped for attachments.
		$this->assertSame( 'My Attachment', $title );
	}

	/**
	 * Test that a WP_Post object is accepted as input.
	 */
	public function test_acf_get_post_title_accepts_post_object() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Object Input Post',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'Object Input Post', acf_get_post_title( get_post( $post_id ) ) );
	}
}
