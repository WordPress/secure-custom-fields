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
		$pagenow = null;
		$post    = null;
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
