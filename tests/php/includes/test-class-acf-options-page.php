<?php
/**
 * Tests for includes/class-acf-options-page.php
 *
 * Covers the legacy acf_options_page class API and its public wrapper
 * functions: registration via acf_add_options_page()/acf_add_options_sub_page(),
 * slug/parent resolution, capability defaults, redirect behavior and
 * updating/reading options pages. Saving and reading field values on options
 * pages ('option' context) is covered elsewhere.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for the acf_options_page class and wrapper functions.
 */
class Test_Class_ACF_Options_Page extends BaseTestCase {

	/**
	 * Snapshot of the registered options pages, restored in tear_down().
	 *
	 * @var array
	 */
	private $pages_backup = array();

	/**
	 * Snapshot of the $_wp_last_utility_menu global.
	 *
	 * @var mixed
	 */
	private $utility_menu_backup;

	/**
	 * Isolates the options page registry and utility menu position.
	 */
	public function set_up() {
		parent::set_up();

		global $_wp_last_utility_menu;
		$this->utility_menu_backup = $_wp_last_utility_menu;
		$_wp_last_utility_menu     = 60; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored in tear_down().

		$this->pages_backup       = acf_options_page()->pages;
		acf_options_page()->pages = array();
	}

	/**
	 * Restores the options page registry and utility menu position.
	 */
	public function tear_down() {
		acf_options_page()->pages = $this->pages_backup;

		global $_wp_last_utility_menu;
		$_wp_last_utility_menu = $this->utility_menu_backup; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the snapshot taken in set_up().

		parent::tear_down();
	}

	// =========================================================================
	// acf_options_page() instance.
	// =========================================================================

	/**
	 * Test acf_options_page() returns a shared acf_options_page instance.
	 */
	public function test_acf_options_page_returns_shared_instance() {
		$instance = acf_options_page();

		$this->assertInstanceOf( 'acf_options_page', $instance, 'Should return an acf_options_page object' );
		$this->assertSame( $instance, acf_options_page(), 'Should return the same instance on subsequent calls' );
	}

	// =========================================================================
	// validate_page() / registration defaults.
	// =========================================================================

	/**
	 * Test an empty argument registers the default Options page.
	 */
	public function test_add_options_page_with_empty_arg_creates_default_page() {
		$page = acf_add_options_page();

		$this->assertIsArray( $page, 'Should return the page settings array' );
		$this->assertSame( 'acf-options', $page['menu_slug'], 'Default page should use the acf-options slug' );
		$this->assertSame( 'Options', $page['page_title'], 'Default page should be titled Options' );
		$this->assertSame( 'Options', $page['menu_title'], 'Default menu title should be Options' );
	}

	/**
	 * Test a string argument is used as the page title.
	 */
	public function test_add_options_page_with_string_arg() {
		$page = acf_add_options_page( 'Simple Page' );

		$this->assertSame( 'Simple Page', $page['page_title'], 'String argument should become the page title' );
		$this->assertSame( 'Simple Page', $page['menu_title'], 'String argument should become the menu title' );
		$this->assertSame( 'acf-options-simple-page', $page['menu_slug'], 'Slug should be generated from the menu title' );
	}

	/**
	 * Test default settings applied to a registered options page.
	 */
	public function test_add_options_page_applies_default_settings() {
		$page = acf_add_options_page(
			array(
				'page_title' => 'Theme Settings',
				'menu_slug'  => 'theme-settings',
			)
		);

		$this->assertSame( 'edit_posts', $page['capability'], 'Default capability should be edit_posts' );
		$this->assertSame( '', $page['parent_slug'], 'Default parent_slug should be empty' );
		$this->assertNull( $page['position'], 'Default position should be null' );
		$this->assertFalse( $page['icon_url'], 'Default icon_url should be false' );
		$this->assertTrue( $page['redirect'], 'Default redirect should be true' );
		$this->assertSame( 'options', $page['post_id'], 'Default post_id should be options' );
		$this->assertFalse( $page['autoload'], 'Default autoload should be false' );
		$this->assertSame( 'Update', $page['update_button'], 'Default update button label should be Update' );
		$this->assertSame( 'Options Updated', $page['updated_message'], 'Default updated message should be Options Updated' );
	}

	/**
	 * Test menu_title falls back to page_title when not provided.
	 */
	public function test_menu_title_defaults_to_page_title() {
		$page = acf_add_options_page(
			array(
				'page_title' => 'Fallback Title',
			)
		);

		$this->assertSame( 'Fallback Title', $page['menu_title'], 'menu_title should fall back to page_title' );
	}

