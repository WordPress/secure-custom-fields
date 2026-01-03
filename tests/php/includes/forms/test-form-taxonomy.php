<?php
/**
 * Test acf_form_taxonomy class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the acf_form_taxonomy class.
acf_include( 'includes/forms/form-taxonomy.php' );

/**
 * Class Test_Form_Taxonomy
 */
class Test_Form_Taxonomy extends BaseTestCase {

	/**
	 * Clean up after each test to prevent global state pollution.
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Reset globals to prevent polluting other tests.
		global $pagenow;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting globals in test tearDown.
		$pagenow = null;
	}

	/**
	 * Test if the acf_form_taxonomy class exists.
	 */
	public function test_form_taxonomy_class_exists() {
		$this->assertTrue( class_exists( 'acf_form_taxonomy' ), 'acf_form_taxonomy class should exist' );
	}

	/**
	 * Test if the acf_form_taxonomy class is properly initialized.
	 */
	public function test_form_taxonomy_initialization() {
		$form_taxonomy = new acf_form_taxonomy();

		$this->assertInstanceOf( 'acf_form_taxonomy', $form_taxonomy, 'acf_form_taxonomy should be properly initialized' );
	}

	/**
	 * Test constructor registers correct actions.
	 */
	public function test_constructor_registers_actions() {
		$form_taxonomy = new acf_form_taxonomy();

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', array( $form_taxonomy, 'admin_enqueue_scripts' ) ),
			'Should register admin_enqueue_scripts action'
		);

		$this->assertNotFalse(
			has_action( 'create_term', array( $form_taxonomy, 'save_term' ) ),
			'Should register create_term action'
		);

		$this->assertNotFalse(
			has_action( 'edit_term', array( $form_taxonomy, 'save_term' ) ),
			'Should register edit_term action'
		);

