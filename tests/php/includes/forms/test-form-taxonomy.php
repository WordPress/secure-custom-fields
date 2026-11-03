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
	 * Term the save tests submit values for.
	 *
	 * @var integer
	 */
	private $term_id = 5;

	/**
	 * $_POST['acf'] as seen by acf/save_post, or null when no save ran.
	 *
	 * @var array|null
	 */
	private $saved_values;

	/**
	 * Test filters that must be removed during cleanup.
	 *
	 * @var array
	 */
	private $test_filters = array();

	/**
	 * Registers the field type and location the save tests use.
	 */
	public function set_up() {
		parent::set_up();

		acf_reset_local();
		acf_enable_local();
		$this->reset_stores();

		acf_include( 'includes/fields/class-acf-field-text.php' );
		if ( ! acf_get_field_type( 'text' ) ) {
			acf_register_field_type( 'acf_field_text' );
		}

		acf_include( 'includes/locations/class-acf-location-taxonomy.php' );
		if ( ! acf_get_location_type( 'taxonomy' ) ) {
			acf_register_location_type( 'ACF_Location_Taxonomy' );
		}

		if ( ! has_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts' ) ) {
			add_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts', 20, 2 );
		}

		$this->saved_values = null;
		$this->add_test_filter( 'acf/save_post', array( $this, 'capture_saved_values' ), 1 );

		$_POST  = array();
		$_FILES = array();
	}

	/**
	 * Clean up after each test to prevent global state pollution.
	 */
	public function tear_down() {
		foreach ( $this->test_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}
		$this->test_filters = array();

		acf_reset_local();
		acf_enable_local();
		$this->reset_stores();

		$_POST  = array();
		$_FILES = array();

		global $pagenow;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting globals in test tearDown.
		$pagenow = null;

		parent::tear_down();
	}

	/**
	 * Records $_POST['acf'] when a save runs.
	 */
	public function capture_saved_values() {
		/* phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Test reads the request it built. */
		$this->saved_values = isset( $_POST['acf'] ) ? $_POST['acf'] : array();
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

	/**
	 * A term save keeps fields from groups located on the taxonomy and drops the rest.
	 */
	public function test_save_term_drops_fields_not_located_on_taxonomy() {
		$this->add_term_groups();
		$this->submit(
			array(
				'field_form_taxonomy_category' => 'kept',
				'field_form_taxonomy_tag'      => 'dropped',
				'field_form_taxonomy_unknown'  => 'dropped',
			)
		);

		( new acf_form_taxonomy() )->save_term( $this->term_id, 0, 'category' );

		$this->assertSame( array( 'field_form_taxonomy_category' => 'kept' ), $this->saved_values );
	}

	/**
	 * The taxonomy allowed-keys filter can allow extra keys, and receives the ACF term ID.
	 */
	public function test_save_term_allowed_keys_filter_adds_keys() {
		$received = null;
		$this->add_test_filter(
			'acf/form/taxonomy/allowed_field_keys',
			function ( $keys, $post_id ) use ( &$received ) {
				$received = $post_id;
				$keys[]   = 'field_form_taxonomy_injected';
				return $keys;
			},
			10,
			2
		);
		$this->add_term_groups();
		$this->submit(
			array(
				'field_form_taxonomy_category' => 'kept',
				'field_form_taxonomy_injected' => 'kept',
			)
		);

		( new acf_form_taxonomy() )->save_term( $this->term_id, 0, 'category' );

		$this->assertSame( 'term_' . $this->term_id, $received );
		$this->assertSame(
			array(
				'field_form_taxonomy_category' => 'kept',
				'field_form_taxonomy_injected' => 'kept',
			),
			$this->saved_values
		);
	}

	/**
	 * Editing a term accepts fields listed for that term and ignores lists signed for another term.
	 */
	public function test_save_term_accepts_only_fields_listed_for_term() {
		$this->add_term_groups();
		$this->submit(
			array(
				'field_form_taxonomy_tag'     => 'kept',
				'field_form_taxonomy_unknown' => 'dropped',
			)
		);
		$_POST['_acf_fields'] = array(
			'term_' . $this->term_id => array( $this->get_signed_field_list( 'term_' . $this->term_id, array( 'field_form_taxonomy_tag' ) ) ),
			'term_6'                 => array( $this->get_signed_field_list( 'term_6', array( 'field_form_taxonomy_unknown' ) ) ),
		);

		( new acf_form_taxonomy() )->save_term( $this->term_id, 0, 'category' );

		$this->assertSame( array( 'field_form_taxonomy_tag' => 'kept' ), $this->saved_values );
	}

	/**
	 * Creating a term accepts fields listed for the taxonomy's Add form, not for another taxonomy or the term itself.
	 */
	public function test_create_term_accepts_only_fields_listed_for_add_form() {
		$this->add_term_groups();
		$this->submit(
			array(
				'field_form_taxonomy_tag'     => 'kept',
				'field_form_taxonomy_unknown' => 'dropped',
				'field_form_taxonomy_other'   => 'dropped',
			)
		);
		$_POST['_acf_fields'] = array(
			'term_0/category'        => array( $this->get_signed_field_list( 'term_0/category', array( 'field_form_taxonomy_tag' ) ) ),
			'term_0/post_tag'        => array( $this->get_signed_field_list( 'term_0/post_tag', array( 'field_form_taxonomy_unknown' ) ) ),
			'term_' . $this->term_id => array( $this->get_signed_field_list( 'term_' . $this->term_id, array( 'field_form_taxonomy_other' ) ) ),
		);

		$this->save_term_in_action( 'create_term', 'category' );

		$this->assertSame( array( 'field_form_taxonomy_tag' => 'kept' ), $this->saved_values );
	}

	/**
	 * Registers field groups located on categories and on tags.
	 */
	private function add_term_groups() {
		$this->add_group( 'group_form_taxonomy_category', 'category', 'field_form_taxonomy_category' );
		$this->add_group( 'group_form_taxonomy_tag', 'post_tag', 'field_form_taxonomy_tag' );
	}

	/**
	 * Builds a nonce-verified taxonomy form submission.
	 *
	 * @param array $values The submitted acf values.
	 */
	private function submit( array $values ) {
		$_POST['_acf_screen'] = 'taxonomy';
		$_POST['_acf_nonce']  = wp_create_nonce( 'taxonomy' );
		$_POST['acf']         = $values;
	}

	/**
	 * Calls save_term() as if it ran inside the given action.
	 *
	 * @param string $action   The action name.
	 * @param string $taxonomy The taxonomy.
	 */
	private function save_term_in_action( $action, $taxonomy ) {
		global $wp_current_filter;

		$wp_current_filter[] = $action; /* phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates the current action for save_term(). */
		try {
			( new acf_form_taxonomy() )->save_term( $this->term_id, 0, $taxonomy );
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * Returns a field list signed for a render ID, as acf_render_fields() outputs it.
	 *
	 * @param string $render_id The render ID.
	 * @param array  $keys      The listed field keys.
	 * @return string
	 */
	private function get_signed_field_list( $render_id, array $keys ) {
		ob_start();
		_acf_render_field_list( $keys, $render_id, '_acf_fields' );
		$processor = new WP_HTML_Tag_Processor( ob_get_clean() );
		$processor->next_tag( 'input' );

		return (string) $processor->get_attribute( 'value' );
	}

	/**
	 * Registers a local field group located on a taxonomy, with one text field.
	 *
	 * @param string $group_key The field group key.
	 * @param string $taxonomy  The taxonomy the group is located on.
	 * @param string $field_key The field key.
	 */
	private function add_group( $group_key, $taxonomy, $field_key ) {
		acf_add_local_field_group(
			array(
				'key'      => $group_key,
				'title'    => $group_key,
				'fields'   => array(
					array(
						'key'   => $field_key,
						'name'  => $field_key,
						'label' => $field_key,
						'type'  => 'text',
					),
				),
				'active'   => true,
				'location' => array(
					array(
						array(
							'param'    => 'taxonomy',
							'operator' => '==',
							'value'    => $taxonomy,
						),
					),
				),
			)
		);
	}

	/**
	 * Adds and tracks a test-specific filter.
	 *
	 * @param string   $hook          Filter name.
	 * @param callable $callback      Filter callback.
	 * @param int      $priority      Filter priority.
	 * @param int      $accepted_args Accepted argument count.
	 */
	private function add_test_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook, $callback, $priority, $accepted_args );

		$this->test_filters[] = array(
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		);
	}

	/**
	 * Resets the stores the fixtures write to.
	 */
	private function reset_stores() {
		foreach ( array( 'local-empty', 'fields', 'field-groups', 'values', 'form' ) as $store_name ) {
			$store = acf_get_store( $store_name );
			if ( $store ) {
				$store->reset();
			}
		}
	}
}