	/**
	 * Test menu_slug is generated from the menu title when not provided.
	 */
	public function test_menu_slug_generated_from_menu_title() {
		$page = acf_add_options_page(
			array(
				'page_title' => 'Ignored',
				'menu_title' => 'My Custom Menu',
			)
		);

		$this->assertSame( 'acf-options-my-custom-menu', $page['menu_slug'], 'Slug should be acf-options- plus the sanitized menu title' );
	}

	/**
	 * Test legacy setting names (title, menu, slug, parent) are migrated.
	 */
	public function test_legacy_setting_names_are_migrated() {
		$page = acf_add_options_page(
			array(
				'title'  => 'Legacy Title',
				'menu'   => 'Legacy Menu',
				'slug'   => 'legacy-slug',
				'parent' => 'legacy-parent',
			)
		);

		$this->assertSame( 'Legacy Title', $page['page_title'], 'title should migrate to page_title' );
		$this->assertSame( 'Legacy Menu', $page['menu_title'], 'menu should migrate to menu_title' );
		$this->assertSame( 'legacy-slug', $page['menu_slug'], 'slug should migrate to menu_slug' );
		$this->assertSame( 'legacy-parent', $page['parent_slug'], 'parent should migrate to parent_slug' );
	}

	/**
	 * Test position is standardized to an int or null.
	 */
	public function test_position_is_cast_to_int_or_null() {
		$numeric = acf_add_options_page(
			array(
				'page_title' => 'Numeric Position',
				'menu_slug'  => 'numeric-position',
				'position'   => '42',
			)
		);
		$this->assertSame( 42, $numeric['position'], 'Numeric string position should be cast to int' );

		$invalid = acf_add_options_page(
			array(
				'page_title' => 'Invalid Position',
				'menu_slug'  => 'invalid-position',
				'position'   => 'top',
			)
		);
		$this->assertNull( $invalid['position'], 'Non-numeric position should become null' );
	}

	/**
	 * Test the acf/validate_options_page filter is applied during validation.
	 */
	public function test_validate_options_page_filter_is_applied() {
		add_filter(
			'acf/validate_options_page',
			function ( $page ) {
				$page['capability'] = 'manage_options';
				return $page;
			}
		);

		$page = acf_add_options_page(
			array(
				'page_title' => 'Filtered Page',
				'menu_slug'  => 'filtered-page',
			)
		);

		$this->assertSame( 'manage_options', $page['capability'], 'The validate filter should be able to modify the page settings' );
	}

	/**
	 * Test adding a page with an existing slug returns false.
	 */
	public function test_add_options_page_returns_false_for_duplicate_slug() {
		acf_add_options_page(
			array(
				'page_title' => 'Original',
				'menu_slug'  => 'duplicate-slug',
			)
		);

		$duplicate = acf_add_options_page(
			array(
				'page_title' => 'Duplicate',
				'menu_slug'  => 'duplicate-slug',
			)
		);

		$this->assertFalse( $duplicate, 'Registering the same slug twice should return false' );
		$this->assertSame( 'Original', acf_get_options_page( 'duplicate-slug' )['page_title'], 'The original registration should be kept' );
	}

	// =========================================================================
	// acf_get_options_page() / acf_get_options_pages().
	// =========================================================================

	/**
	 * Test acf_get_options_page() returns a registered page and false otherwise.
	 */
	public function test_get_options_page_returns_registered_page() {
		acf_add_options_page(
			array(
				'page_title' => 'Lookup Page',
				'menu_slug'  => 'lookup-page',
			)
		);

		$page = acf_get_options_page( 'lookup-page' );

		$this->assertIsArray( $page, 'Should return the registered page' );
		$this->assertSame( 'Lookup Page', $page['page_title'], 'Should return the matching settings' );

		$this->assertFalse( acf_get_options_page( 'missing-page' ), 'Should return false for an unknown slug' );
	}

	/**
	 * Test the acf/get_options_page filter is applied on retrieval.
	 */
	public function test_get_options_page_filter_is_applied() {
		acf_add_options_page(
			array(
				'page_title' => 'Filter Target',
				'menu_slug'  => 'filter-target',
			)
		);

		add_filter(
			'acf/get_options_page',
			function ( $page, $slug ) {
				$page['filtered_slug'] = $slug;
				return $page;
			},
			10,
			2
		);

		$page = acf_get_options_page( 'filter-target' );

		$this->assertSame( 'filter-target', $page['filtered_slug'], 'The get filter should receive the page and slug' );
	}

	/**
	 * Test acf_get_options_pages() returns false when nothing is registered.
	 */
	public function test_get_options_pages_returns_false_when_none_registered() {
		$this->assertFalse( acf_get_options_pages(), 'Should return false with no registered pages' );
	}