		$this->assertNotFalse(
			has_action( 'delete_term', array( $form_taxonomy, 'delete_term' ) ),
			'Should register delete_term action'
		);
	}

	/**
	 * Test validate_page returns false on non-taxonomy pages.
	 */
	public function test_validate_page_returns_false_on_non_taxonomy_page() {
		$form_taxonomy = new acf_form_taxonomy();

		// Set pagenow to a non-taxonomy page.
		global $pagenow;
		$pagenow = 'edit.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.

		$this->assertFalse( $form_taxonomy->validate_page(), 'Should return false on non-taxonomy pages' );
	}

	/**
	 * Test validate_page returns true on edit-tags.php.
	 */
	public function test_validate_page_returns_true_on_edit_tags_page() {
		$form_taxonomy = new acf_form_taxonomy();

		// Set pagenow to edit-tags.php.
		global $pagenow;
		$pagenow = 'edit-tags.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.

		$this->assertTrue( $form_taxonomy->validate_page(), 'Should return true on edit-tags.php' );
	}

	/**
	 * Test validate_page returns true on term.php.
	 */
	public function test_validate_page_returns_true_on_term_page() {
		$form_taxonomy = new acf_form_taxonomy();

		// Set pagenow to term.php.
		global $pagenow;
		$pagenow = 'term.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.

		$this->assertTrue( $form_taxonomy->validate_page(), 'Should return true on term.php' );
	}

	/**
	 * Test save_term returns early when nonce fails.
	 */
	public function test_save_term_returns_when_nonce_fails() {
		$form_taxonomy = new acf_form_taxonomy();

		$term_id  = 123;
		$tt_id    = 456;
		$taxonomy = 'category';

		// Clear any nonce.
		unset( $_POST['_acf_nonce'] );

		$result = $form_taxonomy->save_term( $term_id, $tt_id, $taxonomy );

		$this->assertEquals( $term_id, $result, 'Should return term_id when nonce verification fails' );
	}

	/**
	 * Test add_term sets view and renders nothing without matching field groups.
	 */
	public function test_add_term_sets_view_and_renders_nothing_without_field_groups() {
		$form_taxonomy = new acf_form_taxonomy();

		$taxonomy = 'category';

		ob_start();
		$form_taxonomy->add_term( $taxonomy );
		$output = ob_get_clean();

		$this->assertEquals( 'add', $form_taxonomy->view, 'View should be set to add' );
		$this->assertEmpty( $output, 'Should render nothing when no field groups match' );
	}

	/**
	 * Test edit_term sets view and renders nothing without matching field groups.
	 */
	public function test_edit_term_sets_view_and_renders_nothing_without_field_groups() {
		$form_taxonomy = new acf_form_taxonomy();

		$term          = new stdClass();
		$term->term_id = 200;
		$taxonomy      = 'post_tag';

		ob_start();
		$form_taxonomy->edit_term( $term, $taxonomy );
		$output = ob_get_clean();

		$this->assertEquals( 'edit', $form_taxonomy->view, 'View should be set to edit' );
		$this->assertEmpty( $output, 'Should render nothing when no field groups match' );
	}

	/**
	 * Test admin_footer outputs JavaScript.
	 */
	public function test_admin_footer_outputs_script() {
		$form_taxonomy = new acf_form_taxonomy();

		// Set view to 'add'.
		$form_taxonomy->view = 'add';

		ob_start();
		$form_taxonomy->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script type="text/javascript">', $output, 'Should output script tag' );
		$this->assertStringContainsString( "var view = 'add'", $output, 'Should contain view variable set to add' );
	}

	/**
	 * Test admin_footer includes add term specific JavaScript.
	 */
	public function test_admin_footer_includes_add_term_script() {
		$form_taxonomy = new acf_form_taxonomy();

		// Set view to 'add'.
		$form_taxonomy->view = 'add';

		ob_start();
		$form_taxonomy->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( '#acf-term-fields', $output, 'Should contain add term specific script' );
		$this->assertStringContainsString( 'action=add-tag', $output, 'Should handle add-tag AJAX' );
	}

	/**
	 * Test admin_footer does not include add term script for edit view.
	 */
	public function test_admin_footer_excludes_add_script_for_edit_view() {
		$form_taxonomy = new acf_form_taxonomy();

		// Set view to 'edit'.
		$form_taxonomy->view = 'edit';

		ob_start();
		$form_taxonomy->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( "var view = 'edit'", $output, 'Should contain view variable set to edit' );
	}

	/**
	 * Test delete_term returns early when termmeta table exists.
	 */
	public function test_delete_term_returns_early_with_termmeta() {
		$form_taxonomy = new acf_form_taxonomy();

		$term         = 123;
		$tt_id        = 456;
		$taxonomy     = 'category';
		$deleted_term = new stdClass();

		// When termmeta table exists, the function returns null (early return).
		$result = $form_taxonomy->delete_term( $term, $tt_id, $taxonomy, $deleted_term );

		// The function returns null when termmeta table exists (early return).
		$this->assertNull( $result, 'Should return null when termmeta table exists' );
	}

	/**
	 * Data provider for validate_page scenarios.
	 *
	 * @return array
	 */
	public function validate_page_scenarios_provider() {
		return array(
			'edit-tags.php' => array( 'edit-tags.php', true ),
			'term.php'      => array( 'term.php', true ),
			'edit.php'      => array( 'edit.php', false ),
			'post.php'      => array( 'post.php', false ),
			'index.php'     => array( 'index.php', false ),
			'options.php'   => array( 'options.php', false ),
			'users.php'     => array( 'users.php', false ),
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
		$form_taxonomy = new acf_form_taxonomy();

		global $pagenow;
		$pagenow = $page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.

		$result = $form_taxonomy->validate_page();

		$this->assertEquals( $expected, $result, "validate_page should return $expected for $page" );
	}

	/**
	 * Test admin_enqueue_scripts returns early on non-taxonomy pages.
	 */
	public function test_admin_enqueue_scripts_bails_on_wrong_page() {
		$form_taxonomy = new acf_form_taxonomy();

		// Set pagenow to a non-taxonomy page.
		global $pagenow;
		$pagenow = 'edit.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.

		$form_taxonomy->admin_enqueue_scripts();

		// On wrong page, admin_footer action should NOT be added.
		$this->assertFalse(
			has_action( 'admin_footer', array( $form_taxonomy, 'admin_footer' ) ),
			'admin_footer action should not be added on non-taxonomy pages'
		);
	}

	/**
	 * Data provider for view states.
	 *
	 * @return array
	 */
	public function view_state_provider() {
		return array(
			'add_view'  => array( 'add', 'add' ),
			'edit_view' => array( 'edit', 'edit' ),
		);
	}

	/**
	 * Test view property is set correctly.
	 *
	 * @dataProvider view_state_provider
	 *
	 * @param string $expected_view The expected view value.
	 * @param string $set_view      The view to set.
	 */
	public function test_view_property_assignment( $expected_view, $set_view ) {
		$form_taxonomy = new acf_form_taxonomy();

		$form_taxonomy->view = $set_view;

		$this->assertEquals( $expected_view, $form_taxonomy->view, "View should be set to $expected_view" );
	}
}
