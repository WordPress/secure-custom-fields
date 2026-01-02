<?php
/**
 * Tests for the ACF_Repeater_Table class.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Repeater_Table (repeater table rendering helper).
 */
class Test_ACF_Repeater_Table extends BaseTestCase {

	/**
	 * Get a base repeater field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_repeater_test',
				'name'          => 'test_repeater',
				'type'          => 'repeater',
				'label'         => 'Test Repeater',
				'min'           => 0,
				'max'           => 0,
				'layout'        => 'table',
				'button_label'  => 'Add Row',
				'collapsed'     => '',
				'rows_per_page' => 20,
				'pagination'    => false,
				'prefix'        => 'acf',
				'value'         => array(),
				'sub_fields'    => array(
					array(
						'key'          => 'field_sub_text',
						'name'         => 'sub_text',
						'_name'        => 'sub_text',
						'type'         => 'text',
						'label'        => 'Sub Text',
						'required'     => 0,
						'instructions' => '',
						'wrapper'      => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
					),
				),
			),
			$overrides
		);
	}

	/**
	 * Test constructor disables pagination when parent_repeater is set.
	 *
	 * Tests the logic:
	 * `if ( ! empty( $this->field['parent_repeater'] ) || ! empty( $this->field['parent_layout'] ) ) { $this->field['pagination'] = false; }`
	 */
	public function test_constructor_disables_pagination_for_nested_repeater() {
		$field = $this->get_field(
			array(
				'pagination'      => true,
				'parent_repeater' => 'field_parent',
			)
		);

		$table = new ACF_Repeater_Table( $field );

		// Use reflection to access private property.
		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'field' );
		$prop->setAccessible( true );
		$internal_field = $prop->getValue( $table );

