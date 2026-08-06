<?php
/**
 * Tests for includes/admin/class-acf-admin-options-page.php.
 *
 * Covers field-group discovery and the top-level field allowlist used when an
 * Options Page submission is validated and saved.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Test subclass exposes protected helpers.

acf_include( 'includes/admin/class-acf-admin-options-page.php' );

/**
 * Exposes the Options Page save-scope helpers for unit testing.
 */
class Testable_ACF_Admin_Options_Page extends acf_admin_options_page {

	/**
	 * Exposes get_options_page_field_groups().
	 *
	 * @return array
	 */
	public function get_test_field_groups() {
		return $this->get_options_page_field_groups();
	}

	/**
	 * Exposes get_options_page_allowed_field_keys().
	 *
	 * @return array
	 */
	public function get_test_allowed_field_keys() {
		return $this->get_options_page_allowed_field_keys();
	}

	/**
	 * Exposes filter_options_page_field_values().
	 *
	 * @param array $values Submitted ACF values.
	 * @return array
	 */
	public function filter_test_field_values( array $values ): array {
		return $this->filter_options_page_field_values( $values );
	}
}

/**
 * Tests for the administrative Options Page save scope.
 */
class Test_ACF_Admin_Options_Page extends BaseTestCase {

	/**
	 * Menu slug for the lower-capability test page.
	 *
	 * @var string
	 */
	private const LOW_PAGE_SLUG = 'scf-test-low-options';

	/**
	 * Menu slug for the protected test page.
	 *
	 * @var string
	 */
	private const PROTECTED_PAGE_SLUG = 'scf-test-protected-options';

	/**
	 * Options Page instance under test.
	 *
	 * @var Testable_ACF_Admin_Options_Page
	 */
	private $options_page;

	/**
	 * Test filters that must be removed during cleanup.
	 *
	 * @var array
	 */
	private $test_filters = array();

