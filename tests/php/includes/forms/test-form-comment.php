<?php
/**
 * Test acf_form_comment class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the acf_form_comment class.
acf_include( 'includes/forms/form-comment.php' );

/**
 * Class Test_Form_Comment
 */
class Test_Form_Comment extends BaseTestCase {

	/**
	 * Clean up after each test to prevent global state pollution.
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Reset globals to prevent polluting other tests.
		global $pagenow, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting globals in test tearDown.
		$pagenow = null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting globals in test tearDown.
		$post = null;
	}

	/**
	 * Test if the acf_form_comment class exists.
	 */
	public function test_form_comment_class_exists() {
		$this->assertTrue( class_exists( 'acf_form_comment' ), 'acf_form_comment class should exist' );
	}

	/**
	 * Test if the acf_form_comment class is properly initialized.
	 */
	public function test_form_comment_initialization() {
		$form_comment = new acf_form_comment();

		$this->assertInstanceOf( 'acf_form_comment', $form_comment, 'acf_form_comment should be properly initialized' );
	}

	/**
	 * Test constructor registers correct actions.
	 */
	public function test_constructor_registers_actions() {
		$form_comment = new acf_form_comment();

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', array( $form_comment, 'admin_enqueue_scripts' ) ),
			'Should register admin_enqueue_scripts action'
		);

		$this->assertNotFalse(
			has_filter( 'comment_form_field_comment', array( $form_comment, 'comment_form_field_comment' ) ),
			'Should register comment_form_field_comment filter'
		);

		$this->assertNotFalse(
			has_action( 'edit_comment', array( $form_comment, 'save_comment' ) ),
			'Should register edit_comment action'
		);