		$this->assertFalse( $internal_field['pagination'] );
	}

	/**
	 * Test constructor disables pagination when parent_layout is set.
	 */
	public function test_constructor_disables_pagination_for_flexible_content_child() {
		$field = $this->get_field(
			array(
				'pagination'    => true,
				'parent_layout' => 'layout_123',
			)
		);

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'field' );
		$prop->setAccessible( true );
		$internal_field = $prop->getValue( $table );

		$this->assertFalse( $internal_field['pagination'] );
	}

	/**
	 * Test constructor sets pagination to false when empty.
	 *
	 * Tests the logic:
	 * `if ( empty( $this->field['pagination'] ) ) { $this->field['pagination'] = false; }`
	 */
	public function test_constructor_defaults_pagination_to_false() {
		$field = $this->get_field();
		unset( $field['pagination'] );

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'field' );
		$prop->setAccessible( true );
		$internal_field = $prop->getValue( $table );

		$this->assertFalse( $internal_field['pagination'] );
	}

	/**
	 * Test setup hides order when max is 1.
	 *
	 * Tests the logic:
	 * `if ( 1 === (int) $this->field['max'] ) { $this->show_order = false; }`
	 */
	public function test_setup_hides_order_when_max_is_one() {
		$field = $this->get_field( array( 'max' => 1 ) );

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'show_order' );
		$prop->setAccessible( true );

		$this->assertFalse( $prop->getValue( $table ) );
	}

	/**
	 * Test setup hides add/remove buttons when max <= min.
	 *
	 * Tests the logic:
	 * `if ( $this->field['max'] <= $this->field['min'] ) { $this->show_remove = false; $this->show_add = false; }`
	 */
	public function test_setup_hides_add_remove_when_max_equals_min() {
		$field = $this->get_field(
			array(
				'min' => 3,
				'max' => 3,
			)
		);

		$table = new ACF_Repeater_Table( $field );

		$reflection  = new ReflectionClass( $table );
		$show_add    = $reflection->getProperty( 'show_add' );
		$show_remove = $reflection->getProperty( 'show_remove' );
		$show_add->setAccessible( true );
		$show_remove->setAccessible( true );

		$this->assertFalse( $show_add->getValue( $table ) );
		$this->assertFalse( $show_remove->getValue( $table ) );
	}

	/**
	 * Test setup hides add/remove buttons when max < min.
	 */
	public function test_setup_hides_add_remove_when_max_less_than_min() {
		$field = $this->get_field(
			array(
				'min' => 5,
				'max' => 3,
			)
		);

		$table = new ACF_Repeater_Table( $field );

		$reflection  = new ReflectionClass( $table );
		$show_add    = $reflection->getProperty( 'show_add' );
		$show_remove = $reflection->getProperty( 'show_remove' );
		$show_add->setAccessible( true );
		$show_remove->setAccessible( true );

		$this->assertFalse( $show_add->getValue( $table ) );
		$this->assertFalse( $show_remove->getValue( $table ) );
	}

	/**
	 * Test setup defaults rows_per_page when empty.
	 *
	 * Tests the logic:
	 * `if ( empty( $this->field['rows_per_page'] ) ) { $this->field['rows_per_page'] = 20; }`
	 */
	public function test_setup_defaults_rows_per_page_when_empty() {
		$field = $this->get_field( array( 'rows_per_page' => '' ) );

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'field' );
		$prop->setAccessible( true );
		$internal_field = $prop->getValue( $table );

		$this->assertEquals( 20, $internal_field['rows_per_page'] );
	}

	/**
	 * Test setup defaults rows_per_page when less than 1.
	 *
	 * Tests the logic:
	 * `if ( (int) $this->field['rows_per_page'] < 1 ) { $this->field['rows_per_page'] = 20; }`
	 */
	public function test_setup_defaults_rows_per_page_when_zero() {
		$field = $this->get_field( array( 'rows_per_page' => 0 ) );

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'field' );
		$prop->setAccessible( true );
		$internal_field = $prop->getValue( $table );

		$this->assertEquals( 20, $internal_field['rows_per_page'] );
	}

	/**
	 * Test setup defaults rows_per_page when negative.
	 */
	public function test_setup_defaults_rows_per_page_when_negative() {
		$field = $this->get_field( array( 'rows_per_page' => -5 ) );

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'field' );
		$prop->setAccessible( true );
		$internal_field = $prop->getValue( $table );

		$this->assertEquals( 20, $internal_field['rows_per_page'] );
	}

	/**
	 * Test setup adds collapsed-target class to collapsed sub_field.
	 *
	 * Tests the logic:
	 * `if ( $sub_field['key'] === $this->field['collapsed'] ) { $sub_field['wrapper']['class'] .= ' -collapsed-target'; }`
	 */
	public function test_setup_adds_collapsed_target_class() {
		$field = $this->get_field( array( 'collapsed' => 'field_sub_text' ) );

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'sub_fields' );
		$prop->setAccessible( true );
		$sub_fields = $prop->getValue( $table );

		$this->assertStringContainsString( '-collapsed-target', $sub_fields[0]['wrapper']['class'] );
	}

	/**
	 * Test prepare_value pads array to min.
	 *
	 * Tests the logic:
	 * `if ( $this->field['min'] ) { $value = array_pad( $value, $this->field['min'], array() ); }`
	 */
	public function test_prepare_value_pads_to_min() {
		$field = $this->get_field(
			array(
				'min'   => 3,
				'value' => array( array( 'field_sub_text' => 'row1' ) ),
			)
		);

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'value' );
		$prop->setAccessible( true );
		$value = $prop->getValue( $table );

		// Should have 3 rows + acfcloneindex.
		$this->assertCount( 4, $value );
		$this->assertArrayHasKey( 'acfcloneindex', $value );
	}

	/**
	 * Test prepare_value slices array to max.
	 *
	 * Tests the logic:
	 * `if ( $this->field['max'] ) { $value = array_slice( $value, 0, $this->field['max'] ); }`
	 */
	public function test_prepare_value_slices_to_max() {
		$field = $this->get_field(
			array(
				'max'   => 2,
				'value' => array(
					array( 'field_sub_text' => 'row1' ),
					array( 'field_sub_text' => 'row2' ),
					array( 'field_sub_text' => 'row3' ),
					array( 'field_sub_text' => 'row4' ),
				),
			)
		);

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'value' );
		$prop->setAccessible( true );
		$value = $prop->getValue( $table );

		// Should have 2 rows + acfcloneindex.
		$this->assertCount( 3, $value );
	}

	/**
	 * Test prepare_value converts non-array to empty array.
	 *
	 * Tests the logic:
	 * `$value = is_array( $this->field['value'] ) ? $this->field['value'] : array();`
	 */
	public function test_prepare_value_converts_non_array() {
		$field = $this->get_field( array( 'value' => 'not an array' ) );

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'value' );
		$prop->setAccessible( true );
		$value = $prop->getValue( $table );

		// Should only have acfcloneindex.
		$this->assertCount( 1, $value );
		$this->assertArrayHasKey( 'acfcloneindex', $value );
	}

	/**
	 * Test that min/max are applied when pagination is disabled.
	 *
	 * This confirms the logic path where pagination is empty and pad/slice occurs.
	 * Compare with test_prepare_value_pads_to_min and test_prepare_value_slices_to_max
	 * which test the individual operations.
	 */
	public function test_prepare_value_applies_min_max_when_not_paginated() {
		$field = $this->get_field(
			array(
				'min'        => 3,
				'max'        => 5,
				'pagination' => false,
				'value'      => array( array( 'field_sub_text' => 'row1' ) ),
			)
		);

		$table = new ACF_Repeater_Table( $field );

		$reflection = new ReflectionClass( $table );
		$prop       = $reflection->getProperty( 'value' );
		$prop->setAccessible( true );
		$value = $prop->getValue( $table );

		// Should have 3 rows (padded to min) + acfcloneindex = 4.
		$this->assertCount( 4, $value );
	}

	/**
	 * Test thead only renders for table layout.
	 *
	 * Tests the early return:
	 * `if ( 'table' !== $this->field['layout'] ) { return; }`
	 */
	public function test_thead_only_renders_for_table_layout() {
		$field = $this->get_field( array( 'layout' => 'block' ) );

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->thead();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test thead renders for table layout.
	 */
	public function test_thead_renders_for_table_layout() {
		$field = $this->get_field( array( 'layout' => 'table' ) );

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->thead();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<thead>', $output );
		$this->assertStringContainsString( 'acf-th', $output );
	}

	/**
	 * Test rows returns array when should_return is true.
	 */
	public function test_rows_returns_array_when_should_return() {
		$field = $this->get_field(
			array(
				'value' => array(
					array( 'field_sub_text' => 'row1' ),
				),
			)
		);

		$table = new ACF_Repeater_Table( $field );

		$result = $table->rows( true );

		$this->assertIsArray( $result );
	}

	/**
	 * Test rows excludes acfcloneindex when should_return is true.
	 *
	 * Tests the logic:
	 * `if ( $should_return && isset( $this->value['acfcloneindex'] ) ) { unset( $this->value['acfcloneindex'] ); }`
	 */
	public function test_rows_excludes_clone_when_returning() {
		$field = $this->get_field(
			array(
				'value' => array(
					array( 'field_sub_text' => 'row1' ),
				),
			)
		);

		$table = new ACF_Repeater_Table( $field );

		$result = $table->rows( true );

		$this->assertArrayNotHasKey( 'acfcloneindex', $result );
	}

	/**
	 * Test row_handle does not render when show_order is false.
	 *
	 * Tests the early return:
	 * `if ( ! $this->show_order ) { return; }`
	 */
	public function test_row_handle_respects_show_order() {
		$field = $this->get_field( array( 'max' => 1 ) ); // max=1 sets show_order to false.

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->row_handle( 0 );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test row_handle renders when show_order is true.
	 */
	public function test_row_handle_renders_when_show_order_true() {
		$field = $this->get_field( array( 'max' => 0 ) ); // max=0 means unlimited, show_order remains true.

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->row_handle( 0 );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'acf-row-handle', $output );
		$this->assertStringContainsString( 'acf-row-number', $output );
	}

	/**
	 * Test row_actions does not render when show_remove is false.
	 *
	 * Tests the early return:
	 * `if ( ! $this->show_remove ) { return; }`
	 */
	public function test_row_actions_respects_show_remove() {
		$field = $this->get_field(
			array(
				'min' => 3,
				'max' => 3,
			)
		); // max=min sets show_remove to false.

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->row_actions();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test row_actions renders add/duplicate/remove buttons.
	 */
	public function test_row_actions_renders_buttons() {
		$field = $this->get_field();

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->row_actions();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'add-row', $output );
		$this->assertStringContainsString( 'duplicate-row', $output );
		$this->assertStringContainsString( 'remove-row', $output );
	}

	/**
	 * Test table_actions does not render when show_add is false.
	 *
	 * Tests the early return:
	 * `if ( ! $this->show_add ) { return; }`
	 */
	public function test_table_actions_respects_show_add() {
		$field = $this->get_field(
			array(
				'min' => 3,
				'max' => 3,
			)
		); // max=min sets show_add to false.

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->table_actions();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test table_actions renders add button with custom label.
	 */
	public function test_table_actions_renders_add_button() {
		$field = $this->get_field( array( 'button_label' => 'Add New Item' ) );

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->table_actions();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Add New Item', $output );
		$this->assertStringContainsString( 'acf-repeater-add-row', $output );
	}

	/**
	 * Test pagination does not render when pagination is disabled.
	 *
	 * Tests the early return:
	 * `if ( empty( $this->field['pagination'] ) ) { return; }`
	 */
	public function test_pagination_respects_pagination_setting() {
		$field = $this->get_field( array( 'pagination' => false ) );

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->pagination();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test row uses correct element for different layouts.
	 *
	 * Tests the layout-specific logic:
	 * - 'row' layout uses div with -left class
	 * - 'block' layout uses div
	 * - 'table' layout uses td
	 */
	public function test_row_uses_correct_element_for_row_layout() {
		$field = $this->get_field( array( 'layout' => 'row' ) );

		$table = new ACF_Repeater_Table( $field );

		$result = $table->row( 0, array(), true );

		$this->assertStringContainsString( 'acf-fields -left', $result );
	}

	/**
	 * Test row uses correct element for block layout.
	 */
	public function test_row_uses_correct_element_for_block_layout() {
		$field = $this->get_field( array( 'layout' => 'block' ) );

		$table = new ACF_Repeater_Table( $field );

		$result = $table->row( 0, array(), true );

		$this->assertStringContainsString( 'class="acf-fields"', $result );
	}

	/**
	 * Test row adds acf-clone class for acfcloneindex.
	 *
	 * Tests the logic:
	 * `if ( 'acfcloneindex' === $i ) { $id = 'acfcloneindex'; $class .= ' acf-clone'; }`
	 */
	public function test_row_adds_clone_class_for_clone_index() {
		$field = $this->get_field();

		$table = new ACF_Repeater_Table( $field );

		$result = $table->row( 'acfcloneindex', array(), true );

		$this->assertStringContainsString( 'acf-clone', $result );
		$this->assertStringContainsString( 'data-id="acfcloneindex"', $result );
	}

	/**
	 * Test render outputs complete table structure.
	 */
	public function test_render_outputs_table_structure() {
		$field = $this->get_field(
			array(
				'value' => array(
					array( 'field_sub_text' => 'row1' ),
				),
			)
		);

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'class="acf-repeater', $output );
		$this->assertStringContainsString( '<table class="acf-table">', $output );
		$this->assertStringContainsString( '<tbody>', $output );
		$this->assertStringContainsString( 'acf-actions', $output );
	}

	/**
	 * Test row_handle includes pagination input when pagination enabled.
	 *
	 * Tests the logic:
	 * `if ( ! empty( $this->field['pagination'] ) ) { ... $input = sprintf(...) ... }`
	 */
	public function test_row_handle_includes_pagination_input() {
		$field = $this->get_field(
			array(
				'pagination' => true,
				'total_rows' => 100,
				'orig_name'  => 'test_repeater',
			)
		);

		// Need to bypass the constructor's pagination disable for non-admin.
		$table = new ACF_Repeater_Table( $field );

		// Use reflection to force pagination on for testing.
		$reflection = new ReflectionClass( $table );
		$field_prop = $reflection->getProperty( 'field' );
		$field_prop->setAccessible( true );
		$internal_field               = $field_prop->getValue( $table );
		$internal_field['pagination'] = true;
		$field_prop->setValue( $table, $internal_field );

		ob_start();
		$table->row_handle( 0 );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'acf-order-input', $output );
	}

	/**
	 * Test thead includes sub_field width styling when set.
	 *
	 * Tests the logic:
	 * `if ( $sub_field['wrapper']['width'] ) { $attrs['data-width'] = ...; $attrs['style'] = ...; }`
	 */
	public function test_thead_includes_width_styling() {
		$field                                      = $this->get_field( array( 'layout' => 'table' ) );
		$field['sub_fields'][0]['wrapper']['width'] = '50';

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->thead();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-width="50"', $output );
		$this->assertStringContainsString( 'width: 50%', $output );
	}

	/**
	 * Test row_handle includes collapse icon when collapsed is set.
	 *
	 * Tests the logic:
	 * `if ( $this->field['collapsed'] ) : ?><a class="acf-icon -collapse...`
	 */
	public function test_row_handle_includes_collapse_icon() {
		$field = $this->get_field( array( 'collapsed' => 'field_sub_text' ) );

		$table = new ACF_Repeater_Table( $field );

		ob_start();
		$table->row_handle( 0 );
		$output = ob_get_clean();

		$this->assertStringContainsString( '-collapse', $output );
		$this->assertStringContainsString( 'collapse-row', $output );
	}
}