	/**
	 * Sets up isolated local field groups and the required field-type hooks.
	 */
	public function set_up() {
		parent::set_up();

		acf_reset_local();
		acf_enable_local();
		acf_enable_filter( 'clone' );
		acf_reset_validation_errors();

		foreach ( array( 'local-empty', 'fields', 'field-groups', 'values' ) as $store_name ) {
			$store = acf_get_store( $store_name );
			if ( $store ) {
				$store->reset();
			}
		}

		$this->ensure_field_types();
		$this->ensure_options_page_location();

		if ( ! has_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts' ) ) {
			add_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts', 20, 2 );
		}

		$this->options_page       = new Testable_ACF_Admin_Options_Page();
		$this->options_page->page = $this->make_page( self::LOW_PAGE_SLUG );

		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * Removes local fields, request data, filters, and validation state.
	 */
	public function tear_down() {
		foreach ( $this->test_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}

		$this->test_filters = array();

		acf_reset_validation_errors();
		acf_reset_local();
		acf_enable_local();
		acf_enable_filter( 'clone' );

		foreach ( array( 'local-empty', 'fields', 'field-groups', 'values' ) as $store_name ) {
			$store = acf_get_store( $store_name );
			if ( $store ) {
				$store->reset();
			}
		}

		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Foreign required fields are removed before validation, while an allowed
	 * required field continues to produce its normal validation error.
	 */
	public function test_filtered_values_validate_only_required_fields_on_current_page() {
		$this->add_page_group(
			'group_low_required',
			self::LOW_PAGE_SLUG,
			array( $this->make_text_field( 'field_low_required', array( 'required' => 1 ) ) )
		);
		$this->add_page_group(
			'group_protected_required',
			self::PROTECTED_PAGE_SLUG,
			array( $this->make_text_field( 'field_protected_required', array( 'required' => 1 ) ) )
		);

		$_POST['acf'] = $this->options_page->filter_test_field_values(
			array(
				'field_low_required'       => 'low-updated',
				'field_protected_required' => '',
			)
		);

		$this->assertTrue( acf_validate_save_post() );
		$this->assertFalse( acf_get_validation_error( 'acf[field_protected_required]' ) );

		acf_reset_validation_errors();
		$_POST['acf'] = $this->options_page->filter_test_field_values(
			array(
				'field_low_required'       => '',
				'field_protected_required' => 'protected-original',
			)
		);

		$this->assertFalse( acf_validate_save_post() );
		$this->assertIsArray( acf_get_validation_error( 'acf[field_low_required]' ) );
		$this->assertFalse( acf_get_validation_error( 'acf[field_protected_required]' ) );
	}

	/**
	 * All groups assigned to the current page contribute roots to the allowlist.
	 */
	public function test_get_field_groups_and_allowed_keys_include_multiple_current_page_groups() {
		$this->add_page_group(
			'group_low_first',
			self::LOW_PAGE_SLUG,
			array( $this->make_text_field( 'field_low_first' ) ),
			'Low First'
		);
		$this->add_page_group(
			'group_low_second',
			self::LOW_PAGE_SLUG,
			array( $this->make_text_field( 'field_low_second' ) ),
			'Low Second'
		);
		$this->add_page_group(
			'group_protected_multiple',
			self::PROTECTED_PAGE_SLUG,
			array( $this->make_text_field( 'field_protected_multiple' ) )
		);

		$group_keys = wp_list_pluck( $this->options_page->get_test_field_groups(), 'key' );
		$allowed    = $this->options_page->get_test_allowed_field_keys();

		$this->assertSame( array( 'group_low_first', 'group_low_second' ), array_values( $group_keys ) );
		$this->assertSame( array( 'field_low_first', 'field_low_second' ), $allowed );
	}

	/**
	 * Group, repeater, and flexible-content payloads remain intact below their
	 * allowed top-level roots.
	 */
	public function test_filter_field_values_preserves_nested_field_payloads() {
		$this->add_page_group(
			'group_low_nested',
			self::LOW_PAGE_SLUG,
			array(
				array(
					'key'        => 'field_low_group',
					'name'       => 'low_group',
					'label'      => 'Low Group',
					'type'       => 'group',
					'sub_fields' => array(
						$this->make_text_field( 'field_low_group_text' ),
					),
				),
				array(
					'key'           => 'field_low_repeater',
					'name'          => 'low_repeater',
					'label'         => 'Low Repeater',
					'type'          => 'repeater',
					'pagination'    => 1,
					'rows_per_page' => 2,
					'sub_fields'    => array(
						$this->make_text_field( 'field_low_repeater_text' ),
					),
				),
				array(
					'key'     => 'field_low_flexible',
					'name'    => 'low_flexible',
					'label'   => 'Low Flexible',
					'type'    => 'flexible_content',
					'layouts' => array(
						array(
							'key'        => 'layout_low_text',
							'name'       => 'low_text',
							'label'      => 'Low Text',
							'display'    => 'block',
							'sub_fields' => array(
								$this->make_text_field( 'field_low_flexible_text' ),
							),
						),
					),
				),
			)
		);
		$this->add_page_group(
			'group_protected_nested',
			self::PROTECTED_PAGE_SLUG,
			array( $this->make_text_field( 'field_protected_nested' ) )
		);

		$allowed_values = array(
			'field_low_group'    => array(
				'field_low_group_text' => 'group-value',
			),
			'field_low_repeater' => array(
				'row-4' => array(
					'field_low_repeater_text' => 'repeater-value',
				),
				'row-5' => array(
					'field_low_repeater_text' => 'repeater-second-value',
				),
			),
			'field_low_flexible' => array(
				'row-0' => array(
					'acf_fc_layout'           => 'low_text',
					'field_low_flexible_text' => 'flexible-value',
				),
			),
		);

		$this->assertSame(
			$allowed_values,
			$this->options_page->filter_test_field_values(
				$allowed_values + array( 'field_protected_nested' => 'attempted-overwrite' )
			)
		);
	}

	/**
	 * Group clones use their field key as the root, while seamless clones use
	 * the clone key extracted from each expanded field's input prefix.
	 */
	public function test_allowed_keys_discover_group_and_seamless_clone_roots() {
		$this->add_page_group(
			'group_clone_sources',
			self::PROTECTED_PAGE_SLUG,
			array(
				$this->make_text_field( 'field_clone_group_source' ),
				$this->make_text_field( 'field_clone_seamless_source' ),
			)
		);
		$this->add_page_group(
			'group_low_clones',
			self::LOW_PAGE_SLUG,
			array(
				array(
					'key'          => 'field_low_clone_group',
					'name'         => 'low_clone_group',
					'label'        => 'Low Clone Group',
					'type'         => 'clone',
					'display'      => 'group',
					'clone'        => array( 'field_clone_group_source' ),
					'prefix_label' => 0,
					'prefix_name'  => 0,
				),
				array(
					'key'          => 'field_low_clone_seamless',
					'name'         => 'low_clone_seamless',
					'label'        => 'Low Clone Seamless',
					'type'         => 'clone',
					'display'      => 'seamless',
					'clone'        => array( 'field_clone_seamless_source' ),
					'prefix_label' => 0,
					'prefix_name'  => 0,
				),
			)
		);

		$allowed = $this->options_page->get_test_allowed_field_keys();

		$this->assertContains( 'field_low_clone_group', $allowed );
		$this->assertContains( 'field_low_clone_seamless', $allowed );
		$this->assertNotContains( 'field_clone_seamless_source', $allowed );
		$this->assertNotContains( 'field_low_clone_seamless_field_clone_seamless_source', $allowed );

		$clone_values = array(
			'field_low_clone_group'    => array(
				'field_clone_group_source' => 'group-clone-value',
			),
			'field_low_clone_seamless' => array(
				'field_clone_seamless_source' => 'seamless-clone-value',
			),
		);

		$this->assertSame(
			$clone_values,
			$this->options_page->filter_test_field_values(
				$clone_values + array( 'field_clone_seamless_source' => 'unscoped-root' )
			)
		);
	}

	/**
	 * A seamless clone of a seamless clone flattens into subfields with a
	 * single-segment input prefix naming the outer clone, which is the
	 * submitted top-level root.
	 */
	public function test_allowed_keys_discover_nested_seamless_clone_root() {
		$this->add_page_group(
			'group_nested_clone_source',
			self::PROTECTED_PAGE_SLUG,
			array( $this->make_text_field( 'field_nested_clone_text' ) )
		);
		$this->add_page_group(
			'group_nested_clone_inner',
			self::PROTECTED_PAGE_SLUG,
			array(
				array(
					'key'          => 'field_nested_inner_clone',
					'name'         => 'nested_inner_clone',
					'label'        => 'Nested Inner Clone',
					'type'         => 'clone',
					'display'      => 'seamless',
					'clone'        => array( 'field_nested_clone_text' ),
					'prefix_label' => 0,
					'prefix_name'  => 0,
				),
			)
		);
		$this->add_page_group(
			'group_low_nested_outer',
			self::LOW_PAGE_SLUG,
			array(
				array(
					'key'          => 'field_low_nested_outer_clone',
					'name'         => 'low_nested_outer_clone',
					'label'        => 'Low Nested Outer Clone',
					'type'         => 'clone',
					'display'      => 'seamless',
					'clone'        => array( 'field_nested_inner_clone' ),
					'prefix_label' => 0,
					'prefix_name'  => 0,
				),
			)
		);

		$this->assertSame(
			array( 'field_low_nested_outer_clone' ),
			$this->options_page->get_test_allowed_field_keys()
		);
	}

	/**
	 * Each page accepts only its own fields when both pages use the same
	 * custom post_id.
	 */
	public function test_shared_custom_post_id_scopes_each_page_to_its_own_fields() {
		$shared_post_id = 'shared-options-protected-save';

		$this->add_page_group(
			'group_low_protected_save',
			self::LOW_PAGE_SLUG,
			array( $this->make_text_field( 'field_low_protected_save' ) )
		);
		$this->add_page_group(
			'group_protected_save',
			self::PROTECTED_PAGE_SLUG,
			array( $this->make_text_field( 'field_protected_save' ) )
		);

		$this->options_page->page = $this->make_page( self::LOW_PAGE_SLUG, $shared_post_id );

		$this->assertSame(
			array( 'field_low_protected_save' => 'low-updated' ),
			$this->options_page->filter_test_field_values(
				array(
					'field_low_protected_save' => 'low-updated',
					'field_protected_save'     => 'attempted-overwrite',
				)
			)
		);

		$this->options_page->page = $this->make_page( self::PROTECTED_PAGE_SLUG, $shared_post_id );

		$this->assertSame(
			array( 'field_protected_save' => 'protected-updated' ),
			$this->options_page->filter_test_field_values(
				array(
					'field_low_protected_save' => 'attempted-overwrite',
					'field_protected_save'     => 'protected-updated',
				)
			)
		);
	}

	/**
	 * Fields added by the standard acf/load_fields loader participate in the
	 * allowlist for the matching page group.
	 */
	public function test_allowed_keys_include_fields_added_by_acf_load_fields() {
		$this->add_page_group(
			'group_low_load_fields',
			self::LOW_PAGE_SLUG,
			array( $this->make_text_field( 'field_low_load_fields' ) )
		);
		acf_add_local_field(
			array(
				'key'    => 'field_low_loader_injected',
				'name'   => 'low_loader_injected',
				'label'  => 'Low Loader Injected',
				'type'   => 'text',
				'parent' => 'group_loader_source',
			)
		);

		$this->add_test_filter(
			'acf/load_fields',
			static function ( $fields, $field_group ) {
				if ( 'group_low_load_fields' === $field_group['key'] ) {
					$fields[] = acf_get_field( 'field_low_loader_injected' );
				}

				return $fields;
			},
			10,
			2
		);

		$this->assertIsArray( acf_get_field( 'field_low_loader_injected' ) );
		$this->assertSame(
			array( 'field_low_load_fields', 'field_low_loader_injected' ),
			$this->options_page->get_test_allowed_field_keys()
		);
		$this->assertSame(
			array( 'field_low_loader_injected' => 'injected-value' ),
			$this->options_page->filter_test_field_values(
				array( 'field_low_loader_injected' => 'injected-value' )
			)
		);
	}

	/**
	 * Discovering saveable keys does not invoke render-time field filters.
	 */
	public function test_allowed_keys_do_not_run_pre_render_fields() {
		$this->add_page_group(
			'group_low_without_pre_render',
			self::LOW_PAGE_SLUG,
			array( $this->make_text_field( 'field_low_without_pre_render' ) )
		);

		$pre_render_calls = 0;
		$this->add_test_filter(
			'acf/pre_render_fields',
			static function ( $fields ) use ( &$pre_render_calls ) {
				++$pre_render_calls;
				return $fields;
			}
		);

		$this->assertSame(
			array( 'field_low_without_pre_render' ),
			$this->options_page->get_test_allowed_field_keys()
		);
		$this->assertSame( 0, $pre_render_calls );
	}

	/**
	 * Ensures field types needed by these fixtures have active hooks.
	 */
	private function ensure_field_types() {
		$field_types = array(
			'text'             => 'acf_field_text',
			'group'            => 'acf_field__group',
			'repeater'         => 'acf_field_repeater',
			'flexible_content' => 'acf_field_flexible_content',
			'clone'            => 'acf_field_clone',
		);

		acf_include( 'includes/fields/class-acf-field-text.php' );
		acf_include( 'includes/fields/class-acf-field-group.php' );
		acf_include( 'includes/fields/class-acf-repeater-table.php' );
		acf_include( 'includes/fields/class-acf-field-repeater.php' );
		acf_include( 'includes/fields/class-acf-field-flexible-content.php' );
		acf_include( 'includes/fields/class-acf-field-clone.php' );

		foreach ( $field_types as $type => $class_name ) {
			$instance = acf_get_field_type( $type );

			if (
				! $instance ||
				! has_filter( "acf/load_field/type={$type}", array( $instance, 'load_field' ) ) ||
				( 'clone' === $type && ! has_filter( 'acf/get_fields', array( $instance, 'acf_get_fields' ) ) )
			) {
				acf_register_field_type( $class_name );
			}
		}
	}

	/**
	 * Ensures the Options Page location type can evaluate local group rules.
	 */
	private function ensure_options_page_location() {
		acf_include( 'includes/locations/class-acf-location-options-page.php' );

		if ( ! acf_get_location_type( 'options_page' ) ) {
			acf_register_location_type( 'ACF_Location_Options_Page' );
		}
	}

	/**
	 * Builds canonical page data for the class under test.
	 *
	 * @param string $menu_slug Page menu slug.
	 * @param string $post_id   Page storage identifier.
	 * @return array
	 */
	private function make_page( $menu_slug, $post_id = 'options' ) {
		return array(
			'menu_slug'  => $menu_slug,
			'post_id'    => $post_id,
			'capability' => self::PROTECTED_PAGE_SLUG === $menu_slug ? 'manage_options' : 'edit_posts',
		);
	}

	/**
	 * Registers a local group assigned to a specific Options Page.
	 *
	 * @param string $group_key Group key.
	 * @param string $menu_slug Options Page menu slug.
	 * @param array  $fields    Group fields.
	 * @param string $title     Optional group title.
	 */
	private function add_page_group( $group_key, $menu_slug, array $fields, $title = '' ) {
		acf_add_local_field_group(
			array(
				'key'      => $group_key,
				'title'    => $title ? $title : ucwords( str_replace( '_', ' ', $group_key ) ),
				'fields'   => $fields,
				'active'   => true,
				'location' => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => $menu_slug,
						),
					),
				),
			)
		);
	}

	/**
	 * Builds a text field definition.
	 *
	 * @param string $key       Field key.
	 * @param array  $overrides Optional field overrides.
	 * @return array
	 */
	private function make_text_field( $key, array $overrides = array() ) {
		$name = preg_replace( '/^field_/', '', $key );

		return array_merge(
			array(
				'key'   => $key,
				'name'  => $name,
				'label' => ucwords( str_replace( '_', ' ', $name ) ),
				'type'  => 'text',
			),
			$overrides
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
}