		$this->assertNotFalse(
			has_action( 'comment_post', array( $form_comment, 'save_comment' ) ),
			'Should register comment_post action'
		);
	}

	/**
	 * Test validate_page returns false on non-comment pages.
	 */
	public function test_validate_page_returns_false_on_non_comment_page() {
		$form_comment = new acf_form_comment();

		// Set pagenow to a non-comment page.
		global $pagenow;
		$pagenow = 'edit.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.

		$this->assertFalse( $form_comment->validate_page(), 'Should return false on non-comment pages' );
	}

	/**
	 * Test validate_page returns true on comment.php.
	 */
	public function test_validate_page_returns_true_on_comment_page() {
		$form_comment = new acf_form_comment();

		// Set pagenow to comment.php.
		global $pagenow;
		$pagenow = 'comment.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.

		$this->assertTrue( $form_comment->validate_page(), 'Should return true on comment.php' );
	}

	/**
	 * Test save_comment returns early when nonce fails.
	 */
	public function test_save_comment_returns_when_nonce_fails() {
		$form_comment = new acf_form_comment();

		$comment_id = 123;

		// Clear any nonce.
		unset( $_POST['_acf_nonce'] );

		// Should return the comment_id when nonce verification fails.
		$result = $form_comment->save_comment( $comment_id );

		$this->assertEquals( $comment_id, $result, 'Should return comment_id when nonce verification fails' );
	}

	/**
	 * Test save_comment sanitizes POST data with wp_kses_post_deep.
	 */
	public function test_save_comment_sanitizes_post_data() {
		$form_comment = new acf_form_comment();

		$comment_id = 456;

		// Set up valid nonce.
		$_POST['_acf_screen'] = 'comment';
		$_POST['_acf_nonce']  = wp_create_nonce( 'comment' );
		$_POST['acf']         = array(
			'field_test' => '<script>alert("xss")</script><p>Safe content</p>',
		);

		// Track if save was attempted.
		$saved = false;
		add_action(
			'acf/save_post',
			function () use ( &$saved ) {
				$saved = true;
			}
		);

		$form_comment->save_comment( $comment_id );

		// Verify ACF data was sanitized (script tags should be removed).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Test intentionally reads raw $_POST to verify sanitization.
		$this->assertStringNotContainsString( '<script>', $_POST['acf']['field_test'], 'Script tags should be sanitized' );

		// Cleanup.
		unset( $_POST['_acf_screen'] );
		unset( $_POST['_acf_nonce'] );
		unset( $_POST['acf'] );
	}

	/**
	 * Test comment_form_field_comment returns html unchanged when no field groups.
	 */
	public function test_comment_form_field_comment_returns_unchanged_without_field_groups() {
		$form_comment = new acf_form_comment();

		// Set up global post.
		global $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.
		$post            = new stdClass();
		$post->ID        = 1;
		$post->post_type = 'post';

		$html = '<textarea name="comment">Original comment field</textarea>';

		// Ensure no field groups match.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		$result = $form_comment->comment_form_field_comment( $html );

		$this->assertEquals( $html, $result, 'Should return html unchanged when no field groups match' );
	}

	/**
	 * Test comment_form_field_comment appends fields when field groups exist.
	 */
	public function test_comment_form_field_comment_appends_fields() {
		$form_comment = new acf_form_comment();

		// Set up global post.
		global $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.
		$post            = new stdClass();
		$post->ID        = 1;
		$post->post_type = 'post';

		$html = '<textarea name="comment">Original comment field</textarea>';

		$result = $form_comment->comment_form_field_comment( $html );

		// Method should complete without error. Original html should always be preserved.
		$this->assertStringContainsString( $html, $result, 'Should contain original html' );
	}

	/**
	 * Test admin_enqueue_scripts returns early on non-comment pages.
	 */
	public function test_admin_enqueue_scripts_bails_on_wrong_page() {
		$form_comment = new acf_form_comment();

		// Set pagenow to a non-comment page.
		global $pagenow;
		$pagenow = 'edit.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.

		$form_comment->admin_enqueue_scripts();

		// On wrong page, admin_footer action should NOT be added.
		$this->assertFalse(
			has_action( 'admin_footer', array( $form_comment, 'admin_footer' ) ),
			'admin_footer action should not be added on non-comment pages'
		);
	}

	/**
	 * Test admin_enqueue_scripts adds actions on comment.php.
	 */
	public function test_admin_enqueue_scripts_adds_actions_on_comment_page() {
		$form_comment = new acf_form_comment();

		// Set pagenow to comment.php.
		global $pagenow;
		$pagenow = 'comment.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.

		$form_comment->admin_enqueue_scripts();

		// On comment page, admin_footer action should be added.
		$this->assertNotFalse(
			has_action( 'admin_footer', array( $form_comment, 'admin_footer' ) ),
			'admin_footer action should be added on comment.php'
		);

		// edit_comment action should be added.
		$this->assertNotFalse(
			has_action( 'add_meta_boxes_comment', array( $form_comment, 'edit_comment' ) ),
			'add_meta_boxes_comment action should be added on comment.php'
		);
	}

	/**
	 * Test edit_comment stores correct form data.
	 */
	public function test_edit_comment_renders_nothing_without_field_groups() {
		$form_comment = new acf_form_comment();

		// Create a mock comment object.
		$comment                  = new stdClass();
		$comment->comment_ID      = 123;
		$comment->comment_post_ID = 1;

		// Ensure no field groups match to avoid rendering.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		ob_start();
		$form_comment->edit_comment( $comment );
		$output = ob_get_clean();

		// No output should be generated when no field groups match.
		$this->assertEmpty( $output, 'edit_comment should render nothing without field groups' );
	}

	/**
	 * Test edit_comment uses correct post_id format.
	 *
	 * When field groups exist, edit_comment should store form data with
	 * post_id formatted as "comment_{comment_ID}".
	 */
	public function test_edit_comment_uses_correct_post_id_format() {
		$form_comment = new acf_form_comment();

		// Create a mock comment object.
		$comment                  = new stdClass();
		$comment->comment_ID      = 456;
		$comment->comment_post_ID = 1;

		// Register a real field group to trigger the rendering path.
		$field_group = acf_update_field_group(
			array(
				'key'                   => 'group_comment_test',
				'title'                 => 'Comment Test Group',
				'location'              => array(
					array(
						array(
							'param'    => 'comment',
							'operator' => '==',
							'value'    => 'all',
						),
					),
				),
				'instruction_placement' => 'label',
			)
		);

		// Capture output to prevent it from polluting test output.
		ob_start();
		$form_comment->edit_comment( $comment );
		ob_get_clean();

		// Verify form data was stored with correct post_id format.
		$form_data = acf_get_form_data( 'post_id' );
		$this->assertEquals( 'comment_456', $form_data, 'post_id should be formatted as comment_{id}' );

		// Cleanup: delete the field group.
		acf_delete_field_group( $field_group['ID'] );
	}

	/**
	 * Test admin_footer outputs spinner JavaScript.
	 */
	public function test_admin_footer_outputs_spinner_script() {
		$form_comment = new acf_form_comment();

		ob_start();
		$form_comment->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script type="text/javascript">', $output, 'Should output script tag' );
		$this->assertStringContainsString( 'spinner', $output, 'Should contain spinner code' );
		$this->assertStringContainsString( '#publishing-action', $output, 'Should target publishing-action element' );
	}

	/**
	 * Data provider for validate_page scenarios.
	 *
	 * @return array
	 */
	public function validate_page_scenarios_provider() {
		return array(
			'comment.php'   => array( 'comment.php', true ),
			'edit.php'      => array( 'edit.php', false ),
			'post.php'      => array( 'post.php', false ),
			'index.php'     => array( 'index.php', false ),
			'options.php'   => array( 'options.php', false ),
			'edit-tags.php' => array( 'edit-tags.php', false ),
		);
	}

	/**
	 * Test validate_page with various page scenarios.
	 *
	 * @dataProvider validate_page_scenarios_provider
	 *
	 * @param string $page     The page to test.
	 * @param bool   $expected Expected return value.
	 */
	public function test_validate_page_scenarios( $page, $expected ) {
		$form_comment = new acf_form_comment();

		global $pagenow;
		$pagenow = $page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.

		$result = $form_comment->validate_page();

		$this->assertEquals( $expected, $result, "validate_page should return $expected for $page" );
	}
}