	/**
	 * Test parent pages with redirect enabled point their menu slug at the first child.
	 */
	public function test_get_options_pages_redirects_parent_to_first_child() {
		acf_add_options_page(
			array(
				'page_title' => 'Theme Settings',
				'menu_slug'  => 'theme-settings',
			)
		);
		acf_add_options_sub_page(
			array(
				'page_title'  => 'Header',
				'menu_slug'   => 'theme-header',
				'parent_slug' => 'theme-settings',
			)
		);
		acf_add_options_sub_page(
			array(
				'page_title'  => 'Footer',
				'menu_slug'   => 'theme-footer',
				'parent_slug' => 'theme-settings',
			)
		);

		$pages = acf_get_options_pages();

		$this->assertSame( 'theme-header', $pages['theme-settings']['menu_slug'], 'Parent menu_slug should redirect to the first child' );
		$this->assertSame( 'theme-settings', $pages['theme-settings']['_menu_slug'], 'Original parent slug should be preserved in _menu_slug' );
		$this->assertSame( 'theme-header', $pages['theme-header']['parent_slug'], 'First child should become its own parent slug' );
		$this->assertSame( 'theme-header', $pages['theme-footer']['parent_slug'], 'Later children should also point at the first child' );
	}

	/**
	 * Test parent pages with redirect disabled keep their own menu slug.
	 */
	public function test_get_options_pages_respects_redirect_false() {
		acf_add_options_page(
			array(
				'page_title' => 'No Redirect',
				'menu_slug'  => 'no-redirect',
				'redirect'   => false,
			)
		);
		acf_add_options_sub_page(
			array(
				'page_title'  => 'Sub Page',
				'menu_slug'   => 'nr-sub',
				'parent_slug' => 'no-redirect',
			)
		);

		$pages = acf_get_options_pages();

		$this->assertSame( 'no-redirect', $pages['no-redirect']['menu_slug'], 'Parent menu_slug should be unchanged when redirect is disabled' );
		$this->assertArrayNotHasKey( '_menu_slug', $pages['no-redirect'], 'No _menu_slug backup should be created' );
		$this->assertSame( 'no-redirect', $pages['nr-sub']['parent_slug'], 'Child should keep pointing at the parent slug' );
	}

	/**
	 * Test top-level pages without a position get one from the utility menu counter.
	 */
	public function test_get_options_pages_assigns_position_from_utility_menu() {
		global $_wp_last_utility_menu;
		$_wp_last_utility_menu = 60; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored in tear_down().

		acf_add_options_page(
			array(
				'page_title' => 'Positioned',
				'menu_slug'  => 'positioned',
			)
		);

		$pages = acf_get_options_pages();

		$this->assertSame( 61, $pages['positioned']['position'], 'Position should be the incremented utility menu counter' );
		$this->assertSame( 61, $_wp_last_utility_menu, 'The utility menu counter should be advanced' );
	}

	// =========================================================================
	// acf_add_options_sub_page() parent resolution.
	// =========================================================================

	/**
	 * Test a sub page without a parent defaults to (and auto-creates) the default page.
	 */
	public function test_add_options_sub_page_defaults_parent_to_default_options_page() {
		$sub = acf_add_options_sub_page( 'Orphan Sub' );

		$this->assertSame( 'acf-options', $sub['parent_slug'], 'Sub page should default to the acf-options parent' );
		$this->assertSame( 'acf-options-orphan-sub', $sub['menu_slug'], 'Sub page slug should be generated from its title' );

		$default = acf_get_options_page( 'acf-options' );
		$this->assertIsArray( $default, 'The default Options parent page should be auto-created' );
		$this->assertSame( 'Options', $default['page_title'], 'Auto-created parent should use the default Options title' );
	}

	/**
	 * Test a sub page keeps an explicitly provided parent slug.
	 */
	public function test_add_options_sub_page_keeps_explicit_parent() {
		acf_add_options_page(
			array(
				'page_title' => 'Parent Page',
				'menu_slug'  => 'parent-page',
			)
		);

		$sub = acf_add_options_sub_page(
			array(
				'page_title'  => 'Child Page',
				'menu_slug'   => 'child-page',
				'parent_slug' => 'parent-page',
			)
		);

		$this->assertSame( 'parent-page', $sub['parent_slug'], 'Explicit parent slug should be preserved' );
		$this->assertFalse( acf_get_options_page( 'acf-options' ), 'The default page should not be auto-created' );
	}

	/**
	 * Test the legacy register_options_page() alias registers a sub page.
	 */
	public function test_register_options_page_legacy_alias_adds_sub_page() {
		register_options_page( 'Legacy Registered' );

		$page = acf_get_options_page( 'acf-options-legacy-registered' );

		$this->assertIsArray( $page, 'Legacy alias should register the page' );
		$this->assertSame( 'acf-options', $page['parent_slug'], 'Legacy alias registers a sub page of the default page' );
	}

	// =========================================================================
	// acf_update_options_page() and shortcut setters.
	// =========================================================================

	/**
	 * Test acf_update_options_page() merges new settings into an existing page.
	 */
	public function test_update_options_page_merges_settings() {
		acf_add_options_page(
			array(
				'page_title' => 'Updatable',
				'menu_slug'  => 'updatable',
			)
		);

		$updated = acf_update_options_page(
			'updatable',
			array(
				'capability' => 'manage_options',
				'autoload'   => true,
			)
		);

		$this->assertSame( 'manage_options', $updated['capability'], 'Capability should be updated' );
		$this->assertTrue( $updated['autoload'], 'Autoload should be updated' );
		$this->assertSame( 'Updatable', $updated['page_title'], 'Untouched settings should be preserved' );

		$fetched = acf_get_options_page( 'updatable' );
		$this->assertSame( 'manage_options', $fetched['capability'], 'The update should persist in the registry' );
	}

	/**
	 * Test acf_update_options_page() returns false for an unknown slug.
	 */
	public function test_update_options_page_returns_false_for_unknown_slug() {
		$this->assertFalse(
			acf_update_options_page( 'unknown-slug', array( 'capability' => 'manage_options' ) ),
			'Updating an unregistered page should return false'
		);
	}

	/**
	 * Test acf_set_options_page_title() updates the default page titles.
	 */
	public function test_set_options_page_title_updates_default_page() {
		acf_add_options_page();
		acf_set_options_page_title( 'Site Options' );

		$page = acf_get_options_page( 'acf-options' );

		$this->assertSame( 'Site Options', $page['page_title'], 'Page title should be updated' );
		$this->assertSame( 'Site Options', $page['menu_title'], 'Menu title should be updated' );
	}

	/**
	 * Test acf_set_options_page_menu() updates only the default page menu title.
	 */
	public function test_set_options_page_menu_updates_default_page() {
		acf_add_options_page();
		acf_set_options_page_menu( 'Menu Only' );

		$page = acf_get_options_page( 'acf-options' );

		$this->assertSame( 'Menu Only', $page['menu_title'], 'Menu title should be updated' );
		$this->assertSame( 'Options', $page['page_title'], 'Page title should be unchanged' );
	}

	/**
	 * Test acf_set_options_page_capability() updates the default page capability.
	 */
	public function test_set_options_page_capability_updates_default_page() {
		acf_add_options_page();
		acf_set_options_page_capability( 'manage_options' );

		$page = acf_get_options_page( 'acf-options' );

		$this->assertSame( 'manage_options', $page['capability'], 'Capability should be updated' );
	}

	// =========================================================================
	// Interplay between UI-created and code-registered pages.
	// =========================================================================

	/**
	 * Test UI options page settings register through the code API via
	 * ACF_UI_Options_Page::get_options_page_args().
	 */
	public function test_ui_options_page_args_register_via_code_api() {
		$instance = acf_get_instance( 'ACF_UI_Options_Page' );

		// A UI-created options page as stored by the acf-ui-options-page post type.
		$ui_page = wp_parse_args(
			array(
				'key'        => 'ui_options_page_interplay',
				'title'      => 'UI Page',
				'page_title' => 'UI Page',
				'menu_slug'  => 'ui-page',
			),
			$instance->get_settings_array()
		);

		$args = $instance->get_options_page_args( $ui_page );

		$this->assertArrayNotHasKey( 'key', $args, 'UI-specific settings should be stripped from the registration args' );
		$this->assertArrayNotHasKey( 'title', $args, 'UI-specific settings should be stripped from the registration args' );

		$registered = acf_add_options_page( $args );

		$this->assertIsArray( $registered, 'UI page args should register through acf_add_options_page()' );
		$this->assertSame( 'ui-page', $registered['menu_slug'], 'The UI menu slug should be preserved' );
		$this->assertFalse( $registered['redirect'], 'The UI redirect default (false) should override the code default (true)' );

		$fetched = acf_get_options_page( 'ui-page' );
		$this->assertSame( 'UI Page', $fetched['page_title'], 'The UI page should be retrievable via the code API' );

		// A code-registered page can no longer claim the same slug.
		$conflict = acf_add_options_page(
			array(
				'page_title' => 'Code Page',
				'menu_slug'  => 'ui-page',
			)
		);
		$this->assertFalse( $conflict, 'Code registrations should not override an existing UI-registered slug' );
	}
}
